<?php
/**
 * Cluster Detail View
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
    wp_redirect( admin_url( 'admin.php?page=travelseo-clusters' ) );
    exit;
}

// Get cluster ID
$cluster_id = isset( $_GET['cluster_id'] ) ? absint( $_GET['cluster_id'] ) : 0;

if ( ! $cluster_id ) {
    wp_redirect( admin_url( 'admin.php?page=travelseo-clusters' ) );
    exit;
}

// Initialize cluster module
$cluster_module = new Cluster();
$cluster = $cluster_module->get( $cluster_id );

if ( ! $cluster ) {
    wp_redirect( admin_url( 'admin.php?page=travelseo-clusters' ) );
    exit;
}

// Handle update action
if ( isset( $_POST['tsa_update_cluster'] ) && check_admin_referer( 'tsa_update_cluster_nonce' ) ) {
    $update_data = array(
        'name' => sanitize_text_field( $_POST['cluster_name'] ),
        'pillar_keyword' => sanitize_text_field( $_POST['pillar_keyword'] ),
        'description' => sanitize_textarea_field( $_POST['cluster_description'] ?? '' ),
        'status' => sanitize_text_field( $_POST['cluster_status'] ),
    );
    
    if ( $cluster_module->update( $cluster_id, $update_data ) ) {
        $success_message = 'Cluster updated successfully!';
        $cluster = $cluster_module->get( $cluster_id ); // Refresh data
    } else {
        $error_message = 'Failed to update cluster.';
    }
}

// Handle remove job action
if ( isset( $_GET['action'] ) && $_GET['action'] === 'remove_job' && isset( $_GET['job_id'] ) && check_admin_referer( 'tsa_cluster_action' ) ) {
    $job_id = absint( $_GET['job_id'] );
    if ( $cluster_module->remove_job( $cluster_id, $job_id ) ) {
        $success_message = 'Article removed from cluster.';
        $cluster = $cluster_module->get( $cluster_id ); // Refresh data
    }
}

// Get jobs in this cluster
$cluster_jobs = $cluster_module->get_jobs( $cluster_id );

// Separate pillar and supporting articles
$pillar_articles = array_filter( $cluster_jobs, function( $job ) {
    return $job->role === 'pillar';
} );
$supporting_articles = array_filter( $cluster_jobs, function( $job ) {
    return $job->role === 'supporting';
} );
?>

<div class="wrap tsa-cluster-detail">
    <h1 class="wp-heading-inline">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-clusters' ) ); ?>" class="tsa-back-link">
            <span class="dashicons dashicons-arrow-left-alt"></span>
        </a>
        <?php echo esc_html( $cluster->name ); ?>
        <span class="tsa-status-badge tsa-status-<?php echo esc_attr( $cluster->status ); ?>">
            <?php echo esc_html( ucfirst( $cluster->status ) ); ?>
        </span>
    </h1>
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

    <div class="tsa-cluster-grid">
        <!-- Left Column: Cluster Info -->
        <div class="tsa-cluster-info">
            <div class="tsa-form-section">
                <h2>
                    <span class="dashicons dashicons-edit"></span>
                    Cluster Settings
                </h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'tsa_update_cluster_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cluster_name">Cluster Name</label>
                            </th>
                            <td>
                                <input type="text" name="cluster_name" id="cluster_name" class="regular-text" 
                                       value="<?php echo esc_attr( $cluster->name ); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pillar_keyword">Pillar Keyword</label>
                            </th>
                            <td>
                                <input type="text" name="pillar_keyword" id="pillar_keyword" class="regular-text" 
                                       value="<?php echo esc_attr( $cluster->pillar_keyword ); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cluster_description">Description</label>
                            </th>
                            <td>
                                <textarea name="cluster_description" id="cluster_description" rows="3" class="large-text"><?php echo esc_textarea( $cluster->description ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cluster_status">Status</label>
                            </th>
                            <td>
                                <select name="cluster_status" id="cluster_status">
                                    <option value="active" <?php selected( $cluster->status, 'active' ); ?>>Active</option>
                                    <option value="inactive" <?php selected( $cluster->status, 'inactive' ); ?>>Inactive</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="tsa_update_cluster" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            Save Changes
                        </button>
                    </p>
                </form>
            </div>

            <!-- Cluster Statistics -->
            <div class="tsa-form-section">
                <h2>
                    <span class="dashicons dashicons-chart-bar"></span>
                    Statistics
                </h2>
                <table class="tsa-stats-table">
                    <tr>
                        <td>Total Articles</td>
                        <td><strong><?php echo esc_html( $cluster->article_count ); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Pillar Content</td>
                        <td><strong><?php echo esc_html( count( $pillar_articles ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Supporting Articles</td>
                        <td><strong><?php echo esc_html( count( $supporting_articles ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Created</td>
                        <td><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $cluster->created_at ) ) ); ?></td>
                    </tr>
                    <tr>
                        <td>Last Updated</td>
                        <td><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $cluster->updated_at ) ) ); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Right Column: Articles -->
        <div class="tsa-cluster-articles">
            <!-- Pillar Content -->
            <div class="tsa-form-section">
                <h2>
                    <span class="dashicons dashicons-star-filled"></span>
                    Pillar Content
                </h2>
                <?php if ( empty( $pillar_articles ) ) : ?>
                    <div class="tsa-empty-state" style="padding: 20px;">
                        <p>No pillar content assigned yet. The pillar content is the main comprehensive article for this topic cluster.</p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pillar_articles as $job ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $job->title ); ?></strong>
                                        <?php if ( $job->linked_post_id ) : ?>
                                            <a href="<?php echo esc_url( get_permalink( $job->linked_post_id ) ); ?>" target="_blank" class="dashicons dashicons-external"></a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="tsa-status-badge tsa-status-<?php echo esc_attr( $job->status ); ?>">
                                            <?php echo esc_html( ucfirst( $job->status ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-job-detail&job_id=' . $job->id ) ); ?>" class="button button-small">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Supporting Articles -->
            <div class="tsa-form-section">
                <h2>
                    <span class="dashicons dashicons-admin-page"></span>
                    Supporting Articles (<?php echo esc_html( count( $supporting_articles ) ); ?>)
                </h2>
                <?php if ( empty( $supporting_articles ) ) : ?>
                    <div class="tsa-empty-state" style="padding: 20px;">
                        <p>No supporting articles yet. When you create articles with titles containing "<code><?php echo esc_html( $cluster->pillar_keyword ); ?></code>", they will be suggested to join this cluster.</p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Link Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $supporting_articles as $job ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $job->title ); ?></strong>
                                        <?php if ( $job->linked_post_id ) : ?>
                                            <a href="<?php echo esc_url( get_permalink( $job->linked_post_id ) ); ?>" target="_blank" class="dashicons dashicons-external"></a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="tsa-status-badge tsa-status-<?php echo esc_attr( $job->status ); ?>">
                                            <?php echo esc_html( ucfirst( $job->status ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $link_status_labels = array(
                                            'pending' => 'Pending',
                                            'linked' => 'Linked',
                                            'failed' => 'Failed',
                                        );
                                        $link_status = $job->link_status ?? 'pending';
                                        ?>
                                        <span class="tsa-link-status tsa-link-<?php echo esc_attr( $link_status ); ?>">
                                            <?php echo esc_html( $link_status_labels[ $link_status ] ?? $link_status ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=travelseo-job-detail&job_id=' . $job->id ) ); ?>" class="button button-small">View</a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=travelseo-cluster-detail&cluster_id=' . $cluster_id . '&action=remove_job&job_id=' . $job->id ), 'tsa_cluster_action' ) ); ?>" 
                                           class="button button-small"
                                           onclick="return confirm('Remove this article from the cluster?');">
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Internal Linking Visualization -->
            <div class="tsa-form-section">
                <h2>
                    <span class="dashicons dashicons-admin-links"></span>
                    Internal Linking Map
                </h2>
                <div class="tsa-cluster-map">
                    <div class="tsa-cluster-visual">
                        <div class="tsa-pillar-node">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="tsa-node-label"><?php echo esc_html( $cluster->pillar_keyword ); ?></span>
                        </div>
                        <div class="tsa-supporting-nodes">
                            <?php 
                            $display_count = min( count( $supporting_articles ), 6 );
                            $i = 0;
                            foreach ( $supporting_articles as $job ) : 
                                if ( $i >= $display_count ) break;
                                $i++;
                            ?>
                                <div class="tsa-supporting-node">
                                    <span class="tsa-node-label"><?php echo esc_html( wp_trim_words( $job->title, 3, '...' ) ); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ( count( $supporting_articles ) > 6 ) : ?>
                                <div class="tsa-supporting-node tsa-more-node">
                                    <span class="tsa-node-label">+<?php echo esc_html( count( $supporting_articles ) - 6 ); ?> more</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="description">This visualization shows the hub-and-spoke model of your topic cluster. The pillar content links to all supporting articles, and supporting articles link back to the pillar.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tsa-cluster-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    margin-top: 20px;
}

.tsa-stats-table {
    width: 100%;
}

.tsa-stats-table td {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.tsa-stats-table td:first-child {
    color: #646970;
}

.tsa-link-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.tsa-link-pending {
    background: #fff8e5;
    color: #996800;
}

.tsa-link-linked {
    background: #edfaef;
    color: #00a32a;
}

.tsa-link-failed {
    background: #fcf0f1;
    color: #d63638;
}

/* Cluster Visualization */
.tsa-cluster-visual {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 30px;
    background: #f6f7f7;
    border-radius: 8px;
    margin-bottom: 15px;
}

.tsa-pillar-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 25px;
    background: #2271b1;
    color: #fff;
    border-radius: 8px;
    margin-bottom: 30px;
    position: relative;
}

.tsa-pillar-node::after {
    content: '';
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 20px;
    background: #2271b1;
}

.tsa-pillar-node .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-bottom: 5px;
}

.tsa-supporting-nodes {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    max-width: 500px;
}

.tsa-supporting-node {
    padding: 8px 15px;
    background: #fff;
    border: 2px solid #2271b1;
    border-radius: 5px;
    font-size: 12px;
}

.tsa-more-node {
    background: #f0f0f1;
    border-color: #c3c4c7;
    color: #646970;
}

.tsa-node-label {
    font-weight: 500;
}

@media screen and (max-width: 1200px) {
    .tsa-cluster-grid {
        grid-template-columns: 1fr;
    }
}
</style>
