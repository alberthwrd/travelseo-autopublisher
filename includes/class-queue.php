<?php
/**
 * Queue Manager V3 - Background Processing dengan Flow Modern
 *
 * Flow Baru:
 * 1. Research (scrape 5-8 sumber)
 * 2. Summarize (rangkum data jadi 1 dokumen)
 * 3. Write (tulis artikel dari rangkuman)
 * 4. QA (spinning + quality check)
 * 5. Images (suggest gambar)
 * 6. Ready
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher;

class Queue {

    public static function init() {
        add_action( 'tsa_process_job', array( __CLASS__, 'process_job' ), 10, 1 );
        add_action( 'tsa_process_job_cron', array( __CLASS__, 'process_job' ), 10, 1 );
        add_action( 'tsa_process_batch', array( __CLASS__, 'process_batch' ) );
        
        if ( ! wp_next_scheduled( 'tsa_process_batch' ) ) {
            wp_schedule_event( time(), 'every_minute', 'tsa_process_batch' );
        }
        
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __( 'Every Minute', 'travelseo-autopublisher' ),
        );
        $schedules['every_30_seconds'] = array(
            'interval' => 30,
            'display' => __( 'Every 30 Seconds', 'travelseo-autopublisher' ),
        );
        return $schedules;
    }

    public static function schedule_job( $job_id, $delay = 0 ) {
        $timestamp = time() + $delay;
        
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( 
                $timestamp, 
                'tsa_process_job', 
                array( 'job_id' => $job_id ), 
                'travelseo-autopublisher' 
            );
            return true;
        }
        
        wp_schedule_single_event( $timestamp, 'tsa_process_job_cron', array( $job_id ) );
        return true;
    }

    public static function process_job( $job_id ) {
        global $wpdb;
        
        // Increase time limit for long processes
        @set_time_limit( 300 );
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
        
        if ( ! $job ) {
            error_log( "TravelSEO: Job #{$job_id} not found." );
            return;
        }
        
        // Skip if already completed or failed
        if ( in_array( $job->status, array( 'ready', 'pushed', 'failed' ), true ) ) {
            return;
        }
        
        // Load dependencies
        self::load_dependencies();
        
        $settings = json_decode( $job->settings, true ) ?: array();
        $research_pack = json_decode( $job->research_pack, true ) ?: array();
        $draft_pack = json_decode( $job->draft_pack, true ) ?: array();
        
        try {
            switch ( $job->status ) {
                case 'queued':
                    self::stage_research( $job_id, $job->title_input, $settings, $table_jobs );
                    break;
                    
                case 'researching':
                    // Re-fetch research_pack
                    $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
                    $research_pack = json_decode( $job->research_pack, true ) ?: array();
                    self::stage_summarize( $job_id, $research_pack, $settings, $table_jobs );
                    break;
                    
                case 'summarizing':
                    // Re-fetch research_pack with summary
                    $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
                    $research_pack = json_decode( $job->research_pack, true ) ?: array();
                    self::stage_write( $job_id, $research_pack, $settings, $table_jobs );
                    break;
                    
                case 'writing':
                    // Re-fetch draft_pack
                    $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
                    $draft_pack = json_decode( $job->draft_pack, true ) ?: array();
                    self::stage_qa( $job_id, $draft_pack, $settings, $table_jobs );
                    break;
                    
                case 'qa':
                    // Re-fetch draft_pack
                    $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
                    $draft_pack = json_decode( $job->draft_pack, true ) ?: array();
                    self::stage_images( $job_id, $draft_pack, $table_jobs );
                    break;
                    
                case 'images':
                    // Mark as ready
                    $wpdb->update( $table_jobs, array( 'status' => 'ready' ), array( 'id' => $job_id ) );
                    tsa_log_job( $job_id, 'Job completed successfully. Ready for publishing.' );
                    break;
            }
            
        } catch ( \Exception $e ) {
            $wpdb->update( $table_jobs, array( 'status' => 'failed' ), array( 'id' => $job_id ) );
            tsa_log_job( $job_id, 'Error: ' . $e->getMessage() );
            error_log( "TravelSEO: Job #{$job_id} failed - " . $e->getMessage() );
        }
    }

    /**
     * Stage 1: Research - Scrape 5-8 sumber
     */
    private static function stage_research( $job_id, $title, $settings, $table_jobs ) {
        global $wpdb;
        
        tsa_log_job( $job_id, 'Stage 1/5: Research - Memulai scraping sumber...' );
        
        $agent = new Agents\Research_Agent( $job_id, $title, $settings );
        $research_pack = $agent->run();
        
        // Validate research pack
        if ( empty( $research_pack ) || empty( $research_pack['sources'] ) ) {
            // Create minimal research pack if scraping failed
            $research_pack = array(
                'title' => $title,
                'keyword' => $title,
                'sources' => array(),
                'scraped_content' => array(),
                'facts' => array(),
                'status' => 'minimal',
            );
            tsa_log_job( $job_id, 'Research: Menggunakan data minimal karena scraping gagal.' );
        }
        
        $wpdb->update( $table_jobs, array(
            'status' => 'researching',
            'research_pack' => wp_json_encode( $research_pack ),
        ), array( 'id' => $job_id ) );
        
        tsa_log_job( $job_id, 'Research selesai. Melanjutkan ke Summarize...' );
        self::schedule_job( $job_id, 2 );
    }

    /**
     * Stage 2: Summarize - Rangkum semua data jadi 1 dokumen
     */
    private static function stage_summarize( $job_id, $research_pack, $settings, $table_jobs ) {
        global $wpdb;
        
        tsa_log_job( $job_id, 'Stage 2/5: Summarize - Merangkum data research...' );
        
        $agent = new Agents\Summarizer_Agent( $job_id, $research_pack, $settings );
        $research_pack = $agent->run();
        
        $wpdb->update( $table_jobs, array(
            'status' => 'summarizing',
            'research_pack' => wp_json_encode( $research_pack ),
        ), array( 'id' => $job_id ) );
        
        tsa_log_job( $job_id, 'Summarize selesai. Melanjutkan ke Writing...' );
        self::schedule_job( $job_id, 2 );
    }

    /**
     * Stage 3: Write - Tulis artikel dari rangkuman
     */
    private static function stage_write( $job_id, $research_pack, $settings, $table_jobs ) {
        global $wpdb;
        
        tsa_log_job( $job_id, 'Stage 3/5: Write - Menulis artikel...' );
        
        $agent = new Agents\Writer_Agent( $job_id, $research_pack, $settings );
        $draft_pack = $agent->run();
        
        // Validate draft pack
        if ( empty( $draft_pack['content'] ) ) {
            throw new \Exception( 'Writer Agent gagal menghasilkan konten.' );
        }
        
        $wpdb->update( $table_jobs, array(
            'status' => 'writing',
            'draft_pack' => wp_json_encode( $draft_pack ),
        ), array( 'id' => $job_id ) );
        
        tsa_log_job( $job_id, 'Writing selesai. Melanjutkan ke QA...' );
        self::schedule_job( $job_id, 2 );
    }

    /**
     * Stage 4: QA - Spinning + Quality Check
     */
    private static function stage_qa( $job_id, $draft_pack, $settings, $table_jobs ) {
        global $wpdb;
        
        tsa_log_job( $job_id, 'Stage 4/5: QA - Quality assurance dan spinning...' );
        
        $agent = new Agents\QA_Agent( $job_id, $draft_pack, $settings );
        $draft_pack = $agent->run();
        
        $wpdb->update( $table_jobs, array(
            'status' => 'qa',
            'draft_pack' => wp_json_encode( $draft_pack ),
        ), array( 'id' => $job_id ) );
        
        tsa_log_job( $job_id, 'QA selesai. Melanjutkan ke Images...' );
        self::schedule_job( $job_id, 2 );
    }

    /**
     * Stage 5: Images - Suggest gambar
     */
    private static function stage_images( $job_id, $draft_pack, $table_jobs ) {
        global $wpdb;
        
        tsa_log_job( $job_id, 'Stage 5/5: Images - Merencanakan gambar...' );
        
        $agent = new Agents\Image_Agent( $job_id, $draft_pack );
        $draft_pack = $agent->run();
        
        $wpdb->update( $table_jobs, array(
            'status' => 'ready',
            'draft_pack' => wp_json_encode( $draft_pack ),
        ), array( 'id' => $job_id ) );
        
        tsa_log_job( $job_id, 'Semua stage selesai! Artikel siap untuk di-publish.' );
    }

    /**
     * Load all agent dependencies
     */
    private static function load_dependencies() {
        require_once TSA_PLUGIN_DIR . 'includes/helpers.php';
        
        $agents = array(
            'class-research-agent.php',
            'class-summarizer-agent.php',
            'class-writer-agent.php',
            'class-qa-agent.php',
            'class-image-agent.php',
        );
        
        foreach ( $agents as $agent ) {
            $file = TSA_PLUGIN_DIR . 'includes/agents/' . $agent;
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }

    public static function process_batch() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $settings = get_option( 'tsa_settings', array() );
        $rate_limit = isset( $settings['rate_limit'] ) ? intval( $settings['rate_limit'] ) : 3;
        
        // Get jobs that need processing - prioritize by status order
        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, status FROM $table_jobs 
             WHERE status IN ('queued', 'researching', 'summarizing', 'writing', 'qa', 'images') 
             ORDER BY 
                CASE status 
                    WHEN 'images' THEN 1
                    WHEN 'qa' THEN 2
                    WHEN 'writing' THEN 3
                    WHEN 'summarizing' THEN 4
                    WHEN 'researching' THEN 5
                    WHEN 'queued' THEN 6
                END,
                created_at ASC 
             LIMIT %d",
            $rate_limit
        ) );
        
        foreach ( $jobs as $job ) {
            // Check if job is already scheduled
            if ( function_exists( 'as_next_scheduled_action' ) ) {
                $scheduled = as_next_scheduled_action( 'tsa_process_job', array( 'job_id' => $job->id ), 'travelseo-autopublisher' );
                if ( $scheduled ) {
                    continue;
                }
            }
            
            // Process the job
            self::process_job( $job->id );
            
            // Delay between jobs to prevent overload
            sleep( 1 );
        }
    }

    public static function cancel_job( $job_id ) {
        if ( function_exists( 'as_unschedule_action' ) ) {
            as_unschedule_action( 'tsa_process_job', array( 'job_id' => $job_id ), 'travelseo-autopublisher' );
        }
        
        wp_clear_scheduled_hook( 'tsa_process_job_cron', array( $job_id ) );
        
        return true;
    }

    public static function get_queue_status() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        
        $status = array(
            'queued' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'queued'" ),
            'researching' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'researching'" ),
            'summarizing' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'summarizing'" ),
            'writing' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'writing'" ),
            'qa' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'qa'" ),
            'images' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'images'" ),
            'ready' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'ready'" ),
            'pushed' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'pushed'" ),
            'failed' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'" ),
        );
        
        $status['processing'] = $status['researching'] + $status['summarizing'] + $status['writing'] + $status['qa'] + $status['images'];
        
        if ( function_exists( 'as_get_scheduled_actions' ) ) {
            $pending_actions = as_get_scheduled_actions( array(
                'hook' => 'tsa_process_job',
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ) );
            $status['scheduled_actions'] = count( $pending_actions );
        }
        
        return $status;
    }

    public static function retry_failed_jobs( $job_id = 0 ) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        
        if ( $job_id > 0 ) {
            $wpdb->update( $table_jobs, array( 'status' => 'queued' ), array( 'id' => $job_id ) );
            self::schedule_job( $job_id );
            return 1;
        }
        
        $failed_jobs = $wpdb->get_results( "SELECT id FROM $table_jobs WHERE status = 'failed'" );
        
        foreach ( $failed_jobs as $job ) {
            $wpdb->update( $table_jobs, array( 'status' => 'queued' ), array( 'id' => $job->id ) );
            self::schedule_job( $job->id );
        }
        
        return count( $failed_jobs );
    }

    public static function cleanup_old_jobs( $days = 30 ) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_jobs WHERE status IN ('pushed', 'failed') AND created_at < %s",
            $cutoff_date
        ) );
        
        return $deleted;
    }

    /**
     * Force process a specific job immediately
     */
    public static function force_process( $job_id ) {
        self::load_dependencies();
        self::process_job( $job_id );
    }
}

// Initialize queue
Queue::init();
