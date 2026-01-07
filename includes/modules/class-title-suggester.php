<?php
/**
 * AI Title Suggester
 *
 * Generates SEO-optimized, click-worthy article title suggestions
 * based on user keywords using AI or built-in templates.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/modules
 */

namespace TravelSEO_Autopublisher\Modules;

/**
 * Title Suggester Class
 *
 * Provides intelligent title suggestions for travel/tourism content.
 */
class Title_Suggester {

    /**
     * Title templates for different content types
     *
     * @var array
     */
    private $templates = array(
        'destinasi' => array(
            '[TAHUN] Panduan Lengkap {keyword}: Harga Tiket, Jam Buka & Tips Berkunjung',
            '{keyword} - Destinasi Wisata yang Wajib Dikunjungi di {lokasi}',
            'Mengunjungi {keyword}: Pengalaman Tak Terlupakan yang Harus Anda Coba',
            'Pesona {keyword}: Surga Tersembunyi yang Jarang Diketahui Wisatawan',
            '{keyword} [TAHUN]: Review Lengkap, Foto & Pengalaman Pribadi',
            'Eksplorasi {keyword}: Panduan Wisata Terlengkap untuk Pemula',
            'Keindahan {keyword} yang Memukau - Wajib Masuk Bucket List!',
            'Rahasia {keyword}: Tips dari Warga Lokal yang Jarang Dibagikan',
            '{keyword}: Semua yang Perlu Anda Tahu Sebelum Berkunjung',
            'Liburan Seru di {keyword} - Aktivitas, Biaya & Rekomendasi',
        ),
        'kuliner' => array(
            '{keyword}: Kuliner Legendaris yang Wajib Dicoba di {lokasi}',
            'Berburu {keyword} - Rekomendasi Tempat Makan Terbaik [TAHUN]',
            'Mencicipi {keyword}: Pengalaman Kuliner Autentik di {lokasi}',
            '{keyword} Paling Enak di {lokasi} - Review Jujur & Harga',
            'Wisata Kuliner {lokasi}: Menikmati {keyword} yang Legendaris',
            'Rekomendasi {keyword} di {lokasi}: Dari yang Murah Sampai Premium',
            '{keyword} {lokasi}: Cita Rasa yang Bikin Ketagihan',
            'Hunting {keyword} di {lokasi} - Panduan Lengkap untuk Foodie',
            'Kelezatan {keyword}: Kuliner Khas {lokasi} yang Menggugah Selera',
            '{keyword} Terenak di {lokasi} [TAHUN] - Wajib Coba!',
        ),
        'hotel' => array(
            'Review {keyword}: Penginapan Terbaik di {lokasi} [TAHUN]',
            '{keyword} - Pengalaman Menginap Mewah dengan Budget Terjangkau',
            'Staycation di {keyword}: Fasilitas Lengkap & Harga Terbaru',
            '{keyword} {lokasi}: Review Jujur, Foto & Tips Booking',
            'Menginap di {keyword}: Worth It atau Tidak? Review Lengkap',
            '{keyword}: Pilihan Hotel Terbaik untuk Liburan Keluarga',
            'Pengalaman Menginap di {keyword} - Kelebihan & Kekurangan',
            '{keyword} [TAHUN]: Harga, Fasilitas & Cara Booking Termurah',
            'Review Lengkap {keyword}: Apakah Sesuai Ekspektasi?',
            '{keyword}: Hotel Instagramable di {lokasi} yang Wajib Dicoba',
        ),
        'aktivitas' => array(
            '{keyword} di {lokasi}: Panduan Lengkap untuk Pemula',
            'Serunya {keyword} - Aktivitas Wajib Saat Liburan di {lokasi}',
            '{keyword}: Pengalaman Seru yang Tak Terlupakan di {lokasi}',
            'Mencoba {keyword} di {lokasi} - Tips, Biaya & Persiapan',
            '{keyword} Terbaik di {lokasi} [TAHUN] - Review & Rekomendasi',
            'Petualangan {keyword}: Aktivitas Outdoor Paling Seru di {lokasi}',
            '{keyword} di {lokasi}: Semua yang Perlu Anda Ketahui',
            'Pengalaman {keyword} di {lokasi} - Apakah Worth It?',
            '{keyword}: Aktivitas Liburan Seru untuk Keluarga di {lokasi}',
            'Guide Lengkap {keyword} di {lokasi} - Dari A sampai Z',
        ),
        'umum' => array(
            '{keyword} [TAHUN]: Panduan Wisata Super Lengkap',
            'Menjelajahi {keyword} - Semua yang Perlu Anda Tahu',
            '{keyword}: Destinasi Impian yang Wajib Dikunjungi',
            'Liburan ke {keyword} - Tips, Biaya & Itinerary Lengkap',
            '{keyword} Review [TAHUN]: Pengalaman Nyata & Rekomendasi',
            'Pesona {keyword} yang Memikat Hati - Panduan Lengkap',
            '{keyword}: Tempat Wisata Favorit yang Tak Pernah Sepi',
            'Eksplorasi {keyword} - Hidden Gem yang Wajib Dikunjungi',
            '{keyword} Terbaru [TAHUN]: Update Info & Tips Berkunjung',
            'Keajaiban {keyword}: Mengapa Tempat Ini Begitu Spesial?',
        ),
    );

    /**
     * Click-bait prefixes for extra engagement
     *
     * @var array
     */
    private $clickbait_prefixes = array(
        '[REVIEW JUJUR]',
        '[UPDATE TERBARU]',
        '[WAJIB BACA]',
        '[VIRAL]',
        '[RAHASIA]',
        '[TIPS HEMAT]',
        '[REKOMENDASI]',
        '[PENGALAMAN PRIBADI]',
    );

    /**
     * Number modifiers for listicles
     *
     * @var array
     */
    private $number_modifiers = array(
        '5 Alasan Mengapa',
        '7 Hal yang Harus Anda Tahu Tentang',
        '10 Fakta Menarik Tentang',
        '15 Tips Berkunjung ke',
        '20 Spot Foto Terbaik di',
        '3 Kesalahan yang Harus Dihindari Saat Mengunjungi',
        '8 Aktivitas Seru di',
        '12 Kuliner Wajib Coba di',
    );

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option( 'tsa_settings', array() );
    }

    /**
     * Generate title suggestions
     *
     * @param string $keyword     Main keyword
     * @param string $location    Location (optional)
     * @param string $type        Content type (destinasi, kuliner, hotel, aktivitas, umum)
     * @param int    $count       Number of suggestions to generate
     * @param bool   $use_ai      Whether to use AI for generation
     * @return array Array of title suggestions
     */
    public function suggest( $keyword, $location = '', $type = 'umum', $count = 10, $use_ai = false ) {
        $suggestions = array();

        // Determine content type if not specified
        if ( $type === 'auto' || empty( $type ) ) {
            $type = $this->detect_content_type( $keyword );
        }

        // Try AI generation first if enabled
        if ( $use_ai && $this->is_ai_available() ) {
            $ai_suggestions = $this->generate_with_ai( $keyword, $location, $type, $count );
            if ( ! empty( $ai_suggestions ) ) {
                return $ai_suggestions;
            }
        }

        // Fallback to template-based generation
        $suggestions = $this->generate_from_templates( $keyword, $location, $type, $count );

        return $suggestions;
    }

    /**
     * Detect content type from keyword
     *
     * @param string $keyword Keyword to analyze
     * @return string Content type
     */
    private function detect_content_type( $keyword ) {
        $keyword_lower = strtolower( $keyword );

        // Kuliner keywords
        $kuliner_keywords = array( 'makanan', 'kuliner', 'restoran', 'cafe', 'kafe', 'warung', 'rumah makan', 'nasi', 'mie', 'sate', 'bakso', 'soto', 'rendang', 'gudeg', 'seafood', 'minuman', 'kopi', 'es', 'jajanan' );
        foreach ( $kuliner_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'kuliner';
            }
        }

        // Hotel keywords
        $hotel_keywords = array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel', 'guest house', 'cottage', 'glamping', 'menginap' );
        foreach ( $hotel_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'hotel';
            }
        }

        // Aktivitas keywords
        $aktivitas_keywords = array( 'rafting', 'diving', 'snorkeling', 'hiking', 'trekking', 'camping', 'surfing', 'paragliding', 'bungee', 'arung jeram', 'panjat tebing', 'flying fox', 'outbound' );
        foreach ( $aktivitas_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'aktivitas';
            }
        }

        // Destinasi keywords (default for tourism)
        $destinasi_keywords = array( 'pantai', 'gunung', 'danau', 'air terjun', 'taman', 'museum', 'candi', 'pura', 'masjid', 'gereja', 'kebun', 'hutan', 'goa', 'pulau', 'desa wisata', 'kampung', 'kolam renang', 'waterpark', 'theme park' );
        foreach ( $destinasi_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'destinasi';
            }
        }

        return 'umum';
    }

    /**
     * Check if AI is available
     *
     * @return bool
     */
    private function is_ai_available() {
        // Check for OpenAI API key
        if ( ! empty( $this->settings['openai_api_key'] ) ) {
            return true;
        }

        // Check for other AI providers
        if ( ! empty( $this->settings['deepseek_api_key'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Generate titles using AI
     *
     * @param string $keyword  Main keyword
     * @param string $location Location
     * @param string $type     Content type
     * @param int    $count    Number of suggestions
     * @return array
     */
    private function generate_with_ai( $keyword, $location, $type, $count ) {
        $prompt = $this->build_ai_prompt( $keyword, $location, $type, $count );

        // Try OpenAI first
        if ( ! empty( $this->settings['openai_api_key'] ) ) {
            return $this->call_openai( $prompt );
        }

        // Try DeepSeek
        if ( ! empty( $this->settings['deepseek_api_key'] ) ) {
            return $this->call_deepseek( $prompt );
        }

        return array();
    }

    /**
     * Build AI prompt for title generation
     *
     * @param string $keyword  Main keyword
     * @param string $location Location
     * @param string $type     Content type
     * @param int    $count    Number of suggestions
     * @return string
     */
    private function build_ai_prompt( $keyword, $location, $type, $count ) {
        $type_descriptions = array(
            'destinasi' => 'tempat wisata/destinasi',
            'kuliner' => 'kuliner/makanan/restoran',
            'hotel' => 'hotel/penginapan',
            'aktivitas' => 'aktivitas wisata/petualangan',
            'umum' => 'wisata umum',
        );

        $type_desc = $type_descriptions[ $type ] ?? 'wisata';
        $location_text = ! empty( $location ) ? " di {$location}" : '';
        $year = date( 'Y' );

        return "Kamu adalah seorang ahli SEO dan copywriter untuk website wisata Indonesia.

Buatkan {$count} variasi judul artikel yang menarik, SEO-friendly, dan click-worthy untuk keyword: \"{$keyword}\"{$location_text}.

Kategori konten: {$type_desc}

Kriteria judul yang baik:
1. Mengandung keyword utama di awal judul
2. Memiliki angka atau tahun ({$year}) jika relevan
3. Memicu rasa penasaran pembaca
4. Panjang ideal 50-60 karakter
5. Menggunakan power words seperti: Lengkap, Terbaru, Rahasia, Wajib, Terbaik, dll.
6. Variasikan format: listicle, how-to, review, panduan

Format output: Berikan hanya daftar judul, satu per baris, tanpa nomor atau bullet.";
    }

    /**
     * Call OpenAI API
     *
     * @param string $prompt Prompt text
     * @return array
     */
    private function call_openai( $prompt ) {
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
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'temperature' => 0.8,
                'max_tokens' => 1000,
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            tsa_log( 'OpenAI API error: ' . $response->get_error_message(), 'error' );
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            $content = $body['choices'][0]['message']['content'];
            return $this->parse_ai_response( $content );
        }

        return array();
    }

    /**
     * Call DeepSeek API
     *
     * @param string $prompt Prompt text
     * @return array
     */
    private function call_deepseek( $prompt ) {
        $api_key = $this->settings['deepseek_api_key'];

        $response = wp_remote_post( 'https://api.deepseek.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'temperature' => 0.8,
                'max_tokens' => 1000,
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            tsa_log( 'DeepSeek API error: ' . $response->get_error_message(), 'error' );
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            $content = $body['choices'][0]['message']['content'];
            return $this->parse_ai_response( $content );
        }

        return array();
    }

    /**
     * Parse AI response into array of titles
     *
     * @param string $content AI response content
     * @return array
     */
    private function parse_ai_response( $content ) {
        $lines = explode( "\n", trim( $content ) );
        $titles = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            // Remove numbering if present
            $line = preg_replace( '/^\d+[\.\)]\s*/', '', $line );
            $line = preg_replace( '/^[-*]\s*/', '', $line );

            if ( ! empty( $line ) && strlen( $line ) > 10 ) {
                $titles[] = $line;
            }
        }

        return $titles;
    }

    /**
     * Generate titles from templates
     *
     * @param string $keyword  Main keyword
     * @param string $location Location
     * @param string $type     Content type
     * @param int    $count    Number of suggestions
     * @return array
     */
    private function generate_from_templates( $keyword, $location, $type, $count ) {
        $suggestions = array();
        $year = date( 'Y' );

        // Get templates for the content type
        $type_templates = $this->templates[ $type ] ?? $this->templates['umum'];

        // Also include some general templates
        $all_templates = array_merge( $type_templates, $this->templates['umum'] );
        $all_templates = array_unique( $all_templates );

        // Shuffle for variety
        shuffle( $all_templates );

        // Generate from templates
        foreach ( $all_templates as $template ) {
            if ( count( $suggestions ) >= $count ) {
                break;
            }

            $title = str_replace(
                array( '{keyword}', '{lokasi}', '[TAHUN]' ),
                array( $keyword, $location ?: 'Indonesia', $year ),
                $template
            );

            // Clean up if location is empty
            if ( empty( $location ) ) {
                $title = str_replace( ' di Indonesia', '', $title );
                $title = str_replace( ' Indonesia', '', $title );
            }

            $suggestions[] = $title;
        }

        // Add some listicle variations
        if ( count( $suggestions ) < $count ) {
            shuffle( $this->number_modifiers );
            foreach ( $this->number_modifiers as $modifier ) {
                if ( count( $suggestions ) >= $count ) {
                    break;
                }
                $title = $modifier . ' ' . $keyword;
                if ( ! empty( $location ) ) {
                    $title .= ' ' . $location;
                }
                $suggestions[] = $title;
            }
        }

        // Add some clickbait variations
        if ( count( $suggestions ) < $count ) {
            shuffle( $this->clickbait_prefixes );
            foreach ( $this->clickbait_prefixes as $prefix ) {
                if ( count( $suggestions ) >= $count ) {
                    break;
                }
                $base_title = $suggestions[ array_rand( array_slice( $suggestions, 0, 5 ) ) ] ?? $keyword;
                $suggestions[] = $prefix . ' ' . $base_title;
            }
        }

        return array_slice( $suggestions, 0, $count );
    }

    /**
     * Get related keywords for a topic
     *
     * @param string $keyword Main keyword
     * @return array Related keywords
     */
    public function get_related_keywords( $keyword ) {
        $related = array();
        $keyword_lower = strtolower( $keyword );

        // Common travel-related suffixes
        $suffixes = array(
            'harga tiket',
            'jam buka',
            'lokasi',
            'review',
            'tips berkunjung',
            'rute',
            'fasilitas',
            'spot foto',
            'penginapan terdekat',
            'kuliner terdekat',
        );

        foreach ( $suffixes as $suffix ) {
            $related[] = $keyword . ' ' . $suffix;
        }

        return $related;
    }

    /**
     * Validate title for SEO
     *
     * @param string $title Title to validate
     * @return array Validation result with score and suggestions
     */
    public function validate_title( $title ) {
        $result = array(
            'score' => 100,
            'issues' => array(),
            'suggestions' => array(),
        );

        $length = mb_strlen( $title );

        // Check length
        if ( $length < 30 ) {
            $result['score'] -= 20;
            $result['issues'][] = 'Judul terlalu pendek (kurang dari 30 karakter)';
            $result['suggestions'][] = 'Tambahkan kata kunci atau deskripsi tambahan';
        } elseif ( $length > 60 ) {
            $result['score'] -= 10;
            $result['issues'][] = 'Judul terlalu panjang (lebih dari 60 karakter)';
            $result['suggestions'][] = 'Persingkat judul agar tidak terpotong di hasil pencarian';
        }

        // Check for numbers
        if ( ! preg_match( '/\d/', $title ) ) {
            $result['score'] -= 5;
            $result['suggestions'][] = 'Pertimbangkan menambahkan angka atau tahun untuk meningkatkan CTR';
        }

        // Check for power words
        $power_words = array( 'lengkap', 'terbaru', 'terbaik', 'rahasia', 'wajib', 'gratis', 'mudah', 'cepat', 'murah', 'review', 'panduan', 'tips' );
        $has_power_word = false;
        foreach ( $power_words as $word ) {
            if ( stripos( $title, $word ) !== false ) {
                $has_power_word = true;
                break;
            }
        }
        if ( ! $has_power_word ) {
            $result['score'] -= 5;
            $result['suggestions'][] = 'Tambahkan power word seperti: Lengkap, Terbaru, Terbaik, dll.';
        }

        // Check for special characters that might cause issues
        if ( preg_match( '/[<>"\']/', $title ) ) {
            $result['score'] -= 10;
            $result['issues'][] = 'Judul mengandung karakter khusus yang tidak disarankan';
        }

        return $result;
    }
}
