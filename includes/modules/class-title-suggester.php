<?php
/**
 * AI Title Suggester V2
 *
 * Generates SEO-optimized, short and punchy article title suggestions
 * Maximum 8 words, no symbols like ":", "-", "&", etc.
 * Focus on natural, search-friendly titles.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/modules
 * @version    2.0.0
 */

namespace TravelSEO_Autopublisher\Modules;

/**
 * Title Suggester Class V2
 *
 * Provides intelligent, SEO-focused title suggestions for travel/tourism content.
 * Rules:
 * - Maximum 8 words
 * - No symbols (:, -, &, |, etc)
 * - Natural language
 * - SEO optimized
 */
class Title_Suggester {

    /**
     * Title templates for different content types
     * All templates follow: max 8 words, no symbols
     *
     * @var array
     */
    private $templates = array(
        'destinasi' => array(
            // Pattern: [Keyword] + [Benefit/Action] + [Year optional]
            '{keyword} Panduan Wisata Lengkap Terbaru',
            'Wisata {keyword} Info Tiket dan Jam Buka',
            '{keyword} Tempat Liburan Wajib Dikunjungi',
            'Jelajahi {keyword} Destinasi Favorit Wisatawan',
            '{keyword} Review Lengkap dan Tips Berkunjung',
            'Liburan Seru di {keyword} Panduan Lengkap',
            '{keyword} Spot Wisata Tersembunyi yang Menakjubkan',
            'Mengunjungi {keyword} Pengalaman Wisata Terbaik',
            '{keyword} Destinasi Hits untuk Liburan Keluarga',
            'Pesona {keyword} yang Wajib Anda Kunjungi',
            '{keyword} Tempat Wisata Instagramable Terpopuler',
            'Eksplorasi {keyword} Panduan Wisatawan Pemula',
        ),
        'kuliner' => array(
            '{keyword} Kuliner Legendaris Wajib Dicoba',
            'Wisata Kuliner {keyword} Rekomendasi Terbaik',
            '{keyword} Makanan Enak Harga Terjangkau',
            'Mencicipi {keyword} Pengalaman Kuliner Autentik',
            '{keyword} Tempat Makan Favorit Wisatawan',
            'Berburu {keyword} Kuliner Khas Daerah',
            '{keyword} Review Makanan dan Harga Terbaru',
            'Kelezatan {keyword} yang Bikin Ketagihan',
            '{keyword} Rekomendasi Kuliner Lokal Terpopuler',
            'Menikmati {keyword} Cita Rasa Nusantara',
            '{keyword} Spot Kuliner Hits dan Viral',
            'Hunting {keyword} Panduan Lengkap Foodie',
        ),
        'hotel' => array(
            '{keyword} Review Hotel dan Harga Terbaru',
            'Menginap di {keyword} Pengalaman Lengkap',
            '{keyword} Penginapan Terbaik Harga Terjangkau',
            'Staycation {keyword} Fasilitas dan Tips Booking',
            '{keyword} Hotel Rekomendasi Liburan Keluarga',
            'Review {keyword} Kelebihan dan Kekurangan',
            '{keyword} Penginapan Instagramable yang Nyaman',
            'Pengalaman Menginap {keyword} Worth It',
            '{keyword} Hotel Murah Fasilitas Lengkap',
            'Liburan di {keyword} Review Jujur',
            '{keyword} Rekomendasi Hotel Terbaik Tahun Ini',
            'Booking {keyword} Tips Dapat Harga Murah',
        ),
        'aktivitas' => array(
            '{keyword} Aktivitas Seru Saat Liburan',
            'Mencoba {keyword} Pengalaman Tak Terlupakan',
            '{keyword} Panduan Lengkap untuk Pemula',
            'Serunya {keyword} Aktivitas Wajib Dicoba',
            '{keyword} Petualangan Outdoor Paling Seru',
            'Pengalaman {keyword} Tips dan Persiapan',
            '{keyword} Aktivitas Liburan Keluarga Terbaik',
            'Guide {keyword} dari Awal Sampai Akhir',
            '{keyword} Rekomendasi Aktivitas Wisata Populer',
            'Menikmati {keyword} Pengalaman Seru Bersama',
            '{keyword} Aktivitas Hits yang Viral',
            'Persiapan {keyword} Panduan Lengkap Wisatawan',
        ),
        'umum' => array(
            '{keyword} Panduan Wisata Lengkap Terbaru',
            'Menjelajahi {keyword} Semua yang Perlu Diketahui',
            '{keyword} Destinasi Impian Wajib Dikunjungi',
            'Liburan ke {keyword} Tips dan Itinerary',
            '{keyword} Review Pengalaman Nyata Wisatawan',
            'Pesona {keyword} yang Memikat Hati',
            '{keyword} Tempat Favorit Wisatawan Indonesia',
            'Eksplorasi {keyword} Hidden Gem Menakjubkan',
            '{keyword} Info Terbaru dan Tips Berkunjung',
            'Keindahan {keyword} yang Harus Dilihat',
            '{keyword} Rekomendasi Wisata Terpopuler',
            'Mengunjungi {keyword} Panduan Praktis Lengkap',
        ),
    );

    /**
     * SEO power words to enhance titles
     *
     * @var array
     */
    private $power_words = array(
        'Lengkap', 'Terbaru', 'Terbaik', 'Wajib', 'Populer',
        'Favorit', 'Hits', 'Viral', 'Rekomendasi', 'Panduan',
        'Tips', 'Review', 'Pengalaman', 'Seru', 'Menakjubkan',
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
     * @return array Array of title suggestions with metadata
     */
    public function suggest( $keyword, $location = '', $type = 'umum', $count = 10, $use_ai = false ) {
        // Clean keyword
        $keyword = $this->clean_keyword( $keyword );
        $location = $this->clean_keyword( $location );

        // Determine content type if not specified
        if ( $type === 'auto' || empty( $type ) ) {
            $type = $this->detect_content_type( $keyword );
        }

        // Try AI generation first if enabled
        if ( $use_ai && $this->is_ai_available() ) {
            $ai_suggestions = $this->generate_with_ai( $keyword, $location, $type, $count );
            if ( ! empty( $ai_suggestions ) ) {
                return $this->format_suggestions( $ai_suggestions, $keyword, $type );
            }
        }

        // Fallback to template-based generation
        $suggestions = $this->generate_from_templates( $keyword, $location, $type, $count );

        return $this->format_suggestions( $suggestions, $keyword, $type );
    }

    /**
     * Clean keyword - remove unwanted characters
     *
     * @param string $keyword Keyword to clean
     * @return string
     */
    private function clean_keyword( $keyword ) {
        // Remove symbols
        $keyword = preg_replace( '/[:\-&|"\']+/', ' ', $keyword );
        // Remove extra spaces
        $keyword = preg_replace( '/\s+/', ' ', $keyword );
        // Trim
        $keyword = trim( $keyword );
        // Capitalize first letter of each word
        $keyword = ucwords( strtolower( $keyword ) );

        return $keyword;
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
        $kuliner_keywords = array( 'makanan', 'kuliner', 'restoran', 'cafe', 'kafe', 'warung', 'rumah makan', 'nasi', 'mie', 'sate', 'bakso', 'soto', 'rendang', 'gudeg', 'seafood', 'minuman', 'kopi', 'es', 'jajanan', 'masakan', 'hidangan' );
        foreach ( $kuliner_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'kuliner';
            }
        }

        // Hotel keywords
        $hotel_keywords = array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel', 'guest house', 'cottage', 'glamping', 'menginap', 'akomodasi' );
        foreach ( $hotel_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'hotel';
            }
        }

        // Aktivitas keywords
        $aktivitas_keywords = array( 'rafting', 'diving', 'snorkeling', 'hiking', 'trekking', 'camping', 'surfing', 'paragliding', 'bungee', 'arung jeram', 'panjat tebing', 'flying fox', 'outbound', 'tour', 'tur' );
        foreach ( $aktivitas_keywords as $kw ) {
            if ( strpos( $keyword_lower, $kw ) !== false ) {
                return 'aktivitas';
            }
        }

        // Destinasi keywords (default for tourism)
        $destinasi_keywords = array( 'pantai', 'gunung', 'danau', 'air terjun', 'taman', 'museum', 'candi', 'pura', 'masjid', 'gereja', 'kebun', 'hutan', 'goa', 'pulau', 'desa wisata', 'kampung', 'kolam renang', 'waterpark', 'theme park', 'wisata', 'tempat' );
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
        return ! empty( $this->settings['openai_api_key'] );
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

        if ( ! empty( $this->settings['openai_api_key'] ) ) {
            return $this->call_openai( $prompt );
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
        $type_indo = array(
            'destinasi' => 'tempat wisata',
            'kuliner' => 'kuliner/makanan',
            'hotel' => 'hotel/penginapan',
            'aktivitas' => 'aktivitas wisata',
            'umum' => 'wisata',
        );

        $location_text = ! empty( $location ) ? " di {$location}" : '';

        $prompt = "Kamu adalah ahli SEO Indonesia. Buatkan {$count} judul artikel SEO untuk keyword \"{$keyword}\"{$location_text}.

ATURAN WAJIB:
1. Maksimal 8 kata per judul
2. DILARANG menggunakan simbol apapun (: - & | / \\ \" ')
3. Bahasa Indonesia natural dan mudah dibaca
4. Fokus pada search intent user
5. Gunakan power words: Lengkap, Terbaru, Terbaik, Wajib, Panduan, Tips, Review
6. Keyword utama harus ada di judul
7. Judul harus menarik untuk diklik

Tipe konten: {$type_indo[$type]}

Format output: Satu judul per baris, tanpa nomor atau bullet.";

        return $prompt;
    }

    /**
     * Call OpenAI API
     *
     * @param string $prompt Prompt to send
     * @return array
     */
    private function call_openai( $prompt ) {
        $api_key = $this->settings['openai_api_key'];
        $endpoint = $this->settings['openai_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
        $model = $this->settings['openai_model'] ?? 'gpt-3.5-turbo';

        $response = wp_remote_post( $endpoint, array(
            'timeout' => 30,
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
                'max_tokens' => 500,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['choices'][0]['message']['content'] ) ) {
            return array();
        }

        $content = $body['choices'][0]['message']['content'];
        $lines = explode( "\n", $content );
        $titles = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            // Remove numbering if present
            $line = preg_replace( '/^\d+[\.\)]\s*/', '', $line );
            // Remove symbols
            $line = preg_replace( '/[:\-&|"\']+/', ' ', $line );
            $line = preg_replace( '/\s+/', ' ', $line );
            $line = trim( $line );

            if ( ! empty( $line ) && str_word_count( $line ) <= 10 ) {
                $titles[] = $line;
            }
        }

        return array_slice( $titles, 0, 12 );
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
        $templates = $this->templates[ $type ] ?? $this->templates['umum'];
        $suggestions = array();

        // Shuffle templates for variety
        shuffle( $templates );

        foreach ( $templates as $template ) {
            if ( count( $suggestions ) >= $count ) {
                break;
            }

            $title = str_replace( '{keyword}', $keyword, $template );

            if ( ! empty( $location ) ) {
                $title = str_replace( '{lokasi}', $location, $title );
            } else {
                // Remove location placeholder if no location provided
                $title = str_replace( ' di {lokasi}', '', $title );
                $title = str_replace( ' {lokasi}', '', $title );
                $title = str_replace( '{lokasi}', '', $title );
            }

            // Clean up any remaining placeholders
            $title = preg_replace( '/\{[^}]+\}/', '', $title );
            $title = preg_replace( '/\s+/', ' ', $title );
            $title = trim( $title );

            // Ensure max 8 words
            $words = explode( ' ', $title );
            if ( count( $words ) > 8 ) {
                $title = implode( ' ', array_slice( $words, 0, 8 ) );
            }

            if ( ! empty( $title ) && ! in_array( $title, $suggestions ) ) {
                $suggestions[] = $title;
            }
        }

        // Add variations if needed
        if ( count( $suggestions ) < $count ) {
            $variations = $this->generate_variations( $keyword, $location, $count - count( $suggestions ) );
            $suggestions = array_merge( $suggestions, $variations );
        }

        return array_slice( $suggestions, 0, $count );
    }

    /**
     * Generate title variations
     *
     * @param string $keyword  Main keyword
     * @param string $location Location
     * @param int    $count    Number of variations
     * @return array
     */
    private function generate_variations( $keyword, $location, $count ) {
        $variations = array();
        $year = date( 'Y' );

        $patterns = array(
            "{$keyword} Panduan Lengkap {$year}",
            "{$keyword} Info Terbaru dan Terlengkap",
            "Wisata {$keyword} yang Wajib Dikunjungi",
            "{$keyword} Review dan Rekomendasi Terbaik",
            "Mengunjungi {$keyword} Tips Lengkap",
            "{$keyword} Destinasi Populer Tahun Ini",
            "Liburan di {$keyword} Panduan Wisatawan",
            "{$keyword} Tempat Hits yang Viral",
        );

        if ( ! empty( $location ) ) {
            $patterns[] = "{$keyword} {$location} Panduan Lengkap";
            $patterns[] = "Wisata {$keyword} di {$location}";
        }

        shuffle( $patterns );

        foreach ( $patterns as $pattern ) {
            if ( count( $variations ) >= $count ) {
                break;
            }

            // Ensure max 8 words
            $words = explode( ' ', $pattern );
            if ( count( $words ) <= 8 ) {
                $variations[] = $pattern;
            }
        }

        return $variations;
    }

    /**
     * Format suggestions with metadata
     *
     * @param array  $suggestions Raw suggestions
     * @param string $keyword     Original keyword
     * @param string $type        Content type
     * @return array
     */
    private function format_suggestions( $suggestions, $keyword, $type ) {
        $formatted = array();

        foreach ( $suggestions as $index => $title ) {
            $word_count = str_word_count( $title );
            $char_count = strlen( $title );

            // Calculate SEO score
            $seo_score = $this->calculate_seo_score( $title, $keyword );

            $formatted[] = array(
                'title' => $title,
                'word_count' => $word_count,
                'char_count' => $char_count,
                'seo_score' => $seo_score,
                'type' => $type,
                'has_keyword' => stripos( $title, $keyword ) !== false,
                'index' => $index + 1,
            );
        }

        // Sort by SEO score
        usort( $formatted, function( $a, $b ) {
            return $b['seo_score'] - $a['seo_score'];
        } );

        return $formatted;
    }

    /**
     * Calculate SEO score for a title
     *
     * @param string $title   Title to score
     * @param string $keyword Target keyword
     * @return int Score 0-100
     */
    private function calculate_seo_score( $title, $keyword ) {
        $score = 50; // Base score

        // Keyword presence (+20)
        if ( stripos( $title, $keyword ) !== false ) {
            $score += 20;
        }

        // Keyword at beginning (+10)
        if ( stripos( $title, $keyword ) === 0 ) {
            $score += 10;
        }

        // Optimal length 5-7 words (+10)
        $word_count = str_word_count( $title );
        if ( $word_count >= 5 && $word_count <= 7 ) {
            $score += 10;
        }

        // Contains power words (+5 each, max +15)
        $power_bonus = 0;
        foreach ( $this->power_words as $word ) {
            if ( stripos( $title, $word ) !== false ) {
                $power_bonus += 5;
            }
        }
        $score += min( $power_bonus, 15 );

        // No symbols (+5)
        if ( ! preg_match( '/[:\-&|"\']+/', $title ) ) {
            $score += 5;
        }

        // Character length 40-60 (+5)
        $char_count = strlen( $title );
        if ( $char_count >= 40 && $char_count <= 60 ) {
            $score += 5;
        }

        return min( $score, 100 );
    }

    /**
     * Get content type options
     *
     * @return array
     */
    public function get_type_options() {
        return array(
            'auto' => 'Auto Detect',
            'destinasi' => 'Destinasi Wisata',
            'kuliner' => 'Kuliner',
            'hotel' => 'Hotel/Penginapan',
            'aktivitas' => 'Aktivitas',
            'umum' => 'Umum',
        );
    }
}
