<?php
/**
 * Cluster List View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TravelSEO_Autopublisher\Modules\Cluster;

// Check if feature is enabled
$settings = get_option( 'tsa_settings', array() );
if ( empty( $settings['feature_topical_cluster'] ) ) {
    ?>
    <div class="wrap">
        <h1>Topical Clusters</h1>
        <div class="notice notice-warning">
            <p>
                <strong>Feature Disabled:</strong> Topical Authority Cluster Builder is currently disabled.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-settings' ) ); ?>">Enable it in Settings</a>
            </p>
        </div>
    </div>
    <?php
    return;
}

// Initialize cluster module
$cluster_module = new Cluster();

// Handle actions
if ( isset( $_GET['action'] ) && isset( $_GET['cluster_id'] ) && check_admin_referer( 'tsa_cluster_action' ) ) {
    $cluster_id = absint( $_GET['cluster_id'] );
    
    if ( $_GET['action'] === 'delete' ) {
        if ( $cluster_module->delete( $cluster_id ) ) {
            $success_message = 'Cluster deleted successfully.';
        } else {
            $error_message = 'Failed to delete cluster.';
        }
    }
}

// Handle new cluster creation
if ( isset( $_POST['tsa_create_cluster'] ) && check_admin_referer( 'tsa_create_cluster_nonce' ) ) {
    $cluster_data = array(
        'name' => sanitize_text_field( $_POST['cluster_name'] ),
        'pillar_keyword' => sanitize_text_field( $_POST['pillar_keyword'] ),
        'description' => sanitize_textarea_field( $_POST['cluster_description'] ?? '' ),
    );
    
    $new_cluster_id = $cluster_module->create( $cluster_data );
    
    if ( $new_cluster_id ) {
        $success_message = 'Cluster created successfully!';
    } else {
        $error_message = 'Failed to create cluster. Please check the required fields.';
    }
}

// Get pagination parameters
$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

// Get clusters
$clusters_data = $cluster_module->get_all( array(
    'page' => $current_page,
    'per_page' => 20,
    'search' => $search,
    'status' => $status_filter,
) );

$clusters = $clusters_data['items'];
$total_pages = $clusters_data['pages'];

// Get statistics
$stats = $cluster_module->get_statistics();
?>

<div class="wrap tsa-cluster-list">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-networking"></span>
        Topical Authority Clusters
    </h1>
    <button type="button" class="page-title-action" id="tsa-new-cluster-btn">Add New Cluster</button>
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

    <!-- Statistics -->
    <div class="tsa-stats-grid">
        <div class="tsa-stat-card tsa-stat-total">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $stats['total_clusters'] ); ?></div>
                <div class="tsa-stat-label">Total Clusters</div>
            </div>
        </div>
        <div class="tsa-stat-card tsa-stat-ready">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $stats['active_clusters'] ); ?></div>
                <div class="tsa-stat-label">Active Clusters</div>
            </div>
        </div>
        <div class="tsa-stat-card tsa-stat-pushed">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-admin-page"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $stats['total_articles'] ); ?></div>
                <div class="tsa-stat-label">Total Articles</div>
            </div>
        </div>
        <div class="tsa-stat-card tsa-stat-queued">
            <div class="tsa-stat-icon">
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <div class="tsa-stat-content">
                <div class="tsa-stat-number"><?php echo esc_html( $stats['linked_articles'] ); ?></div>
                <div class="tsa-stat-label">Linked Articles</div>
            </div>
        </div>
    </div>

    <!-- New Cluster Form (Hidden by default) -->
    <div id="tsa-new-cluster-form" class="tsa-form-section" style="display: none;">
        <h2>Create New Cluster</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'tsa_create_cluster_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cluster_name">Cluster Name <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="cluster_name" id="cluster_name" class="regular-text" required 
                               placeholder="e.g., Wisata Bali">
                        <p class="description">A descriptive name for this topic cluster.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pillar_keyword">Pillar Keyword <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="pillar_keyword" id="pillar_keyword" class="regular-text" required
                               placeholder="e.g., wisata bali">
                        <p class="description">The main keyword that defines this cluster. Articles containing this keyword will be suggested to join this cluster.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cluster_description">Description</label>
                    </th>
                    <td>
                        <textarea name="cluster_description" id="cluster_description" rows="3" class="large-text"
                                  placeholder="Optional description for this cluster..."></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="tsa_create_cluster" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    Create Cluster
                </button>
                <button type="button" class="button" id="tsa-cancel-cluster">Cancel</button>
            </p>
        </form>
    </div>

    <!-- Filters -->
    <div class="tsa-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="travelseo-clusters">
            
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php selected( $status_filter, 'active' ); ?>>Active</option>
                <option value="inactive" <?php selected( $status_filter, 'inactive' ); ?>>Inactive</option>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search clusters...">
            
            <button type="submit" class="button">Filter</button>
            
            <?php if ( $search || $status_filter ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-clusters' ) ); ?>" class="button">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Clusters Table -->
    <?php if ( empty( $clusters ) ) : ?>
        <div class="tsa-empty-state">
            <span class="dashicons dashicons-networking"></span>
            <p>No clusters found. Create your first topic cluster to start building topical authority!</p>
            <button type="button" class="button button-primary" id="tsa-new-cluster-btn-empty">
                <span class="dashicons dashicons-plus-alt2"></span>
                Create First Cluster
            </button>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-name">Cluster Name</th>
                    <th scope="col" class="column-keyword">Pillar Keyword</th>
                    <th scope="col" class="column-articles">Articles</th>
                    <th scope="col" class="column-status">Status</th>
                    <th scope="col" class="column-created">Created</th>
                    <th scope="col" class="column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $clusters as $cluster ) : ?>
                    <tr>
                        <td class="column-name">
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-cluster-detail&cluster_id=' . $cluster->id ) ); ?>">
                                    <?php echo esc_html( $cluster->name ); ?>
                                </a>
                            </strong>
                            <?php if ( $cluster->description ) : ?>
                                <p class="description"><?php echo esc_html( wp_trim_words( $cluster->description, 10 ) ); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="column-keyword">
                            <code><?php echo esc_html( $cluster->pillar_keyword ); ?></code>
                        </td>
                        <td class="column-articles">
                            <span class="tsa-article-count"><?php echo esc_html( $cluster->article_count ); ?></span>
                        </td>
                        <td class="column-status">
                            <span class="tsa-status-badge tsa-status-<?php echo esc_attr( $cluster->status ); ?>">
                                <?php echo esc_html( ucfirst( $cluster->status ) ); ?>
                            </span>
                        </td>
                        <td class="column-created">
                            <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $cluster->created_at ) ) ); ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-cluster-detail&cluster_id=' . $cluster->id ) ); ?>" 
                               class="button button-small">
                                View
                            </a>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=travelseo-clusters&action=delete&cluster_id=' . $cluster->id ), 'tsa_cluster_action' ) ); ?>" 
                               class="button button-small submitdelete"
                               onclick="return confirm('Are you sure you want to delete this cluster?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $pagination_args = array(
                        'base' => add_query_arg( 'paged', '%#%' ),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                    );
                    echo paginate_links( $pagination_args );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle new cluster form
    $('#tsa-new-cluster-btn, #tsa-new-cluster-btn-empty').on('click', function() {
        $('#tsa-new-cluster-form').slideDown();
        $('#cluster_name').focus();
    });
    
    $('#tsa-cancel-cluster').on('click', function() {
        $('#tsa-new-cluster-form').slideUp();
    });
});
</script>

<style>
.tsa-article-count {
    display: inline-block;
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}
.tsa-status-active {
    background: #edfaef;
    color: #00a32a;
}
.tsa-status-inactive {
    background: #f0f0f1;
    color: #646970;
}
.column-name { width: 25%; }
.column-keyword { width: 20%; }
.column-articles { width: 10%; }
.column-status { width: 10%; }
.column-created { width: 15%; }
.column-actions { width: 20%; }
</style>
