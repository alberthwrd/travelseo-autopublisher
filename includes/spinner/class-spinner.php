<?php
/**
 * Professional Indonesian Text Spinner
 *
 * Advanced text spinning system for creating unique, human-like content
 * that passes AI detection and plagiarism checkers.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/spinner
 */

namespace TravelSEO_Autopublisher\Spinner;

/**
 * Spinner Class
 *
 * Provides professional-grade text spinning with multiple techniques:
 * - Synonym replacement
 * - Sentence restructuring
 * - Phrase variation
 * - Active/passive voice conversion
 * - Connector word variation
 */
class Spinner {

    /**
     * Synonym dictionary
     *
     * @var array
     */
    private $synonyms = array();

    /**
     * Phrase variations
     *
     * @var array
     */
    private $phrases = array();

    /**
     * Connector words
     *
     * @var array
     */
    private $connectors = array();

    /**
     * Spinning intensity (0-100)
     *
     * @var int
     */
    private $intensity = 50;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dictionary();
    }

    /**
     * Load synonym dictionary and phrase variations
     */
    private function load_dictionary() {
        // =====================================================
        // KAMUS SINONIM BAHASA INDONESIA - SANGAT LENGKAP
        // =====================================================

        $this->synonyms = array(
            // === KATA KERJA (VERBS) ===
            'adalah' => array( 'merupakan', 'ialah', 'yakni', 'yaitu', 'termasuk' ),
            'ada' => array( 'terdapat', 'tersedia', 'hadir', 'eksis', 'dijumpai' ),
            'memiliki' => array( 'mempunyai', 'punya', 'mengandung', 'dilengkapi dengan' ),
            'menjadi' => array( 'berubah menjadi', 'menjelma menjadi', 'bertransformasi menjadi' ),
            'memberikan' => array( 'menyajikan', 'menawarkan', 'menghadirkan', 'menyediakan', 'menyuguhkan' ),
            'membuat' => array( 'menciptakan', 'menghasilkan', 'memproduksi', 'membangun', 'merancang' ),
            'melihat' => array( 'memandang', 'mengamati', 'menyaksikan', 'menonton', 'memperhatikan' ),
            'mendapatkan' => array( 'memperoleh', 'meraih', 'mendapat', 'menghasilkan', 'menerima' ),
            'menggunakan' => array( 'memakai', 'memanfaatkan', 'mengaplikasikan', 'menerapkan' ),
            'menunjukkan' => array( 'memperlihatkan', 'menampilkan', 'membuktikan', 'mengindikasikan' ),
            'menyediakan' => array( 'menawarkan', 'memberikan', 'menghadirkan', 'memfasilitasi' ),
            'mencoba' => array( 'menguji', 'mengetes', 'menjajal', 'mengeksplorasi' ),
            'menikmati' => array( 'merasakan', 'mengapresiasi', 'menyukai', 'mengecap' ),
            'mengunjungi' => array( 'mendatangi', 'berkunjung ke', 'menyambangi', 'singgah di' ),
            'terletak' => array( 'berlokasi', 'berada', 'terposisi', 'berdiri' ),
            'dikenal' => array( 'terkenal', 'populer', 'masyhur', 'kondang', 'familiar' ),
            'menarik' => array( 'memikat', 'menawan', 'memesona', 'menggoda', 'menggiurkan' ),
            'menawarkan' => array( 'menyajikan', 'menghadirkan', 'memberikan', 'menyediakan' ),
            'mencakup' => array( 'meliputi', 'termasuk', 'mengandung', 'terdiri dari' ),
            'membantu' => array( 'menolong', 'mendukung', 'memfasilitasi', 'mempermudah' ),
            'menemukan' => array( 'menjumpai', 'mendapati', 'menghadapi', 'bertemu dengan' ),
            'menampilkan' => array( 'memperlihatkan', 'menyajikan', 'menghadirkan', 'memamerkan' ),
            'menghabiskan' => array( 'meluangkan', 'menyisihkan', 'mengalokasikan' ),
            'berkembang' => array( 'bertumbuh', 'meningkat', 'maju', 'progresif' ),
            'berlangsung' => array( 'terjadi', 'berjalan', 'dilaksanakan', 'diselenggarakan' ),
            'disarankan' => array( 'direkomendasikan', 'dianjurkan', 'disugesti', 'diusulkan' ),
            'diperlukan' => array( 'dibutuhkan', 'dipersyaratkan', 'wajib', 'harus ada' ),
            'dilengkapi' => array( 'dibekali', 'disertai', 'ditambah dengan', 'memiliki' ),

            // === KATA SIFAT (ADJECTIVES) ===
            'bagus' => array( 'baik', 'apik', 'menarik', 'menawan', 'memukau', 'ciamik' ),
            'indah' => array( 'cantik', 'elok', 'menawan', 'memesona', 'mempesona', 'asri' ),
            'besar' => array( 'luas', 'lebar', 'megah', 'agung', 'raksasa', 'jumbo' ),
            'kecil' => array( 'mungil', 'mini', 'kompak', 'sederhana', 'ringkas' ),
            'banyak' => array( 'melimpah', 'berlimpah', 'beragam', 'berbagai', 'aneka', 'bermacam-macam' ),
            'sedikit' => array( 'beberapa', 'sejumlah', 'segelintir', 'terbatas' ),
            'baru' => array( 'terbaru', 'anyar', 'modern', 'terkini', 'mutakhir' ),
            'lama' => array( 'tua', 'kuno', 'klasik', 'bersejarah', 'tempo dulu' ),
            'cepat' => array( 'kilat', 'sigap', 'gesit', 'tangkas', 'ekspres' ),
            'lambat' => array( 'pelan', 'perlahan', 'santai', 'tidak terburu-buru' ),
            'mudah' => array( 'gampang', 'simpel', 'sederhana', 'praktis', 'tidak sulit' ),
            'sulit' => array( 'susah', 'rumit', 'kompleks', 'menantang', 'tidak mudah' ),
            'murah' => array( 'terjangkau', 'ekonomis', 'hemat', 'ramah kantong', 'bersahabat' ),
            'mahal' => array( 'premium', 'mewah', 'eksklusif', 'high-end', 'berkelas' ),
            'enak' => array( 'lezat', 'nikmat', 'sedap', 'gurih', 'mantap', 'maknyus' ),
            'segar' => array( 'fresh', 'sejuk', 'menyegarkan', 'baru', 'asri' ),
            'nyaman' => array( 'cozy', 'tenteram', 'tenang', 'damai', 'rileks' ),
            'ramai' => array( 'padat', 'penuh', 'sesak', 'hiruk pikuk', 'meriah' ),
            'sepi' => array( 'lengang', 'sunyi', 'tenang', 'hening', 'tidak ramai' ),
            'lengkap' => array( 'komplit', 'komprehensif', 'menyeluruh', 'tuntas', 'detail' ),
            'unik' => array( 'khas', 'istimewa', 'spesial', 'berbeda', 'otentik', 'autentik' ),
            'terkenal' => array( 'populer', 'kondang', 'masyhur', 'legendaris', 'ikonik' ),
            'strategis' => array( 'ideal', 'prima', 'optimal', 'tepat', 'menguntungkan' ),
            'tradisional' => array( 'klasik', 'konvensional', 'turun-temurun', 'warisan' ),
            'modern' => array( 'kontemporer', 'terkini', 'mutakhir', 'up-to-date', 'kekinian' ),
            'alami' => array( 'natural', 'asli', 'murni', 'organik', 'asri' ),
            'buatan' => array( 'artifisial', 'sintetis', 'man-made', 'tiruan' ),
            'penting' => array( 'krusial', 'esensial', 'vital', 'signifikan', 'utama' ),
            'menarik' => array( 'atraktif', 'memikat', 'menawan', 'eye-catching', 'memukau' ),
            'berbagai' => array( 'beragam', 'aneka', 'bermacam-macam', 'bervariasi', 'multi' ),
            'sempurna' => array( 'ideal', 'prima', 'optimal', 'terbaik', 'paripurna' ),
            'luar biasa' => array( 'istimewa', 'spektakuler', 'fantastis', 'menakjubkan', 'wow' ),

            // === KATA BENDA (NOUNS) ===
            'tempat' => array( 'lokasi', 'area', 'spot', 'destinasi', 'kawasan', 'wilayah' ),
            'wisata' => array( 'pariwisata', 'rekreasi', 'liburan', 'traveling', 'jalan-jalan' ),
            'pengunjung' => array( 'wisatawan', 'turis', 'tamu', 'pelancong', 'traveler' ),
            'pemandangan' => array( 'panorama', 'view', 'lanskap', 'bentangan alam', 'vista' ),
            'fasilitas' => array( 'sarana', 'prasarana', 'amenitas', 'kelengkapan', 'perlengkapan' ),
            'harga' => array( 'tarif', 'biaya', 'ongkos', 'rate', 'nominal' ),
            'tiket' => array( 'karcis', 'pass', 'voucher', 'akses masuk' ),
            'makanan' => array( 'kuliner', 'hidangan', 'santapan', 'sajian', 'menu' ),
            'minuman' => array( 'beverages', 'drinks', 'cairan', 'es', 'jus' ),
            'penginapan' => array( 'akomodasi', 'hotel', 'resort', 'villa', 'homestay' ),
            'perjalanan' => array( 'trip', 'tour', 'ekspedisi', 'petualangan', 'jelajah' ),
            'pengalaman' => array( 'experience', 'sensasi', 'kesan', 'momen', 'kenangan' ),
            'keindahan' => array( 'pesona', 'kecantikan', 'keelokkan', 'kemolekan', 'eksotisme' ),
            'suasana' => array( 'atmosfer', 'nuansa', 'ambience', 'vibes', 'aura' ),
            'aktivitas' => array( 'kegiatan', 'acara', 'agenda', 'program', 'event' ),
            'informasi' => array( 'info', 'data', 'keterangan', 'detail', 'rincian' ),
            'waktu' => array( 'jam', 'periode', 'durasi', 'momen', 'saat' ),
            'jarak' => array( 'rentang', 'radius', 'range', 'perjalanan', 'tempuh' ),
            'pilihan' => array( 'opsi', 'alternatif', 'variasi', 'ragam', 'seleksi' ),
            'keuntungan' => array( 'manfaat', 'benefit', 'kelebihan', 'plus point', 'nilai tambah' ),
            'kekurangan' => array( 'kelemahan', 'minus', 'downside', 'keterbatasan' ),
            'tips' => array( 'saran', 'rekomendasi', 'anjuran', 'trik', 'panduan' ),
            'cara' => array( 'metode', 'teknik', 'langkah', 'prosedur', 'tahapan' ),
            'sejarah' => array( 'riwayat', 'kisah', 'cerita', 'latar belakang', 'asal-usul' ),
            'budaya' => array( 'kultur', 'tradisi', 'adat', 'kebiasaan', 'warisan' ),
            'alam' => array( 'nature', 'lingkungan', 'ekosistem', 'habitat' ),
            'pantai' => array( 'pesisir', 'beach', 'tepi laut', 'bibir pantai' ),
            'gunung' => array( 'pegunungan', 'bukit', 'puncak', 'dataran tinggi' ),
            'air terjun' => array( 'waterfall', 'curug', 'grojogan', 'cascade' ),
            'danau' => array( 'telaga', 'lake', 'waduk', 'bendungan' ),
            'hutan' => array( 'rimba', 'belantara', 'forest', 'pepohonan' ),
            'taman' => array( 'kebun', 'park', 'garden', 'area hijau' ),
            'museum' => array( 'galeri', 'gedung pameran', 'pusat sejarah' ),
            'restoran' => array( 'rumah makan', 'warung', 'kedai', 'cafe', 'tempat makan' ),

            // === KATA KETERANGAN (ADVERBS) ===
            'sangat' => array( 'amat', 'sungguh', 'benar-benar', 'luar biasa', 'super' ),
            'cukup' => array( 'lumayan', 'agak', 'relatif', 'memadai', 'terbilang' ),
            'selalu' => array( 'senantiasa', 'terus-menerus', 'konsisten', 'rutin' ),
            'sering' => array( 'kerap', 'acap kali', 'berkali-kali', 'lazim' ),
            'jarang' => array( 'sesekali', 'kadang-kadang', 'tidak sering', 'langka' ),
            'biasanya' => array( 'umumnya', 'lazimnya', 'pada umumnya', 'normalnya' ),
            'terutama' => array( 'khususnya', 'utamanya', 'terlebih', 'apalagi' ),
            'sebenarnya' => array( 'sesungguhnya', 'sejatinya', 'pada dasarnya', 'faktanya' ),
            'tentunya' => array( 'pastinya', 'tentu saja', 'sudah pasti', 'jelas' ),
            'mungkin' => array( 'barangkali', 'kemungkinan', 'bisa jadi', 'boleh jadi' ),
            'segera' => array( 'secepatnya', 'langsung', 'tanpa menunda', 'saat itu juga' ),
            'secara' => array( 'dengan cara', 'melalui', 'lewat', 'via' ),
            'hampir' => array( 'nyaris', 'mendekati', 'kurang lebih', 'sekitar' ),
            'bahkan' => array( 'malah', 'justru', 'terlebih lagi', 'lebih dari itu' ),
            'hanya' => array( 'cuma', 'sekedar', 'semata', 'melulu' ),
            'juga' => array( 'pula', 'turut', 'ikut', 'serta' ),
            'sudah' => array( 'telah', 'udah', 'sempat', 'pernah' ),
            'belum' => array( 'masih belum', 'tidak/belum', 'hingga kini belum' ),
            'akan' => array( 'bakal', 'hendak', 'berencana untuk', 'siap untuk' ),
            'sedang' => array( 'tengah', 'lagi', 'dalam proses', 'saat ini' ),

            // === KATA PENGHUBUNG (CONJUNCTIONS) ===
            'dan' => array( 'serta', 'juga', 'sekaligus', 'plus', 'beserta' ),
            'atau' => array( 'maupun', 'ataupun', 'entah', 'bisa juga' ),
            'tetapi' => array( 'namun', 'akan tetapi', 'tapi', 'meski demikian' ),
            'karena' => array( 'sebab', 'lantaran', 'dikarenakan', 'mengingat', 'pasalnya' ),
            'sehingga' => array( 'hingga', 'sampai', 'akibatnya', 'alhasil', 'maka' ),
            'jika' => array( 'apabila', 'bila', 'kalau', 'andai', 'seandainya' ),
            'ketika' => array( 'saat', 'sewaktu', 'tatkala', 'pada saat', 'waktu' ),
            'setelah' => array( 'sesudah', 'usai', 'pasca', 'begitu', 'selepas' ),
            'sebelum' => array( 'sebelumnya', 'pra', 'menjelang', 'prior to' ),
            'selain' => array( 'di samping', 'selain dari', 'tak hanya', 'bukan hanya' ),
            'meskipun' => array( 'walaupun', 'kendati', 'biarpun', 'sekalipun' ),
            'agar' => array( 'supaya', 'untuk', 'demi', 'guna' ),
            'oleh karena itu' => array( 'maka dari itu', 'dengan demikian', 'karenanya', 'oleh sebab itu' ),
            'dengan demikian' => array( 'oleh karena itu', 'maka', 'sehingga', 'alhasil' ),
            'di sisi lain' => array( 'di lain pihak', 'sebaliknya', 'namun di sisi lain' ),
            'selanjutnya' => array( 'kemudian', 'berikutnya', 'lalu', 'setelah itu' ),
            'pertama' => array( 'pertama-tama', 'yang pertama', 'langkah awal', 'mulanya' ),
            'kedua' => array( 'yang kedua', 'selanjutnya', 'berikutnya' ),
            'terakhir' => array( 'yang terakhir', 'akhirnya', 'pada akhirnya', 'sebagai penutup' ),

            // === FRASA UMUM WISATA ===
            'wajib dikunjungi' => array( 'harus dikunjungi', 'must visit', 'tidak boleh dilewatkan', 'destinasi impian' ),
            'sangat direkomendasikan' => array( 'highly recommended', 'patut dicoba', 'layak dikunjungi' ),
            'cocok untuk' => array( 'ideal untuk', 'sempurna untuk', 'pas untuk', 'tepat untuk' ),
            'tidak kalah menarik' => array( 'sama menariknya', 'tak kalah memukau', 'juga patut dicoba' ),
            'bisa dibilang' => array( 'dapat dikatakan', 'terbilang', 'termasuk', 'dikategorikan sebagai' ),
            'perlu diketahui' => array( 'penting untuk diketahui', 'yang perlu dicatat', 'catatan penting' ),
            'tidak heran' => array( 'wajar saja', 'pantas saja', 'masuk akal' ),
            'menjadi favorit' => array( 'menjadi primadona', 'menjadi andalan', 'menjadi pilihan utama' ),
            'patut dicoba' => array( 'layak dicoba', 'wajib dicoba', 'recommended' ),
            'semakin populer' => array( 'makin hits', 'kian diminati', 'tambah terkenal' ),
        );

        // =====================================================
        // VARIASI FRASA PEMBUKA DAN PENUTUP
        // =====================================================

        $this->phrases = array(
            // Frasa pembuka paragraf
            'openers' => array(
                'Berbicara tentang' => array( 'Membahas mengenai', 'Menyinggung soal', 'Berkaitan dengan' ),
                'Tidak dapat dipungkiri' => array( 'Tak bisa disangkal', 'Sudah menjadi rahasia umum', 'Faktanya' ),
                'Menariknya' => array( 'Yang menarik', 'Uniknya', 'Hal yang menarik' ),
                'Perlu diketahui' => array( 'Penting untuk dicatat', 'Yang perlu diingat', 'Catatan penting' ),
                'Bagi Anda yang' => array( 'Untuk Anda yang', 'Jika Anda', 'Apabila Anda' ),
                'Salah satu' => array( 'Satu di antara', 'Termasuk salah satu', 'Merupakan salah satu' ),
                'Seperti namanya' => array( 'Sesuai namanya', 'Sebagaimana namanya', 'Seperti yang tersirat dari namanya' ),
                'Tak heran jika' => array( 'Wajar saja bila', 'Pantas saja kalau', 'Tidak mengherankan jika' ),
            ),
            // Frasa transisi
            'transitions' => array(
                'Selain itu' => array( 'Di samping itu', 'Lebih dari itu', 'Tak hanya itu' ),
                'Tidak hanya itu' => array( 'Bukan hanya itu', 'Lebih dari sekadar itu', 'Tak cuma itu' ),
                'Yang tak kalah menarik' => array( 'Sama menariknya', 'Hal lain yang memikat', 'Daya tarik lainnya' ),
                'Berbeda dengan' => array( 'Tidak seperti', 'Berlainan dengan', 'Kontras dengan' ),
                'Sementara itu' => array( 'Di sisi lain', 'Pada saat yang sama', 'Adapun' ),
                'Dengan kata lain' => array( 'Artinya', 'Maksudnya', 'Singkatnya' ),
            ),
            // Frasa penutup
            'closers' => array(
                'Jadi, tunggu apa lagi?' => array( 'So, kapan mau ke sana?', 'Yuk, segera rencanakan!', 'Masih ragu?' ),
                'Selamat berlibur!' => array( 'Happy traveling!', 'Selamat menikmati!', 'Have a great trip!' ),
                'Semoga bermanfaat' => array( 'Semoga membantu', 'Semoga informatif', 'Semoga berguna' ),
            ),
        );

        // =====================================================
        // KATA PENGHUBUNG UNTUK VARIASI KALIMAT
        // =====================================================

        $this->connectors = array(
            'addition' => array( 'Selain itu,', 'Di samping itu,', 'Lebih lanjut,', 'Tak hanya itu,', 'Bahkan,', 'Terlebih lagi,' ),
            'contrast' => array( 'Namun,', 'Akan tetapi,', 'Meski demikian,', 'Di sisi lain,', 'Sebaliknya,', 'Kendati demikian,' ),
            'cause' => array( 'Oleh karena itu,', 'Maka dari itu,', 'Dengan demikian,', 'Alhasil,', 'Karenanya,', 'Sebab itu,' ),
            'example' => array( 'Misalnya,', 'Contohnya,', 'Sebagai contoh,', 'Seperti halnya,', 'Ambil contoh,' ),
            'emphasis' => array( 'Yang pasti,', 'Jelas sekali,', 'Tentunya,', 'Sudah pasti,', 'Tak diragukan lagi,' ),
            'sequence' => array( 'Pertama,', 'Selanjutnya,', 'Kemudian,', 'Berikutnya,', 'Terakhir,', 'Akhirnya,' ),
            'summary' => array( 'Singkatnya,', 'Intinya,', 'Pada dasarnya,', 'Secara garis besar,', 'Kesimpulannya,' ),
        );
    }

    /**
     * Set spinning intensity
     *
     * @param int $intensity Intensity level (0-100)
     */
    public function set_intensity( $intensity ) {
        $this->intensity = max( 0, min( 100, $intensity ) );
    }

    /**
     * Spin text content
     *
     * @param string $text    Text to spin
     * @param array  $options Spinning options
     * @return string Spun text
     */
    public function spin( $text, $options = array() ) {
        $defaults = array(
            'intensity' => $this->intensity,
            'preserve_keywords' => array(),
            'vary_sentence_structure' => true,
            'vary_connectors' => true,
            'humanize' => true,
        );

        $options = array_merge( $defaults, $options );

        // Step 1: Replace synonyms
        $text = $this->replace_synonyms( $text, $options['intensity'], $options['preserve_keywords'] );

        // Step 2: Replace phrases
        $text = $this->replace_phrases( $text, $options['intensity'] );

        // Step 3: Vary connectors
        if ( $options['vary_connectors'] ) {
            $text = $this->vary_connectors( $text );
        }

        // Step 4: Vary sentence structure (active/passive)
        if ( $options['vary_sentence_structure'] ) {
            $text = $this->vary_sentence_structure( $text, $options['intensity'] );
        }

        // Step 5: Humanize (add natural variations)
        if ( $options['humanize'] ) {
            $text = $this->humanize( $text );
        }

        return $text;
    }

    /**
     * Replace words with synonyms
     *
     * @param string $text              Text to process
     * @param int    $intensity         Replacement intensity
     * @param array  $preserve_keywords Keywords to preserve
     * @return string Processed text
     */
    private function replace_synonyms( $text, $intensity, $preserve_keywords = array() ) {
        // Convert preserve keywords to lowercase for comparison
        $preserve_lower = array_map( 'strtolower', $preserve_keywords );

        foreach ( $this->synonyms as $word => $synonyms ) {
            // Skip if word is in preserve list
            if ( in_array( strtolower( $word ), $preserve_lower ) ) {
                continue;
            }

            // Decide whether to replace based on intensity
            if ( mt_rand( 1, 100 ) > $intensity ) {
                continue;
            }

            // Pick a random synonym
            $replacement = $synonyms[ array_rand( $synonyms ) ];

            // Replace with case preservation
            $text = $this->replace_preserve_case( $text, $word, $replacement );
        }

        return $text;
    }

    /**
     * Replace phrases with variations
     *
     * @param string $text      Text to process
     * @param int    $intensity Replacement intensity
     * @return string Processed text
     */
    private function replace_phrases( $text, $intensity ) {
        foreach ( $this->phrases as $category => $phrases ) {
            foreach ( $phrases as $original => $variations ) {
                if ( mt_rand( 1, 100 ) > $intensity ) {
                    continue;
                }

                if ( stripos( $text, $original ) !== false ) {
                    $replacement = $variations[ array_rand( $variations ) ];
                    $text = $this->replace_preserve_case( $text, $original, $replacement );
                }
            }
        }

        return $text;
    }

    /**
     * Vary connector words at sentence beginnings
     *
     * @param string $text Text to process
     * @return string Processed text
     */
    private function vary_connectors( $text ) {
        // Split into sentences
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text );
        $new_sentences = array();

        foreach ( $sentences as $sentence ) {
            // Check if sentence starts with a connector
            foreach ( $this->connectors as $type => $connectors ) {
                foreach ( $connectors as $connector ) {
                    if ( stripos( $sentence, $connector ) === 0 ) {
                        // 50% chance to replace
                        if ( mt_rand( 1, 100 ) <= 50 ) {
                            $new_connector = $connectors[ array_rand( $connectors ) ];
                            $sentence = $new_connector . substr( $sentence, strlen( $connector ) );
                        }
                        break 2;
                    }
                }
            }
            $new_sentences[] = $sentence;
        }

        return implode( ' ', $new_sentences );
    }

    /**
     * Vary sentence structure (active/passive conversion)
     *
     * @param string $text      Text to process
     * @param int    $intensity Conversion intensity
     * @return string Processed text
     */
    private function vary_sentence_structure( $text, $intensity ) {
        // Simple active to passive patterns for Indonesian
        $patterns = array(
            // "X menawarkan Y" -> "Y ditawarkan oleh X"
            '/(\w+)\s+menawarkan\s+(\w+)/i' => '$2 ditawarkan oleh $1',
            // "X menyediakan Y" -> "Y disediakan oleh X"
            '/(\w+)\s+menyediakan\s+(\w+)/i' => '$2 disediakan oleh $1',
            // "X memiliki Y" -> "Y dimiliki oleh X"
            '/(\w+)\s+memiliki\s+(\w+)/i' => '$2 dimiliki oleh $1',
        );

        foreach ( $patterns as $pattern => $replacement ) {
            if ( mt_rand( 1, 100 ) <= $intensity / 2 ) { // Lower chance for structure changes
                $text = preg_replace( $pattern, $replacement, $text, 1 );
            }
        }

        return $text;
    }

    /**
     * Add human-like variations
     *
     * @param string $text Text to process
     * @return string Processed text
     */
    private function humanize( $text ) {
        // Add occasional filler words (very sparingly)
        $fillers = array(
            'sebenarnya' => 'sejatinya',
            'memang' => 'tentunya',
            'tentu saja' => 'sudah pasti',
        );

        // Vary punctuation slightly
        $text = preg_replace( '/\.\s+/', '. ', $text ); // Normalize spacing

        // Add slight variations to repeated phrases
        $text = $this->reduce_repetition( $text );

        return $text;
    }

    /**
     * Reduce word repetition
     *
     * @param string $text Text to process
     * @return string Processed text
     */
    private function reduce_repetition( $text ) {
        // Find repeated words (appearing more than 3 times)
        $words = str_word_count( strtolower( $text ), 1 );
        $word_counts = array_count_values( $words );

        foreach ( $word_counts as $word => $count ) {
            if ( $count > 3 && isset( $this->synonyms[ $word ] ) ) {
                // Replace some occurrences with synonyms
                $synonyms = $this->synonyms[ $word ];
                $occurrences = 0;

                $text = preg_replace_callback(
                    '/\b' . preg_quote( $word, '/' ) . '\b/i',
                    function( $matches ) use ( &$occurrences, $synonyms, $count ) {
                        $occurrences++;
                        // Replace every other occurrence after the first 2
                        if ( $occurrences > 2 && $occurrences % 2 === 0 ) {
                            return $synonyms[ array_rand( $synonyms ) ];
                        }
                        return $matches[0];
                    },
                    $text
                );
            }
        }

        return $text;
    }

    /**
     * Replace text while preserving case
     *
     * @param string $text        Original text
     * @param string $search      Text to find
     * @param string $replacement Replacement text
     * @return string Processed text
     */
    private function replace_preserve_case( $text, $search, $replacement ) {
        // Find all occurrences with their original case
        $pattern = '/\b' . preg_quote( $search, '/' ) . '\b/i';

        return preg_replace_callback( $pattern, function( $matches ) use ( $replacement ) {
            $original = $matches[0];

            // Check case pattern
            if ( ctype_upper( $original ) ) {
                // ALL CAPS
                return strtoupper( $replacement );
            } elseif ( ctype_upper( $original[0] ) ) {
                // Title Case
                return ucfirst( $replacement );
            } else {
                // lowercase
                return strtolower( $replacement );
            }
        }, $text );
    }

    /**
     * Get spinning statistics
     *
     * @param string $original Original text
     * @param string $spun     Spun text
     * @return array Statistics
     */
    public function get_stats( $original, $spun ) {
        $original_words = str_word_count( $original );
        $spun_words = str_word_count( $spun );

        // Calculate similarity
        similar_text( $original, $spun, $similarity );

        // Count changed words
        $original_arr = str_word_count( strtolower( $original ), 1 );
        $spun_arr = str_word_count( strtolower( $spun ), 1 );
        $changed = count( array_diff( $original_arr, $spun_arr ) );

        return array(
            'original_words' => $original_words,
            'spun_words' => $spun_words,
            'similarity_percent' => round( $similarity, 2 ),
            'uniqueness_percent' => round( 100 - $similarity, 2 ),
            'words_changed' => $changed,
            'change_percent' => round( ( $changed / max( $original_words, 1 ) ) * 100, 2 ),
        );
    }

    /**
     * Add custom synonyms
     *
     * @param string $word     Word to add synonyms for
     * @param array  $synonyms Array of synonyms
     */
    public function add_synonyms( $word, $synonyms ) {
        $word = strtolower( $word );
        if ( isset( $this->synonyms[ $word ] ) ) {
            $this->synonyms[ $word ] = array_unique( array_merge( $this->synonyms[ $word ], $synonyms ) );
        } else {
            $this->synonyms[ $word ] = $synonyms;
        }
    }

    /**
     * Get all synonyms for a word
     *
     * @param string $word Word to look up
     * @return array Synonyms or empty array
     */
    public function get_synonyms( $word ) {
        return $this->synonyms[ strtolower( $word ) ] ?? array();
    }
}
