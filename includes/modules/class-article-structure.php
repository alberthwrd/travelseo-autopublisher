<?php
/**
 * Article Structure Manager
 *
 * Defines and manages the comprehensive article structure
 * with 5-AI writer workflow for rich content generation.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/modules
 */

namespace TravelSEO_Autopublisher\Modules;

/**
 * Article Structure Class
 *
 * Manages article templates and section definitions for different content types.
 */
class Article_Structure {

    /**
     * Minimum word count target
     *
     * @var int
     */
    const MIN_WORD_COUNT = 700;

    /**
     * Maximum word count target
     *
     * @var int
     */
    const MAX_WORD_COUNT = 2000;

    /**
     * Content types with their section definitions
     *
     * @var array
     */
    private $structures = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_structures();
    }

    /**
     * Initialize article structures for different content types
     */
    private function init_structures() {
        // Destinasi Wisata Structure
        $this->structures['destinasi'] = array(
            'name' => 'Destinasi Wisata',
            'min_words' => 1000,
            'max_words' => 2500,
            'sections' => array(
                // AI Writer #1: The Analyst & Hook Master
                array(
                    'ai_writer' => 1,
                    'id' => 'intro',
                    'title' => null, // No H2 for intro
                    'type' => 'intro',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Tulis paragraf pembuka yang memikat dan membuat pembaca penasaran. Gunakan hook yang kuat, sebutkan apa yang membuat destinasi ini spesial, dan berikan preview singkat tentang apa yang akan dibahas.',
                    'elements' => array( 'hook', 'overview', 'promise' ),
                ),
                array(
                    'ai_writer' => 1,
                    'id' => 'sekilas',
                    'title' => 'Sekilas Tentang {keyword}',
                    'type' => 'overview',
                    'min_words' => 150,
                    'max_words' => 250,
                    'prompt_instruction' => 'Tulis gambaran umum destinasi dalam 2-3 paragraf. Jelaskan apa itu, di mana lokasinya, dan mengapa tempat ini menarik perhatian wisatawan.',
                    'elements' => array( 'description', 'location_brief', 'unique_selling_point' ),
                ),
                array(
                    'ai_writer' => 1,
                    'id' => 'daya_tarik',
                    'title' => 'Daya Tarik Utama yang Membuatnya Istimewa',
                    'type' => 'highlights',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Buat daftar 5-7 daya tarik utama dalam format bullet points. Setiap poin harus singkat tapi informatif.',
                    'elements' => array( 'bullet_list', 'highlights' ),
                ),

                // AI Writer #2: The Historian & Storyteller
                array(
                    'ai_writer' => 2,
                    'id' => 'sejarah',
                    'title' => 'Sejarah dan Latar Belakang {keyword}',
                    'type' => 'history',
                    'min_words' => 200,
                    'max_words' => 350,
                    'prompt_instruction' => 'Ceritakan sejarah destinasi secara mendalam dan menarik. Kapan dibangun/ditemukan? Siapa tokoh penting di baliknya? Bagaimana perkembangannya?',
                    'elements' => array( 'history', 'timeline', 'key_figures' ),
                ),
                array(
                    'ai_writer' => 2,
                    'id' => 'mitos',
                    'title' => 'Mitos, Legenda & Fakta Menarik',
                    'type' => 'stories',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Sajikan 2-3 mitos, legenda, atau fakta unik yang jarang diketahui. Buat pembaca merasa mendapat informasi eksklusif.',
                    'elements' => array( 'myths', 'legends', 'fun_facts' ),
                ),

                // AI Writer #3: The Practical Guide
                array(
                    'ai_writer' => 3,
                    'id' => 'lokasi',
                    'title' => 'Lokasi, Alamat Lengkap & Peta',
                    'type' => 'location',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Berikan alamat lengkap, koordinat GPS jika ada, dan deskripsi patokan terdekat. Sertakan placeholder untuk embed Google Maps.',
                    'elements' => array( 'address', 'coordinates', 'landmarks', 'map_embed' ),
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'transportasi',
                    'title' => 'Cara Menuju Lokasi (Transportasi)',
                    'type' => 'transportation',
                    'min_words' => 150,
                    'max_words' => 250,
                    'prompt_instruction' => 'Jelaskan berbagai opsi transportasi: kendaraan pribadi (rute, kondisi jalan, parkir), transportasi umum (angkutan, nomor rute, biaya), dan ojek/taksi online.',
                    'elements' => array( 'private_vehicle', 'public_transport', 'online_transport' ),
                    'has_subsections' => true,
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'tiket',
                    'title' => 'Harga Tiket Masuk (HTM) Terbaru {year}',
                    'type' => 'pricing',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Buat tabel harga tiket dengan kategori: Dewasa, Anak-anak, Turis Asing, Parkir Motor, Parkir Mobil. Pisahkan weekday dan weekend jika berbeda.',
                    'elements' => array( 'price_table', 'price_notes', 'discounts' ),
                    'has_table' => true,
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'jam_buka',
                    'title' => 'Jam Buka Operasional',
                    'type' => 'hours',
                    'min_words' => 50,
                    'max_words' => 100,
                    'prompt_instruction' => 'Buat tabel jam operasional per hari. Sebutkan juga hari libur khusus jika ada.',
                    'elements' => array( 'hours_table', 'special_hours', 'holidays' ),
                    'has_table' => true,
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'fasilitas',
                    'title' => 'Fasilitas yang Tersedia',
                    'type' => 'facilities',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Buat checklist fasilitas yang tersedia: Toilet, Mushola, Parkir, Warung Makan, Toko Suvenir, WiFi, Gazebo, dll.',
                    'elements' => array( 'facility_list', 'accessibility' ),
                ),

                // AI Writer #4: The Local Expert
                array(
                    'ai_writer' => 4,
                    'id' => 'aktivitas',
                    'title' => 'Aktivitas Seru yang Bisa Dilakukan',
                    'type' => 'activities',
                    'min_words' => 150,
                    'max_words' => 300,
                    'prompt_instruction' => 'Jelaskan 5-7 aktivitas menarik yang bisa dilakukan. Berikan detail untuk setiap aktivitas, bukan hanya daftar.',
                    'elements' => array( 'activity_list', 'activity_details', 'recommendations' ),
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'tips_lokal',
                    'title' => 'Tips Rahasia dari Warga Lokal',
                    'type' => 'insider_tips',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Berikan 5-7 tips orang dalam yang jarang diketahui wisatawan umum. Buat pembaca merasa mendapat informasi eksklusif.',
                    'elements' => array( 'insider_tips', 'best_times', 'hidden_spots' ),
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'kuliner',
                    'title' => 'Kuliner Wajib Coba di Sekitar Lokasi',
                    'type' => 'food',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Rekomendasikan 3-5 kuliner khas atau tempat makan terbaik di sekitar destinasi. Sebutkan nama tempat, menu andalan, dan kisaran harga.',
                    'elements' => array( 'food_recommendations', 'restaurant_list', 'price_range' ),
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'oleh_oleh',
                    'title' => 'Oleh-Oleh Khas yang Bisa Dibawa Pulang',
                    'type' => 'souvenirs',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Rekomendasikan 3-5 oleh-oleh khas yang unik. Sebutkan nama, harga kisaran, dan di mana membelinya.',
                    'elements' => array( 'souvenir_list', 'where_to_buy', 'price_range' ),
                ),

                // AI Writer #5: The SEO & Closer
                array(
                    'ai_writer' => 5,
                    'id' => 'itinerary',
                    'title' => 'Contoh Itinerary Perjalanan',
                    'type' => 'itinerary',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Buat contoh itinerary half-day atau full-day yang praktis. Format timeline dengan jam dan aktivitas.',
                    'elements' => array( 'timeline', 'schedule', 'duration' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'penginapan',
                    'title' => 'Rekomendasi Penginapan Terdekat',
                    'type' => 'accommodation',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Rekomendasikan 3-5 penginapan dengan berbagai range harga (budget, mid-range, premium). Sebutkan nama, kisaran harga, dan jarak dari destinasi.',
                    'elements' => array( 'hotel_list', 'price_range', 'distance' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'kesimpulan',
                    'title' => 'Kesimpulan: Mengapa {keyword} Wajib Dikunjungi?',
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Rangkum poin-poin utama dan berikan call-to-action yang kuat. Yakinkan pembaca untuk segera merencanakan kunjungan.',
                    'elements' => array( 'summary', 'cta', 'final_thoughts' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'faq',
                    'title' => 'Pertanyaan yang Sering Diajukan (FAQ)',
                    'type' => 'faq',
                    'min_words' => 150,
                    'max_words' => 300,
                    'prompt_instruction' => 'Buat 5-7 FAQ yang relevan dengan format tanya-jawab. Ambil inspirasi dari Google PAA jika tersedia.',
                    'elements' => array( 'faq_list', 'schema_faq' ),
                    'has_schema' => true,
                ),
            ),
        );

        // Kuliner Structure
        $this->structures['kuliner'] = array(
            'name' => 'Kuliner & Restoran',
            'min_words' => 800,
            'max_words' => 1800,
            'sections' => array(
                // AI Writer #1
                array(
                    'ai_writer' => 1,
                    'id' => 'intro',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Tulis pembuka yang menggugah selera. Deskripsikan aroma, rasa, atau pengalaman kuliner yang akan membuat pembaca lapar.',
                    'elements' => array( 'sensory_hook', 'overview' ),
                ),
                array(
                    'ai_writer' => 1,
                    'id' => 'sekilas',
                    'title' => 'Mengenal {keyword}',
                    'type' => 'overview',
                    'min_words' => 120,
                    'max_words' => 200,
                    'prompt_instruction' => 'Jelaskan apa itu kuliner ini, asal-usulnya, dan apa yang membuatnya spesial.',
                    'elements' => array( 'description', 'origin', 'uniqueness' ),
                ),

                // AI Writer #2
                array(
                    'ai_writer' => 2,
                    'id' => 'sejarah',
                    'title' => 'Sejarah dan Asal-Usul {keyword}',
                    'type' => 'history',
                    'min_words' => 150,
                    'max_words' => 250,
                    'prompt_instruction' => 'Ceritakan sejarah kuliner ini. Kapan pertama kali dibuat? Siapa penciptanya? Bagaimana evolusinya?',
                    'elements' => array( 'history', 'evolution', 'cultural_significance' ),
                ),
                array(
                    'ai_writer' => 2,
                    'id' => 'bahan',
                    'title' => 'Bahan-Bahan dan Proses Pembuatan',
                    'type' => 'ingredients',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Jelaskan bahan-bahan utama dan proses pembuatan secara singkat. Apa yang membuat rasanya autentik?',
                    'elements' => array( 'ingredients_list', 'cooking_process', 'secret_ingredients' ),
                ),

                // AI Writer #3
                array(
                    'ai_writer' => 3,
                    'id' => 'rekomendasi',
                    'title' => 'Rekomendasi Tempat Makan {keyword} Terbaik',
                    'type' => 'recommendations',
                    'min_words' => 200,
                    'max_words' => 400,
                    'prompt_instruction' => 'Rekomendasikan 5-7 tempat makan terbaik. Untuk setiap tempat, sebutkan: nama, alamat, menu andalan, kisaran harga, dan review singkat.',
                    'elements' => array( 'restaurant_list', 'reviews', 'price_range' ),
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'harga',
                    'title' => 'Kisaran Harga {keyword}',
                    'type' => 'pricing',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Buat tabel perbandingan harga dari berbagai tempat. Kategorikan: murah, menengah, premium.',
                    'elements' => array( 'price_table', 'price_comparison' ),
                    'has_table' => true,
                ),

                // AI Writer #4
                array(
                    'ai_writer' => 4,
                    'id' => 'tips',
                    'title' => 'Tips Menikmati {keyword} seperti Warga Lokal',
                    'type' => 'tips',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Berikan tips cara menikmati kuliner ini seperti orang lokal. Adakah cara makan khusus? Pelengkap yang wajib?',
                    'elements' => array( 'eating_tips', 'accompaniments', 'local_customs' ),
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'variasi',
                    'title' => 'Variasi dan Menu Lain yang Wajib Dicoba',
                    'type' => 'variations',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Sebutkan variasi lain dari kuliner ini atau menu pendamping yang direkomendasikan.',
                    'elements' => array( 'variations', 'side_dishes', 'drinks' ),
                ),

                // AI Writer #5
                array(
                    'ai_writer' => 5,
                    'id' => 'kesimpulan',
                    'title' => 'Kesimpulan',
                    'type' => 'conclusion',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Rangkum pengalaman kuliner dan berikan rekomendasi final. Ajak pembaca untuk segera mencoba.',
                    'elements' => array( 'summary', 'final_recommendation', 'cta' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'faq',
                    'title' => 'FAQ Seputar {keyword}',
                    'type' => 'faq',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Buat 4-6 FAQ tentang kuliner ini: halal/tidak, bisa dibawa pulang, tahan berapa lama, dll.',
                    'elements' => array( 'faq_list' ),
                    'has_schema' => true,
                ),
            ),
        );

        // Hotel Structure
        $this->structures['hotel'] = array(
            'name' => 'Hotel & Penginapan',
            'min_words' => 900,
            'max_words' => 2000,
            'sections' => array(
                array(
                    'ai_writer' => 1,
                    'id' => 'intro',
                    'title' => null,
                    'type' => 'intro',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Tulis pembuka yang menggambarkan pengalaman menginap. Apa kesan pertama saat tiba?',
                    'elements' => array( 'first_impression', 'overview' ),
                ),
                array(
                    'ai_writer' => 1,
                    'id' => 'sekilas',
                    'title' => 'Sekilas Tentang {keyword}',
                    'type' => 'overview',
                    'min_words' => 120,
                    'max_words' => 200,
                    'prompt_instruction' => 'Jelaskan hotel ini: tipe, bintang, lokasi strategis, dan target market.',
                    'elements' => array( 'hotel_type', 'star_rating', 'location', 'target_market' ),
                ),
                array(
                    'ai_writer' => 2,
                    'id' => 'lokasi',
                    'title' => 'Lokasi dan Akses',
                    'type' => 'location',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Jelaskan lokasi hotel, jarak dari bandara/stasiun, dan tempat wisata terdekat.',
                    'elements' => array( 'address', 'accessibility', 'nearby_attractions' ),
                ),
                array(
                    'ai_writer' => 2,
                    'id' => 'kamar',
                    'title' => 'Tipe Kamar dan Fasilitas',
                    'type' => 'rooms',
                    'min_words' => 150,
                    'max_words' => 300,
                    'prompt_instruction' => 'Jelaskan tipe-tipe kamar yang tersedia, ukuran, view, dan fasilitas di dalam kamar.',
                    'elements' => array( 'room_types', 'room_facilities', 'bed_types' ),
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'fasilitas',
                    'title' => 'Fasilitas Hotel',
                    'type' => 'facilities',
                    'min_words' => 120,
                    'max_words' => 220,
                    'prompt_instruction' => 'Buat daftar fasilitas hotel: kolam renang, gym, spa, restoran, meeting room, dll.',
                    'elements' => array( 'facility_list', 'highlight_facilities' ),
                ),
                array(
                    'ai_writer' => 3,
                    'id' => 'harga',
                    'title' => 'Harga Kamar dan Cara Booking',
                    'type' => 'pricing',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Buat tabel harga per tipe kamar. Sebutkan juga cara booking dan tips mendapat harga terbaik.',
                    'elements' => array( 'price_table', 'booking_tips', 'best_deals' ),
                    'has_table' => true,
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'review',
                    'title' => 'Review Jujur: Kelebihan dan Kekurangan',
                    'type' => 'review',
                    'min_words' => 150,
                    'max_words' => 250,
                    'prompt_instruction' => 'Berikan review jujur dengan format kelebihan dan kekurangan. Buat pembaca bisa memutuskan.',
                    'elements' => array( 'pros', 'cons', 'rating' ),
                ),
                array(
                    'ai_writer' => 4,
                    'id' => 'tips',
                    'title' => 'Tips Menginap di {keyword}',
                    'type' => 'tips',
                    'min_words' => 80,
                    'max_words' => 150,
                    'prompt_instruction' => 'Berikan tips untuk mendapat pengalaman terbaik: request kamar, waktu check-in, dll.',
                    'elements' => array( 'stay_tips', 'best_practices' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'kesimpulan',
                    'title' => 'Kesimpulan: Worth It atau Tidak?',
                    'type' => 'conclusion',
                    'min_words' => 100,
                    'max_words' => 180,
                    'prompt_instruction' => 'Berikan verdict final. Untuk siapa hotel ini cocok? Apakah worth it untuk harganya?',
                    'elements' => array( 'verdict', 'recommendation', 'cta' ),
                ),
                array(
                    'ai_writer' => 5,
                    'id' => 'faq',
                    'title' => 'FAQ',
                    'type' => 'faq',
                    'min_words' => 100,
                    'max_words' => 200,
                    'prompt_instruction' => 'Buat 4-6 FAQ: check-in/out time, breakfast included, pet-friendly, dll.',
                    'elements' => array( 'faq_list' ),
                    'has_schema' => true,
                ),
            ),
        );

        // Default/General Structure
        $this->structures['umum'] = $this->structures['destinasi'];
    }

    /**
     * Get structure for a content type
     *
     * @param string $type Content type
     * @return array Structure definition
     */
    public function get_structure( $type = 'destinasi' ) {
        return $this->structures[ $type ] ?? $this->structures['umum'];
    }

    /**
     * Get all available structures
     *
     * @return array All structures
     */
    public function get_all_structures() {
        return $this->structures;
    }

    /**
     * Get sections for a specific AI writer
     *
     * @param string $type      Content type
     * @param int    $ai_writer AI writer number (1-5)
     * @return array Sections for that AI writer
     */
    public function get_sections_for_ai_writer( $type, $ai_writer ) {
        $structure = $this->get_structure( $type );
        $sections = array();

        foreach ( $structure['sections'] as $section ) {
            if ( $section['ai_writer'] === $ai_writer ) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * Generate prompt for a specific section
     *
     * @param array  $section      Section definition
     * @param string $keyword      Main keyword
     * @param array  $research_data Research data from scraper
     * @return string Generated prompt
     */
    public function generate_section_prompt( $section, $keyword, $research_data = array() ) {
        $year = date( 'Y' );
        $title = str_replace( array( '{keyword}', '{year}' ), array( $keyword, $year ), $section['title'] ?? '' );

        $prompt = "Kamu adalah penulis konten wisata profesional Indonesia.\n\n";
        $prompt .= "TUGAS: {$section['prompt_instruction']}\n\n";
        $prompt .= "KEYWORD UTAMA: {$keyword}\n";
        $prompt .= "JUDUL SECTION: {$title}\n";
        $prompt .= "TARGET KATA: {$section['min_words']} - {$section['max_words']} kata\n\n";

        // Add research data if available
        if ( ! empty( $research_data ) ) {
            $prompt .= "DATA RISET YANG TERSEDIA:\n";
            if ( ! empty( $research_data['facts'] ) ) {
                $prompt .= "- Fakta: " . implode( ', ', array_slice( $research_data['facts'], 0, 5 ) ) . "\n";
            }
            if ( ! empty( $research_data['prices'] ) ) {
                $prompt .= "- Info Harga: " . implode( ', ', $research_data['prices'] ) . "\n";
            }
            if ( ! empty( $research_data['hours'] ) ) {
                $prompt .= "- Jam Operasional: " . implode( ', ', $research_data['hours'] ) . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "ATURAN PENULISAN:\n";
        $prompt .= "1. Gunakan Bahasa Indonesia yang baik dan natural\n";
        $prompt .= "2. Tulis dalam gaya informatif tapi engaging\n";
        $prompt .= "3. Hindari kalimat klise dan filler\n";
        $prompt .= "4. Berikan informasi yang akurat dan bermanfaat\n";
        $prompt .= "5. Jangan ulangi informasi yang sudah ada di section lain\n\n";

        // Add specific instructions based on section type
        if ( ! empty( $section['has_table'] ) ) {
            $prompt .= "FORMAT: Sertakan tabel dalam format Markdown.\n";
        }

        if ( ! empty( $section['has_schema'] ) && $section['type'] === 'faq' ) {
            $prompt .= "FORMAT: Gunakan format FAQ dengan pertanyaan sebagai H3 dan jawaban sebagai paragraf.\n";
        }

        $prompt .= "\nTulis konten sekarang:";

        return $prompt;
    }

    /**
     * Calculate word count target for each AI writer
     *
     * @param string $type Content type
     * @return array Word count targets per AI writer
     */
    public function get_word_targets_per_ai( $type = 'destinasi' ) {
        $structure = $this->get_structure( $type );
        $targets = array(
            1 => array( 'min' => 0, 'max' => 0 ),
            2 => array( 'min' => 0, 'max' => 0 ),
            3 => array( 'min' => 0, 'max' => 0 ),
            4 => array( 'min' => 0, 'max' => 0 ),
            5 => array( 'min' => 0, 'max' => 0 ),
        );

        foreach ( $structure['sections'] as $section ) {
            $ai = $section['ai_writer'];
            $targets[ $ai ]['min'] += $section['min_words'];
            $targets[ $ai ]['max'] += $section['max_words'];
        }

        return $targets;
    }

    /**
     * Validate article against structure requirements
     *
     * @param string $content Article content
     * @param string $type    Content type
     * @return array Validation result
     */
    public function validate_article( $content, $type = 'destinasi' ) {
        $structure = $this->get_structure( $type );
        $result = array(
            'valid' => true,
            'word_count' => str_word_count( strip_tags( $content ) ),
            'issues' => array(),
            'score' => 100,
        );

        // Check word count
        if ( $result['word_count'] < $structure['min_words'] ) {
            $result['valid'] = false;
            $result['issues'][] = "Jumlah kata ({$result['word_count']}) kurang dari minimum ({$structure['min_words']})";
            $result['score'] -= 20;
        }

        // Check for required sections (H2 headers)
        foreach ( $structure['sections'] as $section ) {
            if ( ! empty( $section['title'] ) ) {
                $title_pattern = str_replace( array( '{keyword}', '{year}' ), '.*', preg_quote( $section['title'], '/' ) );
                if ( ! preg_match( '/<h2[^>]*>.*' . $title_pattern . '.*<\/h2>/i', $content ) &&
                     ! preg_match( '/##\s*' . $title_pattern . '/i', $content ) ) {
                    $result['issues'][] = "Section '{$section['title']}' tidak ditemukan";
                    $result['score'] -= 5;
                }
            }
        }

        // Check for tables if required
        $needs_table = false;
        foreach ( $structure['sections'] as $section ) {
            if ( ! empty( $section['has_table'] ) ) {
                $needs_table = true;
                break;
            }
        }
        if ( $needs_table && strpos( $content, '<table' ) === false && strpos( $content, '|' ) === false ) {
            $result['issues'][] = 'Artikel memerlukan tabel tapi tidak ditemukan';
            $result['score'] -= 10;
        }

        // Check for FAQ if required
        $needs_faq = false;
        foreach ( $structure['sections'] as $section ) {
            if ( $section['type'] === 'faq' ) {
                $needs_faq = true;
                break;
            }
        }
        if ( $needs_faq && stripos( $content, 'faq' ) === false && stripos( $content, 'pertanyaan' ) === false ) {
            $result['issues'][] = 'Section FAQ tidak ditemukan';
            $result['score'] -= 10;
        }

        $result['valid'] = $result['score'] >= 70;

        return $result;
    }
}
