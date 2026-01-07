<?php
/**
 * Helper functions for the plugin
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * Get plugin option with default value
 *
 * @param string $key Option key
 * @param mixed $default Default value
 * @return mixed
 */
function tsa_get_option( $key, $default = '' ) {
    $options = get_option( 'tsa_settings', array() );
    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Update plugin option
 *
 * @param string $key Option key
 * @param mixed $value Option value
 * @return bool
 */
function tsa_update_option( $key, $value ) {
    $options = get_option( 'tsa_settings', array() );
    $options[ $key ] = $value;
    return update_option( 'tsa_settings', $options );
}

/**
 * Get all jobs
 *
 * @param array $args Query arguments
 * @return array
 */
function tsa_get_jobs( $args = array() ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_jobs';
    
    $defaults = array(
        'status' => '',
        'campaign_id' => 0,
        'limit' => 20,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $where = array( '1=1' );
    
    if ( ! empty( $args['status'] ) ) {
        $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
    }
    
    if ( ! empty( $args['campaign_id'] ) ) {
        $where[] = $wpdb->prepare( 'campaign_id = %d', $args['campaign_id'] );
    }
    
    $where_clause = implode( ' AND ', $where );
    $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
    
    $sql = $wpdb->prepare(
        "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d",
        $args['limit'],
        $args['offset']
    );
    
    return $wpdb->get_results( $sql );
}

/**
 * Get single job by ID
 *
 * @param int $job_id Job ID
 * @return object|null
 */
function tsa_get_job( $job_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_jobs';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $job_id ) );
}

/**
 * Create a new job
 *
 * @param array $data Job data
 * @return int|false Job ID or false on failure
 */
function tsa_create_job( $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_jobs';
    
    $defaults = array(
        'campaign_id' => null,
        'title_input' => '',
        'status' => 'queued',
        'settings' => '{}',
        'research_pack' => '{}',
        'draft_pack' => '{}',
        'log' => ''
    );
    
    $data = wp_parse_args( $data, $defaults );
    
    $result = $wpdb->insert( $table, $data );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update job
 *
 * @param int $job_id Job ID
 * @param array $data Data to update
 * @return bool
 */
function tsa_update_job( $job_id, $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_jobs';
    
    return $wpdb->update( $table, $data, array( 'id' => $job_id ) ) !== false;
}

/**
 * Delete job
 *
 * @param int $job_id Job ID
 * @return bool
 */
function tsa_delete_job( $job_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_jobs';
    
    return $wpdb->delete( $table, array( 'id' => $job_id ) ) !== false;
}

/**
 * Get all campaigns
 *
 * @return array
 */
function tsa_get_campaigns() {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_campaigns';
    return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
}

/**
 * Create a new campaign
 *
 * @param string $name Campaign name
 * @return int|false Campaign ID or false on failure
 */
function tsa_create_campaign( $name ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tsa_campaigns';
    
    $result = $wpdb->insert( $table, array( 'name' => $name ) );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Log message to job
 *
 * @param int $job_id Job ID
 * @param string $message Log message
 * @return bool
 */
function tsa_log_job( $job_id, $message ) {
    $job = tsa_get_job( $job_id );
    if ( ! $job ) {
        return false;
    }
    
    $timestamp = current_time( 'mysql' );
    $new_log = "[{$timestamp}] {$message}\n" . $job->log;
    
    return tsa_update_job( $job_id, array( 'log' => $new_log ) );
}

/**
 * Get job status label
 *
 * @param string $status Status key
 * @return string
 */
function tsa_get_status_label( $status ) {
    $labels = array(
        'queued' => __( 'Queued', 'travelseo-autopublisher' ),
        'researching' => __( 'Researching', 'travelseo-autopublisher' ),
        'drafting' => __( 'Drafting', 'travelseo-autopublisher' ),
        'qa' => __( 'QA Review', 'travelseo-autopublisher' ),
        'image_planning' => __( 'Image Planning', 'travelseo-autopublisher' ),
        'ready' => __( 'Ready', 'travelseo-autopublisher' ),
        'pushed' => __( 'Pushed to WP', 'travelseo-autopublisher' ),
        'failed' => __( 'Failed', 'travelseo-autopublisher' ),
    );
    
    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

/**
 * Get job status color class
 *
 * @param string $status Status key
 * @return string
 */
function tsa_get_status_color( $status ) {
    $colors = array(
        'queued' => 'gray',
        'researching' => 'blue',
        'drafting' => 'blue',
        'qa' => 'orange',
        'image_planning' => 'purple',
        'ready' => 'green',
        'pushed' => 'teal',
        'failed' => 'red',
    );
    
    return isset( $colors[ $status ] ) ? $colors[ $status ] : 'gray';
}

/**
 * Sanitize and validate URL
 *
 * @param string $url URL to validate
 * @return string|false
 */
function tsa_validate_url( $url ) {
    $url = esc_url_raw( $url );
    
    if ( empty( $url ) ) {
        return false;
    }
    
    // Only allow http and https protocols
    $parsed = wp_parse_url( $url );
    if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
        return false;
    }
    
    // Block internal IPs for security
    $host = $parsed['host'];
    $ip = gethostbyname( $host );
    
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        // This is a private or reserved IP, block it
        return false;
    }
    
    return $url;
}

/**
 * Check if robots.txt allows crawling
 *
 * @param string $url URL to check
 * @return bool
 */
function tsa_check_robots_txt( $url ) {
    $respect_robots = tsa_get_option( 'respect_robots', true );
    
    if ( ! $respect_robots ) {
        return true;
    }
    
    $parsed = wp_parse_url( $url );
    $robots_url = $parsed['scheme'] . '://' . $parsed['host'] . '/robots.txt';
    
    $response = wp_remote_get( $robots_url, array(
        'timeout' => 5,
        'user-agent' => 'TravelSEO-Bot/1.0'
    ) );
    
    if ( is_wp_error( $response ) ) {
        return true; // Allow if we can't fetch robots.txt
    }
    
    $body = wp_remote_retrieve_body( $response );
    
    // Simple robots.txt parser
    $lines = explode( "\n", $body );
    $user_agent_match = false;
    
    foreach ( $lines as $line ) {
        $line = trim( $line );
        
        if ( stripos( $line, 'user-agent:' ) === 0 ) {
            $agent = trim( substr( $line, 11 ) );
            $user_agent_match = ( $agent === '*' || stripos( $agent, 'TravelSEO' ) !== false );
        }
        
        if ( $user_agent_match && stripos( $line, 'disallow:' ) === 0 ) {
            $path = trim( substr( $line, 9 ) );
            if ( $path === '/' ) {
                return false; // Disallowed
            }
            
            $url_path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
            if ( strpos( $url_path, $path ) === 0 ) {
                return false; // Disallowed
            }
        }
    }
    
    return true;
}

/**
 * Extract keywords from text
 *
 * @param string $text Text to analyze
 * @param int $limit Maximum keywords to return
 * @return array
 */
function tsa_extract_keywords( $text, $limit = 10 ) {
    // Remove HTML tags
    $text = wp_strip_all_tags( $text );
    
    // Convert to lowercase
    $text = strtolower( $text );
    
    // Remove special characters
    $text = preg_replace( '/[^a-z0-9\s]/', '', $text );
    
    // Split into words
    $words = preg_split( '/\s+/', $text );
    
    // Stop words in Indonesian and English
    $stop_words = array(
        'yang', 'dan', 'di', 'ke', 'dari', 'ini', 'itu', 'dengan', 'untuk', 'pada',
        'adalah', 'sebagai', 'dalam', 'tidak', 'akan', 'atau', 'juga', 'sudah',
        'bisa', 'ada', 'lebih', 'sangat', 'saat', 'oleh', 'karena', 'seperti',
        'the', 'and', 'is', 'in', 'to', 'of', 'a', 'for', 'on', 'with', 'as',
        'at', 'by', 'an', 'be', 'this', 'that', 'it', 'from', 'or', 'are'
    );
    
    // Count word frequency
    $word_count = array();
    foreach ( $words as $word ) {
        if ( strlen( $word ) < 3 ) {
            continue;
        }
        if ( in_array( $word, $stop_words, true ) ) {
            continue;
        }
        if ( ! isset( $word_count[ $word ] ) ) {
            $word_count[ $word ] = 0;
        }
        $word_count[ $word ]++;
    }
    
    // Sort by frequency
    arsort( $word_count );
    
    // Return top keywords
    return array_slice( array_keys( $word_count ), 0, $limit );
}

/**
 * Generate meta description from content
 *
 * @param string $content Article content
 * @param int $max_length Maximum length
 * @return string
 */
function tsa_generate_meta_description( $content, $max_length = 160 ) {
    // Remove HTML tags
    $text = wp_strip_all_tags( $content );
    
    // Remove extra whitespace
    $text = preg_replace( '/\s+/', ' ', $text );
    $text = trim( $text );
    
    // Truncate to max length
    if ( strlen( $text ) > $max_length ) {
        $text = substr( $text, 0, $max_length - 3 );
        // Cut at last complete word
        $text = substr( $text, 0, strrpos( $text, ' ' ) );
        $text .= '...';
    }
    
    return $text;
}

/**
 * Calculate keyword density
 *
 * @param string $content Article content
 * @param string $keyword Keyword to check
 * @return float
 */
function tsa_calculate_keyword_density( $content, $keyword ) {
    $text = strtolower( wp_strip_all_tags( $content ) );
    $keyword = strtolower( $keyword );
    
    $word_count = str_word_count( $text );
    $keyword_count = substr_count( $text, $keyword );
    
    if ( $word_count === 0 ) {
        return 0;
    }
    
    return round( ( $keyword_count / $word_count ) * 100, 2 );
}

/**
 * Get travel-related keyword variations
 *
 * @param string $keyword Base keyword
 * @return array
 */
function tsa_get_keyword_variations( $keyword ) {
    $variations = array(
        $keyword,
        $keyword . ' harga tiket',
        $keyword . ' jam buka',
        $keyword . ' rute',
        $keyword . ' parkir',
        $keyword . ' review',
        $keyword . ' terdekat',
        $keyword . ' ' . date( 'Y' ),
        $keyword . ' ramai jam berapa',
        $keyword . ' tips',
        $keyword . ' rekomendasi',
        'wisata ' . $keyword,
        'destinasi ' . $keyword,
        'tempat wisata ' . $keyword,
    );
    
    return $variations;
}

/**
 * Generate FAQ questions for travel content
 *
 * @param string $topic Topic/title
 * @return array
 */
function tsa_generate_faq_questions( $topic ) {
    $questions = array(
        'Berapa harga tiket masuk ' . $topic . '?',
        'Jam berapa ' . $topic . ' buka dan tutup?',
        'Bagaimana cara menuju ' . $topic . '?',
        'Apa saja fasilitas yang tersedia di ' . $topic . '?',
        'Kapan waktu terbaik untuk mengunjungi ' . $topic . '?',
        'Apakah ' . $topic . ' cocok untuk anak-anak?',
        'Berapa lama waktu yang dibutuhkan untuk menjelajahi ' . $topic . '?',
        'Apa saja yang bisa dilakukan di ' . $topic . '?',
        'Apakah ada restoran atau tempat makan di sekitar ' . $topic . '?',
        'Bagaimana tips hemat berkunjung ke ' . $topic . '?',
    );
    
    return $questions;
}
