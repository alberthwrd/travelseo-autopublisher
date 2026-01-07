<?php
/**
 * Queue Manager - Background Processing
 *
 * Handles job queue processing using Action Scheduler (preferred)
 * or WP Cron as fallback.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * Queue Manager Class
 */
class Queue {

    /**
     * Initialize queue hooks
     */
    public static function init() {
        // Action Scheduler hooks
        add_action( 'tsa_process_job', array( __CLASS__, 'process_job' ), 10, 1 );
        
        // WP Cron fallback hooks
        add_action( 'tsa_process_job_cron', array( __CLASS__, 'process_job' ), 10, 1 );
        
        // Batch processing hook
        add_action( 'tsa_process_batch', array( __CLASS__, 'process_batch' ) );
        
        // Schedule batch processor if not already scheduled
        if ( ! wp_next_scheduled( 'tsa_process_batch' ) ) {
            wp_schedule_event( time(), 'every_minute', 'tsa_process_batch' );
        }
        
        // Add custom cron interval
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
    }

    /**
     * Add custom cron interval
     *
     * @param array $schedules Existing schedules
     * @return array
     */
    public static function add_cron_interval( $schedules ) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __( 'Every Minute', 'travelseo-autopublisher' ),
        );
        return $schedules;
    }

    /**
     * Schedule a job for processing
     *
     * @param int $job_id Job ID
     * @param int $delay Delay in seconds (default: 0)
     * @return bool
     */
    public static function schedule_job( $job_id, $delay = 0 ) {
        $timestamp = time() + $delay;
        
        // Prefer Action Scheduler if available
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( 
                $timestamp, 
                'tsa_process_job', 
                array( 'job_id' => $job_id ), 
                'travelseo-autopublisher' 
            );
            return true;
        }
        
        // Fallback to WP Cron
        wp_schedule_single_event( $timestamp, 'tsa_process_job_cron', array( $job_id ) );
        return true;
    }

    /**
     * Process a single job
     *
     * @param int $job_id Job ID
     */
    public static function process_job( $job_id ) {
        global $wpdb;
        
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
        require_once TSA_PLUGIN_DIR . 'includes/helpers.php';
        require_once TSA_PLUGIN_DIR . 'includes/agents/class-research-agent.php';
        require_once TSA_PLUGIN_DIR . 'includes/agents/class-writer-agent.php';
        require_once TSA_PLUGIN_DIR . 'includes/agents/class-qa-agent.php';
        require_once TSA_PLUGIN_DIR . 'includes/agents/class-image-agent.php';
        
        $settings = json_decode( $job->settings, true ) ?: array();
        $research_pack = json_decode( $job->research_pack, true ) ?: array();
        $draft_pack = json_decode( $job->draft_pack, true ) ?: array();
        
        try {
            // Determine which stage to run based on current status
            switch ( $job->status ) {
                case 'queued':
                    // Stage 1: Research
                    $agent = new Agents\Research_Agent( $job_id, $job->title_input, $settings );
                    $research_pack = $agent->run();
                    
                    $wpdb->update( $table_jobs, array(
                        'status' => 'researching',
                        'research_pack' => wp_json_encode( $research_pack ),
                    ), array( 'id' => $job_id ) );
                    
                    // Schedule next stage
                    self::schedule_job( $job_id, 1 );
                    break;
                    
                case 'researching':
                    // Stage 2: Writing
                    $agent = new Agents\Writer_Agent( $job_id, $research_pack, $settings );
                    $draft_pack = $agent->run();
                    
                    $wpdb->update( $table_jobs, array(
                        'status' => 'drafting',
                        'draft_pack' => wp_json_encode( $draft_pack ),
                    ), array( 'id' => $job_id ) );
                    
                    // Schedule next stage
                    self::schedule_job( $job_id, 1 );
                    break;
                    
                case 'drafting':
                    // Stage 3: QA
                    $agent = new Agents\QA_Agent( $job_id, $draft_pack, $research_pack );
                    $draft_pack = $agent->run();
                    
                    $wpdb->update( $table_jobs, array(
                        'status' => 'qa',
                        'draft_pack' => wp_json_encode( $draft_pack ),
                    ), array( 'id' => $job_id ) );
                    
                    // Schedule next stage
                    self::schedule_job( $job_id, 1 );
                    break;
                    
                case 'qa':
                    // Stage 4: Image Planning
                    $agent = new Agents\Image_Agent( $job_id, $draft_pack );
                    $draft_pack = $agent->run();
                    
                    $wpdb->update( $table_jobs, array(
                        'status' => 'ready',
                        'draft_pack' => wp_json_encode( $draft_pack ),
                    ), array( 'id' => $job_id ) );
                    
                    tsa_log_job( $job_id, 'Job completed successfully. Ready for publishing.' );
                    break;
            }
            
        } catch ( \Exception $e ) {
            // Mark as failed
            $wpdb->update( $table_jobs, array(
                'status' => 'failed',
            ), array( 'id' => $job_id ) );
            
            tsa_log_job( $job_id, 'Error: ' . $e->getMessage() );
            error_log( "TravelSEO: Job #{$job_id} failed - " . $e->getMessage() );
        }
    }

    /**
     * Process batch of queued jobs
     * This runs periodically to pick up any jobs that weren't scheduled
     */
    public static function process_batch() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        
        // Get rate limit setting
        $settings = get_option( 'tsa_settings', array() );
        $rate_limit = isset( $settings['rate_limit'] ) ? $settings['rate_limit'] : 5;
        
        // Get jobs that need processing
        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM $table_jobs 
             WHERE status IN ('queued', 'researching', 'drafting', 'qa', 'image_planning') 
             ORDER BY created_at ASC 
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
            
            // Small delay between jobs
            usleep( 500000 ); // 0.5 seconds
        }
    }

    /**
     * Cancel a scheduled job
     *
     * @param int $job_id Job ID
     * @return bool
     */
    public static function cancel_job( $job_id ) {
        if ( function_exists( 'as_unschedule_action' ) ) {
            as_unschedule_action( 'tsa_process_job', array( 'job_id' => $job_id ), 'travelseo-autopublisher' );
        }
        
        wp_clear_scheduled_hook( 'tsa_process_job_cron', array( $job_id ) );
        
        return true;
    }

    /**
     * Get queue status
     *
     * @return array
     */
    public static function get_queue_status() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        
        $status = array(
            'queued' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'queued'" ),
            'processing' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status IN ('researching', 'drafting', 'qa', 'image_planning')" ),
            'ready' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'ready'" ),
            'pushed' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'pushed'" ),
            'failed' => $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'" ),
        );
        
        // Check Action Scheduler status
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

    /**
     * Retry failed jobs
     *
     * @param int $job_id Specific job ID or 0 for all failed jobs
     * @return int Number of jobs queued for retry
     */
    public static function retry_failed_jobs( $job_id = 0 ) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        
        if ( $job_id > 0 ) {
            $wpdb->update( $table_jobs, array( 'status' => 'queued' ), array( 'id' => $job_id ) );
            self::schedule_job( $job_id );
            return 1;
        }
        
        // Retry all failed jobs
        $failed_jobs = $wpdb->get_results( "SELECT id FROM $table_jobs WHERE status = 'failed'" );
        
        foreach ( $failed_jobs as $job ) {
            $wpdb->update( $table_jobs, array( 'status' => 'queued' ), array( 'id' => $job->id ) );
            self::schedule_job( $job->id );
        }
        
        return count( $failed_jobs );
    }

    /**
     * Clear completed jobs older than X days
     *
     * @param int $days Number of days
     * @return int Number of jobs deleted
     */
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
}

// Initialize queue
Queue::init();
