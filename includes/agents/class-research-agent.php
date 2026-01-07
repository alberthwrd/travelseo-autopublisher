<?php
/**
 * Research Agent V2 - Enhanced Web Scraper and SERP Research
 *
 * This agent is responsible for:
 * - Searching for relevant sources based on the input title
 * - Scraping content from top 5+ sources
 * - Extracting comprehensive data: facts, prices, hours, location, tips
 * - Building a rich Research Pack for the Writer Agent
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    2.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;
use function TravelSEO_Autopublisher\tsa_validate_url;
use function TravelSEO_Autopublisher\tsa_check_robots_txt;
use function TravelSEO_Autopublisher\tsa_extract_keywords;
use function TravelSEO_Autopublisher\tsa_get_keyword_variations;
use function TravelSEO_Autopublisher\tsa_generate_faq_questions;

/**
 * Research Agent Class V2
 */
class Research_Agent {

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
     * Research results
     */
    private $research_pack;

    /**
     * Minimum sources to scrape
     */
    const MIN_SOURCES = 5;

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
     * Initialize research pack structure
     */
    private function init_research_pack() {
        $this->research_pack = array(
            'title' => $this->title,
            'content_type' => $this->detect_content_type(),
            'sources' => array(),
            'scraped_content' => array(),
            
            // Core information
            'overview' => '',
            'history' => '',
            'unique_points' => array(),
            
            // Practical info
            'location' => array(
                'address' => '',
                'coordinates' => '',
                'landmarks' => array(),
                'city' => '',
                'province' => '',
            ),
            'pricing' => array(
                'ticket_adult' => '',
                'ticket_child' => '',
                'ticket_foreign' => '',
                'parking_motor' => '',
                'parking_car' => '',
                'other_fees' => array(),
                'notes' => '',
            ),
            'hours' => array(
                'weekday' => '',
                'weekend' => '',
                'holiday' => '',
                'notes' => '',
            ),
            'facilities' => array(),
            
            // Experience info
            'activities' => array(),
            'photo_spots' => array(),
            'tips' => array(),
            'warnings' => array(),
            
            // Related info
            'nearby_food' => array(),
            'nearby_hotels' => array(),
            'nearby_attractions' => array(),
            
            // SEO data
            'keywords' => array(),
            'lsi_keywords' => array(),
            'faq_questions' => array(),
            'search_intent' => '',
            
            // Meta
            'created_at' => current_time( 'mysql' ),
            'source_count' => 0,
            'data_quality_score' => 0,
        );
    }

    /**
     * Detect content type from title
     */
    private function detect_content_type() {
        $title_lower = strtolower( $this->title );
        
        $types = array(
            'kuliner' => array( 'makanan', 'kuliner', 'restoran', 'cafe', 'kafe', 'warung', 'nasi', 'mie', 'sate', 'bakso', 'soto' ),
            'hotel' => array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel', 'cottage', 'glamping' ),
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
        tsa_log_job( $this->job_id, 'Research Agent V2: Memulai riset untuk "' . $this->title . '"' );
        tsa_update_job( $this->job_id, array( 'status' => 'researching' ) );

        // Step 1: Search for sources (minimum 5)
        tsa_log_job( $this->job_id, 'Research Agent: Mencari sumber referensi...' );
        $sources = $this->search_sources();
        
        // Step 2: Scrape each source
        tsa_log_job( $this->job_id, 'Research Agent: Scraping ' . count( $sources ) . ' sumber...' );
        foreach ( $sources as $source ) {
            $scraped = $this->scrape_source( $source );
            if ( $scraped ) {
                $this->research_pack['sources'][] = $source;
                $this->research_pack['scraped_content'][] = $scraped;
            }
        }

        // Step 3: Extract and consolidate data
        tsa_log_job( $this->job_id, 'Research Agent: Mengekstrak data penting...' );
        $this->extract_practical_info();
        $this->extract_experience_info();
        $this->extract_related_info();

        // Step 4: Generate SEO data
        tsa_log_job( $this->job_id, 'Research Agent: Generating SEO data...' );
        $this->research_pack['keywords'] = tsa_get_keyword_variations( $this->title );
        $this->research_pack['lsi_keywords'] = $this->generate_lsi_keywords();
        $this->research_pack['faq_questions'] = $this->generate_comprehensive_faq();
        $this->research_pack['search_intent'] = $this->analyze_search_intent();

        // Step 5: Enhance with AI if available
        if ( $this->has_ai_api() ) {
            tsa_log_job( $this->job_id, 'Research Agent: Enhancing dengan AI...' );
            $this->enhance_with_ai();
        }

        // Step 6: Calculate data quality score
        $this->research_pack['source_count'] = count( $this->research_pack['sources'] );
        $this->research_pack['data_quality_score'] = $this->calculate_quality_score();

        tsa_log_job( $this->job_id, 'Research Agent: Selesai. ' . $this->research_pack['source_count'] . ' sumber, Quality Score: ' . $this->research_pack['data_quality_score'] . '%' );

        return $this->research_pack;
    }

    /**
     * Search for relevant sources
     */
    private function search_sources() {
        $sources = array();
        
        // Check if SERP API is configured
        $serp_api_key = tsa_get_option( 'serp_api_key', '' );
        
        if ( ! empty( $serp_api_key ) ) {
            $sources = $this->search_with_serp_api( $serp_api_key );
        } else {
            $sources = $this->search_free();
        }

        // Ensure minimum sources
        if ( count( $sources ) < self::MIN_SOURCES ) {
            $fallback = $this->get_fallback_sources();
            $sources = array_merge( $sources, $fallback );
        }

        // Remove duplicates and limit
        $sources = $this->deduplicate_sources( $sources );
        
        return array_slice( $sources, 0, 8 ); // Get up to 8 sources
    }

    /**
     * Search using SERP API
     */
    private function search_with_serp_api( $api_key ) {
        $sources = array();
        $content_type = $this->research_pack['content_type'];
        
        // Build optimized search query
        $search_queries = array(
            $this->title . ' wisata',
            $this->title . ' harga tiket jam buka',
            $this->title . ' review pengalaman',
        );
        
        foreach ( $search_queries as $query ) {
            $encoded_query = urlencode( $query );
            $url = "https://serpapi.com/search.json?q={$encoded_query}&hl=id&gl=id&num=5&api_key={$api_key}";
            
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            
            if ( ! is_wp_error( $response ) ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );
                
                if ( isset( $data['organic_results'] ) ) {
                    foreach ( $data['organic_results'] as $result ) {
                        if ( isset( $result['link'] ) ) {
                            $sources[] = array(
                                'url' => $result['link'],
                                'title' => $result['title'] ?? '',
                                'snippet' => $result['snippet'] ?? '',
                                'position' => $result['position'] ?? 0,
                            );
                        }
                    }
                }
            }
        }
        
        return $sources;
    }

    /**
     * Search using free methods
     */
    private function search_free() {
        $sources = array();
        
        // 1. Wikipedia Indonesia
        $wiki_sources = $this->search_wikipedia();
        $sources = array_merge( $sources, $wiki_sources );
        
        // 2. DuckDuckGo Instant Answer
        $ddg_sources = $this->search_duckduckgo();
        $sources = array_merge( $sources, $ddg_sources );
        
        // 3. Build direct URLs to trusted sources
        $direct_sources = $this->build_direct_source_urls();
        $sources = array_merge( $sources, $direct_sources );
        
        return $sources;
    }

    /**
     * Search Wikipedia
     */
    private function search_wikipedia() {
        $sources = array();
        $wiki_query = urlencode( $this->title );
        
        // Search API
        $search_url = "https://id.wikipedia.org/w/api.php?action=query&list=search&srsearch={$wiki_query}&format=json&srlimit=3";
        
        $response = wp_remote_get( $search_url, array( 'timeout' => 10 ) );
        
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( isset( $data['query']['search'] ) ) {
                foreach ( $data['query']['search'] as $result ) {
                    $page_title = str_replace( ' ', '_', $result['title'] );
                    $sources[] = array(
                        'url' => "https://id.wikipedia.org/wiki/{$page_title}",
                        'title' => $result['title'],
                        'snippet' => strip_tags( $result['snippet'] ),
                        'source_type' => 'wikipedia',
                    );
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
        $ddg_query = urlencode( $this->title . ' wisata indonesia' );
        $ddg_url = "https://api.duckduckgo.com/?q={$ddg_query}&format=json&no_html=1";
        
        $response = wp_remote_get( $ddg_url, array( 'timeout' => 10 ) );
        
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            // Abstract
            if ( ! empty( $data['AbstractURL'] ) ) {
                $sources[] = array(
                    'url' => $data['AbstractURL'],
                    'title' => $data['Heading'] ?? $this->title,
                    'snippet' => $data['AbstractText'] ?? '',
                    'source_type' => 'duckduckgo',
                );
            }
            
            // Related topics
            if ( isset( $data['RelatedTopics'] ) && is_array( $data['RelatedTopics'] ) ) {
                foreach ( array_slice( $data['RelatedTopics'], 0, 3 ) as $topic ) {
                    if ( isset( $topic['FirstURL'] ) ) {
                        $sources[] = array(
                            'url' => $topic['FirstURL'],
                            'title' => $topic['Text'] ?? '',
                            'snippet' => $topic['Text'] ?? '',
                            'source_type' => 'duckduckgo',
                        );
                    }
                }
            }
        }
        
        return $sources;
    }

    /**
     * Build direct URLs to trusted sources
     */
    private function build_direct_source_urls() {
        $sources = array();
        $keyword = urlencode( $this->title );
        
        // Trusted Indonesian travel sources
        $trusted_sources = array(
            array(
                'base' => 'https://www.tripadvisor.co.id/Search?q=',
                'name' => 'TripAdvisor',
            ),
            array(
                'base' => 'https://www.traveloka.com/id-id/activities/search?query=',
                'name' => 'Traveloka',
            ),
            array(
                'base' => 'https://www.tiket.com/to-do/search?q=',
                'name' => 'Tiket.com',
            ),
        );
        
        foreach ( $trusted_sources as $source ) {
            $sources[] = array(
                'url' => $source['base'] . $keyword,
                'title' => $source['name'] . ' - ' . $this->title,
                'snippet' => '',
                'source_type' => 'direct',
            );
        }
        
        return $sources;
    }

    /**
     * Get fallback sources
     */
    private function get_fallback_sources() {
        $sources = array();
        $content_type = $this->research_pack['content_type'];
        
        // Generic fallback based on content type
        $fallbacks = array(
            'destinasi' => array(
                array( 'url' => 'https://indonesia.travel/id/id/home', 'title' => 'Wonderful Indonesia' ),
            ),
            'kuliner' => array(
                array( 'url' => 'https://id.wikipedia.org/wiki/Daftar_masakan_Indonesia', 'title' => 'Masakan Indonesia' ),
            ),
            'hotel' => array(
                array( 'url' => 'https://www.booking.com', 'title' => 'Booking.com' ),
            ),
        );
        
        if ( isset( $fallbacks[ $content_type ] ) ) {
            foreach ( $fallbacks[ $content_type ] as $fb ) {
                $sources[] = array(
                    'url' => $fb['url'],
                    'title' => $fb['title'],
                    'snippet' => '',
                    'source_type' => 'fallback',
                );
            }
        }
        
        return $sources;
    }

    /**
     * Deduplicate sources by domain
     */
    private function deduplicate_sources( $sources ) {
        $seen_domains = array();
        $unique = array();
        
        foreach ( $sources as $source ) {
            $domain = parse_url( $source['url'], PHP_URL_HOST );
            if ( ! in_array( $domain, $seen_domains ) ) {
                $seen_domains[] = $domain;
                $unique[] = $source;
            }
        }
        
        return $unique;
    }

    /**
     * Scrape a single source
     */
    private function scrape_source( $source ) {
        $url = $source['url'];
        
        // Validate URL
        if ( ! tsa_validate_url( $url ) ) {
            return null;
        }
        
        // Check robots.txt
        if ( ! tsa_check_robots_txt( $url ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: Skipping (robots.txt) - ' . $url );
            return null;
        }
        
        // Fetch content
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; TravelSEO Research Bot/2.0)',
        ) );
        
        if ( is_wp_error( $response ) ) {
            return null;
        }
        
        $html = wp_remote_retrieve_body( $response );
        
        if ( empty( $html ) ) {
            return null;
        }
        
        // Parse and extract content
        return $this->parse_html_content( $html, $url );
    }

    /**
     * Parse HTML and extract relevant content
     */
    private function parse_html_content( $html, $url ) {
        // Remove scripts, styles, and comments
        $html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
        $html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
        $html = preg_replace( '/<!--.*?-->/s', '', $html );
        
        // Extract title
        preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $title_match );
        $title = isset( $title_match[1] ) ? trim( strip_tags( $title_match[1] ) ) : '';
        
        // Extract meta description
        preg_match( '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/is', $html, $desc_match );
        $meta_desc = isset( $desc_match[1] ) ? trim( $desc_match[1] ) : '';
        
        // Extract headings
        preg_match_all( '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $html, $heading_matches );
        $headings = array_map( 'strip_tags', $heading_matches[1] ?? array() );
        
        // Extract paragraphs
        preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $p_matches );
        $paragraphs = array();
        foreach ( $p_matches[1] ?? array() as $p ) {
            $clean = trim( strip_tags( $p ) );
            if ( strlen( $clean ) > 50 ) { // Only meaningful paragraphs
                $paragraphs[] = $clean;
            }
        }
        
        // Extract lists
        preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $html, $li_matches );
        $list_items = array_map( function( $li ) {
            return trim( strip_tags( $li ) );
        }, $li_matches[1] ?? array() );
        
        // Extract tables (for pricing, hours, etc.)
        $tables = $this->extract_tables( $html );
        
        // Combine all text for analysis
        $all_text = implode( "\n", array_merge( array( $title, $meta_desc ), $headings, $paragraphs ) );
        
        return array(
            'url' => $url,
            'title' => $title,
            'meta_description' => $meta_desc,
            'headings' => array_slice( $headings, 0, 20 ),
            'paragraphs' => array_slice( $paragraphs, 0, 30 ),
            'list_items' => array_slice( $list_items, 0, 30 ),
            'tables' => $tables,
            'full_text' => substr( $all_text, 0, 10000 ), // Limit text size
            'word_count' => str_word_count( $all_text ),
        );
    }

    /**
     * Extract tables from HTML
     */
    private function extract_tables( $html ) {
        $tables = array();
        
        preg_match_all( '/<table[^>]*>(.*?)<\/table>/is', $html, $table_matches );
        
        foreach ( $table_matches[1] ?? array() as $table_html ) {
            $rows = array();
            preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $table_html, $tr_matches );
            
            foreach ( $tr_matches[1] ?? array() as $tr ) {
                preg_match_all( '/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $td_matches );
                $cells = array_map( function( $cell ) {
                    return trim( strip_tags( $cell ) );
                }, $td_matches[1] ?? array() );
                
                if ( ! empty( $cells ) ) {
                    $rows[] = $cells;
                }
            }
            
            if ( ! empty( $rows ) ) {
                $tables[] = $rows;
            }
        }
        
        return array_slice( $tables, 0, 5 );
    }

    /**
     * Extract practical information from scraped content
     */
    private function extract_practical_info() {
        $all_text = '';
        foreach ( $this->research_pack['scraped_content'] as $content ) {
            $all_text .= $content['full_text'] . "\n";
        }
        
        // Extract address patterns
        $address_patterns = array(
            '/(?:alamat|lokasi|terletak di|berada di)[:\s]*([^\.]+)/i',
            '/(?:jl\.|jalan)[^,\.]+(?:,\s*[^,\.]+)*/i',
        );
        
        foreach ( $address_patterns as $pattern ) {
            if ( preg_match( $pattern, $all_text, $match ) ) {
                $this->research_pack['location']['address'] = trim( $match[1] ?? $match[0] );
                break;
            }
        }
        
        // Extract pricing patterns
        $price_patterns = array(
            '/(?:harga tiket|htm|tiket masuk)[:\s]*(?:rp\.?\s*)?(\d+[\d\.,]*)/i',
            '/(?:dewasa|adult)[:\s]*(?:rp\.?\s*)?(\d+[\d\.,]*)/i',
            '/(?:anak|child)[:\s]*(?:rp\.?\s*)?(\d+[\d\.,]*)/i',
        );
        
        foreach ( $price_patterns as $pattern ) {
            if ( preg_match( $pattern, $all_text, $match ) ) {
                $price = 'Rp ' . number_format( (int) preg_replace( '/[^\d]/', '', $match[1] ), 0, ',', '.' );
                if ( stripos( $match[0], 'anak' ) !== false || stripos( $match[0], 'child' ) !== false ) {
                    $this->research_pack['pricing']['ticket_child'] = $price;
                } else {
                    $this->research_pack['pricing']['ticket_adult'] = $price;
                }
            }
        }
        
        // Extract hours patterns
        $hours_patterns = array(
            '/(?:jam buka|jam operasional|buka)[:\s]*(\d{1,2}[:.]\d{2})\s*[-–]\s*(\d{1,2}[:.]\d{2})/i',
            '/(?:senin|selasa|rabu|kamis|jumat|sabtu|minggu)[:\s]*(\d{1,2}[:.]\d{2})\s*[-–]\s*(\d{1,2}[:.]\d{2})/i',
        );
        
        foreach ( $hours_patterns as $pattern ) {
            if ( preg_match( $pattern, $all_text, $match ) ) {
                $this->research_pack['hours']['weekday'] = $match[1] . ' - ' . $match[2];
                break;
            }
        }
        
        // Extract facilities
        $facility_keywords = array(
            'toilet', 'mushola', 'masjid', 'parkir', 'warung', 'restoran', 'cafe',
            'wifi', 'gazebo', 'playground', 'kolam renang', 'toko suvenir', 'atm',
        );
        
        foreach ( $facility_keywords as $facility ) {
            if ( stripos( $all_text, $facility ) !== false ) {
                $this->research_pack['facilities'][] = ucfirst( $facility );
            }
        }
        
        $this->research_pack['facilities'] = array_unique( $this->research_pack['facilities'] );
    }

    /**
     * Extract experience information
     */
    private function extract_experience_info() {
        $all_text = '';
        $all_lists = array();
        
        foreach ( $this->research_pack['scraped_content'] as $content ) {
            $all_text .= $content['full_text'] . "\n";
            $all_lists = array_merge( $all_lists, $content['list_items'] ?? array() );
        }
        
        // Extract activities
        $activity_keywords = array(
            'berenang', 'snorkeling', 'diving', 'foto', 'selfie', 'piknik',
            'camping', 'hiking', 'trekking', 'bermain', 'bersantai', 'menikmati',
            'melihat', 'mengunjungi', 'berkeliling', 'menjelajahi',
        );
        
        foreach ( $all_lists as $item ) {
            foreach ( $activity_keywords as $keyword ) {
                if ( stripos( $item, $keyword ) !== false && strlen( $item ) > 10 && strlen( $item ) < 200 ) {
                    $this->research_pack['activities'][] = $item;
                    break;
                }
            }
        }
        
        $this->research_pack['activities'] = array_unique( array_slice( $this->research_pack['activities'], 0, 10 ) );
        
        // Extract tips
        $tip_patterns = array(
            '/(?:tips?|saran|disarankan|sebaiknya)[:\s]*([^\.]+\.)/i',
            '/(?:jangan lupa|pastikan|perhatikan)[:\s]*([^\.]+\.)/i',
        );
        
        foreach ( $tip_patterns as $pattern ) {
            preg_match_all( $pattern, $all_text, $matches );
            if ( ! empty( $matches[1] ) ) {
                foreach ( $matches[1] as $tip ) {
                    $tip = trim( $tip );
                    if ( strlen( $tip ) > 20 && strlen( $tip ) < 300 ) {
                        $this->research_pack['tips'][] = $tip;
                    }
                }
            }
        }
        
        $this->research_pack['tips'] = array_unique( array_slice( $this->research_pack['tips'], 0, 10 ) );
    }

    /**
     * Extract related information
     */
    private function extract_related_info() {
        $all_text = '';
        foreach ( $this->research_pack['scraped_content'] as $content ) {
            $all_text .= $content['full_text'] . "\n";
        }
        
        // Extract nearby food mentions
        $food_patterns = array(
            '/(?:kuliner|makanan|warung|restoran|cafe)[^\.]*(?:terdekat|sekitar|dekat)[^\.]*\./i',
            '/(?:mencicipi|menikmati|mencoba)[^\.]*(?:kuliner|makanan|masakan)[^\.]*\./i',
        );
        
        foreach ( $food_patterns as $pattern ) {
            preg_match_all( $pattern, $all_text, $matches );
            if ( ! empty( $matches[0] ) ) {
                foreach ( $matches[0] as $food ) {
                    $this->research_pack['nearby_food'][] = trim( $food );
                }
            }
        }
        
        $this->research_pack['nearby_food'] = array_unique( array_slice( $this->research_pack['nearby_food'], 0, 5 ) );
    }

    /**
     * Generate LSI keywords
     */
    private function generate_lsi_keywords() {
        $lsi = array();
        $title_words = explode( ' ', strtolower( $this->title ) );
        
        // Common LSI patterns for travel content
        $lsi_templates = array(
            '{keyword} terdekat',
            '{keyword} terbaru',
            'harga tiket {keyword}',
            'jam buka {keyword}',
            'rute ke {keyword}',
            'hotel dekat {keyword}',
            'kuliner {keyword}',
            'tips berkunjung {keyword}',
            'review {keyword}',
            'pengalaman {keyword}',
        );
        
        foreach ( $lsi_templates as $template ) {
            $lsi[] = str_replace( '{keyword}', $this->title, $template );
        }
        
        return $lsi;
    }

    /**
     * Generate comprehensive FAQ questions
     */
    private function generate_comprehensive_faq() {
        $faqs = array();
        $keyword = $this->title;
        $type = $this->research_pack['content_type'];
        
        // Universal FAQs
        $universal = array(
            "Apa itu {$keyword}?",
            "Dimana lokasi {$keyword}?",
            "Berapa harga tiket masuk {$keyword}?",
            "Jam berapa {$keyword} buka?",
            "Apa saja fasilitas di {$keyword}?",
            "Bagaimana cara menuju {$keyword}?",
            "Kapan waktu terbaik mengunjungi {$keyword}?",
            "Apakah {$keyword} cocok untuk anak-anak?",
        );
        
        $faqs = array_merge( $faqs, $universal );
        
        // Type-specific FAQs
        $type_faqs = array(
            'destinasi' => array(
                "Apa saja aktivitas yang bisa dilakukan di {$keyword}?",
                "Apakah ada penginapan dekat {$keyword}?",
                "Berapa lama waktu yang dibutuhkan untuk mengunjungi {$keyword}?",
            ),
            'kuliner' => array(
                "Apa menu andalan di {$keyword}?",
                "Berapa kisaran harga makanan di {$keyword}?",
                "Apakah {$keyword} menyediakan delivery?",
            ),
            'hotel' => array(
                "Berapa harga kamar di {$keyword}?",
                "Apa saja fasilitas kamar di {$keyword}?",
                "Apakah {$keyword} menyediakan sarapan?",
            ),
        );
        
        if ( isset( $type_faqs[ $type ] ) ) {
            $faqs = array_merge( $faqs, $type_faqs[ $type ] );
        }
        
        return array_slice( $faqs, 0, 10 );
    }

    /**
     * Analyze search intent
     */
    private function analyze_search_intent() {
        $title_lower = strtolower( $this->title );
        
        // Informational intent keywords
        $info_keywords = array( 'apa', 'siapa', 'mengapa', 'bagaimana', 'sejarah', 'asal' );
        foreach ( $info_keywords as $kw ) {
            if ( strpos( $title_lower, $kw ) !== false ) {
                return 'informational';
            }
        }
        
        // Transactional intent keywords
        $trans_keywords = array( 'beli', 'booking', 'pesan', 'harga', 'tiket', 'promo' );
        foreach ( $trans_keywords as $kw ) {
            if ( strpos( $title_lower, $kw ) !== false ) {
                return 'transactional';
            }
        }
        
        // Navigational intent (specific place names)
        return 'navigational';
    }

    /**
     * Check if AI API is available
     */
    private function has_ai_api() {
        return ! empty( tsa_get_option( 'openai_api_key', '' ) );
    }

    /**
     * Enhance research with AI
     */
    private function enhance_with_ai() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }
        
        // Build context from scraped content
        $context = '';
        foreach ( $this->research_pack['scraped_content'] as $content ) {
            $context .= substr( $content['full_text'], 0, 2000 ) . "\n\n";
        }
        $context = substr( $context, 0, 8000 );
        
        $prompt = "Berdasarkan informasi berikut tentang \"{$this->title}\", ekstrak dan rangkum data penting dalam format JSON:

INFORMASI:
{$context}

Ekstrak dalam format JSON:
{
    \"overview\": \"Deskripsi singkat 2-3 kalimat\",
    \"unique_points\": [\"Poin unik 1\", \"Poin unik 2\", \"Poin unik 3\"],
    \"address\": \"Alamat lengkap jika ditemukan\",
    \"ticket_price\": \"Harga tiket jika ditemukan\",
    \"opening_hours\": \"Jam buka jika ditemukan\",
    \"best_activities\": [\"Aktivitas 1\", \"Aktivitas 2\"],
    \"insider_tips\": [\"Tips 1\", \"Tips 2\"]
}

Jika informasi tidak ditemukan, isi dengan string kosong atau array kosong.";

        $endpoint = tsa_get_option( 'openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        $response = wp_remote_post( $endpoint, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $model,
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: AI enhancement failed - ' . $response->get_error_message() );
            return;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
            $content = $body['choices'][0]['message']['content'];
            
            // Try to parse JSON from response
            preg_match( '/\{[^{}]*\}/s', $content, $json_match );
            if ( ! empty( $json_match[0] ) ) {
                $ai_data = json_decode( $json_match[0], true );
                
                if ( $ai_data ) {
                    // Merge AI data with existing data
                    if ( ! empty( $ai_data['overview'] ) ) {
                        $this->research_pack['overview'] = $ai_data['overview'];
                    }
                    if ( ! empty( $ai_data['unique_points'] ) ) {
                        $this->research_pack['unique_points'] = array_merge(
                            $this->research_pack['unique_points'],
                            $ai_data['unique_points']
                        );
                    }
                    if ( ! empty( $ai_data['address'] ) && empty( $this->research_pack['location']['address'] ) ) {
                        $this->research_pack['location']['address'] = $ai_data['address'];
                    }
                    if ( ! empty( $ai_data['insider_tips'] ) ) {
                        $this->research_pack['tips'] = array_merge(
                            $this->research_pack['tips'],
                            $ai_data['insider_tips']
                        );
                    }
                    
                    tsa_log_job( $this->job_id, 'Research Agent: AI enhancement successful' );
                }
            }
        }
    }

    /**
     * Calculate data quality score
     */
    private function calculate_quality_score() {
        $score = 0;
        $max_score = 100;
        
        // Source count (max 20 points)
        $source_count = count( $this->research_pack['sources'] );
        $score += min( $source_count * 4, 20 );
        
        // Has address (10 points)
        if ( ! empty( $this->research_pack['location']['address'] ) ) {
            $score += 10;
        }
        
        // Has pricing (10 points)
        if ( ! empty( $this->research_pack['pricing']['ticket_adult'] ) ) {
            $score += 10;
        }
        
        // Has hours (10 points)
        if ( ! empty( $this->research_pack['hours']['weekday'] ) ) {
            $score += 10;
        }
        
        // Has facilities (10 points)
        if ( count( $this->research_pack['facilities'] ) >= 3 ) {
            $score += 10;
        }
        
        // Has activities (10 points)
        if ( count( $this->research_pack['activities'] ) >= 3 ) {
            $score += 10;
        }
        
        // Has tips (10 points)
        if ( count( $this->research_pack['tips'] ) >= 3 ) {
            $score += 10;
        }
        
        // Has overview (10 points)
        if ( ! empty( $this->research_pack['overview'] ) ) {
            $score += 10;
        }
        
        // Has FAQs (10 points)
        if ( count( $this->research_pack['faq_questions'] ) >= 5 ) {
            $score += 10;
        }
        
        return min( $score, $max_score );
    }
}
