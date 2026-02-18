<?php
/**
 * Topical Authority Cluster Builder
 *
 * Manages topic clusters for building topical authority in SEO.
 * A cluster consists of a pillar content and multiple supporting articles.
 *
 * @link       https://yourwebsite.com
 * @since      1.1.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/modules
 */

namespace TravelSEO_Autopublisher\Modules;

/**
 * Cluster Class
 *
 * Handles CRUD operations for topic clusters and manages
 * the relationship between pillar content and supporting articles.
 */
class Cluster {

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Cluster-Job relationship table name
     *
     * @var string
     */
    private $relation_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tsa_clusters';
        $this->relation_table = $wpdb->prefix . 'tsa_cluster_jobs';
    }

    /**
     * Check if feature is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        $settings = get_option( 'tsa_settings', array() );
        return ! empty( $settings['feature_topical_cluster'] );
    }

    /**
     * Create database tables for clusters
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Clusters table
        $clusters_table = $wpdb->prefix . 'tsa_clusters';
        $sql_clusters = "CREATE TABLE $clusters_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            pillar_keyword varchar(255) NOT NULL,
            pillar_post_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            article_count int(11) DEFAULT 0,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY pillar_keyword (pillar_keyword),
            KEY status (status)
        ) $charset_collate;";

        // Cluster-Job relationship table
        $relation_table = $wpdb->prefix . 'tsa_cluster_jobs';
        $sql_relation = "CREATE TABLE $relation_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cluster_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            role varchar(20) DEFAULT 'supporting',
            link_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cluster_job (cluster_id, job_id),
            KEY cluster_id (cluster_id),
            KEY job_id (job_id),
            KEY role (role)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_clusters );
        dbDelta( $sql_relation );

        // Log table creation
        tsa_log( 'Cluster tables created/updated', 'info' );
    }

    /**
     * Create a new cluster
     *
     * @param array $data Cluster data
     * @return int|false Cluster ID or false on failure
     */
    public function create( $data ) {
        global $wpdb;

        // Validate required fields
        if ( empty( $data['name'] ) || empty( $data['pillar_keyword'] ) ) {
            tsa_log( 'Cluster creation failed: missing required fields', 'error' );
            return false;
        }

        // Generate slug
        $slug = sanitize_title( $data['name'] );
        $original_slug = $slug;
        $counter = 1;

        // Ensure unique slug
        while ( $this->get_by_slug( $slug ) ) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        $insert_data = array(
            'name' => sanitize_text_field( $data['name'] ),
            'slug' => $slug,
            'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
            'pillar_keyword' => sanitize_text_field( $data['pillar_keyword'] ),
            'pillar_post_id' => isset( $data['pillar_post_id'] ) ? absint( $data['pillar_post_id'] ) : null,
            'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
            'meta_data' => isset( $data['meta_data'] ) ? wp_json_encode( $data['meta_data'] ) : null,
        );

        $result = $wpdb->insert( $this->table_name, $insert_data );

        if ( $result ) {
            $cluster_id = $wpdb->insert_id;
            tsa_log( "Cluster created: ID {$cluster_id}, Name: {$data['name']}", 'info' );
            return $cluster_id;
        }

        tsa_log( 'Cluster creation failed: ' . $wpdb->last_error, 'error' );
        return false;
    }

    /**
     * Get cluster by ID
     *
     * @param int $id Cluster ID
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ) );
    }

    /**
     * Get cluster by slug
     *
     * @param string $slug Cluster slug
     * @return object|null
     */
    public function get_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s",
            $slug
        ) );
    }

    /**
     * Update cluster
     *
     * @param int   $id   Cluster ID
     * @param array $data Data to update
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        $update_data = array();
        $allowed_fields = array( 'name', 'description', 'pillar_keyword', 'pillar_post_id', 'status', 'meta_data' );

        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                if ( $field === 'meta_data' && is_array( $data[ $field ] ) ) {
                    $update_data[ $field ] = wp_json_encode( $data[ $field ] );
                } else {
                    $update_data[ $field ] = $data[ $field ];
                }
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $wpdb->update( $this->table_name, $update_data, array( 'id' => $id ) );

        if ( $result !== false ) {
            tsa_log( "Cluster updated: ID {$id}", 'info' );
            return true;
        }

        return false;
    }

    /**
     * Delete cluster
     *
     * @param int $id Cluster ID
     * @return bool
     */
    public function delete( $id ) {
        global $wpdb;

        // First, remove all job relationships
        $wpdb->delete( $this->relation_table, array( 'cluster_id' => $id ) );

        // Then delete the cluster
        $result = $wpdb->delete( $this->table_name, array( 'id' => $id ) );

        if ( $result ) {
            tsa_log( "Cluster deleted: ID {$id}", 'info' );
            return true;
        }

        return false;
    }

    /**
     * Get all clusters with pagination
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(name LIKE %s OR pillar_keyword LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            $count_query = $wpdb->prepare( $count_query, $values );
        }
        $total = $wpdb->get_var( $count_query );

        // Get items
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $items = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

        return array(
            'items' => $items,
            'total' => (int) $total,
            'pages' => ceil( $total / $args['per_page'] ),
            'current_page' => $args['page'],
        );
    }

    /**
     * Add job to cluster
     *
     * @param int    $cluster_id Cluster ID
     * @param int    $job_id     Job ID
     * @param string $role       Role (pillar or supporting)
     * @return bool
     */
    public function add_job( $cluster_id, $job_id, $role = 'supporting' ) {
        global $wpdb;

        // Check if relationship already exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->relation_table} WHERE cluster_id = %d AND job_id = %d",
            $cluster_id,
            $job_id
        ) );

        if ( $exists ) {
            // Update role if needed
            return $wpdb->update(
                $this->relation_table,
                array( 'role' => $role ),
                array( 'cluster_id' => $cluster_id, 'job_id' => $job_id )
            ) !== false;
        }

        $result = $wpdb->insert(
            $this->relation_table,
            array(
                'cluster_id' => $cluster_id,
                'job_id' => $job_id,
                'role' => $role,
            )
        );

        if ( $result ) {
            // Update article count
            $this->update_article_count( $cluster_id );
            tsa_log( "Job {$job_id} added to cluster {$cluster_id} as {$role}", 'info' );
            return true;
        }

        return false;
    }

    /**
     * Remove job from cluster
     *
     * @param int $cluster_id Cluster ID
     * @param int $job_id     Job ID
     * @return bool
     */
    public function remove_job( $cluster_id, $job_id ) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->relation_table,
            array( 'cluster_id' => $cluster_id, 'job_id' => $job_id )
        );

        if ( $result ) {
            $this->update_article_count( $cluster_id );
            return true;
        }

        return false;
    }

    /**
     * Get jobs in a cluster
     *
     * @param int $cluster_id Cluster ID
     * @return array
     */
    public function get_jobs( $cluster_id ) {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'tsa_jobs';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT j.*, cj.role, cj.link_status, cj.post_id as linked_post_id
             FROM {$this->relation_table} cj
             JOIN {$jobs_table} j ON cj.job_id = j.id
             WHERE cj.cluster_id = %d
             ORDER BY cj.role DESC, j.created_at DESC",
            $cluster_id
        ) );
    }

    /**
     * Get cluster for a job
     *
     * @param int $job_id Job ID
     * @return object|null
     */
    public function get_cluster_for_job( $job_id ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*, cj.role
             FROM {$this->table_name} c
             JOIN {$this->relation_table} cj ON c.id = cj.cluster_id
             WHERE cj.job_id = %d",
            $job_id
        ) );
    }

    /**
     * Update article count for a cluster
     *
     * @param int $cluster_id Cluster ID
     * @return void
     */
    private function update_article_count( $cluster_id ) {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relation_table} WHERE cluster_id = %d",
            $cluster_id
        ) );

        $wpdb->update(
            $this->table_name,
            array( 'article_count' => $count ),
            array( 'id' => $cluster_id )
        );
    }

    /**
     * Suggest cluster for a title based on keyword matching
     *
     * @param string $title Article title
     * @return object|null Suggested cluster or null
     */
    public function suggest_cluster( $title ) {
        global $wpdb;

        // Get all active clusters
        $clusters = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'active'"
        );

        if ( empty( $clusters ) ) {
            return null;
        }

        $title_lower = strtolower( $title );
        $best_match = null;
        $best_score = 0;

        foreach ( $clusters as $cluster ) {
            $keyword_lower = strtolower( $cluster->pillar_keyword );
            
            // Check if pillar keyword is in title
            if ( strpos( $title_lower, $keyword_lower ) !== false ) {
                $score = strlen( $keyword_lower );
                if ( $score > $best_score ) {
                    $best_score = $score;
                    $best_match = $cluster;
                }
            }

            // Check individual words
            $keywords = explode( ' ', $keyword_lower );
            $matches = 0;
            foreach ( $keywords as $word ) {
                if ( strlen( $word ) > 3 && strpos( $title_lower, $word ) !== false ) {
                    $matches++;
                }
            }

            if ( $matches > 0 ) {
                $score = $matches * 10;
                if ( $score > $best_score ) {
                    $best_score = $score;
                    $best_match = $cluster;
                }
            }
        }

        return $best_match;
    }

    /**
     * Get related articles for internal linking
     *
     * @param int $job_id     Current job ID
     * @param int $cluster_id Cluster ID
     * @param int $limit      Number of articles to return
     * @return array
     */
    public function get_related_articles( $job_id, $cluster_id, $limit = 5 ) {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'tsa_jobs';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT j.id, j.title, j.slug, cj.post_id, cj.role
             FROM {$this->relation_table} cj
             JOIN {$jobs_table} j ON cj.job_id = j.id
             WHERE cj.cluster_id = %d 
             AND cj.job_id != %d
             AND j.status IN ('ready', 'pushed')
             ORDER BY cj.role DESC, RAND()
             LIMIT %d",
            $cluster_id,
            $job_id,
            $limit
        ) );
    }

    /**
     * Update link status for a job in cluster
     *
     * @param int    $cluster_id  Cluster ID
     * @param int    $job_id      Job ID
     * @param string $link_status Link status
     * @param int    $post_id     WordPress post ID
     * @return bool
     */
    public function update_link_status( $cluster_id, $job_id, $link_status, $post_id = null ) {
        global $wpdb;

        $data = array( 'link_status' => $link_status );
        if ( $post_id ) {
            $data['post_id'] = $post_id;
        }

        return $wpdb->update(
            $this->relation_table,
            $data,
            array( 'cluster_id' => $cluster_id, 'job_id' => $job_id )
        ) !== false;
    }

    /**
     * Get cluster statistics
     *
     * @return array
     */
    public function get_statistics() {
        global $wpdb;

        $stats = array(
            'total_clusters' => 0,
            'active_clusters' => 0,
            'total_articles' => 0,
            'linked_articles' => 0,
        );

        $stats['total_clusters'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        $stats['active_clusters'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'"
        );

        $stats['total_articles'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->relation_table}"
        );

        $stats['linked_articles'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->relation_table} WHERE link_status = 'linked'"
        );

        return $stats;
    }
}
