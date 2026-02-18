<?php
/**
 * Project Hyperion - Agent #4: The Synthesizer V4
 * Cohesive Narrative Weaving + Aggressive Content Expansion
 *
 * Menerima output dari Council V4 (sudah berupa HTML lengkap)
 * dan memastikan artikel minimal 1000 kata melalui expansion loop.
 *
 * @version 4.0.0
 */

if (!defined('ABSPATH')) exit;

class TSA_Synthesizer_Agent {

    private $site_name = '';
    private $min_words = 1000;

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * MAIN: Synthesize dan pastikan minimal 1000 kata
     */
    public function synthesize($title, $council_output, $blueprint) {
        $log = array();
        $log[] = '[Synthesizer V4] Memulai penyatuan narasi untuk: "' . $title . '"';

        // Council V4 sudah menghasilkan full_html
        $full_html = '';

        if (is_array($council_output) && !empty($council_output['full_html'])) {
            $full_html = $council_output['full_html'];
            $log[] = '[Synthesizer V4] Menggunakan full_html dari Council';
        } elseif (is_array($council_output)) {
            $full_html = $this->merge_sections($council_output);
            $log[] = '[Synthesizer V4] Menggabungkan sections dari Council';
        }

        $full_html = $this->clean_html($full_html);
        $word_count = $this->count_words($full_html);
        $log[] = "[Synthesizer V4] Word count awal: {$word_count}";

        // ============================================================
        // EXPANSION LOOP: Pastikan minimal 1000 kata
        // ============================================================
        $expansion_round = 0;
        $max_rounds = 5;
        $short_name = $this->extract_short_name($title);

        while ($word_count < $this->min_words && $expansion_round < $max_rounds) {
            $expansion_round++;
            $needed = $this->min_words - $word_count;
            $log[] = "[Synthesizer V4] Expansion round {$expansion_round}: perlu +{$needed} kata";

            // Round 1: AI expansion
            if ($expansion_round === 1) {
                $ai_extra = $this->ai_expand($title, $needed);
                if (!empty($ai_extra) && $this->count_words($ai_extra) > 80) {
                    $full_html = $this->insert_before_end($full_html, $ai_extra);
                }
            }
            // Round 2: FAQ section
            elseif ($expansion_round === 2 && stripos($full_html, 'Pertanyaan yang Sering') === false) {
                $full_html = $this->insert_before_end($full_html, $this->generate_faq($short_name));
            }
            // Round 3: Wisata terdekat
            elseif ($expansion_round === 3 && stripos($full_html, 'Wisata Terdekat') === false) {
                $full_html = $this->insert_before_end($full_html, $this->generate_nearby($short_name));
            }
            // Round 4: Pengalaman pengunjung
            elseif ($expansion_round === 4 && stripos($full_html, 'Pengalaman') === false) {
                $full_html = $this->insert_before_end($full_html, $this->generate_experience($short_name));
            }
            // Round 5: Expand paragraf
            elseif ($expansion_round === 5) {
                $full_html = $this->expand_paragraphs($full_html, $short_name);
            }

            $word_count = $this->count_words($full_html);
            $log[] = "[Synthesizer V4] Setelah round {$expansion_round}: {$word_count} kata";
        }

        // Disclaimer
        if (stripos($full_html, 'Disclaimer') === false) {
            $full_html .= "\n\n<p><em><strong>Disclaimer:</strong> Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi. Artikel ini ditulis oleh tim {$this->site_name} berdasarkan riset dari berbagai sumber terpercaya.</em></p>\n";
        }

        $word_count = $this->count_words($full_html);
        $log[] = "[Synthesizer V4] ✓ Final: {$word_count} kata, {$expansion_round} expansion rounds";

        return array(
            'article_html'     => $full_html,
            'title'            => $council_output['title'] ?? $title,
            'meta'             => $council_output['meta'] ?? '',
            'word_count'       => $word_count,
            'expansion_rounds' => $expansion_round,
            'log'              => $log,
        );
    }

    private function merge_sections($data) {
        $html = '';
        if (!empty($data['introduction'])) $html .= $data['introduction'] . "\n\n";
        if (!empty($data['sections']) && is_array($data['sections'])) {
            foreach ($data['sections'] as $s) {
                if (is_array($s)) {
                    if (!empty($s['heading'])) $html .= "<h2>{$s['heading']}</h2>\n\n";
                    if (!empty($s['content'])) $html .= $s['content'] . "\n\n";
                } elseif (is_string($s)) {
                    $html .= $s . "\n\n";
                }
            }
        }
        if (!empty($data['conclusion'])) $html .= $data['conclusion'] . "\n\n";
        return $html;
    }

    private function ai_expand($title, $needed) {
        $prompt = "Tulis 2-3 section tambahan tentang \"{$title}\" untuk website {$this->site_name}. Setiap section harus punya H2 heading dan 2-3 paragraf panjang (4-5 kalimat per paragraf). Gunakan HTML (<h2>, <p>, <strong>, <em>, <ul>). JANGAN gunakan kata saya/aku. Minimal 300 kata. Topik bisa: pengalaman pengunjung, wisata terdekat, tips khusus, atau fakta menarik.";

        $result = $this->call_ai($prompt);
        if (!empty($result)) return $this->clean_ai_output($result);
        return '';
    }

    private function generate_faq($short_name) {
        $html = "<h2>Pertanyaan yang Sering Diajukan Tentang {$short_name}</h2>\n\n";

        $faqs = array(
            array("Berapa harga tiket masuk {$short_name}?", "Harga <strong>tiket masuk</strong> {$short_name} dapat bervariasi tergantung musim dan kebijakan pengelola. Umumnya harga tiket untuk dewasa dan anak-anak berbeda. Disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi untuk mendapatkan informasi harga <strong>terbaru</strong> sebelum berkunjung, karena harga dapat berubah sewaktu-waktu terutama pada musim liburan dan hari libur nasional."),
            array("Kapan waktu terbaik untuk berkunjung ke {$short_name}?", "Waktu <strong>terbaik</strong> untuk berkunjung adalah pada pagi hari antara pukul 08.00-10.00 atau sore hari menjelang senja ketika cuaca lebih sejuk dan pemandangan lebih indah. Hindari berkunjung pada hari libur nasional jika Anda ingin menghindari keramaian. Musim kemarau (April-Oktober) umumnya menjadi waktu yang ideal karena cuaca yang lebih bersahabat."),
            array("Apa saja fasilitas yang tersedia di {$short_name}?", "Umumnya tersedia <strong>fasilitas</strong> dasar seperti <strong>area parkir</strong> yang luas untuk kendaraan roda dua dan roda empat, toilet bersih, mushola untuk ibadah, dan warung makan. Beberapa area juga dilengkapi dengan gazebo untuk beristirahat, spot foto instagramable, dan area bermain anak."),
            array("Bagaimana cara menuju {$short_name}?", "<strong>Lokasi</strong> ini dapat dijangkau dengan kendaraan pribadi maupun transportasi umum. Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk panduan rute <strong>terbaik</strong>. Layanan ojek online seperti Gojek dan Grab juga tersedia untuk kemudahan akses."),
            array("Apakah {$short_name} cocok untuk liburan keluarga?", "<strong>Destinasi</strong> ini sangat cocok untuk liburan keluarga. Atmosfer yang nyaman, pemandangan yang indah, dan <strong>fasilitas</strong> yang memadai menjadikannya pilihan tepat untuk menghabiskan waktu berkualitas bersama orang-orang tercinta. Anak-anak juga bisa menikmati berbagai aktivitas yang tersedia."),
        );

        foreach ($faqs as $faq) {
            $html .= "<h3>{$faq[0]}</h3>\n<p>{$faq[1]}</p>\n\n";
        }
        return $html;
    }

    private function generate_nearby($short_name) {
        $html = "<h2>Destinasi Wisata Terdekat dari {$short_name}</h2>\n\n";
        $html .= "<p>Selain mengunjungi <strong>{$short_name}</strong>, Anda juga bisa menjelajahi beberapa destinasi wisata terdekat yang tidak kalah menarik. Menggabungkan kunjungan ke beberapa tempat wisata dalam satu perjalanan bisa menjadi cara yang efisien untuk memaksimalkan pengalaman liburan Anda dan mendapatkan lebih banyak kenangan indah.</p>\n\n";
        $html .= "<p>Daerah sekitar {$short_name} dikenal memiliki banyak potensi wisata yang beragam, mulai dari wisata alam yang menyegarkan, wisata budaya yang sarat akan nilai sejarah, hingga wisata kuliner yang memanjakan lidah. Dengan merencanakan <em>itinerary</em> yang baik, Anda bisa mengunjungi beberapa destinasi dalam satu hari dan mendapatkan pengalaman liburan yang lebih lengkap dan berkesan.</p>\n\n";
        $html .= "<p>{$this->site_name} merekomendasikan untuk mengalokasikan waktu setidaknya satu hari penuh agar bisa menikmati seluruh destinasi di sekitar area ini. Jangan lupa untuk mencatat daftar tempat yang ingin dikunjungi dan mempersiapkan segala kebutuhan perjalanan agar liburan berjalan lancar tanpa hambatan.</p>\n\n";
        return $html;
    }

    private function generate_experience($short_name) {
        $html = "<h2>Pengalaman dan Ulasan Pengunjung {$short_name}</h2>\n\n";
        $html .= "<p>Banyak pengunjung yang memberikan ulasan positif setelah berkunjung ke <strong>{$short_name}</strong>. Keindahan alam yang memukau, kebersihan lingkungan yang terjaga, dan keramahan pengelola serta masyarakat sekitar menjadi poin-poin yang sering mendapat apresiasi dari para wisatawan baik lokal maupun mancanegara.</p>\n\n";
        $html .= "<p>Beberapa pengunjung merekomendasikan untuk mengalokasikan waktu setidaknya 2-3 jam agar bisa menikmati seluruh area wisata dengan santai tanpa terburu-buru. Bagi pecinta fotografi, tempat ini menjadi surga tersendiri dengan berbagai sudut yang <em>instagramable</em> dan pemandangan yang memukau di setiap sisinya.</p>\n\n";
        $html .= "<blockquote><p><strong>Tips dari Pengunjung:</strong> Datanglah saat hari kerja untuk mendapatkan suasana yang lebih tenang dan leluasa dalam menjelajahi setiap sudut destinasi. Bawa juga bekal makanan ringan dan air minum yang cukup untuk menghemat pengeluaran selama berkunjung.</p></blockquote>\n\n";
        return $html;
    }

    private function expand_paragraphs($html, $short_name) {
        $extras = array(
            "<p>Dengan berbagai keunggulan yang ditawarkan, tidak mengherankan jika <strong>{$short_name}</strong> menjadi salah satu destinasi favorit yang banyak direkomendasikan oleh para <em>traveler</em> dan <em>blogger</em> wisata. Setiap sudut tempat ini menyimpan pesona tersendiri yang sayang untuk dilewatkan begitu saja.</p>\n\n",
            "<p>Bagi Anda yang sedang mencari inspirasi destinasi wisata untuk liburan berikutnya, <strong>{$short_name}</strong> layak masuk dalam daftar pertimbangan utama. Pengalaman berkunjung ke tempat ini dijamin akan meninggalkan kesan mendalam dan membuat Anda ingin kembali lagi di kesempatan berikutnya untuk menjelajahi lebih banyak sudut yang belum sempat dikunjungi.</p>\n\n",
            "<p>Penting untuk diingat bahwa setiap destinasi wisata memiliki keunikan dan daya tariknya masing-masing. Yang membuat <strong>{$short_name}</strong> istimewa adalah perpaduan antara keindahan alam, kekayaan budaya, dan keramahan masyarakat setempat yang menciptakan pengalaman wisata yang autentik, berkesan, dan tidak akan terlupakan.</p>\n\n",
        );
        return $this->insert_before_end($html, implode('', $extras));
    }

    private function insert_before_end($html, $new_content) {
        $patterns = array('/<h2[^>]*>\s*Kesimpulan/i', '/<h2[^>]*>\s*Penutup/i', '/<p><em><strong>Disclaimer/i');
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                return substr($html, 0, $pos) . $new_content . "\n\n" . substr($html, $pos);
            }
        }
        return $html . "\n\n" . $new_content;
    }

    private function clean_html($html) {
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    private function count_words($html) {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        return str_word_count(trim($text));
    }

    private function extract_short_name($title) {
        $title = preg_replace('/\b(panduan|lengkap|terbaru|info|wisata|destinasi|kuliner|hotel|review|rekomendasi|\d{4})\b/i', '', $title);
        return ucwords(trim(preg_replace('/\s+/', ' ', $title)));
    }

    private function clean_ai_output($text) {
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<![<\/])\*([^*]+)\*/', '<em>$1</em>', $text);
        if (strpos($text, '<p>') === false && strpos($text, '<h2') === false) {
            $paragraphs = preg_split('/\n{2,}/', $text);
            $html = '';
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (!empty($p) && strlen($p) > 10) {
                    $html .= (strpos($p, '<') !== 0 ? '<p>' . $p . '</p>' : $p) . "\n\n";
                }
            }
            $text = $html;
        }
        return trim($text);
    }

    private function call_ai($prompt) {
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result) && strlen($result) > 100) return $result;
        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) return $this->call_openai($prompt, $api_key);
        return '';
    }

    private function call_duckduckgo_ai($prompt) {
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10, 'headers' => array('x-vqd-accept' => '1', 'User-Agent' => 'Mozilla/5.0'), 'sslverify' => false,
        ));
        if (is_wp_error($token_response)) return '';
        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        $response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 90,
            'headers' => array('Content-Type' => 'application/json', 'x-vqd-4' => $vqd, 'User-Agent' => 'Mozilla/5.0'),
            'body' => wp_json_encode(array('model' => 'gpt-4o-mini', 'messages' => array(array('role' => 'user', 'content' => $prompt)))),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';

        $body = wp_remote_retrieve_body($response);
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
            'timeout' => 120,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array('model' => $model, 'messages' => array(
                array('role' => 'system', 'content' => 'Kamu adalah editor senior media wisata Indonesia. Tulis konten PANJANG dan INFORMATIF.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.7, 'max_tokens' => 8000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
