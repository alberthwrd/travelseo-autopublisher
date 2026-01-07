<?php
/**
 * WordPress Post Pusher
 *
 * Handles pushing generated articles to WordPress posts,
 * including category/tag assignment and SEO plugin integration.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * WP Post Pusher Class
 */
class WP_Post_Pusher {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Job data
     *
     * @var object
     */
    private $job;

    /**
     * Draft pack
     *
     * @var array
     */
    private $draft_pack;

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     *
     * @param int $job_id Job ID
     */
    public function __construct( $job_id ) {
        global $wpdb;
        
        $this->job_id = $job_id;
        
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $this->job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );
        
        if ( $this->job ) {
            $this->draft_pack = json_decode( $this->job->draft_pack, true ) ?: array();
            $this->settings = json_decode( $this->job->settings, true ) ?: array();
        }
    }

    /**
     * Push article to WordPress
     *
     * @param string $publish_mode Override publish mode (draft, publish, pending)
     * @return int|false Post ID on success, false on failure
     */
    public function push( $publish_mode = null ) {
        if ( ! $this->job || empty( $this->draft_pack ) ) {
            return false;
        }
        
        // Determine publish mode
        if ( $publish_mode === null ) {
            $publish_mode = isset( $this->settings['publish_mode'] ) ? $this->settings['publish_mode'] : 'draft';
        }
        
        // Prepare post data
        $post_data = array(
            'post_title'   => $this->draft_pack['title'],
            'post_content' => $this->prepare_content(),
            'post_excerpt' => $this->draft_pack['meta_description'] ?? '',
            'post_status'  => $publish_mode,
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
            'post_name'    => $this->draft_pack['slug'] ?? '',
        );
        
        // Handle scheduled posts
        if ( $publish_mode === 'schedule' && isset( $this->settings['schedule_date'] ) ) {
            $post_data['post_status'] = 'future';
            $post_data['post_date'] = $this->settings['schedule_date'];
            $post_data['post_date_gmt'] = get_gmt_from_date( $this->settings['schedule_date'] );
        }
        
        // Insert the post
        $post_id = wp_insert_post( $post_data, true );
        
        if ( is_wp_error( $post_id ) ) {
            tsa_log_job( $this->job_id, 'Failed to create post: ' . $post_id->get_error_message() );
            return false;
        }
        
        // Assign category
        $this->assign_category( $post_id );
        
        // Assign tags
        $this->assign_tags( $post_id );
        
        // Set featured image if available
        $this->set_featured_image( $post_id );
        
        // Set SEO meta
        $this->set_seo_meta( $post_id );
        
        // Update job status
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $wpdb->update( $table_jobs, array(
            'status' => 'pushed',
            'post_id' => $post_id,
        ), array( 'id' => $this->job_id ) );
        
        tsa_log_job( $this->job_id, "Article pushed to WordPress. Post ID: {$post_id}" );
        
        return $post_id;
    }

    /**
     * Prepare content for WordPress
     *
     * @return string
     */
    private function prepare_content() {
        $content = $this->draft_pack['content'];
        
        // Add FAQ schema if available
        if ( ! empty( $this->draft_pack['faq'] ) ) {
            $content .= $this->generate_faq_section();
        }
        
        // Convert image placeholders to proper HTML
        $content = $this->process_image_placeholders( $content );
        
        // Add internal links
        $content = $this->add_internal_links( $content );
        
        return $content;
    }

    /**
     * Generate FAQ section with schema markup
     *
     * @return string
     */
    private function generate_faq_section() {
        $faq = $this->draft_pack['faq'];
        
        $html = "\n\n<h2>FAQ - Pertanyaan yang Sering Diajukan</h2>\n";
        $html .= '<div class="tsa-faq-section" itemscope itemtype="https://schema.org/FAQPage">' . "\n";
        
        foreach ( $faq as $item ) {
            $html .= '<div class="tsa-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' . "\n";
            $html .= '<h3 itemprop="name">' . esc_html( $item['question'] ) . '</h3>' . "\n";
            $html .= '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">' . "\n";
            $html .= '<p itemprop="text">' . esc_html( $item['answer'] ) . '</p>' . "\n";
            $html .= '</div></div>' . "\n";
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Process image placeholders
     *
     * @param string $content Content
     * @return string
     */
    private function process_image_placeholders( $content ) {
        // Replace placeholder comments with actual images if available
        if ( ! empty( $this->draft_pack['image_recommendations'] ) ) {
            foreach ( $this->draft_pack['image_recommendations'] as $rec ) {
                if ( isset( $rec['fetched_image'] ) || isset( $rec['media_library_image'] ) ) {
                    $image_url = $rec['fetched_image']['url'] ?? $rec['media_library_image']['url'] ?? '';
                    $alt_text = $rec['suggested_alt_text'] ?? '';
                    
                    $placeholder = '<!-- IMAGE PLACEHOLDER: ' . preg_quote( $rec['primary_keyword'], '/' ) . ' .*?-->';
                    $replacement = '<figure class="wp-block-image size-large"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt_text ) . '" /></figure>';
                    
                    $content = preg_replace( '/' . $placeholder . '/', $replacement, $content, 1 );
                }
            }
        }
        
        return $content;
    }

    /**
     * Add internal links to content
     *
     * @param string $content Content
     * @return string
     */
    private function add_internal_links( $content ) {
        if ( empty( $this->draft_pack['internal_links'] ) ) {
            return $content;
        }
        
        foreach ( $this->draft_pack['internal_links'] as $link ) {
            $keyword = $link['keyword'];
            $url = $link['url'];
            $title = $link['title'];
            
            // Only replace first occurrence
            $pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b(?![^<]*>)/i';
            $replacement = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '">$1</a>';
            
            $content = preg_replace( $pattern, $replacement, $content, 1 );
        }
        
        return $content;
    }

    /**
     * Assign category to post
     *
     * @param int $post_id Post ID
     */
    private function assign_category( $post_id ) {
        $category_id = null;
        
        // Check if specific category was selected
        if ( isset( $this->settings['category_id'] ) && $this->settings['category_id'] > 0 ) {
            $category_id = $this->settings['category_id'];
        } elseif ( isset( $this->draft_pack['category_name'] ) ) {
            // Auto-detect or create category
            $category_name = $this->draft_pack['category_name'];
            
            // Check if category exists
            $existing = get_term_by( 'name', $category_name, 'category' );
            
            if ( $existing ) {
                $category_id = $existing->term_id;
            } else {
                // Create new category
                $result = wp_insert_term( $category_name, 'category', array(
                    'slug' => sanitize_title( $category_name ),
                ) );
                
                if ( ! is_wp_error( $result ) ) {
                    $category_id = $result['term_id'];
                }
            }
        }
        
        if ( $category_id ) {
            wp_set_post_categories( $post_id, array( $category_id ) );
        }
    }

    /**
     * Assign tags to post
     *
     * @param int $post_id Post ID
     */
    private function assign_tags( $post_id ) {
        if ( empty( $this->draft_pack['tag_names'] ) ) {
            return;
        }
        
        $tags = $this->draft_pack['tag_names'];
        
        // Limit to configured number
        $tag_count = isset( $this->settings['tag_count'] ) ? $this->settings['tag_count'] : 5;
        $tags = array_slice( $tags, 0, $tag_count );
        
        wp_set_post_tags( $post_id, $tags, false );
    }

    /**
     * Set featured image
     *
     * @param int $post_id Post ID
     */
    private function set_featured_image( $post_id ) {
        // Check if we have a hero image
        if ( empty( $this->draft_pack['image_recommendations'] ) ) {
            return;
        }
        
        $hero_image = null;
        foreach ( $this->draft_pack['image_recommendations'] as $rec ) {
            if ( $rec['position'] === 'hero' ) {
                $hero_image = $rec;
                break;
            }
        }
        
        if ( ! $hero_image ) {
            return;
        }
        
        // Check if image is from media library
        if ( isset( $hero_image['media_library_image']['id'] ) ) {
            set_post_thumbnail( $post_id, $hero_image['media_library_image']['id'] );
            return;
        }
        
        // Download and attach external image
        if ( isset( $hero_image['fetched_image']['url'] ) ) {
            $image_url = $hero_image['fetched_image']['url'];
            $attachment_id = $this->sideload_image( $image_url, $post_id, $hero_image['suggested_alt_text'] ?? '' );
            
            if ( $attachment_id ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
    }

    /**
     * Sideload image from URL
     *
     * @param string $url Image URL
     * @param int $post_id Post ID
     * @param string $alt_text Alt text
     * @return int|false Attachment ID or false
     */
    private function sideload_image( $url, $post_id, $alt_text = '' ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Download file to temp location
        $tmp = download_url( $url );
        
        if ( is_wp_error( $tmp ) ) {
            return false;
        }
        
        // Get file info
        $file_array = array(
            'name' => basename( parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        );
        
        // Ensure proper extension
        if ( ! preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $file_array['name'] ) ) {
            $file_array['name'] .= '.jpg';
        }
        
        // Upload to media library
        $attachment_id = media_handle_sideload( $file_array, $post_id );
        
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return false;
        }
        
        // Set alt text
        if ( $alt_text ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
        }
        
        return $attachment_id;
    }

    /**
     * Set SEO meta data
     *
     * @param int $post_id Post ID
     */
    private function set_seo_meta( $post_id ) {
        $settings = get_option( 'tsa_settings', array() );
        $seo_plugin = isset( $settings['seo_plugin'] ) ? $settings['seo_plugin'] : 'auto';
        
        $meta_title = $this->draft_pack['meta_title'] ?? '';
        $meta_desc = $this->draft_pack['meta_description'] ?? '';
        $focus_keyword = $this->draft_pack['title'] ?? '';
        
        // Auto-detect SEO plugin
        if ( $seo_plugin === 'auto' ) {
            if ( defined( 'WPSEO_VERSION' ) ) {
                $seo_plugin = 'yoast';
            } elseif ( defined( 'RANK_MATH_VERSION' ) ) {
                $seo_plugin = 'rankmath';
            } else {
                $seo_plugin = 'none';
            }
        }
        
        switch ( $seo_plugin ) {
            case 'yoast':
                update_post_meta( $post_id, '_yoast_wpseo_title', $meta_title );
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
                update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyword );
                break;
                
            case 'rankmath':
                update_post_meta( $post_id, 'rank_math_title', $meta_title );
                update_post_meta( $post_id, 'rank_math_description', $meta_desc );
                update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
                break;
                
            default:
                // Store in custom meta for future use
                update_post_meta( $post_id, '_tsa_meta_title', $meta_title );
                update_post_meta( $post_id, '_tsa_meta_description', $meta_desc );
                break;
        }
    }

    /**
     * Create draft and redirect to editor
     *
     * @return string Editor URL
     */
    public function create_draft_and_get_editor_url() {
        $post_id = $this->push( 'draft' );
        
        if ( ! $post_id ) {
            return false;
        }
        
        return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
    }

    /**
     * Preview content without saving
     *
     * @return array Preview data
     */
    public function get_preview_data() {
        if ( ! $this->job || empty( $this->draft_pack ) ) {
            return array();
        }
        
        return array(
            'title' => $this->draft_pack['title'],
            'content' => $this->prepare_content(),
            'excerpt' => $this->draft_pack['meta_description'] ?? '',
            'category' => $this->draft_pack['category_name'] ?? '',
            'tags' => $this->draft_pack['tag_names'] ?? array(),
            'meta_title' => $this->draft_pack['meta_title'] ?? '',
            'meta_description' => $this->draft_pack['meta_description'] ?? '',
            'word_count' => $this->draft_pack['word_count'] ?? 0,
            'faq' => $this->draft_pack['faq'] ?? array(),
            'image_recommendations' => $this->draft_pack['image_recommendations'] ?? array(),
        );
    }
}
