<?php
/**
 * Jobs List View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Handle actions
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

// Handle view single job
if ( $action === 'view' && $job_id > 0 ) {
    include TSA_PLUGIN_DIR . 'admin/views/view-job-detail.php';
    return;
}

// Handle push action
if ( $action === 'push' && $job_id > 0 && wp_verify_nonce( $_GET['_wpnonce'], 'tsa_push_job' ) ) {
    require_once TSA_PLUGIN_DIR . 'includes/class-wp-post-pusher.php';
    $pusher = new \TravelSEO_Autopublisher\WP_Post_Pusher( $job_id );
    $result = $pusher->push();
    
    if ( $result ) {
        $success_message = 'Article pushed to WordPress successfully!';
    } else {
        $error_message = 'Failed to push article to WordPress.';
    }
}

// Handle delete action
if ( $action === 'delete' && $job_id > 0 && wp_verify_nonce( $_GET['_wpnonce'], 'tsa_delete_job' ) ) {
    $table_jobs = $wpdb->prefix . 'tsa_jobs';
    $wpdb->delete( $table_jobs, array( 'id' => $job_id ) );
    $success_message = 'Job deleted successfully.';
}

// Handle retry action
if ( $action === 'retry' && $job_id > 0 && wp_verify_nonce( $_GET['_wpnonce'], 'tsa_retry_job' ) ) {
    $table_jobs = $wpdb->prefix . 'tsa_jobs';
    $wpdb->update( $table_jobs, array( 'status' => 'queued' ), array( 'id' => $job_id ) );
    
    // Reschedule the job
    if ( function_exists( 'as_schedule_single_action' ) ) {
        as_schedule_single_action( time(), 'tsa_process_job', array( 'job_id' => $job_id ), 'travelseo-autopublisher' );
    } else {
        wp_schedule_single_event( time(), 'tsa_process_job_cron', array( $job_id ) );
    }
    
    $success_message = 'Job queued for retry.';
}

// Get filter parameters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$campaign_filter = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 20;
$offset = ( $paged - 1 ) * $per_page;

// Build query
$table_jobs = $wpdb->prefix . 'tsa_jobs';
$where = array( '1=1' );

if ( ! empty( $status_filter ) ) {
    $where[] = $wpdb->prepare( 'status = %s', $status_filter );
}

if ( $campaign_filter > 0 ) {
    $where[] = $wpdb->prepare( 'campaign_id = %d', $campaign_filter );
}

if ( ! empty( $search ) ) {
    $where[] = $wpdb->prepare( 'title_input LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
}

$where_clause = implode( ' AND ', $where );

// Get total count
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_jobs WHERE $where_clause" );
$total_pages = ceil( $total_items / $per_page );

// Get jobs
$jobs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $table_jobs WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
) );

// Get campaigns for filter
$table_campaigns = $wpdb->prefix . 'tsa_campaigns';
$campaigns = $wpdb->get_results( "SELECT * FROM $table_campaigns ORDER BY created_at DESC" );

// Status options
$statuses = array(
    'queued' => 'Queued',
    'researching' => 'Researching',
    'drafting' => 'Drafting',
    'qa' => 'QA Review',
    'image_planning' => 'Image Planning',
    'ready' => 'Ready',
    'pushed' => 'Published',
    'failed' => 'Failed',
);
?>

<div class="wrap tsa-jobs-list">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-list-view"></span>
        Jobs
    </h1>
    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-new-campaign' ); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <?php if ( isset( $success_message ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $success_message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $error_message ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error_message ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="tsa-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="travelseo-autopublisher-jobs">
            
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ( $statuses as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="campaign_id">
                <option value="">All Campaigns</option>
                <?php foreach ( $campaigns as $campaign ) : ?>
                    <option value="<?php echo esc_attr( $campaign->id ); ?>" <?php selected( $campaign_filter, $campaign->id ); ?>>
                        <?php echo esc_html( $campaign->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search titles...">
            
            <button type="submit" class="button">Filter</button>
            
            <?php if ( ! empty( $status_filter ) || ! empty( $campaign_filter ) || ! empty( $search ) ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs' ); ?>" class="button">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Jobs Table -->
    <?php if ( empty( $jobs ) ) : ?>
        <div class="tsa-empty-state">
            <span class="dashicons dashicons-format-aside"></span>
            <p>No jobs found.</p>
            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-new-campaign' ); ?>" class="button button-primary">Create Campaign</a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </th>
                    <th class="column-id">ID</th>
                    <th class="column-title">Title</th>
                    <th class="column-status">Status</th>
                    <th class="column-progress">Progress</th>
                    <th class="column-campaign">Campaign</th>
                    <th class="column-date">Date</th>
                    <th class="column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $jobs as $job ) : ?>
                    <?php
                    $status_class = 'tsa-status-' . esc_attr( $job->status );
                    $status_label = isset( $statuses[ $job->status ] ) ? $statuses[ $job->status ] : ucfirst( $job->status );
                    
                    // Calculate progress
                    $progress_map = array(
                        'queued' => 0,
                        'researching' => 20,
                        'drafting' => 40,
                        'qa' => 60,
                        'image_planning' => 80,
                        'ready' => 100,
                        'pushed' => 100,
                        'failed' => 0,
                    );
                    $progress = isset( $progress_map[ $job->status ] ) ? $progress_map[ $job->status ] : 0;
                    
                    // Get campaign name
                    $campaign_name = '';
                    foreach ( $campaigns as $campaign ) {
                        if ( $campaign->id == $job->campaign_id ) {
                            $campaign_name = $campaign->name;
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" name="job_ids[]" value="<?php echo esc_attr( $job->id ); ?>">
                        </td>
                        <td class="column-id"><?php echo esc_html( $job->id ); ?></td>
                        <td class="column-title">
                            <strong>
                                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=view&job_id=' . $job->id ); ?>">
                                    <?php echo esc_html( $job->title_input ); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=view&job_id=' . $job->id ); ?>">View</a> |
                                </span>
                                <?php if ( $job->status === 'ready' ) : ?>
                                    <span class="push">
                                        <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) ); ?>">Publish</a> |
                                    </span>
                                <?php endif; ?>
                                <?php if ( $job->status === 'failed' ) : ?>
                                    <span class="retry">
                                        <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=retry&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_retry_job' ) ); ?>">Retry</a> |
                                    </span>
                                <?php endif; ?>
                                <span class="delete">
                                    <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=delete&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_delete_job' ) ); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this job?');" 
                                       class="submitdelete">Delete</a>
                                </span>
                            </div>
                        </td>
                        <td class="column-status">
                            <span class="tsa-status-badge <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( $status_label ); ?>
                            </span>
                        </td>
                        <td class="column-progress">
                            <div class="tsa-progress-bar">
                                <div class="tsa-progress-fill <?php echo $job->status === 'failed' ? 'failed' : ''; ?>" 
                                     style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
                            </div>
                            <span class="tsa-progress-text"><?php echo esc_html( $progress ); ?>%</span>
                        </td>
                        <td class="column-campaign">
                            <?php if ( $campaign_name ) : ?>
                                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&campaign_id=' . $job->campaign_id ); ?>">
                                    <?php echo esc_html( $campaign_name ); ?>
                                </a>
                            <?php else : ?>
                                <em>No campaign</em>
                            <?php endif; ?>
                        </td>
                        <td class="column-date">
                            <abbr title="<?php echo esc_attr( $job->created_at ); ?>">
                                <?php echo esc_html( human_time_diff( strtotime( $job->created_at ), current_time( 'timestamp' ) ) ); ?> ago
                            </abbr>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=view&job_id=' . $job->id ); ?>" 
                               class="button button-small">View</a>
                            <?php if ( $job->status === 'ready' ) : ?>
                                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) ); ?>" 
                                   class="button button-small button-primary">Publish</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html( $total_items ); ?> items</span>
                    <span class="pagination-links">
                        <?php if ( $paged > 1 ) : ?>
                            <a class="first-page button" href="<?php echo add_query_arg( 'paged', 1 ); ?>">
                                <span class="screen-reader-text">First page</span>
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                            <a class="prev-page button" href="<?php echo add_query_arg( 'paged', $paged - 1 ); ?>">
                                <span class="screen-reader-text">Previous page</span>
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <span class="tablenav-paging-text">
                                <?php echo esc_html( $paged ); ?> of <span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
                            </span>
                        </span>
                        
                        <?php if ( $paged < $total_pages ) : ?>
                            <a class="next-page button" href="<?php echo add_query_arg( 'paged', $paged + 1 ); ?>">
                                <span class="screen-reader-text">Next page</span>
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                            <a class="last-page button" href="<?php echo add_query_arg( 'paged', $total_pages ); ?>">
                                <span class="screen-reader-text">Last page</span>
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
