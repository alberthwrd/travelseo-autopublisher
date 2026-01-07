<?php
/**
 * Image Agent - Image Planning & Recommendations
 *
 * This agent is responsible for:
 * - Analyzing content to determine image placement
 * - Generating image search keywords
 * - Fetching images from APIs (Unsplash/Pexels) if configured
 * - Searching WordPress Media Library
 * - Generating alt text suggestions
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

/**
 * Image Agent Class
 */
class Image_Agent {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Draft pack from previous agents
     *
     * @var array
     */
    private $draft_pack;

    /**
     * Image recommendations
     *
     * @var array
     */
    private $image_recommendations;

    /**
     * Constructor
     *
     * @param int $job_id Job ID
     * @param array $draft_pack Draft pack from previous agents
     */
    public function __construct( $job_id, $draft_pack ) {
        $this->job_id = $job_id;
        $this->draft_pack = $draft_pack;
        $this->image_recommendations = array();
    }

    /**
     * Run the image planning process
     *
     * @return array Updated draft pack
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Image Agent: Starting image planning...' );
        
        // Update job status
        tsa_update_job( $this->job_id, array( 'status' => 'image_planning' ) );

        // Step 1: Analyze content and identify image placement points
        $this->analyze_content();

        // Step 2: Generate image search keywords for each placement
        $this->generate_keywords();

        // Step 3: Check image mode and fetch if needed
        $image_mode = tsa_get_option( 'image_mode', 'recommend' );
        
        switch ( $image_mode ) {
            case 'auto_fetch':
                $this->fetch_images_from_api();
                break;
            case 'media_library':
                $this->search_media_library();
                break;
            case 'recommend':
            default:
                // Just keep recommendations
                break;
        }

        // Step 4: Generate alt text suggestions
        $this->generate_alt_texts();

        // Add image recommendations to draft pack
        $this->draft_pack['image_recommendations'] = $this->image_recommendations;

        // Step 5: Insert image placeholders if auto mode
        if ( $image_mode === 'auto_fetch' || $image_mode === 'media_library' ) {
            $this->insert_image_placeholders();
        }

        tsa_log_job( $this->job_id, 'Image Agent: Completed. ' . count( $this->image_recommendations ) . ' image recommendations.' );

        return $this->draft_pack;
    }

    /**
     * Analyze content to identify image placement points
     */
    private function analyze_content() {
        $content = $this->draft_pack['content'];
        $title = $this->draft_pack['title'];
        
        // Find all H2 headings
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE );
        
        $placements = array();
        
        // Add hero image at the beginning
        $placements[] = array(
            'position' => 'hero',
            'after_element' => 'start',
            'section' => 'Hero Image',
            'description' => 'Gambar utama di awal artikel',
            'priority' => 1,
        );
        
        // Add images after specific sections
        $image_worthy_sections = array(
            'sekilas' => 'Overview',
            'lokasi' => 'Location',
            'aktivitas' => 'Activities',
            'daya tarik' => 'Attractions',
            'fasilitas' => 'Facilities',
            'tips' => 'Tips',
            'rekomendasi' => 'Recommendations',
        );
        
        foreach ( $matches[1] as $index => $match ) {
            $heading_text = strtolower( strip_tags( $match[0] ) );
            $offset = $match[1];
            
            foreach ( $image_worthy_sections as $keyword => $section_name ) {
                if ( strpos( $heading_text, $keyword ) !== false ) {
                    $placements[] = array(
                        'position' => 'after_h2_' . $index,
                        'after_element' => 'h2',
                        'heading' => $match[0],
                        'section' => $section_name,
                        'description' => 'Gambar untuk section ' . $section_name,
                        'priority' => $index + 2,
                    );
                    break;
                }
            }
        }
        
        // Limit to reasonable number of images
        $this->image_recommendations = array_slice( $placements, 0, 7 );
        
        tsa_log_job( $this->job_id, 'Image Agent: Identified ' . count( $this->image_recommendations ) . ' image placements.' );
    }

    /**
     * Generate image search keywords for each placement
     */
    private function generate_keywords() {
        $title = $this->draft_pack['title'];
        
        foreach ( $this->image_recommendations as &$rec ) {
            $keywords = array();
            
            switch ( $rec['section'] ) {
                case 'Hero Image':
                    $keywords = array(
                        $title,
                        $title . ' pemandangan',
                        $title . ' landscape',
                        'wisata ' . $title,
                    );
                    break;
                    
                case 'Overview':
                    $keywords = array(
                        $title . ' view',
                        $title . ' panorama',
                        'tempat wisata ' . $title,
                    );
                    break;
                    
                case 'Location':
                    $keywords = array(
                        $title . ' lokasi',
                        $title . ' map',
                        $title . ' akses',
                        'jalan menuju ' . $title,
                    );
                    break;
                    
                case 'Activities':
                case 'Attractions':
                    $keywords = array(
                        $title . ' aktivitas',
                        $title . ' wisatawan',
                        $title . ' pengunjung',
                        'kegiatan di ' . $title,
                    );
                    break;
                    
                case 'Facilities':
                    $keywords = array(
                        $title . ' fasilitas',
                        $title . ' area parkir',
                        $title . ' toilet',
                    );
                    break;
                    
                case 'Tips':
                    $keywords = array(
                        $title . ' tips',
                        'berkunjung ke ' . $title,
                        $title . ' guide',
                    );
                    break;
                    
                case 'Recommendations':
                    $keywords = array(
                        'wisata dekat ' . $title,
                        'tempat wisata sekitar',
                        'destinasi terdekat',
                    );
                    break;
                    
                default:
                    $keywords = array(
                        $title,
                        'wisata ' . $title,
                    );
            }
            
            $rec['search_keywords'] = $keywords;
            $rec['primary_keyword'] = $keywords[0];
        }
    }

    /**
     * Fetch images from API (Unsplash/Pexels)
     */
    private function fetch_images_from_api() {
        $unsplash_key = tsa_get_option( 'unsplash_api_key', '' );
        $pexels_key = tsa_get_option( 'pexels_api_key', '' );
        
        if ( empty( $unsplash_key ) && empty( $pexels_key ) ) {
            tsa_log_job( $this->job_id, 'Image Agent: No image API keys configured. Skipping auto-fetch.' );
            return;
        }
        
        foreach ( $this->image_recommendations as &$rec ) {
            $keyword = $rec['primary_keyword'];
            
            // Try Unsplash first
            if ( ! empty( $unsplash_key ) ) {
                $image = $this->fetch_from_unsplash( $keyword, $unsplash_key );
                if ( $image ) {
                    $rec['fetched_image'] = $image;
                    $rec['image_source'] = 'unsplash';
                    continue;
                }
            }
            
            // Fallback to Pexels
            if ( ! empty( $pexels_key ) ) {
                $image = $this->fetch_from_pexels( $keyword, $pexels_key );
                if ( $image ) {
                    $rec['fetched_image'] = $image;
                    $rec['image_source'] = 'pexels';
                }
            }
        }
        
        tsa_log_job( $this->job_id, 'Image Agent: Fetched images from API.' );
    }

    /**
     * Fetch image from Unsplash
     *
     * @param string $keyword Search keyword
     * @param string $api_key API key
     * @return array|false
     */
    private function fetch_from_unsplash( $keyword, $api_key ) {
        $url = 'https://api.unsplash.com/search/photos?' . http_build_query( array(
            'query' => $keyword,
            'per_page' => 1,
            'orientation' => 'landscape',
        ) );
        
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Client-ID ' . $api_key,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['results'][0] ) ) {
            $result = $data['results'][0];
            return array(
                'url' => $result['urls']['regular'],
                'thumb' => $result['urls']['thumb'],
                'photographer' => $result['user']['name'],
                'photographer_url' => $result['user']['links']['html'],
                'source_url' => $result['links']['html'],
                'width' => $result['width'],
                'height' => $result['height'],
            );
        }
        
        return false;
    }

    /**
     * Fetch image from Pexels
     *
     * @param string $keyword Search keyword
     * @param string $api_key API key
     * @return array|false
     */
    private function fetch_from_pexels( $keyword, $api_key ) {
        $url = 'https://api.pexels.com/v1/search?' . http_build_query( array(
            'query' => $keyword,
            'per_page' => 1,
            'orientation' => 'landscape',
        ) );
        
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => $api_key,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['photos'][0] ) ) {
            $result = $data['photos'][0];
            return array(
                'url' => $result['src']['large'],
                'thumb' => $result['src']['medium'],
                'photographer' => $result['photographer'],
                'photographer_url' => $result['photographer_url'],
                'source_url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
            );
        }
        
        return false;
    }

    /**
     * Search WordPress Media Library
     */
    private function search_media_library() {
        foreach ( $this->image_recommendations as &$rec ) {
            $keyword = $rec['primary_keyword'];
            
            // Search in media library
            $attachments = get_posts( array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                's' => $keyword,
                'posts_per_page' => 1,
            ) );
            
            if ( ! empty( $attachments ) ) {
                $attachment = $attachments[0];
                $rec['media_library_image'] = array(
                    'id' => $attachment->ID,
                    'url' => wp_get_attachment_url( $attachment->ID ),
                    'title' => $attachment->post_title,
                );
                $rec['image_source'] = 'media_library';
            }
        }
        
        tsa_log_job( $this->job_id, 'Image Agent: Searched media library.' );
    }

    /**
     * Generate alt text suggestions
     */
    private function generate_alt_texts() {
        $title = $this->draft_pack['title'];
        
        foreach ( $this->image_recommendations as &$rec ) {
            $section = $rec['section'];
            
            // Generate SEO-friendly alt text
            $alt_texts = array();
            
            switch ( $section ) {
                case 'Hero Image':
                    $alt_texts = array(
                        $title . ' - Destinasi Wisata Populer',
                        'Pemandangan ' . $title,
                        'Wisata ' . $title . ' ' . date( 'Y' ),
                    );
                    break;
                    
                case 'Location':
                    $alt_texts = array(
                        'Lokasi ' . $title,
                        'Peta menuju ' . $title,
                        'Akses ke ' . $title,
                    );
                    break;
                    
                case 'Activities':
                case 'Attractions':
                    $alt_texts = array(
                        'Aktivitas wisata di ' . $title,
                        'Pengunjung menikmati ' . $title,
                        'Daya tarik ' . $title,
                    );
                    break;
                    
                default:
                    $alt_texts = array(
                        $title . ' - ' . $section,
                        $section . ' di ' . $title,
                    );
            }
            
            $rec['suggested_alt_text'] = $alt_texts[0];
            $rec['alt_text_options'] = $alt_texts;
        }
    }

    /**
     * Insert image placeholders into content
     */
    private function insert_image_placeholders() {
        $content = $this->draft_pack['content'];
        
        // Insert hero image at the beginning
        $hero_placeholder = $this->create_image_placeholder( $this->image_recommendations[0] );
        $content = $hero_placeholder . "\n\n" . $content;
        
        // Insert other images after their respective H2 headings
        foreach ( array_slice( $this->image_recommendations, 1 ) as $rec ) {
            if ( isset( $rec['heading'] ) ) {
                $heading = $rec['heading'];
                $placeholder = $this->create_image_placeholder( $rec );
                
                // Find the heading and insert image after the next paragraph
                $pattern = '/(<h2[^>]*>' . preg_quote( $heading, '/' ) . '<\/h2>.*?<\/p>)/is';
                $content = preg_replace( $pattern, '$1' . "\n\n" . $placeholder, $content, 1 );
            }
        }
        
        $this->draft_pack['content'] = $content;
    }

    /**
     * Create image placeholder HTML
     *
     * @param array $rec Image recommendation
     * @return string
     */
    private function create_image_placeholder( $rec ) {
        $alt_text = $rec['suggested_alt_text'] ?? '';
        $caption = $rec['section'] ?? '';
        
        // Check if we have a fetched image
        if ( isset( $rec['fetched_image'] ) ) {
            $image_url = $rec['fetched_image']['url'];
            $source = $rec['image_source'];
            $photographer = $rec['fetched_image']['photographer'] ?? '';
            
            $credit = '';
            if ( $source === 'unsplash' && $photographer ) {
                $credit = '<figcaption>Photo by ' . esc_html( $photographer ) . ' on Unsplash</figcaption>';
            } elseif ( $source === 'pexels' && $photographer ) {
                $credit = '<figcaption>Photo by ' . esc_html( $photographer ) . ' on Pexels</figcaption>';
            }
            
            return '<figure class="wp-block-image size-large">
<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt_text ) . '" />
' . $credit . '
</figure>';
        }
        
        // Check if we have a media library image
        if ( isset( $rec['media_library_image'] ) ) {
            $image_url = $rec['media_library_image']['url'];
            
            return '<figure class="wp-block-image size-large">
<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt_text ) . '" />
</figure>';
        }
        
        // Return placeholder comment
        return '<!-- IMAGE PLACEHOLDER: ' . esc_html( $rec['primary_keyword'] ) . ' | Alt: ' . esc_html( $alt_text ) . ' -->';
    }

    /**
     * Get the draft pack
     *
     * @return array
     */
    public function get_draft_pack() {
        return $this->draft_pack;
    }

    /**
     * Get image recommendations
     *
     * @return array
     */
    public function get_image_recommendations() {
        return $this->image_recommendations;
    }
}
