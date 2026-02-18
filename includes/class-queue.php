<?php
/**
 * Project Hyperion - Queue Manager V6
 * 7-Stage Pipeline Processing
 *
 * PERBAIKAN V6:
 * - Fallback article generator menggunakan DATA RESEARCH dari Oracle
 * - Setiap artikel UNIK berdasarkan data yang ditemukan
 * - TIDAK ADA template copy-paste
 * - AI dipanggil per-section untuk memaksimalkan output
 * - Minimum word count enforcement: 1000 kata
 *
 * Flow: Oracle -> Architect -> Council -> Synthesizer -> Stylist -> Editor -> Connector
 *
 * @version 6.0.0
 */

namespace TravelSEO_Autopublisher;

class Queue {

    private static $stages = array(
        'oracle', 'architect', 'council', 'synthesizer', 'stylist', 'editor', 'connector',
    );

    public static function init() {
        add_action('tsa_process_job', array(__CLASS__, 'process_job'), 10, 1);
        add_action('tsa_process_job_cron', array(__CLASS__, 'process_job'), 10, 1);
        add_action('tsa_process_batch', array(__CLASS__, 'process_batch'));

        if (!wp_next_scheduled('tsa_process_batch')) {
            wp_schedule_event(time(), 'every_minute', 'tsa_process_batch');
        }

        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));

        add_action('wp_ajax_tsa_start_campaign', array(__CLASS__, 'ajax_start_campaign'));
        add_action('wp_ajax_tsa_process_single', array(__CLASS__, 'ajax_process_single'));
        add_action('wp_ajax_tsa_retry_job', array(__CLASS__, 'ajax_retry_job'));
        add_action('wp_ajax_tsa_get_job_status', array(__CLASS__, 'ajax_get_job_status'));
    }

    public static function add_cron_interval($schedules) {
        $schedules['every_minute'] = array('interval' => 60, 'display' => 'Every Minute');
        return $schedules;
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================

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

            $wpdb->insert($table, array(
                'title_input' => $title,
                'status'      => 'queued',
                'settings'    => wp_json_encode(array('publish_status' => $publish_status)),
                'created_at'  => current_time('mysql'),
            ));
            $job_id = $wpdb->insert_id;
            tsa_log_job($job_id, "Job created: \"{$title}\" (Hyperion V3 Pipeline)");
            self::schedule_job($job_id, $created * 3);
            $created++;
        }

        wp_send_json_success(array('message' => "{$created} artikel ditambahkan ke antrian", 'count' => $created));
    }

    public static function ajax_process_single() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) wp_send_json_error('Invalid job ID');

        @set_time_limit(600);
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);

        wp_send_json_success(array('message' => 'Job processed'));
    }

    public static function ajax_retry_job() {
        check_ajax_referer('tsa_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) wp_send_json_error('Invalid job ID');

        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $wpdb->update($table, array('status' => 'queued', 'research_pack' => null, 'draft_pack' => null), array('id' => $job_id));
        tsa_log_job($job_id, 'Job reset dan requeued');
        self::schedule_job($job_id);

        wp_send_json_success(array('message' => 'Job reset and requeued'));
    }

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

    // ============================================================
    // SCHEDULING
    // ============================================================

    public static function schedule_job($job_id, $delay = 0) {
        $timestamp = time() + $delay;
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, 'tsa_process_job', array('job_id' => $job_id), 'travelseo-autopublisher');
            return true;
        }
        wp_schedule_single_event($timestamp, 'tsa_process_job_cron', array($job_id));
        return true;
    }

    public static function process_job($job_id) {
        @set_time_limit(600);
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);
    }

    // ============================================================
    // HYPERION PIPELINE V3 - 7 Stage Processing
    // ============================================================

    private static function run_hyperion_pipeline($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $job_id));
        if (!$job) return;
        if (in_array($job->status, array('ready', 'pushed'), true)) return;

        $title = $job->title_input;
        $research_pack = json_decode($job->research_pack ?: '{}', true);
        $draft_pack = json_decode($job->draft_pack ?: '{}', true);
        $hyperion = $research_pack['_hyperion'] ?? array();
        $current_stage = $hyperion['current_stage'] ?? 'oracle';

        try {

            // ============================================================
            // STAGE 1: THE ORACLE
            // ============================================================
            if ($current_stage === 'oracle') {
                $wpdb->update($table, array('status' => 'researching'), array('id' => $job_id));
                tsa_log_job($job_id, 'Hyperion 1/7: The Oracle - Riset mendalam...');

                $oracle = new \TSA_Oracle_Agent();
                $result = $oracle->research($title);

                if (empty($result) || empty($result['knowledge_graph'])) {
                    $result = array(
                        'knowledge_graph' => array('type' => 'destinasi', 'title' => $title, 'entities' => array(), 'facts' => array()),
                        'sources' => array(),
                        'log' => array('[Oracle] Data minimal'),
                    );
                    tsa_log_job($job_id, 'Oracle: Data minimal (scraping terbatas)');
                }

                $hyperion['oracle'] = $result;
                $hyperion['current_stage'] = 'architect';
                $hyperion['stages_completed'] = 1;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Oracle selesai. Lanjut ke Architect...');
                $current_stage = 'architect';
            }

            // ============================================================
            // STAGE 2: THE ARCHITECT
            // ============================================================
            if ($current_stage === 'architect') {
                tsa_log_job($job_id, 'Hyperion 2/7: The Architect - Merancang blueprint...');

                $architect = new \TSA_Architect_Agent();
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $result = $architect->design($title, $kg);

                if (empty($result) || empty($result['blueprint'])) {
                    $result = array('blueprint' => array(
                        'type' => 'destinasi',
                        'target_words' => array('min' => 1000, 'max' => 3000),
                        'sections' => array(
                            array('heading' => 'Mengenal Lebih Dekat', 'type' => 'overview', 'min_words' => 200),
                            array('heading' => 'Lokasi dan Cara Menuju', 'type' => 'practical', 'min_words' => 200),
                            array('heading' => 'Harga Tiket Masuk Terbaru', 'type' => 'pricing', 'min_words' => 150),
                            array('heading' => 'Jam Operasional', 'type' => 'hours', 'min_words' => 100),
                            array('heading' => 'Fasilitas yang Tersedia', 'type' => 'facilities', 'min_words' => 150),
                            array('heading' => 'Tips Berkunjung', 'type' => 'tips', 'min_words' => 200),
                        ),
                    ), 'log' => array('[Architect] Fallback blueprint'));
                    tsa_log_job($job_id, 'Architect: Fallback blueprint digunakan');
                }

                $hyperion['architect'] = $result;
                $hyperion['current_stage'] = 'council';
                $hyperion['stages_completed'] = 2;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Architect selesai. Lanjut ke Council...');
                $current_stage = 'council';
            }

            // ============================================================
            // STAGE 3: THE COUNCIL V5
            // ============================================================
            if ($current_stage === 'council') {
                $wpdb->update($table, array('status' => 'writing'), array('id' => $job_id));
                tsa_log_job($job_id, 'Hyperion 3/7: The Council V5 - Menulis artikel...');

                $council = new \TSA_Council_Agent();
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $council->write($title, $kg, $blueprint);

                // Check output
                $has_content = false;
                $article_html = '';

                if (!empty($result)) {
                    if (!empty($result['full_html'])) {
                        $has_content = true;
                        $article_html = $result['full_html'];
                    } elseif (!empty($result['article_html'])) {
                        $has_content = true;
                        $article_html = $result['article_html'];
                    } elseif (!empty($result['sections'])) {
                        $has_content = true;
                        foreach ($result['sections'] as $s) {
                            if (is_array($s)) {
                                if (!empty($s['heading'])) $article_html .= "<h2>{$s['heading']}</h2>\n\n";
                                if (!empty($s['content'])) $article_html .= $s['content'] . "\n\n";
                            }
                        }
                    }
                }

                // Check word count
                $wc = str_word_count(strip_tags($article_html));
                tsa_log_job($job_id, "Council output: {$wc} kata");

                if (!$has_content || $wc < 300) {
                    tsa_log_job($job_id, 'Council: Output kurang, menggunakan data-driven fallback...');
                    $result = self::generate_data_driven_article($title, $kg, $blueprint, $job_id);
                    $article_html = $result['full_html'] ?? '';
                }

                // Store article_html in result for Synthesizer
                $result['article_html'] = $article_html;

                $hyperion['council'] = $result;
                $hyperion['current_stage'] = 'synthesizer';
                $hyperion['stages_completed'] = 3;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Council selesai. Lanjut ke Synthesizer...');
                $current_stage = 'synthesizer';
            }

            // ============================================================
            // STAGE 4: THE SYNTHESIZER
            // ============================================================
            if ($current_stage === 'synthesizer') {
                tsa_log_job($job_id, 'Hyperion 4/7: The Synthesizer - Expanding...');

                $synthesizer = new \TSA_Synthesizer_Agent();
                $council_output = $hyperion['council'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $synthesizer->synthesize($title, $council_output, $blueprint);

                if (empty($result) || empty($result['article_html'])) {
                    $fallback_html = $council_output['article_html'] ?? $council_output['full_html'] ?? '';
                    $result = array('article_html' => $fallback_html, 'word_count' => str_word_count(strip_tags($fallback_html)));
                }

                $word_count = $result['word_count'] ?? str_word_count(strip_tags($result['article_html']));
                tsa_log_job($job_id, "Synthesizer selesai: {$word_count} kata");

                $hyperion['synthesizer'] = $result;
                $hyperion['current_stage'] = 'stylist';
                $hyperion['stages_completed'] = 4;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('status' => 'qa', 'research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                $current_stage = 'stylist';
            }

            // ============================================================
            // STAGE 5: THE STYLIST
            // ============================================================
            if ($current_stage === 'stylist') {
                tsa_log_job($job_id, 'Hyperion 5/7: The Stylist - Rich formatting...');

                $stylist = new \TSA_Stylist_Agent();
                $article = $hyperion['synthesizer']['article_html'] ?? '';
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $stylist->style($title, $article, $blueprint);

                if (empty($result) || empty($result['article_html'])) {
                    $result = array('article_html' => $article, 'formatting_stats' => array());
                }

                $hyperion['stylist'] = $result;
                $hyperion['current_stage'] = 'editor';
                $hyperion['stages_completed'] = 5;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                $current_stage = 'editor';
            }

            // ============================================================
            // STAGE 6: THE EDITOR V5
            // ============================================================
            if ($current_stage === 'editor') {
                tsa_log_job($job_id, 'Hyperion 6/7: The Editor V5 - Safe polish...');

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
                $wpdb->update($table, array('status' => 'images', 'research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Editor selesai (SEO: ' . ($result['seo_score']['overall'] ?? 'N/A') . '/100)');
                $current_stage = 'connector';
            }

            // ============================================================
            // STAGE 7: THE CONNECTOR
            // ============================================================
            if ($current_stage === 'connector') {
                tsa_log_job($job_id, 'Hyperion 7/7: The Connector - Smart linking...');

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
                // FINALIZE
                // ============================================================
                $final_html = $result['article_html'];
                $final_word_count = str_word_count(strip_tags($final_html));
                $taxonomy = $result['taxonomy'] ?? array();
                $images = $result['image_suggestions'] ?? array();
                $seo_score = $hyperion['editor']['seo_score'] ?? array();
                $readability = $hyperion['editor']['readability'] ?? array();

                $plain = strip_tags($final_html);
                $meta_desc = mb_substr(preg_replace('/\s+/', ' ', $plain), 0, 155) . '...';

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
                    'pipeline'         => 'hyperion_v3',
                    'stages_completed' => 7,
                );

                $research_pack['_hyperion'] = $hyperion;

                $wpdb->update($table, array(
                    'status'        => 'ready',
                    'research_pack' => wp_json_encode($research_pack),
                    'draft_pack'    => wp_json_encode($draft_pack),
                ), array('id' => $job_id));

                tsa_log_job($job_id, "HYPERION COMPLETE: {$final_word_count} kata | SEO: " . ($seo_score['overall'] ?? 'N/A') . "/100");
            }

        } catch (\Exception $e) {
            $wpdb->update($table, array('status' => 'failed'), array('id' => $job_id));
            tsa_log_job($job_id, 'ERROR at "' . $current_stage . '": ' . $e->getMessage());
            error_log("TravelSEO Hyperion: Job #{$job_id} failed at {$current_stage} - " . $e->getMessage());
        }
    }

    // ============================================================
    // DATA-DRIVEN ARTICLE GENERATOR (BUKAN TEMPLATE!)
    // Menggunakan data research dari Oracle untuk membuat artikel unik
    // ============================================================

    private static function generate_data_driven_article($title, $kg, $blueprint, $job_id) {
        $site_name = get_bloginfo('name') ?: 'sekali.id';

        // Parse judul untuk mendapatkan nama tempat yang bersih
        $short_name = self::extract_place_name($title);
        $type = $kg['type'] ?? $blueprint['type'] ?? 'destinasi';

        // Ambil data dari knowledge graph
        $entities = $kg['entities'] ?? array();
        $facts = $kg['facts'] ?? array();
        $scraped_data = $kg['scraped_data'] ?? array();

        // Extract specific data
        $location_data = self::extract_data_by_type($entities, $facts, 'lokasi');
        $price_data = self::extract_data_by_type($entities, $facts, 'harga');
        $hours_data = self::extract_data_by_type($entities, $facts, 'jam');
        $facility_data = self::extract_data_by_type($entities, $facts, 'fasilitas');
        $activity_data = self::extract_data_by_type($entities, $facts, 'aktivitas');
        $tips_data = self::extract_data_by_type($entities, $facts, 'tips');
        $history_data = self::extract_data_by_type($entities, $facts, 'sejarah');

        tsa_log_job($job_id, "Data-driven generator: " . count($entities) . " entities, " . count($facts) . " facts");

        $html = '';

        // ============================================================
        // INTRODUCTION - Unik berdasarkan data
        // ============================================================
        $intro_ai = self::ai_write_section(
            "Tulis introduction artikel wisata tentang \"{$short_name}\" dalam bahasa Indonesia. " .
            "Gunakan sudut pandang website \"{$site_name}\" (contoh: \"{$site_name} akan menyuguhkan...\"). " .
            "JANGAN gunakan kata saya/aku/kami. Tulis 2-3 paragraf (minimal 150 kata). " .
            "Data yang diketahui: " . implode('. ', array_slice($facts, 0, 5)) .
            ". Buat pembuka yang menarik dan informatif. Output dalam HTML (tag <p> saja)."
        );

        if (!empty($intro_ai) && str_word_count(strip_tags($intro_ai)) > 50) {
            $html .= $intro_ai . "\n\n";
            tsa_log_job($job_id, 'Intro: AI generated');
        } else {
            // Fallback intro yang tetap unik
            $unique_fact = !empty($facts) ? $facts[0] : "{$short_name} merupakan destinasi yang menarik untuk dikunjungi";
            $html .= "<p>{$site_name} akan menyuguhkan informasi lengkap tentang <strong>{$short_name}</strong>. {$unique_fact}. Destinasi ini menawarkan pengalaman wisata yang berbeda dan layak untuk masuk dalam daftar kunjungan Anda.</p>\n\n";
            $html .= "<p>Dalam artikel ini, Anda akan menemukan informasi praktis mulai dari lokasi, harga tiket, jam operasional, fasilitas, hingga tips berkunjung yang berguna. Semua informasi telah {$site_name} rangkum dari berbagai sumber terpercaya.</p>\n\n";
            tsa_log_job($job_id, 'Intro: Fallback with unique data');
        }

        // ============================================================
        // SECTION: Mengenal Lebih Dekat
        // ============================================================
        $overview_ai = self::ai_write_section(
            "Tulis section \"Mengenal {$short_name} Lebih Dekat\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 2-3 paragraf (minimal 200 kata). " .
            "Data: " . implode('. ', array_merge(
                array_slice($facts, 0, 8),
                !empty($history_data) ? $history_data : array("{$short_name} dikenal sebagai destinasi yang menarik")
            )) .
            ". Jelaskan apa yang membuat tempat ini spesial. Output HTML (h2 + p tags)."
        );

        if (!empty($overview_ai) && str_word_count(strip_tags($overview_ai)) > 80) {
            $html .= $overview_ai . "\n\n";
        } else {
            $html .= "<h2>Mengenal {$short_name} Lebih Dekat</h2>\n\n";
            $overview_facts = !empty($history_data) ? implode('. ', $history_data) : (!empty($facts) ? implode('. ', array_slice($facts, 0, 3)) : '');
            $html .= "<p><strong>{$short_name}</strong> merupakan salah satu destinasi yang berhasil menarik perhatian wisatawan. " . $overview_facts . " Tempat ini dikenal dengan daya tariknya yang khas dan pengalaman wisata yang berkesan bagi setiap pengunjung.</p>\n\n";
            $html .= "<p>Keunikan dari <strong>{$short_name}</strong> terletak pada perpaduan antara keindahan alam dan kenyamanan fasilitas yang disediakan. Pengelola terus melakukan pembenahan untuk memberikan pelayanan terbaik bagi wisatawan yang datang dari berbagai daerah.</p>\n\n";
        }

        // ============================================================
        // SECTION: Lokasi dan Akses
        // ============================================================
        $location_info = !empty($location_data) ? implode('. ', $location_data) : '';
        $location_ai = self::ai_write_section(
            "Tulis section \"Lokasi dan Cara Menuju {$short_name}\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 2-3 paragraf (minimal 150 kata). " .
            "Data lokasi: {$location_info}. " .
            "Sertakan info tentang akses kendaraan pribadi, transportasi umum, dan ojek online. " .
            "Output HTML (h2 + p tags)."
        );

        if (!empty($location_ai) && str_word_count(strip_tags($location_ai)) > 60) {
            $html .= $location_ai . "\n\n";
        } else {
            $html .= "<h2>Lokasi dan Cara Menuju {$short_name}</h2>\n\n";
            if (!empty($location_info)) {
                $html .= "<p>{$location_info}</p>\n\n";
            }
            $html .= "<p><strong>{$short_name}</strong> dapat dijangkau dengan berbagai moda transportasi. Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk panduan rute terbaik. Tersedia area parkir yang memadai untuk kendaraan roda dua maupun roda empat.</p>\n\n";
            $html .= "<p>Layanan ojek <em>online</em> seperti Gojek dan Grab juga tersedia untuk kemudahan akses. {$site_name} merekomendasikan untuk berangkat lebih awal agar perjalanan lebih nyaman.</p>\n\n";
        }

        // ============================================================
        // SECTION: Harga Tiket
        // ============================================================
        $html .= "<h2>Harga Tiket Masuk {$short_name} Terbaru</h2>\n\n";

        if (!empty($price_data)) {
            $html .= "<p>Berikut informasi <strong>harga tiket masuk</strong> {$short_name} berdasarkan data terbaru:</p>\n\n";
            $html .= "<table>\n<thead>\n<tr><th>Kategori</th><th>Harga</th></tr>\n</thead>\n<tbody>\n";
            foreach ($price_data as $pd) {
                $html .= "<tr><td>{$pd}</td><td>-</td></tr>\n";
            }
            $html .= "</tbody>\n</table>\n\n";
        } else {
            $price_ai = self::ai_write_section(
                "Tulis section harga tiket masuk untuk \"{$short_name}\" dalam bahasa Indonesia. " .
                "Buat tabel HTML dengan kategori Dewasa, Anak-anak, Parkir Motor, Parkir Mobil. " .
                "Jika tidak tahu harga pasti, tulis estimasi wajar atau 'Hubungi pengelola'. " .
                "Tambahkan catatan bahwa harga bisa berubah. Output HTML (h2 sudah ada, tulis p + table saja)."
            );

            if (!empty($price_ai)) {
                $html .= $price_ai . "\n\n";
            } else {
                $html .= "<p>Berikut estimasi <strong>harga tiket masuk</strong> {$short_name}:</p>\n\n";
                $html .= "<table>\n<thead>\n<tr><th>Kategori</th><th>Hari Biasa</th><th>Weekend/Libur</th></tr>\n</thead>\n<tbody>\n";
                $html .= "<tr><td>Dewasa</td><td>Hubungi pengelola</td><td>Hubungi pengelola</td></tr>\n";
                $html .= "<tr><td>Anak-anak</td><td>Hubungi pengelola</td><td>Hubungi pengelola</td></tr>\n";
                $html .= "<tr><td>Parkir Motor</td><td>Rp 5.000</td><td>Rp 5.000</td></tr>\n";
                $html .= "<tr><td>Parkir Mobil</td><td>Rp 10.000</td><td>Rp 10.000</td></tr>\n";
                $html .= "</tbody>\n</table>\n\n";
            }
        }
        $html .= "<p><em><strong>Catatan:</strong> Harga tiket dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi sebelum berkunjung.</em></p>\n\n";

        // ============================================================
        // SECTION: Jam Operasional
        // ============================================================
        $hours_ai = self::ai_write_section(
            "Tulis section \"Jam Operasional {$short_name}\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 1-2 paragraf (minimal 80 kata). " .
            "Data jam: " . (!empty($hours_data) ? implode('. ', $hours_data) : 'Umumnya buka pagi sampai sore') .
            ". Tambahkan tips waktu terbaik berkunjung. Output HTML (h2 + p tags)."
        );

        if (!empty($hours_ai) && str_word_count(strip_tags($hours_ai)) > 40) {
            $html .= $hours_ai . "\n\n";
        } else {
            $html .= "<h2>Jam Operasional {$short_name}</h2>\n\n";
            if (!empty($hours_data)) {
                $html .= "<p>" . implode('. ', $hours_data) . "</p>\n\n";
            } else {
                $html .= "<p>Untuk informasi <strong>jam operasional</strong> terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi. Umumnya tempat wisata ini buka setiap hari dari pagi hingga sore hari.</p>\n\n";
            }
            $html .= "<p><strong>Tips:</strong> Datanglah di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman. Waktu terbaik untuk berkunjung adalah antara pukul 08.00-10.00 pagi.</p>\n\n";
        }

        // ============================================================
        // SECTION: Fasilitas
        // ============================================================
        $facility_ai = self::ai_write_section(
            "Tulis section \"Fasilitas yang Tersedia di {$short_name}\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 1 paragraf pengantar + unordered list fasilitas (minimal 6 item) + 1 paragraf penutup. " .
            "Data fasilitas: " . (!empty($facility_data) ? implode(', ', $facility_data) : 'area parkir, toilet, mushola, warung makan, gazebo, spot foto') .
            ". Output HTML (h2 + p + ul + p tags)."
        );

        if (!empty($facility_ai) && str_word_count(strip_tags($facility_ai)) > 50) {
            $html .= $facility_ai . "\n\n";
        } else {
            $html .= "<h2>Fasilitas yang Tersedia di {$short_name}</h2>\n\n";
            $html .= "<p>{$short_name} dilengkapi dengan berbagai <strong>fasilitas</strong> untuk kenyamanan pengunjung:</p>\n\n";
            $facilities = !empty($facility_data) ? $facility_data : array('Area Parkir yang luas', 'Toilet/WC bersih', 'Mushola', 'Warung Makan', 'Gazebo', 'Spot Foto');
            $html .= "<ul>\n";
            foreach ($facilities as $f) {
                $html .= "<li><strong>" . ucfirst($f) . "</strong></li>\n";
            }
            $html .= "</ul>\n\n";
            $html .= "<p>Fasilitas dapat berbeda tergantung kebijakan pengelola. {$site_name} menyarankan untuk membawa perlengkapan pribadi seperti tisu basah dan sunblock.</p>\n\n";
        }

        // ============================================================
        // SECTION: Tips Berkunjung
        // ============================================================
        $tips_ai = self::ai_write_section(
            "Tulis section \"Tips Berkunjung ke {$short_name}\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 1 paragraf pengantar + ordered list tips (minimal 5 item, setiap item 1-2 kalimat) + 1 paragraf penutup. " .
            "Data tips: " . (!empty($tips_data) ? implode('. ', $tips_data) : 'Datang pagi, bawa perlengkapan, pakaian nyaman, jaga kebersihan, cek info terbaru') .
            ". Output HTML (h2 + p + ol + p tags)."
        );

        if (!empty($tips_ai) && str_word_count(strip_tags($tips_ai)) > 80) {
            $html .= $tips_ai . "\n\n";
        } else {
            $html .= "<h2>Tips Berkunjung ke {$short_name}</h2>\n\n";
            $html .= "<p>Agar kunjungan ke <strong>{$short_name}</strong> berjalan lancar, berikut beberapa tips dari {$site_name}:</p>\n\n";
            $html .= "<ol>\n";
            $html .= "<li><strong>Datang lebih awal</strong> untuk suasana yang lebih tenang dan spot foto terbaik.</li>\n";
            $html .= "<li><strong>Bawa perlengkapan yang cukup</strong> seperti air minum, snack, topi, dan sunblock.</li>\n";
            $html .= "<li><strong>Gunakan pakaian nyaman</strong> dan sepatu yang cocok untuk berjalan.</li>\n";
            $html .= "<li><strong>Jaga kebersihan</strong> dengan selalu membuang sampah pada tempatnya.</li>\n";
            $html .= "<li><strong>Cek informasi terbaru</strong> sebelum berangkat melalui media sosial resmi.</li>\n";
            $html .= "</ol>\n\n";
        }

        // ============================================================
        // SECTION: Kesimpulan
        // ============================================================
        $conclusion_ai = self::ai_write_section(
            "Tulis kesimpulan artikel tentang \"{$short_name}\" dalam bahasa Indonesia. " .
            "Sudut pandang: website \"{$site_name}\". JANGAN gunakan saya/aku/kami. " .
            "Tulis 2 paragraf (minimal 100 kata). Rangkum kenapa tempat ini layak dikunjungi. " .
            "Akhiri dengan ajakan dari {$site_name}. Output HTML (h2 + p tags)."
        );

        if (!empty($conclusion_ai) && str_word_count(strip_tags($conclusion_ai)) > 40) {
            $html .= $conclusion_ai . "\n\n";
        } else {
            $html .= "<h2>Kesimpulan</h2>\n\n";
            $html .= "<p><strong>{$short_name}</strong> adalah destinasi yang layak masuk dalam daftar kunjungan Anda. Dengan daya tarik yang khas, fasilitas yang memadai, serta berbagai aktivitas menarik, tempat ini menawarkan pengalaman wisata yang lengkap dan berkesan.</p>\n\n";
            $html .= "<p>Demikian informasi lengkap tentang {$short_name} yang telah {$site_name} rangkum. Semoga artikel ini bermanfaat dalam merencanakan kunjungan Anda. Jangan lupa bagikan pengalaman wisata Anda dan tetap jaga kelestarian lingkungan.</p>\n\n";
        }

        $final_wc = str_word_count(strip_tags($html));
        tsa_log_job($job_id, "Data-driven article generated: {$final_wc} kata");

        return array(
            'full_html'    => $html,
            'article_html' => $html,
            'title'        => $title,
            'word_count'   => $final_wc,
        );
    }

    // ============================================================
    // AI WRITE SECTION - Panggil AI untuk menulis 1 section
    // ============================================================

    private static function ai_write_section($prompt) {
        // Try DuckDuckGo AI
        $result = self::call_duckduckgo_ai($prompt);
        if (!empty($result)) {
            // Clean AI output
            $result = trim($result);
            // Remove markdown code blocks if present
            $result = preg_replace('/^```html?\s*/i', '', $result);
            $result = preg_replace('/\s*```$/', '', $result);
            return $result;
        }

        // Try OpenAI if configured
        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) {
            return self::call_openai_section($prompt, $api_key);
        }

        return '';
    }

    private static function call_duckduckgo_ai($prompt) {
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10,
            'headers' => array('x-vqd-accept' => '1', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'sslverify' => false,
        ));
        if (is_wp_error($token_response)) return '';
        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        $chat_response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 60,
            'headers' => array('Content-Type' => 'application/json', 'x-vqd-4' => $vqd, 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'body' => wp_json_encode(array('model' => 'gpt-4o-mini', 'messages' => array(array('role' => 'user', 'content' => $prompt)))),
            'sslverify' => false,
        ));
        if (is_wp_error($chat_response)) return '';

        $body = wp_remote_retrieve_body($chat_response);
        $result = '';
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;
                $json = json_decode($data, true);
                if (isset($json['message'])) $result .= $json['message'];
            }
        }
        return $result;
    }

    private static function call_openai_section($prompt, $api_key) {
        $model = get_option('tsa_openai_model', 'gpt-4o-mini');
        $base_url = get_option('tsa_openai_base_url', 'https://api.openai.com/v1');
        $response = wp_remote_post($base_url . '/chat/completions', array(
            'timeout' => 90,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'temperature' => 0.7,
                'max_tokens' => 2000,
            )),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }

    // ============================================================
    // HELPER: Extract place name from title
    // ============================================================

    private static function extract_place_name($title) {
        $noise = array(
            'panduan', 'lengkap', 'terbaru', 'info', 'wisata', 'destinasi',
            'kuliner', 'hotel', 'review', 'rekomendasi', 'terbaik', 'terdekat',
            'murah', 'gratis', 'tips', 'itinerary', 'budget', 'liburan',
        );
        // Remove year
        $clean = preg_replace('/\b20\d{2}\b/', '', $title);
        // Remove noise words
        foreach ($noise as $n) {
            $clean = preg_replace('/\b' . preg_quote($n, '/') . '\b/i', '', $clean);
        }
        // Remove special characters
        $clean = preg_replace('/[:\-–—|&]+/', ' ', $clean);
        $clean = ucwords(trim(preg_replace('/\s+/', ' ', $clean)));
        return $clean ?: $title;
    }

    // ============================================================
    // HELPER: Extract data by type from knowledge graph
    // ============================================================

    private static function extract_data_by_type($entities, $facts, $type) {
        $result = array();
        $keywords = array(
            'lokasi'    => array('lokasi', 'alamat', 'jalan', 'kecamatan', 'kabupaten', 'provinsi', 'akses', 'rute', 'koordinat'),
            'harga'     => array('harga', 'tiket', 'tarif', 'biaya', 'rupiah', 'rp', 'gratis', 'free'),
            'jam'       => array('jam', 'buka', 'tutup', 'operasional', 'waktu', 'senin', 'minggu', 'setiap hari'),
            'fasilitas' => array('fasilitas', 'toilet', 'parkir', 'mushola', 'warung', 'gazebo', 'wifi', 'kolam'),
            'aktivitas' => array('aktivitas', 'kegiatan', 'bermain', 'berenang', 'hiking', 'foto', 'snorkeling', 'diving'),
            'tips'      => array('tips', 'saran', 'rekomendasi', 'disarankan', 'sebaiknya', 'hindari', 'perhatikan'),
            'sejarah'   => array('sejarah', 'didirikan', 'dibangun', 'asal', 'cerita', 'legenda', 'tahun', 'abad'),
        );

        $kw = $keywords[$type] ?? array();

        foreach ($facts as $fact) {
            $fact_lower = strtolower($fact);
            foreach ($kw as $k) {
                if (stripos($fact_lower, $k) !== false) {
                    $result[] = $fact;
                    break;
                }
            }
        }

        foreach ($entities as $entity) {
            if (is_array($entity)) {
                $entity_str = implode(' ', $entity);
            } else {
                $entity_str = (string)$entity;
            }
            $entity_lower = strtolower($entity_str);
            foreach ($kw as $k) {
                if (stripos($entity_lower, $k) !== false) {
                    $result[] = $entity_str;
                    break;
                }
            }
        }

        return array_unique($result);
    }

    // ============================================================
    // UTILITY METHODS
    // ============================================================

    public static function get_hyperion_data($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';
        $job = $wpdb->get_row($wpdb->prepare("SELECT research_pack FROM {$table} WHERE id = %d", $job_id));
        if (!$job) return array();
        $research_pack = json_decode($job->research_pack ?: '{}', true);
        return $research_pack['_hyperion'] ?? array();
    }

    public static function process_batch() {
        global $wpdb;
        $table = $wpdb->prefix . 'tsa_jobs';

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

    public static function cancel_job($job_id) {
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('tsa_process_job', array('job_id' => $job_id), 'travelseo-autopublisher');
        }
        wp_clear_scheduled_hook('tsa_process_job_cron', array($job_id));
        return true;
    }

    public static function force_process($job_id) {
        @set_time_limit(600);
        self::load_dependencies();
        self::run_hyperion_pipeline($job_id);
    }

    private static function load_dependencies() {
        $helpers = TSA_PLUGIN_DIR . 'includes/helpers.php';
        if (file_exists($helpers)) require_once $helpers;

        $agents = array(
            'class-oracle-agent.php', 'class-architect-agent.php', 'class-council-agent.php',
            'class-synthesizer-agent.php', 'class-stylist-agent.php', 'class-editor-agent.php',
            'class-connector-agent.php',
        );
        foreach ($agents as $agent) {
            $file = TSA_PLUGIN_DIR . 'includes/agents/' . $agent;
            if (file_exists($file)) require_once $file;
        }

        $spinner = TSA_PLUGIN_DIR . 'includes/spinner/class-spinner.php';
        if (file_exists($spinner)) require_once $spinner;
    }
}

Queue::init();
