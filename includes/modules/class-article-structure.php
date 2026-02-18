<?php
/**
 * Article Structure Manager V2
 *
 * Defines professional article structure with:
 * - Flexible introduction (1-3 paragraphs)
 * - Internal links mandatory
 * - Branding "sekali.id" integration
 * - Natural content flow, not too many H2/H3
 * - Focus on dense, informative content
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/modules
 * @version    2.0.0
 */

namespace TravelSEO_Autopublisher\Modules;

/**
 * Article Structure Class V2
 *
 * Professional article structure for SEO-optimized content.
 */
class Article_Structure {

    /**
     * Minimum word count target
     */
    const MIN_WORD_COUNT = 800;

    /**
     * Maximum word count target
     */
    const MAX_WORD_COUNT = 2000;

    /**
     * Brand name for content
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
    );

    /**
     * Content structures
     */
    private $structures = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_structures();
    }

    /**
     * Get random brand phrase
     */
    public function get_brand_phrase() {
        return $this->brand_phrases[ array_rand( $this->brand_phrases ) ];
    }

    /**
     * Initialize article structures
     */
    private function init_structures() {
        // Universal structure for all content types
        // Simpler, more natural flow with fewer H2/H3
        
        $this->structures['destinasi'] = array(
            'name' => 'Destinasi Wisata',
            'min_words' => 800,
            'max_words' => 2000,
            'sections' => array(
                // Introduction - Flexible 1-3 paragraphs
                array(
                    'id' => 'introduction',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 150,
                    'max_words' => 300,
                    'paragraphs' => '1-3',
                    'prompt' => 'Tulis introduction yang menarik dalam 1-3 paragraf. Paragraf pertama harus langsung memikat pembaca dengan hook yang kuat. Sebutkan bahwa "' . self::BRAND_NAME . ' akan menyuguhkan informasi lengkap" tentang destinasi ini. Jelaskan secara singkat apa yang membuat tempat ini spesial dan apa yang akan dibahas dalam artikel.',
                    'must_include' => array( 'brand_mention', 'hook', 'preview' ),
                ),
                
                // Main content - Sekilas dan Info Penting
                array(
                    'id' => 'overview',
                    'title' => 'Mengenal {keyword} Lebih Dekat',
                    'type' => 'main',
                    'min_words' => 200,
                    'max_words' => 400,
                    'prompt' => 'Tulis gambaran lengkap tentang destinasi dalam beberapa paragraf padat. Jelaskan lokasi, sejarah singkat, dan apa yang membuatnya istimewa. Gunakan gaya bercerita yang natural, bukan daftar poin. Sertakan fakta menarik yang jarang diketahui.',
                    'must_include' => array( 'description', 'history_brief', 'unique_points' ),
                ),
                
                // Practical Info - Combined in one section
                array(
                    'id' => 'practical_info',
                    'title' => 'Informasi Praktis untuk Pengunjung',
                    'type' => 'practical',
                    'min_words' => 250,
                    'max_words' => 450,
                    'prompt' => 'Tulis informasi praktis yang dibutuhkan pengunjung dalam format yang mudah dibaca. Gabungkan: alamat lengkap, jam operasional, harga tiket (dalam format tabel jika perlu), dan cara menuju lokasi. Gunakan paragraf untuk penjelasan dan tabel untuk data harga/jam.',
                    'must_include' => array( 'address', 'hours', 'prices', 'directions' ),
                    'allow_table' => true,
                ),
                
                // Experience - What to do
                array(
                    'id' => 'experience',
                    'title' => 'Pengalaman dan Aktivitas Menarik',
                    'type' => 'experience',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Tulis tentang pengalaman yang bisa didapat dan aktivitas yang bisa dilakukan. Ceritakan dengan gaya personal seolah-olah sudah pernah mengunjungi. Sebutkan spot foto terbaik, aktivitas seru, dan momen yang tidak boleh dilewatkan.',
                    'must_include' => array( 'activities', 'photo_spots', 'experiences' ),
                ),
                
                // Tips and Recommendations
                array(
                    'id' => 'tips',
                    'title' => 'Tips Berkunjung agar Lebih Menyenangkan',
                    'type' => 'tips',
                    'min_words' => 150,
                    'max_words' => 250,
                    'prompt' => 'Berikan tips praktis untuk pengunjung. Waktu terbaik berkunjung, apa yang harus dibawa, hal yang perlu dihindari, dan rekomendasi kuliner/penginapan terdekat. Tulis dalam paragraf, bukan bullet points berlebihan.',
                    'must_include' => array( 'best_time', 'what_to_bring', 'nearby_food' ),
                ),
                
                // Conclusion with Internal Links
                array(
                    'id' => 'conclusion',
                    'title' => null,
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt' => 'Tulis kesimpulan yang mengajak pembaca untuk berkunjung. Ringkas poin-poin utama dan berikan call-to-action. WAJIB sertakan placeholder untuk internal links ke artikel terkait dengan format: [INTERNAL_LINK:keyword]. Akhiri dengan kalimat yang memorable.',
                    'must_include' => array( 'summary', 'cta', 'internal_links' ),
                    'internal_links_required' => true,
                ),
            ),
        );

        // Kuliner structure
        $this->structures['kuliner'] = array(
            'name' => 'Kuliner',
            'min_words' => 800,
            'max_words' => 1800,
            'sections' => array(
                array(
                    'id' => 'introduction',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 150,
                    'max_words' => 280,
                    'paragraphs' => '1-3',
                    'prompt' => 'Tulis introduction menarik tentang kuliner ini. Gunakan hook yang membuat pembaca lapar. Sebutkan bahwa "' . self::BRAND_NAME . ' akan menyuguhkan" review lengkap. Jelaskan mengapa kuliner ini istimewa dan layak dicoba.',
                    'must_include' => array( 'brand_mention', 'hook', 'preview' ),
                ),
                array(
                    'id' => 'about',
                    'title' => 'Mengenal {keyword}',
                    'type' => 'main',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Ceritakan tentang kuliner ini secara mendalam. Asal-usul, sejarah, bahan utama, dan apa yang membuatnya berbeda dari yang lain. Gunakan deskripsi sensorik yang membuat pembaca bisa membayangkan rasanya.',
                    'must_include' => array( 'origin', 'ingredients', 'taste_description' ),
                ),
                array(
                    'id' => 'practical_info',
                    'title' => 'Lokasi dan Informasi Lengkap',
                    'type' => 'practical',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Berikan informasi praktis: alamat lengkap, jam buka, range harga menu, dan cara menuju lokasi. Sertakan rekomendasi menu yang wajib dicoba beserta harganya dalam format tabel.',
                    'must_include' => array( 'address', 'hours', 'menu_prices', 'directions' ),
                    'allow_table' => true,
                ),
                array(
                    'id' => 'review',
                    'title' => 'Review dan Pengalaman Makan',
                    'type' => 'experience',
                    'min_words' => 150,
                    'max_words' => 280,
                    'prompt' => 'Tulis review seperti pengalaman pribadi. Ceritakan suasana tempat, pelayanan, dan tentu saja rasa makanannya. Berikan penilaian jujur tentang kelebihan dan kekurangan.',
                    'must_include' => array( 'ambiance', 'service', 'taste_review' ),
                ),
                array(
                    'id' => 'conclusion',
                    'title' => null,
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt' => 'Kesimpulan singkat dengan rekomendasi. Apakah worth it untuk dikunjungi? Untuk siapa tempat ini cocok? WAJIB sertakan [INTERNAL_LINK:keyword] untuk artikel kuliner terkait.',
                    'must_include' => array( 'recommendation', 'internal_links' ),
                    'internal_links_required' => true,
                ),
            ),
        );

        // Hotel structure
        $this->structures['hotel'] = array(
            'name' => 'Hotel/Penginapan',
            'min_words' => 800,
            'max_words' => 1800,
            'sections' => array(
                array(
                    'id' => 'introduction',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 150,
                    'max_words' => 280,
                    'paragraphs' => '1-3',
                    'prompt' => 'Tulis introduction yang membuat pembaca tertarik menginap. Sebutkan "' . self::BRAND_NAME . ' akan mengulas" hotel ini secara lengkap. Highlight keunggulan utama dan untuk siapa hotel ini cocok.',
                    'must_include' => array( 'brand_mention', 'hook', 'target_audience' ),
                ),
                array(
                    'id' => 'overview',
                    'title' => 'Tentang {keyword}',
                    'type' => 'main',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Gambaran lengkap tentang hotel: lokasi strategis, konsep/tema, fasilitas utama, dan apa yang membedakannya dari hotel lain di area yang sama.',
                    'must_include' => array( 'location', 'concept', 'main_facilities' ),
                ),
                array(
                    'id' => 'rooms_facilities',
                    'title' => 'Tipe Kamar dan Fasilitas',
                    'type' => 'practical',
                    'min_words' => 200,
                    'max_words' => 380,
                    'prompt' => 'Jelaskan tipe-tipe kamar yang tersedia dengan harga masing-masing (dalam tabel). Sebutkan fasilitas di kamar dan fasilitas umum hotel. Highlight fasilitas unggulan.',
                    'must_include' => array( 'room_types', 'prices', 'facilities' ),
                    'allow_table' => true,
                ),
                array(
                    'id' => 'experience',
                    'title' => 'Pengalaman Menginap',
                    'type' => 'experience',
                    'min_words' => 150,
                    'max_words' => 280,
                    'prompt' => 'Ceritakan pengalaman menginap seperti review pribadi. Bagaimana check-in, kebersihan, kenyamanan, pelayanan staff, dan breakfast jika ada. Berikan penilaian objektif.',
                    'must_include' => array( 'check_in', 'cleanliness', 'service', 'breakfast' ),
                ),
                array(
                    'id' => 'conclusion',
                    'title' => null,
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt' => 'Kesimpulan dengan rekomendasi jelas. Worth it atau tidak? Untuk siapa cocok? Tips booking untuk dapat harga terbaik. WAJIB sertakan [INTERNAL_LINK:keyword] ke artikel hotel/destinasi terkait.',
                    'must_include' => array( 'verdict', 'booking_tips', 'internal_links' ),
                    'internal_links_required' => true,
                ),
            ),
        );

        // Aktivitas structure
        $this->structures['aktivitas'] = array(
            'name' => 'Aktivitas Wisata',
            'min_words' => 800,
            'max_words' => 1800,
            'sections' => array(
                array(
                    'id' => 'introduction',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 150,
                    'max_words' => 280,
                    'paragraphs' => '1-3',
                    'prompt' => 'Tulis introduction yang membangkitkan semangat petualangan. Sebutkan "' . self::BRAND_NAME . ' akan memandu" pembaca untuk mencoba aktivitas ini. Jelaskan sensasi dan pengalaman yang akan didapat.',
                    'must_include' => array( 'brand_mention', 'excitement', 'preview' ),
                ),
                array(
                    'id' => 'about',
                    'title' => 'Mengenal {keyword}',
                    'type' => 'main',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Jelaskan aktivitas ini secara detail. Apa itu, bagaimana cara melakukannya, tingkat kesulitan, dan untuk siapa aktivitas ini cocok. Sertakan informasi keamanan.',
                    'must_include' => array( 'description', 'difficulty', 'safety' ),
                ),
                array(
                    'id' => 'practical_info',
                    'title' => 'Informasi Praktis dan Biaya',
                    'type' => 'practical',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt' => 'Berikan info praktis: lokasi, biaya/paket yang tersedia (dalam tabel), durasi, apa yang termasuk dalam paket, dan apa yang perlu dibawa sendiri.',
                    'must_include' => array( 'location', 'prices', 'duration', 'what_to_bring' ),
                    'allow_table' => true,
                ),
                array(
                    'id' => 'experience',
                    'title' => 'Pengalaman dan Tips',
                    'type' => 'experience',
                    'min_words' => 150,
                    'max_words' => 280,
                    'prompt' => 'Ceritakan pengalaman melakukan aktivitas ini. Momen-momen seru, tantangan yang dihadapi, dan tips untuk pemula. Tulis dengan gaya personal yang engaging.',
                    'must_include' => array( 'experience_story', 'challenges', 'tips' ),
                ),
                array(
                    'id' => 'conclusion',
                    'title' => null,
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt' => 'Kesimpulan yang memotivasi pembaca untuk mencoba. Ringkas pengalaman dan berikan final tips. WAJIB sertakan [INTERNAL_LINK:keyword] ke artikel aktivitas/destinasi terkait.',
                    'must_include' => array( 'motivation', 'final_tips', 'internal_links' ),
                    'internal_links_required' => true,
                ),
            ),
        );

        // Umum structure (fallback)
        $this->structures['umum'] = $this->structures['destinasi'];
        $this->structures['umum']['name'] = 'Umum';
    }

    /**
     * Get structure for content type
     */
    public function get_structure( $type = 'umum' ) {
        return $this->structures[ $type ] ?? $this->structures['umum'];
    }

    /**
     * Get all structures
     */
    public function get_all_structures() {
        return $this->structures;
    }

    /**
     * Get section by ID
     */
    public function get_section( $type, $section_id ) {
        $structure = $this->get_structure( $type );
        foreach ( $structure['sections'] as $section ) {
            if ( $section['id'] === $section_id ) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Generate internal link placeholders
     */
    public function generate_internal_link_placeholders( $keyword, $type, $count = 3 ) {
        $related_keywords = $this->get_related_keywords( $keyword, $type );
        $placeholders = array();

        for ( $i = 0; $i < min( $count, count( $related_keywords ) ); $i++ ) {
            $placeholders[] = '[INTERNAL_LINK:' . $related_keywords[ $i ] . ']';
        }

        return $placeholders;
    }

    /**
     * Get related keywords for internal linking
     */
    private function get_related_keywords( $keyword, $type ) {
        // This would ideally query existing posts
        // For now, generate suggestions based on type
        $suggestions = array();
        $keyword_lower = strtolower( $keyword );

        switch ( $type ) {
            case 'destinasi':
                $suggestions = array(
                    'wisata ' . $keyword_lower,
                    'hotel dekat ' . $keyword_lower,
                    'kuliner ' . $keyword_lower,
                    'tiket ' . $keyword_lower,
                    'rute ke ' . $keyword_lower,
                );
                break;
            case 'kuliner':
                $suggestions = array(
                    'restoran ' . $keyword_lower,
                    'makanan khas',
                    'wisata kuliner',
                    'tempat makan enak',
                    'kuliner legendaris',
                );
                break;
            case 'hotel':
                $suggestions = array(
                    'penginapan murah',
                    'hotel terbaik',
                    'resort ' . $keyword_lower,
                    'villa ' . $keyword_lower,
                    'staycation',
                );
                break;
            default:
                $suggestions = array(
                    'wisata populer',
                    'tempat wisata',
                    'liburan keluarga',
                    'destinasi favorit',
                    'tips traveling',
                );
        }

        return $suggestions;
    }

    /**
     * Calculate estimated reading time
     */
    public function calculate_reading_time( $word_count ) {
        // Average reading speed: 200 words per minute
        $minutes = ceil( $word_count / 200 );
        return $minutes;
    }

    /**
     * Validate article against structure
     */
    public function validate_article( $content, $type ) {
        $structure = $this->get_structure( $type );
        $issues = array();

        // Check word count
        $word_count = str_word_count( strip_tags( $content ) );
        if ( $word_count < $structure['min_words'] ) {
            $issues[] = "Artikel terlalu pendek ({$word_count} kata). Minimum: {$structure['min_words']} kata.";
        }

        // Check for internal links
        if ( strpos( $content, '[INTERNAL_LINK:' ) === false && strpos( $content, '<a href=' ) === false ) {
            $issues[] = "Artikel tidak memiliki internal links. Wajib tambahkan minimal 2-3 internal links.";
        }

        // Check for brand mention
        if ( stripos( $content, self::BRAND_NAME ) === false ) {
            $issues[] = "Artikel tidak menyebutkan brand '" . self::BRAND_NAME . "'. Wajib ada di introduction.";
        }

        return array(
            'valid' => empty( $issues ),
            'issues' => $issues,
            'word_count' => $word_count,
            'reading_time' => $this->calculate_reading_time( $word_count ),
        );
    }

    /**
     * Get writing guidelines
     */
    public function get_writing_guidelines() {
        return array(
            'brand' => array(
                'name' => self::BRAND_NAME,
                'phrases' => $this->brand_phrases,
                'usage' => 'Wajib menyebutkan brand di introduction dengan natural, contoh: "sekali.id akan menyuguhkan informasi lengkap tentang..."',
            ),
            'style' => array(
                'tone' => 'Informatif, friendly, dan personal',
                'perspective' => 'Gunakan "Anda" untuk pembaca, "kami" atau "sekali.id" untuk penulis',
                'avoid' => array(
                    'Terlalu banyak H2/H3 (maksimal 4-5 heading)',
                    'Bullet points berlebihan',
                    'Kalimat terlalu formal/kaku',
                    'Kata-kata klise seperti "tidak diragukan lagi"',
                ),
            ),
            'seo' => array(
                'keyword_density' => '1-2%',
                'internal_links' => 'Minimal 2-3 internal links',
                'meta_description' => '150-160 karakter',
            ),
            'content' => array(
                'introduction' => '1-3 paragraf, fleksibel sesuai kebutuhan',
                'body' => 'Paragraf padat, informatif, tidak terlalu banyak subheading',
                'conclusion' => 'Ringkas dengan CTA dan internal links',
            ),
        );
    }
}
