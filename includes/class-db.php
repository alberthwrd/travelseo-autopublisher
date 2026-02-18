<?php
/**
 * The database-specific functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * The database-specific functionality of the plugin.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 * @author     Your Name
 */
class DB {

    /**
     * Plugin activation hook.
     *
     * Create custom database tables.
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_campaigns = $wpdb->prefix . 'tsa_campaigns';
        $sql_campaigns = "CREATE TABLE $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $table_jobs = $wpdb->prefix . 'tsa_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            title varchar(500) NOT NULL,
            slug varchar(500) DEFAULT NULL,
            title_input text NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'queued',
            priority int(11) DEFAULT 10,
            idempotency_key varchar(64) DEFAULT NULL,
            settings longtext,
            research_pack longtext,
            draft_pack longtext,
            qa_pack longtext,
            image_pack longtext,
            final_content longtext,
            meta_title varchar(255) DEFAULT NULL,
            meta_description text,
            focus_keyword varchar(255) DEFAULT NULL,
            categories text,
            tags text,
            schema_data longtext,
            quality_score int(11) DEFAULT NULL,
            word_count int(11) DEFAULT NULL,
            log longtext,
            error_message text,
            retry_count int(11) DEFAULT 0,
            last_step varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY campaign_id (campaign_id),
            KEY priority (priority),
            UNIQUE KEY idempotency_key (idempotency_key)
        ) $charset_collate;";

        // Clusters table
        $table_clusters = $wpdb->prefix . 'tsa_clusters';
        $sql_clusters = "CREATE TABLE $table_clusters (
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
        $table_cluster_jobs = $wpdb->prefix . 'tsa_cluster_jobs';
        $sql_cluster_jobs = "CREATE TABLE $table_cluster_jobs (
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
        dbDelta( $sql_campaigns );
        dbDelta( $sql_jobs );
        dbDelta( $sql_clusters );
        dbDelta( $sql_cluster_jobs );
        
        // Store DB version for future migrations
        update_option( 'tsa_db_version', '1.1.0' );
    }

    /**
     * Plugin deactivation hook.
     */
    public static function deactivate() {
        // Do nothing
    }

}
