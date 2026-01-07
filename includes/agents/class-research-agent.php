<?php
/**
 * Research Agent - Web Scraper and SERP Research
 *
 * This agent is responsible for:
 * - Searching for relevant sources based on the input title
 * - Scraping content from top sources (respecting robots.txt)
 * - Extracting key facts, points, and information
 * - Building a Research Pack for the next agent
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
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
 * Research Agent Class
 */
class Research_Agent {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Job title/topic
     *
     * @var string
     */
    private $title;

    /**
     * Job settings
     *
     * @var array
     */
    private $settings;

    /**
     * Research results
     *
     * @var array
     */
    private $research_pack;

    /**
     * Constructor
     *
     * @param int $job_id Job ID
     * @param string $title Job title
     * @param array $settings Job settings
     */
    public function __construct( $job_id, $title, $settings = array() ) {
        $this->job_id = $job_id;
        $this->title = $title;
        $this->settings = $settings;
        $this->research_pack = array(
            'title' => $title,
            'sources' => array(),
            'facts' => array(),
            'keywords' => array(),
            'faq_questions' => array(),
            'location_info' => array(),
            'pricing_info' => array(),
            'hours_info' => array(),
            'tips' => array(),
            'nearby_places' => array(),
            'created_at' => current_time( 'mysql' ),
        );
    }

    /**
     * Run the research process
     *
     * @return array Research pack
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Research Agent: Starting research for "' . $this->title . '"' );
        
        // Update job status
        tsa_update_job( $this->job_id, array( 'status' => 'researching' ) );

        // Step 1: Search for sources
        $sources = $this->search_sources();
        
        // Step 2: Scrape each source
        foreach ( $sources as $source ) {
            $scraped = $this->scrape_source( $source );
            if ( $scraped ) {
                $this->research_pack['sources'][] = $scraped;
            }
        }

        // Step 3: Generate keyword variations
        $this->research_pack['keywords'] = tsa_get_keyword_variations( $this->title );
        
        // Step 4: Generate FAQ questions
        $this->research_pack['faq_questions'] = tsa_generate_faq_questions( $this->title );

        // Step 5: Consolidate facts from all sources
        $this->consolidate_facts();

        // Step 6: If API is available, enhance with AI
        if ( $this->has_ai_api() ) {
            $this->enhance_with_ai();
        }

        tsa_log_job( $this->job_id, 'Research Agent: Completed. Found ' . count( $this->research_pack['sources'] ) . ' sources.' );

        return $this->research_pack;
    }

    /**
     * Search for relevant sources
     *
     * @return array List of source URLs
     */
    private function search_sources() {
        $sources = array();
        
        // Check if SERP API is configured
        $serp_api_key = tsa_get_option( 'serp_api_key', '' );
        
        if ( ! empty( $serp_api_key ) ) {
            // Use SERP API
            $sources = $this->search_with_serp_api( $serp_api_key );
        } else {
            // Fallback: Use free search methods
            $sources = $this->search_free();
        }

        tsa_log_job( $this->job_id, 'Research Agent: Found ' . count( $sources ) . ' potential sources.' );

        return array_slice( $sources, 0, 5 ); // Limit to top 5
    }

    /**
     * Search using SERP API (optional)
     *
     * @param string $api_key SERP API key
     * @return array
     */
    private function search_with_serp_api( $api_key ) {
        $sources = array();
        
        $query = urlencode( $this->title . ' wisata' );
        $url = "https://serpapi.com/search.json?q={$query}&hl=id&gl=id&api_key={$api_key}";
        
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: SERP API error - ' . $response->get_error_message() );
            return $this->search_free();
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['organic_results'] ) ) {
            foreach ( $data['organic_results'] as $result ) {
                if ( isset( $result['link'] ) ) {
                    $sources[] = array(
                        'url' => $result['link'],
                        'title' => isset( $result['title'] ) ? $result['title'] : '',
                        'snippet' => isset( $result['snippet'] ) ? $result['snippet'] : '',
                    );
                }
            }
        }
        
        return $sources;
    }

    /**
     * Search using free methods (no API required)
     *
     * @return array
     */
    private function search_free() {
        $sources = array();
        
        // Predefined trusted sources for travel content
        $trusted_domains = array(
            'wikipedia.org',
            'tripadvisor.co.id',
            'traveloka.com',
            'tiket.com',
            'indonesia.travel',
            'wonderful.co.id',
            'detik.com/travel',
            'kompas.com/travel',
            'liputan6.com/lifestyle/travel',
        );
        
        // Build search URLs for Wikipedia (most reliable free source)
        $wiki_query = urlencode( $this->title );
        $wiki_url = "https://id.wikipedia.org/w/api.php?action=opensearch&search={$wiki_query}&limit=3&format=json";
        
        $response = wp_remote_get( $wiki_url, array(
            'timeout' => 10,
        ) );
        
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( isset( $data[3] ) && is_array( $data[3] ) ) {
                foreach ( $data[3] as $index => $url ) {
                    $sources[] = array(
                        'url' => $url,
                        'title' => isset( $data[1][ $index ] ) ? $data[1][ $index ] : '',
                        'snippet' => isset( $data[2][ $index ] ) ? $data[2][ $index ] : '',
                    );
                }
            }
        }

        // Try to get results from DuckDuckGo Instant Answer API (free, no key required)
        $ddg_query = urlencode( $this->title . ' wisata indonesia' );
        $ddg_url = "https://api.duckduckgo.com/?q={$ddg_query}&format=json&no_html=1";
        
        $response = wp_remote_get( $ddg_url, array(
            'timeout' => 10,
        ) );
        
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            // Get related topics
            if ( isset( $data['RelatedTopics'] ) && is_array( $data['RelatedTopics'] ) ) {
                foreach ( $data['RelatedTopics'] as $topic ) {
                    if ( isset( $topic['FirstURL'] ) ) {
                        $sources[] = array(
                            'url' => $topic['FirstURL'],
                            'title' => isset( $topic['Text'] ) ? $topic['Text'] : '',
                            'snippet' => isset( $topic['Text'] ) ? $topic['Text'] : '',
                        );
                    }
                }
            }
        }

        // Add fallback sources based on topic
        $fallback_sources = $this->get_fallback_sources();
        $sources = array_merge( $sources, $fallback_sources );

        return $sources;
    }

    /**
     * Get fallback sources based on topic type
     *
     * @return array
     */
    private function get_fallback_sources() {
        $sources = array();
        
        // Detect topic type
        $title_lower = strtolower( $this->title );
        
        // Tourism/destination keywords
        $tourism_keywords = array( 'wisata', 'pantai', 'gunung', 'air terjun', 'danau', 'taman', 'candi', 'museum' );
        $culinary_keywords = array( 'kuliner', 'makanan', 'restoran', 'cafe', 'warung', 'masakan' );
        
        $is_tourism = false;
        $is_culinary = false;
        
        foreach ( $tourism_keywords as $keyword ) {
            if ( strpos( $title_lower, $keyword ) !== false ) {
                $is_tourism = true;
                break;
            }
        }
        
        foreach ( $culinary_keywords as $keyword ) {
            if ( strpos( $title_lower, $keyword ) !== false ) {
                $is_culinary = true;
                break;
            }
        }
        
        // Add relevant fallback sources
        if ( $is_tourism ) {
            $sources[] = array(
                'url' => 'https://indonesia.travel/id/id/home',
                'title' => 'Wonderful Indonesia - Official Tourism Website',
                'snippet' => 'Official tourism website of Indonesia',
                'is_fallback' => true,
            );
        }
        
        if ( $is_culinary ) {
            $sources[] = array(
                'url' => 'https://id.wikipedia.org/wiki/Daftar_masakan_Indonesia',
                'title' => 'Daftar Masakan Indonesia - Wikipedia',
                'snippet' => 'Daftar lengkap masakan Indonesia',
                'is_fallback' => true,
            );
        }
        
        return $sources;
    }

    /**
     * Scrape content from a source
     *
     * @param array $source Source info
     * @return array|false Scraped data or false on failure
     */
    private function scrape_source( $source ) {
        $url = $source['url'];
        
        // Validate URL
        $validated_url = tsa_validate_url( $url );
        if ( ! $validated_url ) {
            tsa_log_job( $this->job_id, 'Research Agent: Invalid URL skipped - ' . $url );
            return false;
        }
        
        // Check robots.txt
        if ( ! tsa_check_robots_txt( $validated_url ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: Blocked by robots.txt - ' . $url );
            return false;
        }
        
        tsa_log_job( $this->job_id, 'Research Agent: Scraping - ' . $url );
        
        // Fetch the page
        $response = wp_remote_get( $validated_url, array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; TravelSEO-Bot/1.0; +https://example.com/bot)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: Failed to fetch - ' . $response->get_error_message() );
            return false;
        }
        
        $html = wp_remote_retrieve_body( $response );
        
        if ( empty( $html ) ) {
            return false;
        }
        
        // Parse the HTML
        $scraped_data = $this->parse_html( $html, $validated_url );
        $scraped_data['url'] = $validated_url;
        $scraped_data['original_title'] = $source['title'];
        $scraped_data['original_snippet'] = $source['snippet'];
        
        return $scraped_data;
    }

    /**
     * Parse HTML content and extract relevant information
     *
     * @param string $html HTML content
     * @param string $url Source URL
     * @return array
     */
    private function parse_html( $html, $url ) {
        $data = array(
            'title' => '',
            'content_summary' => '',
            'headings' => array(),
            'key_facts' => array(),
            'images' => array(),
            'location' => '',
            'hours' => '',
            'price' => '',
            'tips' => array(),
        );
        
        // Use DOMDocument to parse HTML
        libxml_use_internal_errors( true );
        $dom = new \DOMDocument();
        $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();
        
        $xpath = new \DOMXPath( $dom );
        
        // Extract title
        $title_nodes = $xpath->query( '//title' );
        if ( $title_nodes->length > 0 ) {
            $data['title'] = trim( $title_nodes->item( 0 )->textContent );
        }
        
        // Extract headings (H1, H2, H3)
        $heading_nodes = $xpath->query( '//h1 | //h2 | //h3' );
        foreach ( $heading_nodes as $node ) {
            $heading_text = trim( $node->textContent );
            if ( ! empty( $heading_text ) && strlen( $heading_text ) < 200 ) {
                $data['headings'][] = $heading_text;
            }
        }
        
        // Extract main content paragraphs
        $paragraph_nodes = $xpath->query( '//article//p | //main//p | //div[contains(@class, "content")]//p | //div[contains(@class, "entry")]//p' );
        $content_parts = array();
        
        foreach ( $paragraph_nodes as $node ) {
            $text = trim( $node->textContent );
            if ( strlen( $text ) > 50 && strlen( $text ) < 1000 ) {
                $content_parts[] = $text;
            }
        }
        
        // If no content found in article/main, try general paragraphs
        if ( empty( $content_parts ) ) {
            $paragraph_nodes = $xpath->query( '//p' );
            foreach ( $paragraph_nodes as $node ) {
                $text = trim( $node->textContent );
                if ( strlen( $text ) > 50 && strlen( $text ) < 1000 ) {
                    $content_parts[] = $text;
                }
            }
        }
        
        // Limit to first 5 paragraphs for summary
        $data['content_summary'] = implode( "\n\n", array_slice( $content_parts, 0, 5 ) );
        
        // Extract key facts using pattern matching
        $data['key_facts'] = $this->extract_key_facts( $content_parts );
        
        // Extract location information
        $data['location'] = $this->extract_location_info( $content_parts );
        
        // Extract hours information
        $data['hours'] = $this->extract_hours_info( $content_parts );
        
        // Extract price information
        $data['price'] = $this->extract_price_info( $content_parts );
        
        // Extract tips
        $data['tips'] = $this->extract_tips( $content_parts );
        
        // Extract images (alt text for reference)
        $img_nodes = $xpath->query( '//img[@alt]' );
        foreach ( $img_nodes as $node ) {
            $alt = $node->getAttribute( 'alt' );
            if ( ! empty( $alt ) && strlen( $alt ) > 5 ) {
                $data['images'][] = $alt;
            }
        }
        
        return $data;
    }

    /**
     * Extract key facts from content
     *
     * @param array $paragraphs Content paragraphs
     * @return array
     */
    private function extract_key_facts( $paragraphs ) {
        $facts = array();
        
        $fact_patterns = array(
            '/terletak di (.+?)(?:\.|,)/i',
            '/didirikan pada (.+?)(?:\.|,)/i',
            '/dibuka pada (.+?)(?:\.|,)/i',
            '/memiliki luas (.+?)(?:\.|,)/i',
            '/ketinggian (.+?)(?:\.|,)/i',
            '/berjarak (.+?)(?:\.|,)/i',
            '/dikenal sebagai (.+?)(?:\.|,)/i',
            '/merupakan (.+?)(?:\.|,)/i',
        );
        
        foreach ( $paragraphs as $paragraph ) {
            foreach ( $fact_patterns as $pattern ) {
                if ( preg_match( $pattern, $paragraph, $matches ) ) {
                    $facts[] = trim( $matches[0] );
                }
            }
        }
        
        return array_unique( array_slice( $facts, 0, 10 ) );
    }

    /**
     * Extract location information
     *
     * @param array $paragraphs Content paragraphs
     * @return string
     */
    private function extract_location_info( $paragraphs ) {
        $location_patterns = array(
            '/(?:terletak|berlokasi|berada) di (.+?)(?:\.|,)/i',
            '/alamat[:\s]+(.+?)(?:\.|,)/i',
            '/lokasi[:\s]+(.+?)(?:\.|,)/i',
        );
        
        $full_text = implode( ' ', $paragraphs );
        
        foreach ( $location_patterns as $pattern ) {
            if ( preg_match( $pattern, $full_text, $matches ) ) {
                return trim( $matches[1] );
            }
        }
        
        return '';
    }

    /**
     * Extract operating hours information
     *
     * @param array $paragraphs Content paragraphs
     * @return string
     */
    private function extract_hours_info( $paragraphs ) {
        $hours_patterns = array(
            '/(?:jam buka|buka)[:\s]+(.+?)(?:\.|,|$)/i',
            '/(?:jam operasional)[:\s]+(.+?)(?:\.|,|$)/i',
            '/(\d{1,2}[:.]\d{2}\s*-\s*\d{1,2}[:.]\d{2})/i',
            '/buka (?:dari )?(?:pukul )?(\d{1,2}[:.]\d{2}.+?)(?:\.|,|$)/i',
        );
        
        $full_text = implode( ' ', $paragraphs );
        
        foreach ( $hours_patterns as $pattern ) {
            if ( preg_match( $pattern, $full_text, $matches ) ) {
                return trim( $matches[1] );
            }
        }
        
        return '';
    }

    /**
     * Extract price information
     *
     * @param array $paragraphs Content paragraphs
     * @return string
     */
    private function extract_price_info( $paragraphs ) {
        $price_patterns = array(
            '/(?:harga tiket|tiket masuk|htm)[:\s]+(?:Rp\.?\s*)?(\d[\d.,]+)/i',
            '/(?:Rp\.?\s*)(\d[\d.,]+)(?:\s*per\s*orang)?/i',
            '/(?:biaya masuk)[:\s]+(?:Rp\.?\s*)?(\d[\d.,]+)/i',
        );
        
        $full_text = implode( ' ', $paragraphs );
        
        foreach ( $price_patterns as $pattern ) {
            if ( preg_match( $pattern, $full_text, $matches ) ) {
                return 'Rp ' . trim( $matches[1] );
            }
        }
        
        return '';
    }

    /**
     * Extract tips from content
     *
     * @param array $paragraphs Content paragraphs
     * @return array
     */
    private function extract_tips( $paragraphs ) {
        $tips = array();
        
        $tip_keywords = array(
            'tips', 'saran', 'disarankan', 'sebaiknya', 'jangan lupa', 'pastikan',
            'perhatikan', 'hindari', 'waktu terbaik', 'rekomendasi'
        );
        
        foreach ( $paragraphs as $paragraph ) {
            foreach ( $tip_keywords as $keyword ) {
                if ( stripos( $paragraph, $keyword ) !== false ) {
                    // Extract sentence containing the tip
                    $sentences = preg_split( '/(?<=[.!?])\s+/', $paragraph );
                    foreach ( $sentences as $sentence ) {
                        if ( stripos( $sentence, $keyword ) !== false && strlen( $sentence ) > 20 ) {
                            $tips[] = trim( $sentence );
                        }
                    }
                }
            }
        }
        
        return array_unique( array_slice( $tips, 0, 5 ) );
    }

    /**
     * Consolidate facts from all sources
     */
    private function consolidate_facts() {
        $all_facts = array();
        $all_tips = array();
        $locations = array();
        $hours = array();
        $prices = array();
        
        foreach ( $this->research_pack['sources'] as $source ) {
            if ( isset( $source['key_facts'] ) ) {
                $all_facts = array_merge( $all_facts, $source['key_facts'] );
            }
            if ( isset( $source['tips'] ) ) {
                $all_tips = array_merge( $all_tips, $source['tips'] );
            }
            if ( ! empty( $source['location'] ) ) {
                $locations[] = $source['location'];
            }
            if ( ! empty( $source['hours'] ) ) {
                $hours[] = $source['hours'];
            }
            if ( ! empty( $source['price'] ) ) {
                $prices[] = $source['price'];
            }
        }
        
        $this->research_pack['facts'] = array_unique( $all_facts );
        $this->research_pack['tips'] = array_unique( $all_tips );
        
        // Use most common/first found for location, hours, price
        $this->research_pack['location_info'] = ! empty( $locations ) ? $locations[0] : '';
        $this->research_pack['hours_info'] = ! empty( $hours ) ? $hours[0] : '';
        $this->research_pack['pricing_info'] = ! empty( $prices ) ? $prices[0] : '';
        
        // Extract keywords from all content
        $all_content = '';
        foreach ( $this->research_pack['sources'] as $source ) {
            if ( isset( $source['content_summary'] ) ) {
                $all_content .= ' ' . $source['content_summary'];
            }
        }
        
        $extracted_keywords = tsa_extract_keywords( $all_content, 15 );
        $this->research_pack['keywords'] = array_unique(
            array_merge( $this->research_pack['keywords'], $extracted_keywords )
        );
    }

    /**
     * Check if AI API is available
     *
     * @return bool
     */
    private function has_ai_api() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        return ! empty( $api_key );
    }

    /**
     * Enhance research pack with AI
     */
    private function enhance_with_ai() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $api_endpoint = tsa_get_option( 'openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        if ( empty( $api_key ) ) {
            return;
        }
        
        tsa_log_job( $this->job_id, 'Research Agent: Enhancing with AI...' );
        
        // Prepare content summary for AI
        $content_for_ai = "Topik: " . $this->title . "\n\n";
        $content_for_ai .= "Fakta yang ditemukan:\n";
        foreach ( $this->research_pack['facts'] as $fact ) {
            $content_for_ai .= "- " . $fact . "\n";
        }
        
        $prompt = "Berdasarkan informasi berikut tentang destinasi wisata, buatlah ringkasan terstruktur dalam format JSON dengan field: summary (ringkasan 2-3 kalimat), highlights (array 5 poin menarik), best_time (waktu terbaik berkunjung), target_audience (siapa yang cocok berkunjung). Gunakan Bahasa Indonesia.\n\n" . $content_for_ai;
        
        $response = wp_remote_post( $api_endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'Kamu adalah asisten peneliti wisata yang ahli dalam menganalisis dan meringkas informasi destinasi wisata Indonesia.',
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Research Agent: AI enhancement failed - ' . $response->get_error_message() );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $ai_response = $data['choices'][0]['message']['content'];
            
            // Try to parse JSON from response
            if ( preg_match( '/\{[\s\S]*\}/', $ai_response, $matches ) ) {
                $ai_data = json_decode( $matches[0], true );
                if ( $ai_data ) {
                    $this->research_pack['ai_enhanced'] = $ai_data;
                }
            }
        }
        
        tsa_log_job( $this->job_id, 'Research Agent: AI enhancement completed.' );
    }

    /**
     * Get the research pack
     *
     * @return array
     */
    public function get_research_pack() {
        return $this->research_pack;
    }
}
