<?php
/**
 * Writer Agent V3 - Human-like Article Writer
 * 
 * Agent ini membaca summary dari Summarizer Agent seperti manusia membaca
 * dokumen riset, kemudian menulis artikel baru yang original dan komprehensif.
 * Target: 1000-3000 kata dengan branding sekali.id
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Writer_Agent {

    /**
     * Job ID
     */
    private $job_id;

    /**
     * Summary pack dari Summarizer Agent
     */
    private $summary_pack;

    /**
     * Settings
     */
    private $settings;

    /**
     * Brand name
     */
    const BRAND_NAME = 'sekali.id';

    /**
     * Target word count
     */
    const MIN_WORDS = 1000;
    const MAX_WORDS = 3000;

    /**
     * Constructor
     */
    public function __construct( $job_id, $summary_pack, $settings = array() ) {
        $this->job_id = $job_id;
        $this->summary_pack = $summary_pack;
        $this->settings = $settings;
    }

    /**
     * Run writing process
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Writer Agent V3: Memulai penulisan artikel...' );
        tsa_update_job( $this->job_id, array( 'status' => 'drafting' ) );
        
        $title = $this->summary_pack['title'] ?? '';
        $content_type = $this->summary_pack['content_type'] ?? 'umum';
        $summary = $this->summary_pack['summary'] ?? '';
        $info = $this->summary_pack['info'] ?? array();
        $keywords = $this->summary_pack['keywords'] ?? array();
        
        // Check if AI API is available
        $use_ai = $this->has_ai_api();
        
        if ( $use_ai ) {
            tsa_log_job( $this->job_id, 'Writer Agent: Menggunakan AI untuk menulis artikel...' );
            $article = $this->write_with_ai( $title, $content_type, $summary, $info, $keywords );
        } else {
            tsa_log_job( $this->job_id, 'Writer Agent: Menggunakan template untuk menulis artikel...' );
            $article = $this->write_locally( $title, $content_type, $summary, $info, $keywords );
        }
        
        // Build draft pack
        $draft_pack = $this->build_draft_pack( $title, $content_type, $article, $keywords );
        
        $word_count = str_word_count( strip_tags( $article ) );
        tsa_log_job( $this->job_id, "Writer Agent: Artikel selesai. {$word_count} kata" );
        
        return $draft_pack;
    }

    /**
     * Check if AI API is available
     */
    private function has_ai_api() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        return ! empty( $api_key );
    }

    /**
     * Write article using AI
     */
    private function write_with_ai( $title, $content_type, $summary, $info, $keywords ) {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $api_url = tsa_get_option( 'openai_api_url', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        $prompt = $this->build_writer_prompt( $title, $content_type, $summary, $info, $keywords );
        
        $response = wp_remote_post( $api_url, array(
            'timeout' => 180,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode( array(
                'model'       => $model,
                'messages'    => array(
                    array(
                        'role'    => 'system',
                        'content' => $this->get_system_prompt(),
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => 0.7,
                'max_tokens'  => 4000,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Writer Agent: AI Error - ' . $response->get_error_message() );
            return $this->write_locally( $title, $content_type, $summary, $info, $keywords );
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            $article = $body['choices'][0]['message']['content'];
            
            // Ensure branding is present
            if ( strpos( $article, self::BRAND_NAME ) === false ) {
                $article = $this->inject_branding( $article, $title );
            }
            
            return $article;
        }
        
        return $this->write_locally( $title, $content_type, $summary, $info, $keywords );
    }

    /**
     * Get system prompt for AI
     */
    private function get_system_prompt() {
        return 'Anda adalah penulis konten travel profesional untuk ' . self::BRAND_NAME . '. 
Gaya penulisan Anda:
- Natural dan mengalir seperti penulis manusia profesional
- Informatif namun tetap menarik dibaca
- Menggunakan bahasa Indonesia yang baik dan benar
- Tidak kaku atau seperti robot
- Memberikan nilai tambah bagi pembaca

Aturan penulisan:
- WAJIB menyebut "' . self::BRAND_NAME . '" di paragraf pembuka
- Gunakan kata ganti "' . self::BRAND_NAME . '" bukan "saya/kami/kita"
- Contoh: "' . self::BRAND_NAME . ' akan menyuguhkan informasi lengkap..."
- Minimal 1000 kata, maksimal 3000 kata
- Struktur: Introduction (1-3 paragraf) -> Body -> Conclusion
- Tidak perlu terlalu banyak H2/H3, fokus pada konten yang padat dan informatif
- Setiap section harus mengalir natural ke section berikutnya';
    }

    /**
     * Build writer prompt
     */
    private function build_writer_prompt( $title, $content_type, $summary, $info, $keywords ) {
        $keywords_str = implode( ', ', array_slice( $keywords, 0, 10 ) );
        
        return "Tugas: Tulis artikel lengkap tentang \"{$title}\" berdasarkan ringkasan riset berikut.

RINGKASAN RISET:
{$summary}

KEYWORDS UNTUK SEO: {$keywords_str}

INSTRUKSI PENULISAN:

1. INTRODUCTION (1-3 paragraf)
   - Mulai dengan: \"" . self::BRAND_NAME . " akan menyuguhkan informasi lengkap tentang {$title}...\"
   - Hook yang menarik perhatian pembaca
   - Preview singkat apa yang akan dibahas

2. BODY ARTIKEL
   - Tulis konten yang padat dan informatif
   - Gunakan H2 untuk section utama (maksimal 4-5 H2)
   - Setiap section minimal 2-3 paragraf
   - Masukkan informasi praktis: lokasi, harga, jam buka
   - Tambahkan tips dan rekomendasi
   - Buat tabel jika ada data yang perlu ditampilkan (harga, jam buka)

3. CONCLUSION
   - Rangkum poin-poin penting
   - Ajakan untuk berkunjung
   - Sebutkan \"" . self::BRAND_NAME . "\" sekali lagi

4. FORMAT OUTPUT
   - Gunakan Markdown
   - H2 untuk judul section (##)
   - Bold untuk penekanan (**text**)
   - Tabel untuk data terstruktur
   - Minimal 1000 kata, maksimal 3000 kata

PENTING:
- Tulis seperti penulis manusia profesional, BUKAN seperti AI
- Jangan copy-paste dari ringkasan, interpretasikan dan tulis ulang dengan gaya sendiri
- Buat narasi yang mengalir dan enak dibaca
- Fokus pada value untuk pembaca";
    }

    /**
     * Write article locally without AI
     */
    private function write_locally( $title, $content_type, $summary, $info, $keywords ) {
        $article = '';
        
        // Introduction
        $article .= $this->write_introduction( $title, $content_type, $info );
        
        // Body sections based on content type
        switch ( $content_type ) {
            case 'destinasi':
                $article .= $this->write_destinasi_body( $title, $summary, $info );
                break;
            case 'kuliner':
                $article .= $this->write_kuliner_body( $title, $summary, $info );
                break;
            case 'hotel':
                $article .= $this->write_hotel_body( $title, $summary, $info );
                break;
            default:
                $article .= $this->write_generic_body( $title, $summary, $info );
        }
        
        // Conclusion
        $article .= $this->write_conclusion( $title, $content_type );
        
        // Disclaimer
        $article .= $this->write_disclaimer();
        
        return $article;
    }

    /**
     * Write introduction
     */
    private function write_introduction( $title, $content_type, $info ) {
        $intro = '';
        
        // Opening paragraph with branding
        $intro .= self::BRAND_NAME . " akan menyuguhkan informasi lengkap tentang {$title}. ";
        
        $type_desc = array(
            'destinasi' => 'Destinasi ini menawarkan pengalaman wisata yang unik dan berbeda dari yang lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda.',
            'kuliner'   => 'Kuliner ini menawarkan cita rasa yang khas dan autentik, menjadikannya pilihan tepat bagi Anda yang ingin menikmati kelezatan kuliner lokal.',
            'hotel'     => 'Penginapan ini menawarkan kenyamanan dan fasilitas yang lengkap, menjadikannya pilihan tepat untuk akomodasi selama perjalanan Anda.',
            'umum'      => 'Tempat ini menawarkan pengalaman yang menarik dan berkesan, menjadikannya pilihan yang patut dipertimbangkan.',
        );
        
        $intro .= $type_desc[ $content_type ] ?? $type_desc['umum'];
        $intro .= "\n\n";
        
        // Second paragraph - what reader will find
        $intro .= "Dalam artikel ini, Anda akan menemukan informasi lengkap mulai dari lokasi, ";
        
        if ( $content_type === 'destinasi' ) {
            $intro .= "harga tiket masuk, jam operasional, fasilitas yang tersedia, hingga tips berkunjung yang berguna. ";
        } elseif ( $content_type === 'kuliner' ) {
            $intro .= "menu andalan, harga, jam buka, hingga tips menikmati kuliner ini dengan maksimal. ";
        } elseif ( $content_type === 'hotel' ) {
            $intro .= "tipe kamar, fasilitas, harga, hingga tips booking untuk mendapatkan penawaran terbaik. ";
        } else {
            $intro .= "berbagai informasi penting hingga tips yang berguna untuk Anda. ";
        }
        
        $intro .= "Semua informasi telah " . self::BRAND_NAME . " rangkum dari berbagai sumber terpercaya untuk memudahkan perencanaan Anda.\n\n";
        
        return $intro;
    }

    /**
     * Write destinasi body
     */
    private function write_destinasi_body( $title, $summary, $info ) {
        $body = '';
        
        // Section 1: Overview
        $body .= "## Mengenal {$title} Lebih Dekat\n\n";
        $body .= "{$title} merupakan salah satu destinasi wisata yang menarik perhatian banyak pengunjung. ";
        $body .= "Tempat ini menawarkan pemandangan yang indah dan suasana yang menyenangkan untuk bersantai bersama keluarga maupun teman. ";
        $body .= "Keindahan alam yang disuguhkan menjadi daya tarik utama yang membuat wisatawan terus berdatangan.\n\n";
        
        if ( ! empty( $info['deskripsi'] ) ) {
            $body .= $info['deskripsi'] . "\n\n";
        }
        
        $body .= "Baik untuk liburan keluarga, gathering bersama teman, maupun solo traveling, tempat ini mampu mengakomodasi berbagai kebutuhan wisata Anda. ";
        $body .= "Suasana yang ditawarkan sangat cocok untuk melepas penat dari rutinitas sehari-hari.\n\n";
        
        // Section 2: Practical Info
        $body .= "## Informasi Praktis untuk Pengunjung\n\n";
        $body .= "Sebelum berkunjung ke {$title}, ada baiknya Anda mengetahui beberapa informasi praktis berikut ini.\n\n";
        
        // Location
        $body .= "**Lokasi dan Akses**\n\n";
        if ( ! empty( $info['alamat'] ) ) {
            $body .= "{$title} berlokasi di {$info['alamat']}. ";
        } else {
            $body .= "{$title} dapat dijangkau dengan berbagai moda transportasi. ";
        }
        $body .= "Untuk mencapai lokasi ini, Anda bisa menggunakan kendaraan pribadi maupun transportasi umum. ";
        $body .= "Gunakan aplikasi navigasi seperti Google Maps atau Waze untuk panduan rute terbaik menuju lokasi.\n\n";
        
        // Price table
        $body .= "**Harga Tiket Masuk**\n\n";
        $body .= "| Kategori | Harga |\n";
        $body .= "|----------|-------|\n";
        if ( ! empty( $info['harga'] ) ) {
            $body .= "| Dewasa | {$info['harga']} |\n";
            $body .= "| Anak-anak | Hubungi pengelola |\n";
        } else {
            $body .= "| Dewasa | Hubungi pengelola untuk info terbaru |\n";
            $body .= "| Anak-anak | Hubungi pengelola untuk info terbaru |\n";
        }
        $body .= "\n*Catatan: Harga dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola sebelum berkunjung.*\n\n";
        
        // Operating hours
        $body .= "**Jam Operasional**\n\n";
        if ( ! empty( $info['jam_buka'] ) ) {
            $body .= "{$title} buka pada jam {$info['jam_buka']}. ";
        } else {
            $body .= "Untuk informasi jam operasional terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi destinasi ini. ";
        }
        $body .= "Datanglah di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman.\n\n";
        
        // Section 3: Facilities
        $body .= "## Fasilitas yang Tersedia\n\n";
        $body .= "{$title} dilengkapi dengan berbagai fasilitas untuk kenyamanan pengunjung. ";
        
        if ( ! empty( $info['fasilitas'] ) ) {
            $body .= "Beberapa fasilitas yang tersedia antara lain:\n\n";
            foreach ( $info['fasilitas'] as $fasilitas ) {
                $body .= "- **{$fasilitas}** - Tersedia untuk kenyamanan pengunjung\n";
            }
            $body .= "\n";
        } else {
            $body .= "Fasilitas umum seperti area parkir, toilet, dan tempat istirahat tersedia untuk memastikan kenyamanan selama berkunjung.\n\n";
        }
        
        // Section 4: Activities
        $body .= "## Aktivitas Menarik yang Bisa Dilakukan\n\n";
        $body .= "Ada berbagai aktivitas menarik yang bisa Anda lakukan saat berkunjung ke {$title}. ";
        
        if ( ! empty( $info['aktivitas'] ) ) {
            $body .= "Beberapa aktivitas yang direkomendasikan:\n\n";
            foreach ( $info['aktivitas'] as $aktivitas ) {
                $body .= "- **{$aktivitas}** - Aktivitas yang sayang untuk dilewatkan\n";
            }
            $body .= "\n";
        } else {
            $body .= "Anda bisa menikmati pemandangan, berfoto di spot-spot menarik, bersantai bersama keluarga, atau sekadar menikmati suasana yang menenangkan.\n\n";
        }
        
        $body .= "Jangan lupa untuk mengabadikan momen berharga Anda dengan berfoto di berbagai spot instagramable yang tersedia. ";
        $body .= "Pemandangan yang indah akan menjadi latar belakang sempurna untuk foto-foto kenangan Anda.\n\n";
        
        // Section 5: Tips
        $body .= "## Tips Berkunjung agar Lebih Menyenangkan\n\n";
        $body .= self::BRAND_NAME . " memiliki beberapa tips yang bisa membantu Anda mendapatkan pengalaman terbaik saat berkunjung:\n\n";
        $body .= "1. **Datang di waktu yang tepat** - Pagi hari adalah waktu terbaik untuk menghindari keramaian dan mendapatkan pencahayaan foto yang bagus.\n\n";
        $body .= "2. **Persiapkan perlengkapan** - Bawa topi, sunscreen, dan air minum yang cukup, terutama jika berkunjung di siang hari.\n\n";
        $body .= "3. **Cek cuaca** - Periksa prakiraan cuaca sebelum berangkat untuk persiapan yang lebih baik.\n\n";
        $body .= "4. **Bawa kamera** - Jangan lupa membawa kamera atau smartphone dengan baterai penuh untuk mengabadikan momen.\n\n";
        $body .= "5. **Patuhi peraturan** - Selalu ikuti peraturan yang berlaku demi keselamatan dan kenyamanan bersama.\n\n";
        
        return $body;
    }

    /**
     * Write kuliner body
     */
    private function write_kuliner_body( $title, $summary, $info ) {
        $body = '';
        
        $body .= "## Tentang {$title}\n\n";
        $body .= "{$title} merupakan salah satu kuliner yang wajib dicoba. ";
        $body .= "Cita rasa yang khas dan autentik menjadikan kuliner ini favorit banyak orang. ";
        $body .= "Kelezatan yang ditawarkan mampu memanjakan lidah siapa saja yang mencobanya.\n\n";
        
        if ( ! empty( $info['deskripsi'] ) ) {
            $body .= $info['deskripsi'] . "\n\n";
        }
        
        $body .= "## Informasi Lokasi dan Harga\n\n";
        if ( ! empty( $info['alamat'] ) ) {
            $body .= "**Lokasi:** {$info['alamat']}\n\n";
        }
        if ( ! empty( $info['harga'] ) ) {
            $body .= "**Kisaran Harga:** {$info['harga']}\n\n";
        }
        if ( ! empty( $info['jam_buka'] ) ) {
            $body .= "**Jam Buka:** {$info['jam_buka']}\n\n";
        }
        
        $body .= "## Tips Menikmati {$title}\n\n";
        $body .= "1. Datang di luar jam makan siang untuk menghindari antrian.\n";
        $body .= "2. Coba menu andalan yang menjadi favorit pengunjung.\n";
        $body .= "3. Tanyakan tingkat kepedasan jika tidak terbiasa dengan makanan pedas.\n";
        $body .= "4. Reservasi terlebih dahulu jika berkunjung di akhir pekan.\n\n";
        
        return $body;
    }

    /**
     * Write hotel body
     */
    private function write_hotel_body( $title, $summary, $info ) {
        $body = '';
        
        $body .= "## Tentang {$title}\n\n";
        $body .= "{$title} merupakan pilihan akomodasi yang menawarkan kenyamanan dan fasilitas lengkap. ";
        $body .= "Penginapan ini cocok untuk berbagai kebutuhan, baik untuk liburan keluarga, perjalanan bisnis, maupun honeymoon.\n\n";
        
        if ( ! empty( $info['deskripsi'] ) ) {
            $body .= $info['deskripsi'] . "\n\n";
        }
        
        $body .= "## Fasilitas dan Layanan\n\n";
        if ( ! empty( $info['fasilitas'] ) ) {
            foreach ( $info['fasilitas'] as $fasilitas ) {
                $body .= "- {$fasilitas}\n";
            }
            $body .= "\n";
        } else {
            $body .= "Fasilitas standar hotel seperti AC, WiFi, TV, dan kamar mandi dalam tersedia untuk kenyamanan tamu.\n\n";
        }
        
        $body .= "## Tips Booking\n\n";
        $body .= "1. Booking jauh-jauh hari untuk mendapatkan harga terbaik.\n";
        $body .= "2. Cek review terbaru dari tamu sebelumnya.\n";
        $body .= "3. Tanyakan tentang promo atau paket khusus.\n";
        $body .= "4. Konfirmasi check-in dan check-out time sebelum kedatangan.\n\n";
        
        return $body;
    }

    /**
     * Write generic body
     */
    private function write_generic_body( $title, $summary, $info ) {
        $body = '';
        
        $body .= "## Tentang {$title}\n\n";
        $body .= "{$title} merupakan tempat yang menarik untuk dikunjungi. ";
        $body .= "Tempat ini menawarkan pengalaman yang unik dan berkesan bagi setiap pengunjung.\n\n";
        
        if ( ! empty( $info['deskripsi'] ) ) {
            $body .= $info['deskripsi'] . "\n\n";
        }
        
        $body .= "## Informasi Penting\n\n";
        if ( ! empty( $info['alamat'] ) ) {
            $body .= "**Lokasi:** {$info['alamat']}\n\n";
        }
        if ( ! empty( $info['harga'] ) ) {
            $body .= "**Harga:** {$info['harga']}\n\n";
        }
        if ( ! empty( $info['jam_buka'] ) ) {
            $body .= "**Jam Operasional:** {$info['jam_buka']}\n\n";
        }
        
        $body .= "## Tips Berkunjung\n\n";
        $body .= "1. Persiapkan perjalanan dengan baik sebelum berangkat.\n";
        $body .= "2. Bawa perlengkapan yang diperlukan.\n";
        $body .= "3. Cek informasi terbaru sebelum berkunjung.\n";
        $body .= "4. Patuhi peraturan yang berlaku.\n\n";
        
        return $body;
    }

    /**
     * Write conclusion
     */
    private function write_conclusion( $title, $content_type ) {
        $conclusion = "## Penutup\n\n";
        
        $conclusion .= "Demikian informasi lengkap tentang {$title} yang telah " . self::BRAND_NAME . " rangkum untuk Anda. ";
        
        if ( $content_type === 'destinasi' ) {
            $conclusion .= "Destinasi ini layak untuk masuk dalam daftar kunjungan Anda. ";
            $conclusion .= "Dengan berbagai daya tarik yang dimiliki, {$title} menawarkan pengalaman wisata yang memuaskan.\n\n";
            $conclusion .= "Jadi, tunggu apa lagi? Segera rencanakan kunjungan Anda dan nikmati pesona {$title} bersama orang-orang tercinta. ";
        } elseif ( $content_type === 'kuliner' ) {
            $conclusion .= "Kuliner ini wajib dicoba bagi Anda pecinta kuliner. ";
            $conclusion .= "Cita rasa yang khas akan memberikan pengalaman kuliner yang tak terlupakan.\n\n";
        } elseif ( $content_type === 'hotel' ) {
            $conclusion .= "Penginapan ini bisa menjadi pilihan tepat untuk akomodasi Anda. ";
            $conclusion .= "Fasilitas dan layanan yang ditawarkan akan membuat perjalanan Anda semakin nyaman.\n\n";
        } else {
            $conclusion .= "Semoga informasi ini bermanfaat untuk perencanaan Anda.\n\n";
        }
        
        $conclusion .= "Selamat berlibur dan semoga pengalaman Anda menyenangkan!\n\n";
        
        return $conclusion;
    }

    /**
     * Write disclaimer
     */
    private function write_disclaimer() {
        return "*Disclaimer: Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi.*\n";
    }

    /**
     * Inject branding if missing
     */
    private function inject_branding( $article, $title ) {
        $brand_intro = self::BRAND_NAME . " akan menyuguhkan informasi lengkap tentang {$title}. ";
        
        // Find first paragraph and inject
        $first_newline = strpos( $article, "\n\n" );
        if ( $first_newline !== false ) {
            $article = $brand_intro . substr( $article, 0, $first_newline ) . "\n\n" . substr( $article, $first_newline + 2 );
        } else {
            $article = $brand_intro . $article;
        }
        
        return $article;
    }

    /**
     * Build draft pack
     */
    private function build_draft_pack( $title, $content_type, $article, $keywords ) {
        // Convert markdown to HTML
        $content_html = $this->markdown_to_html( $article );
        
        // Calculate word count
        $word_count = str_word_count( strip_tags( $content_html ) );
        
        // Generate meta
        $meta_title = $this->generate_meta_title( $title );
        $meta_description = $this->generate_meta_description( $title, $content_type );
        $slug = sanitize_title( $title );
        
        // Generate category
        $category = $this->determine_category( $content_type, $title );
        
        // Generate tags
        $tags = $this->generate_tags( $keywords, $title );
        
        // Calculate reading time
        $reading_time = ceil( $word_count / 200 ) . ' menit';
        
        return array(
            'title'            => $title,
            'slug'             => $slug,
            'content'          => $article,
            'content_html'     => $content_html,
            'meta_title'       => $meta_title,
            'meta_description' => $meta_description,
            'word_count'       => $word_count,
            'reading_time'     => $reading_time,
            'category_name'    => $category,
            'tag_names'        => $tags,
            'content_type'     => $content_type,
            'keywords'         => $keywords,
        );
    }

    /**
     * Convert markdown to HTML
     */
    private function markdown_to_html( $markdown ) {
        // Headers
        $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $markdown );
        $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
        
        // Bold
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        
        // Italic
        $html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
        
        // Links
        $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );
        
        // Tables
        $html = $this->convert_tables( $html );
        
        // Paragraphs
        $html = $this->convert_paragraphs( $html );
        
        return $html;
    }

    /**
     * Convert markdown tables to HTML
     */
    private function convert_tables( $text ) {
        $lines = explode( "\n", $text );
        $in_table = false;
        $table_html = '';
        $result = array();
        
        foreach ( $lines as $line ) {
            if ( preg_match( '/^\|(.+)\|$/', $line ) ) {
                if ( ! $in_table ) {
                    $in_table = true;
                    $table_html = '<table class="tsa-table">';
                }
                
                // Skip separator line
                if ( preg_match( '/^\|[\s\-:|]+\|$/', $line ) ) {
                    continue;
                }
                
                $cells = explode( '|', trim( $line, '|' ) );
                $tag = ( strpos( $table_html, '<tr>' ) === false ) ? 'th' : 'td';
                
                $table_html .= '<tr>';
                foreach ( $cells as $cell ) {
                    $table_html .= "<{$tag}>" . trim( $cell ) . "</{$tag}>";
                }
                $table_html .= '</tr>';
            } else {
                if ( $in_table ) {
                    $table_html .= '</table>';
                    $result[] = $table_html;
                    $table_html = '';
                    $in_table = false;
                }
                $result[] = $line;
            }
        }
        
        if ( $in_table ) {
            $table_html .= '</table>';
            $result[] = $table_html;
        }
        
        return implode( "\n", $result );
    }

    /**
     * Convert paragraphs
     */
    private function convert_paragraphs( $text ) {
        $blocks = preg_split( '/\n\n+/', $text );
        $result = array();
        
        foreach ( $blocks as $block ) {
            $block = trim( $block );
            if ( empty( $block ) ) {
                continue;
            }
            
            // Skip if already HTML tag
            if ( preg_match( '/^<(h[1-6]|table|ul|ol|div|p)/', $block ) ) {
                $result[] = $block;
            }
            // List items
            elseif ( preg_match( '/^[\-\*\d]/', $block ) ) {
                $items = explode( "\n", $block );
                $list_html = '<ul>';
                foreach ( $items as $item ) {
                    $item = preg_replace( '/^[\-\*\d\.]+\s*/', '', $item );
                    if ( ! empty( trim( $item ) ) ) {
                        $list_html .= '<li>' . trim( $item ) . '</li>';
                    }
                }
                $list_html .= '</ul>';
                $result[] = $list_html;
            }
            // Regular paragraph
            else {
                $result[] = '<p>' . nl2br( $block ) . '</p>';
            }
        }
        
        return implode( "\n", $result );
    }

    /**
     * Generate meta title
     */
    private function generate_meta_title( $title ) {
        $year = date( 'Y' );
        $meta = "{$title} {$year} - Info Lengkap dan Tips Berkunjung";
        
        if ( strlen( $meta ) > 60 ) {
            $meta = "{$title} - Info Lengkap {$year}";
        }
        
        return $meta;
    }

    /**
     * Generate meta description
     */
    private function generate_meta_description( $title, $content_type ) {
        $year = date( 'Y' );
        
        $templates = array(
            'destinasi' => "Panduan lengkap {$title} {$year}. Info harga tiket, jam buka, fasilitas, dan tips berkunjung. Temukan semua yang perlu Anda ketahui di sini.",
            'kuliner'   => "Review lengkap {$title} {$year}. Info menu, harga, lokasi, dan tips menikmati kuliner ini. Wajib dicoba!",
            'hotel'     => "Review {$title} {$year}. Info kamar, fasilitas, harga, dan tips booking. Panduan lengkap untuk perjalanan Anda.",
            'umum'      => "Informasi lengkap tentang {$title} {$year}. Semua yang perlu Anda ketahui ada di sini.",
        );
        
        $meta = $templates[ $content_type ] ?? $templates['umum'];
        
        if ( strlen( $meta ) > 160 ) {
            $meta = substr( $meta, 0, 157 ) . '...';
        }
        
        return $meta;
    }

    /**
     * Determine category
     */
    private function determine_category( $content_type, $title ) {
        $categories = array(
            'destinasi' => 'Destinasi Wisata',
            'kuliner'   => 'Kuliner',
            'hotel'     => 'Penginapan',
            'aktivitas' => 'Aktivitas',
            'umum'      => 'Travel',
        );
        
        return $categories[ $content_type ] ?? 'Travel';
    }

    /**
     * Generate tags
     */
    private function generate_tags( $keywords, $title ) {
        $tags = array();
        
        // Add title words as tags
        $title_words = explode( ' ', $title );
        foreach ( $title_words as $word ) {
            if ( strlen( $word ) > 3 ) {
                $tags[] = ucfirst( strtolower( $word ) );
            }
        }
        
        // Add top keywords
        foreach ( array_slice( $keywords, 0, 5 ) as $keyword ) {
            if ( strlen( $keyword ) > 3 ) {
                $tags[] = ucfirst( $keyword );
            }
        }
        
        // Remove duplicates and limit
        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 8 );
        
        return $tags;
    }
}
