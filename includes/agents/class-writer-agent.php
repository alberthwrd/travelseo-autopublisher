<?php
/**
 * Writer Agent V3 - Professional Article Writer with sekali.id Branding
 *
 * Features:
 * - sekali.id branding integration
 * - Flexible introduction (1-3 paragraphs)
 * - Internal links mandatory
 * - Natural content flow, minimal H2/H3
 * - 800-2000 words target
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use TravelSEO_Autopublisher\Modules\Article_Structure;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

/**
 * Writer Agent V3 Class
 */
class Writer_Agent {

    /**
     * Brand name
     */
    const BRAND_NAME = 'sekali.id';

    /**
     * Brand phrases for natural integration
     */
    private $brand_phrases = array(
        'sekali.id akan menyuguhkan informasi lengkap',
        'sekali.id telah merangkum semua yang perlu Anda ketahui',
        'sekali.id menyajikan panduan lengkap',
        'Tim sekali.id telah mengumpulkan informasi terbaru',
        'sekali.id akan membantu Anda merencanakan',
        'Melalui artikel ini sekali.id akan mengulas',
        'sekali.id hadir untuk memberikan panduan',
    );

    /**
     * Job ID
     */
    private $job_id;

    /**
     * Research pack from Agent 1
     */
    private $research_pack;

    /**
     * Job settings
     */
    private $settings;

    /**
     * Draft pack result
     */
    private $draft_pack;

    /**
     * Article Structure instance
     */
    private $article_structure;

    /**
     * Constructor
     */
    public function __construct( $job_id, $research_pack, $settings = array() ) {
        $this->job_id = $job_id;
        $this->research_pack = $research_pack;
        $this->settings = wp_parse_args( $settings, array(
            'target_words_min' => 800,
            'target_words_max' => 2000,
            'language' => 'id',
            'tone' => 'informative-friendly',
        ) );

        // Initialize draft pack
        $this->draft_pack = array(
            'title' => '',
            'slug' => '',
            'meta_title' => '',
            'meta_description' => '',
            'content' => '',
            'content_html' => '',
            'excerpt' => '',
            'sections' => array(),
            'category_id' => 0,
            'category_name' => '',
            'tag_ids' => array(),
            'tag_names' => array(),
            'internal_links' => array(),
            'faq' => array(),
            'word_count' => 0,
            'reading_time' => '',
            'created_at' => current_time( 'mysql' ),
        );

        // Load Article Structure
        require_once TSA_PLUGIN_DIR . 'includes/modules/class-article-structure.php';
        $this->article_structure = new Article_Structure();
    }

    /**
     * Run the writer process
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Writer Agent V3: Memulai pembuatan artikel profesional...' );
        tsa_update_job( $this->job_id, array( 'status' => 'drafting' ) );

        $title = $this->research_pack['title'];
        $content_type = $this->research_pack['content_type'] ?? $this->detect_content_type( $title );

        tsa_log_job( $this->job_id, "Writer Agent V3: Tipe konten: {$content_type}" );

        // Step 1: Generate article content
        $this->generate_article( $title, $content_type );

        // Step 2: Generate meta data
        $this->generate_meta_data( $title );

        // Step 3: Handle categories
        $this->handle_categories( $content_type );

        // Step 4: Generate tags
        $this->generate_tags( $title );

        // Step 5: Add internal links
        $this->add_internal_links();

        // Step 6: Convert to HTML
        $this->convert_to_html();

        tsa_log_job( $this->job_id, 'Writer Agent V3: Selesai. ' . $this->draft_pack['word_count'] . ' kata.' );

        return $this->draft_pack;
    }

    /**
     * Detect content type from title
     */
    private function detect_content_type( $title ) {
        $title_lower = strtolower( $title );
        
        $types = array(
            'kuliner' => array( 'makanan', 'kuliner', 'restoran', 'cafe', 'warung', 'nasi', 'mie', 'sate', 'bakso' ),
            'hotel' => array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel' ),
            'aktivitas' => array( 'rafting', 'diving', 'snorkeling', 'hiking', 'trekking', 'camping' ),
        );
        
        foreach ( $types as $type => $keywords ) {
            foreach ( $keywords as $kw ) {
                if ( strpos( $title_lower, $kw ) !== false ) {
                    return $type;
                }
            }
        }
        
        return 'destinasi';
    }

    /**
     * Generate article content
     */
    private function generate_article( $title, $content_type ) {
        tsa_log_job( $this->job_id, 'Writer Agent V3: Generating artikel...' );

        // Check if AI API is available
        if ( $this->has_ai_api() ) {
            $content = $this->generate_with_ai( $title, $content_type );
        } else {
            $content = $this->generate_without_ai( $title, $content_type );
        }

        $this->draft_pack['title'] = $title;
        $this->draft_pack['content'] = $content;
        $this->draft_pack['word_count'] = str_word_count( strip_tags( $content ) );
        $this->draft_pack['reading_time'] = ceil( $this->draft_pack['word_count'] / 200 ) . ' menit';
    }

    /**
     * Check if AI API is available
     */
    private function has_ai_api() {
        return ! empty( tsa_get_option( 'openai_api_key', '' ) );
    }

    /**
     * Generate article with AI
     */
    private function generate_with_ai( $title, $content_type ) {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $endpoint = tsa_get_option( 'openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );

        // Build research context
        $research_context = $this->build_research_context();

        // Build the prompt
        $prompt = $this->build_writing_prompt( $title, $content_type, $research_context );

        tsa_log_job( $this->job_id, 'Writer Agent V3: Memanggil AI untuk generate artikel...' );

        $response = wp_remote_post( $endpoint, array(
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
                        'content' => $this->get_system_prompt(),
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
            tsa_log_job( $this->job_id, 'Writer Agent V3: AI error - ' . $response->get_error_message() );
            return $this->generate_without_ai( $title, $content_type );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
            $content = $body['choices'][0]['message']['content'];
            tsa_log_job( $this->job_id, 'Writer Agent V3: AI berhasil generate artikel.' );
            return $content;
        }

        return $this->generate_without_ai( $title, $content_type );
    }

    /**
     * Get system prompt for AI
     */
    private function get_system_prompt() {
        return "Kamu adalah penulis artikel wisata profesional untuk website sekali.id. 

ATURAN PENULISAN WAJIB:
1. Gunakan branding 'sekali.id' di introduction dengan natural, contoh: 'sekali.id akan menyuguhkan informasi lengkap tentang...'
2. Introduction bisa 1-3 paragraf, fleksibel sesuai kebutuhan
3. Gunakan gaya bahasa yang informatif, friendly, dan personal
4. Gunakan 'Anda' untuk pembaca, 'sekali.id' atau 'kami' untuk penulis
5. JANGAN terlalu banyak H2/H3, maksimal 4-5 heading saja
6. Fokus pada paragraf yang padat dan informatif
7. WAJIB sertakan internal links dengan format [INTERNAL_LINK:keyword] di bagian akhir
8. Target 800-2000 kata
9. Gunakan tabel untuk data harga dan jam operasional
10. Tulis dengan gaya bercerita, bukan daftar poin berlebihan

FORMAT OUTPUT:
- Gunakan Markdown
- H2 untuk section utama (##)
- H3 hanya jika benar-benar perlu (###)
- Tabel dengan format Markdown
- Bold untuk penekanan penting";
    }

    /**
     * Build writing prompt
     */
    private function build_writing_prompt( $title, $content_type, $research_context ) {
        $year = date( 'Y' );
        $brand_phrase = $this->brand_phrases[ array_rand( $this->brand_phrases ) ];

        $type_instructions = array(
            'destinasi' => "Tulis artikel tentang destinasi wisata. Bahas: gambaran umum, sejarah singkat, lokasi & cara menuju, harga tiket & jam buka, aktivitas yang bisa dilakukan, tips berkunjung, dan kesimpulan dengan internal links.",
            'kuliner' => "Tulis artikel tentang kuliner. Bahas: pengenalan kuliner, asal-usul & keunikan, lokasi & harga menu, review pengalaman makan, dan kesimpulan dengan internal links.",
            'hotel' => "Tulis artikel tentang hotel/penginapan. Bahas: gambaran hotel, tipe kamar & harga, fasilitas, pengalaman menginap, dan kesimpulan dengan internal links.",
            'aktivitas' => "Tulis artikel tentang aktivitas wisata. Bahas: pengenalan aktivitas, persiapan & biaya, pengalaman & tips, dan kesimpulan dengan internal links.",
        );

        $instruction = $type_instructions[ $content_type ] ?? $type_instructions['destinasi'];

        $prompt = "Tulis artikel lengkap tentang \"{$title}\" untuk tahun {$year}.

INSTRUKSI KHUSUS:
{$instruction}

DATA RISET YANG TERSEDIA:
{$research_context}

ATURAN PENTING:
1. Mulai introduction dengan branding, contoh: \"{$brand_phrase} tentang {$title}...\"
2. Introduction bisa 1-3 paragraf, buat menarik dan informatif
3. Gunakan data riset di atas untuk informasi faktual (harga, jam buka, alamat)
4. Jika data tidak tersedia, gunakan frasa seperti 'Untuk informasi terbaru, silakan hubungi pengelola'
5. WAJIB tambahkan minimal 2-3 [INTERNAL_LINK:keyword] di bagian kesimpulan
6. Target minimal 800 kata, maksimal 2000 kata
7. Gunakan tabel Markdown untuk data harga dan jam operasional

Tulis artikel sekarang:";

        return $prompt;
    }

    /**
     * Build research context from research pack
     */
    private function build_research_context() {
        $context = array();

        // Overview
        if ( ! empty( $this->research_pack['overview'] ) ) {
            $context[] = "OVERVIEW: " . $this->research_pack['overview'];
        }

        // Location
        if ( ! empty( $this->research_pack['location']['address'] ) ) {
            $context[] = "ALAMAT: " . $this->research_pack['location']['address'];
        }

        // Pricing
        if ( ! empty( $this->research_pack['pricing'] ) ) {
            $pricing = $this->research_pack['pricing'];
            $price_info = array();
            if ( ! empty( $pricing['ticket_adult'] ) ) {
                $price_info[] = "Dewasa: " . $pricing['ticket_adult'];
            }
            if ( ! empty( $pricing['ticket_child'] ) ) {
                $price_info[] = "Anak-anak: " . $pricing['ticket_child'];
            }
            if ( ! empty( $price_info ) ) {
                $context[] = "HARGA TIKET: " . implode( ', ', $price_info );
            }
        }

        // Hours
        if ( ! empty( $this->research_pack['hours']['weekday'] ) ) {
            $context[] = "JAM BUKA: " . $this->research_pack['hours']['weekday'];
        }

        // Facilities
        if ( ! empty( $this->research_pack['facilities'] ) ) {
            $context[] = "FASILITAS: " . implode( ', ', array_slice( $this->research_pack['facilities'], 0, 10 ) );
        }

        // Activities
        if ( ! empty( $this->research_pack['activities'] ) ) {
            $context[] = "AKTIVITAS: " . implode( '; ', array_slice( $this->research_pack['activities'], 0, 5 ) );
        }

        // Tips
        if ( ! empty( $this->research_pack['tips'] ) ) {
            $context[] = "TIPS: " . implode( '; ', array_slice( $this->research_pack['tips'], 0, 5 ) );
        }

        // Unique points
        if ( ! empty( $this->research_pack['unique_points'] ) ) {
            $context[] = "KEUNIKAN: " . implode( '; ', array_slice( $this->research_pack['unique_points'], 0, 5 ) );
        }

        if ( empty( $context ) ) {
            return "Data riset terbatas. Gunakan pengetahuan umum dan frasa 'Untuk informasi terbaru, silakan hubungi pengelola' untuk data spesifik.";
        }

        return implode( "\n", $context );
    }

    /**
     * Generate article without AI (template-based)
     */
    private function generate_without_ai( $title, $content_type ) {
        tsa_log_job( $this->job_id, 'Writer Agent V3: Generating artikel dengan template...' );

        $year = date( 'Y' );
        $brand_phrase = $this->brand_phrases[ array_rand( $this->brand_phrases ) ];

        // Build article from template
        $content = "";

        // Introduction (1-2 paragraphs)
        $content .= $this->generate_introduction( $title, $brand_phrase );

        // Main content based on type
        switch ( $content_type ) {
            case 'kuliner':
                $content .= $this->generate_kuliner_content( $title, $year );
                break;
            case 'hotel':
                $content .= $this->generate_hotel_content( $title, $year );
                break;
            case 'aktivitas':
                $content .= $this->generate_aktivitas_content( $title, $year );
                break;
            default:
                $content .= $this->generate_destinasi_content( $title, $year );
        }

        // Conclusion with internal links
        $content .= $this->generate_conclusion( $title, $content_type );

        return $content;
    }

    /**
     * Generate introduction
     */
    private function generate_introduction( $title, $brand_phrase ) {
        $intro = "";

        // Paragraph 1 - Hook with brand
        $intro .= "{$brand_phrase} tentang {$title}. ";
        $intro .= "Destinasi ini menawarkan pengalaman wisata yang unik dan berbeda dari yang lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda.\n\n";

        // Paragraph 2 - Preview
        $intro .= "Dalam artikel ini, Anda akan menemukan informasi lengkap mulai dari lokasi, harga tiket, jam operasional, hingga tips berkunjung yang berguna. ";
        $intro .= "Semua informasi telah kami rangkum agar perjalanan Anda lebih mudah dan menyenangkan.\n\n";

        return $intro;
    }

    /**
     * Generate destinasi content
     */
    private function generate_destinasi_content( $title, $year ) {
        $content = "";

        // Section 1: Mengenal lebih dekat
        $content .= "## Mengenal {$title} Lebih Dekat\n\n";
        $content .= "{$title} merupakan salah satu destinasi wisata yang menarik perhatian banyak pengunjung. ";
        
        if ( ! empty( $this->research_pack['overview'] ) ) {
            $content .= $this->research_pack['overview'] . " ";
        } else {
            $content .= "Tempat ini menawarkan keindahan alam dan suasana yang menenangkan, cocok untuk liburan bersama keluarga maupun teman. ";
        }

        if ( ! empty( $this->research_pack['unique_points'] ) ) {
            $content .= "\n\nBeberapa hal yang membuat {$title} istimewa:\n";
            foreach ( array_slice( $this->research_pack['unique_points'], 0, 4 ) as $point ) {
                $content .= "- {$point}\n";
            }
        }
        $content .= "\n";

        // Section 2: Informasi Praktis
        $content .= "## Informasi Praktis untuk Pengunjung\n\n";

        // Address
        if ( ! empty( $this->research_pack['location']['address'] ) ) {
            $content .= "**Alamat:** " . $this->research_pack['location']['address'] . "\n\n";
        } else {
            $content .= "Untuk alamat lengkap dan petunjuk arah, Anda dapat menggunakan aplikasi navigasi seperti Google Maps dengan mencari \"{$title}\".\n\n";
        }

        // Price table
        $content .= "### Harga Tiket Masuk {$year}\n\n";
        $content .= "| Kategori | Harga |\n";
        $content .= "|----------|-------|\n";
        
        $pricing = $this->research_pack['pricing'] ?? array();
        $content .= "| Dewasa | " . ( $pricing['ticket_adult'] ?? 'Hubungi pengelola' ) . " |\n";
        $content .= "| Anak-anak | " . ( $pricing['ticket_child'] ?? 'Hubungi pengelola' ) . " |\n";
        $content .= "| Parkir Motor | " . ( $pricing['parking_motor'] ?? 'Rp 5.000' ) . " |\n";
        $content .= "| Parkir Mobil | " . ( $pricing['parking_car'] ?? 'Rp 10.000' ) . " |\n\n";
        $content .= "*Catatan: Harga dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pengelola sebelum berkunjung.*\n\n";

        // Hours
        $content .= "### Jam Operasional\n\n";
        $hours = $this->research_pack['hours'] ?? array();
        if ( ! empty( $hours['weekday'] ) ) {
            $content .= "- Senin - Jumat: " . $hours['weekday'] . "\n";
            $content .= "- Sabtu - Minggu: " . ( $hours['weekend'] ?? $hours['weekday'] ) . "\n\n";
        } else {
            $content .= "Untuk jam operasional terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi {$title}.\n\n";
        }

        // Section 3: Pengalaman dan Aktivitas
        $content .= "## Pengalaman dan Aktivitas Menarik\n\n";
        $content .= "Berkunjung ke {$title} tidak hanya tentang melihat pemandangan, tetapi juga tentang pengalaman yang akan Anda dapatkan. ";

        if ( ! empty( $this->research_pack['activities'] ) ) {
            $content .= "Berikut beberapa aktivitas yang bisa Anda lakukan:\n\n";
            foreach ( array_slice( $this->research_pack['activities'], 0, 5 ) as $activity ) {
                $content .= "- {$activity}\n";
            }
            $content .= "\n";
        } else {
            $content .= "Anda bisa berfoto di berbagai spot menarik, menikmati suasana alam, atau sekadar bersantai menikmati keindahan yang ditawarkan.\n\n";
        }

        // Facilities
        if ( ! empty( $this->research_pack['facilities'] ) ) {
            $content .= "**Fasilitas yang tersedia:** " . implode( ', ', $this->research_pack['facilities'] ) . ".\n\n";
        }

        // Section 4: Tips
        $content .= "## Tips Berkunjung agar Lebih Menyenangkan\n\n";
        
        if ( ! empty( $this->research_pack['tips'] ) ) {
            foreach ( array_slice( $this->research_pack['tips'], 0, 5 ) as $tip ) {
                $content .= "- {$tip}\n";
            }
            $content .= "\n";
        } else {
            $content .= "- Datanglah di pagi hari untuk menghindari keramaian\n";
            $content .= "- Bawa perlengkapan pribadi seperti topi, sunscreen, dan air minum\n";
            $content .= "- Gunakan pakaian dan alas kaki yang nyaman\n";
            $content .= "- Jaga kebersihan dan ikuti peraturan yang berlaku\n";
            $content .= "- Siapkan kamera untuk mengabadikan momen\n\n";
        }

        return $content;
    }

    /**
     * Generate kuliner content
     */
    private function generate_kuliner_content( $title, $year ) {
        $content = "";

        $content .= "## Mengenal {$title}\n\n";
        $content .= "{$title} merupakan salah satu kuliner yang wajib dicoba saat berkunjung ke daerah ini. ";
        $content .= "Dengan cita rasa yang khas dan autentik, kuliner ini telah menjadi favorit banyak wisatawan.\n\n";

        $content .= "## Lokasi dan Informasi Lengkap\n\n";
        
        if ( ! empty( $this->research_pack['location']['address'] ) ) {
            $content .= "**Alamat:** " . $this->research_pack['location']['address'] . "\n\n";
        }

        $content .= "### Menu dan Harga\n\n";
        $content .= "| Menu | Harga |\n";
        $content .= "|------|-------|\n";
        $content .= "| Menu Utama | Rp 25.000 - Rp 50.000 |\n";
        $content .= "| Minuman | Rp 5.000 - Rp 15.000 |\n\n";

        $content .= "## Review dan Pengalaman Makan\n\n";
        $content .= "Suasana tempat makan ini cukup nyaman dengan pelayanan yang ramah. ";
        $content .= "Rasa makanan tidak mengecewakan, dengan bumbu yang pas dan porsi yang cukup mengenyangkan.\n\n";

        return $content;
    }

    /**
     * Generate hotel content
     */
    private function generate_hotel_content( $title, $year ) {
        $content = "";

        $content .= "## Tentang {$title}\n\n";
        $content .= "{$title} merupakan pilihan penginapan yang cocok untuk Anda yang mencari kenyamanan dengan harga terjangkau. ";
        $content .= "Lokasi yang strategis memudahkan akses ke berbagai tempat wisata di sekitarnya.\n\n";

        $content .= "## Tipe Kamar dan Fasilitas\n\n";
        $content .= "| Tipe Kamar | Harga per Malam |\n";
        $content .= "|------------|----------------|\n";
        $content .= "| Standard | Rp 300.000 - Rp 500.000 |\n";
        $content .= "| Deluxe | Rp 500.000 - Rp 800.000 |\n";
        $content .= "| Suite | Rp 800.000 - Rp 1.500.000 |\n\n";

        $content .= "**Fasilitas:** AC, WiFi, TV, Kamar Mandi Dalam, Sarapan (tergantung paket).\n\n";

        $content .= "## Pengalaman Menginap\n\n";
        $content .= "Proses check-in cukup cepat dan staff sangat membantu. ";
        $content .= "Kamar bersih dan nyaman, cocok untuk istirahat setelah seharian berwisata.\n\n";

        return $content;
    }

    /**
     * Generate aktivitas content
     */
    private function generate_aktivitas_content( $title, $year ) {
        $content = "";

        $content .= "## Mengenal {$title}\n\n";
        $content .= "{$title} adalah aktivitas wisata yang menawarkan pengalaman seru dan tak terlupakan. ";
        $content .= "Cocok untuk Anda yang mencari petualangan dan tantangan baru.\n\n";

        $content .= "## Informasi Praktis dan Biaya\n\n";
        $content .= "| Paket | Harga | Durasi |\n";
        $content .= "|-------|-------|--------|\n";
        $content .= "| Basic | Rp 150.000 | 2 jam |\n";
        $content .= "| Premium | Rp 300.000 | 4 jam |\n\n";

        $content .= "**Yang perlu dibawa:** Pakaian ganti, handuk, sunscreen, dan kamera tahan air.\n\n";

        $content .= "## Pengalaman dan Tips\n\n";
        $content .= "Aktivitas ini sangat menyenangkan dan aman karena didampingi pemandu berpengalaman. ";
        $content .= "Pastikan Anda dalam kondisi sehat dan ikuti semua instruksi keselamatan.\n\n";

        return $content;
    }

    /**
     * Generate conclusion with internal links
     */
    private function generate_conclusion( $title, $content_type ) {
        $content = "\n";
        $content .= "---\n\n";
        $content .= "Demikian informasi lengkap tentang {$title} yang telah sekali.id rangkum untuk Anda. ";
        $content .= "Semoga artikel ini membantu dalam merencanakan perjalanan wisata Anda.\n\n";

        $content .= "Jangan lupa untuk mengecek informasi terbaru sebelum berkunjung, karena harga dan jam operasional dapat berubah sewaktu-waktu. ";
        $content .= "Selamat berlibur dan nikmati pengalaman wisata yang menyenangkan!\n\n";

        // Internal links
        $content .= "**Baca juga artikel terkait:**\n";
        $content .= "- [INTERNAL_LINK:wisata terdekat]\n";
        $content .= "- [INTERNAL_LINK:kuliner khas daerah]\n";
        $content .= "- [INTERNAL_LINK:hotel murah]\n\n";

        // Disclaimer
        $content .= "*Disclaimer: Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi.*\n";

        return $content;
    }

    /**
     * Generate meta data
     */
    private function generate_meta_data( $title ) {
        $year = date( 'Y' );

        // Meta title (max 60 chars)
        $this->draft_pack['meta_title'] = substr( "{$title} {$year} Panduan Lengkap", 0, 60 );

        // Meta description (max 160 chars)
        $this->draft_pack['meta_description'] = substr(
            "Panduan lengkap {$title} {$year}. Info harga tiket, jam buka, fasilitas, dan tips berkunjung dari sekali.id.",
            0, 160
        );

        // Slug
        $this->draft_pack['slug'] = sanitize_title( $title );

        // Excerpt
        $this->draft_pack['excerpt'] = substr( strip_tags( $this->draft_pack['content'] ), 0, 300 ) . '...';
    }

    /**
     * Handle categories
     */
    private function handle_categories( $content_type ) {
        $category_map = array(
            'destinasi' => 'Destinasi Wisata',
            'kuliner' => 'Kuliner',
            'hotel' => 'Hotel & Penginapan',
            'aktivitas' => 'Aktivitas Wisata',
            'umum' => 'Wisata',
        );

        $category_name = $category_map[ $content_type ] ?? 'Wisata';
        $this->draft_pack['category_name'] = $category_name;

        // Check if category exists in WordPress
        $category = get_term_by( 'name', $category_name, 'category' );
        
        if ( $category ) {
            $this->draft_pack['category_id'] = $category->term_id;
        } else {
            // Create new category
            $result = wp_insert_term( $category_name, 'category' );
            if ( ! is_wp_error( $result ) ) {
                $this->draft_pack['category_id'] = $result['term_id'];
                tsa_log_job( $this->job_id, "Writer Agent V3: Kategori baru dibuat: {$category_name}" );
            }
        }
    }

    /**
     * Generate tags (3-10 tags)
     */
    private function generate_tags( $title ) {
        $tags = array();
        $title_words = explode( ' ', strtolower( $title ) );

        // Add title words as tags
        foreach ( $title_words as $word ) {
            if ( strlen( $word ) > 3 ) {
                $tags[] = ucfirst( $word );
            }
        }

        // Add common travel tags
        $common_tags = array( 'Wisata', 'Liburan', 'Travel', 'Indonesia' );
        $tags = array_merge( $tags, $common_tags );

        // Add year
        $tags[] = date( 'Y' );

        // Unique and limit
        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 10 );

        $this->draft_pack['tag_names'] = $tags;

        // Get or create tag IDs
        $tag_ids = array();
        foreach ( $tags as $tag_name ) {
            $tag = get_term_by( 'name', $tag_name, 'post_tag' );
            if ( $tag ) {
                $tag_ids[] = $tag->term_id;
            } else {
                $result = wp_insert_term( $tag_name, 'post_tag' );
                if ( ! is_wp_error( $result ) ) {
                    $tag_ids[] = $result['term_id'];
                }
            }
        }

        $this->draft_pack['tag_ids'] = $tag_ids;
    }

    /**
     * Add internal links
     */
    private function add_internal_links() {
        // Get existing posts for internal linking
        $related_posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'rand',
        ) );

        $internal_links = array();
        foreach ( $related_posts as $post ) {
            $internal_links[] = array(
                'title' => $post->post_title,
                'url' => get_permalink( $post->ID ),
            );
        }

        $this->draft_pack['internal_links'] = array_slice( $internal_links, 0, 5 );

        // Replace placeholders with actual links
        $content = $this->draft_pack['content'];
        
        foreach ( $internal_links as $link ) {
            $placeholder_pattern = '/\[INTERNAL_LINK:[^\]]+\]/';
            if ( preg_match( $placeholder_pattern, $content ) ) {
                $replacement = "[{$link['title']}]({$link['url']})";
                $content = preg_replace( $placeholder_pattern, $replacement, $content, 1 );
            }
        }

        // Remove any remaining placeholders
        $content = preg_replace( '/\[INTERNAL_LINK:[^\]]+\]/', '', $content );

        $this->draft_pack['content'] = $content;
    }

    /**
     * Convert Markdown to HTML
     */
    private function convert_to_html() {
        $content = $this->draft_pack['content'];

        // Convert headers
        $content = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content );
        $content = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content );

        // Convert bold
        $content = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content );

        // Convert italic
        $content = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $content );

        // Convert links
        $content = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content );

        // Convert lists
        $content = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $content );
        $content = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content );

        // Convert tables (basic)
        $content = $this->convert_markdown_tables( $content );

        // Convert paragraphs
        $content = preg_replace( '/\n\n+/', '</p><p>', $content );
        $content = '<p>' . $content . '</p>';

        // Clean up
        $content = str_replace( '<p></p>', '', $content );
        $content = str_replace( '<p><h', '<h', $content );
        $content = str_replace( '</h2></p>', '</h2>', $content );
        $content = str_replace( '</h3></p>', '</h3>', $content );
        $content = str_replace( '<p><ul>', '<ul>', $content );
        $content = str_replace( '</ul></p>', '</ul>', $content );
        $content = str_replace( '<p><table>', '<table>', $content );
        $content = str_replace( '</table></p>', '</table>', $content );
        $content = str_replace( '<p>---</p>', '<hr>', $content );

        $this->draft_pack['content_html'] = $content;
    }

    /**
     * Convert Markdown tables to HTML
     */
    private function convert_markdown_tables( $content ) {
        // Match markdown tables
        $pattern = '/\|(.+)\|\n\|[-| ]+\|\n((?:\|.+\|\n?)+)/';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $header_cells = array_map( 'trim', explode( '|', trim( $matches[1], '|' ) ) );
            $rows = explode( "\n", trim( $matches[2] ) );
            
            $html = '<table class="tsa-table"><thead><tr>';
            foreach ( $header_cells as $cell ) {
                $html .= '<th>' . trim( $cell ) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            
            foreach ( $rows as $row ) {
                if ( empty( trim( $row ) ) ) continue;
                $cells = array_map( 'trim', explode( '|', trim( $row, '|' ) ) );
                $html .= '<tr>';
                foreach ( $cells as $cell ) {
                    $html .= '<td>' . trim( $cell ) . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            return $html;
        }, $content );
    }
}
