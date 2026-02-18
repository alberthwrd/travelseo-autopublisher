<?php
/**
 * Professional Indonesian Text Spinner V5
 *
 * PERBAIKAN V5:
 * - Hapus semua sinonim berbahaya (zona, cuti, bertransformasi, dll)
 * - Proteksi nama tempat, brand, angka, HTML
 * - Hanya spin kata-kata yang AMAN
 * - Intensity default lebih rendah (30%)
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/spinner
 */

namespace TravelSEO_Autopublisher\Spinner;

class Spinner {

    private $synonyms = array();
    private $phrases = array();
    private $connectors = array();
    private $intensity = 30;
    private $protected_words = array();

    public function __construct() {
        $this->load_dictionary();
    }

    /**
     * Set protected words (nama tempat, brand, dll)
     */
    public function set_protected_words($words) {
        $this->protected_words = array_map('strtolower', $words);
    }

    /**
     * Load SAFE synonym dictionary
     */
    private function load_dictionary() {
        // === SINONIM AMAN (tanpa yang merusak konteks) ===
        $this->synonyms = array(
            // Kata kerja aman
            'merupakan'    => array('adalah', 'ialah', 'yakni', 'yaitu'),
            'memberikan'   => array('menyajikan', 'menawarkan', 'menghadirkan', 'menyediakan'),
            'menawarkan'   => array('menyediakan', 'menghadirkan', 'menyajikan'),
            'mendapatkan'  => array('memperoleh', 'meraih', 'mendapat'),
            'menikmati'    => array('merasakan', 'menghayati'),
            'mengunjungi'  => array('mendatangi', 'menyambangi'),
            'melihat'      => array('menyaksikan', 'mengamati'),
            'mengetahui'   => array('memahami', 'mengerti'),
            'menggunakan'  => array('memakai', 'memanfaatkan'),
            'mencoba'      => array('menjajal', 'merasakan'),
            'disarankan'   => array('direkomendasikan', 'dianjurkan'),
            'terletak'     => array('berlokasi', 'berada'),
            'menjelajahi'  => array('mengeksplorasi', 'menelusuri'),
            'memiliki'     => array('mempunyai', 'punya'),

            // Kata sifat aman
            'indah'        => array('cantik', 'elok', 'memesona'),
            'menarik'      => array('menawan', 'memukau', 'memikat'),
            'terkenal'     => array('populer', 'ternama'),
            'populer'      => array('terkenal', 'diminati'),
            'unik'         => array('khas', 'istimewa'),
            'lengkap'      => array('komprehensif', 'menyeluruh'),
            'nyaman'       => array('tenteram', 'asri'),
            'lezat'        => array('nikmat', 'sedap', 'enak'),
            'cocok'        => array('sesuai', 'pas', 'ideal'),

            // Kata benda aman
            'pengunjung'   => array('wisatawan', 'pelancong'),
            'wisatawan'    => array('pelancong', 'pengunjung'),
            'pemandangan'  => array('panorama', 'lanskap'),
            'keindahan'    => array('pesona', 'keelokkan'),
            'pengalaman'   => array('sensasi', 'kesan'),
            'informasi'    => array('info', 'keterangan'),
            'aktivitas'    => array('kegiatan'),
            'suasana'      => array('atmosfer', 'nuansa'),
            'makanan'      => array('hidangan', 'sajian', 'kuliner'),
            'pilihan'      => array('opsi', 'alternatif'),
            'daerah'       => array('wilayah', 'kawasan'),

            // Frasa aman
            'selain itu'      => array('di samping itu', 'tak hanya itu'),
            'oleh karena itu' => array('maka dari itu', 'karenanya'),
            'tidak hanya'     => array('bukan hanya', 'tak hanya'),
            'sangat'          => array('amat', 'sungguh', 'begitu'),
            'bisa'            => array('dapat', 'mampu'),
            'harus'           => array('wajib', 'perlu'),
            'juga'            => array('pula', 'turut'),
            'karena'          => array('sebab', 'lantaran'),
            'namun'           => array('tetapi', 'akan tetapi'),
            'bahkan'          => array('malah', 'justru'),
            'biasanya'        => array('umumnya', 'pada umumnya'),
            'terutama'        => array('khususnya', 'utamanya'),

            // Wisata aman
            'destinasi'    => array('tujuan wisata', 'tempat wisata'),
            'spot foto'    => array('titik foto', 'lokasi berfoto'),
            'tiket masuk'  => array('karcis masuk', 'biaya masuk'),
        );

        // Variasi frasa
        $this->phrases = array(
            'openers' => array(
                'Berbicara tentang' => array('Membahas mengenai', 'Berkaitan dengan'),
                'Bagi Anda yang' => array('Untuk Anda yang', 'Jika Anda'),
                'Tak heran jika' => array('Wajar saja bila', 'Tidak mengherankan jika'),
            ),
            'transitions' => array(
                'Selain itu' => array('Di samping itu', 'Tak hanya itu'),
                'Sementara itu' => array('Di sisi lain', 'Adapun'),
            ),
        );

        $this->connectors = array(
            'addition' => array('Selain itu,', 'Di samping itu,', 'Lebih lanjut,', 'Tak hanya itu,'),
            'contrast' => array('Namun,', 'Akan tetapi,', 'Meski demikian,', 'Di sisi lain,'),
            'cause'    => array('Oleh karena itu,', 'Maka dari itu,', 'Karenanya,'),
            'example'  => array('Misalnya,', 'Contohnya,', 'Sebagai contoh,'),
        );
    }

    public function set_intensity($intensity) {
        $this->intensity = max(0, min(100, $intensity));
    }

    /**
     * Spin text content (SAFE)
     */
    public function spin($text, $options = array()) {
        $defaults = array(
            'intensity' => $this->intensity,
            'preserve_keywords' => array(),
            'vary_sentence_structure' => false, // Default OFF (berbahaya)
            'vary_connectors' => true,
            'humanize' => true,
        );

        $options = array_merge($defaults, $options);

        // Merge protected words
        $all_protected = array_merge($this->protected_words, array_map('strtolower', $options['preserve_keywords']));

        // Step 1: Replace synonyms (SAFE)
        $text = $this->replace_synonyms($text, $options['intensity'], $all_protected);

        // Step 2: Replace phrases
        $text = $this->replace_phrases($text, $options['intensity']);

        // Step 3: Vary connectors
        if ($options['vary_connectors']) {
            $text = $this->vary_connectors($text);
        }

        // Step 4: Humanize
        if ($options['humanize']) {
            $text = $this->humanize($text);
        }

        return $text;
    }

    private function replace_synonyms($text, $intensity, $preserve = array()) {
        foreach ($this->synonyms as $word => $synonyms) {
            if (in_array(strtolower($word), $preserve)) continue;
            if (mt_rand(1, 100) > $intensity) continue;

            // Check context safety
            if ($this->is_near_protected($text, $word, $preserve)) continue;

            $replacement = $synonyms[array_rand($synonyms)];
            $text = $this->replace_preserve_case($text, $word, $replacement, 1);
        }
        return $text;
    }

    /**
     * Check if word is near a protected word
     */
    private function is_near_protected($text, $word, $protected) {
        $text_lower = strtolower($text);
        $word_pos = stripos($text_lower, strtolower($word));
        if ($word_pos === false) return false;

        foreach ($protected as $pw) {
            $pw_pos = stripos($text_lower, $pw);
            if ($pw_pos !== false && abs($pw_pos - $word_pos) < 25) {
                return true;
            }
        }

        // Near numbers/prices
        if (preg_match('/(?:Rp|IDR|\d{3,})/', substr($text, max(0, $word_pos - 20), 40))) {
            return true;
        }

        return false;
    }

    private function replace_phrases($text, $intensity) {
        foreach ($this->phrases as $category => $phrases) {
            foreach ($phrases as $original => $variations) {
                if (mt_rand(1, 100) > $intensity) continue;
                if (stripos($text, $original) !== false) {
                    $replacement = $variations[array_rand($variations)];
                    $text = $this->replace_preserve_case($text, $original, $replacement, 1);
                }
            }
        }
        return $text;
    }

    private function vary_connectors($text) {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $new_sentences = array();

        foreach ($sentences as $sentence) {
            foreach ($this->connectors as $type => $connectors) {
                foreach ($connectors as $connector) {
                    if (stripos($sentence, $connector) === 0 && mt_rand(1, 100) <= 40) {
                        $new_connector = $connectors[array_rand($connectors)];
                        $sentence = $new_connector . substr($sentence, strlen($connector));
                        break 2;
                    }
                }
            }
            $new_sentences[] = $sentence;
        }

        return implode(' ', $new_sentences);
    }

    private function humanize($text) {
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = preg_replace('/\.\s*\./', '.', $text);
        $text = $this->reduce_repetition($text);
        return $text;
    }

    private function reduce_repetition($text) {
        $words = str_word_count(strtolower($text), 1);
        $word_counts = array_count_values($words);

        foreach ($word_counts as $word => $count) {
            if ($count > 4 && isset($this->synonyms[$word])) {
                $synonyms = $this->synonyms[$word];
                $occurrences = 0;
                $text = preg_replace_callback(
                    '/\b' . preg_quote($word, '/') . '\b/i',
                    function($matches) use (&$occurrences, $synonyms) {
                        $occurrences++;
                        if ($occurrences > 3 && $occurrences % 2 === 0) {
                            return $synonyms[array_rand($synonyms)];
                        }
                        return $matches[0];
                    },
                    $text
                );
            }
        }
        return $text;
    }

    private function replace_preserve_case($text, $search, $replacement, $limit = -1) {
        $pattern = '/\b' . preg_quote($search, '/') . '\b/iu';
        $count = 0;
        return preg_replace_callback($pattern, function($matches) use ($replacement, $limit, &$count) {
            if ($limit > 0 && $count >= $limit) return $matches[0];
            $count++;
            $original = $matches[0];
            if (ctype_upper($original)) return strtoupper($replacement);
            if (ctype_upper($original[0])) return ucfirst($replacement);
            return strtolower($replacement);
        }, $text);
    }

    public function get_stats($original, $spun) {
        $original_words = str_word_count($original);
        $spun_words = str_word_count($spun);
        similar_text($original, $spun, $similarity);
        return array(
            'original_words' => $original_words,
            'spun_words' => $spun_words,
            'similarity_percent' => round($similarity, 2),
            'uniqueness_percent' => round(100 - $similarity, 2),
        );
    }

    public function add_synonyms($word, $synonyms) {
        $word = strtolower($word);
        if (isset($this->synonyms[$word])) {
            $this->synonyms[$word] = array_unique(array_merge($this->synonyms[$word], $synonyms));
        } else {
            $this->synonyms[$word] = $synonyms;
        }
    }
}
