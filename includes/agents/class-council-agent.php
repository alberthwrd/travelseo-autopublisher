<?php
/**
 * Project Hyperion - Agent #3: The Council
 * Multi-Perspective Content Synthesis
 *
 * Tugas: Menulis draf konten dari berbagai sudut pandang menggunakan
 * 5 AI persona yang berbeda, masing-masing menulis section tertentu.
 */

if (!defined('ABSPATH')) exit;

class TSA_Council_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Generate konten dari 5 perspektif berbeda
     */
    public function write($title, $knowledge_graph, $blueprint) {
        $log = array();
        $log[] = '[Council] Memulai penulisan multi-perspektif untuk: "' . $title . '"';

        $ai_data = $knowledge_graph['ai_analysis'] ?? array();
        $sections = $blueprint['sections'] ?? array();
        $type = $blueprint['type'] ?? 'destinasi';

        // Siapkan context data dari knowledge graph
        $context = $this->prepare_context($knowledge_graph);

        // Step 1: Generate Introduction (selalu oleh Storyteller)
        $intro = $this->write_introduction($title, $type, $context, $ai_data, $log);

        // Step 2: Generate setiap section dengan persona yang sesuai
        $section_drafts = array();
        foreach ($sections as $i => $section) {
            $persona = $this->assign_persona($section, $i, count($sections));
            $draft = $this->write_section($title, $section, $context, $ai_data, $persona, $log);
            $section_drafts[] = array(
                'heading'  => $section['heading'],
                'format'   => $section['format'],
                'has_table' => $section['has_table'] ?? false,
                'has_list'  => $section['has_list'] ?? false,
                'content'  => $draft,
                'persona'  => $persona,
            );
        }

        // Step 3: Generate Conclusion
        $conclusion = $this->write_conclusion($title, $type, $context, $ai_data, $log);

        $log[] = '[Council] ✓ Penulisan selesai: intro + ' . count($section_drafts) . ' sections + conclusion';

        return array(
            'introduction'   => $intro,
            'sections'       => $section_drafts,
            'conclusion'     => $conclusion,
            'meta'           => $blueprint['meta'] ?? '',
            'title'          => $blueprint['title'] ?? $title,
            'log'            => $log,
        );
    }

    /**
     * Siapkan context text dari knowledge graph
     */
    private function prepare_context($kg) {
        $context = '';

        // AI Analysis data
        $ai_data = $kg['ai_analysis'] ?? array();
        foreach ($ai_data as $key => $val) {
            $context .= strtoupper($key) . ": " . $val . "\n";
        }

        // Key facts
        if (!empty($kg['key_facts'])) {
            $context .= "\nFAKTA PENTING:\n";
            foreach (array_slice($kg['key_facts'], 0, 10) as $fact) {
                $context .= "- " . $fact . "\n";
            }
        }

        // Content map (ringkasan dari sumber)
        if (!empty($kg['content_map'])) {
            $context .= "\nDATA DARI SUMBER:\n";
            foreach (array_slice($kg['content_map'], 0, 5) as $cm) {
                $context .= "--- " . ($cm['source'] ?? 'Unknown') . " ---\n";
                $text = $cm['text'] ?? '';
                if (strlen($text) > 2000) $text = substr($text, 0, 2000);
                $context .= $text . "\n\n";
            }
        }

        // Limit total context
        if (strlen($context) > 15000) {
            $context = substr($context, 0, 15000);
        }

        return $context;
    }

    /**
     * Assign persona berdasarkan section
     */
    private function assign_persona($section, $index, $total) {
        $heading_lower = strtolower($section['heading']);
        $format = $section['format'] ?? 'paragraph';

        // Storyteller: sejarah, mengenal, review
        if (preg_match('/(sejarah|mengenal|review|tentang|latar|cerita)/i', $heading_lower)) {
            return 'storyteller';
        }

        // Analis: harga, tiket, jam, data
        if (preg_match('/(harga|tiket|jam|operasional|biaya|tarif)/i', $heading_lower) || $format === 'table') {
            return 'analyst';
        }

        // Pemandu Praktis: lokasi, cara, tips, akses
        if (preg_match('/(lokasi|cara|tips|akses|menuju|rute|panduan|booking)/i', $heading_lower) || $format === 'list') {
            return 'practical_guide';
        }

        // Ahli Lokal: kuliner, budaya, aktivitas, daya tarik
        if (preg_match('/(kuliner|budaya|aktivitas|daya tarik|menarik|unik|lokal|tradisi)/i', $heading_lower)) {
            return 'local_expert';
        }

        // Ahli Lokal: fasilitas
        if (preg_match('/(fasilitas|layanan|akomodasi)/i', $heading_lower)) {
            return 'practical_guide';
        }

        // Default: berdasarkan posisi
        if ($index === 0) return 'storyteller';
        if ($index === $total - 1) return 'seo_strategist';
        return 'local_expert';
    }

    /**
     * Write Introduction (2-3 paragraf)
     */
    private function write_introduction($title, $type, $context, $ai_data, &$log) {
        $log[] = '[Council] Writing introduction (Storyteller persona)...';

        $ringkasan = $ai_data['RINGKASAN_TOPIK'] ?? '';
        $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';

        $prompt = "Kamu adalah seorang penulis konten senior untuk website {$this->site_name}. Kamu menulis dengan gaya jurnalistik yang memikat, informatif, dan natural seperti manusia.

TOPIK: {$title}
TIPE: {$type}

DATA RISET:
{$ringkasan}
{$daya_tarik}

KONTEKS TAMBAHAN:
{$context}

TUGAS: Tulis INTRODUCTION artikel (2-3 paragraf) dengan aturan berikut:

ATURAN KETAT:
1. Paragraf PERTAMA WAJIB menyebut \"{$this->site_name}\" secara natural, contoh: \"{$this->site_name} akan menyuguhkan informasi lengkap tentang {$title}\" atau \"{$this->site_name} kali ini mengajak Anda menjelajahi...\"
2. JANGAN gunakan kata \"saya\" atau \"aku\", ganti dengan \"{$this->site_name}\"
3. Paragraf pertama: Hook yang menarik + sebutkan brand + overview singkat topik
4. Paragraf kedua: Jelaskan apa yang akan dibahas dalam artikel ini, buat pembaca penasaran
5. Paragraf ketiga (opsional): Fakta menarik atau konteks tambahan
6. Gunakan <strong> untuk keyword utama (1-2 kali saja)
7. Gunakan <em> untuk istilah lokal atau penekanan (1 kali saja)
8. JANGAN gunakan heading (H1/H2/H3) di introduction
9. Tulis minimal 150 kata, maksimal 300 kata
10. Gaya bahasa: informatif, hangat, mengajak, BUKAN formal kaku
11. JANGAN mulai dengan \"Selamat datang\" atau \"Halo\"

PENTING: Output HANYA berisi paragraf HTML (tag <p>), tanpa heading, tanpa markdown. Langsung tulis kontennya.";

        $result = $this->call_ai($prompt);

        if (empty($result)) {
            // Fallback introduction
            $result = "<p><strong>{$this->site_name}</strong> akan menyuguhkan informasi lengkap tentang <strong>{$title}</strong>. Destinasi ini menawarkan pengalaman yang unik dan berbeda dari yang lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda bersama keluarga maupun teman.</p>\n\n<p>Dalam artikel ini, Anda akan menemukan informasi lengkap mulai dari lokasi, harga tiket, jam operasional, fasilitas yang tersedia, hingga tips berkunjung yang berguna. Semua informasi telah {$this->site_name} rangkum dari berbagai sumber terpercaya agar Anda bisa merencanakan kunjungan dengan lebih baik.</p>";
        }

        // Ensure HTML format
        $result = $this->ensure_html_paragraphs($result);

        $log[] = '[Council] ✓ Introduction selesai (' . str_word_count(strip_tags($result)) . ' kata)';
        return $result;
    }

    /**
     * Write individual section
     */
    private function write_section($title, $section, $context, $ai_data, $persona, &$log) {
        $heading = $section['heading'];
        $format = $section['format'] ?? 'paragraph';
        $instruction = $section['instruction'] ?? '';
        $paragraphs = $section['paragraphs'] ?? 3;
        $has_table = $section['has_table'] ?? false;
        $has_list = $section['has_list'] ?? false;

        $log[] = "[Council] Writing \"{$heading}\" ({$persona} persona)...";

        $persona_instruction = $this->get_persona_instruction($persona);

        // Ambil data relevan dari ai_data
        $relevant_data = $this->get_relevant_data($heading, $ai_data);

        $format_instruction = '';
        if ($has_table) {
            $format_instruction = "\nWAJIB buat TABEL HTML (<table>) untuk data harga/jam/perbandingan. Format tabel:\n<table>\n<thead><tr><th>Kolom 1</th><th>Kolom 2</th><th>Kolom 3</th></tr></thead>\n<tbody><tr><td>Data</td><td>Data</td><td>Data</td></tr></tbody>\n</table>\nTambahkan paragraf sebelum dan sesudah tabel.";
        }
        if ($has_list) {
            $format_instruction .= "\nWAJIB buat LIST HTML. Untuk tips gunakan <ol>, untuk fasilitas gunakan <ul>. Format:\n<ul>\n<li><strong>Nama Item</strong> - Deskripsi singkat item ini.</li>\n</ul>";
        }

        $prompt = "Kamu adalah penulis konten untuk website {$this->site_name}.

{$persona_instruction}

TOPIK ARTIKEL: {$title}
SECTION: {$heading}
INSTRUKSI KHUSUS: {$instruction}

DATA RELEVAN:
{$relevant_data}

KONTEKS:
{$context}
{$format_instruction}

ATURAN KETAT:
1. Tulis {$paragraphs}-" . ($paragraphs + 1) . " paragraf yang padat dan informatif
2. JANGAN gunakan kata \"saya\" atau \"aku\", ganti dengan \"{$this->site_name}\" jika perlu menyebut penulis
3. Gunakan <strong> untuk keyword penting, nama tempat, harga, dan info krusial (3-5 kali per section)
4. Gunakan <em> untuk istilah asing atau penekanan emosional (1-2 kali per section)
5. Setiap paragraf minimal 3 kalimat, maksimal 5 kalimat
6. Gaya bahasa: informatif, natural, mengalir seperti artikel media besar
7. JANGAN ulangi informasi yang sudah ada di introduction
8. Tulis minimal 200 kata, maksimal 500 kata untuk section ini
9. JANGAN sertakan heading (H2/H3), hanya isi konten paragraf/tabel/list
10. Jika ada data harga, WAJIB sebutkan sumber atau catatan bahwa harga dapat berubah

PENTING: Output HANYA berisi HTML (tag <p>, <strong>, <em>, <table>, <ul>, <ol>, <li>, <blockquote>). TANPA heading, TANPA markdown.";

        $result = $this->call_ai($prompt);

        if (empty($result)) {
            $result = $this->generate_fallback_section($heading, $format, $ai_data);
        }

        $result = $this->ensure_html_paragraphs($result);

        $word_count = str_word_count(strip_tags($result));
        $log[] = "[Council] ✓ \"{$heading}\" selesai ({$word_count} kata, {$persona})";

        return $result;
    }

    /**
     * Write Conclusion
     */
    private function write_conclusion($title, $type, $context, $ai_data, &$log) {
        $log[] = '[Council] Writing conclusion (SEO Strategist persona)...';

        $prompt = "Kamu adalah SEO Strategist senior untuk website {$this->site_name}.

TOPIK: {$title}

TUGAS: Tulis KESIMPULAN artikel (2-3 paragraf) dengan aturan:

1. Paragraf pertama: Rangkum poin-poin utama artikel secara singkat
2. Paragraf kedua: Berikan rekomendasi final dan ajakan untuk berkunjung
3. Paragraf ketiga: Sebutkan \"{$this->site_name}\" dan ajak pembaca membaca artikel terkait lainnya
4. JANGAN gunakan kata \"saya\" atau \"aku\"
5. Gunakan <strong> untuk keyword utama (1-2 kali)
6. Akhiri dengan kalimat yang memotivasi pembaca
7. Tulis minimal 100 kata, maksimal 200 kata
8. JANGAN gunakan heading, hanya paragraf HTML

PENTING: Output HANYA berisi tag <p> dan formatting inline. TANPA heading, TANPA markdown.";

        $result = $this->call_ai($prompt);

        if (empty($result)) {
            $result = "<p>Demikian informasi lengkap tentang <strong>{$title}</strong> yang telah {$this->site_name} rangkum untuk Anda. Dengan berbagai daya tarik dan fasilitas yang tersedia, destinasi ini layak masuk dalam daftar kunjungan Anda berikutnya.</p>\n\n<p>Jangan lupa untuk mempersiapkan segala kebutuhan sebelum berkunjung agar pengalaman liburan Anda semakin menyenangkan. Semoga informasi dari {$this->site_name} ini bermanfaat dan membantu Anda merencanakan perjalanan yang tak terlupakan.</p>";
        }

        $result = $this->ensure_html_paragraphs($result);
        $log[] = '[Council] ✓ Conclusion selesai';

        return $result;
    }

    /**
     * Get persona instruction
     */
    private function get_persona_instruction($persona) {
        $instructions = array(
            'storyteller' => "PERSONA: Storyteller - Kamu menulis dengan gaya naratif yang memikat. Gunakan deskripsi yang vivid, ceritakan pengalaman, dan buat pembaca merasa seolah-olah mereka sedang berada di lokasi tersebut. Fokus pada atmosfer, sejarah, dan keunikan.",

            'analyst' => "PERSONA: Analis Data - Kamu menulis dengan fokus pada fakta, angka, dan data yang akurat. Sajikan informasi dalam format yang mudah dipahami. Gunakan tabel untuk data numerik. Selalu sertakan sumber atau catatan untuk data yang bisa berubah.",

            'practical_guide' => "PERSONA: Pemandu Praktis - Kamu menulis panduan yang actionable dan mudah diikuti. Fokus pada langkah-langkah praktis, tips yang bisa langsung diterapkan, dan informasi yang membantu pembaca merencanakan kunjungan mereka.",

            'local_expert' => "PERSONA: Ahli Lokal - Kamu menulis dengan pengetahuan mendalam tentang budaya dan tradisi lokal. Bagikan insight yang tidak ditemukan di tempat lain, rekomendasi kuliner khas, dan pengalaman autentik yang hanya diketahui oleh penduduk lokal.",

            'seo_strategist' => "PERSONA: SEO Strategist - Kamu menulis dengan memperhatikan optimasi mesin pencari. Gunakan keyword secara natural, buat konten yang menjawab pertanyaan pembaca, dan struktur yang memudahkan Google memahami konten.",
        );

        return $instructions[$persona] ?? $instructions['storyteller'];
    }

    /**
     * Get relevant data dari ai_analysis berdasarkan heading
     */
    private function get_relevant_data($heading, $ai_data) {
        $heading_lower = strtolower($heading);
        $relevant = '';

        $mapping = array(
            'sejarah|mengenal|tentang|review' => array('RINGKASAN_TOPIK', 'SEJARAH', 'FAKTA_UNIK'),
            'lokasi|cara|menuju|akses|rute' => array('LOKASI_LENGKAP'),
            'harga|tiket|jam|operasional|biaya' => array('HARGA_TIKET', 'JAM_OPERASIONAL'),
            'fasilitas|layanan' => array('FASILITAS'),
            'aktivitas|daya tarik|menarik' => array('AKTIVITAS', 'DAYA_TARIK'),
            'kuliner|makanan|restoran' => array('KULINER_TERDEKAT'),
            'tips|rekomendasi|saran' => array('TIPS'),
        );

        foreach ($mapping as $pattern => $keys) {
            if (preg_match('/(' . $pattern . ')/i', $heading_lower)) {
                foreach ($keys as $key) {
                    if (!empty($ai_data[$key])) {
                        $relevant .= "{$key}: {$ai_data[$key]}\n";
                    }
                }
                break;
            }
        }

        // Selalu tambahkan ringkasan
        if (empty($relevant) && !empty($ai_data['RINGKASAN_TOPIK'])) {
            $relevant = "RINGKASAN: " . $ai_data['RINGKASAN_TOPIK'];
        }

        return $relevant;
    }

    /**
     * Generate fallback section content
     */
    private function generate_fallback_section($heading, $format, $ai_data) {
        $heading_lower = strtolower($heading);

        if (strpos($format, 'table') !== false || strpos($heading_lower, 'harga') !== false) {
            $harga = $ai_data['HARGA_TIKET'] ?? 'Hubungi pengelola';
            $jam = $ai_data['JAM_OPERASIONAL'] ?? 'Buka setiap hari';
            return "<p>Berikut informasi harga tiket masuk dan jam operasional yang perlu Anda ketahui sebelum berkunjung.</p>\n\n<table>\n<thead><tr><th>Kategori</th><th>Harga</th><th>Keterangan</th></tr></thead>\n<tbody>\n<tr><td><strong>Dewasa</strong></td><td>{$harga}</td><td>Per orang</td></tr>\n<tr><td><strong>Anak-anak</strong></td><td>{$harga}</td><td>Per orang</td></tr>\n</tbody>\n</table>\n\n<p><em>Catatan: Harga tiket dapat berubah sewaktu-waktu. Disarankan untuk menghubungi pihak pengelola atau mengecek informasi terbaru sebelum berkunjung.</em></p>\n\n<p><strong>Jam Operasional:</strong> {$jam}. Waktu terbaik untuk berkunjung adalah pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih nyaman.</p>";
        }

        if (strpos($format, 'list') !== false || strpos($heading_lower, 'tips') !== false) {
            $tips = $ai_data['TIPS'] ?? 'Datang pagi hari, bawa kamera, gunakan sunblock, bawa bekal air minum, pakai alas kaki nyaman, jaga kebersihan';
            $tips_arr = array_map('trim', explode(',', $tips));
            $list_html = "<ol>\n";
            foreach ($tips_arr as $tip) {
                $list_html .= "<li><strong>" . ucfirst($tip) . "</strong> - Hal ini akan membuat pengalaman berkunjung Anda semakin menyenangkan dan berkesan.</li>\n";
            }
            $list_html .= "</ol>";
            return "<p>Agar kunjungan Anda semakin menyenangkan, berikut beberapa tips yang bisa diterapkan.</p>\n\n{$list_html}";
        }

        if (strpos($heading_lower, 'fasilitas') !== false) {
            $fasilitas = $ai_data['FASILITAS'] ?? 'Area parkir, toilet, mushola, warung makan, gazebo, spot foto';
            $fas_arr = array_map('trim', explode(',', $fasilitas));
            $list_html = "<ul>\n";
            foreach ($fas_arr as $fas) {
                $list_html .= "<li><strong>" . ucfirst($fas) . "</strong> - Tersedia untuk kenyamanan pengunjung.</li>\n";
            }
            $list_html .= "</ul>";
            return "<p>Destinasi ini telah dilengkapi dengan berbagai fasilitas untuk menunjang kenyamanan pengunjung selama berada di lokasi.</p>\n\n{$list_html}";
        }

        // Default paragraph
        return "<p>Informasi tentang bagian ini sedang dalam proses pengumpulan data. Silakan kunjungi sumber resmi untuk informasi terkini.</p>";
    }

    /**
     * Ensure content is in HTML paragraph format
     */
    private function ensure_html_paragraphs($content) {
        $content = trim($content);

        // Remove markdown headers
        $content = preg_replace('/^#{1,6}\s+.*$/m', '', $content);

        // Remove markdown bold/italic and convert to HTML
        $content = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $content);
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);

        // If no HTML tags, wrap in paragraphs
        if (strpos($content, '<p>') === false && strpos($content, '<table') === false && strpos($content, '<ul') === false && strpos($content, '<ol') === false) {
            $paragraphs = preg_split('/\n{2,}/', $content);
            $html = '';
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (!empty($p)) {
                    $html .= '<p>' . $p . "</p>\n\n";
                }
            }
            $content = $html;
        }

        return trim($content);
    }

    /**
     * Call AI (same pattern as other agents)
     */
    private function call_ai($prompt) {
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result)) return $result;

        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) {
            return $this->call_openai($prompt, $api_key);
        }
        return '';
    }

    private function call_duckduckgo_ai($prompt) {
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10,
            'headers' => array('x-vqd-accept' => '1', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'sslverify' => false,
        ));
        if (is_wp_error($token_response)) return '';
        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        $chat_response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 45,
            'headers' => array('Content-Type' => 'application/json', 'x-vqd-4' => $vqd, 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
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
            'timeout' => 60,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array('model' => $model, 'messages' => array(
                array('role' => 'system', 'content' => 'Kamu adalah penulis konten profesional untuk media wisata Indonesia. Tulis dalam bahasa Indonesia yang natural dan informatif.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.75, 'max_tokens' => 4000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
