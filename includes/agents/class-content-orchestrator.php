<?php
/**
 * Content Orchestrator - 5 AI Writers Coordinator
 *
 * Coordinates the 5 AI Writers to produce comprehensive, high-quality
 * travel articles with 700-2000+ words.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 */

namespace TravelSEO_Autopublisher\Agents;

use TravelSEO_Autopublisher\Modules\Article_Structure;
use TravelSEO_Autopublisher\Spinner\Spinner;

/**
 * Content Orchestrator Class
 *
 * Manages the workflow of 5 specialized AI Writers:
 * 1. The Analyst & Hook Master - Intro, overview, highlights
 * 2. The Historian & Storyteller - History, myths, cultural context
 * 3. The Practical Guide - Location, transport, prices, hours, facilities
 * 4. The Local Expert - Activities, tips, food, souvenirs
 * 5. The SEO & Closer - Itinerary, conclusion, FAQ
 */
class Content_Orchestrator {

    /**
     * Article structure manager
     *
     * @var Article_Structure
     */
    private $structure;

    /**
     * Text spinner
     *
     * @var Spinner
     */
    private $spinner;

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * AI Provider (openai, deepseek, free)
     *
     * @var string
     */
    private $ai_provider = 'free';

    /**
     * Generated content storage
     *
     * @var array
     */
    private $content_parts = array();

    /**
     * Processing log
     *
     * @var array
     */
    private $log = array();

    /**
     * Constructor
     */
    public function __construct() {
        require_once TSA_PLUGIN_DIR . 'includes/modules/class-article-structure.php';
        require_once TSA_PLUGIN_DIR . 'includes/spinner/class-spinner.php';

        $this->structure = new Article_Structure();
        $this->spinner = new Spinner();
        $this->settings = get_option( 'tsa_settings', array() );

        // Determine AI provider
        if ( ! empty( $this->settings['openai_api_key'] ) ) {
            $this->ai_provider = 'openai';
        } elseif ( ! empty( $this->settings['deepseek_api_key'] ) ) {
            $this->ai_provider = 'deepseek';
        } else {
            $this->ai_provider = 'free';
        }
    }

    /**
     * Generate complete article using 5 AI Writers
     *
     * @param string $keyword       Main keyword/title
     * @param string $content_type  Content type (destinasi, kuliner, hotel, umum)
     * @param array  $research_data Research data from scraper
     * @param array  $options       Additional options
     * @return array Generated article data
     */
    public function generate_article( $keyword, $content_type = 'destinasi', $research_data = array(), $options = array() ) {
        $this->log( "Starting article generation for: {$keyword}" );
        $this->log( "Content type: {$content_type}" );
        $this->log( "AI Provider: {$this->ai_provider}" );

        $defaults = array(
            'spin_content' => true,
            'spin_intensity' => 50,
            'preserve_keywords' => array( $keyword ),
            'location' => '',
        );
        $options = array_merge( $defaults, $options );

        // Get article structure
        $article_structure = $this->structure->get_structure( $content_type );
        $this->log( "Using structure: {$article_structure['name']}" );

        // Initialize content parts
        $this->content_parts = array();
        $total_words = 0;

        // Process each AI Writer (1-5)
        for ( $ai_writer = 1; $ai_writer <= 5; $ai_writer++ ) {
            $this->log( "=== AI Writer #{$ai_writer} Starting ===" );

            $sections = $this->structure->get_sections_for_ai_writer( $content_type, $ai_writer );

            foreach ( $sections as $section ) {
                $this->log( "Processing section: {$section['id']}" );

                // Generate content for this section
                $section_content = $this->generate_section_content(
                    $section,
                    $keyword,
                    $research_data,
                    $options
                );

                if ( ! empty( $section_content ) ) {
                    // Apply spinning if enabled
                    if ( $options['spin_content'] ) {
                        $original_content = $section_content;
                        $section_content = $this->spinner->spin( $section_content, array(
                            'intensity' => $options['spin_intensity'],
                            'preserve_keywords' => $options['preserve_keywords'],
                        ) );

                        $stats = $this->spinner->get_stats( $original_content, $section_content );
                        $this->log( "Spinning stats - Uniqueness: {$stats['uniqueness_percent']}%" );
                    }

                    $word_count = str_word_count( strip_tags( $section_content ) );
                    $total_words += $word_count;

                    $this->content_parts[ $section['id'] ] = array(
                        'ai_writer' => $ai_writer,
                        'section' => $section,
                        'content' => $section_content,
                        'word_count' => $word_count,
                    );

                    $this->log( "Section '{$section['id']}' completed: {$word_count} words" );
                }
            }

            $this->log( "=== AI Writer #{$ai_writer} Completed ===" );
        }

        // Assemble final article
        $final_article = $this->assemble_article( $keyword, $content_type, $options );

        // Generate metadata
        $metadata = $this->generate_metadata( $keyword, $final_article, $research_data );

        $this->log( "Article generation completed. Total words: {$total_words}" );

        return array(
            'success' => true,
            'keyword' => $keyword,
            'content_type' => $content_type,
            'article' => $final_article,
            'metadata' => $metadata,
            'word_count' => $total_words,
            'sections' => $this->content_parts,
            'log' => $this->log,
        );
    }

    /**
     * Generate content for a specific section
     *
     * @param array  $section       Section definition
     * @param string $keyword       Main keyword
     * @param array  $research_data Research data
     * @param array  $options       Options
     * @return string Generated content
     */
    private function generate_section_content( $section, $keyword, $research_data, $options ) {
        // Build the prompt
        $prompt = $this->build_section_prompt( $section, $keyword, $research_data, $options );

        // Generate content based on AI provider
        switch ( $this->ai_provider ) {
            case 'openai':
                return $this->generate_with_openai( $prompt, $section );

            case 'deepseek':
                return $this->generate_with_deepseek( $prompt, $section );

            case 'free':
            default:
                return $this->generate_with_templates( $section, $keyword, $research_data, $options );
        }
    }

    /**
     * Build prompt for section generation
     *
     * @param array  $section       Section definition
     * @param string $keyword       Main keyword
     * @param array  $research_data Research data
     * @param array  $options       Options
     * @return string Prompt
     */
    private function build_section_prompt( $section, $keyword, $research_data, $options ) {
        $year = date( 'Y' );
        $location = $options['location'] ?? '';

        // Replace placeholders in title
        $title = str_replace(
            array( '{keyword}', '{year}', '{lokasi}' ),
            array( $keyword, $year, $location ),
            $section['title'] ?? ''
        );

        $prompt = "Kamu adalah penulis konten wisata profesional Indonesia dengan pengalaman 10+ tahun.\n\n";

        // AI Writer persona based on number
        $personas = array(
            1 => "Kamu adalah 'The Hook Master' - ahli membuat pembuka yang memikat dan membuat pembaca penasaran.",
            2 => "Kamu adalah 'The Storyteller' - ahli menceritakan sejarah dan kisah menarik dengan gaya yang engaging.",
            3 => "Kamu adalah 'The Practical Guide' - ahli memberikan informasi praktis yang akurat dan berguna.",
            4 => "Kamu adalah 'The Local Expert' - kamu tahu tips rahasia yang hanya diketahui warga lokal.",
            5 => "Kamu adalah 'The SEO Closer' - ahli merangkum dan membuat pembaca mengambil tindakan.",
        );

        $prompt .= $personas[ $section['ai_writer'] ] . "\n\n";

        $prompt .= "=== TUGAS ===\n";
        $prompt .= $section['prompt_instruction'] . "\n\n";

        $prompt .= "=== DETAIL ===\n";
        $prompt .= "- Keyword utama: {$keyword}\n";
        if ( $title ) {
            $prompt .= "- Judul section (H2): {$title}\n";
        }
        $prompt .= "- Target kata: {$section['min_words']} - {$section['max_words']} kata\n";
        $prompt .= "- Tahun: {$year}\n";
        if ( $location ) {
            $prompt .= "- Lokasi: {$location}\n";
        }
        $prompt .= "\n";

        // Add research data if available
        if ( ! empty( $research_data ) ) {
            $prompt .= "=== DATA RISET TERSEDIA ===\n";

            if ( ! empty( $research_data['facts'] ) ) {
                $prompt .= "Fakta:\n";
                foreach ( array_slice( $research_data['facts'], 0, 5 ) as $fact ) {
                    $prompt .= "- {$fact}\n";
                }
            }

            if ( ! empty( $research_data['prices'] ) && in_array( $section['type'], array( 'pricing', 'overview' ) ) ) {
                $prompt .= "Info Harga:\n";
                foreach ( $research_data['prices'] as $price ) {
                    $prompt .= "- {$price}\n";
                }
            }

            if ( ! empty( $research_data['hours'] ) && $section['type'] === 'hours' ) {
                $prompt .= "Jam Operasional:\n";
                foreach ( $research_data['hours'] as $hour ) {
                    $prompt .= "- {$hour}\n";
                }
            }

            if ( ! empty( $research_data['facilities'] ) && $section['type'] === 'facilities' ) {
                $prompt .= "Fasilitas:\n";
                foreach ( $research_data['facilities'] as $facility ) {
                    $prompt .= "- {$facility}\n";
                }
            }

            $prompt .= "\n";
        }

        $prompt .= "=== ATURAN PENULISAN ===\n";
        $prompt .= "1. Gunakan Bahasa Indonesia yang baik, natural, dan mudah dipahami\n";
        $prompt .= "2. Tulis dengan gaya informatif namun engaging (tidak kaku)\n";
        $prompt .= "3. HINDARI kalimat klise seperti 'tidak dapat dipungkiri', 'di era modern ini'\n";
        $prompt .= "4. HINDARI filler words yang tidak perlu\n";
        $prompt .= "5. Berikan informasi yang AKURAT dan BERMANFAAT\n";
        $prompt .= "6. Gunakan variasi kalimat (panjang-pendek)\n";
        $prompt .= "7. Sisipkan keyword secara natural (jangan dipaksakan)\n";

        // Format-specific instructions
        if ( ! empty( $section['has_table'] ) ) {
            $prompt .= "8. WAJIB sertakan tabel dalam format Markdown\n";
        }

        if ( $section['type'] === 'faq' ) {
            $prompt .= "8. Format FAQ dengan pertanyaan sebagai ### (H3) dan jawaban sebagai paragraf\n";
        }

        if ( $section['type'] === 'highlights' ) {
            $prompt .= "8. Gunakan format bullet points dengan penjelasan singkat\n";
        }

        $prompt .= "\n=== OUTPUT ===\n";
        $prompt .= "Tulis konten sekarang (HANYA konten, tanpa komentar tambahan):\n";

        return $prompt;
    }

    /**
     * Generate content using OpenAI
     *
     * @param string $prompt  Prompt
     * @param array  $section Section definition
     * @return string Generated content
     */
    private function generate_with_openai( $prompt, $section ) {
        $api_key = $this->settings['openai_api_key'];
        $model = $this->settings['openai_model'] ?? 'gpt-3.5-turbo';

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'Kamu adalah penulis konten wisata profesional Indonesia. Tulis dalam Bahasa Indonesia yang natural dan engaging.',
                    ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'temperature' => 0.7,
                'max_tokens' => $section['max_words'] * 2, // Approximate tokens
            ) ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'OpenAI API error: ' . $response->get_error_message(), 'error' );
            return $this->generate_with_templates( $section, '', array(), array() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            return trim( $body['choices'][0]['message']['content'] );
        }

        $this->log( 'OpenAI returned no content, falling back to templates', 'warning' );
        return $this->generate_with_templates( $section, '', array(), array() );
    }

    /**
     * Generate content using DeepSeek
     *
     * @param string $prompt  Prompt
     * @param array  $section Section definition
     * @return string Generated content
     */
    private function generate_with_deepseek( $prompt, $section ) {
        $api_key = $this->settings['deepseek_api_key'];

        $response = wp_remote_post( 'https://api.deepseek.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'Kamu adalah penulis konten wisata profesional Indonesia. Tulis dalam Bahasa Indonesia yang natural dan engaging.',
                    ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'temperature' => 0.7,
                'max_tokens' => $section['max_words'] * 2,
            ) ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'DeepSeek API error: ' . $response->get_error_message(), 'error' );
            return $this->generate_with_templates( $section, '', array(), array() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            return trim( $body['choices'][0]['message']['content'] );
        }

        $this->log( 'DeepSeek returned no content, falling back to templates', 'warning' );
        return $this->generate_with_templates( $section, '', array(), array() );
    }

    /**
     * Generate content using built-in templates (FREE mode)
     *
     * @param array  $section       Section definition
     * @param string $keyword       Main keyword
     * @param array  $research_data Research data
     * @param array  $options       Options
     * @return string Generated content
     */
    private function generate_with_templates( $section, $keyword, $research_data, $options ) {
        $year = date( 'Y' );
        $location = $options['location'] ?? '';

        // Template-based content generation
        $templates = $this->get_section_templates();

        $template_key = $section['type'];
        if ( ! isset( $templates[ $template_key ] ) ) {
            $template_key = 'default';
        }

        $template = $templates[ $template_key ];

        // Replace placeholders
        $content = str_replace(
            array( '{keyword}', '{year}', '{lokasi}', '{KEYWORD}', '{YEAR}' ),
            array( $keyword, $year, $location ?: 'Indonesia', strtoupper( $keyword ), $year ),
            $template
        );

        // Insert research data if available
        if ( ! empty( $research_data ) ) {
            $content = $this->inject_research_data( $content, $section['type'], $research_data );
        }

        return $content;
    }

    /**
     * Get section templates for FREE mode
     *
     * @return array Templates
     */
    private function get_section_templates() {
        return array(
            'intro' => "Ingin tahu lebih banyak tentang {keyword}? Artikel ini akan membahas secara lengkap mulai dari lokasi, harga tiket, jam operasional, hingga tips berkunjung yang berguna untuk perjalanan Anda. {keyword} merupakan salah satu destinasi yang semakin populer dan menarik perhatian banyak wisatawan. Tempat ini menawarkan pengalaman wisata yang unik dan berbeda dari destinasi lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda.",

            'overview' => "{keyword} merupakan salah satu destinasi wisata yang menarik perhatian banyak pengunjung. Tempat ini menawarkan pengalaman wisata yang unik dan berbeda dari destinasi lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda.\n\nBerlokasi di kawasan yang strategis, {keyword} mudah dijangkau dari berbagai arah. Keindahan alam dan suasana yang ditawarkan menjadi daya tarik utama yang membuat wisatawan terus berdatangan. Baik untuk liburan keluarga, gathering bersama teman, maupun solo traveling, tempat ini mampu mengakomodasi berbagai kebutuhan wisata Anda.",

            'highlights' => "Berikut adalah beberapa daya tarik utama yang membuat {keyword} begitu istimewa:\n\n- **Pemandangan Alam yang Memukau**: Nikmati panorama indah yang akan memanjakan mata Anda\n- **Suasana yang Tenang dan Asri**: Jauh dari hiruk pikuk kota, cocok untuk relaksasi\n- **Spot Foto Instagramable**: Berbagai sudut menarik untuk mengabadikan momen\n- **Fasilitas Lengkap**: Tersedia berbagai fasilitas untuk kenyamanan pengunjung\n- **Akses Mudah**: Lokasi strategis yang mudah dijangkau dengan berbagai moda transportasi",

            'history' => "{keyword} memiliki sejarah yang menarik untuk diketahui. Destinasi ini telah menjadi bagian penting dari warisan budaya dan pariwisata daerah setempat. Seiring berjalannya waktu, tempat ini terus berkembang dan semakin populer di kalangan wisatawan yang mencari pengalaman wisata yang autentik dan berkesan.\n\nPerkembangan {keyword} tidak lepas dari peran masyarakat lokal yang turut menjaga dan melestarikan keindahan serta nilai-nilai budaya yang ada. Hal ini menjadikan destinasi ini tidak hanya menawarkan keindahan visual, tetapi juga kekayaan cerita dan makna di baliknya.",

            'stories' => "Ada beberapa fakta menarik tentang {keyword} yang mungkin belum banyak diketahui:\n\n**Fakta Unik #1**: Tempat ini memiliki keunikan tersendiri yang tidak ditemukan di destinasi lain.\n\n**Fakta Unik #2**: Banyak pengunjung yang merasa terpesona dengan keindahan dan suasana yang ditawarkan.\n\n**Fakta Unik #3**: Destinasi ini sering menjadi lokasi favorit untuk berbagai kegiatan dan acara spesial.",

            'location' => "**Alamat Lengkap:**\n{keyword}\n[Alamat lengkap akan diupdate]\n\n**Koordinat GPS:**\nGunakan aplikasi navigasi seperti Google Maps atau Waze untuk panduan rute terbaik menuju lokasi.\n\n**Patokan Terdekat:**\nLokasi ini mudah ditemukan dengan mengikuti petunjuk arah yang tersedia di sepanjang jalan.",

            'transportation' => "### Kendaraan Pribadi\nGunakan aplikasi navigasi seperti Google Maps atau Waze untuk panduan rute terbaik. Kondisi jalan menuju lokasi umumnya baik dan mudah dilalui. Tersedia area parkir yang memadai untuk kendaraan roda dua maupun roda empat.\n\n### Transportasi Umum\nTersedia angkutan umum dari pusat kota menuju lokasi wisata. Anda bisa menggunakan bus atau angkot dengan rute yang melewati area sekitar destinasi.\n\n### Ojek Online\nLayanan ojek online seperti Gojek dan Grab tersedia untuk kemudahan akses. Pastikan untuk mengaktifkan GPS agar driver dapat menemukan lokasi dengan mudah.",

            'pricing' => "Berikut informasi harga tiket masuk {keyword}:\n\n| Kategori | Weekday | Weekend | Catatan |\n|----------|---------|---------|----------|\n| Dewasa | Rp 25.000 | Rp 30.000 | - |\n| Anak-anak | Rp 15.000 | Rp 20.000 | Usia 5-12 tahun |\n| Parkir Motor | Rp 5.000 | Rp 5.000 | - |\n| Parkir Mobil | Rp 10.000 | Rp 10.000 | - |\n\n*Catatan: Harga dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola atau mengecek informasi terbaru sebelum berkunjung.*",

            'hours' => "| Hari | Jam Buka | Jam Tutup |\n|------|----------|----------|\n| Senin - Jumat | 08.00 | 17.00 |\n| Sabtu & Minggu | 07.00 | 18.00 |\n| Hari Libur Nasional | 07.00 | 18.00 |\n\n**Tips:** Datanglah di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman.",

            'facilities' => "Fasilitas yang tersedia di {keyword}:\n\n✅ **Toilet/WC** - Bersih dan terawat\n✅ **Mushola** - Untuk ibadah pengunjung muslim\n✅ **Area Parkir** - Luas dan aman\n✅ **Warung Makan** - Menyediakan berbagai makanan dan minuman\n✅ **Toko Suvenir** - Oleh-oleh khas\n✅ **Gazebo/Tempat Istirahat** - Untuk bersantai\n✅ **Spot Foto** - Berbagai sudut instagramable\n\n*Fasilitas dapat berbeda tergantung kebijakan pengelola.*",

            'activities' => "Berikut aktivitas seru yang bisa Anda lakukan di {keyword}:\n\n**1. Menikmati Pemandangan**\nLuangkan waktu untuk menikmati keindahan alam yang ditawarkan. Bawa kamera untuk mengabadikan momen-momen indah.\n\n**2. Berfoto di Spot Instagramable**\nTersedia berbagai spot foto menarik yang cocok untuk feed Instagram Anda.\n\n**3. Piknik Bersama Keluarga**\nArea yang luas memungkinkan Anda untuk piknik bersama keluarga atau teman.\n\n**4. Eksplorasi Sekitar**\nJelajahi area sekitar untuk menemukan sudut-sudut tersembunyi yang menarik.\n\n**5. Kuliner Lokal**\nJangan lewatkan untuk mencicipi makanan khas yang tersedia di sekitar lokasi.",

            'insider_tips' => "Tips rahasia dari warga lokal untuk pengalaman terbaik di {keyword}:\n\n🔑 **Waktu Terbaik Berkunjung**: Datanglah di hari kerja atau pagi hari di akhir pekan untuk menghindari keramaian.\n\n🔑 **Bawa Perlengkapan**: Siapkan topi, sunblock, dan air minum yang cukup.\n\n🔑 **Pakaian Nyaman**: Gunakan pakaian dan alas kaki yang nyaman untuk berjalan.\n\n🔑 **Simpan Barang Berharga**: Jaga barang berharga Anda dengan baik.\n\n🔑 **Hormati Aturan**: Patuhi peraturan yang berlaku dan jaga kebersihan.",

            'food' => "Kuliner wajib coba di sekitar {keyword}:\n\n**1. Makanan Khas Lokal**\nNikmati cita rasa autentik masakan daerah yang tersedia di warung-warung sekitar lokasi. Harga mulai dari Rp 15.000 - Rp 50.000 per porsi.\n\n**2. Jajanan Tradisional**\nBerbagai jajanan tradisional tersedia untuk menemani perjalanan wisata Anda.\n\n**3. Minuman Segar**\nSegarkan diri dengan minuman khas seperti es kelapa muda atau jus buah segar.\n\n*Tips: Tanyakan rekomendasi kepada warga lokal untuk menemukan tempat makan terbaik!*",

            'souvenirs' => "Oleh-oleh khas yang bisa dibawa pulang dari {keyword}:\n\n🎁 **Kerajinan Tangan Lokal** - Rp 25.000 - Rp 100.000\nProduk kerajinan tangan khas daerah yang unik dan berkualitas.\n\n🎁 **Makanan Khas** - Rp 20.000 - Rp 75.000\nCamilan atau makanan khas yang bisa dijadikan buah tangan.\n\n🎁 **Suvenir Khas** - Rp 15.000 - Rp 50.000\nGantungan kunci, kaos, atau merchandise bertema destinasi.\n\n*Tersedia di toko suvenir di area wisata atau pasar terdekat.*",

            'itinerary' => "**Contoh Itinerary Half-Day (4-5 jam):**\n\n⏰ **08.00 - 08.30**: Perjalanan menuju lokasi\n⏰ **08.30 - 09.00**: Tiba di lokasi, parkir, dan beli tiket\n⏰ **09.00 - 10.30**: Eksplorasi area utama dan foto-foto\n⏰ **10.30 - 11.00**: Istirahat dan menikmati pemandangan\n⏰ **11.00 - 12.00**: Makan siang di warung sekitar\n⏰ **12.00 - 12.30**: Belanja oleh-oleh\n⏰ **12.30**: Perjalanan pulang\n\n*Sesuaikan jadwal dengan kebutuhan dan kondisi Anda.*",

            'accommodation' => "Rekomendasi penginapan di sekitar {keyword}:\n\n**Budget (Rp 150.000 - 300.000/malam)**\n- Guest house dan homestay lokal\n- Cocok untuk backpacker atau budget traveler\n\n**Mid-Range (Rp 300.000 - 600.000/malam)**\n- Hotel bintang 2-3 dengan fasilitas standar\n- Cocok untuk keluarga kecil\n\n**Premium (Rp 600.000+/malam)**\n- Resort atau hotel bintang 4-5\n- Fasilitas lengkap dan pelayanan prima\n\n*Tips: Booking lebih awal untuk mendapat harga terbaik, terutama saat musim liburan.*",

            'conclusion' => "{keyword} adalah destinasi yang wajib masuk dalam daftar kunjungan Anda. Dengan keindahan alam yang memukau, fasilitas yang memadai, dan berbagai aktivitas menarik yang bisa dilakukan, tempat ini menawarkan pengalaman wisata yang lengkap dan memuaskan.\n\nBaik untuk liburan keluarga, quality time bersama pasangan, maupun petualangan solo, {keyword} mampu mengakomodasi berbagai kebutuhan wisata Anda. Jadi, tunggu apa lagi? Segera rencanakan kunjungan Anda dan nikmati pesona {keyword} yang tak terlupakan!",

            'faq' => "### Apakah {keyword} cocok untuk anak-anak?\nYa, destinasi ini ramah anak dan cocok untuk liburan keluarga. Pastikan untuk selalu mengawasi anak-anak Anda.\n\n### Bolehkah membawa makanan dari luar?\nKebijakan ini dapat berbeda-beda. Sebaiknya konfirmasi terlebih dahulu kepada pihak pengelola.\n\n### Apakah ada penginapan di dekat lokasi?\nYa, tersedia berbagai pilihan penginapan dengan berbagai range harga di sekitar lokasi.\n\n### Kapan waktu terbaik untuk berkunjung?\nWaktu terbaik adalah di pagi hari atau hari kerja untuk menghindari keramaian.\n\n### Apakah tersedia pemandu wisata?\nUntuk informasi pemandu wisata, silakan hubungi pihak pengelola atau pusat informasi wisata setempat.",

            'default' => "{keyword} menawarkan pengalaman wisata yang menarik dan berkesan. Dengan berbagai daya tarik yang dimiliki, destinasi ini layak untuk dikunjungi dan dieksplorasi lebih lanjut.",
        );
    }

    /**
     * Inject research data into template content
     *
     * @param string $content       Template content
     * @param string $section_type  Section type
     * @param array  $research_data Research data
     * @return string Updated content
     */
    private function inject_research_data( $content, $section_type, $research_data ) {
        // Replace placeholder prices with actual data
        if ( $section_type === 'pricing' && ! empty( $research_data['prices'] ) ) {
            // Try to parse and inject actual prices
            foreach ( $research_data['prices'] as $price_info ) {
                if ( stripos( $price_info, 'dewasa' ) !== false ) {
                    $content = preg_replace( '/Rp\s*\d+\.?\d*\s*\|\s*Rp\s*\d+\.?\d*\s*\|\s*-\s*\|/i', $price_info . ' |', $content, 1 );
                }
            }
        }

        // Inject hours data
        if ( $section_type === 'hours' && ! empty( $research_data['hours'] ) ) {
            // Append actual hours info
            $content .= "\n\n**Informasi Terbaru:**\n";
            foreach ( $research_data['hours'] as $hour ) {
                $content .= "- {$hour}\n";
            }
        }

        // Inject facilities
        if ( $section_type === 'facilities' && ! empty( $research_data['facilities'] ) ) {
            $content .= "\n\n**Fasilitas Tambahan:**\n";
            foreach ( $research_data['facilities'] as $facility ) {
                $content .= "✅ {$facility}\n";
            }
        }

        return $content;
    }

    /**
     * Assemble final article from content parts
     *
     * @param string $keyword      Main keyword
     * @param string $content_type Content type
     * @param array  $options      Options
     * @return string Final article HTML/Markdown
     */
    private function assemble_article( $keyword, $content_type, $options ) {
        $year = date( 'Y' );
        $structure = $this->structure->get_structure( $content_type );

        $article = "# {$keyword}\n\n";
        $article .= "*Panduan lengkap {$keyword} {$year}. Info jam buka, fasilitas, harga tiket, dan tips berkunjung.*\n\n";

        // Assemble sections in order
        foreach ( $structure['sections'] as $section ) {
            if ( isset( $this->content_parts[ $section['id'] ] ) ) {
                $part = $this->content_parts[ $section['id'] ];

                // Add section title if exists
                if ( ! empty( $section['title'] ) ) {
                    $title = str_replace(
                        array( '{keyword}', '{year}' ),
                        array( $keyword, $year ),
                        $section['title']
                    );
                    $article .= "## {$title}\n\n";
                }

                // Add content
                $article .= $part['content'] . "\n\n";

                // Add separator between major sections
                if ( in_array( $section['id'], array( 'daya_tarik', 'mitos', 'fasilitas', 'oleh_oleh' ) ) ) {
                    $article .= "---\n\n";
                }
            }
        }

        return $article;
    }

    /**
     * Generate article metadata
     *
     * @param string $keyword       Main keyword
     * @param string $article       Article content
     * @param array  $research_data Research data
     * @return array Metadata
     */
    private function generate_metadata( $keyword, $article, $research_data ) {
        $year = date( 'Y' );

        // Generate meta title (max 60 chars)
        $meta_title = "{$keyword} {$year}: Panduan Lengkap, Harga & Tips";
        if ( strlen( $meta_title ) > 60 ) {
            $meta_title = "{$keyword} - Panduan Wisata Lengkap {$year}";
        }

        // Generate meta description (max 160 chars)
        $meta_description = "Panduan lengkap {$keyword} {$year}. Info harga tiket, jam buka, fasilitas, aktivitas seru, dan tips berkunjung dari warga lokal.";
        if ( strlen( $meta_description ) > 160 ) {
            $meta_description = substr( $meta_description, 0, 157 ) . '...';
        }

        // Generate focus keyword
        $focus_keyword = strtolower( $keyword );

        // Generate tags
        $tags = $this->generate_tags( $keyword, $article );

        // Generate categories suggestion
        $categories = $this->suggest_categories( $keyword );

        return array(
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
            'focus_keyword' => $focus_keyword,
            'tags' => $tags,
            'categories' => $categories,
            'word_count' => str_word_count( strip_tags( $article ) ),
            'reading_time' => ceil( str_word_count( strip_tags( $article ) ) / 200 ) . ' menit',
        );
    }

    /**
     * Generate tags from content
     *
     * @param string $keyword Main keyword
     * @param string $article Article content
     * @return array Tags (3-10)
     */
    private function generate_tags( $keyword, $article ) {
        $tags = array();

        // Add main keyword as tag
        $tags[] = $keyword;

        // Extract potential tags from keyword
        $keyword_parts = explode( ' ', $keyword );
        foreach ( $keyword_parts as $part ) {
            if ( strlen( $part ) > 3 ) {
                $tags[] = $part;
            }
        }

        // Add common travel tags based on content
        $travel_tags = array(
            'wisata' => array( 'wisata', 'traveling', 'liburan', 'jalan-jalan' ),
            'pantai' => array( 'pantai', 'beach', 'laut' ),
            'gunung' => array( 'gunung', 'hiking', 'pendakian' ),
            'kuliner' => array( 'kuliner', 'makanan', 'food' ),
            'hotel' => array( 'hotel', 'penginapan', 'staycation' ),
        );

        $content_lower = strtolower( $article );
        foreach ( $travel_tags as $key => $related ) {
            if ( stripos( $content_lower, $key ) !== false ) {
                $tags = array_merge( $tags, $related );
            }
        }

        // Unique and limit to 10
        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 10 );

        return $tags;
    }

    /**
     * Suggest categories based on keyword
     *
     * @param string $keyword Main keyword
     * @return array Suggested categories
     */
    private function suggest_categories( $keyword ) {
        $keyword_lower = strtolower( $keyword );
        $categories = array();

        // Category mapping
        $category_map = array(
            'Destinasi Wisata' => array( 'wisata', 'tempat', 'destinasi', 'pantai', 'gunung', 'danau', 'air terjun', 'taman', 'museum' ),
            'Kuliner' => array( 'kuliner', 'makanan', 'restoran', 'cafe', 'warung', 'rumah makan' ),
            'Penginapan' => array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay' ),
            'Tips Wisata' => array( 'tips', 'panduan', 'guide', 'cara' ),
            'Review' => array( 'review', 'ulasan', 'pengalaman' ),
        );

        foreach ( $category_map as $category => $keywords ) {
            foreach ( $keywords as $kw ) {
                if ( stripos( $keyword_lower, $kw ) !== false ) {
                    $categories[] = $category;
                    break;
                }
            }
        }

        // Default category if none matched
        if ( empty( $categories ) ) {
            $categories[] = 'Destinasi Wisata';
        }

        return array_unique( $categories );
    }

    /**
     * Add log entry
     *
     * @param string $message Log message
     * @param string $level   Log level (info, warning, error)
     */
    private function log( $message, $level = 'info' ) {
        $this->log[] = array(
            'time' => current_time( 'mysql' ),
            'level' => $level,
            'message' => $message,
        );

        // Also log to plugin log
        if ( function_exists( 'tsa_log' ) ) {
            tsa_log( $message, $level );
        }
    }

    /**
     * Get processing log
     *
     * @return array Log entries
     */
    public function get_log() {
        return $this->log;
    }
}
