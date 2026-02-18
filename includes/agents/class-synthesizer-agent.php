<?php
/**
 * Project Hyperion - Agent #4: The Synthesizer
 * Cohesive Narrative Weaving
 *
 * Tugas: Menyatukan semua draf dari The Council menjadi satu artikel
 * yang kohesif, mengalir mulus, tanpa pengulangan.
 */

if (!defined('ABSPATH')) exit;

class TSA_Synthesizer_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Synthesize semua draf menjadi satu artikel kohesif
     */
    public function synthesize($title, $council_output, $blueprint) {
        $log = array();
        $log[] = '[Synthesizer] Memulai penyatuan narasi untuk: "' . $title . '"';

        $intro = $council_output['introduction'] ?? '';
        $sections = $council_output['sections'] ?? array();
        $conclusion = $council_output['conclusion'] ?? '';
        $seo_title = $council_output['title'] ?? $title;
        $meta = $council_output['meta'] ?? '';

        // Step 1: Assemble raw article
        $raw_article = $this->assemble_article($seo_title, $intro, $sections, $conclusion);
        $raw_word_count = str_word_count(strip_tags($raw_article));
        $log[] = '[Synthesizer] Raw article: ' . $raw_word_count . ' kata';

        // Step 2: Check word count dan expand jika perlu
        $min_words = $blueprint['target_words']['min'] ?? 1500;
        if ($raw_word_count < $min_words) {
            $log[] = '[Synthesizer] Artikel kurang dari ' . $min_words . ' kata, expanding...';
            $raw_article = $this->expand_article($title, $raw_article, $min_words, $log);
        }

        // Step 3: AI polish untuk koherensi
        $polished = $this->polish_coherence($title, $raw_article, $log);

        // Step 4: Remove duplicates dan redundancies
        $cleaned = $this->remove_redundancies($polished);

        // Step 5: Final word count check
        $final_word_count = str_word_count(strip_tags($cleaned));
        $log[] = '[Synthesizer] ✓ Artikel final: ' . $final_word_count . ' kata';

        return array(
            'article_html' => $cleaned,
            'title'        => $seo_title,
            'meta'         => $meta,
            'word_count'   => $final_word_count,
            'log'          => $log,
        );
    }

    /**
     * Assemble semua bagian menjadi satu artikel HTML
     */
    private function assemble_article($title, $intro, $sections, $conclusion) {
        $html = '';

        // Introduction (tanpa heading)
        if (!empty($intro)) {
            $html .= "<!-- introduction -->\n";
            $html .= $intro . "\n\n";
        }

        // Sections
        foreach ($sections as $section) {
            $heading = $section['heading'] ?? '';
            $content = $section['content'] ?? '';

            if (!empty($heading)) {
                $html .= "<h2>{$heading}</h2>\n\n";
            }
            if (!empty($content)) {
                $html .= $content . "\n\n";
            }
        }

        // Conclusion
        if (!empty($conclusion)) {
            $html .= "<h2>Kesimpulan</h2>\n\n";
            $html .= $conclusion . "\n\n";
        }

        return trim($html);
    }

    /**
     * Expand artikel jika kurang dari target kata
     */
    private function expand_article($title, $article, $min_words, &$log) {
        $current_words = str_word_count(strip_tags($article));
        $needed = $min_words - $current_words + 200; // Extra buffer

        $prompt = "Kamu adalah editor senior untuk website {$this->site_name}.

TOPIK: {$title}

Berikut adalah artikel yang sudah ditulis tetapi masih kurang panjang ({$current_words} kata, target minimal {$min_words} kata):

{$article}

TUGAS: Tambahkan konten BARU yang relevan untuk memperkaya artikel ini. Kamu perlu menambahkan sekitar {$needed} kata.

ATURAN:
1. JANGAN mengulang informasi yang sudah ada
2. Tambahkan detail baru yang memperkaya setiap section
3. Bisa menambahkan paragraf baru di section yang sudah ada
4. Bisa menambahkan sub-section baru jika relevan (misalnya: wisata sekitar, pengalaman pengunjung, dll)
5. Pertahankan format HTML yang sudah ada (<p>, <strong>, <em>, <table>, <ul>, <ol>)
6. Gunakan <strong> untuk keyword penting
7. JANGAN ubah struktur heading yang sudah ada
8. Gaya bahasa harus konsisten dengan artikel yang sudah ada

Output: Artikel lengkap yang sudah diperkaya (termasuk konten lama + konten baru). Format HTML.";

        $result = $this->call_ai($prompt);

        if (!empty($result) && str_word_count(strip_tags($result)) > $current_words) {
            $log[] = '[Synthesizer] Expanded to ' . str_word_count(strip_tags($result)) . ' kata';
            return $result;
        }

        // Fallback: tambahkan section FAQ
        $faq = $this->generate_faq($title);
        $article .= "\n\n<h2>Pertanyaan yang Sering Diajukan</h2>\n\n" . $faq;
        $log[] = '[Synthesizer] Added FAQ section as expansion';

        return $article;
    }

    /**
     * Generate FAQ section
     */
    private function generate_faq($title) {
        $prompt = "Buat 5 FAQ (Frequently Asked Questions) tentang \"{$title}\" dalam format HTML.

Format setiap FAQ:
<h3>[Pertanyaan]</h3>
<p>[Jawaban 2-3 kalimat yang informatif]</p>

Pertanyaan harus relevan dan sering dicari orang. Jawaban harus spesifik dan membantu.
JANGAN gunakan kata \"saya\" atau \"aku\".";

        $result = $this->call_ai($prompt);

        if (!empty($result)) return $result;

        // Fallback FAQ
        return "<h3>Berapa harga tiket masuk {$title}?</h3>\n<p>Harga tiket masuk dapat bervariasi tergantung musim dan kebijakan pengelola. Disarankan untuk menghubungi pihak pengelola atau mengecek informasi terbaru sebelum berkunjung.</p>\n\n<h3>Kapan waktu terbaik untuk berkunjung?</h3>\n<p>Waktu terbaik untuk berkunjung adalah pada pagi hari atau sore hari menjelang senja. Hindari hari libur nasional jika ingin menghindari keramaian.</p>\n\n<h3>Apa saja fasilitas yang tersedia?</h3>\n<p>Umumnya tersedia fasilitas dasar seperti area parkir, toilet, mushola, dan warung makan. Fasilitas dapat berbeda tergantung kebijakan pengelola.</p>\n\n<h3>Bagaimana cara menuju lokasi?</h3>\n<p>Lokasi dapat dijangkau dengan kendaraan pribadi maupun transportasi umum. Gunakan aplikasi navigasi seperti Google Maps untuk panduan rute terbaik.</p>\n\n<h3>Apakah cocok untuk liburan keluarga?</h3>\n<p>Destinasi ini sangat cocok untuk liburan keluarga. Suasana yang nyaman dan fasilitas yang memadai menjadikannya pilihan tepat untuk menghabiskan waktu bersama orang-orang tercinta.</p>";
    }

    /**
     * Polish koherensi artikel dengan AI
     */
    private function polish_coherence($title, $article, &$log) {
        // Jika artikel terlalu panjang untuk AI, skip polish
        if (strlen($article) > 20000) {
            $log[] = '[Synthesizer] Artikel terlalu panjang untuk AI polish, skip...';
            return $article;
        }

        $prompt = "Kamu adalah editor senior untuk website {$this->site_name}. Tugasmu adalah mempoles artikel agar lebih kohesif dan mengalir natural.

ARTIKEL:
{$article}

TUGAS EDITING:
1. Perbaiki transisi antar paragraf agar mengalir mulus
2. Hapus kalimat yang redundan atau berulang
3. Pastikan gaya bahasa konsisten dari awal sampai akhir
4. Pastikan TIDAK ADA kata \"saya\" atau \"aku\" - ganti dengan \"{$this->site_name}\" jika perlu
5. Pastikan setiap <strong> digunakan untuk keyword penting (bukan berlebihan)
6. Pastikan setiap <em> digunakan untuk istilah asing atau penekanan
7. PERTAHANKAN semua heading (H2, H3), tabel, dan list yang sudah ada
8. PERTAHANKAN format HTML
9. JANGAN kurangi jumlah kata secara signifikan
10. JANGAN tambahkan heading baru

Output: Artikel yang sudah dipoles dalam format HTML yang sama.";

        $result = $this->call_ai($prompt);

        if (!empty($result) && str_word_count(strip_tags($result)) >= str_word_count(strip_tags($article)) * 0.8) {
            $log[] = '[Synthesizer] AI polish berhasil';
            return $result;
        }

        $log[] = '[Synthesizer] AI polish gagal, menggunakan basic cleanup';
        return $this->basic_cleanup($article);
    }

    /**
     * Remove redundancies dari artikel
     */
    private function remove_redundancies($article) {
        // Remove duplicate paragraphs
        $parts = preg_split('/(<h[2-6][^>]*>.*?<\/h[2-6]>|<table[\s\S]*?<\/table>|<[uo]l[\s\S]*?<\/[uo]l>)/i', $article, -1, PREG_SPLIT_DELIM_CAPTURE);

        $seen_paragraphs = array();
        $cleaned = '';

        foreach ($parts as $part) {
            // Skip headings, tables, lists - always keep them
            if (preg_match('/^<(h[2-6]|table|[uo]l)/i', trim($part))) {
                $cleaned .= $part;
                continue;
            }

            // Check paragraphs for duplicates
            $paragraphs = preg_split('/<\/p>/', $part);
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (empty($p)) continue;

                $clean_text = strtolower(strip_tags($p));
                $clean_text = preg_replace('/\s+/', ' ', $clean_text);

                // Check similarity with seen paragraphs
                $is_duplicate = false;
                foreach ($seen_paragraphs as $seen) {
                    if (similar_text($clean_text, $seen, $percent) && $percent > 80) {
                        $is_duplicate = true;
                        break;
                    }
                }

                if (!$is_duplicate && strlen($clean_text) > 20) {
                    $seen_paragraphs[] = $clean_text;
                    if (strpos($p, '<p>') === false && strpos($p, '<p ') === false) {
                        $cleaned .= '<p>' . $p . "</p>\n\n";
                    } else {
                        $cleaned .= $p . "</p>\n\n";
                    }
                }
            }
        }

        return trim($cleaned);
    }

    /**
     * Basic cleanup tanpa AI
     */
    private function basic_cleanup($article) {
        // Replace "saya" dan "aku"
        $article = preg_replace('/\bsaya\b/i', $this->site_name, $article);
        $article = preg_replace('/\baku\b/i', $this->site_name, $article);

        // Clean excessive whitespace
        $article = preg_replace('/\n{3,}/', "\n\n", $article);

        // Ensure proper HTML structure
        $article = preg_replace('/<p>\s*<\/p>/', '', $article);

        return trim($article);
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
                array('role' => 'system', 'content' => 'Kamu adalah editor senior untuk media wisata Indonesia.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.6, 'max_tokens' => 8000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
