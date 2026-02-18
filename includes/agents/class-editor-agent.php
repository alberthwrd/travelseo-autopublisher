<?php
/**
 * Project Hyperion - Agent #6: The Editor
 * Human-like Polish & SEO Audit
 *
 * Tugas: Membaca ulang keseluruhan artikel, memperbaiki tata bahasa,
 * spinning kata agar natural, memastikan lolos AI checker dan plagiarism,
 * serta melakukan audit SEO akhir.
 */

if (!defined('ABSPATH')) exit;

class TSA_Editor_Agent {

    private $site_name = '';
    private $synonyms = array();

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
        $this->load_synonyms();
    }

    /**
     * Edit dan polish artikel
     */
    public function edit($title, $article_html) {
        $log = array();
        $log[] = '[Editor] Memulai editing dan polishing untuk: "' . $title . '"';

        // Step 1: Remove AI patterns
        $html = $this->remove_ai_patterns($article_html);
        $log[] = '[Editor] AI patterns removed';

        // Step 2: Professional Indonesian spinning
        $html = $this->spin_content($html);
        $log[] = '[Editor] Content spinning applied';

        // Step 3: Fix grammar dan tata bahasa
        $html = $this->fix_grammar($html);
        $log[] = '[Editor] Grammar fixed';

        // Step 4: Replace "saya/aku" with brand
        $html = $this->replace_first_person($html);
        $log[] = '[Editor] First person replaced with brand';

        // Step 5: AI-powered final polish (jika tersedia)
        $html = $this->ai_final_polish($title, $html, $log);

        // Step 6: SEO Audit
        $seo_score = $this->seo_audit($title, $html);
        $log[] = '[Editor] SEO Score: ' . $seo_score['overall'] . '/100';

        // Step 7: Readability check
        $readability = $this->readability_check($html);
        $log[] = '[Editor] Readability Score: ' . $readability['score'] . '/100';

        $word_count = str_word_count(strip_tags($html));
        $log[] = '[Editor] ✓ Editing selesai (' . $word_count . ' kata)';

        return array(
            'article_html' => $html,
            'seo_score'    => $seo_score,
            'readability'  => $readability,
            'word_count'   => $word_count,
            'log'          => $log,
        );
    }

    /**
     * Load kamus sinonim Bahasa Indonesia yang sangat lengkap
     */
    private function load_synonyms() {
        $this->synonyms = array(
            // === KATA KERJA UMUM ===
            'merupakan'   => array('adalah', 'ialah', 'yakni', 'yaitu', 'termasuk'),
            'menjadi'     => array('berubah menjadi', 'menjelma', 'bertransformasi'),
            'memiliki'    => array('mempunyai', 'punya', 'mengandung', 'dilengkapi'),
            'memberikan'  => array('menyajikan', 'menyediakan', 'menghadirkan', 'menyuguhkan', 'menawarkan'),
            'menawarkan'  => array('menyediakan', 'menghadirkan', 'menyuguhkan', 'menyajikan'),
            'menyediakan' => array('menyiapkan', 'menghadirkan', 'memfasilitasi'),
            'menghadirkan' => array('menyajikan', 'menyuguhkan', 'menampilkan', 'memperlihatkan'),
            'menampilkan' => array('memperlihatkan', 'mempertontonkan', 'menyajikan', 'memamerkan'),
            'mendapatkan' => array('memperoleh', 'meraih', 'mendapat', 'menemukan'),
            'menemukan'   => array('menjumpai', 'mendapati', 'menemui'),
            'menikmati'   => array('merasakan', 'mengecap', 'menyesap', 'menghayati'),
            'mengunjungi' => array('mendatangi', 'menyambangi', 'berkunjung ke', 'singgah di'),
            'melihat'     => array('memandang', 'menyaksikan', 'mengamati', 'meninjau'),
            'mengetahui'  => array('memahami', 'mengerti', 'mengenal', 'menyadari'),
            'menggunakan' => array('memakai', 'memanfaatkan', 'mempergunakan'),
            'membuat'     => array('menciptakan', 'menghasilkan', 'mewujudkan', 'merancang'),
            'mencoba'     => array('menjajal', 'menguji', 'mengetes', 'merasakan'),
            'membantu'    => array('menolong', 'memudahkan', 'mendukung', 'menunjang'),
            'memastikan'  => array('meyakinkan', 'menjamin', 'mengonfirmasi'),
            'menyarankan' => array('merekomendasikan', 'menganjurkan', 'mengusulkan'),
            'disarankan'  => array('direkomendasikan', 'dianjurkan', 'sebaiknya'),
            'terletak'    => array('berlokasi', 'berada', 'terposisi', 'berdiri'),
            'berkunjung'  => array('datang', 'menyambangi', 'mendatangi', 'singgah'),
            'berjalan'    => array('melangkah', 'bergerak', 'menyusuri'),
            'menjelajahi' => array('mengeksplorasi', 'menyusuri', 'menyelami', 'menelusuri'),

            // === KATA SIFAT ===
            'indah'       => array('cantik', 'elok', 'memesona', 'menawan', 'rupawan'),
            'bagus'       => array('baik', 'apik', 'menarik', 'menawan', 'istimewa'),
            'menarik'     => array('menawan', 'memukau', 'mengagumkan', 'memikat', 'atraktif'),
            'terkenal'    => array('populer', 'masyhur', 'kondang', 'kenamaan', 'ternama'),
            'populer'     => array('terkenal', 'digemari', 'diminati', 'digandrungi'),
            'unik'        => array('khas', 'istimewa', 'berbeda', 'spesial', 'eksklusif'),
            'lengkap'     => array('komprehensif', 'menyeluruh', 'komplit', 'tuntas'),
            'besar'       => array('luas', 'megah', 'raksasa', 'jumbo'),
            'kecil'       => array('mungil', 'mini', 'kompak', 'sederhana'),
            'banyak'      => array('beragam', 'bermacam-macam', 'melimpah', 'berlimpah', 'aneka'),
            'berbagai'    => array('beragam', 'bermacam-macam', 'aneka', 'beraneka'),
            'penting'     => array('krusial', 'esensial', 'signifikan', 'vital'),
            'mudah'       => array('gampang', 'simpel', 'praktis', 'sederhana'),
            'nyaman'      => array('tenteram', 'tenang', 'asri', 'sejuk'),
            'lezat'       => array('nikmat', 'sedap', 'enak', 'gurih', 'menggugah selera'),
            'segar'       => array('sejuk', 'bersih', 'asri', 'menyegarkan'),
            'strategis'   => array('ideal', 'tepat', 'prima', 'optimal'),
            'cocok'       => array('sesuai', 'pas', 'ideal', 'tepat'),
            'terbaik'     => array('terunggul', 'paling baik', 'nomor satu', 'terdepan'),
            'modern'      => array('kontemporer', 'kekinian', 'terkini', 'mutakhir'),
            'tradisional' => array('konvensional', 'klasik', 'turun-temurun', 'warisan'),
            'alami'       => array('natural', 'asli', 'murni', 'asri'),

            // === KATA BENDA ===
            'tempat'      => array('lokasi', 'area', 'kawasan', 'zona', 'spot'),
            'pengunjung'  => array('wisatawan', 'pelancong', 'turis', 'pengunjung', 'tamu'),
            'wisatawan'   => array('pelancong', 'turis', 'pengunjung', 'traveler'),
            'pemandangan' => array('panorama', 'lanskap', 'bentangan alam', 'vista'),
            'keindahan'   => array('pesona', 'kecantikan', 'keelokkan', 'kemolekan'),
            'pengalaman'  => array('experience', 'sensasi', 'kesan', 'momen'),
            'perjalanan'  => array('trip', 'wisata', 'petualangan', 'ekspedisi', 'tur'),
            'liburan'     => array('rekreasi', 'pelesiran', 'vakansi', 'cuti'),
            'fasilitas'   => array('sarana', 'prasarana', 'infrastruktur', 'perlengkapan'),
            'informasi'   => array('info', 'keterangan', 'data', 'kabar'),
            'aktivitas'   => array('kegiatan', 'acara', 'agenda', 'hal'),
            'suasana'     => array('atmosfer', 'nuansa', 'aura', 'ambiance'),
            'makanan'     => array('hidangan', 'santapan', 'sajian', 'kuliner', 'menu'),
            'minuman'     => array('sajian minum', 'racikan', 'olahan minum'),
            'harga'       => array('tarif', 'biaya', 'ongkos', 'rate'),
            'pilihan'     => array('opsi', 'alternatif', 'ragam', 'varian'),
            'keluarga'    => array('sanak keluarga', 'anggota keluarga', 'orang-orang tercinta'),
            'teman'       => array('sahabat', 'kawan', 'rekan', 'sobat'),
            'daerah'      => array('wilayah', 'kawasan', 'region', 'area'),
            'sejarah'     => array('riwayat', 'kisah', 'catatan masa lalu', 'jejak historis'),
            'budaya'      => array('tradisi', 'adat', 'kebudayaan', 'warisan budaya'),
            'alam'        => array('lingkungan', 'ekosistem', 'habitat', 'bentang alam'),

            // === FRASA UMUM ===
            'selain itu'  => array('di samping itu', 'lebih dari itu', 'tak hanya itu', 'tidak hanya itu'),
            'oleh karena itu' => array('maka dari itu', 'dengan demikian', 'sehingga', 'karenanya'),
            'dengan demikian' => array('oleh sebab itu', 'maka dari itu', 'karenanya'),
            'namun demikian' => array('meskipun begitu', 'walau demikian', 'kendati demikian'),
            'tidak hanya'  => array('bukan hanya', 'tak hanya', 'bukan cuma'),
            'sangat'       => array('amat', 'sungguh', 'begitu', 'luar biasa', 'betul-betul'),
            'tentu saja'   => array('pastinya', 'sudah pasti', 'tak diragukan', 'jelas'),
            'pada dasarnya' => array('sejatinya', 'hakikatnya', 'intinya', 'esensinya'),
            'saat ini'     => array('kini', 'dewasa ini', 'sekarang', 'masa kini'),
            'di samping'   => array('selain', 'di luar', 'tak hanya'),
            'wajib'        => array('harus', 'perlu', 'mesti', 'kudu'),
            'bisa'         => array('dapat', 'mampu', 'sanggup'),
            'harus'        => array('wajib', 'perlu', 'mesti'),
            'juga'         => array('pula', 'turut', 'ikut'),
            'akan'         => array('bakal', 'hendak'),
            'agar'         => array('supaya', 'demi', 'guna'),
            'karena'       => array('sebab', 'lantaran', 'mengingat'),
            'sehingga'     => array('hingga', 'sampai', 'akibatnya'),
            'namun'        => array('tetapi', 'akan tetapi', 'kendati', 'meski'),
            'tetapi'       => array('namun', 'akan tetapi', 'meskipun demikian'),
            'bahkan'       => array('malah', 'malahan', 'justru'),

            // === KATA KETERANGAN ===
            'kemudian'     => array('selanjutnya', 'lalu', 'setelah itu', 'berikutnya'),
            'selanjutnya'  => array('kemudian', 'lalu', 'berikutnya', 'setelah itu'),
            'pertama'      => array('yang utama', 'langkah awal', 'hal pertama'),
            'terakhir'     => array('yang terakhir', 'penutup', 'akhirnya'),
            'terutama'     => array('khususnya', 'utamanya', 'terlebih'),
            'biasanya'     => array('umumnya', 'lazimnya', 'pada umumnya', 'kerap kali'),
            'seringkali'   => array('kerap kali', 'acap kali', 'sering', 'lazimnya'),
            'segera'       => array('lekas', 'cepat', 'secepatnya', 'sesegera mungkin'),

            // === KATA WISATA SPESIFIK ===
            'destinasi'    => array('tujuan wisata', 'objek wisata', 'tempat wisata'),
            'panorama'     => array('pemandangan', 'lanskap', 'bentangan alam'),
            'spot foto'    => array('titik foto', 'lokasi berfoto', 'area foto'),
            'tiket masuk'  => array('karcis masuk', 'biaya masuk', 'tarif masuk'),
            'jam buka'     => array('jam operasional', 'waktu buka', 'jadwal operasional'),
            'berkunjung'   => array('datang', 'menyambangi', 'mengunjungi', 'singgah'),
        );
    }

    /**
     * Remove AI-generated patterns
     */
    private function remove_ai_patterns($html) {
        $ai_patterns = array(
            '/\btentu saja,?\s/i' => '',
            '/\bperlu dicatat bahwa\b/i' => '',
            '/\bperlu diingat bahwa\b/i' => '',
            '/\bpada kesempatan kali ini\b/i' => '',
            '/\bdalam konteks ini\b/i' => '',
            '/\bsebagai catatan\b/i' => '',
            '/\bseperti yang kita ketahui\b/i' => '',
            '/\bseperti yang telah disebutkan\b/i' => '',
            '/\bsebagaimana yang kita tahu\b/i' => '',
            '/\bhal ini menunjukkan bahwa\b/i' => '',
            '/\bdapat disimpulkan bahwa\b/i' => '',
            '/\bmenariknya,?\s/i' => '',
            '/\byang tak kalah menarik\b/i' => 'yang juga patut dicoba',
            '/\byang tak kalah penting\b/i' => 'yang juga perlu diperhatikan',
            '/\btidak bisa dipungkiri\b/i' => '',
            '/\btidak dapat dipungkiri\b/i' => '',
            '/\bpatut diakui\b/i' => '',
            '/\bsungguh luar biasa\b/i' => 'sangat mengesankan',
            '/\bluar biasa indah\b/i' => 'sangat memesona',
            '/\bbenar-benar menakjubkan\b/i' => 'sangat mengagumkan',
            '/\bsangat-sangat\b/i' => 'sangat',
            '/\bbenar-benar luar biasa\b/i' => 'sangat istimewa',
        );

        foreach ($ai_patterns as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        // Clean double spaces after removal
        $html = preg_replace('/\s{2,}/', ' ', $html);
        $html = preg_replace('/\.\s*\./', '.', $html);

        return $html;
    }

    /**
     * Professional content spinning
     */
    private function spin_content($html) {
        // Split HTML into text and tags
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $spin_count = 0;
        $spin_intensity = (int)get_option('tsa_spin_intensity', 35); // 0-100

        foreach ($parts as $part) {
            // Skip HTML tags
            if (preg_match('/^<[^>]+>$/', $part)) {
                $result .= $part;
                continue;
            }

            // Spin text content
            $words_in_part = str_word_count($part);
            if ($words_in_part < 3) {
                $result .= $part;
                continue;
            }

            foreach ($this->synonyms as $word => $alternatives) {
                // Calculate probability based on intensity
                if (mt_rand(1, 100) > $spin_intensity) continue;

                $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
                if (preg_match($pattern, $part)) {
                    $replacement = $alternatives[array_rand($alternatives)];
                    $part = preg_replace($pattern, $replacement, $part, 1);
                    $spin_count++;
                }
            }

            $result .= $part;
        }

        return $result;
    }

    /**
     * Fix grammar dan tata bahasa
     */
    private function fix_grammar($html) {
        // Fix double words
        $html = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $html);

        // Fix spacing around punctuation
        $html = preg_replace('/\s+([.,;:!?])/', '$1', $html);
        $html = preg_replace('/([.,;:!?])(?=[A-Za-z])/', '$1 ', $html);

        // Fix capitalization after period
        $html = preg_replace_callback('/\.\s+([a-z])/u', function($m) {
            return '. ' . mb_strtoupper($m[1]);
        }, $html);

        // Fix "di" prefix (Indonesian grammar)
        $html = preg_replace('/\bdi\s+(mana|sini|situ|sana|atas|bawah|dalam|luar|antara|balik|samping|depan|belakang|sekitar)\b/i', 'di $1', $html);

        // Fix common typos
        $typos = array(
            '/\byang yang\b/i' => 'yang',
            '/\bdan dan\b/i' => 'dan',
            '/\buntuk untuk\b/i' => 'untuk',
            '/\bdari dari\b/i' => 'dari',
            '/\bdengan dengan\b/i' => 'dengan',
            '/\bini ini\b/i' => 'ini',
            '/\bitu itu\b/i' => 'itu',
        );

        foreach ($typos as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        return $html;
    }

    /**
     * Replace first person pronouns with brand name
     */
    private function replace_first_person($html) {
        // Split into tags and text
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';

        foreach ($parts as $part) {
            if (preg_match('/^<[^>]+>$/', $part)) {
                $result .= $part;
                continue;
            }

            // Replace "saya" and "aku" with brand name (case-insensitive)
            $part = preg_replace('/\bsaya\b/i', $this->site_name, $part);
            $part = preg_replace('/\baku\b/i', $this->site_name, $part);
            $part = preg_replace('/\bkami\b/i', $this->site_name, $part);

            $result .= $part;
        }

        return $result;
    }

    /**
     * AI-powered final polish
     */
    private function ai_final_polish($title, $html, &$log) {
        // Jika artikel terlalu panjang, skip AI polish
        if (strlen($html) > 25000) {
            $log[] = '[Editor] Artikel terlalu panjang untuk AI polish, skip';
            return $html;
        }

        $prompt = "Kamu adalah editor bahasa Indonesia senior. Tugasmu HANYA mempoles tata bahasa dan membuat artikel lebih natural.

ARTIKEL:
{$html}

TUGAS (HANYA perbaiki, JANGAN ubah struktur atau kurangi konten):
1. Perbaiki kalimat yang terdengar kaku atau tidak natural
2. Pastikan setiap paragraf mengalir dengan baik ke paragraf berikutnya
3. Pastikan TIDAK ADA kata \"saya\", \"aku\", atau \"kami\" - ganti dengan \"{$this->site_name}\"
4. Perbaiki tata bahasa Indonesia yang salah
5. PERTAHANKAN semua HTML tags (h2, h3, p, strong, em, table, ul, ol, li, blockquote, hr)
6. JANGAN hapus atau ubah heading
7. JANGAN kurangi jumlah kata
8. JANGAN tambahkan kata-kata berlebihan

Output: Artikel yang sudah dipoles dalam format HTML yang SAMA PERSIS strukturnya.";

        $result = $this->call_ai($prompt);

        if (!empty($result)) {
            $original_words = str_word_count(strip_tags($html));
            $polished_words = str_word_count(strip_tags($result));

            // Only accept if word count is within 20% range
            if ($polished_words >= $original_words * 0.8 && $polished_words <= $original_words * 1.2) {
                $log[] = '[Editor] AI final polish berhasil (' . $polished_words . ' kata)';
                return $result;
            }
        }

        $log[] = '[Editor] AI polish skipped (fallback to manual)';
        return $html;
    }

    /**
     * SEO Audit
     */
    private function seo_audit($title, $html) {
        $text = strip_tags($html);
        $word_count = str_word_count($text);
        $title_lower = strtolower($title);
        $text_lower = strtolower($text);

        $scores = array();

        // 1. Word count (target: 1000-3000)
        if ($word_count >= 1500) $scores['word_count'] = 100;
        elseif ($word_count >= 1000) $scores['word_count'] = 80;
        elseif ($word_count >= 700) $scores['word_count'] = 60;
        else $scores['word_count'] = 40;

        // 2. Keyword in content
        $title_words = array_filter(explode(' ', $title_lower), function($w) { return strlen($w) > 3; });
        $keyword_count = 0;
        foreach ($title_words as $kw) {
            $keyword_count += substr_count($text_lower, $kw);
        }
        $density = ($word_count > 0) ? ($keyword_count / $word_count) * 100 : 0;
        if ($density >= 1 && $density <= 3) $scores['keyword_density'] = 100;
        elseif ($density >= 0.5 && $density <= 4) $scores['keyword_density'] = 80;
        else $scores['keyword_density'] = 50;

        // 3. Headings present
        $h2_count = preg_match_all('/<h2/i', $html);
        if ($h2_count >= 4 && $h2_count <= 8) $scores['headings'] = 100;
        elseif ($h2_count >= 2) $scores['headings'] = 70;
        else $scores['headings'] = 40;

        // 4. Bold/Strong usage
        $bold_count = preg_match_all('/<strong/i', $html);
        if ($bold_count >= 5 && $bold_count <= 20) $scores['formatting'] = 100;
        elseif ($bold_count >= 3) $scores['formatting'] = 70;
        else $scores['formatting'] = 40;

        // 5. Tables or lists present
        $has_table = preg_match('/<table/i', $html);
        $has_list = preg_match('/<[uo]l/i', $html);
        if ($has_table && $has_list) $scores['rich_content'] = 100;
        elseif ($has_table || $has_list) $scores['rich_content'] = 70;
        else $scores['rich_content'] = 30;

        // 6. Internal links placeholder
        $link_count = preg_match_all('/<a\s/i', $html);
        if ($link_count >= 3) $scores['links'] = 100;
        elseif ($link_count >= 1) $scores['links'] = 60;
        else $scores['links'] = 30;

        // 7. Meta description length
        $scores['meta'] = 80; // Will be checked by Connector

        // Overall score
        $overall = array_sum($scores) / count($scores);

        return array(
            'overall'  => round($overall),
            'details'  => $scores,
            'word_count' => $word_count,
            'keyword_density' => round($density, 2),
            'h2_count' => $h2_count,
            'bold_count' => $bold_count,
        );
    }

    /**
     * Readability check
     */
    private function readability_check($html) {
        $text = strip_tags($html);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        $word_count = str_word_count($text);

        // Average sentence length
        $avg_sentence_length = ($sentence_count > 0) ? $word_count / $sentence_count : 0;

        // Paragraph count
        $paragraph_count = preg_match_all('/<p/i', $html);

        // Average paragraph length
        $avg_paragraph_length = ($paragraph_count > 0) ? $word_count / $paragraph_count : 0;

        // Score calculation
        $score = 100;

        // Penalize very long sentences
        if ($avg_sentence_length > 25) $score -= 20;
        elseif ($avg_sentence_length > 20) $score -= 10;

        // Penalize very long paragraphs
        if ($avg_paragraph_length > 150) $score -= 15;
        elseif ($avg_paragraph_length > 100) $score -= 5;

        // Bonus for good structure
        if (preg_match_all('/<h2/i', $html) >= 3) $score += 5;
        if (preg_match('/<[uo]l/i', $html)) $score += 5;
        if (preg_match('/<table/i', $html)) $score += 5;

        $score = max(0, min(100, $score));

        return array(
            'score' => $score,
            'avg_sentence_length' => round($avg_sentence_length, 1),
            'avg_paragraph_length' => round($avg_paragraph_length, 1),
            'sentence_count' => $sentence_count,
            'paragraph_count' => $paragraph_count,
        );
    }

    /**
     * Call AI
     */
    private function call_ai($prompt) {
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result)) return $result;

        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) return $this->call_openai($prompt, $api_key);
        return '';
    }

    private function call_duckduckgo_ai($prompt) {
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10, 'headers' => array('x-vqd-accept' => '1', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'), 'sslverify' => false,
        ));
        if (is_wp_error($token_response)) return '';
        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        $chat_response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 60, 'headers' => array('Content-Type' => 'application/json', 'x-vqd-4' => $vqd, 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'body' => wp_json_encode(array('model' => 'gpt-4o-mini', 'messages' => array(array('role' => 'user', 'content' => $prompt)))),
            'sslverify' => false,
        ));
        if (is_wp_error($chat_response)) return '';

        $body = wp_remote_retrieve_body($chat_response);
        $result = '';
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;
                $json = json_decode($data, true);
                if (isset($json['message'])) $result .= $json['message'];
            }
        }
        return $result;
    }

    private function call_openai($prompt, $api_key) {
        $model = get_option('tsa_openai_model', 'gpt-4o-mini');
        $base_url = get_option('tsa_openai_base_url', 'https://api.openai.com/v1');
        $response = wp_remote_post($base_url . '/chat/completions', array(
            'timeout' => 90, 'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array('model' => $model, 'messages' => array(
                array('role' => 'system', 'content' => 'Kamu adalah editor bahasa Indonesia senior. Perbaiki tata bahasa dan buat artikel lebih natural.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.5, 'max_tokens' => 8000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
