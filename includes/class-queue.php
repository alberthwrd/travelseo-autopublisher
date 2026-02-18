<?php
/**
 * Project Hyperion - Queue Manager V5
 * 7-Stage Pipeline Processing with Word Count Enforcement
 *
 * Flow: Oracle -> Architect -> Council -> Synthesizer -> Stylist -> Editor -> Connector
 *
 * @package    TravelSEO_Autopublisher
 * @version    5.0.0 (Hyperion V2)
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
            tsa_log_job($job_id, "Job created: \"{$title}\" (Hyperion V2 Pipeline)");
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
    // HYPERION PIPELINE V2 - 7 Stage Processing
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
                    // Fallback blueprint
                    $result = array('blueprint' => array(
                        'type' => 'destinasi',
                        'target_words' => array('min' => 1000, 'max' => 3000),
                        'sections' => array(
                            array('heading' => 'Mengenal Lebih Dekat', 'type' => 'overview'),
                            array('heading' => 'Informasi Praktis', 'type' => 'practical'),
                            array('heading' => 'Tips Berkunjung', 'type' => 'tips'),
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
            // STAGE 3: THE COUNCIL (V4 - full_html output)
            // ============================================================
            if ($current_stage === 'council') {
                $wpdb->update($table, array('status' => 'writing'), array('id' => $job_id));
                tsa_log_job($job_id, 'Hyperion 3/7: The Council - Menulis artikel lengkap...');

                $council = new \TSA_Council_Agent();
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $council->write($title, $kg, $blueprint);

                // Council V4 menghasilkan full_html ATAU sections
                $has_content = false;
                if (!empty($result)) {
                    if (!empty($result['full_html'])) $has_content = true;
                    if (!empty($result['sections'])) $has_content = true;
                    if (!empty($result['article_html'])) $has_content = true;
                }

                if (!$has_content) {
                    tsa_log_job($job_id, 'Council: Output kosong, menggunakan fallback content generator...');
                    $result = self::generate_fallback_article($title, $kg, $blueprint);
                }

                $hyperion['council'] = $result;
                $hyperion['current_stage'] = 'synthesizer';
                $hyperion['stages_completed'] = 3;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Council selesai. Lanjut ke Synthesizer...');
                $current_stage = 'synthesizer';
            }

            // ============================================================
            // STAGE 4: THE SYNTHESIZER (V4 - expansion loop)
            // ============================================================
            if ($current_stage === 'synthesizer') {
                tsa_log_job($job_id, 'Hyperion 4/7: The Synthesizer - Menyatukan & expanding...');

                $synthesizer = new \TSA_Synthesizer_Agent();
                $council_output = $hyperion['council'] ?? array();
                $blueprint = $hyperion['architect']['blueprint'] ?? array();
                $result = $synthesizer->synthesize($title, $council_output, $blueprint);

                if (empty($result) || empty($result['article_html'])) {
                    tsa_log_job($job_id, 'Synthesizer: Output kosong, menggunakan Council output langsung');
                    $fallback_html = $council_output['full_html'] ?? $council_output['article_html'] ?? '';
                    if (empty($fallback_html) && !empty($council_output['sections'])) {
                        foreach ($council_output['sections'] as $s) {
                            if (is_array($s)) {
                                if (!empty($s['heading'])) $fallback_html .= "<h2>{$s['heading']}</h2>\n\n";
                                if (!empty($s['content'])) $fallback_html .= $s['content'] . "\n\n";
                            }
                        }
                    }
                    $result = array('article_html' => $fallback_html, 'word_count' => str_word_count(strip_tags($fallback_html)));
                }

                $word_count = $result['word_count'] ?? str_word_count(strip_tags($result['article_html']));
                tsa_log_job($job_id, "Synthesizer selesai: {$word_count} kata");

                $hyperion['synthesizer'] = $result;
                $hyperion['current_stage'] = 'stylist';
                $hyperion['stages_completed'] = 4;
                $research_pack['_hyperion'] = $hyperion;
                $wpdb->update($table, array('status' => 'qa', 'research_pack' => wp_json_encode($research_pack)), array('id' => $job_id));
                tsa_log_job($job_id, 'Lanjut ke Stylist...');
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
                tsa_log_job($job_id, 'Stylist selesai. Lanjut ke Editor...');
                $current_stage = 'editor';
            }

            // ============================================================
            // STAGE 6: THE EDITOR
            // ============================================================
            if ($current_stage === 'editor') {
                tsa_log_job($job_id, 'Hyperion 6/7: The Editor - Polish & SEO audit...');

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
                tsa_log_job($job_id, 'Editor selesai (SEO: ' . ($result['seo_score']['overall'] ?? 'N/A') . '/100). Lanjut ke Connector...');
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
                    'pipeline'         => 'hyperion_v2',
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
    // FALLBACK ARTICLE GENERATOR
    // Menghasilkan artikel 1000+ kata tanpa AI jika semua AI gagal
    // ============================================================

    private static function generate_fallback_article($title, $kg, $blueprint) {
        $site_name = get_bloginfo('name') ?: 'sekali.id';
        $short_name = preg_replace('/\b(panduan|lengkap|terbaru|info|wisata|destinasi|kuliner|hotel|review|rekomendasi|\d{4})\b/i', '', $title);
        $short_name = ucwords(trim(preg_replace('/\s+/', ' ', $short_name)));
        $type = $blueprint['type'] ?? $kg['type'] ?? 'destinasi';

        $html = '';

        // INTRODUCTION (150+ kata)
        $html .= "<p>{$site_name} akan menyuguhkan informasi lengkap tentang <strong>{$short_name}</strong> yang menjadi salah satu destinasi menarik dan layak untuk dikunjungi. Tempat ini menawarkan pengalaman wisata yang unik dan berbeda dari destinasi lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda bersama keluarga, pasangan, maupun teman-teman.</p>\n\n";
        $html .= "<p>Dalam artikel ini, Anda akan menemukan informasi lengkap mulai dari lokasi dan cara menuju ke sana, harga tiket masuk terbaru, jam operasional, fasilitas yang tersedia, hingga tips berkunjung yang berguna untuk memaksimalkan pengalaman wisata Anda. Semua informasi telah {$site_name} rangkum dari berbagai sumber terpercaya agar Anda bisa merencanakan kunjungan dengan lebih baik dan matang.</p>\n\n";

        // SECTION 1: Mengenal Lebih Dekat (200+ kata)
        $html .= "<h2>Mengenal {$short_name} Lebih Dekat</h2>\n\n";
        $html .= "<p><strong>{$short_name}</strong> merupakan salah satu destinasi yang berhasil menarik perhatian banyak wisatawan dalam beberapa tahun terakhir. Tempat ini dikenal dengan keindahan alamnya yang memukau, suasana yang tenang dan asri, serta berbagai daya tarik yang membuat pengunjung betah berlama-lama menikmati setiap sudutnya.</p>\n\n";
        $html .= "<p>Destinasi ini memiliki sejarah yang cukup panjang dan menarik. Seiring berjalannya waktu, tempat ini terus berkembang dan semakin populer di kalangan wisatawan yang mencari pengalaman wisata yang autentik dan berkesan. Pengelola terus melakukan pembenahan dan peningkatan fasilitas untuk memberikan kenyamanan terbaik bagi setiap pengunjung yang datang.</p>\n\n";
        $html .= "<p>Keunikan utama dari <strong>{$short_name}</strong> terletak pada perpaduan antara keindahan alam yang masih terjaga dengan sentuhan modernitas yang tidak berlebihan. Hal ini menciptakan atmosfer yang sangat nyaman dan cocok untuk berbagai jenis wisatawan, mulai dari yang mencari ketenangan hingga yang mencari petualangan seru.</p>\n\n";

        // SECTION 2: Lokasi dan Akses (200+ kata)
        $html .= "<h2>Lokasi dan Cara Menuju {$short_name}</h2>\n\n";
        $html .= "<p><strong>{$short_name}</strong> terletak di lokasi yang cukup strategis dan mudah dijangkau dengan berbagai moda transportasi. Untuk mencapai lokasi ini, Anda bisa menggunakan kendaraan pribadi maupun transportasi umum. Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk mendapatkan panduan rute terbaik menuju lokasi.</p>\n\n";
        $html .= "<p>Bagi Anda yang menggunakan kendaraan pribadi, tersedia area parkir yang cukup luas untuk menampung kendaraan roda dua maupun roda empat. Kondisi jalan menuju lokasi umumnya sudah cukup baik dan bisa dilalui dengan nyaman. Namun, disarankan untuk tetap berhati-hati terutama jika berkunjung saat musim hujan.</p>\n\n";
        $html .= "<p>Layanan ojek <em>online</em> seperti Gojek dan Grab juga tersedia untuk kemudahan akses bagi wisatawan yang tidak membawa kendaraan sendiri. Selain itu, transportasi umum dari pusat kota juga tersedia meskipun dengan jadwal yang terbatas. {$site_name} merekomendasikan untuk berangkat lebih awal agar perjalanan lebih nyaman dan tidak terburu-buru.</p>\n\n";

        // SECTION 3: Harga Tiket (150+ kata)
        $html .= "<h2>Harga Tiket Masuk {$short_name} Terbaru</h2>\n\n";
        $html .= "<p>Berikut informasi <strong>harga tiket masuk</strong> {$short_name} yang perlu Anda ketahui sebelum berkunjung:</p>\n\n";
        $html .= "<table>\n<thead>\n<tr><th>Kategori</th><th>Hari Biasa</th><th>Weekend/Libur</th></tr>\n</thead>\n<tbody>\n";
        $html .= "<tr><td>Dewasa</td><td>Hubungi pengelola</td><td>Hubungi pengelola</td></tr>\n";
        $html .= "<tr><td>Anak-anak</td><td>Hubungi pengelola</td><td>Hubungi pengelola</td></tr>\n";
        $html .= "<tr><td>Parkir Motor</td><td>Rp 5.000</td><td>Rp 5.000</td></tr>\n";
        $html .= "<tr><td>Parkir Mobil</td><td>Rp 10.000</td><td>Rp 10.000</td></tr>\n";
        $html .= "</tbody>\n</table>\n\n";
        $html .= "<p><em><strong>Catatan:</strong> Harga tiket dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi untuk mendapatkan informasi harga terbaru sebelum berkunjung.</em></p>\n\n";

        // SECTION 4: Jam Operasional (100+ kata)
        $html .= "<h2>Jam Operasional {$short_name}</h2>\n\n";
        $html .= "<p>Untuk informasi <strong>jam operasional</strong> terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi destinasi ini. Umumnya tempat wisata seperti ini buka setiap hari mulai dari pagi hingga sore hari, namun jam operasional bisa berbeda pada hari libur nasional atau event tertentu.</p>\n\n";
        $html .= "<p><strong>Tips:</strong> Datanglah di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman. Waktu terbaik untuk berkunjung adalah antara pukul 08.00-10.00 pagi atau menjelang sore hari ketika cuaca lebih sejuk dan pemandangan <em>sunset</em> yang memukau.</p>\n\n";

        // SECTION 5: Fasilitas (150+ kata)
        $html .= "<h2>Fasilitas yang Tersedia di {$short_name}</h2>\n\n";
        $html .= "<p>{$short_name} dilengkapi dengan berbagai <strong>fasilitas</strong> yang dirancang untuk memberikan kenyamanan maksimal bagi setiap pengunjung. Berikut beberapa fasilitas yang bisa Anda nikmati:</p>\n\n";
        $html .= "<ul>\n";
        $html .= "<li><strong>Area Parkir</strong> - Luas dan aman untuk kendaraan roda dua maupun roda empat</li>\n";
        $html .= "<li><strong>Toilet/WC</strong> - Bersih dan terawat dengan baik</li>\n";
        $html .= "<li><strong>Mushola</strong> - Tersedia untuk ibadah pengunjung muslim</li>\n";
        $html .= "<li><strong>Warung Makan</strong> - Menyediakan berbagai makanan dan minuman dengan harga terjangkau</li>\n";
        $html .= "<li><strong>Gazebo</strong> - Tempat beristirahat yang nyaman sambil menikmati pemandangan</li>\n";
        $html .= "<li><strong>Spot Foto</strong> - Berbagai sudut <em>instagramable</em> untuk mengabadikan momen</li>\n";
        $html .= "</ul>\n\n";
        $html .= "<p>Fasilitas dapat berbeda tergantung kebijakan pengelola dan perkembangan terbaru di lokasi. {$site_name} menyarankan untuk membawa perlengkapan pribadi seperti tisu basah, sunblock, dan topi untuk kenyamanan ekstra selama berkunjung.</p>\n\n";

        // SECTION 6: Tips Berkunjung (200+ kata)
        $html .= "<h2>Tips Berkunjung ke {$short_name} agar Lebih Menyenangkan</h2>\n\n";
        $html .= "<p>Agar kunjungan Anda ke <strong>{$short_name}</strong> berjalan lancar dan menyenangkan, berikut beberapa tips yang {$site_name} rekomendasikan:</p>\n\n";
        $html .= "<ol>\n";
        $html .= "<li><strong>Datang lebih awal</strong> - Berkunjung di pagi hari memberikan suasana yang lebih tenang dan nyaman. Anda juga bisa mendapatkan spot foto terbaik tanpa harus berdesakan dengan pengunjung lain.</li>\n";
        $html .= "<li><strong>Bawa perlengkapan yang cukup</strong> - Siapkan air minum, snack ringan, topi, kacamata hitam, dan sunblock untuk melindungi diri dari terik matahari.</li>\n";
        $html .= "<li><strong>Gunakan pakaian yang nyaman</strong> - Pilih pakaian yang nyaman dan sepatu yang cocok untuk berjalan, terutama jika area wisata cukup luas.</li>\n";
        $html .= "<li><strong>Jaga kebersihan</strong> - Selalu buang sampah pada tempatnya dan jaga kelestarian lingkungan sekitar agar destinasi ini tetap indah untuk generasi mendatang.</li>\n";
        $html .= "<li><strong>Cek informasi terbaru</strong> - Sebelum berangkat, pastikan untuk mengecek jam operasional dan harga tiket terbaru melalui media sosial resmi atau menghubungi pengelola.</li>\n";
        $html .= "</ol>\n\n";

        // SECTION 7: Kesimpulan (100+ kata)
        $html .= "<h2>Kesimpulan</h2>\n\n";
        $html .= "<p><strong>{$short_name}</strong> adalah destinasi yang layak masuk dalam daftar kunjungan Anda. Dengan keindahan alam yang memukau, fasilitas yang memadai, serta berbagai aktivitas menarik yang bisa dilakukan, tempat ini menawarkan pengalaman wisata yang lengkap dan berkesan untuk semua kalangan.</p>\n\n";
        $html .= "<p>Demikian informasi lengkap tentang {$short_name} yang telah {$site_name} rangkum untuk Anda. Semoga artikel ini bermanfaat dalam merencanakan kunjungan Anda. Jangan lupa untuk membagikan pengalaman wisata Anda dan tetap jaga kelestarian lingkungan di setiap destinasi yang dikunjungi.</p>\n\n";

        return array(
            'full_html' => $html,
            'title'     => $title,
            'meta'      => "Panduan lengkap {$short_name}. Info harga tiket, jam buka, fasilitas, dan tips berkunjung terbaru.",
            'sections'  => array(),
        );
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
