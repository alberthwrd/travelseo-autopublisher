<?php
/**
 * Project Hyperion - Agent #2: The Architect
 * Narrative Design & Dynamic Outline
 *
 * Tugas: Merancang blueprint artikel yang dinamis dan unik,
 * menentukan struktur heading, paragraf, dan instruksi formatting.
 */

if (!defined('ABSPATH')) exit;

class TSA_Architect_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Design article blueprint dari knowledge graph
     */
    public function design($title, $knowledge_graph) {
        $log = array();
        $log[] = '[Architect] Merancang blueprint untuk: "' . $title . '"';

        $type = $knowledge_graph['type'] ?? 'destinasi';
        $ai_data = $knowledge_graph['ai_analysis'] ?? array();

        // Step 1: Generate blueprint via AI
        $blueprint = $this->generate_ai_blueprint($title, $type, $knowledge_graph, $log);

        // Step 2: Jika AI gagal, gunakan template
        if (empty($blueprint) || count($blueprint['sections'] ?? array()) < 3) {
            $log[] = '[Architect] AI blueprint kurang lengkap, menggunakan enhanced template...';
            $blueprint = $this->generate_template_blueprint($title, $type, $knowledge_graph);
        }

        // Step 3: Validate & enrich blueprint
        $blueprint = $this->validate_blueprint($blueprint, $title, $type, $knowledge_graph);
        $log[] = '[Architect] ✓ Blueprint selesai: ' . count($blueprint['sections']) . ' sections';

        return array(
            'blueprint' => $blueprint,
            'log'       => $log,
        );
    }

    /**
     * Generate blueprint via AI
     */
    private function generate_ai_blueprint($title, $type, $kg, &$log) {
        $ai_data = $kg['ai_analysis'] ?? array();
        $facts_text = '';
        foreach ($ai_data as $key => $val) {
            $facts_text .= "{$key}: {$val}\n";
        }

        $prompt = "Kamu adalah seorang Content Architect senior yang merancang struktur artikel SEO untuk media besar Indonesia seperti Traveloka, Narasi.tv, dan Kompas.

TOPIK: {$title}
TIPE: {$type}
BRAND: {$this->site_name}

DATA RISET:
{$facts_text}

TUGAS: Rancang BLUEPRINT artikel yang akan mendominasi Google Page One.

ATURAN PENTING:
1. Judul H1 harus singkat, padat, SEO-friendly, TANPA simbol : - & dll, MAKSIMAL 8 kata
2. Introduction WAJIB 2-3 paragraf, paragraf pertama HARUS menyebut \"{$this->site_name}\" contoh: \"{$this->site_name} akan menyuguhkan informasi lengkap tentang...\"
3. Gunakan MAKSIMAL 5-7 H2 section (tidak terlalu banyak, fokus konten padat)
4. H3 hanya jika benar-benar diperlukan (untuk sub-list dalam H2)
5. Setiap H2 WAJIB punya instruksi: berapa paragraf, apakah perlu tabel/list/bold
6. WAJIB ada section dengan tabel (harga/jam operasional)
7. WAJIB ada section tips berkunjung
8. WAJIB ada kesimpulan dengan CTA
9. Target total: 1500-3000 kata

FORMAT OUTPUT (gunakan format ini PERSIS):
TITLE|||[Judul H1 tanpa simbol, max 8 kata]
META|||[Meta description 150-160 karakter]
SECTION|||[H2 text]|||[paragraphs:2-4]|||[format:paragraph/table/list/mixed]|||[instruksi spesifik untuk writer]
SECTION|||[H2 text]|||[paragraphs:2-4]|||[format:paragraph/table/list/mixed]|||[instruksi spesifik untuk writer]
...
CLOSING|||[instruksi untuk kesimpulan]

Buat minimal 5 SECTION dan maksimal 7 SECTION.";

        $response = $this->call_ai($prompt);
        if (empty($response)) return null;

        return $this->parse_ai_blueprint($response, $title);
    }

    /**
     * Parse AI blueprint response
     */
    private function parse_ai_blueprint($response, $original_title) {
        $blueprint = array(
            'title'    => '',
            'meta'     => '',
            'sections' => array(),
            'closing'  => '',
        );

        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'TITLE|||') === 0) {
                $parts = explode('|||', $line);
                $title = trim($parts[1] ?? '');
                // Clean title: remove symbols
                $title = preg_replace('/[:\-&|–—]+/', ' ', $title);
                $title = preg_replace('/\s+/', ' ', trim($title));
                // Limit to 8 words
                $words = explode(' ', $title);
                if (count($words) > 8) {
                    $title = implode(' ', array_slice($words, 0, 8));
                }
                $blueprint['title'] = $title;
            }
            elseif (strpos($line, 'META|||') === 0) {
                $parts = explode('|||', $line);
                $blueprint['meta'] = trim($parts[1] ?? '');
            }
            elseif (strpos($line, 'SECTION|||') === 0) {
                $parts = explode('|||', $line);
                $blueprint['sections'][] = array(
                    'heading'     => trim($parts[1] ?? ''),
                    'paragraphs'  => (int)preg_replace('/[^0-9]/', '', $parts[2] ?? '3'),
                    'format'      => trim($parts[3] ?? 'paragraph'),
                    'instruction' => trim($parts[4] ?? ''),
                    'bold_keywords' => array(),
                    'has_table'   => (strpos(strtolower($parts[3] ?? ''), 'table') !== false),
                    'has_list'    => (strpos(strtolower($parts[3] ?? ''), 'list') !== false),
                );
            }
            elseif (strpos($line, 'CLOSING|||') === 0) {
                $parts = explode('|||', $line);
                $blueprint['closing'] = trim($parts[1] ?? '');
            }
        }

        // Fallback title
        if (empty($blueprint['title'])) {
            $blueprint['title'] = $this->generate_seo_title($original_title);
        }

        return $blueprint;
    }

    /**
     * Generate template blueprint sebagai fallback
     */
    private function generate_template_blueprint($title, $type, $kg) {
        $seo_title = $this->generate_seo_title($title);
        $ai_data = $kg['ai_analysis'] ?? array();

        $blueprint = array(
            'title'    => $seo_title,
            'meta'     => "Panduan lengkap {$title}. Info lokasi, harga tiket, jam buka, fasilitas, dan tips berkunjung terbaru.",
            'sections' => array(),
            'closing'  => 'Tulis kesimpulan yang mengajak pembaca untuk berkunjung, sebutkan brand ' . $this->site_name,
        );

        // Template berdasarkan tipe konten
        switch ($type) {
            case 'destinasi':
                $blueprint['sections'] = array(
                    array(
                        'heading'     => 'Mengenal ' . $this->extract_short_name($title) . ' Lebih Dekat',
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis deskripsi mendalam tentang destinasi ini. Ceritakan sejarah singkat, daya tarik utama, dan mengapa tempat ini layak dikunjungi. Gunakan bold untuk nama tempat dan kata kunci penting. Gunakan italic untuk istilah lokal.',
                        'bold_keywords' => array($title),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Lokasi dan Cara Menuju ' . $this->extract_short_name($title),
                        'paragraphs'  => 2,
                        'format'      => 'mixed',
                        'instruction' => 'Jelaskan lokasi lengkap dengan alamat. Berikan panduan cara menuju dengan kendaraan pribadi dan transportasi umum. Bold untuk alamat dan nama jalan. Tambahkan tips navigasi.',
                        'bold_keywords' => array('Lokasi', 'Alamat', 'Google Maps'),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Harga Tiket dan Jam Operasional',
                        'paragraphs'  => 2,
                        'format'      => 'table',
                        'instruction' => 'Buat TABEL HTML untuk harga tiket (kolom: Kategori, Weekday, Weekend). Tambahkan paragraf tentang jam operasional. Bold untuk harga dan jam. Tambahkan catatan bahwa harga dapat berubah.',
                        'bold_keywords' => array(),
                        'has_table'   => true,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Daya Tarik dan Aktivitas Menarik',
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis tentang daya tarik utama dan aktivitas yang bisa dilakukan. Setiap aktivitas dijelaskan dalam 2-3 kalimat. Bold untuk nama aktivitas. Buat konten yang membuat pembaca ingin berkunjung.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Fasilitas yang Tersedia',
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Buat daftar fasilitas menggunakan unordered list HTML. Setiap item list diberi bold untuk nama fasilitas diikuti deskripsi singkat. Tambahkan paragraf pembuka sebelum list.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                    array(
                        'heading'     => 'Tips Berkunjung agar Lebih Menyenangkan',
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Tulis 5-7 tips praktis menggunakan ordered list HTML. Setiap tips harus spesifik dan actionable. Bold untuk kata kunci tips. Tambahkan paragraf pembuka.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                );
                break;

            case 'kuliner':
                $blueprint['sections'] = array(
                    array(
                        'heading'     => 'Mengenal ' . $this->extract_short_name($title),
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis tentang kuliner ini: sejarah, bahan utama, cita rasa khas, dan mengapa wajib dicoba. Bold untuk nama makanan dan bahan utama.',
                        'bold_keywords' => array($title),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Rekomendasi Tempat Terbaik',
                        'paragraphs'  => 3,
                        'format'      => 'mixed',
                        'instruction' => 'Rekomendasikan 3-5 tempat terbaik untuk menikmati kuliner ini. Setiap tempat: nama (bold), alamat, range harga, dan review singkat.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Daftar Harga Menu Populer',
                        'paragraphs'  => 2,
                        'format'      => 'table',
                        'instruction' => 'Buat TABEL HTML daftar menu dan harga (kolom: Menu, Harga, Keterangan). Bold untuk nama menu.',
                        'bold_keywords' => array(),
                        'has_table'   => true,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Jam Buka dan Lokasi',
                        'paragraphs'  => 2,
                        'format'      => 'paragraph',
                        'instruction' => 'Informasi jam buka, hari operasional, dan cara menuju lokasi. Bold untuk jam dan alamat.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Tips Menikmati ' . $this->extract_short_name($title),
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Tulis 5-7 tips menikmati kuliner ini. Ordered list. Bold untuk kata kunci tips.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                );
                break;

            case 'hotel':
                $blueprint['sections'] = array(
                    array(
                        'heading'     => 'Review Lengkap ' . $this->extract_short_name($title),
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis review mendalam: lokasi strategis, konsep, suasana, dan keunggulan utama. Bold untuk nama hotel dan fitur unggulan.',
                        'bold_keywords' => array($title),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Tipe Kamar dan Harga',
                        'paragraphs'  => 2,
                        'format'      => 'table',
                        'instruction' => 'Buat TABEL HTML tipe kamar dan harga (kolom: Tipe Kamar, Harga/Malam, Fasilitas). Bold untuk tipe kamar.',
                        'bold_keywords' => array(),
                        'has_table'   => true,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Fasilitas dan Layanan',
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Daftar fasilitas hotel menggunakan unordered list. Bold untuk nama fasilitas.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                    array(
                        'heading'     => 'Lokasi dan Akses',
                        'paragraphs'  => 2,
                        'format'      => 'paragraph',
                        'instruction' => 'Jelaskan lokasi, jarak ke tempat wisata terdekat, dan akses transportasi. Bold untuk nama tempat.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Tips Booking dan Menginap',
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Tulis 5-7 tips booking dan menginap. Ordered list. Bold untuk kata kunci.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                );
                break;

            default:
                $blueprint['sections'] = array(
                    array(
                        'heading'     => 'Mengenal ' . $this->extract_short_name($title),
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis deskripsi mendalam. Bold untuk kata kunci utama.',
                        'bold_keywords' => array($title),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Informasi Praktis',
                        'paragraphs'  => 2,
                        'format'      => 'table',
                        'instruction' => 'Buat tabel informasi penting. Bold untuk data kunci.',
                        'bold_keywords' => array(),
                        'has_table'   => true,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Hal Menarik yang Perlu Diketahui',
                        'paragraphs'  => 3,
                        'format'      => 'paragraph',
                        'instruction' => 'Tulis konten informatif dan menarik. Bold untuk poin penting.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => false,
                    ),
                    array(
                        'heading'     => 'Tips dan Rekomendasi',
                        'paragraphs'  => 2,
                        'format'      => 'list',
                        'instruction' => 'Tulis tips praktis menggunakan list. Bold untuk kata kunci.',
                        'bold_keywords' => array(),
                        'has_table'   => false,
                        'has_list'    => true,
                    ),
                );
        }

        return $blueprint;
    }

    /**
     * Validate dan enrich blueprint
     */
    private function validate_blueprint($blueprint, $title, $type, $kg) {
        // Pastikan title ada dan clean
        if (empty($blueprint['title'])) {
            $blueprint['title'] = $this->generate_seo_title($title);
        }

        // Clean title dari simbol
        $blueprint['title'] = preg_replace('/[:\-&|–—"\']+/', ' ', $blueprint['title']);
        $blueprint['title'] = preg_replace('/\s+/', ' ', trim($blueprint['title']));
        $words = explode(' ', $blueprint['title']);
        if (count($words) > 8) {
            $blueprint['title'] = implode(' ', array_slice($words, 0, 8));
        }
        $blueprint['title'] = ucwords(strtolower($blueprint['title']));

        // Pastikan meta description ada
        if (empty($blueprint['meta'])) {
            $blueprint['meta'] = "Panduan lengkap {$title}. Info lokasi, harga tiket, jam buka, fasilitas, dan tips berkunjung terbaru.";
        }

        // Pastikan minimal 5 sections
        if (count($blueprint['sections']) < 5) {
            $blueprint['sections'][] = array(
                'heading'     => 'Tips Berkunjung',
                'paragraphs'  => 2,
                'format'      => 'list',
                'instruction' => 'Tulis tips praktis berkunjung.',
                'bold_keywords' => array(),
                'has_table'   => false,
                'has_list'    => true,
            );
        }

        // Pastikan ada section dengan tabel
        $has_table = false;
        foreach ($blueprint['sections'] as $s) {
            if ($s['has_table']) { $has_table = true; break; }
        }
        if (!$has_table) {
            // Tambahkan section harga
            array_splice($blueprint['sections'], 2, 0, array(array(
                'heading'     => 'Informasi Harga dan Jam Operasional',
                'paragraphs'  => 2,
                'format'      => 'table',
                'instruction' => 'Buat tabel harga tiket dan informasi jam operasional.',
                'bold_keywords' => array(),
                'has_table'   => true,
                'has_list'    => false,
            )));
        }

        // Tambahkan metadata ke blueprint
        $blueprint['type'] = $type;
        $blueprint['site_name'] = $this->site_name;
        $blueprint['target_words'] = array('min' => 1500, 'max' => 3000);
        $blueprint['formatting_rules'] = array(
            'bold'   => 'Gunakan <strong> untuk keyword utama, nama tempat, harga, dan info penting',
            'italic' => 'Gunakan <em> untuk istilah asing, nama lokal, dan penekanan emosional',
            'table'  => 'Gunakan <table> untuk data harga, jam operasional, dan perbandingan',
            'list'   => 'Gunakan <ul>/<ol> untuk tips, fasilitas, dan langkah-langkah',
            'blockquote' => 'Gunakan <blockquote> untuk kutipan atau catatan penting',
        );

        return $blueprint;
    }

    /**
     * Generate SEO-friendly title (max 8 kata, tanpa simbol)
     */
    private function generate_seo_title($title) {
        $clean = preg_replace('/[:\-&|–—"\']+/', ' ', $title);
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $words = explode(' ', $clean);

        if (count($words) <= 8) {
            return ucwords(strtolower($clean));
        }

        // Ambil 8 kata pertama
        return ucwords(strtolower(implode(' ', array_slice($words, 0, 8))));
    }

    /**
     * Extract nama pendek dari judul
     */
    private function extract_short_name($title) {
        // Remove common prefixes
        $clean = preg_replace('/^(wisata|destinasi|tempat|objek|pantai|gunung|danau|hotel|restoran|cafe|kuliner)\s+/i', '', $title);
        $words = explode(' ', trim($clean));
        return implode(' ', array_slice($words, 0, 4));
    }

    /**
     * Call AI (sama dengan Oracle)
     */
    private function call_ai($prompt) {
        // Prioritas 1: DuckDuckGo AI (free)
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result)) return $result;

        // Prioritas 2: OpenAI API
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
            'timeout' => 30,
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
                array('role' => 'system', 'content' => 'Kamu adalah Content Architect senior untuk media besar Indonesia.'),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.7, 'max_tokens' => 3000)),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
