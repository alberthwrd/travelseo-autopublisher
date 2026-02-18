<?php
/**
 * Project Hyperion - Agent #6: The Editor V5
 * Smart Polish & Safe Spinning
 *
 * PERBAIKAN V5:
 * - Spinner AMAN: proteksi nama tempat, brand, angka, HTML
 * - Sinonim yang TIDAK merusak konteks
 * - Hapus sinonim berbahaya: zona, cuti, bertransformasi, dll
 * - Proteksi "Sekali.id" dan nama destinasi
 * - Hanya spin kata-kata yang AMAN untuk di-spin
 *
 * @version 5.0.0
 */

if (!defined('ABSPATH')) exit;

class TSA_Editor_Agent {

    private $site_name = '';
    private $safe_synonyms = array();
    private $protected_words = array();

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
        $this->load_safe_synonyms();
    }

    /**
     * Edit dan polish artikel
     */
    public function edit($title, $article_html) {
        $log = array();
        $log[] = '[Editor V5] Memulai editing untuk: "' . $title . '"';

        // Build protected words list dari judul
        $this->build_protected_list($title);

        // Step 1: Remove AI patterns
        $html = $this->remove_ai_patterns($article_html);
        $log[] = '[Editor V5] AI patterns removed';

        // Step 2: SAFE spinning (tidak merusak kata)
        $html = $this->safe_spin($html);
        $log[] = '[Editor V5] Safe spinning applied';

        // Step 3: Fix grammar
        $html = $this->fix_grammar($html);
        $log[] = '[Editor V5] Grammar fixed';

        // Step 4: Replace first person
        $html = $this->replace_first_person($html);
        $log[] = '[Editor V5] First person replaced';

        // Step 5: AI polish (optional)
        $html = $this->ai_final_polish($title, $html, $log);

        // Step 6: SEO Audit
        $seo_score = $this->seo_audit($title, $html);
        $log[] = '[Editor V5] SEO Score: ' . $seo_score['overall'] . '/100';

        // Step 7: Readability
        $readability = $this->readability_check($html);
        $log[] = '[Editor V5] Readability: ' . $readability['score'] . '/100';

        $word_count = str_word_count(strip_tags($html));
        $log[] = '[Editor V5] ✓ Selesai (' . $word_count . ' kata)';

        return array(
            'article_html' => $html,
            'seo_score'    => $seo_score,
            'readability'  => $readability,
            'word_count'   => $word_count,
            'log'          => $log,
        );
    }

    /**
     * Build list kata yang TIDAK BOLEH di-spin
     */
    private function build_protected_list($title) {
        $this->protected_words = array(
            // Brand
            strtolower($this->site_name),
            'sekali.id',
            'sekali',
            // Nama dari judul (semua kata > 3 huruf)
        );

        // Tambahkan kata-kata dari judul sebagai protected
        $title_words = preg_split('/[\s\-–—:,]+/', strtolower($title));
        foreach ($title_words as $w) {
            $w = trim($w);
            if (strlen($w) > 3 && !in_array($w, array('yang', 'untuk', 'dengan', 'dari', 'akan', 'pada', 'info', 'lengkap', 'panduan', 'terbaru', 'wisata', 'destinasi'))) {
                $this->protected_words[] = $w;
            }
        }

        // Tambahkan kata-kata umum yang TIDAK BOLEH di-spin
        $this->protected_words = array_merge($this->protected_words, array(
            'google', 'maps', 'waze', 'gojek', 'grab', 'instagram',
            'whatsapp', 'facebook', 'twitter', 'tiktok', 'youtube',
            'indonesia', 'jakarta', 'bandung', 'bali', 'lombok',
            'jogja', 'yogyakarta', 'surabaya', 'malang', 'semarang',
            'rupiah', 'wib', 'wita', 'wit',
        ));

        $this->protected_words = array_unique($this->protected_words);
    }

    /**
     * Load SAFE synonyms - hanya sinonim yang TIDAK merusak konteks
     * DIHAPUS: zona, cuti, bertransformasi, dan sinonim berbahaya lainnya
     */
    private function load_safe_synonyms() {
        $this->safe_synonyms = array(
            // === KATA KERJA AMAN ===
            'merupakan'    => array('adalah', 'ialah', 'yakni'),
            'memberikan'   => array('menyajikan', 'menawarkan', 'menghadirkan'),
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

            // === KATA SIFAT AMAN ===
            'indah'        => array('cantik', 'elok', 'memesona'),
            'menarik'      => array('menawan', 'memukau', 'memikat'),
            'terkenal'     => array('populer', 'ternama'),
            'populer'      => array('terkenal', 'diminati'),
            'unik'         => array('khas', 'istimewa'),
            'lengkap'      => array('komprehensif', 'menyeluruh'),
            'nyaman'       => array('tenteram', 'asri'),
            'lezat'        => array('nikmat', 'sedap', 'enak'),
            'segar'        => array('sejuk', 'menyegarkan'),
            'cocok'        => array('sesuai', 'pas', 'ideal'),
            'modern'       => array('kekinian', 'terkini'),

            // === KATA BENDA AMAN (tanpa sinonim berbahaya) ===
            'pengunjung'   => array('wisatawan', 'pelancong'),
            'wisatawan'    => array('pelancong', 'pengunjung'),
            'pemandangan'  => array('panorama', 'lanskap'),
            'keindahan'    => array('pesona', 'keelokkan'),
            'pengalaman'   => array('sensasi', 'kesan'),
            'perjalanan'   => array('trip', 'petualangan'),
            'informasi'    => array('info', 'keterangan'),
            'aktivitas'    => array('kegiatan'),
            'suasana'      => array('atmosfer', 'nuansa'),
            'makanan'      => array('hidangan', 'sajian', 'kuliner'),
            'pilihan'      => array('opsi', 'alternatif'),
            'daerah'       => array('wilayah', 'kawasan'),

            // === FRASA AMAN ===
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

            // === KATA WISATA AMAN ===
            'destinasi'    => array('tujuan wisata', 'tempat wisata'),
            'spot foto'    => array('titik foto', 'lokasi berfoto'),
            'tiket masuk'  => array('karcis masuk', 'biaya masuk'),
        );

        // DIHAPUS dari sinonim (berbahaya):
        // 'tempat' => 'zona' (merusak: "tempat wisata" → "zona wisata" aneh)
        // 'liburan' => 'cuti' (merusak: "liburan keluarga" → "cuti keluarga" salah konteks)
        // 'menjadi' => 'bertransformasi' (merusak: "menjadi pilihan" → "bertransformasi pilihan")
        // 'fasilitas' => 'infrastruktur' (merusak: "fasilitas toilet" → "infrastruktur toilet")
        // 'besar' => 'raksasa' (merusak: "besar sekali" → "raksasa sekali")
        // 'harga' => 'ongkos' (merusak: "harga tiket" → "ongkos tiket" kurang tepat)
    }

    /**
     * SAFE SPIN - hanya spin kata yang aman, proteksi nama dan brand
     */
    private function safe_spin($html) {
        // Split HTML into text and tags
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $spin_intensity = (int)get_option('tsa_spin_intensity', 30); // Default 30% (lebih rendah = lebih aman)

        foreach ($parts as $part) {
            // Skip HTML tags
            if (preg_match('/^<[^>]+>$/', $part)) {
                $result .= $part;
                continue;
            }

            // Skip jika terlalu pendek
            if (str_word_count($part) < 3) {
                $result .= $part;
                continue;
            }

            // Spin hanya kata-kata yang aman
            foreach ($this->safe_synonyms as $word => $alternatives) {
                // Probability check
                if (mt_rand(1, 100) > $spin_intensity) continue;

                // PROTEKSI: skip jika kata ada di protected list
                if ($this->is_protected_context($part, $word)) continue;

                $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
                if (preg_match($pattern, $part)) {
                    $replacement = $alternatives[array_rand($alternatives)];
                    // Hanya replace 1 kali per kata per bagian
                    $part = preg_replace($pattern, $replacement, $part, 1);
                }
            }

            $result .= $part;
        }

        return $result;
    }

    /**
     * Cek apakah kata dalam konteks yang dilindungi
     */
    private function is_protected_context($text, $word) {
        $text_lower = strtolower($text);
        $word_lower = strtolower($word);

        // Cek apakah kata ada di dekat protected word
        foreach ($this->protected_words as $pw) {
            if (stripos($text_lower, $pw) !== false) {
                // Jika protected word ada di text, cek proximity
                $pw_pos = stripos($text_lower, $pw);
                $word_pos = stripos($text_lower, $word_lower);

                if ($word_pos !== false) {
                    $distance = abs($pw_pos - $word_pos);
                    // Jika kata terlalu dekat dengan protected word (dalam 30 karakter), skip
                    if ($distance < 30) return true;
                }
            }
        }

        // Proteksi kata dalam konteks angka/harga
        if (preg_match('/\b' . preg_quote($word, '/') . '\b.*?(?:Rp|IDR|\d{3,})/i', $text)) return true;
        if (preg_match('/(?:Rp|IDR|\d{3,}).*?\b' . preg_quote($word, '/') . '\b/i', $text)) return true;

        return false;
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

        $html = preg_replace('/\s{2,}/', ' ', $html);
        $html = preg_replace('/\.\s*\./', '.', $html);

        return $html;
    }

    /**
     * Fix grammar
     */
    private function fix_grammar($html) {
        // Fix double words
        $html = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $html);

        // Fix spacing
        $html = preg_replace('/\s+([.,;:!?])/', '$1', $html);
        $html = preg_replace('/([.,;:!?])(?=[A-Za-z])/', '$1 ', $html);

        // Fix capitalization after period
        $html = preg_replace_callback('/\.\s+([a-z])/u', function($m) {
            return '. ' . mb_strtoupper($m[1]);
        }, $html);

        // Fix "Sekali. Id" → "Sekali.id"
        $html = preg_replace('/Sekali\.\s*[Ii]d/', 'Sekali.id', $html);
        $html = preg_replace('/sekali\.\s*[Ii]d/', 'sekali.id', $html);

        // Fix common double words
        $doubles = array('yang yang', 'dan dan', 'untuk untuk', 'dari dari', 'dengan dengan', 'ini ini', 'itu itu', 'di di', 'ke ke');
        foreach ($doubles as $d) {
            $parts = explode(' ', $d);
            $html = preg_replace('/\b' . preg_quote($parts[0], '/') . '\s+' . preg_quote($parts[1], '/') . '\b/i', $parts[0], $html);
        }

        return $html;
    }

    /**
     * Replace first person
     */
    private function replace_first_person($html) {
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';

        foreach ($parts as $part) {
            if (preg_match('/^<[^>]+>$/', $part)) {
                $result .= $part;
                continue;
            }

            $part = preg_replace('/\bsaya\b/i', $this->site_name, $part);
            $part = preg_replace('/\baku\b/i', $this->site_name, $part);
            $part = preg_replace('/\bkami\b/i', $this->site_name, $part);

            $result .= $part;
        }

        return $result;
    }

    /**
     * AI final polish
     */
    private function ai_final_polish($title, $html, &$log) {
        if (strlen($html) > 25000) {
            $log[] = '[Editor V5] Artikel terlalu panjang untuk AI polish, skip';
            return $html;
        }

        $prompt = "Kamu adalah editor bahasa Indonesia senior. Tugasmu HANYA mempoles tata bahasa dan membuat artikel lebih natural.

ARTIKEL:
{$html}

TUGAS (HANYA perbaiki, JANGAN ubah struktur atau kurangi konten):
1. Perbaiki kalimat yang terdengar kaku atau tidak natural
2. Pastikan setiap paragraf mengalir dengan baik
3. Pastikan TIDAK ADA kata \"saya\", \"aku\", atau \"kami\" - ganti dengan \"{$this->site_name}\"
4. JANGAN ubah nama tempat, brand, atau angka
5. PERTAHANKAN semua HTML tags
6. JANGAN hapus atau ubah heading
7. JANGAN kurangi jumlah kata

Output: Artikel yang sudah dipoles dalam format HTML yang SAMA.";

        $result = $this->call_ai($prompt);

        if (!empty($result)) {
            $original_words = str_word_count(strip_tags($html));
            $polished_words = str_word_count(strip_tags($result));

            if ($polished_words >= $original_words * 0.8 && $polished_words <= $original_words * 1.2) {
                $log[] = '[Editor V5] AI polish berhasil (' . $polished_words . ' kata)';
                return $result;
            }
        }

        $log[] = '[Editor V5] AI polish skipped';
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

        // Word count
        if ($word_count >= 1500) $scores['word_count'] = 100;
        elseif ($word_count >= 1000) $scores['word_count'] = 80;
        elseif ($word_count >= 700) $scores['word_count'] = 60;
        else $scores['word_count'] = 40;

        // Keyword density
        $title_words = array_filter(explode(' ', $title_lower), function($w) { return strlen($w) > 3; });
        $keyword_count = 0;
        foreach ($title_words as $kw) {
            $keyword_count += substr_count($text_lower, $kw);
        }
        $density = ($word_count > 0) ? ($keyword_count / $word_count) * 100 : 0;
        if ($density >= 1 && $density <= 3) $scores['keyword_density'] = 100;
        elseif ($density >= 0.5 && $density <= 4) $scores['keyword_density'] = 80;
        else $scores['keyword_density'] = 50;

        // Headings
        $h2_count = preg_match_all('/<h2/i', $html);
        if ($h2_count >= 4 && $h2_count <= 8) $scores['headings'] = 100;
        elseif ($h2_count >= 2) $scores['headings'] = 70;
        else $scores['headings'] = 40;

        // Formatting
        $bold_count = preg_match_all('/<strong/i', $html);
        if ($bold_count >= 5 && $bold_count <= 20) $scores['formatting'] = 100;
        elseif ($bold_count >= 3) $scores['formatting'] = 70;
        else $scores['formatting'] = 40;

        // Rich content
        $has_table = preg_match('/<table/i', $html);
        $has_list = preg_match('/<[uo]l/i', $html);
        if ($has_table && $has_list) $scores['rich_content'] = 100;
        elseif ($has_table || $has_list) $scores['rich_content'] = 70;
        else $scores['rich_content'] = 30;

        // Links
        $link_count = preg_match_all('/<a\s/i', $html);
        if ($link_count >= 3) $scores['links'] = 100;
        elseif ($link_count >= 1) $scores['links'] = 60;
        else $scores['links'] = 30;

        $scores['meta'] = 80;
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

        $avg_sentence_length = ($sentence_count > 0) ? $word_count / $sentence_count : 0;
        $paragraph_count = preg_match_all('/<p/i', $html);
        $avg_paragraph_length = ($paragraph_count > 0) ? $word_count / $paragraph_count : 0;

        $score = 100;
        if ($avg_sentence_length > 25) $score -= 20;
        elseif ($avg_sentence_length > 20) $score -= 10;
        if ($avg_paragraph_length > 150) $score -= 15;
        elseif ($avg_paragraph_length > 100) $score -= 5;
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
                array('role' => 'system', 'content' => 'Kamu adalah editor bahasa Indonesia senior.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.5, 'max_tokens' => 8000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
