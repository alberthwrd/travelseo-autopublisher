<?php
/**
 * Project Hyperion - Agent #3: The Council V5
 * AI-First Content Generator - ZERO Template
 *
 * FILOSOFI: Setiap paragraf HARUS di-generate oleh AI secara unik.
 * Tidak ada template copy-paste. Setiap artikel BERBEDA.
 *
 * STRATEGI:
 * 1. Panggil AI per-section (bukan 1 panggilan besar) agar output lebih panjang
 * 2. Setiap prompt menyertakan data research spesifik untuk section tersebut
 * 3. AI dipaksa menulis 150-400 kata per section
 * 4. Fallback: panggil AI dengan prompt yang lebih pendek dan fokus
 * 5. Last resort: generate dari data research mentah (bukan template)
 *
 * @version 5.0.0
 */

if (!defined('ABSPATH')) exit;

class TSA_Council_Agent {

    private $site_name = '';
    private $min_words = 1000;
    private $max_words = 3000;

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * MAIN: Generate artikel lengkap - AI per-section
     */
    public function write($title, $knowledge_graph, $blueprint) {
        $log = array();
        $log[] = '[Council V5] Memulai penulisan AI-First untuk: "' . $title . '"';

        $ai_data = $knowledge_graph['ai_analysis'] ?? array();
        $sections = $blueprint['sections'] ?? array();
        $type = $blueprint['type'] ?? $knowledge_graph['type'] ?? 'destinasi';
        $seo_title = $blueprint['title'] ?? $title;
        $meta = $blueprint['meta'] ?? '';

        // Parse judul menjadi nama pendek yang natural
        $short_name = $this->parse_topic_name($title);
        $log[] = "[Council V5] Topik terdeteksi: \"{$short_name}\" (tipe: {$type})";

        // Siapkan context data dari knowledge graph
        $context = $this->prepare_context($knowledge_graph);
        $research_text = $this->prepare_research_text($knowledge_graph);

        // ============================================================
        // GENERATE ARTIKEL: AI per-section
        // ============================================================
        $article_html = '';

        // 1. INTRODUCTION (2-3 paragraf)
        $intro = $this->generate_introduction($title, $short_name, $type, $ai_data, $research_text, $log);
        $article_html .= $intro;

        // 2. BODY SECTIONS (4-7 sections)
        if (empty($sections)) {
            $sections = $this->generate_smart_sections($title, $short_name, $type, $ai_data);
        }

        foreach ($sections as $i => $section) {
            $heading = $section['heading'] ?? '';
            if (empty($heading)) continue;

            // Ambil data spesifik untuk section ini
            $section_data = $this->get_section_specific_data($heading, $ai_data, $knowledge_graph);

            $section_html = $this->generate_section_ai($title, $short_name, $heading, $section, $section_data, $type, $research_text, $log);
            $article_html .= $section_html;
        }

        // 3. KESIMPULAN
        $conclusion = $this->generate_conclusion($title, $short_name, $type, $ai_data, $log);
        $article_html .= $conclusion;

        // Hitung kata
        $word_count = str_word_count(strip_tags($article_html));
        $log[] = "[Council V5] Total output: {$word_count} kata";

        // Jika masih kurang, tambah section bonus
        if ($word_count < $this->min_words) {
            $log[] = "[Council V5] Kurang dari {$this->min_words} kata, menambah section bonus...";
            $bonus = $this->generate_bonus_sections($title, $short_name, $type, $ai_data, $research_text, $log);
            // Sisipkan sebelum kesimpulan
            $article_html = $this->insert_before_conclusion($article_html, $bonus);
            $word_count = str_word_count(strip_tags($article_html));
            $log[] = "[Council V5] Setelah bonus: {$word_count} kata";
        }

        $parsed = $this->parse_article_into_sections($article_html);

        $log[] = '[Council V5] ✓ Selesai: ' . $word_count . ' kata, ' . count($parsed['sections']) . ' sections';

        return array(
            'introduction' => $parsed['introduction'],
            'sections'     => $parsed['sections'],
            'conclusion'   => $parsed['conclusion'],
            'full_html'    => $article_html,
            'meta'         => $meta,
            'title'        => $seo_title,
            'word_count'   => $word_count,
            'log'          => $log,
        );
    }

    // ============================================================
    // INTRODUCTION GENERATOR
    // ============================================================

    private function generate_introduction($title, $short_name, $type, $ai_data, $research_text, &$log) {
        $log[] = '[Council V5] Generating introduction...';

        $ringkasan = $ai_data['RINGKASAN_TOPIK'] ?? '';
        $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';
        $lokasi = $ai_data['LOKASI_LENGKAP'] ?? '';

        // Trim research text for intro prompt
        $research_snippet = mb_substr($research_text, 0, 2000);

        $prompt = "Kamu adalah penulis artikel wisata senior untuk website \"{$this->site_name}\".

TUGAS: Tulis INTRODUCTION untuk artikel tentang \"{$title}\".

DATA RISET:
{$research_snippet}

RINGKASAN: {$ringkasan}
DAYA TARIK: {$daya_tarik}
LOKASI: {$lokasi}

ATURAN KETAT:
1. Tulis 2-3 paragraf (total 150-250 kata)
2. Paragraf pertama WAJIB menyebut \"{$this->site_name}\" secara natural, contoh: \"{$this->site_name} akan menyuguhkan informasi lengkap tentang...\" atau \"{$this->site_name} kali ini mengajak Anda menjelajahi...\"
3. JANGAN gunakan kata \"saya\" atau \"aku\"
4. Paragraf kedua: jelaskan apa yang akan dibahas dalam artikel
5. Paragraf ketiga (opsional): fakta menarik atau konteks tambahan
6. Gaya bahasa: jurnalistik, hangat, mengajak - BUKAN formal kaku
7. JANGAN mulai dengan \"Selamat datang\" atau \"Halo\"
8. Gunakan <p> tag untuk setiap paragraf
9. Gunakan <strong> untuk keyword penting (2-3 kali)
10. Gunakan <em> untuk istilah lokal (1 kali)
11. JANGAN tulis heading, langsung paragraf saja
12. Setiap paragraf minimal 3 kalimat yang BERBEDA dan informatif
13. JANGAN mengulang kalimat yang sama dengan kata berbeda

Tulis LANGSUNG dalam HTML (hanya <p>, <strong>, <em>). JANGAN tambahkan penjelasan.";

        $result = $this->call_ai($prompt);

        if (!empty($result) && strlen(strip_tags($result)) > 100) {
            $log[] = '[Council V5] Introduction: AI berhasil';
            return $this->clean_ai_output($result) . "\n\n";
        }

        // Fallback: generate dari data research
        $log[] = '[Council V5] Introduction: AI gagal, generate dari data...';
        return $this->generate_intro_from_data($title, $short_name, $type, $ai_data, $lokasi);
    }

    private function generate_intro_from_data($title, $short_name, $type, $ai_data, $lokasi) {
        $html = '';
        $ringkasan = $ai_data['RINGKASAN_TOPIK'] ?? '';
        $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';

        // Paragraf 1 - variasi berdasarkan tipe
        $openers = array(
            "destinasi" => "{$this->site_name} akan menyuguhkan panduan lengkap tentang <strong>{$short_name}</strong>, salah satu destinasi wisata yang semakin populer dan banyak dicari oleh wisatawan.",
            "kuliner" => "{$this->site_name} kali ini mengajak Anda untuk menjelajahi kelezatan <strong>{$short_name}</strong> yang telah memikat lidah banyak penikmat kuliner.",
            "hotel" => "{$this->site_name} akan mengulas secara lengkap tentang <strong>{$short_name}</strong>, pilihan akomodasi yang menawarkan kenyamanan dan pengalaman menginap yang berkesan.",
            "aktivitas" => "{$this->site_name} mengajak Anda merasakan serunya <strong>{$short_name}</strong>, sebuah pengalaman wisata yang sayang untuk dilewatkan.",
        );
        $opener = $openers[$type] ?? $openers['destinasi'];

        $html .= "<p>{$opener}";
        if (!empty($ringkasan) && $ringkasan !== $title) {
            $html .= " " . ucfirst(trim($ringkasan, '.')) . ".";
        } else if (!empty($daya_tarik)) {
            $html .= " Dengan " . strtolower(trim($daya_tarik, '.')) . ", tempat ini berhasil menarik perhatian banyak pengunjung dari berbagai daerah.";
        }
        $html .= "</p>\n\n";

        // Paragraf 2
        $html .= "<p>Dalam artikel ini, Anda akan menemukan informasi lengkap mulai dari lokasi dan cara menuju ke sana, estimasi biaya yang perlu disiapkan, hingga tips berkunjung yang berguna untuk memaksimalkan pengalaman Anda. Semua informasi telah {$this->site_name} rangkum dari berbagai sumber terpercaya dan diperbarui untuk tahun " . date('Y') . ".</p>\n\n";

        // Paragraf 3 jika ada lokasi
        if (!empty($lokasi) && stripos($lokasi, 'silakan') === false && stripos($lokasi, 'hubungi') === false) {
            $html .= "<p>Berlokasi di <strong>" . ucfirst(trim($lokasi, '.')) . "</strong>, destinasi ini menawarkan kemudahan akses yang menjadi nilai tambah tersendiri bagi wisatawan yang ingin berkunjung.</p>\n\n";
        }

        return $html;
    }

    // ============================================================
    // SECTION GENERATOR - AI per-section
    // ============================================================

    private function generate_section_ai($title, $short_name, $heading, $section, $section_data, $type, $research_text, &$log) {
        $has_table = !empty($section['has_table']);
        $has_list = !empty($section['has_list']);
        $instruction = $section['instruction'] ?? '';

        // Ambil snippet research yang relevan
        $relevant_research = $this->extract_relevant_research($heading, $research_text);
        if (strlen($relevant_research) > 3000) {
            $relevant_research = mb_substr($relevant_research, 0, 3000);
        }

        $format_instruction = '';
        if ($has_table) $format_instruction .= "\n- WAJIB sertakan 1 tabel HTML (<table> dengan <thead> dan <tbody>) yang berisi data relevan";
        if ($has_list) $format_instruction .= "\n- WAJIB sertakan 1 list HTML (<ul> atau <ol>) dengan minimal 4 item yang detail";

        $prompt = "Kamu adalah penulis konten wisata senior untuk \"{$this->site_name}\".

TUGAS: Tulis SATU SECTION artikel tentang \"{$title}\".
SECTION: {$heading}
" . (!empty($instruction) ? "INSTRUKSI KHUSUS: {$instruction}\n" : "") . "
DATA SPESIFIK UNTUK SECTION INI:
{$section_data}

KONTEKS DARI RISET:
{$relevant_research}

ATURAN KETAT:
1. Tulis 150-400 kata untuk section ini
2. Mulai dengan <h2>{$heading}</h2> lalu paragraf-paragraf
3. Setiap paragraf minimal 3 kalimat yang INFORMATIF dan BERBEDA
4. Gunakan <strong> untuk keyword penting (2-4 kali)
5. Gunakan <em> untuk istilah lokal atau penekanan (1-2 kali)
6. JANGAN gunakan kata \"saya\" atau \"aku\", ganti dengan \"{$this->site_name}\" jika perlu
7. Gaya bahasa: jurnalistik, informatif, hangat
8. JANGAN mengulang informasi yang sama dengan kata berbeda
9. Setiap kalimat harus memberikan INFORMASI BARU
10. Gunakan data spesifik dari riset (angka, nama, fakta) jika tersedia{$format_instruction}

FORMAT: HTML langsung (<h2>, <p>, <strong>, <em>, <table>, <ul>, <ol>, <blockquote>). JANGAN tambahkan penjelasan.";

        $result = $this->call_ai($prompt);

        if (!empty($result) && strlen(strip_tags($result)) > 80) {
            $log[] = "[Council V5] Section \"{$heading}\": AI berhasil (" . str_word_count(strip_tags($result)) . " kata)";
            $cleaned = $this->clean_ai_output($result);
            // Pastikan ada heading
            if (stripos($cleaned, '<h2') === false) {
                $cleaned = "<h2>{$heading}</h2>\n\n" . $cleaned;
            }
            return $cleaned . "\n\n";
        }

        // Fallback: generate dari data spesifik
        $log[] = "[Council V5] Section \"{$heading}\": AI gagal, generate dari data...";
        return $this->generate_section_from_data($heading, $short_name, $section_data, $ai_data ?? array(), $has_table, $has_list, $type);
    }

    /**
     * Generate section dari data research (bukan template!)
     * Setiap section di-generate berdasarkan DATA yang ada
     */
    private function generate_section_from_data($heading, $short_name, $section_data, $ai_data, $has_table, $has_list, $type) {
        $html = "<h2>{$heading}</h2>\n\n";
        $heading_lower = strtolower($heading);

        // Jika ada section_data, gunakan AI untuk merangkumnya
        if (!empty($section_data) && strlen($section_data) > 50) {
            $prompt = "Rangkum informasi berikut menjadi 2-3 paragraf HTML yang informatif (150-300 kata) tentang \"{$heading}\" untuk {$short_name}. Gunakan <p>, <strong>, <em>. Gaya jurnalistik. JANGAN gunakan kata saya/aku. Data:\n\n{$section_data}";
            $result = $this->call_ai($prompt);
            if (!empty($result) && strlen(strip_tags($result)) > 80) {
                return $html . $this->clean_ai_output($result) . "\n\n";
            }
        }

        // Last resort: generate konten minimal dari data yang ada
        // TAPI tetap berdasarkan data, bukan template generik
        if (preg_match('/(lokasi|cara|menuju|akses|rute|alamat)/i', $heading_lower)) {
            $lokasi = $ai_data['LOKASI_LENGKAP'] ?? '';
            if (!empty($lokasi) && stripos($lokasi, 'silakan') === false) {
                $html .= "<p><strong>{$short_name}</strong> berlokasi di <strong>{$lokasi}</strong>. Akses menuju lokasi ini terbilang mudah karena dapat dijangkau dengan kendaraan pribadi maupun transportasi umum. Gunakan aplikasi navigasi seperti <em>Google Maps</em> untuk mendapatkan rute terbaik.</p>\n\n";
            } else {
                $html .= "<p>Untuk menuju <strong>{$short_name}</strong>, Anda bisa menggunakan kendaraan pribadi atau transportasi umum. Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk mendapatkan panduan rute yang akurat menuju lokasi.</p>\n\n";
            }
            $html .= "<p>Bagi yang menggunakan kendaraan pribadi, tersedia area parkir di sekitar lokasi. Layanan ojek <em>online</em> seperti Gojek dan Grab juga bisa menjadi alternatif transportasi yang praktis. Disarankan untuk berangkat lebih awal terutama pada akhir pekan agar perjalanan lebih nyaman.</p>\n\n";
        }
        elseif (preg_match('/(harga|tiket|biaya|tarif|budget)/i', $heading_lower)) {
            $harga = $ai_data['HARGA_TIKET'] ?? '';
            if (!empty($harga) && stripos($harga, 'hubungi') === false) {
                $html .= "<p>Berikut informasi <strong>harga tiket masuk</strong> {$short_name} yang perlu diketahui sebelum berkunjung. Harga tiket masuk {$short_name} adalah <strong>{$harga}</strong>.</p>\n\n";
            } else {
                $html .= "<p>Untuk informasi <strong>harga tiket masuk</strong> {$short_name} terbaru, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi destinasi ini. Harga dapat berubah sewaktu-waktu terutama pada musim liburan.</p>\n\n";
            }
        }
        elseif (preg_match('/(jam|operasional|buka|tutup|waktu)/i', $heading_lower)) {
            $jam = $ai_data['JAM_OPERASIONAL'] ?? '';
            if (!empty($jam) && stripos($jam, 'hubungi') === false && stripos($jam, 'silakan') === false) {
                $html .= "<p><strong>{$short_name}</strong> beroperasi pada <strong>{$jam}</strong>. Waktu terbaik untuk berkunjung adalah pagi hari antara pukul 08.00-10.00 ketika suasana masih sejuk dan belum terlalu ramai.</p>\n\n";
            } else {
                $html .= "<p>Untuk informasi <strong>jam operasional</strong> terkini, disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi {$short_name}. Umumnya destinasi wisata seperti ini buka setiap hari dari pagi hingga sore.</p>\n\n";
            }
        }
        elseif (preg_match('/(daya tarik|aktivitas|menarik|kegiatan|wahana|spot)/i', $heading_lower)) {
            $aktivitas = $ai_data['AKTIVITAS'] ?? '';
            $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';
            if (!empty($daya_tarik)) {
                $html .= "<p><strong>{$short_name}</strong> memiliki beragam daya tarik yang menjadikannya layak untuk dikunjungi. {$daya_tarik}.</p>\n\n";
            }
            if (!empty($aktivitas)) {
                $items = array_filter(array_map('trim', preg_split('/[,;]/', $aktivitas)));
                if (count($items) > 2) {
                    $html .= "<p>Berikut beberapa aktivitas yang bisa dilakukan saat berkunjung:</p>\n<ul>\n";
                    foreach ($items as $item) {
                        $html .= "<li><strong>" . ucfirst($item) . "</strong></li>\n";
                    }
                    $html .= "</ul>\n\n";
                } else {
                    $html .= "<p>Pengunjung bisa menikmati berbagai aktivitas menarik seperti {$aktivitas}.</p>\n\n";
                }
            }
        }
        elseif (preg_match('/(fasilitas|layanan|akomodasi)/i', $heading_lower)) {
            $fasilitas = $ai_data['FASILITAS'] ?? '';
            if (!empty($fasilitas)) {
                $items = array_filter(array_map('trim', preg_split('/[,;]/', $fasilitas)));
                $html .= "<p><strong>{$short_name}</strong> dilengkapi dengan berbagai fasilitas untuk menunjang kenyamanan pengunjung:</p>\n<ul>\n";
                foreach ($items as $item) {
                    $html .= "<li><strong>" . ucfirst($item) . "</strong></li>\n";
                }
                $html .= "</ul>\n\n";
            } else {
                $html .= "<p>Fasilitas di <strong>{$short_name}</strong> terus ditingkatkan oleh pengelola untuk memberikan kenyamanan bagi setiap pengunjung.</p>\n\n";
            }
        }
        elseif (preg_match('/(tips|rekomendasi|saran|panduan)/i', $heading_lower)) {
            $tips = $ai_data['TIPS'] ?? '';
            if (!empty($tips)) {
                $items = array_filter(array_map('trim', preg_split('/[,;]/', $tips)));
                $html .= "<p>Berikut beberapa tips yang {$this->site_name} rekomendasikan agar kunjungan Anda semakin berkesan:</p>\n<ol>\n";
                foreach ($items as $item) {
                    $html .= "<li>" . ucfirst($item) . "</li>\n";
                }
                $html .= "</ol>\n\n";
            }
        }
        elseif (preg_match('/(kuliner|makanan|restoran|menu)/i', $heading_lower)) {
            $kuliner = $ai_data['KULINER_TERDEKAT'] ?? '';
            if (!empty($kuliner)) {
                $html .= "<p>Di sekitar <strong>{$short_name}</strong>, terdapat berbagai pilihan kuliner yang sayang untuk dilewatkan. {$kuliner}.</p>\n\n";
            }
        }
        else {
            // Section generik - tetap coba AI
            $prompt = "Tulis 2 paragraf informatif (100-200 kata) tentang \"{$heading}\" untuk {$short_name}. Gunakan HTML (<p>, <strong>, <em>). Gaya jurnalistik. JANGAN kata saya/aku.";
            $result = $this->call_ai($prompt);
            if (!empty($result) && strlen(strip_tags($result)) > 50) {
                $html .= $this->clean_ai_output($result) . "\n\n";
            } else {
                $html .= "<p>{$heading} merupakan aspek penting yang perlu diketahui sebelum berkunjung ke <strong>{$short_name}</strong>. Informasi ini akan membantu Anda mempersiapkan kunjungan dengan lebih baik.</p>\n\n";
            }
        }

        return $html;
    }

    // ============================================================
    // CONCLUSION GENERATOR
    // ============================================================

    private function generate_conclusion($title, $short_name, $type, $ai_data, &$log) {
        $log[] = '[Council V5] Generating conclusion...';

        $prompt = "Tulis KESIMPULAN artikel tentang \"{$title}\" untuk website \"{$this->site_name}\".

ATURAN:
1. Tulis 2 paragraf (80-150 kata total)
2. Paragraf 1: rangkum poin utama artikel dan berikan rekomendasi
3. Paragraf 2: ajak pembaca untuk berkunjung dan sebutkan \"{$this->site_name}\"
4. Gunakan <p>, <strong>. JANGAN gunakan kata saya/aku
5. JANGAN tulis heading \"Kesimpulan\", langsung paragraf saja

Tulis LANGSUNG dalam HTML.";

        $result = $this->call_ai($prompt);

        if (!empty($result) && strlen(strip_tags($result)) > 50) {
            $log[] = '[Council V5] Conclusion: AI berhasil';
            return $this->clean_ai_output($result) . "\n\n";
        }

        // Fallback
        $log[] = '[Council V5] Conclusion: AI gagal, generate dari data...';
        $html = "<p>Demikian informasi lengkap tentang <strong>{$short_name}</strong> yang telah {$this->site_name} rangkum untuk Anda. Dengan berbagai daya tarik yang ditawarkan, destinasi ini layak masuk dalam daftar kunjungan Anda berikutnya.</p>\n\n";
        $html .= "<p>Pastikan untuk mempersiapkan segala kebutuhan sebelum berkunjung dan selalu cek informasi terbaru mengenai harga tiket serta jam operasional. Semoga panduan dari {$this->site_name} ini bermanfaat untuk merencanakan perjalanan Anda.</p>\n\n";
        return $html;
    }

    // ============================================================
    // BONUS SECTIONS (jika word count masih kurang)
    // ============================================================

    private function generate_bonus_sections($title, $short_name, $type, $ai_data, $research_text, &$log) {
        $html = '';

        // Bonus 1: FAQ dari AI
        $faq_prompt = "Buat 5 FAQ (Pertanyaan yang Sering Diajukan) tentang \"{$title}\" dalam format HTML.
Setiap FAQ: <h3>Pertanyaan?</h3> lalu <p>Jawaban 2-3 kalimat informatif</p>.
Mulai dengan <h2>Pertanyaan yang Sering Diajukan</h2>.
Gunakan <strong> untuk keyword. JANGAN kata saya/aku. Total 200-300 kata.";

        $faq = $this->call_ai($faq_prompt);
        if (!empty($faq) && strlen(strip_tags($faq)) > 100) {
            $html .= $this->clean_ai_output($faq) . "\n\n";
            $log[] = '[Council V5] Bonus FAQ: AI berhasil';
        }

        // Bonus 2: Wisata Terdekat
        $nearby_prompt = "Tulis 1 section tentang wisata terdekat dari \"{$short_name}\" dalam format HTML.
Mulai dengan <h2>Destinasi Wisata Terdekat dari {$short_name}</h2>.
Tulis 2 paragraf (100-150 kata) yang informatif tentang destinasi lain di sekitar lokasi.
Gunakan <strong>, <em>. JANGAN kata saya/aku.";

        $nearby = $this->call_ai($nearby_prompt);
        if (!empty($nearby) && strlen(strip_tags($nearby)) > 50) {
            $html .= $this->clean_ai_output($nearby) . "\n\n";
            $log[] = '[Council V5] Bonus Wisata Terdekat: AI berhasil';
        }

        return $html;
    }

    // ============================================================
    // SMART SECTION GENERATOR
    // ============================================================

    private function generate_smart_sections($title, $short_name, $type, $ai_data) {
        $sections = array();

        // Tentukan sections berdasarkan tipe konten
        switch ($type) {
            case 'kuliner':
                $sections = array(
                    array('heading' => "Mengenal {$short_name}", 'has_table' => false, 'has_list' => false, 'instruction' => 'Deskripsi umum, sejarah, dan keunikan'),
                    array('heading' => "Lokasi dan Cara Menuju {$short_name}", 'has_table' => false, 'has_list' => true, 'instruction' => 'Alamat, rute, transportasi'),
                    array('heading' => "Menu Andalan dan Harga", 'has_table' => true, 'has_list' => false, 'instruction' => 'Daftar menu, harga, rekomendasi'),
                    array('heading' => "Suasana dan Fasilitas", 'has_table' => false, 'has_list' => true, 'instruction' => 'Ambiance, fasilitas, kapasitas'),
                    array('heading' => "Tips Berkunjung", 'has_table' => false, 'has_list' => true, 'instruction' => 'Waktu terbaik, tips memesan, dll'),
                );
                break;

            case 'hotel':
                $sections = array(
                    array('heading' => "Review {$short_name}", 'has_table' => false, 'has_list' => false, 'instruction' => 'Overview hotel, keunggulan'),
                    array('heading' => "Lokasi dan Akses", 'has_table' => false, 'has_list' => true, 'instruction' => 'Alamat, jarak dari landmark'),
                    array('heading' => "Tipe Kamar dan Harga", 'has_table' => true, 'has_list' => false, 'instruction' => 'Jenis kamar, tarif, fasilitas kamar'),
                    array('heading' => "Fasilitas Hotel", 'has_table' => false, 'has_list' => true, 'instruction' => 'Pool, gym, restaurant, dll'),
                    array('heading' => "Tips Booking dan Menginap", 'has_table' => false, 'has_list' => true, 'instruction' => 'Cara booking, tips hemat'),
                );
                break;

            default: // destinasi
                $sections = array(
                    array('heading' => "Mengenal {$short_name} Lebih Dekat", 'has_table' => false, 'has_list' => false, 'instruction' => 'Sejarah, deskripsi, daya tarik utama'),
                    array('heading' => "Lokasi dan Cara Menuju {$short_name}", 'has_table' => false, 'has_list' => true, 'instruction' => 'Alamat lengkap, rute, transportasi'),
                    array('heading' => "Harga Tiket Masuk dan Jam Operasional", 'has_table' => true, 'has_list' => false, 'instruction' => 'Harga tiket, jam buka, hari operasional'),
                    array('heading' => "Daya Tarik dan Aktivitas Menarik", 'has_table' => false, 'has_list' => true, 'instruction' => 'Aktivitas, wahana, spot foto'),
                    array('heading' => "Fasilitas yang Tersedia", 'has_table' => false, 'has_list' => true, 'instruction' => 'Fasilitas umum dan khusus'),
                    array('heading' => "Tips Berkunjung ke {$short_name}", 'has_table' => false, 'has_list' => true, 'instruction' => 'Tips praktis untuk pengunjung'),
                );
                break;
        }

        return $sections;
    }

    // ============================================================
    // HELPER: Parse topic name dari judul
    // ============================================================

    private function parse_topic_name($title) {
        // Hapus kata-kata SEO filler
        $fillers = array(
            'panduan lengkap', 'info lengkap', 'terbaru', 'review lengkap',
            'rekomendasi', 'itinerary', 'budget lengkap', 'tips',
            'harga tiket', 'jam buka', 'wisata', 'destinasi',
            'info', 'panduan', 'lengkap', 'terbaik', 'terdekat',
        );

        $clean = strtolower($title);

        // Hapus tahun
        $clean = preg_replace('/\b20\d{2}\b/', '', $clean);

        // Hapus simbol
        $clean = preg_replace('/[:\-–—|&]/', ' ', $clean);

        // Hapus filler words
        foreach ($fillers as $filler) {
            $clean = str_ireplace($filler, '', $clean);
        }

        // Hapus angka di awal (seperti "9 Destinasi...")
        $clean = preg_replace('/^\d+\s+/', '', trim($clean));

        $clean = ucwords(trim(preg_replace('/\s+/', ' ', $clean)));

        // Jika terlalu pendek, gunakan judul asli
        if (strlen($clean) < 5) {
            return ucwords(trim(preg_replace('/[:\-–—|]/', ' ', $title)));
        }

        return $clean;
    }

    // ============================================================
    // HELPER: Get section-specific data
    // ============================================================

    private function get_section_specific_data($heading, $ai_data, $kg) {
        $heading_lower = strtolower($heading);
        $data = '';

        $mapping = array(
            'sejarah|mengenal|tentang|review|deskripsi' => array('RINGKASAN_TOPIK', 'SEJARAH', 'FAKTA_UNIK', 'DAYA_TARIK'),
            'lokasi|cara|menuju|akses|rute|alamat' => array('LOKASI_LENGKAP'),
            'harga|tiket|jam|operasional|biaya|tarif|budget' => array('HARGA_TIKET', 'JAM_OPERASIONAL'),
            'fasilitas|layanan|akomodasi' => array('FASILITAS'),
            'aktivitas|daya tarik|menarik|wahana|spot|kegiatan' => array('AKTIVITAS', 'DAYA_TARIK'),
            'kuliner|makanan|restoran|menu' => array('KULINER_TERDEKAT'),
            'tips|rekomendasi|saran|panduan' => array('TIPS'),
        );

        foreach ($mapping as $pattern => $keys) {
            if (preg_match('/(' . $pattern . ')/i', $heading_lower)) {
                foreach ($keys as $key) {
                    if (!empty($ai_data[$key])) {
                        $data .= "{$key}: {$ai_data[$key]}\n";
                    }
                }
                break;
            }
        }

        // Tambahkan data dari entities jika relevan
        if (preg_match('/(harga|tiket|biaya|tarif)/i', $heading_lower)) {
            $prices = $kg['prices'] ?? array();
            if (!empty($prices)) {
                $data .= "HARGA DITEMUKAN: " . implode(', ', array_slice($prices, 0, 10)) . "\n";
            }
        }

        if (preg_match('/(jam|operasional|buka)/i', $heading_lower)) {
            $times = $kg['times'] ?? array();
            if (!empty($times)) {
                $data .= "JAM DITEMUKAN: " . implode(', ', array_slice($times, 0, 5)) . "\n";
            }
        }

        if (preg_match('/(lokasi|alamat|cara)/i', $heading_lower)) {
            $locations = $kg['locations'] ?? array();
            if (!empty($locations)) {
                $data .= "LOKASI DITEMUKAN: " . implode(', ', array_slice($locations, 0, 5)) . "\n";
            }
        }

        return $data;
    }

    // ============================================================
    // HELPER: Extract relevant research text
    // ============================================================

    private function extract_relevant_research($heading, $research_text) {
        $heading_lower = strtolower($heading);
        $relevant = '';

        // Cari paragraf yang relevan dari research text
        $paragraphs = preg_split('/\n{2,}/', $research_text);

        $keywords = array();
        if (preg_match('/(lokasi|cara|menuju)/i', $heading_lower)) {
            $keywords = array('lokasi', 'alamat', 'jalan', 'akses', 'rute', 'menuju', 'transportasi');
        } elseif (preg_match('/(harga|tiket|biaya)/i', $heading_lower)) {
            $keywords = array('harga', 'tiket', 'biaya', 'tarif', 'rupiah', 'rp');
        } elseif (preg_match('/(jam|operasional)/i', $heading_lower)) {
            $keywords = array('jam', 'buka', 'tutup', 'operasional', 'waktu', 'pukul');
        } elseif (preg_match('/(fasilitas)/i', $heading_lower)) {
            $keywords = array('fasilitas', 'parkir', 'toilet', 'mushola', 'warung', 'gazebo');
        } elseif (preg_match('/(aktivitas|daya tarik)/i', $heading_lower)) {
            $keywords = array('aktivitas', 'kegiatan', 'wahana', 'spot', 'foto', 'bermain');
        } elseif (preg_match('/(tips)/i', $heading_lower)) {
            $keywords = array('tips', 'saran', 'rekomendasi', 'disarankan', 'sebaiknya');
        } elseif (preg_match('/(kuliner|makanan)/i', $heading_lower)) {
            $keywords = array('kuliner', 'makanan', 'restoran', 'warung', 'menu', 'masakan');
        }

        foreach ($paragraphs as $p) {
            $p_lower = strtolower($p);
            foreach ($keywords as $kw) {
                if (stripos($p_lower, $kw) !== false) {
                    $relevant .= trim($p) . "\n\n";
                    break;
                }
            }
        }

        return $relevant;
    }

    // ============================================================
    // HELPER: Prepare context & research text
    // ============================================================

    private function prepare_context($kg) {
        $context = '';
        $ai_data = $kg['ai_analysis'] ?? array();
        foreach ($ai_data as $key => $val) {
            if (!empty($val)) {
                $context .= strtoupper($key) . ": " . $val . "\n";
            }
        }
        return $context;
    }

    private function prepare_research_text($kg) {
        $text = '';
        if (!empty($kg['content_map'])) {
            foreach (array_slice($kg['content_map'], 0, 5) as $cm) {
                $t = $cm['text'] ?? '';
                if (strlen($t) > 2000) $t = mb_substr($t, 0, 2000);
                $text .= "=== " . ($cm['source'] ?? 'Sumber') . " ===\n" . $t . "\n\n";
            }
        }
        if (!empty($kg['key_facts'])) {
            $text .= "=== FAKTA PENTING ===\n";
            foreach (array_slice($kg['key_facts'], 0, 15) as $fact) {
                $text .= "- " . $fact . "\n";
            }
        }
        return $text;
    }

    // ============================================================
    // HELPER: Insert before conclusion
    // ============================================================

    private function insert_before_conclusion($html, $bonus) {
        // Cari posisi terakhir </h2> yang berisi "Kesimpulan" atau paragraf terakhir
        $last_h2_pos = strrpos($html, '<h2>');
        if ($last_h2_pos !== false) {
            return substr($html, 0, $last_h2_pos) . $bonus . substr($html, $last_h2_pos);
        }
        // Jika tidak ada, tambahkan di akhir
        return $html . $bonus;
    }

    // ============================================================
    // HELPER: Parse article into sections
    // ============================================================

    private function parse_article_into_sections($html) {
        $result = array('introduction' => '', 'sections' => array(), 'conclusion' => '');

        $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!empty($parts[0])) {
            $result['introduction'] = trim($parts[0]);
        }

        for ($i = 1; $i < count($parts); $i += 2) {
            $heading_html = $parts[$i] ?? '';
            $content = trim($parts[$i + 1] ?? '');

            preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $heading_html, $m);
            $heading = strip_tags($m[1] ?? '');

            if (stripos($heading, 'kesimpulan') !== false || stripos($heading, 'penutup') !== false) {
                $result['conclusion'] = $content;
            } else {
                $result['sections'][] = array('heading' => $heading, 'content' => $content, 'format' => 'paragraph');
            }
        }

        return $result;
    }

    // ============================================================
    // HELPER: Clean AI output
    // ============================================================

    private function clean_ai_output($text) {
        // Remove markdown headers
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        // Convert markdown bold/italic to HTML
        $text = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<![<\/])\*([^*]+)\*/', '<em>$1</em>', $text);

        // Wrap plain text in paragraphs if needed
        if (strpos($text, '<p>') === false && strpos($text, '<h2') === false && strpos($text, '<h3') === false) {
            $paragraphs = preg_split('/\n{2,}/', $text);
            $html = '';
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (!empty($p) && strlen($p) > 10) {
                    if (strpos($p, '<') !== 0) {
                        $html .= '<p>' . $p . "</p>\n\n";
                    } else {
                        $html .= $p . "\n\n";
                    }
                }
            }
            $text = $html;
        }

        return trim($text);
    }

    // ============================================================
    // AI CALL METHODS
    // ============================================================

    private function call_ai($prompt) {
        // Try DuckDuckGo first (free)
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result) && strlen($result) > 50) return $result;

        // Try OpenAI if configured
        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) {
            return $this->call_openai($prompt, $api_key);
        }

        return '';
    }

    private function call_duckduckgo_ai($prompt) {
        // Get VQD token
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10,
            'headers' => array('x-vqd-accept' => '1', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'sslverify' => false,
        ));
        if (is_wp_error($token_response)) return '';

        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        // Call chat API
        $chat_response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-vqd-4' => $vqd,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'body' => wp_json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
            )),
            'sslverify' => false,
        ));

        if (is_wp_error($chat_response)) return '';

        $body = wp_remote_retrieve_body($chat_response);
        $full_text = '';

        // Parse SSE response
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;
                $json = json_decode($data, true);
                if (isset($json['message'])) {
                    $full_text .= $json['message'];
                }
            }
        }

        return trim($full_text);
    }

    private function call_openai($prompt, $api_key) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => wp_json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'system', 'content' => 'Kamu adalah penulis konten wisata profesional untuk website Indonesia. Tulis dalam bahasa Indonesia yang natural dan informatif.'),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'max_tokens' => 4000,
                'temperature' => 0.7,
            )),
            'sslverify' => false,
        ));

        if (is_wp_error($response)) return '';

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
