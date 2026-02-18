<?php
/**
 * Project Hyperion - Queue Manager
 * 7-Stage Pipeline Processing
 *
 * Flow: Oracle → Architect → Council → Synthesizer → Stylist → Editor → Connector
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 * @version    4.0.0 (Hyperion)
 */

namespace TravelSEO_Autopublisher;

class Queue {

    /**
     * All Hyperion stages in order
     */
    private static $stages = array(
        'oracle',       // Stage 1: Research & Entity Extraction
        'architect',    // Stage 2: Narrative Design & Dynamic Outline
        'council',      // Stage 3: Multi-Perspective Content Writing
        'synthesizer',  // Stage 4: Cohesive Narrative Weaving
        'stylist',      // Stage 5: Semantic HTML & Rich Formatting
        'editor',       // Stage 6: Human-like Polish & SEO Audit
        'connector',    // Stage 7: Smart Internal & External Linking
    );

    public static function init() {
        add_action('tsa_process_job', array(__CLASS__, 'process_job'), 10, 1);
        add_action('tsa_process_job_cron', array(__CLASS__, 'process_job'), 10, 1);
        add_action('tsa_process_batch', array(__CLASS__, 'process_batch'));

        if (!wp_next_scheduled('tsa_process_batch')) {
            wp_schedule_event(time(), 'every_minute', 'tsa_process_batch');
        }

        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));

        // AJAX handlers
        add_action('wp_ajax_tsa_start_campaign', array(__CLASS__, 'ajax_start_campaign'));
        add_action('wp_ajax_tsa_process_single', array(__CLASS__, 'ajax_process_single'));
        add_action('wp_ajax_tsa_retry_job', array(__CLASS__, 'ajax_retry_job'));
        add_action('wp_ajax_tsa_get_job_status', array(__CLASS__, 'ajax_get_job_status'));
    }

    public static function add_cron_interval($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => 'Every Minute',
        );
        return $schedules;
    }

    /**
     * AJAX: Start campaign - create jobs from titles
     */
    public static function ajax_start_campaign() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $titles = isset($_POST['titles']) ? array_filter(array_map('sanitize_text_field', (array)$_POST['titles'])) : array();
        $publish_status = sanitize_text_field($_POST['publish_status'] ?? 'draft');

        if (empty($titles)) wp_send_json_error('No titles provided');

        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $created = 0;

        foreach ($titles as $title) {
            $title = trim($title);
            if (empty($title)) continue;

            $settings = wp_json_encode(array('publish_status' => $publish_status));
            $wpdb->insert($table, array(
                'title_input' => $title,
                'status'      => 'queued',
                'settings'    => $settings,
                'created_at'  => current_time('mysql'),
            ));
            $job_id = $wpdb->insert_id;
            tsa_log_job($job_id, "Job created: \"{$title}\" (Hyperion Pipeline)");
            self::schedule_job($job_id, $created * 3);
            $created++;
        }

        wp_send_json_success(array(
            'message' => "{$created} artikel ditambahkan ke antrian Hyperion",
            'count'   => $created,
        ));
    }

    /**
     * AJAX: Process single job immediately
     */
    public static function ajax_process_single() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) wp_send_json_error('Invalid job ID');

        @set_time_limit(600);
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);

        wp_send_json_success(array('message' => 'Job processed via Hyperion Pipeline'));
    }

    /**
     * AJAX: Retry failed job
     */
    public static function ajax_retry_job() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) wp_send_json_error('Invalid job ID');

        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $wpdb->update($table, array(
            'status'        => 'queued',
            'research_pack' => null,
            'draft_pack'    => null,
        ), array('id' => $job_id));

        tsa_log_job($job_id, 'Job reset dan requeued via Hyperion Pipeline');
        self::schedule_job($job_id);

        wp_send_json_success(array('message' => 'Job reset and requeued'));
    }

    /**
     * AJAX: Get job status
     */
    public static function ajax_get_job_status() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');

        $job_id = intval($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
        if (!$job_id) wp_send_json_error('Invalid job ID');

        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $job = $wpdb->get_row($wpdb->prepare("SELECT id, title_input, status FROM {$table} WHERE id = %d", $job_id));

        if (!$job) wp_send_json_error('Job not found');

        $hyperion_data = self::get_hyperion_data($job_id);

        wp_send_json_success(array(
            'id'     => $job->id,
            'title'  => $job->title_input,
            'status' => $job->status,
            'stage'  => $hyperion_data['current_stage'] ?? 'queued',
            'stages_completed' => $hyperion_data['stages_completed'] ?? 0,
        ));
    }

    /**
     * Schedule a job for processing
     */
    public static function schedule_job($job_id, $delay = 0) {
        $timestamp = time() + $delay;

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                $timestamp,
                'tsa_process_job',
                array('job_id' => $job_id),
                'travelseo-autopublisher'
            );
            return true;
        }

        wp_schedule_single_event($timestamp, 'tsa_process_job_cron', array($job_id));
        return true;
    }

    /**
     * Process a job (entry point from cron/scheduler)
     */
    public static function process_job($job_id) {
        @set_time_limit(300);
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);
    }

    /**
     * ============================================================
     * HYPERION PIPELINE - 7 Stage Processing
     * ============================================================
     */
    private static function run_hyperion_pipeline($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $job_id));
        if (!$job) return;

        // Skip completed or pushed jobs
        if (in_array($job->status, array('ready', 'pushed'), true)) return;

        $title = $job->title_input;
        $research_pack = json_decode($job->research_pack ?: '{}', true);
        $draft_pack = json_decode($job->draft_pack ?: '{}', true);
        $hyperion = $research_pack['_hyperion'] ?? array();

        // Determine current stage
        $current_stage = $hyperion['current_stage'] ?? 'oracle';

        try {
            // ============================================================
            // STAGE 1: THE ORACLE - Research & Entity Extraction
            // ============================================================
            if ($current_stage === 'oracle') {
                $wpdb->update($table, array('status' => 'researching'), array('id' => $job_id));
                tsa_log_job($job_id, 'Hyperion Stage 1/7: The Oracle - Riset mendalam...');

                $oracle = new \TSA_Oracle_Agent();
                $result = $oracle->research($title);

                if (empty($result) || empty($result['knowledge_graph'])) {
                    // Fallback: create minimal knowledge graph
                    $result = array(
                        'knowledge_graph' => array(
                            'type' => 'destinasi',
                            'title' => $title,
                            'entities' => array(),
                            'facts' => array(),
                        ),
                        'sources' => array(),
                        'log' => array('[Oracle] Menggunakan data minimal'),
                    );
                    tsa_log_job($job_id, 'Oracle: Menggunakan data minimal (scraping terbatas)');
                }

                $hyperion['oracle'] = $result;
                $hyperion['current_stage'] = 'architect';
                $hyperion['stages_completed'] = 1;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Oracle selesai. Lanjut ke Architect...');
                $current_stage = 'architect';
            }

            // ============================================================
            // STAGE 2: THE ARCHITECT - Narrative Design & Dynamic Outline
            // ============================================================
            if ($current_stage === 'architect') {
                tsa_log_job($job_id, 'Hyperion Stage 2/7: The Architect - Merancang blueprint...');

                $architect = new \TSA_Architect_Agent();
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $result = $architect->design($title, $kg);

                if (empty($result) || empty($result['blueprint'])) {
                    throw new \Exception('Architect gagal merancang blueprint artikel');
                }

                $hyperion['architect'] = $result;
                $hyperion['current_stage'] = 'council';
                $hyperion['stages_completed'] = 2;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'status' => 'writing',
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Architect selesai. Lanjut ke Council...');
                $current_stage = 'council';
            }

            // ============================================================
            // STAGE 3: THE COUNCIL - Multi-Perspective Content Writing
            // ============================================================
            if ($current_stage === 'council') {
                tsa_log_job($job_id, 'Hyperion Stage 3/7: The Council - Menulis dari 5 perspektif...');

                $council = new \TSA_Council_Agent();
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $council->write($title, $kg, $blueprint);

                if (empty($result) || empty($result['sections'])) {
                    throw new \Exception('Council gagal menulis konten');
                }

                $hyperion['council'] = $result;
                $hyperion['current_stage'] = 'synthesizer';
                $hyperion['stages_completed'] = 3;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Council selesai. Lanjut ke Synthesizer...');
                $current_stage = 'synthesizer';
            }

            // ============================================================
            // STAGE 4: THE SYNTHESIZER - Cohesive Narrative Weaving
            // ============================================================
            if ($current_stage === 'synthesizer') {
                tsa_log_job($job_id, 'Hyperion Stage 4/7: The Synthesizer - Menyatukan narasi...');

                $synthesizer = new \TSA_Synthesizer_Agent();
                $sections = $hyperion['council']['sections'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $synthesizer->synthesize($title, $sections, $blueprint);

                if (empty($result) || empty($result['article_html'])) {
                    throw new \Exception('Synthesizer gagal menyatukan narasi');
                }

                $hyperion['synthesizer'] = $result;
                $hyperion['current_stage'] = 'stylist';
                $hyperion['stages_completed'] = 4;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'status' => 'qa',
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Synthesizer selesai. Lanjut ke Stylist...');
                $current_stage = 'stylist';
            }

            // ============================================================
            // STAGE 5: THE STYLIST - Semantic HTML & Rich Formatting
            // ============================================================
            if ($current_stage === 'stylist') {
                tsa_log_job($job_id, 'Hyperion Stage 5/7: The Stylist - Formatting profesional...');

                $stylist = new \TSA_Stylist_Agent();
                $article = $hyperion['synthesizer']['article_html'] ?? '';
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $stylist->style($title, $article, $blueprint);

                if (empty($result) || empty($result['article_html'])) {
                    // Fallback: use unstyled article
                    $result = array('article_html' => $article, 'formatting_stats' => array());
                }

                $hyperion['stylist'] = $result;
                $hyperion['current_stage'] = 'editor';
                $hyperion['stages_completed'] = 5;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Stylist selesai. Lanjut ke Editor...');
                $current_stage = 'editor';
            }

            // ============================================================
            // STAGE 6: THE EDITOR - Human-like Polish & SEO Audit
            // ============================================================
            if ($current_stage === 'editor') {
                tsa_log_job($job_id, 'Hyperion Stage 6/7: The Editor - Polish & SEO audit...');

                $editor = new \TSA_Editor_Agent();
                $article = $hyperion['stylist']['article_html'] ?? '';
                $result = $editor->edit($title, $article);

                if (empty($result) || empty($result['article_html'])) {
                    $result = array(
                        'article_html' => $article,
                        'seo_score' => array('overall' => 50),
                        'readability' => array('score' => 50),
                        'word_count' => str_word_count(strip_tags($article)),
                    );
                }

                $hyperion['editor'] = $result;
                $hyperion['current_stage'] = 'connector';
                $hyperion['stages_completed'] = 6;
                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'status' => 'images',
                    'research_pack' => wp_json_encode($research_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, 'Editor selesai (SEO Score: ' . ($result['seo_score']['overall'] ?? 'N/A') . '). Lanjut ke Connector...');
                $current_stage = 'connector';
            }

            // ============================================================
            // STAGE 7: THE CONNECTOR - Smart Internal & External Linking
            // ============================================================
            if ($current_stage === 'connector') {
                tsa_log_job($job_id, 'Hyperion Stage 7/7: The Connector - Smart linking...');

                $connector = new \TSA_Connector_Agent();
                $article = $hyperion['editor']['article_html'] ?? '';
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $result = $connector->connect($title, $article, $kg);

                if (empty($result) || empty($result['article_html'])) {
                    $result = array(
                        'article_html' => $article,
                        'taxonomy' => array('category' => 'Wisata', 'tags' => array($title)),
                        'image_suggestions' => array(),
                    );
                }

                $hyperion['connector'] = $result;
                $hyperion['current_stage'] = 'complete';
                $hyperion['stages_completed'] = 7;

                // ============================================================
                // FINALIZE - Compile final article data
                // ============================================================
                $final_html = $result['article_html'];
                $final_word_count = str_word_count(strip_tags($final_html));
                $taxonomy = $result['taxonomy'] ?? array();
                $images = $result['image_suggestions'] ?? array();
                $seo_score = $hyperion['editor']['seo_score'] ?? array();
                $readability = $hyperion['editor']['readability'] ?? array();

                // Generate meta description
                $plain = strip_tags($final_html);
                $meta_desc = mb_substr(preg_replace('/\s+/', ' ', $plain), 0, 155) . '...';

                // Build final draft pack
                $draft_pack = array(
                    'content'          => $final_html,
                    'meta_description' => $meta_desc,
                    'word_count'       => $final_word_count,
                    'category'         => $taxonomy['category'] ?? 'Wisata',
                    'category_id'      => $taxonomy['category_id'] ?? 0,
                    'tags'             => $taxonomy['tags'] ?? array(),
                    'seo_score'        => $seo_score,
                    'readability'      => $readability,
                    'image_suggestions' => $images,
                    'formatting_stats' => $hyperion['stylist']['formatting_stats'] ?? array(),
                    'pipeline'         => 'hyperion',
                    'stages_completed' => 7,
                );

                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'status'        => 'ready',
                    'research_pack' => wp_json_encode($research_pack),
                    'draft_pack'    => wp_json_encode($draft_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, "✓ HYPERION COMPLETE: {$final_word_count} kata | SEO: " . ($seo_score['overall'] ?? 'N/A') . "/100 | Readability: " . ($readability['score'] ?? 'N/A') . "/100");
                tsa_log_job($job_id, 'Artikel siap untuk di-publish!');
            }

        } catch (\Exception $e) {
            $wpdb->update($table, array('status' => 'failed'), array('id' => $job_id));
            tsa_log_job($job_id, 'HYPERION ERROR at stage "' . $current_stage . '": ' . $e->getMessage());
            error_log("TravelSEO Hyperion: Job #{$job_id} failed at {$current_stage} - " . $e->getMessage());
        }
    }

    /**
     * Get Hyperion pipeline data for a job
     */
    public static function get_hyperion_data($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $job = $wpdb->get_row($wpdb->prepare("SELECT research_pack FROM {$table} WHERE id = %d", $job_id));

        if (!$job) return array();

        $research_pack = json_decode($job->research_pack ?: '{}', true);
        return $research_pack['_hyperion'] ?? array();
    }

    /**
     * Process batch of jobs (called by cron)
     */
    public static function process_batch() {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

        // Get jobs that need processing
        $jobs = $wpdb->get_results(
            "SELECT id, status FROM {$table}
             WHERE status IN ('queued', 'researching', 'writing', 'qa', 'images')
             ORDER BY
                CASE status
                    WHEN 'images' THEN 1
                    WHEN 'qa' THEN 2
                    WHEN 'writing' THEN 3
                    WHEN 'researching' THEN 4
                    WHEN 'queued' THEN 5
                END,
                created_at ASC
             LIMIT 2"
        );

        foreach ($jobs as $job) {
            self::run_hyperion_pipeline($job->id);
            sleep(1);
        }
    }

    /**
     * Get queue status
     */
    public static function get_queue_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

        return array(
            'queued'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'queued'"),
            'researching' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'researching'"),
            'writing'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'writing'"),
            'qa'          => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'qa'"),
            'images'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'images'"),
            'ready'       => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'ready'"),
            'pushed'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pushed'"),
            'failed'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'"),
        );
    }

    /**
     * Retry failed jobs
     */
    public static function retry_failed_jobs($job_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

        if ($job_id > 0) {
            $wpdb->update($table, array('status' => 'queued', 'research_pack' => null, 'draft_pack' => null), array('id' => $job_id));
            self::schedule_job($job_id);
            return 1;
        }

        $failed = $wpdb->get_results("SELECT id FROM {$table} WHERE status = 'failed'");
        foreach ($failed as $job) {
            $wpdb->update($table, array('status' => 'queued', 'research_pack' => null, 'draft_pack' => null), array('id' => $job->id));
            self::schedule_job($job->id);
        }
        return count($failed);
    }

    /**
     * Cancel a job
     */
    public static function cancel_job($job_id) {
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('tsa_process_job', array('job_id' => $job_id), 'travelseo-autopublisher');
        }
        wp_clear_scheduled_hook('tsa_process_job_cron', array($job_id));
        return true;
    }

    /**
     * Force process a specific job immediately
     */
    public static function force_process($job_id) {
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);
    }

    /**
     * Load all agent dependencies
     */
    private static function load_dependencies() {
        require_once TSA_PLUGIN_DIR . 'includes/helpers.php';

        $agents = array(
            'class-oracle-agent.php',
            'class-architect-agent.php',
            'class-council-agent.php',
            'class-synthesizer-agent.php',
            'class-stylist-agent.php',
            'class-editor-agent.php',
            'class-connector-agent.php',
        );

        foreach ($agents as $agent) {
            $file = TSA_PLUGIN_DIR . 'includes/agents/' . $agent;
            if (file_exists($file)) {
                require_once $file;
            }
        }

        // Load spinner
        $spinner_file = TSA_PLUGIN_DIR . 'includes/spinner/class-spinner.php';
        if (file_exists($spinner_file)) {
            require_once $spinner_file;
        }
    }
}

// Initialize queue
Queue::init();
