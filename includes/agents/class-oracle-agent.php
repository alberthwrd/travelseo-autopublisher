<?php
/**
 * Project Hyperion - Agent #1: The Oracle
 * Quantum Research & Entity Extraction
 *
 * Tugas: Melakukan riset mendalam dari top hasil pencarian Google,
 * mengekstrak entitas, fakta, sentimen, dan membangun Knowledge Graph.
 */

if (!defined('ABSPATH')) exit;

class TSA_Oracle_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Jalankan riset mendalam untuk sebuah judul
     */
    public function research($title) {
        $log = array();
        $log[] = '[Oracle] Memulai Quantum Research untuk: "' . $title . '"';

        // Step 1: Generate search queries
        $queries = $this->generate_search_queries($title);
        $log[] = '[Oracle] Generated ' . count($queries) . ' search queries';

        // Step 2: Scrape top results
        $raw_data = $this->scrape_multiple_sources($queries, $log);
        $log[] = '[Oracle] Berhasil scrape ' . count($raw_data) . ' sumber';

        // Step 3: Extract entities & build knowledge graph
        $knowledge_graph = $this->build_knowledge_graph($title, $raw_data, $log);

        // Step 4: Enrich with AI analysis
        $enriched = $this->enrich_with_ai($title, $knowledge_graph, $log);

        return array(
            'knowledge_graph' => $enriched,
            'raw_sources'     => $raw_data,
            'log'             => $log,
        );
    }

    /**
     * Generate multiple search queries dari judul
     */
    private function generate_search_queries($title) {
        $clean = strtolower(trim($title));
        $queries = array();

        // Query utama
        $queries[] = $title;

        // Variasi query SEO
        $queries[] = $title . ' panduan lengkap';
        $queries[] = $title . ' harga tiket jam buka';
        $queries[] = $title . ' tips berkunjung';
        $queries[] = $title . ' review pengalaman';
        $queries[] = $title . ' lokasi cara menuju';
        $queries[] = $title . ' fasilitas aktivitas';
        $queries[] = $title . ' kuliner terdekat';

        return array_slice($queries, 0, 8);
    }

    /**
     * Scrape multiple sources dari Google search results
     */
    private function scrape_multiple_sources($queries, &$log) {
        $all_data = array();
        $scraped_urls = array();
        $max_sources = 15;

        foreach ($queries as $qi => $query) {
            if (count($all_data) >= $max_sources) break;

            $log[] = '[Oracle] Searching: "' . $query . '"';

            // Google search scrape
            $search_url = 'https://www.google.com/search?q=' . urlencode($query) . '&hl=id&gl=id&num=10';
            $search_html = $this->fetch_url($search_url);

            if (empty($search_html)) {
                // Fallback: DuckDuckGo
                $search_url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
                $search_html = $this->fetch_url($search_url);
            }

            if (empty($search_html)) {
                $log[] = '[Oracle] Search gagal untuk query: "' . $query . '"';
                continue;
            }

            // Extract URLs from search results
            $urls = $this->extract_urls_from_search($search_html);
            $log[] = '[Oracle] Ditemukan ' . count($urls) . ' URL dari query #' . ($qi + 1);

            foreach ($urls as $url) {
                if (count($all_data) >= $max_sources) break;
                if (in_array($url, $scraped_urls)) continue;
                if ($this->is_blocked_domain($url)) continue;

                $scraped_urls[] = $url;
                $content = $this->scrape_article($url);

                if (!empty($content) && strlen($content['text']) > 200) {
                    $all_data[] = $content;
                    $log[] = '[Oracle] ✓ Scraped: ' . $this->truncate($content['title'], 60) . ' (' . strlen($content['text']) . ' chars)';
                }
            }
        }

        // Jika scraping gagal, gunakan fallback data
        if (count($all_data) < 3) {
            $log[] = '[Oracle] Sumber kurang dari 3, menggunakan AI fallback...';
            $all_data = array_merge($all_data, $this->generate_fallback_data($queries[0]));
        }

        return $all_data;
    }

    /**
     * Extract URLs dari halaman hasil pencarian
     */
    private function extract_urls_from_search($html) {
        $urls = array();

        // Google format
        if (preg_match_all('/href="\/url\?q=([^"&]+)/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = urldecode($url);
                if ($this->is_valid_article_url($url)) {
                    $urls[] = $url;
                }
            }
        }

        // Direct link format
        if (preg_match_all('/href="(https?:\/\/[^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->is_valid_article_url($url) && !in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
        }

        // DuckDuckGo format
        if (preg_match_all('/href="(https?:\/\/[^"]+)"[^>]*class="result__a"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if (!in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
        }

        return array_slice(array_unique($urls), 0, 20);
    }

    /**
     * Scrape konten artikel dari URL
     */
    private function scrape_article($url) {
        $html = $this->fetch_url($url);
        if (empty($html)) return null;

        $result = array(
            'url'   => $url,
            'title' => '',
            'text'  => '',
            'meta'  => '',
            'headings' => array(),
            'entities' => array(),
        );

        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $result['title'] = html_entity_decode(strip_tags(trim($m[1])), ENT_QUOTES, 'UTF-8');
        }

        // Extract meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/si', $html, $m)) {
            $result['meta'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        // Extract headings
        if (preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h\1>/si', $html, $hm)) {
            foreach ($hm[2] as $i => $heading) {
                $result['headings'][] = array(
                    'level' => (int)$hm[1][$i],
                    'text'  => strip_tags(trim($heading)),
                );
            }
        }

        // Extract main content
        $text = $this->extract_main_content($html);
        $result['text'] = $text;

        // Extract entities dari konten
        $result['entities'] = $this->extract_entities_from_text($text);

        return $result;
    }

    /**
     * Extract konten utama dari HTML
     */
    private function extract_main_content($html) {
        // Remove script, style, nav, footer, sidebar
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Try to find article/main content
        $content = '';
        $selectors = array(
            '/<article[^>]*>(.*?)<\/article>/si',
            '/<div[^>]*class="[^"]*(?:content|article|post|entry)[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<main[^>]*>(.*?)<\/main>/si',
        );

        foreach ($selectors as $selector) {
            if (preg_match($selector, $html, $m)) {
                $content = $m[1];
                break;
            }
        }

        if (empty($content)) {
            // Fallback: ambil body
            if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $m)) {
                $content = $m[1];
            } else {
                $content = $html;
            }
        }

        // Clean HTML tags tapi pertahankan struktur
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n\n", $content);
        $content = preg_replace('/<\/h[1-6]>/i', "\n\n", $content);
        $content = preg_replace('/<\/li>/i', "\n", $content);
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Clean whitespace
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        // Limit to reasonable size
        if (strlen($content) > 15000) {
            $content = substr($content, 0, 15000);
        }

        return $content;
    }

    /**
     * Extract entitas dari teks
     */
    private function extract_entities_from_text($text) {
        $entities = array(
            'prices'    => array(),
            'times'     => array(),
            'locations' => array(),
            'contacts'  => array(),
            'ratings'   => array(),
            'facts'     => array(),
        );

        // Harga (Rp, IDR)
        if (preg_match_all('/(?:Rp\.?\s*|IDR\s*)[\d.,]+(?:\s*(?:ribu|rb|juta|jt))?/i', $text, $m)) {
            $entities['prices'] = array_unique(array_slice($m[0], 0, 10));
        }

        // Jam operasional
        if (preg_match_all('/\d{1,2}[.:]\d{2}\s*(?:-|sampai|hingga|s\.?d\.?)\s*\d{1,2}[.:]\d{2}\s*(?:WIB|WITA|WIT)?/i', $text, $m)) {
            $entities['times'] = array_unique(array_slice($m[0], 0, 5));
        }

        // Hari operasional
        if (preg_match_all('/(?:Senin|Selasa|Rabu|Kamis|Jumat|Sabtu|Minggu)\s*(?:-|sampai|hingga|s\.?d\.?)\s*(?:Senin|Selasa|Rabu|Kamis|Jumat|Sabtu|Minggu)/i', $text, $m)) {
            $entities['times'] = array_merge($entities['times'], array_unique($m[0]));
        }

        // Lokasi (alamat)
        if (preg_match_all('/(?:Jl\.?|Jalan)\s+[A-Z][^\n,]{5,60}/i', $text, $m)) {
            $entities['locations'] = array_unique(array_slice($m[0], 0, 5));
        }

        // Kabupaten/Kota/Kecamatan
        if (preg_match_all('/(?:Kabupaten|Kota|Kecamatan|Desa|Kelurahan)\s+[A-Z][a-zA-Z\s]{2,30}/i', $text, $m)) {
            $entities['locations'] = array_merge($entities['locations'], array_unique($m[0]));
        }

        // Nomor telepon
        if (preg_match_all('/(?:\+62|0)\d{2,4}[-.\s]?\d{3,4}[-.\s]?\d{3,4}/i', $text, $m)) {
            $entities['contacts'] = array_unique(array_slice($m[0], 0, 5));
        }

        // Rating
        if (preg_match_all('/\d[.,]\d\s*\/\s*\d|\d[.,]\d\s*(?:bintang|stars?)/i', $text, $m)) {
            $entities['ratings'] = array_unique(array_slice($m[0], 0, 5));
        }

        // Fakta kunci (kalimat yang mengandung angka penting)
        $sentences = preg_split('/[.!?]+/', $text);
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 30 && strlen($sentence) < 200) {
                if (preg_match('/\d+/', $sentence) && preg_match('/(?:meter|km|hektar|tahun|abad|luas|tinggi|panjang|jumlah|kapasitas)/i', $sentence)) {
                    $entities['facts'][] = $sentence;
                }
            }
        }
        $entities['facts'] = array_slice($entities['facts'], 0, 15);

        return $entities;
    }

    /**
     * Build Knowledge Graph dari semua data
     */
    private function build_knowledge_graph($title, $raw_data, &$log) {
        $kg = array(
            'topic'       => $title,
            'type'        => $this->detect_content_type($title),
            'summary'     => '',
            'key_facts'   => array(),
            'prices'      => array(),
            'times'       => array(),
            'locations'   => array(),
            'contacts'    => array(),
            'ratings'     => array(),
            'headings'    => array(),
            'sources'     => array(),
            'content_map' => array(),
        );

        // Aggregate semua entitas
        foreach ($raw_data as $source) {
            $kg['sources'][] = array(
                'url'   => $source['url'] ?? '',
                'title' => $source['title'] ?? '',
            );

            if (!empty($source['headings'])) {
                foreach ($source['headings'] as $h) {
                    $kg['headings'][] = $h['text'];
                }
            }

            if (!empty($source['entities'])) {
                $ent = $source['entities'];
                $kg['prices']    = array_merge($kg['prices'], $ent['prices'] ?? array());
                $kg['times']     = array_merge($kg['times'], $ent['times'] ?? array());
                $kg['locations'] = array_merge($kg['locations'], $ent['locations'] ?? array());
                $kg['contacts']  = array_merge($kg['contacts'], $ent['contacts'] ?? array());
                $kg['ratings']   = array_merge($kg['ratings'], $ent['ratings'] ?? array());
                $kg['key_facts'] = array_merge($kg['key_facts'], $ent['facts'] ?? array());
            }

            // Build content map (ringkasan per sumber)
            if (!empty($source['text'])) {
                $kg['content_map'][] = array(
                    'source' => $source['title'] ?? $source['url'],
                    'text'   => $this->truncate($source['text'], 3000),
                );
            }
        }

        // Deduplicate
        $kg['prices']    = array_values(array_unique($kg['prices']));
        $kg['times']     = array_values(array_unique($kg['times']));
        $kg['locations'] = array_values(array_unique($kg['locations']));
        $kg['contacts']  = array_values(array_unique($kg['contacts']));
        $kg['ratings']   = array_values(array_unique($kg['ratings']));
        $kg['headings']  = array_values(array_unique($kg['headings']));
        $kg['key_facts'] = array_values(array_unique(array_slice($kg['key_facts'], 0, 20)));

        $log[] = '[Oracle] Knowledge Graph: ' . count($kg['key_facts']) . ' fakta, ' . count($kg['prices']) . ' harga, ' . count($kg['headings']) . ' heading';

        return $kg;
    }

    /**
     * Enrich Knowledge Graph dengan AI analysis
     */
    private function enrich_with_ai($title, $kg, &$log) {
        $log[] = '[Oracle] Enriching Knowledge Graph with AI...';

        // Siapkan context dari semua content_map
        $context_text = '';
        foreach ($kg['content_map'] as $cm) {
            $context_text .= "=== SUMBER: " . $cm['source'] . " ===\n";
            $context_text .= $cm['text'] . "\n\n";
        }

        // Limit context
        if (strlen($context_text) > 30000) {
            $context_text = substr($context_text, 0, 30000);
        }

        $prompt = "Kamu adalah seorang jurnalis riset senior yang ahli dalam topik wisata, destinasi, dan kuliner Indonesia.

TOPIK: {$title}
TIPE KONTEN: {$kg['type']}

Berikut adalah data mentah dari berbagai sumber yang telah dikumpulkan:

{$context_text}

ENTITAS YANG DITEMUKAN:
- Harga: " . implode(', ', array_slice($kg['prices'], 0, 5)) . "
- Jam: " . implode(', ', array_slice($kg['times'], 0, 3)) . "
- Lokasi: " . implode(', ', array_slice($kg['locations'], 0, 3)) . "
- Fakta: " . implode('; ', array_slice($kg['key_facts'], 0, 5)) . "

TUGAS:
Analisis semua data di atas dan hasilkan laporan riset terstruktur dalam format berikut (gunakan separator |||):

RINGKASAN_TOPIK|||[Tulis ringkasan komprehensif 3-5 kalimat tentang topik ini]
SEJARAH|||[Tulis sejarah/latar belakang 2-3 kalimat, jika tidak ada tulis 'Tidak tersedia']
LOKASI_LENGKAP|||[Alamat lengkap dan cara menuju lokasi]
HARGA_TIKET|||[Informasi harga tiket lengkap, jika tidak ada tulis 'Gratis' atau 'Hubungi pengelola']
JAM_OPERASIONAL|||[Jam buka dan hari operasional]
FASILITAS|||[Daftar fasilitas yang tersedia, pisahkan dengan koma]
AKTIVITAS|||[Daftar aktivitas yang bisa dilakukan, pisahkan dengan koma]
DAYA_TARIK|||[3-5 daya tarik utama, pisahkan dengan koma]
KULINER_TERDEKAT|||[Kuliner khas di sekitar lokasi]
TIPS|||[5-7 tips berkunjung, pisahkan dengan koma]
FAKTA_UNIK|||[3-5 fakta unik atau menarik]
KEYWORD_LSI|||[10-15 keyword LSI yang relevan, pisahkan dengan koma]

Pastikan semua informasi akurat berdasarkan data yang diberikan. Jika data tidak tersedia, berikan estimasi terbaik berdasarkan pengetahuanmu.";

        $ai_response = $this->call_ai($prompt);

        if (!empty($ai_response)) {
            $parsed = $this->parse_oracle_response($ai_response);
            $kg['ai_analysis'] = $parsed;
            $log[] = '[Oracle] ✓ AI enrichment berhasil - ' . count($parsed) . ' data points';
        } else {
            // Fallback: generate basic analysis
            $kg['ai_analysis'] = $this->generate_basic_analysis($title, $kg);
            $log[] = '[Oracle] AI enrichment gagal, menggunakan basic analysis';
        }

        return $kg;
    }

    /**
     * Parse response dari Oracle AI
     */
    private function parse_oracle_response($response) {
        $result = array();
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '|||') !== false) {
                $parts = explode('|||', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key) && !empty($value)) {
                        $result[$key] = $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Generate basic analysis sebagai fallback
     */
    private function generate_basic_analysis($title, $kg) {
        $type = $kg['type'];
        $analysis = array();

        $analysis['RINGKASAN_TOPIK'] = "{$title} merupakan salah satu destinasi yang menarik untuk dikunjungi. Tempat ini menawarkan pengalaman yang unik dan berbeda dari destinasi lainnya, menjadikannya pilihan tepat untuk mengisi waktu liburan.";

        $analysis['SEJARAH'] = "Destinasi ini memiliki sejarah yang menarik dan telah menjadi bagian penting dari warisan budaya daerah setempat.";

        if (!empty($kg['locations'])) {
            $analysis['LOKASI_LENGKAP'] = implode(', ', array_slice($kg['locations'], 0, 3));
        } else {
            $analysis['LOKASI_LENGKAP'] = "Silakan gunakan Google Maps untuk menemukan lokasi {$title}.";
        }

        if (!empty($kg['prices'])) {
            $analysis['HARGA_TIKET'] = implode(', ', array_slice($kg['prices'], 0, 5));
        } else {
            $analysis['HARGA_TIKET'] = "Hubungi pengelola untuk informasi harga terbaru.";
        }

        if (!empty($kg['times'])) {
            $analysis['JAM_OPERASIONAL'] = implode(', ', array_slice($kg['times'], 0, 3));
        } else {
            $analysis['JAM_OPERASIONAL'] = "Buka setiap hari, silakan hubungi pengelola untuk jam operasional terkini.";
        }

        $analysis['FASILITAS'] = "Area parkir, toilet, mushola, warung makan, gazebo, spot foto";
        $analysis['AKTIVITAS'] = "Berfoto, bersantai, menikmati pemandangan, kuliner lokal";
        $analysis['DAYA_TARIK'] = "Keindahan alam, suasana yang tenang, spot foto instagramable";
        $analysis['KULINER_TERDEKAT'] = "Berbagai kuliner khas daerah tersedia di sekitar lokasi";
        $analysis['TIPS'] = "Datang pagi hari untuk menghindari keramaian, bawa kamera, gunakan sunblock, bawa bekal air minum, pakai alas kaki nyaman, jaga kebersihan";
        $analysis['FAKTA_UNIK'] = "Destinasi ini semakin populer di kalangan wisatawan domestik maupun mancanegara";
        $analysis['KEYWORD_LSI'] = "{$title} terbaru, wisata {$title}, tiket masuk {$title}, jam buka {$title}, lokasi {$title}, review {$title}";

        return $analysis;
    }

    /**
     * Generate fallback data jika scraping gagal
     */
    private function generate_fallback_data($query) {
        $fallback = array();

        // Gunakan AI untuk generate data dasar
        $prompt = "Kamu adalah ahli wisata Indonesia. Berikan informasi lengkap tentang: {$query}

Tulis dalam format paragraf yang informatif, mencakup:
1. Deskripsi umum dan daya tarik utama
2. Lokasi dan cara menuju
3. Harga tiket masuk (estimasi jika tidak tahu pasti)
4. Jam operasional
5. Fasilitas yang tersedia
6. Aktivitas yang bisa dilakukan
7. Tips berkunjung
8. Kuliner khas terdekat

Tulis minimal 500 kata dalam bahasa Indonesia yang natural.";

        $response = $this->call_ai($prompt);

        if (!empty($response)) {
            $fallback[] = array(
                'url'      => 'ai-generated',
                'title'    => 'AI Research: ' . $query,
                'text'     => $response,
                'meta'     => '',
                'headings' => array(),
                'entities' => $this->extract_entities_from_text($response),
            );
        }

        return $fallback;
    }

    /**
     * Detect content type dari judul
     */
    private function detect_content_type($title) {
        $title_lower = strtolower($title);

        $types = array(
            'destinasi' => array('pantai', 'gunung', 'danau', 'air terjun', 'taman', 'kebun', 'hutan', 'pulau', 'goa', 'curug', 'candi', 'pura', 'museum', 'wisata', 'tempat', 'destinasi', 'objek'),
            'kuliner'   => array('kuliner', 'makanan', 'restoran', 'cafe', 'kafe', 'warung', 'rumah makan', 'nasi', 'soto', 'bakso', 'mie', 'sate', 'rendang', 'gudeg'),
            'hotel'     => array('hotel', 'resort', 'villa', 'penginapan', 'homestay', 'glamping', 'cottage', 'hostel'),
            'aktivitas' => array('snorkeling', 'diving', 'rafting', 'hiking', 'trekking', 'camping', 'surfing', 'paragliding'),
        );

        foreach ($types as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($title_lower, $kw) !== false) {
                    return $type;
                }
            }
        }

        return 'destinasi';
    }

    /**
     * Fetch URL dengan timeout dan error handling
     */
    private function fetch_url($url) {
        $args = array(
            'timeout'     => 8,
            'redirection' => 3,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'     => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            ),
            'sslverify'   => false,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return '';
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Call AI (prioritas: free/tanpa API, fallback: OpenAI)
     */
    private function call_ai($prompt) {
        // Prioritas 1: Built-in processing (tanpa API)
        $result = $this->call_free_ai($prompt);
        if (!empty($result)) return $result;

        // Prioritas 2: OpenAI API (jika dikonfigurasi)
        $api_key = get_option('tsa_openai_api_key', '');
        if (!empty($api_key)) {
            return $this->call_openai($prompt, $api_key);
        }

        return '';
    }

    /**
     * Call free AI via web scraping
     */
    private function call_free_ai($prompt) {
        // Coba DuckDuckGo AI Chat
        $result = $this->call_duckduckgo_ai($prompt);
        if (!empty($result)) return $result;

        return '';
    }

    /**
     * DuckDuckGo AI Chat (free, no API key)
     */
    private function call_duckduckgo_ai($prompt) {
        // Step 1: Get vqd token
        $token_response = wp_remote_get('https://duckduckgo.com/duckchat/v1/status', array(
            'timeout' => 10,
            'headers' => array(
                'x-vqd-accept' => '1',
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'sslverify' => false,
        ));

        if (is_wp_error($token_response)) return '';

        $vqd = wp_remote_retrieve_header($token_response, 'x-vqd-4');
        if (empty($vqd)) return '';

        // Step 2: Send chat request
        $chat_response = wp_remote_post('https://duckduckgo.com/duckchat/v1/chat', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-vqd-4'     => $vqd,
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'body' => wp_json_encode(array(
                'model'    => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
            'sslverify' => false,
        ));

        if (is_wp_error($chat_response)) return '';

        $body = wp_remote_retrieve_body($chat_response);

        // Parse streaming response
        $result = '';
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;
                $json = json_decode($data, true);
                if (isset($json['message'])) {
                    $result .= $json['message'];
                }
            }
        }

        return $result;
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($prompt, $api_key) {
        $model = get_option('tsa_openai_model', 'gpt-4o-mini');
        $base_url = get_option('tsa_openai_base_url', 'https://api.openai.com/v1');

        $response = wp_remote_post($base_url . '/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => wp_json_encode(array(
                'model'       => $model,
                'messages'    => array(
                    array('role' => 'system', 'content' => 'Kamu adalah ahli riset wisata dan destinasi Indonesia. Jawab dalam bahasa Indonesia yang natural dan informatif.'),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'temperature' => 0.7,
                'max_tokens'  => 4000,
            )),
            'sslverify' => false,
        ));

        if (is_wp_error($response)) return '';

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Helper: Check if URL is valid article
     */
    private function is_valid_article_url($url) {
        if (empty($url)) return false;
        if (strpos($url, 'http') !== 0) return false;
        if (strpos($url, 'google.com') !== false) return false;
        if (strpos($url, 'youtube.com') !== false) return false;
        if (strpos($url, 'facebook.com') !== false) return false;
        if (strpos($url, 'instagram.com') !== false) return false;
        if (strpos($url, 'twitter.com') !== false) return false;
        if (preg_match('/\.(jpg|jpeg|png|gif|pdf|mp4|mp3)$/i', $url)) return false;
        return true;
    }

    /**
     * Helper: Check if domain is blocked
     */
    private function is_blocked_domain($url) {
        $blocked = array('google.com', 'youtube.com', 'facebook.com', 'instagram.com', 'twitter.com', 'tiktok.com', 'pinterest.com');
        foreach ($blocked as $domain) {
            if (strpos($url, $domain) !== false) return true;
        }
        return false;
    }

    /**
     * Helper: Truncate text
     */
    private function truncate($text, $length = 100) {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }
}
