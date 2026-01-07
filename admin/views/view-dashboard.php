<?php
/**
 * Dashboard View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Get statistics
$table_jobs = $wpdb->prefix . 'tsa_jobs';
$table_campaigns = $wpdb->prefix . 'tsa_campaigns';

$total_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs" );
$queued_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'queued'" );
$processing_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status IN ('researching', 'drafting', 'qa', 'image_planning')" );
$ready_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'ready'" );
$pushed_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'pushed'" );
$failed_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'" );
$total_campaigns = $wpdb->get_var( "SELECT COUNT(*) FROM $table_campaigns" );

// Get recent jobs
$recent_jobs = $wpdb->get_results( "SELECT * FROM $table_jobs ORDER BY created_at DESC LIMIT 10" );
?>

<div class="wrap tsa-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-airplane" style="font-size: 30px; margin-right: 10px;"></span>
        TravelSEO Autopublisher
    </h1>
    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-new-campaign' ); ?>" class="page-title-action">New Campaign</a>
    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div class="tsa-stats-grid">
        <div class="tsa-stat-card tsa-stat-total">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $total_jobs ); ?></div>
                <div class="tsa-stat-label">Total Jobs</div>
            </div>
        </div>

        <div class="tsa-stat-card tsa-stat-queued">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $queued_jobs ); ?></div>
                <div class="tsa-stat-label">Queued</div>
            </div>
        </div>

        <div class="tsa-stat-card tsa-stat-processing">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $processing_jobs ); ?></div>
                <div class="tsa-stat-label">Processing</div>
            </div>
        </div>

        <div class="tsa-stat-card tsa-stat-ready">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $ready_jobs ); ?></div>
                <div class="tsa-stat-label">Ready</div>
            </div>
        </div>

        <div class="tsa-stat-card tsa-stat-pushed">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $pushed_jobs ); ?></div>
                <div class="tsa-stat-label">Published</div>
            </div>
        </div>

        <div class="tsa-stat-card tsa-stat-failed">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $failed_jobs ); ?></div>
                <div class="tsa-stat-label">Failed</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="tsa-quick-actions">
        <h2>Quick Actions</h2>
        <div class="tsa-action-buttons">
            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-new-campaign' ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt"></span>
                Create New Campaign
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs' ); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-list-view"></span>
                View All Jobs
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-settings' ); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-admin-settings"></span>
                Settings
            </a>
        </div>
    </div>

    <!-- Recent Jobs -->
    <div class="tsa-recent-jobs">
        <h2>Recent Jobs</h2>
        <?php if ( empty( $recent_jobs ) ) : ?>
            <div class="tsa-empty-state">
                <span class="dashicons dashicons-format-aside"></span>
                <p>No jobs yet. Create your first campaign to get started!</p>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-new-campaign' ); ?>" class="button button-primary">Create Campaign</a>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_jobs as $job ) : ?>
                        <?php
                        $status_class = 'tsa-status-' . esc_attr( $job->status );
                        $status_label = ucfirst( str_replace( '_', ' ', $job->status ) );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $job->id ); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=view&job_id=' . $job->id ); ?>">
                                        <?php echo esc_html( $job->title_input ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <span class="tsa-status-badge <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( human_time_diff( strtotime( $job->created_at ), current_time( 'timestamp' ) ) ); ?> ago</td>
                            <td>
                                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=view&job_id=' . $job->id ); ?>" class="button button-small">View</a>
                                <?php if ( $job->status === 'ready' ) : ?>
                                    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) ); ?>" class="button button-small button-primary">Publish</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs' ); ?>">View all jobs &rarr;</a>
            </p>
        <?php endif; ?>
    </div>

    <!-- System Status -->
    <div class="tsa-system-status">
        <h2>System Status</h2>
        <table class="wp-list-table widefat fixed">
            <tbody>
                <tr>
                    <td><strong>Plugin Version</strong></td>
                    <td><?php echo esc_html( TSA_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong>AI API Status</strong></td>
                    <td>
                        <?php
                        $api_key = get_option( 'tsa_settings', array() );
                        $api_key = isset( $api_key['openai_api_key'] ) ? $api_key['openai_api_key'] : '';
                        if ( ! empty( $api_key ) ) {
                            echo '<span class="tsa-status-badge tsa-status-ready">Configured</span>';
                        } else {
                            echo '<span class="tsa-status-badge tsa-status-queued">Not Configured (Using Free Mode)</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Background Processing</strong></td>
                    <td>
                        <?php
                        if ( class_exists( 'ActionScheduler' ) ) {
                            echo '<span class="tsa-status-badge tsa-status-ready">Action Scheduler Active</span>';
                        } else {
                            echo '<span class="tsa-status-badge tsa-status-queued">WP Cron (Fallback)</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Total Campaigns</strong></td>
                    <td><?php echo esc_html( $total_campaigns ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
