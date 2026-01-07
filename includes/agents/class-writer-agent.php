<?php
/**
 * Writer Agent - SEO Structure & Full Draft Writer
 *
 * This agent is responsible for:
 * - Creating article structure and outline
 * - Writing full article content (±2000 words)
 * - Generating meta title, meta description, slug
 * - Auto-mapping/creating categories and tags
 * - Suggesting internal links
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;
use function TravelSEO_Autopublisher\tsa_generate_meta_description;

/**
 * Writer Agent Class
 */
class Writer_Agent {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Research pack from Agent 1
     *
     * @var array
     */
    private $research_pack;

    /**
     * Job settings
     *
     * @var array
     */
    private $settings;

    /**
     * Draft pack result
     *
     * @var array
     */
    private $draft_pack;

    /**
     * Constructor
     *
     * @param int $job_id Job ID
     * @param array $research_pack Research pack from Agent 1
     * @param array $settings Job settings
     */
    public function __construct( $job_id, $research_pack, $settings = array() ) {
        $this->job_id = $job_id;
        $this->research_pack = $research_pack;
        $this->settings = wp_parse_args( $settings, array(
            'target_words' => 2000,
            'language' => 'id',
            'tone' => 'informative',
        ) );
        
        $this->draft_pack = array(
            'title' => '',
            'slug' => '',
            'meta_title' => '',
            'meta_description' => '',
            'content' => '',
            'excerpt' => '',
            'outline' => array(),
            'category_id' => 0,
            'category_name' => '',
            'tag_ids' => array(),
            'tag_names' => array(),
            'internal_links' => array(),
            'faq' => array(),
            'schema_data' => array(),
            'word_count' => 0,
            'created_at' => current_time( 'mysql' ),
        );
    }

    /**
     * Run the writer process
     *
     * @return array Draft pack
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Writer Agent: Starting article creation...' );
        
        // Update job status
        tsa_update_job( $this->job_id, array( 'status' => 'drafting' ) );

        $title = $this->research_pack['title'];

        // Step 1: Generate article structure/outline
        $this->generate_outline();

        // Step 2: Generate meta data
        $this->generate_meta_data();

        // Step 3: Write full article content
        $this->write_content();

        // Step 4: Handle categories
        $this->handle_categories();

        // Step 5: Generate tags
        $this->generate_tags();

        // Step 6: Find internal links
        $this->find_internal_links();

        // Step 7: Generate FAQ section
        $this->generate_faq();

        // Step 8: Generate schema data
        $this->generate_schema();

        tsa_log_job( $this->job_id, 'Writer Agent: Completed. Word count: ' . $this->draft_pack['word_count'] );

        return $this->draft_pack;
    }

    /**
     * Generate article outline
     */
    private function generate_outline() {
        $title = $this->research_pack['title'];
        
        // Default outline structure for travel content
        $outline = array(
            array(
                'tag' => 'h2',
                'text' => 'Sekilas Tentang ' . $title,
                'description' => 'Pengenalan singkat tentang destinasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Sejarah dan Latar Belakang',
                'description' => 'Sejarah dan asal-usul destinasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Lokasi dan Cara Menuju ' . $title,
                'description' => 'Informasi lokasi dan akses transportasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Harga Tiket Masuk',
                'description' => 'Informasi harga tiket dan biaya',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Jam Operasional',
                'description' => 'Jam buka dan tutup',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Fasilitas yang Tersedia',
                'description' => 'Daftar fasilitas di lokasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Aktivitas dan Daya Tarik Utama',
                'description' => 'Hal-hal menarik yang bisa dilakukan',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Tips Berkunjung ke ' . $title,
                'description' => 'Tips dan saran untuk pengunjung',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Rekomendasi Tempat Sekitar',
                'description' => 'Destinasi lain di sekitar lokasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'FAQ - Pertanyaan yang Sering Diajukan',
                'description' => 'Pertanyaan umum tentang destinasi',
            ),
            array(
                'tag' => 'h2',
                'text' => 'Kesimpulan',
                'description' => 'Ringkasan dan ajakan berkunjung',
            ),
        );

        // If AI is available, enhance the outline
        if ( $this->has_ai_api() ) {
            $outline = $this->enhance_outline_with_ai( $outline );
        }

        $this->draft_pack['outline'] = $outline;
        
        tsa_log_job( $this->job_id, 'Writer Agent: Generated outline with ' . count( $outline ) . ' sections.' );
    }

    /**
     * Generate meta data (title, description, slug)
     */
    private function generate_meta_data() {
        $title = $this->research_pack['title'];
        $year = date( 'Y' );

        // Generate SEO-friendly meta title (50-60 chars)
        $meta_title_templates = array(
            $title . ' - Panduan Lengkap ' . $year,
            $title . ': Harga Tiket, Jam Buka & Tips ' . $year,
            'Wisata ' . $title . ' - Info Lengkap ' . $year,
            $title . ' - Review, Lokasi & Fasilitas ' . $year,
        );
        
        // Pick the best one that fits 50-60 chars
        $meta_title = $title . ' - Panduan Lengkap ' . $year;
        foreach ( $meta_title_templates as $template ) {
            if ( strlen( $template ) >= 50 && strlen( $template ) <= 60 ) {
                $meta_title = $template;
                break;
            }
        }
        
        // Truncate if still too long
        if ( strlen( $meta_title ) > 60 ) {
            $meta_title = substr( $meta_title, 0, 57 ) . '...';
        }

        $this->draft_pack['title'] = $title;
        $this->draft_pack['meta_title'] = $meta_title;
        
        // Generate slug
        $this->draft_pack['slug'] = sanitize_title( $title );

        // Generate meta description (150-160 chars)
        $location = ! empty( $this->research_pack['location_info'] ) ? $this->research_pack['location_info'] : '';
        $price = ! empty( $this->research_pack['pricing_info'] ) ? $this->research_pack['pricing_info'] : '';
        
        $meta_desc = "Panduan lengkap " . $title . " " . $year . ". ";
        if ( ! empty( $location ) ) {
            $meta_desc .= "Lokasi di " . $location . ". ";
        }
        if ( ! empty( $price ) ) {
            $meta_desc .= "Harga tiket mulai " . $price . ". ";
        }
        $meta_desc .= "Info jam buka, fasilitas, dan tips berkunjung.";
        
        // Truncate to 160 chars
        if ( strlen( $meta_desc ) > 160 ) {
            $meta_desc = substr( $meta_desc, 0, 157 ) . '...';
        }
        
        $this->draft_pack['meta_description'] = $meta_desc;
        
        tsa_log_job( $this->job_id, 'Writer Agent: Generated meta data.' );
    }

    /**
     * Write full article content
     */
    private function write_content() {
        $title = $this->research_pack['title'];
        
        // Check if AI API is available
        if ( $this->has_ai_api() ) {
            $content = $this->write_content_with_ai();
        } else {
            $content = $this->write_content_without_ai();
        }
        
        $this->draft_pack['content'] = $content;
        $this->draft_pack['word_count'] = str_word_count( wp_strip_all_tags( $content ) );
        
        // Generate excerpt
        $this->draft_pack['excerpt'] = tsa_generate_meta_description( $content, 300 );
    }

    /**
     * Write content without AI (template-based)
     *
     * @return string
     */
    private function write_content_without_ai() {
        $title = $this->research_pack['title'];
        $content = '';
        
        // Introduction
        $content .= '<p>' . $this->generate_intro_paragraph() . '</p>' . "\n\n";
        
        // Section 1: Sekilas Tentang
        $content .= '<h2>Sekilas Tentang ' . esc_html( $title ) . '</h2>' . "\n";
        $content .= '<p>' . $this->generate_overview_paragraph() . '</p>' . "\n\n";
        
        // Section 2: Sejarah
        $content .= '<h2>Sejarah dan Latar Belakang</h2>' . "\n";
        $content .= '<p>' . $this->generate_history_paragraph() . '</p>' . "\n\n";
        
        // Section 3: Lokasi
        $content .= '<h2>Lokasi dan Cara Menuju ' . esc_html( $title ) . '</h2>' . "\n";
        $content .= $this->generate_location_section();
        
        // Section 4: Harga Tiket
        $content .= '<h2>Harga Tiket Masuk</h2>' . "\n";
        $content .= $this->generate_pricing_section();
        
        // Section 5: Jam Operasional
        $content .= '<h2>Jam Operasional</h2>' . "\n";
        $content .= $this->generate_hours_section();
        
        // Section 6: Fasilitas
        $content .= '<h2>Fasilitas yang Tersedia</h2>' . "\n";
        $content .= $this->generate_facilities_section();
        
        // Section 7: Aktivitas
        $content .= '<h2>Aktivitas dan Daya Tarik Utama</h2>' . "\n";
        $content .= $this->generate_activities_section();
        
        // Section 8: Tips
        $content .= '<h2>Tips Berkunjung ke ' . esc_html( $title ) . '</h2>' . "\n";
        $content .= $this->generate_tips_section();
        
        // Section 9: Rekomendasi Sekitar
        $content .= '<h2>Rekomendasi Tempat Sekitar</h2>' . "\n";
        $content .= '<p>Setelah mengunjungi ' . esc_html( $title ) . ', Anda juga bisa menjelajahi berbagai destinasi menarik lainnya di sekitar lokasi. Beberapa tempat wisata terdekat yang layak dikunjungi antara lain objek wisata alam, kuliner khas daerah, dan spot foto instagramable yang tak kalah menarik.</p>' . "\n\n";
        
        // Section 10: Kesimpulan
        $content .= '<h2>Kesimpulan</h2>' . "\n";
        $content .= '<p>' . esc_html( $title ) . ' merupakan destinasi wisata yang sangat layak untuk dikunjungi. Dengan berbagai daya tarik yang ditawarkan, fasilitas yang memadai, serta aksesibilitas yang mudah, tempat ini cocok untuk liburan bersama keluarga, teman, maupun pasangan. Pastikan untuk merencanakan kunjungan Anda dengan baik dan nikmati setiap momen yang ada.</p>' . "\n";
        
        return $content;
    }

    /**
     * Generate introduction paragraph
     *
     * @return string
     */
    private function generate_intro_paragraph() {
        $title = $this->research_pack['title'];
        $year = date( 'Y' );
        
        $intros = array(
            "Mencari destinasi wisata yang menarik untuk dikunjungi? {$title} bisa menjadi pilihan tepat untuk liburan Anda di tahun {$year}. Destinasi ini menawarkan pengalaman wisata yang tak terlupakan dengan berbagai daya tarik yang memukau.",
            "{$title} adalah salah satu destinasi wisata yang wajib masuk dalam daftar kunjungan Anda. Dengan keindahan alam yang memesona dan berbagai fasilitas yang tersedia, tempat ini menjadi favorit wisatawan lokal maupun mancanegara.",
            "Ingin tahu lebih banyak tentang {$title}? Artikel ini akan membahas secara lengkap mulai dari lokasi, harga tiket, jam operasional, hingga tips berkunjung yang berguna untuk perjalanan Anda.",
        );
        
        return $intros[ array_rand( $intros ) ];
    }

    /**
     * Generate overview paragraph
     *
     * @return string
     */
    private function generate_overview_paragraph() {
        $title = $this->research_pack['title'];
        $facts = $this->research_pack['facts'] ?? array();
        
        $overview = "{$title} merupakan salah satu destinasi wisata yang menarik perhatian banyak pengunjung. ";
        
        if ( ! empty( $facts ) ) {
            $overview .= implode( ' ', array_slice( $facts, 0, 3 ) ) . ' ';
        }
        
        $overview .= "Tempat ini menawarkan pengalaman wisata yang unik dan berbeda dari destinasi lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda.";
        
        return $overview;
    }

    /**
     * Generate history paragraph
     *
     * @return string
     */
    private function generate_history_paragraph() {
        $title = $this->research_pack['title'];
        
        return "{$title} memiliki sejarah yang menarik untuk diketahui. Destinasi ini telah menjadi bagian penting dari warisan budaya dan pariwisata daerah setempat. Seiring berjalannya waktu, tempat ini terus berkembang dan semakin populer di kalangan wisatawan yang mencari pengalaman wisata yang autentik dan berkesan.";
    }

    /**
     * Generate location section
     *
     * @return string
     */
    private function generate_location_section() {
        $title = $this->research_pack['title'];
        $location = $this->research_pack['location_info'] ?? '';
        
        $content = '<p>';
        
        if ( ! empty( $location ) ) {
            $content .= esc_html( $title ) . ' berlokasi di ' . esc_html( $location ) . '. ';
        } else {
            $content .= esc_html( $title ) . ' dapat dijangkau dengan berbagai moda transportasi. ';
        }
        
        $content .= 'Untuk mencapai lokasi ini, Anda bisa menggunakan kendaraan pribadi maupun transportasi umum. Berikut beberapa pilihan akses menuju lokasi:</p>' . "\n";
        
        $content .= '<ul>' . "\n";
        $content .= '<li><strong>Kendaraan Pribadi:</strong> Gunakan aplikasi navigasi seperti Google Maps atau Waze untuk panduan rute terbaik.</li>' . "\n";
        $content .= '<li><strong>Transportasi Umum:</strong> Tersedia angkutan umum dari pusat kota menuju lokasi wisata.</li>' . "\n";
        $content .= '<li><strong>Ojek Online:</strong> Layanan ojek online seperti Gojek dan Grab tersedia untuk kemudahan akses.</li>' . "\n";
        $content .= '</ul>' . "\n\n";
        
        return $content;
    }

    /**
     * Generate pricing section
     *
     * @return string
     */
    private function generate_pricing_section() {
        $title = $this->research_pack['title'];
        $price = $this->research_pack['pricing_info'] ?? '';
        
        $content = '<p>Berikut informasi harga tiket masuk ' . esc_html( $title ) . ':</p>' . "\n";
        
        $content .= '<table>' . "\n";
        $content .= '<thead><tr><th>Kategori</th><th>Harga</th></tr></thead>' . "\n";
        $content .= '<tbody>' . "\n";
        
        if ( ! empty( $price ) ) {
            $content .= '<tr><td>Tiket Masuk</td><td>' . esc_html( $price ) . '</td></tr>' . "\n";
        } else {
            $content .= '<tr><td>Dewasa</td><td>Hubungi pengelola untuk info terbaru</td></tr>' . "\n";
            $content .= '<tr><td>Anak-anak</td><td>Hubungi pengelola untuk info terbaru</td></tr>' . "\n";
        }
        
        $content .= '</tbody>' . "\n";
        $content .= '</table>' . "\n\n";
        
        $content .= '<p><em>Catatan: Harga tiket dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola atau mengecek informasi terbaru sebelum berkunjung.</em></p>' . "\n\n";
        
        return $content;
    }

    /**
     * Generate hours section
     *
     * @return string
     */
    private function generate_hours_section() {
        $hours = $this->research_pack['hours_info'] ?? '';
        
        $content = '<p>';
        
        if ( ! empty( $hours ) ) {
            $content .= 'Jam operasional: ' . esc_html( $hours ) . '. ';
        }
        
        $content .= 'Untuk informasi jam operasional terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi destinasi ini.</p>' . "\n\n";
        
        $content .= '<p><strong>Tips:</strong> Datanglah di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman.</p>' . "\n\n";
        
        return $content;
    }

    /**
     * Generate facilities section
     *
     * @return string
     */
    private function generate_facilities_section() {
        $content = '<p>Untuk kenyamanan pengunjung, tersedia berbagai fasilitas di lokasi wisata ini:</p>' . "\n";
        
        $content .= '<ul>' . "\n";
        $content .= '<li>Area parkir yang luas</li>' . "\n";
        $content .= '<li>Toilet umum</li>' . "\n";
        $content .= '<li>Mushola/tempat ibadah</li>' . "\n";
        $content .= '<li>Warung makan dan minuman</li>' . "\n";
        $content .= '<li>Spot foto instagramable</li>' . "\n";
        $content .= '<li>Pusat informasi wisata</li>' . "\n";
        $content .= '</ul>' . "\n\n";
        
        return $content;
    }

    /**
     * Generate activities section
     *
     * @return string
     */
    private function generate_activities_section() {
        $title = $this->research_pack['title'];
        
        $content = '<p>Ada banyak aktivitas menarik yang bisa Anda lakukan saat berkunjung ke ' . esc_html( $title ) . ':</p>' . "\n";
        
        $content .= '<ol>' . "\n";
        $content .= '<li><strong>Menikmati Pemandangan:</strong> Abadikan momen indah dengan latar belakang pemandangan yang memukau.</li>' . "\n";
        $content .= '<li><strong>Fotografi:</strong> Berbagai spot foto menarik tersedia untuk mengabadikan kenangan liburan Anda.</li>' . "\n";
        $content .= '<li><strong>Wisata Kuliner:</strong> Cicipi berbagai makanan dan minuman khas yang tersedia di sekitar lokasi.</li>' . "\n";
        $content .= '<li><strong>Bersantai:</strong> Nikmati suasana tenang dan sejuk untuk melepas penat dari rutinitas sehari-hari.</li>' . "\n";
        $content .= '<li><strong>Edukasi:</strong> Pelajari sejarah dan keunikan destinasi ini melalui informasi yang tersedia.</li>' . "\n";
        $content .= '</ol>' . "\n\n";
        
        return $content;
    }

    /**
     * Generate tips section
     *
     * @return string
     */
    private function generate_tips_section() {
        $title = $this->research_pack['title'];
        $tips = $this->research_pack['tips'] ?? array();
        
        $content = '<p>Berikut beberapa tips yang berguna untuk kunjungan Anda:</p>' . "\n";
        
        $content .= '<ul>' . "\n";
        
        // Add tips from research if available
        if ( ! empty( $tips ) ) {
            foreach ( array_slice( $tips, 0, 3 ) as $tip ) {
                $content .= '<li>' . esc_html( $tip ) . '</li>' . "\n";
            }
        }
        
        // Add default tips
        $content .= '<li>Datang di pagi hari untuk menghindari keramaian dan cuaca yang terik.</li>' . "\n";
        $content .= '<li>Bawa perlengkapan seperti topi, sunscreen, dan air minum yang cukup.</li>' . "\n";
        $content .= '<li>Kenakan pakaian dan alas kaki yang nyaman untuk berjalan.</li>' . "\n";
        $content .= '<li>Jaga kebersihan dan buang sampah pada tempatnya.</li>' . "\n";
        $content .= '<li>Patuhi peraturan yang berlaku di lokasi wisata.</li>' . "\n";
        $content .= '</ul>' . "\n\n";
        
        return $content;
    }

    /**
     * Write content with AI
     *
     * @return string
     */
    private function write_content_with_ai() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $api_endpoint = tsa_get_option( 'openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        $title = $this->research_pack['title'];
        $target_words = $this->settings['target_words'];
        
        // Prepare research data for AI
        $research_summary = "Topik: " . $title . "\n";
        $research_summary .= "Lokasi: " . ( $this->research_pack['location_info'] ?? 'Tidak tersedia' ) . "\n";
        $research_summary .= "Harga: " . ( $this->research_pack['pricing_info'] ?? 'Tidak tersedia' ) . "\n";
        $research_summary .= "Jam Buka: " . ( $this->research_pack['hours_info'] ?? 'Tidak tersedia' ) . "\n";
        $research_summary .= "Fakta: " . implode( '; ', array_slice( $this->research_pack['facts'] ?? array(), 0, 5 ) ) . "\n";
        $research_summary .= "Tips: " . implode( '; ', array_slice( $this->research_pack['tips'] ?? array(), 0, 3 ) ) . "\n";
        
        $prompt = "Buatlah artikel SEO-friendly tentang destinasi wisata \"{$title}\" dalam Bahasa Indonesia dengan panjang sekitar {$target_words} kata.

Data penelitian:
{$research_summary}

Struktur artikel harus mencakup:
1. Paragraf pembuka yang menarik
2. Sekilas tentang destinasi
3. Sejarah dan latar belakang
4. Lokasi dan cara menuju lokasi
5. Harga tiket masuk (dalam format tabel jika memungkinkan)
6. Jam operasional
7. Fasilitas yang tersedia
8. Aktivitas dan daya tarik utama
9. Tips berkunjung
10. Rekomendasi tempat sekitar
11. Kesimpulan

Gunakan heading H2 untuk setiap section utama. Tulis dengan gaya informatif namun engaging. Sertakan informasi praktis yang berguna bagi pembaca. Format output dalam HTML.";

        tsa_log_job( $this->job_id, 'Writer Agent: Generating content with AI...' );

        $response = wp_remote_post( $api_endpoint, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'Kamu adalah penulis konten wisata profesional yang ahli dalam SEO. Tulis artikel yang informatif, menarik, dan original. Gunakan Bahasa Indonesia yang baik dan benar.',
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Writer Agent: AI error - ' . $response->get_error_message() . '. Falling back to template.' );
            return $this->write_content_without_ai();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            tsa_log_job( $this->job_id, 'Writer Agent: AI content generated successfully.' );
            return $data['choices'][0]['message']['content'];
        }

        tsa_log_job( $this->job_id, 'Writer Agent: AI response invalid. Falling back to template.' );
        return $this->write_content_without_ai();
    }

    /**
     * Handle category mapping/creation
     */
    private function handle_categories() {
        $title = strtolower( $this->research_pack['title'] );
        
        // Detect category based on keywords
        $category_map = array(
            'wisata alam' => array( 'pantai', 'gunung', 'air terjun', 'danau', 'hutan', 'taman nasional', 'bukit' ),
            'wisata budaya' => array( 'candi', 'museum', 'keraton', 'pura', 'masjid', 'gereja', 'tradisional' ),
            'wisata kuliner' => array( 'kuliner', 'makanan', 'restoran', 'cafe', 'warung', 'masakan' ),
            'wisata keluarga' => array( 'taman bermain', 'waterpark', 'kebun binatang', 'aquarium', 'theme park' ),
            'wisata religi' => array( 'masjid', 'gereja', 'pura', 'vihara', 'klenteng', 'ziarah' ),
        );
        
        $detected_category = 'Wisata';
        
        foreach ( $category_map as $category => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $title, $keyword ) !== false ) {
                    $detected_category = ucwords( $category );
                    break 2;
                }
            }
        }
        
        // Check if category exists
        $category = get_term_by( 'name', $detected_category, 'category' );
        
        if ( $category ) {
            $this->draft_pack['category_id'] = $category->term_id;
            $this->draft_pack['category_name'] = $category->name;
        } else {
            // Category will be created when post is pushed
            $this->draft_pack['category_id'] = 0;
            $this->draft_pack['category_name'] = $detected_category;
        }
        
        tsa_log_job( $this->job_id, 'Writer Agent: Category set to "' . $detected_category . '".' );
    }

    /**
     * Generate tags
     */
    private function generate_tags() {
        $title = $this->research_pack['title'];
        $keywords = $this->research_pack['keywords'] ?? array();
        
        // Generate tags from title and keywords
        $tags = array();
        
        // Add title words as tags
        $title_words = explode( ' ', $title );
        foreach ( $title_words as $word ) {
            if ( strlen( $word ) > 3 ) {
                $tags[] = ucfirst( strtolower( $word ) );
            }
        }
        
        // Add keywords as tags
        foreach ( array_slice( $keywords, 0, 5 ) as $keyword ) {
            $tags[] = ucfirst( strtolower( $keyword ) );
        }
        
        // Add common travel tags
        $common_tags = array( 'Wisata', 'Travel', 'Liburan', 'Indonesia', date( 'Y' ) );
        $tags = array_merge( $tags, $common_tags );
        
        // Remove duplicates and limit to 10
        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 10 );
        
        $this->draft_pack['tag_names'] = $tags;
        
        tsa_log_job( $this->job_id, 'Writer Agent: Generated ' . count( $tags ) . ' tags.' );
    }

    /**
     * Find internal links from existing posts
     */
    private function find_internal_links() {
        $keywords = $this->research_pack['keywords'] ?? array();
        $internal_links = array();
        
        // Search for related posts
        foreach ( array_slice( $keywords, 0, 5 ) as $keyword ) {
            $posts = get_posts( array(
                'post_type' => 'post',
                'post_status' => 'publish',
                's' => $keyword,
                'posts_per_page' => 2,
            ) );
            
            foreach ( $posts as $post ) {
                $internal_links[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => get_permalink( $post->ID ),
                    'keyword' => $keyword,
                );
            }
        }
        
        // Remove duplicates
        $unique_links = array();
        $seen_ids = array();
        foreach ( $internal_links as $link ) {
            if ( ! in_array( $link['id'], $seen_ids, true ) ) {
                $unique_links[] = $link;
                $seen_ids[] = $link['id'];
            }
        }
        
        $this->draft_pack['internal_links'] = array_slice( $unique_links, 0, 5 );
        
        tsa_log_job( $this->job_id, 'Writer Agent: Found ' . count( $this->draft_pack['internal_links'] ) . ' internal link suggestions.' );
    }

    /**
     * Generate FAQ section
     */
    private function generate_faq() {
        $title = $this->research_pack['title'];
        $questions = $this->research_pack['faq_questions'] ?? array();
        
        $faq = array();
        
        // Generate answers for each question
        foreach ( array_slice( $questions, 0, 5 ) as $question ) {
            $answer = $this->generate_faq_answer( $question );
            $faq[] = array(
                'question' => $question,
                'answer' => $answer,
            );
        }
        
        $this->draft_pack['faq'] = $faq;
    }

    /**
     * Generate FAQ answer
     *
     * @param string $question Question
     * @return string
     */
    private function generate_faq_answer( $question ) {
        $title = $this->research_pack['title'];
        $question_lower = strtolower( $question );
        
        // Pattern-based answers
        if ( strpos( $question_lower, 'harga' ) !== false || strpos( $question_lower, 'tiket' ) !== false ) {
            $price = $this->research_pack['pricing_info'] ?? '';
            if ( ! empty( $price ) ) {
                return "Harga tiket masuk {$title} adalah {$price}. Harga dapat berubah sewaktu-waktu, disarankan untuk mengecek informasi terbaru sebelum berkunjung.";
            }
            return "Untuk informasi harga tiket terbaru, silakan hubungi pihak pengelola atau cek media sosial resmi {$title}.";
        }
        
        if ( strpos( $question_lower, 'jam' ) !== false || strpos( $question_lower, 'buka' ) !== false ) {
            $hours = $this->research_pack['hours_info'] ?? '';
            if ( ! empty( $hours ) ) {
                return "{$title} buka pada {$hours}. Disarankan untuk datang di pagi hari untuk menghindari keramaian.";
            }
            return "Jam operasional dapat bervariasi. Silakan hubungi pihak pengelola untuk informasi jam buka terkini.";
        }
        
        if ( strpos( $question_lower, 'cara' ) !== false || strpos( $question_lower, 'menuju' ) !== false ) {
            $location = $this->research_pack['location_info'] ?? '';
            if ( ! empty( $location ) ) {
                return "{$title} berlokasi di {$location}. Anda bisa menggunakan kendaraan pribadi atau transportasi umum untuk mencapai lokasi ini.";
            }
            return "Anda bisa menggunakan aplikasi navigasi seperti Google Maps untuk panduan rute menuju {$title}.";
        }
        
        // Default answer
        return "Untuk informasi lebih detail, silakan baca artikel lengkap di atas atau hubungi pihak pengelola {$title}.";
    }

    /**
     * Generate schema data
     */
    private function generate_schema() {
        $title = $this->research_pack['title'];
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'TouristAttraction',
            'name' => $title,
            'description' => $this->draft_pack['meta_description'],
        );
        
        if ( ! empty( $this->research_pack['location_info'] ) ) {
            $schema['address'] = array(
                '@type' => 'PostalAddress',
                'addressLocality' => $this->research_pack['location_info'],
                'addressCountry' => 'ID',
            );
        }
        
        if ( ! empty( $this->research_pack['hours_info'] ) ) {
            $schema['openingHours'] = $this->research_pack['hours_info'];
        }
        
        // Add FAQ schema
        if ( ! empty( $this->draft_pack['faq'] ) ) {
            $faq_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array(),
            );
            
            foreach ( $this->draft_pack['faq'] as $item ) {
                $faq_schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ),
                );
            }
            
            $this->draft_pack['schema_data'] = array(
                'tourist_attraction' => $schema,
                'faq' => $faq_schema,
            );
        } else {
            $this->draft_pack['schema_data'] = array(
                'tourist_attraction' => $schema,
            );
        }
    }

    /**
     * Enhance outline with AI
     *
     * @param array $outline Default outline
     * @return array
     */
    private function enhance_outline_with_ai( $outline ) {
        // For now, return default outline
        // AI enhancement can be added here
        return $outline;
    }

    /**
     * Check if AI API is available
     *
     * @return bool
     */
    private function has_ai_api() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        return ! empty( $api_key );
    }

    /**
     * Get the draft pack
     *
     * @return array
     */
    public function get_draft_pack() {
        return $this->draft_pack;
    }
}
