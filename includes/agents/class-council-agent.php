<?php
/**
 * Project Hyperion - Agent #3: The Council V4
 * MEGA Content Generator
 *
 * Strategi: 1 panggilan AI BESAR untuk seluruh artikel
 * agar memaksimalkan output token dan menghasilkan 1000-3000 kata.
 *
 * Jika AI gagal/pendek → fallback content generator built-in
 * yang menghasilkan artikel lengkap dari data research.
 *
 * @version 4.0.0
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
     * MAIN: Generate artikel lengkap
     */
    public function write($title, $knowledge_graph, $blueprint) {
        $log = array();
        $log[] = '[Council V4] Memulai penulisan MEGA artikel untuk: "' . $title . '"';

        $ai_data = $knowledge_graph['ai_analysis'] ?? array();
        $sections = $blueprint['sections'] ?? array();
        $type = $blueprint['type'] ?? $knowledge_graph['type'] ?? 'destinasi';
        $seo_title = $blueprint['title'] ?? $title;
        $meta = $blueprint['meta'] ?? '';

        // Siapkan context data dari knowledge graph
        $context = $this->prepare_full_context($knowledge_graph);

        // ============================================================
        // STRATEGI 1: Satu panggilan AI BESAR untuk seluruh artikel
        // ============================================================
        $full_article = $this->generate_full_article_ai($title, $seo_title, $type, $sections, $context, $ai_data, $log);

        $word_count = str_word_count(strip_tags($full_article));
        $log[] = "[Council V4] AI output: {$word_count} kata";

        // ============================================================
        // STRATEGI 2: Jika AI output < 800 kata, gunakan BUILT-IN generator
        // ============================================================
        if ($word_count < 800) {
            $log[] = '[Council V4] AI output terlalu pendek, menggunakan BUILT-IN generator...';
            $full_article = $this->generate_full_article_builtin($title, $seo_title, $type, $sections, $context, $ai_data, $log);
            $word_count = str_word_count(strip_tags($full_article));
            $log[] = "[Council V4] Built-in output: {$word_count} kata";
        }

        // ============================================================
        // STRATEGI 3: Jika masih < 1000, expand dengan AI per-section
        // ============================================================
        if ($word_count < $this->min_words) {
            $log[] = '[Council V4] Masih kurang, expanding per-section...';
            $full_article = $this->expand_article_sections($title, $full_article, $type, $context, $ai_data, $log);
            $word_count = str_word_count(strip_tags($full_article));
            $log[] = "[Council V4] Expanded output: {$word_count} kata";
        }

        // Parse article into sections for downstream agents
        $parsed = $this->parse_article_into_sections($full_article);

        $log[] = '[Council V4] ✓ Penulisan selesai: ' . $word_count . ' kata, ' . count($parsed['sections']) . ' sections';

        return array(
            'introduction' => $parsed['introduction'],
            'sections'     => $parsed['sections'],
            'conclusion'   => $parsed['conclusion'],
            'full_html'    => $full_article,
            'meta'         => $meta,
            'title'        => $seo_title,
            'word_count'   => $word_count,
            'log'          => $log,
        );
    }

    /**
     * STRATEGI 1: Satu panggilan AI besar untuk seluruh artikel
     */
    private function generate_full_article_ai($title, $seo_title, $type, $sections, $context, $ai_data, &$log) {
        $log[] = '[Council V4] Strategi 1: Panggilan AI MEGA...';

        // Build section list for prompt
        $section_list = '';
        foreach ($sections as $i => $s) {
            $num = $i + 1;
            $heading = $s['heading'] ?? "Section {$num}";
            $instruction = $s['instruction'] ?? '';
            $has_table = !empty($s['has_table']) ? ' [WAJIB TABEL HTML]' : '';
            $has_list = !empty($s['has_list']) ? ' [WAJIB LIST HTML]' : '';
            $section_list .= "{$num}. H2: {$heading}{$has_table}{$has_list}\n   Instruksi: {$instruction}\n\n";
        }

        // Trim context jika terlalu panjang
        if (strlen($context) > 8000) {
            $context = substr($context, 0, 8000) . "\n[...data dipotong...]";
        }

        $prompt = "Kamu adalah penulis konten senior dan ahli SEO untuk website \"{$this->site_name}\". Tugasmu adalah menulis SATU ARTIKEL LENGKAP tentang topik berikut. Artikel ini HARUS minimal 1000 kata dan maksimal 3000 kata. JANGAN menulis artikel pendek.

=== TOPIK ===
{$seo_title}

=== TIPE KONTEN ===
{$type}

=== DATA RISET (gunakan data ini untuk menulis artikel yang informatif dan akurat) ===
{$context}

=== STRUKTUR ARTIKEL (ikuti urutan ini) ===

INTRODUCTION (2-3 paragraf, WAJIB menyebut \"{$this->site_name}\" di paragraf pertama):
- Paragraf 1: Hook menarik + sebutkan \"{$this->site_name} akan menyuguhkan informasi lengkap tentang {$title}\"
- Paragraf 2: Jelaskan apa yang akan dibahas, buat pembaca penasaran
- Paragraf 3 (opsional): Fakta menarik atau konteks tambahan

SECTIONS:
{$section_list}

KESIMPULAN (2 paragraf):
- Rangkum poin utama dan berikan rekomendasi final
- Sebutkan \"{$this->site_name}\" dan ajak pembaca membaca artikel terkait

=== ATURAN PENULISAN KETAT ===
1. WAJIB minimal 1000 kata. Setiap H2 section harus 150-400 kata
2. JANGAN gunakan kata \"saya\" atau \"aku\", ganti dengan \"{$this->site_name}\"
3. Gunakan tag HTML: <p> untuk paragraf, <h2> untuk heading, <h3> untuk sub-heading
4. Gunakan <strong> untuk keyword penting, nama tempat, harga (3-5 per section)
5. Gunakan <em> untuk istilah lokal atau penekanan (1-2 per section)
6. Jika ada data harga, WAJIB buat <table> HTML dengan <thead> dan <tbody>
7. Jika ada tips, WAJIB buat <ol> atau <ul> HTML
8. Setiap paragraf minimal 3-5 kalimat yang padat dan informatif
9. Gaya bahasa: jurnalistik, informatif, hangat, mengajak - BUKAN formal kaku
10. JANGAN mulai dengan \"Selamat datang\" atau \"Halo\"
11. Tulis LANGSUNG dalam format HTML, JANGAN gunakan markdown
12. Setiap section harus memiliki informasi yang BERBEDA, JANGAN mengulang
13. Gunakan <blockquote> untuk tips penting atau kutipan menarik (1-2 kali dalam artikel)

=== FORMAT OUTPUT ===
Tulis langsung dalam HTML. Mulai dari paragraf introduction (tanpa heading), lalu H2 sections, lalu kesimpulan.
JANGAN tambahkan penjelasan atau komentar. LANGSUNG tulis artikelnya.";

        $result = $this->call_ai($prompt);

        if (!empty($result)) {
            $result = $this->clean_ai_output($result);
        }

        return $result ?: '';
    }

    /**
     * STRATEGI 2: Built-in content generator dari data research
     * Ini adalah fallback yang SANGAT LENGKAP
     */
    private function generate_full_article_builtin($title, $seo_title, $type, $sections, $context, $ai_data, &$log) {
        $log[] = '[Council V4] Menggunakan built-in content generator...';

        $html = '';
        $short_name = $this->extract_short_name($title);

        // ============================================================
        // INTRODUCTION (200-300 kata)
        // ============================================================
        $ringkasan = $ai_data['RINGKASAN_TOPIK'] ?? '';
        $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';
        $lokasi = $ai_data['LOKASI_LENGKAP'] ?? '';

        $html .= "<p><strong>{$this->site_name}</strong> akan menyuguhkan informasi lengkap tentang <strong>{$seo_title}</strong>. ";

        if (!empty($ringkasan)) {
            $html .= $this->humanize_text($ringkasan) . " ";
        } else {
            $html .= "Destinasi ini menawarkan pengalaman wisata yang unik dan berbeda dari yang lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan Anda bersama keluarga maupun teman. ";
        }

        if (!empty($daya_tarik)) {
            $html .= "Dengan <em>" . $this->humanize_text($daya_tarik) . "</em>, tempat ini berhasil menarik perhatian banyak wisatawan dari berbagai daerah.</p>\n\n";
        } else {
            $html .= "Tempat ini telah menjadi salah satu destinasi favorit yang banyak dikunjungi wisatawan lokal maupun mancanegara.</p>\n\n";
        }

        $html .= "<p>Dalam artikel ini, {$this->site_name} akan membahas secara tuntas mulai dari sejarah dan daya tarik utama, informasi lokasi dan akses transportasi, harga tiket masuk dan jam operasional, fasilitas yang tersedia, hingga tips berkunjung yang berguna agar pengalaman liburan Anda semakin berkesan. Semua informasi telah dirangkum dari berbagai sumber terpercaya dan diperbarui untuk tahun " . date('Y') . ".</p>\n\n";

        if (!empty($lokasi)) {
            $html .= "<p>Berlokasi di <strong>" . $this->humanize_text($lokasi) . "</strong>, destinasi ini mudah dijangkau dengan berbagai moda transportasi. Keindahan alam dan suasana yang ditawarkan menjadikan tempat ini cocok untuk berbagai jenis liburan, mulai dari <em>solo traveling</em>, liburan romantis bersama pasangan, hingga wisata keluarga yang menyenangkan.</p>\n\n";
        }

        // ============================================================
        // SECTIONS - Generate dari blueprint
        // ============================================================
        foreach ($sections as $section) {
            $heading = $section['heading'] ?? '';
            $instruction = $section['instruction'] ?? '';
            $has_table = $section['has_table'] ?? false;
            $has_list = $section['has_list'] ?? false;

            if (empty($heading)) continue;

            $html .= "<h2>{$heading}</h2>\n\n";

            // Get relevant data
            $relevant_data = $this->get_relevant_data_for_section($heading, $ai_data, $context);

            // Generate section content based on type
            $section_html = $this->generate_section_content($heading, $relevant_data, $ai_data, $has_table, $has_list, $short_name, $type);

            $html .= $section_html . "\n\n";
        }

        // Jika sections kosong, generate default sections
        if (empty($sections)) {
            $html .= $this->generate_default_sections($title, $short_name, $type, $ai_data, $context);
        }

        // ============================================================
        // KESIMPULAN (150-200 kata)
        // ============================================================
        $html .= "<h2>Kesimpulan</h2>\n\n";
        $html .= "<p>Demikian informasi lengkap tentang <strong>{$seo_title}</strong> yang telah {$this->site_name} rangkum untuk Anda. Dengan berbagai daya tarik yang ditawarkan, mulai dari keindahan alam, fasilitas yang memadai, hingga kemudahan akses, destinasi ini layak masuk dalam daftar kunjungan Anda berikutnya.</p>\n\n";
        $html .= "<p>Pastikan untuk mempersiapkan segala kebutuhan sebelum berkunjung agar pengalaman liburan Anda semakin menyenangkan dan berkesan. Jangan lupa untuk mengecek informasi terbaru mengenai harga tiket dan jam operasional sebelum berangkat. Semoga panduan dari {$this->site_name} ini bermanfaat dan membantu Anda merencanakan perjalanan yang tak terlupakan. Selamat berlibur!</p>\n\n";

        return $html;
    }

    /**
     * Generate konten untuk satu section berdasarkan heading
     */
    private function generate_section_content($heading, $relevant_data, $ai_data, $has_table, $has_list, $short_name, $type) {
        $heading_lower = strtolower($heading);
        $html = '';

        // ============================================================
        // SECTION: Mengenal / Sejarah / Tentang
        // ============================================================
        if (preg_match('/(mengenal|sejarah|tentang|latar|review|deskripsi)/i', $heading_lower)) {
            $ringkasan = $ai_data['RINGKASAN_TOPIK'] ?? '';
            $sejarah = $ai_data['SEJARAH'] ?? '';
            $fakta = $ai_data['FAKTA_UNIK'] ?? '';
            $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';

            if (!empty($sejarah)) {
                $html .= "<p><strong>{$short_name}</strong> memiliki sejarah yang menarik untuk diketahui. " . $this->humanize_text($sejarah) . " Seiring berjalannya waktu, tempat ini terus berkembang dan semakin populer di kalangan wisatawan yang mencari pengalaman wisata yang autentik dan berkesan.</p>\n\n";
            } else {
                $html .= "<p><strong>{$short_name}</strong> merupakan salah satu destinasi yang telah menjadi bagian penting dari warisan budaya dan pariwisata daerah setempat. Destinasi ini telah berkembang dari waktu ke waktu, menarik semakin banyak pengunjung yang ingin menikmati keindahan dan keunikan yang ditawarkan.</p>\n\n";
            }

            if (!empty($daya_tarik)) {
                $html .= "<p>Daya tarik utama dari destinasi ini terletak pada " . $this->humanize_text($daya_tarik) . ". Pengalaman yang disuguhkan oleh tempat ini berbeda dari destinasi lainnya, menjadikannya pilihan yang unik dan menarik untuk dikunjungi. Banyak pengunjung yang merasa puas dan ingin kembali lagi untuk menikmati suasana yang ditawarkan.</p>\n\n";
            }

            if (!empty($fakta)) {
                $html .= "<blockquote><p><strong>Fakta Menarik:</strong> " . $this->humanize_text($fakta) . "</p></blockquote>\n\n";
            }

            if (!empty($ringkasan) && empty($sejarah)) {
                $html .= "<p>" . $this->humanize_text($ringkasan) . " Berlokasi di kawasan yang strategis, destinasi ini mudah dijangkau dan menawarkan berbagai aktivitas menarik yang bisa dinikmati oleh pengunjung dari segala usia.</p>\n\n";
            }

            // Tambahan paragraf agar section lebih panjang
            $html .= "<p>Bagi Anda yang sedang merencanakan liburan, <strong>{$short_name}</strong> bisa menjadi pilihan yang tepat. Tempat ini cocok untuk berbagai jenis wisata, mulai dari wisata keluarga, liburan romantis bersama pasangan, hingga <em>solo traveling</em> untuk Anda yang ingin menikmati ketenangan dan keindahan alam. Dengan suasana yang asri dan fasilitas yang terus ditingkatkan, pengalaman berkunjung ke tempat ini dijamin akan meninggalkan kesan yang mendalam.</p>\n\n";
        }

        // ============================================================
        // SECTION: Lokasi dan Akses
        // ============================================================
        elseif (preg_match('/(lokasi|cara|menuju|akses|rute|alamat)/i', $heading_lower)) {
            $lokasi = $ai_data['LOKASI_LENGKAP'] ?? '';

            if (!empty($lokasi)) {
                $html .= "<p><strong>{$short_name}</strong> berlokasi di <strong>" . $this->humanize_text($lokasi) . "</strong>. Lokasi ini cukup strategis dan dapat dijangkau dengan berbagai moda transportasi, baik kendaraan pribadi maupun transportasi umum.</p>\n\n";
            } else {
                $html .= "<p><strong>{$short_name}</strong> terletak di lokasi yang cukup strategis dan mudah dijangkau. Anda bisa menggunakan berbagai moda transportasi untuk sampai ke destinasi ini.</p>\n\n";
            }

            $html .= "<p>Berikut beberapa pilihan akses menuju lokasi yang bisa Anda pertimbangkan:</p>\n\n";
            $html .= "<ul>\n";
            $html .= "<li><strong>Kendaraan Pribadi</strong> &ndash; Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk panduan rute terbaik menuju lokasi. Pastikan kendaraan dalam kondisi prima, terutama jika melewati jalur yang berkelok atau menanjak.</li>\n";
            $html .= "<li><strong>Transportasi Umum</strong> &ndash; Tersedia angkutan umum dari pusat kota menuju lokasi wisata. Anda bisa bertanya kepada penduduk setempat untuk mengetahui rute angkutan yang paling efisien.</li>\n";
            $html .= "<li><strong>Ojek Online</strong> &ndash; Layanan ojek online seperti <em>Gojek</em> dan <em>Grab</em> tersedia untuk kemudahan akses. Ini menjadi pilihan praktis terutama bagi wisatawan yang tidak membawa kendaraan pribadi.</li>\n";
            $html .= "<li><strong>Travel/Agen Wisata</strong> &ndash; Beberapa agen wisata lokal menyediakan paket perjalanan yang sudah termasuk transportasi menuju lokasi, cocok untuk rombongan atau wisatawan yang ingin perjalanan tanpa repot.</li>\n";
            $html .= "</ul>\n\n";

            $html .= "<p>Disarankan untuk berangkat lebih awal, terutama pada akhir pekan dan hari libur nasional, untuk menghindari kemacetan dan mendapatkan tempat parkir yang lebih leluasa. Perjalanan menuju lokasi juga bisa menjadi pengalaman tersendiri karena Anda akan melewati pemandangan alam yang indah di sepanjang perjalanan.</p>\n\n";
        }

        // ============================================================
        // SECTION: Harga Tiket dan Jam Operasional
        // ============================================================
        elseif (preg_match('/(harga|tiket|jam|operasional|biaya|tarif|booking)/i', $heading_lower)) {
            $harga = $ai_data['HARGA_TIKET'] ?? '';
            $jam = $ai_data['JAM_OPERASIONAL'] ?? '';

            $html .= "<p>Sebelum berkunjung ke <strong>{$short_name}</strong>, penting untuk mengetahui informasi harga tiket masuk dan jam operasional terbaru. Berikut rincian yang telah {$this->site_name} rangkum untuk memudahkan perencanaan kunjungan Anda.</p>\n\n";

            // Tabel harga
            $html .= "<table>\n<thead>\n<tr><th>Kategori</th><th>Hari Biasa (Weekday)</th><th>Akhir Pekan (Weekend)</th><th>Keterangan</th></tr>\n</thead>\n<tbody>\n";

            if (!empty($harga)) {
                $html .= "<tr><td><strong>Dewasa</strong></td><td>" . $this->humanize_text($harga) . "</td><td>" . $this->humanize_text($harga) . "</td><td>Per orang</td></tr>\n";
                $html .= "<tr><td><strong>Anak-anak</strong></td><td>" . $this->humanize_text($harga) . "</td><td>" . $this->humanize_text($harga) . "</td><td>Usia 3-12 tahun</td></tr>\n";
            } else {
                $html .= "<tr><td><strong>Dewasa</strong></td><td>Hubungi pengelola</td><td>Hubungi pengelola</td><td>Per orang</td></tr>\n";
                $html .= "<tr><td><strong>Anak-anak</strong></td><td>Hubungi pengelola</td><td>Hubungi pengelola</td><td>Usia 3-12 tahun</td></tr>\n";
            }

            $html .= "<tr><td><strong>Parkir Motor</strong></td><td>Rp 5.000</td><td>Rp 5.000</td><td>Per kendaraan</td></tr>\n";
            $html .= "<tr><td><strong>Parkir Mobil</strong></td><td>Rp 10.000</td><td>Rp 10.000</td><td>Per kendaraan</td></tr>\n";
            $html .= "</tbody>\n</table>\n\n";

            $html .= "<p><em>Catatan: Harga tiket masuk dapat berubah sewaktu-waktu tanpa pemberitahuan terlebih dahulu. Disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi destinasi untuk mendapatkan informasi harga terbaru sebelum berkunjung.</em></p>\n\n";

            // Jam operasional
            if (!empty($jam)) {
                $html .= "<p><strong>Jam Operasional:</strong> " . $this->humanize_text($jam) . ". ";
            } else {
                $html .= "<p><strong>Jam Operasional:</strong> Umumnya buka setiap hari mulai pukul 08.00 hingga 17.00 waktu setempat. ";
            }
            $html .= "Waktu <strong>terbaik untuk berkunjung</strong> adalah pada pagi hari antara pukul 08.00-10.00 untuk menghindari keramaian dan menikmati suasana yang lebih tenang. Pada akhir pekan dan hari libur nasional, destinasi ini cenderung lebih ramai sehingga disarankan untuk datang lebih awal.</p>\n\n";
        }

        // ============================================================
        // SECTION: Daya Tarik dan Aktivitas
        // ============================================================
        elseif (preg_match('/(daya tarik|aktivitas|menarik|kegiatan|wahana|spot)/i', $heading_lower)) {
            $aktivitas = $ai_data['AKTIVITAS'] ?? '';
            $daya_tarik = $ai_data['DAYA_TARIK'] ?? '';

            $html .= "<p><strong>{$short_name}</strong> menawarkan beragam daya tarik dan aktivitas menarik yang bisa dinikmati oleh pengunjung dari segala usia. ";
            if (!empty($daya_tarik)) {
                $html .= $this->humanize_text($daya_tarik) . "</p>\n\n";
            } else {
                $html .= "Mulai dari keindahan pemandangan alam yang memukau hingga berbagai wahana dan aktivitas seru, tempat ini menjamin pengalaman liburan yang tak terlupakan.</p>\n\n";
            }

            if (!empty($aktivitas)) {
                $aktivitas_arr = array_filter(array_map('trim', preg_split('/[,;.]/', $aktivitas)));
                if (count($aktivitas_arr) > 2) {
                    $html .= "<p>Berikut beberapa aktivitas menarik yang bisa Anda lakukan saat berkunjung:</p>\n\n<ul>\n";
                    foreach (array_slice($aktivitas_arr, 0, 7) as $akt) {
                        $html .= "<li><strong>" . ucfirst($akt) . "</strong> &ndash; Aktivitas ini menjadi salah satu favorit pengunjung dan wajib dicoba saat berkunjung ke {$short_name}.</li>\n";
                    }
                    $html .= "</ul>\n\n";
                } else {
                    $html .= "<p>" . $this->humanize_text($aktivitas) . " Setiap aktivitas dirancang untuk memberikan pengalaman yang berbeda dan berkesan bagi setiap pengunjung.</p>\n\n";
                }
            } else {
                $html .= "<ul>\n";
                $html .= "<li><strong>Menikmati Pemandangan Alam</strong> &ndash; Nikmati keindahan panorama alam yang memukau, cocok untuk bersantai dan melepas penat dari rutinitas sehari-hari.</li>\n";
                $html .= "<li><strong>Berfoto di Spot Instagramable</strong> &ndash; Tersedia berbagai spot foto menarik yang cocok untuk mengabadikan momen liburan Anda dan dibagikan di media sosial.</li>\n";
                $html .= "<li><strong>Wisata Kuliner Lokal</strong> &ndash; Jangan lewatkan kesempatan untuk mencicipi kuliner khas daerah setempat yang tersedia di sekitar lokasi wisata.</li>\n";
                $html .= "<li><strong>Aktivitas Outdoor</strong> &ndash; Berbagai aktivitas luar ruangan tersedia untuk Anda yang menyukai petualangan dan tantangan.</li>\n";
                $html .= "</ul>\n\n";
            }

            $html .= "<p>Selain aktivitas di atas, pengunjung juga bisa menikmati suasana alam yang asri dan udara segar yang menjadi ciri khas destinasi ini. Bagi pecinta fotografi, tempat ini menyediakan banyak sudut menarik yang bisa dijadikan objek foto dengan latar belakang pemandangan yang menakjubkan.</p>\n\n";
        }

        // ============================================================
        // SECTION: Fasilitas
        // ============================================================
        elseif (preg_match('/(fasilitas|layanan|akomodasi|infrastruktur)/i', $heading_lower)) {
            $fasilitas = $ai_data['FASILITAS'] ?? '';

            $html .= "<p>Untuk menunjang kenyamanan pengunjung, <strong>{$short_name}</strong> telah dilengkapi dengan berbagai fasilitas yang memadai. Pengelola terus berupaya meningkatkan kualitas fasilitas agar pengalaman berkunjung semakin menyenangkan.</p>\n\n";

            if (!empty($fasilitas)) {
                $fas_arr = array_filter(array_map('trim', preg_split('/[,;.]/', $fasilitas)));
                $html .= "<ul>\n";
                foreach (array_slice($fas_arr, 0, 8) as $fas) {
                    $html .= "<li><strong>" . ucfirst(trim($fas)) . "</strong> &ndash; Tersedia dan terawat dengan baik untuk kenyamanan pengunjung.</li>\n";
                }
                $html .= "</ul>\n\n";
            } else {
                $html .= "<ul>\n";
                $html .= "<li><strong>Area Parkir Luas</strong> &ndash; Tersedia lahan parkir yang cukup luas untuk kendaraan roda dua maupun roda empat, sehingga pengunjung tidak perlu khawatir mencari tempat parkir.</li>\n";
                $html .= "<li><strong>Toilet/WC</strong> &ndash; Fasilitas toilet yang bersih dan terawat tersedia di beberapa titik lokasi untuk kenyamanan pengunjung.</li>\n";
                $html .= "<li><strong>Mushola</strong> &ndash; Tersedia mushola untuk pengunjung yang ingin menunaikan ibadah sholat selama berada di lokasi wisata.</li>\n";
                $html .= "<li><strong>Warung Makan</strong> &ndash; Beberapa warung makan dan kedai tersedia di sekitar lokasi, menyajikan berbagai pilihan makanan dan minuman dengan harga terjangkau.</li>\n";
                $html .= "<li><strong>Gazebo/Area Istirahat</strong> &ndash; Tersedia gazebo dan area istirahat yang nyaman untuk bersantai menikmati pemandangan.</li>\n";
                $html .= "<li><strong>Spot Foto</strong> &ndash; Berbagai spot foto instagramable tersedia untuk mengabadikan momen liburan Anda.</li>\n";
                $html .= "</ul>\n\n";
            }

            $html .= "<p><em>Catatan: Ketersediaan dan kondisi fasilitas dapat berbeda tergantung kebijakan pengelola. Disarankan untuk membawa perlengkapan pribadi sebagai antisipasi.</em></p>\n\n";
        }

        // ============================================================
        // SECTION: Tips Berkunjung
        // ============================================================
        elseif (preg_match('/(tips|rekomendasi|saran|panduan|persiapan)/i', $heading_lower)) {
            $tips = $ai_data['TIPS'] ?? '';

            $html .= "<p>Agar kunjungan Anda ke <strong>{$short_name}</strong> semakin menyenangkan dan berkesan, berikut beberapa tips praktis yang bisa diterapkan. {$this->site_name} telah merangkum tips ini berdasarkan pengalaman para pengunjung sebelumnya.</p>\n\n";

            $html .= "<ol>\n";
            if (!empty($tips)) {
                $tips_arr = array_filter(array_map('trim', preg_split('/[,;.]/', $tips)));
                foreach (array_slice($tips_arr, 0, 7) as $tip) {
                    $html .= "<li><strong>" . ucfirst(trim($tip)) . "</strong> &ndash; Hal ini akan sangat membantu untuk membuat pengalaman berkunjung Anda lebih nyaman dan menyenangkan.</li>\n";
                }
            } else {
                $html .= "<li><strong>Datang Lebih Awal</strong> &ndash; Usahakan tiba di lokasi pada pagi hari untuk menghindari keramaian dan mendapatkan pengalaman yang lebih tenang serta nyaman.</li>\n";
                $html .= "<li><strong>Bawa Perlengkapan yang Cukup</strong> &ndash; Siapkan sunblock, topi, kacamata hitam, dan air minum yang cukup, terutama jika berkunjung pada siang hari yang terik.</li>\n";
                $html .= "<li><strong>Gunakan Alas Kaki yang Nyaman</strong> &ndash; Pilih sepatu atau sandal yang nyaman karena Anda mungkin akan banyak berjalan kaki menjelajahi area wisata.</li>\n";
                $html .= "<li><strong>Bawa Kamera atau Smartphone</strong> &ndash; Jangan lupa membawa perangkat untuk mengabadikan momen-momen indah selama berkunjung di berbagai spot foto yang tersedia.</li>\n";
                $html .= "<li><strong>Jaga Kebersihan Lingkungan</strong> &ndash; Selalu buang sampah pada tempatnya dan jaga kelestarian alam sekitar agar destinasi ini tetap indah untuk dikunjungi generasi mendatang.</li>\n";
                $html .= "<li><strong>Cek Informasi Terbaru</strong> &ndash; Sebelum berangkat, pastikan untuk mengecek informasi terbaru mengenai harga tiket, jam operasional, dan kondisi cuaca melalui media sosial resmi atau website pengelola.</li>\n";
                $html .= "<li><strong>Siapkan Uang Tunai</strong> &ndash; Meskipun beberapa tempat sudah menerima pembayaran digital, ada baiknya menyiapkan uang tunai untuk berjaga-jaga, terutama untuk membeli makanan atau oleh-oleh dari pedagang lokal.</li>\n";
            }
            $html .= "</ol>\n\n";

            $html .= "<blockquote><p><strong>Tips Pro:</strong> Untuk mendapatkan pengalaman terbaik, pertimbangkan untuk berkunjung pada hari kerja (Senin-Jumat) di luar musim liburan. Suasana akan jauh lebih tenang dan Anda bisa menikmati destinasi ini dengan lebih leluasa.</p></blockquote>\n\n";
        }

        // ============================================================
        // SECTION: Kuliner
        // ============================================================
        elseif (preg_match('/(kuliner|makanan|restoran|wisata rasa|menu|jajanan)/i', $heading_lower)) {
            $kuliner = $ai_data['KULINER_TERDEKAT'] ?? '';

            $html .= "<p>Berkunjung ke <strong>{$short_name}</strong> tidak lengkap tanpa mencicipi kuliner khas yang tersedia di sekitar lokasi. Beragam pilihan makanan dan minuman siap memanjakan lidah Anda setelah lelah menjelajahi area wisata.</p>\n\n";

            if (!empty($kuliner)) {
                $html .= "<p>" . $this->humanize_text($kuliner) . "</p>\n\n";
            }

            $html .= "<p>Selain kuliner khas, di sekitar lokasi juga tersedia berbagai warung makan dan restoran yang menyajikan menu beragam dengan harga yang bervariasi. Mulai dari makanan ringan sebagai camilan hingga hidangan berat untuk makan siang, semuanya tersedia untuk memenuhi kebutuhan kuliner Anda selama berkunjung. Jangan lupa untuk mencoba oleh-oleh khas daerah yang bisa Anda bawa pulang sebagai kenang-kenangan.</p>\n\n";
        }

        // ============================================================
        // SECTION: Default / Lainnya
        // ============================================================
        else {
            // Coba generate dari AI untuk section yang tidak dikenali
            $ai_content = $this->generate_section_ai($heading, $short_name, $ai_data, $relevant_data);
            if (!empty($ai_content)) {
                $html .= $ai_content;
            } else {
                // Fallback generic
                $html .= "<p>Informasi mengenai <strong>{$heading}</strong> di <strong>{$short_name}</strong> menjadi salah satu aspek penting yang perlu diketahui oleh calon pengunjung. Dengan memahami informasi ini, Anda bisa merencanakan kunjungan dengan lebih baik dan mendapatkan pengalaman yang lebih optimal.</p>\n\n";
                $html .= "<p>Pengelola destinasi ini terus berupaya meningkatkan kualitas layanan dan fasilitas untuk memberikan pengalaman terbaik bagi setiap pengunjung. Berbagai inovasi dan perbaikan dilakukan secara berkala agar destinasi ini tetap menjadi pilihan utama wisatawan.</p>\n\n";
            }
        }

        return $html;
    }

    /**
     * Generate section via AI (untuk section yang tidak dikenali pattern-nya)
     */
    private function generate_section_ai($heading, $short_name, $ai_data, $relevant_data) {
        $prompt = "Tulis 3-4 paragraf informatif tentang \"{$heading}\" untuk destinasi wisata \"{$short_name}\". Setiap paragraf minimal 3 kalimat. Gunakan tag HTML <p>, <strong>, <em>. JANGAN gunakan kata saya/aku. Total minimal 200 kata.";

        if (!empty($relevant_data)) {
            $prompt .= "\n\nData referensi: {$relevant_data}";
        }

        $result = $this->call_ai($prompt);
        if (!empty($result) && str_word_count(strip_tags($result)) > 50) {
            return $this->clean_ai_output($result);
        }
        return '';
    }

    /**
     * STRATEGI 3: Expand artikel per-section jika masih kurang
     */
    private function expand_article_sections($title, $article, $type, $context, $ai_data, &$log) {
        $current_words = str_word_count(strip_tags($article));
        $needed = $this->min_words - $current_words;
        $short_name = $this->extract_short_name($title);

        $log[] = "[Council V4] Perlu tambah ~{$needed} kata...";

        // Tambahkan section FAQ
        $faq_html = $this->generate_faq_section($title, $short_name, $ai_data);
        $article .= $faq_html;

        $current_words = str_word_count(strip_tags($article));

        // Jika masih kurang, tambahkan section "Wisata Sekitar"
        if ($current_words < $this->min_words) {
            $article .= $this->generate_nearby_section($short_name, $ai_data);
        }

        // Jika masih kurang, tambahkan section "Pengalaman Pengunjung"
        $current_words = str_word_count(strip_tags($article));
        if ($current_words < $this->min_words) {
            $article .= $this->generate_visitor_experience_section($short_name);
        }

        return $article;
    }

    /**
     * Generate FAQ section
     */
    private function generate_faq_section($title, $short_name, $ai_data) {
        $harga = $ai_data['HARGA_TIKET'] ?? 'bervariasi tergantung musim dan kebijakan pengelola';
        $jam = $ai_data['JAM_OPERASIONAL'] ?? 'umumnya buka setiap hari dari pagi hingga sore';

        $html = "<h2>Pertanyaan yang Sering Diajukan</h2>\n\n";

        $html .= "<h3>Berapa harga tiket masuk {$short_name}?</h3>\n";
        $html .= "<p>Harga <strong>tiket masuk</strong> {$short_name} {$harga}. Disarankan untuk menghubungi pihak pengelola atau mengecek media sosial resmi untuk mendapatkan informasi harga <strong>terbaru</strong> sebelum berkunjung, karena harga dapat berubah sewaktu-waktu terutama pada musim liburan.</p>\n\n";

        $html .= "<h3>Kapan waktu terbaik untuk berkunjung ke {$short_name}?</h3>\n";
        $html .= "<p>Waktu <strong>terbaik</strong> untuk berkunjung adalah pada pagi hari antara pukul 08.00-10.00 atau sore hari menjelang senja. Hindari berkunjung pada hari libur nasional jika Anda ingin menghindari keramaian. Musim kemarau (April-Oktober) umumnya menjadi waktu yang ideal untuk berkunjung karena cuaca yang lebih bersahabat.</p>\n\n";

        $html .= "<h3>Apa saja fasilitas yang tersedia di {$short_name}?</h3>\n";
        $html .= "<p>Umumnya tersedia <strong>fasilitas</strong> dasar seperti <strong>area parkir</strong> yang luas, toilet bersih, mushola, dan warung makan. Beberapa destinasi juga menyediakan gazebo, spot foto, dan area bermain anak. Fasilitas dapat berbeda tergantung kebijakan pengelola dan perkembangan terbaru.</p>\n\n";

        $html .= "<h3>Bagaimana cara menuju {$short_name}?</h3>\n";
        $html .= "<p><strong>Lokasi</strong> ini dapat dijangkau dengan kendaraan pribadi maupun transportasi umum. Gunakan aplikasi navigasi seperti <em>Google Maps</em> atau <em>Waze</em> untuk panduan rute <strong>terbaik</strong>. Layanan ojek online seperti Gojek dan Grab juga tersedia untuk kemudahan akses bagi wisatawan yang tidak membawa kendaraan sendiri.</p>\n\n";

        $html .= "<h3>Apakah {$short_name} cocok untuk liburan keluarga?</h3>\n";
        $html .= "<p><strong>Destinasi</strong> ini sangat cocok untuk liburan keluarga. Atmosfer yang nyaman, pemandangan yang indah, dan <strong>fasilitas</strong> yang memadai menjadikannya pilihan tepat untuk menghabiskan waktu berkualitas bersama orang-orang tercinta. Anak-anak juga bisa menikmati berbagai aktivitas yang tersedia di area wisata.</p>\n\n";

        return $html;
    }

    /**
     * Generate section wisata sekitar
     */
    private function generate_nearby_section($short_name, $ai_data) {
        $html = "<h2>Wisata Terdekat dari {$short_name}</h2>\n\n";
        $html .= "<p>Selain mengunjungi <strong>{$short_name}</strong>, Anda juga bisa menjelajahi beberapa destinasi wisata terdekat yang tidak kalah menarik. Menggabungkan kunjungan ke beberapa tempat wisata dalam satu perjalanan bisa menjadi cara yang efisien untuk memaksimalkan pengalaman liburan Anda.</p>\n\n";
        $html .= "<p>Daerah sekitar {$short_name} dikenal memiliki banyak potensi wisata yang beragam, mulai dari wisata alam, wisata budaya, hingga wisata kuliner. Dengan merencanakan itinerary yang baik, Anda bisa mengunjungi beberapa destinasi dalam satu hari dan mendapatkan pengalaman liburan yang lebih lengkap dan berkesan.</p>\n\n";
        return $html;
    }

    /**
     * Generate section pengalaman pengunjung
     */
    private function generate_visitor_experience_section($short_name) {
        $html = "<h2>Pengalaman Pengunjung di {$short_name}</h2>\n\n";
        $html .= "<p>Banyak pengunjung yang memberikan ulasan positif setelah berkunjung ke <strong>{$short_name}</strong>. Keindahan alam, kebersihan lingkungan, dan keramahan pengelola menjadi poin-poin yang sering mendapat apresiasi dari para wisatawan.</p>\n\n";
        $html .= "<p>Beberapa pengunjung merekomendasikan untuk mengalokasikan waktu setidaknya 2-3 jam agar bisa menikmati seluruh area wisata dengan santai tanpa terburu-buru. Bagi pecinta fotografi, tempat ini menjadi surga tersendiri dengan berbagai sudut yang <em>instagramable</em> dan pemandangan yang memukau di setiap sisinya.</p>\n\n";
        return $html;
    }

    /**
     * Generate default sections jika blueprint kosong
     */
    private function generate_default_sections($title, $short_name, $type, $ai_data, $context) {
        $html = '';

        $default_sections = array(
            array('heading' => 'Mengenal ' . $short_name . ' Lebih Dekat', 'has_table' => false, 'has_list' => false),
            array('heading' => 'Lokasi dan Cara Menuju ' . $short_name, 'has_table' => false, 'has_list' => true),
            array('heading' => 'Harga Tiket Masuk dan Jam Operasional', 'has_table' => true, 'has_list' => false),
            array('heading' => 'Daya Tarik dan Aktivitas Menarik', 'has_table' => false, 'has_list' => true),
            array('heading' => 'Fasilitas yang Tersedia', 'has_table' => false, 'has_list' => true),
            array('heading' => 'Tips Berkunjung agar Lebih Menyenangkan', 'has_table' => false, 'has_list' => true),
        );

        foreach ($default_sections as $section) {
            $html .= "<h2>{$section['heading']}</h2>\n\n";
            $relevant = $this->get_relevant_data_for_section($section['heading'], $ai_data, $context);
            $html .= $this->generate_section_content($section['heading'], $relevant, $ai_data, $section['has_table'], $section['has_list'], $short_name, $type);
        }

        return $html;
    }

    /**
     * Parse full article HTML into sections
     */
    private function parse_article_into_sections($html) {
        $result = array(
            'introduction' => '',
            'sections'     => array(),
            'conclusion'   => '',
        );

        // Split by H2
        $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        // First part is introduction
        if (!empty($parts[0])) {
            $result['introduction'] = trim($parts[0]);
        }

        // Process H2 sections
        for ($i = 1; $i < count($parts); $i += 2) {
            $heading_html = $parts[$i] ?? '';
            $content = trim($parts[$i + 1] ?? '');

            preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $heading_html, $m);
            $heading = strip_tags($m[1] ?? '');

            if (stripos($heading, 'kesimpulan') !== false || stripos($heading, 'penutup') !== false) {
                $result['conclusion'] = $content;
            } else {
                $result['sections'][] = array(
                    'heading' => $heading,
                    'content' => $content,
                    'format'  => 'paragraph',
                );
            }
        }

        return $result;
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function prepare_full_context($kg) {
        $context = '';

        $ai_data = $kg['ai_analysis'] ?? array();
        foreach ($ai_data as $key => $val) {
            if (!empty($val)) {
                $context .= strtoupper($key) . ": " . $val . "\n";
            }
        }

        if (!empty($kg['key_facts'])) {
            $context .= "\nFAKTA PENTING:\n";
            foreach (array_slice($kg['key_facts'], 0, 15) as $fact) {
                $context .= "- " . $fact . "\n";
            }
        }

        if (!empty($kg['content_map'])) {
            $context .= "\nDATA DARI SUMBER:\n";
            foreach (array_slice($kg['content_map'], 0, 5) as $cm) {
                $text = $cm['text'] ?? '';
                if (strlen($text) > 1500) $text = substr($text, 0, 1500);
                $context .= "--- " . ($cm['source'] ?? 'Unknown') . " ---\n" . $text . "\n\n";
            }
        }

        return $context;
    }

    private function get_relevant_data_for_section($heading, $ai_data, $context) {
        $heading_lower = strtolower($heading);
        $relevant = '';

        $mapping = array(
            'sejarah|mengenal|tentang|review' => array('RINGKASAN_TOPIK', 'SEJARAH', 'FAKTA_UNIK', 'DAYA_TARIK'),
            'lokasi|cara|menuju|akses|rute' => array('LOKASI_LENGKAP'),
            'harga|tiket|jam|operasional|biaya' => array('HARGA_TIKET', 'JAM_OPERASIONAL'),
            'fasilitas|layanan' => array('FASILITAS'),
            'aktivitas|daya tarik|menarik|wahana' => array('AKTIVITAS', 'DAYA_TARIK'),
            'kuliner|makanan|restoran' => array('KULINER_TERDEKAT'),
            'tips|rekomendasi|saran' => array('TIPS'),
        );

        foreach ($mapping as $pattern => $keys) {
            if (preg_match('/(' . $pattern . ')/i', $heading_lower)) {
                foreach ($keys as $key) {
                    if (!empty($ai_data[$key])) {
                        $relevant .= $ai_data[$key] . "\n";
                    }
                }
                break;
            }
        }

        return $relevant;
    }

    private function extract_short_name($title) {
        $title = preg_replace('/\b(panduan|lengkap|terbaru|info|wisata|destinasi|kuliner|hotel|review|rekomendasi|\d{4})\b/i', '', $title);
        return ucwords(trim(preg_replace('/\s+/', ' ', $title)));
    }

    private function humanize_text($text) {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        // Capitalize first letter
        $text = ucfirst($text);
        // Remove trailing period if present (we'll add our own)
        $text = rtrim($text, '.');
        return $text;
    }

    private function clean_ai_output($text) {
        // Remove markdown
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        // Convert markdown bold/italic to HTML
        $text = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<![<\/])\*([^*]+)\*/', '<em>$1</em>', $text);

        // Wrap plain text in paragraphs if needed
        if (strpos($text, '<p>') === false && strpos($text, '<h2') === false) {
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
        if (!empty($result) && strlen($result) > 100) return $result;

        // Try OpenAI if configured
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
            'timeout' => 90,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-vqd-4' => $vqd,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'body' => wp_json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
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
            'timeout' => 120,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => wp_json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'Kamu adalah penulis konten profesional senior untuk media wisata Indonesia. Tulis artikel yang SANGAT LENGKAP, PANJANG, dan INFORMATIF dalam bahasa Indonesia. MINIMAL 1000 kata.'),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'temperature' => 0.75,
                'max_tokens' => 8000,
            )),
            'sslverify' => false,
        ));
        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
