<?php
/**
 * Research Agent V3 - Fixed Stuck Issue
 * 
 * Agent untuk melakukan riset dan scraping data dari berbagai sumber.
 * Versi ini memperbaiki masalah stuck dengan timeout handling yang lebih baik.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Research_Agent {

    /**
     * Timeout untuk setiap HTTP request (dalam detik)
     */
    const REQUEST_TIMEOUT = 12;

    /**
     * Maksimal sumber yang akan di-scrape
     */
    const MAX_SOURCES = 8;

    /**
     * Job ID
     */
    private $job_id;

    /**
     * Job title/topic
     */
    private $title;

    /**
     * Job settings
     */
    private $settings;

    /**
     * Research pack
     */
    private $research_pack;

    /**
     * Constructor
     */
    public function __construct( $job_id, $title, $settings = array() ) {
        $this->job_id = $job_id;
        $this->title = $title;
        $this->settings = $settings;
        $this->init_research_pack();
    }

    /**
     * Initialize research pack
     */
    private function init_research_pack() {
        $this->research_pack = array(
            'title'           => $this->title,
            'content_type'    => $this->detect_content_type(),
            'timestamp'       => current_time( 'mysql' ),
            'sources'         => array(),
            'raw_texts'       => array(),
            'combined_text'   => '',
            'extracted_info'  => array(),
            'keywords'        => array(),
            'status'          => 'processing',
            'source_count'    => 0,
            'total_chars'     => 0,
        );
    }

    /**
     * Detect content type dari title
     */
    private function detect_content_type() {
        $title_lower = strtolower( $this->title );
        
        $types = array(
            'kuliner'   => array( 'makanan', 'kuliner', 'restoran', 'cafe', 'kafe', 'warung', 'nasi', 'mie', 'sate', 'bakso', 'soto' ),
            'hotel'     => array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel', 'cottage', 'glamping' ),
            'aktivitas' => array( 'rafting', 'diving', 'snorkeling', 'hiking', 'trekking', 'camping', 'surfing', 'tour' ),
            'destinasi' => array( 'pantai', 'gunung', 'danau', 'air terjun', 'taman', 'museum', 'candi', 'pura', 'wisata', 'kolam renang', 'waterpark' ),
        );
        
        foreach ( $types as $type => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $title_lower, $keyword ) !== false ) {
                    return $type;
                }
            }
        }
        
        return 'umum';
    }

    /**
     * Run the research process
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Research Agent V3: Memulai riset untuk "' . $this->title . '"' );
        tsa_update_job( $this->job_id, array( 'status' => 'researching' ) );

        // Step 1: Get source URLs
        tsa_log_job( $this->job_id, 'Research Agent: Mencari sumber referensi...' );
        $sources = $this->get_source_urls();
        
        tsa_log_job( $this->job_id, 'Research Agent: Ditemukan ' . count( $sources ) . ' sumber' );

        // Step 2: Scrape each source with timeout handling
        tsa_log_job( $this->job_id, 'Research Agent: Mulai scraping...' );
        $successful_scrapes = 0;
        
        foreach ( $sources as $index => $source ) {
            $url = is_array( $source ) ? $source['url'] : $source;
            $short_url = substr( $url, 0, 50 ) . '...';
            
            tsa_log_job( $this->job_id, "Research Agent: [" . ($index + 1) . "/" . count($sources) . "] Scraping: {$short_url}" );
            
            $content = $this->scrape_url_safe( $url );
            
            if ( ! empty( $content ) && strlen( $content ) > 100 ) {
                $this->research_pack['sources'][] = $url;
                $this->research_pack['raw_texts'][] = array(
                    'url'     => $url,
                    'content' => $content,
                    'length'  => strlen( $content ),
                );
                $successful_scrapes++;
                tsa_log_job( $this->job_id, "Research Agent: [" . ($index + 1) . "] OK - " . strlen( $content ) . " chars" );
            } else {
                tsa_log_job( $this->job_id, "Research Agent: [" . ($index + 1) . "] SKIP - Konten kosong/pendek" );
            }
            
            // Prevent rate limiting
            usleep( 500000 ); // 0.5 detik delay
        }

        // Step 3: Combine all text
        tsa_log_job( $this->job_id, 'Research Agent: Menggabungkan data...' );
        $this->combine_raw_texts();

        // Step 4: Extract key information
        tsa_log_job( $this->job_id, 'Research Agent: Mengekstrak informasi penting...' );
        $this->extract_key_info();

        // Step 5: Extract keywords
        $this->extract_keywords();

        // Finalize
        $this->research_pack['status'] = 'completed';
        $this->research_pack['source_count'] = $successful_scrapes;
        $this->research_pack['total_chars'] = strlen( $this->research_pack['combined_text'] );

        tsa_log_job( $this->job_id, "Research Agent: Selesai. {$successful_scrapes} sumber berhasil, " . $this->research_pack['total_chars'] . " total karakter" );

        return $this->research_pack;
    }

    /**
     * Get source URLs
     */
    private function get_source_urls() {
        $sources = array();
        
        // 1. Wikipedia Indonesia
        $wiki_sources = $this->search_wikipedia();
        $sources = array_merge( $sources, $wiki_sources );
        
        // 2. DuckDuckGo
        $ddg_sources = $this->search_duckduckgo();
        $sources = array_merge( $sources, $ddg_sources );
        
        // 3. Direct trusted sources
        $direct_sources = $this->get_direct_sources();
        $sources = array_merge( $sources, $direct_sources );
        
        // Remove duplicates
        $unique_sources = array();
        $seen_domains = array();
        
        foreach ( $sources as $source ) {
            $url = is_array( $source ) ? $source['url'] : $source;
            $domain = parse_url( $url, PHP_URL_HOST );
            
            if ( ! in_array( $domain, $seen_domains ) && $this->is_valid_url( $url ) ) {
                $seen_domains[] = $domain;
                $unique_sources[] = $url;
            }
        }
        
        return array_slice( $unique_sources, 0, self::MAX_SOURCES );
    }

    /**
     * Search Wikipedia
     */
    private function search_wikipedia() {
        $sources = array();
        $query = urlencode( $this->title );
        
        $search_url = "https://id.wikipedia.org/w/api.php?action=query&list=search&srsearch={$query}&format=json&srlimit=2";
        
        $response = $this->make_request( $search_url );
        
        if ( $response ) {
            $data = json_decode( $response, true );
            
            if ( isset( $data['query']['search'] ) ) {
                foreach ( $data['query']['search'] as $result ) {
                    $page_title = str_replace( ' ', '_', $result['title'] );
                    $sources[] = "https://id.wikipedia.org/wiki/{$page_title}";
                }
            }
        }
        
        return $sources;
    }

    /**
     * Search DuckDuckGo
     */
    private function search_duckduckgo() {
        $sources = array();
        $query = urlencode( $this->title . ' wisata indonesia' );
        
        $ddg_url = "https://api.duckduckgo.com/?q={$query}&format=json&no_html=1";
        
        $response = $this->make_request( $ddg_url );
        
        if ( $response ) {
            $data = json_decode( $response, true );
            
            if ( ! empty( $data['AbstractURL'] ) ) {
                $sources[] = $data['AbstractURL'];
            }
            
            if ( isset( $data['RelatedTopics'] ) && is_array( $data['RelatedTopics'] ) ) {
                foreach ( array_slice( $data['RelatedTopics'], 0, 2 ) as $topic ) {
                    if ( isset( $topic['FirstURL'] ) && strpos( $topic['FirstURL'], 'http' ) === 0 ) {
                        $sources[] = $topic['FirstURL'];
                    }
                }
            }
        }
        
        return $sources;
    }

    /**
     * Get direct trusted sources
     */
    private function get_direct_sources() {
        $keyword = urlencode( $this->title );
        
        return array(
            "https://www.tripadvisor.co.id/Search?q={$keyword}",
            "https://travel.detik.com/search/searchall?query={$keyword}",
            "https://travel.kompas.com/search/{$keyword}",
        );
    }

    /**
     * Validate URL
     */
    private function is_valid_url( $url ) {
        if ( strpos( $url, 'http' ) !== 0 ) {
            return false;
        }
        
        // Block social media
        $blocked = array( 'facebook.com', 'twitter.com', 'instagram.com', 'youtube.com', 'tiktok.com', 'google.com' );
        foreach ( $blocked as $domain ) {
            if ( strpos( $url, $domain ) !== false ) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Scrape URL with safety measures
     */
    private function scrape_url_safe( $url ) {
        $html = $this->make_request( $url );
        
        if ( empty( $html ) ) {
            return '';
        }
        
        return $this->extract_text_from_html( $html );
    }

    /**
     * Make HTTP request with timeout
     */
    private function make_request( $url ) {
        // Use wp_remote_get
        $response = wp_remote_get( $url, array(
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'httpversion' => '1.1',
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'     => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            ),
            'sslverify'   => false,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return '';
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return '';
        }
        
        return wp_remote_retrieve_body( $response );
    }

    /**
     * Extract text from HTML
     */
    private function extract_text_from_html( $html ) {
        // Remove unwanted tags
        $html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
        $html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
        $html = preg_replace( '/<noscript[^>]*>.*?<\/noscript>/is', '', $html );
        $html = preg_replace( '/<nav[^>]*>.*?<\/nav>/is', '', $html );
        $html = preg_replace( '/<footer[^>]*>.*?<\/footer>/is', '', $html );
        $html = preg_replace( '/<header[^>]*>.*?<\/header>/is', '', $html );
        $html = preg_replace( '/<!--.*?-->/s', '', $html );
        
        // Try to get main content
        $content = '';
        
        if ( preg_match( '/<article[^>]*>(.*?)<\/article>/is', $html, $m ) ) {
            $content = $m[1];
        } elseif ( preg_match( '/<main[^>]*>(.*?)<\/main>/is', $html, $m ) ) {
            $content = $m[1];
        } elseif ( preg_match( '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m ) ) {
            $content = $m[1];
        } else {
            $content = $html;
        }
        
        // Extract text parts
        $text_parts = array();
        
        // Headings
        preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $headings );
        if ( ! empty( $headings[1] ) ) {
            foreach ( $headings[1] as $h ) {
                $text = trim( strip_tags( $h ) );
                if ( strlen( $text ) > 5 ) {
                    $text_parts[] = "## {$text}";
                }
            }
        }
        
        // Paragraphs
        preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs );
        if ( ! empty( $paragraphs[1] ) ) {
            foreach ( $paragraphs[1] as $p ) {
                $text = trim( strip_tags( $p ) );
                if ( strlen( $text ) > 50 ) {
                    $text_parts[] = $text;
                }
            }
        }
        
        // List items
        preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $content, $list_items );
        if ( ! empty( $list_items[1] ) ) {
            foreach ( $list_items[1] as $li ) {
                $text = trim( strip_tags( $li ) );
                if ( strlen( $text ) > 20 ) {
                    $text_parts[] = "- {$text}";
                }
            }
        }
        
        $final_text = implode( "\n\n", $text_parts );
        
        // Clean up
        $final_text = html_entity_decode( $final_text, ENT_QUOTES, 'UTF-8' );
        $final_text = preg_replace( '/\s+/', ' ', $final_text );
        $final_text = preg_replace( '/\n\s*\n/', "\n\n", $final_text );
        
        // Limit
        if ( strlen( $final_text ) > 8000 ) {
            $final_text = substr( $final_text, 0, 8000 );
        }
        
        return trim( $final_text );
    }

    /**
     * Combine all raw texts into one document
     */
    private function combine_raw_texts() {
        $combined = "# Data Riset untuk: {$this->title}\n\n";
        $combined .= "Tipe Konten: {$this->research_pack['content_type']}\n\n";
        $combined .= "---\n\n";
        
        foreach ( $this->research_pack['raw_texts'] as $index => $data ) {
            $combined .= "## Sumber " . ($index + 1) . "\n";
            $combined .= "URL: {$data['url']}\n\n";
            $combined .= $data['content'] . "\n\n";
            $combined .= "---\n\n";
        }
        
        $this->research_pack['combined_text'] = $combined;
    }

    /**
     * Extract key information
     */
    private function extract_key_info() {
        $all_text = $this->research_pack['combined_text'];
        
        $info = array(
            'nama'        => $this->title,
            'deskripsi'   => '',
            'lokasi'      => '',
            'alamat'      => '',
            'harga'       => '',
            'jam_buka'    => '',
            'fasilitas'   => array(),
            'aktivitas'   => array(),
            'tips'        => array(),
        );
        
        // Harga
        if ( preg_match( '/(?:harga|tiket|tarif|biaya)[^:]*[:=]?\s*(?:Rp\.?\s*)?([0-9.,]+)/i', $all_text, $m ) ) {
            $info['harga'] = 'Rp ' . $m[1];
        }
        
        // Jam buka
        if ( preg_match( '/(?:jam\s*(?:buka|operasional))[^:]*[:=]?\s*([0-9]{1,2}[:.][0-9]{2}\s*[-–]\s*[0-9]{1,2}[:.][0-9]{2})/i', $all_text, $m ) ) {
            $info['jam_buka'] = $m[1];
        }
        
        // Alamat
        if ( preg_match( '/(?:alamat|lokasi|terletak)[^:]*[:=]?\s*([^.\n]{20,150})/i', $all_text, $m ) ) {
            $info['alamat'] = trim( $m[1] );
        }
        
        // Fasilitas
        $fasilitas_list = array( 'toilet', 'parkir', 'mushola', 'restoran', 'warung', 'wifi', 'gazebo', 'kolam', 'playground', 'loker' );
        foreach ( $fasilitas_list as $f ) {
            if ( stripos( $all_text, $f ) !== false ) {
                $info['fasilitas'][] = ucfirst( $f );
            }
        }
        
        // Aktivitas
        $aktivitas_list = array( 'berenang', 'snorkeling', 'diving', 'hiking', 'camping', 'foto', 'selfie', 'kuliner', 'belanja', 'bermain' );
        foreach ( $aktivitas_list as $a ) {
            if ( stripos( $all_text, $a ) !== false ) {
                $info['aktivitas'][] = ucfirst( $a );
            }
        }
        
        // Deskripsi - ambil paragraf pertama yang relevan
        $paragraphs = preg_split( '/\n\n+/', $all_text );
        foreach ( $paragraphs as $p ) {
            $p = trim( $p );
            if ( strlen( $p ) > 100 && strlen( $p ) < 500 && stripos( $p, $this->title ) !== false ) {
                $info['deskripsi'] = $p;
                break;
            }
        }
        
        $this->research_pack['extracted_info'] = $info;
    }

    /**
     * Extract keywords
     */
    private function extract_keywords() {
        $all_text = strtolower( $this->research_pack['combined_text'] );
        $all_text = preg_replace( '/[^a-z0-9\s]/i', ' ', $all_text );
        
        $words = str_word_count( $all_text, 1 );
        $freq = array_count_values( $words );
        
        // Stopwords
        $stopwords = array( 'yang', 'dan', 'di', 'ke', 'dari', 'ini', 'itu', 'dengan', 'untuk', 'pada', 'adalah', 'atau', 'juga', 'tidak', 'akan', 'ada', 'bisa', 'dapat', 'lebih', 'sudah', 'saat', 'oleh', 'setelah', 'karena', 'seperti', 'serta', 'dalam', 'tersebut', 'the', 'and', 'to', 'of', 'in', 'is', 'it', 'for', 'on', 'with' );
        
        foreach ( $stopwords as $sw ) {
            unset( $freq[ $sw ] );
        }
        
        // Filter short words
        foreach ( $freq as $word => $count ) {
            if ( strlen( $word ) < 4 ) {
                unset( $freq[ $word ] );
            }
        }
        
        arsort( $freq );
        
        // Get top keywords
        $keywords = array_slice( array_keys( $freq ), 0, 15 );
        
        // Add title words
        $title_words = explode( ' ', strtolower( $this->title ) );
        $keywords = array_merge( $title_words, $keywords );
        $keywords = array_unique( $keywords );
        
        $this->research_pack['keywords'] = array_slice( $keywords, 0, 20 );
    }
}
