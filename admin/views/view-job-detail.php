<?php
/**
 * Job Detail View V3 - Professional Article Preview
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 * @version    3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

if ( ! $job_id ) {
    echo '<div class="notice notice-error"><p>Invalid job ID.</p></div>';
    return;
}

$table_jobs = $wpdb->prefix . 'tsa_jobs';
$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_jobs WHERE id = %d", $job_id ) );

if ( ! $job ) {
    echo '<div class="notice notice-error"><p>Job not found.</p></div>';
    return;
}

// Parse JSON data
$settings = json_decode( $job->settings, true ) ?: array();
$research_pack = json_decode( $job->research_pack, true ) ?: array();
$draft_pack = json_decode( $job->draft_pack, true ) ?: array();

// Status info
$statuses = array(
    'queued' => array( 'label' => 'Queued', 'icon' => 'clock', 'color' => '#6b7280' ),
    'researching' => array( 'label' => 'Research', 'icon' => 'search', 'color' => '#3b82f6' ),
    'drafting' => array( 'label' => 'Writing', 'icon' => 'edit', 'color' => '#3b82f6' ),
    'qa' => array( 'label' => 'QA', 'icon' => 'visibility', 'color' => '#f59e0b' ),
    'image_planning' => array( 'label' => 'Images', 'icon' => 'format-image', 'color' => '#8b5cf6' ),
    'ready' => array( 'label' => 'Ready', 'icon' => 'yes-alt', 'color' => '#10b981' ),
    'pushed' => array( 'label' => 'Published', 'icon' => 'admin-post', 'color' => '#06b6d4' ),
    'failed' => array( 'label' => 'Failed', 'icon' => 'warning', 'color' => '#ef4444' ),
);

$status_info = isset( $statuses[ $job->status ] ) ? $statuses[ $job->status ] : array( 'label' => ucfirst( $job->status ), 'icon' => 'marker', 'color' => '#6b7280' );

// Get campaign name
$table_campaigns = $wpdb->prefix . 'tsa_campaigns';
$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_campaigns WHERE id = %d", $job->campaign_id ) );

// Get content HTML
$content_html = '';
if ( ! empty( $draft_pack['content_html'] ) ) {
    $content_html = $draft_pack['content_html'];
} elseif ( ! empty( $draft_pack['content'] ) ) {
    // Convert markdown to HTML if needed
    $content_html = $draft_pack['content'];
    // Basic markdown conversion
    $content_html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content_html );
    $content_html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content_html );
    $content_html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content_html );
    $content_html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content_html );
    $content_html = nl2br( $content_html );
}
?>

<style>
/* Professional Article Preview Styles */
.tsa-job-detail {
    max-width: 1400px;
}

.tsa-status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.tsa-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 13px;
    background: <?php echo esc_attr( $status_info['color'] ); ?>;
    color: #fff;
}

.tsa-progress-steps {
    display: flex;
    justify-content: space-between;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.tsa-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
}

.tsa-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 15px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #e5e7eb;
}

.tsa-step.completed:not(:last-child)::after {
    background: #10b981;
}

.tsa-step-indicator {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: #6b7280;
    position: relative;
    z-index: 1;
}

.tsa-step.completed .tsa-step-indicator {
    background: #10b981;
    color: #fff;
}

.tsa-step.active .tsa-step-indicator {
    background: #3b82f6;
    color: #fff;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(59, 130, 246, 0); }
}

.tsa-step-label {
    margin-top: 8px;
    font-size: 12px;
    color: #6b7280;
}

.tsa-step.completed .tsa-step-label,
.tsa-step.active .tsa-step-label {
    color: #111827;
    font-weight: 500;
}

/* Tabs */
.tsa-tabs {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tsa-tab-nav {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.tsa-tab-link {
    padding: 15px 25px;
    text-decoration: none;
    color: #6b7280;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tsa-tab-link:hover {
    color: #111827;
    background: #fff;
}

.tsa-tab-link.active {
    color: #3b82f6;
    background: #fff;
    border-bottom-color: #3b82f6;
}

.tsa-tab-content {
    display: none;
    padding: 30px;
}

.tsa-tab-content.active {
    display: block;
}

/* Article Preview - Professional Blog Style */
.tsa-article-preview {
    max-width: 800px;
    margin: 0 auto;
    font-family: 'Georgia', 'Times New Roman', serif;
    line-height: 1.8;
    color: #1a1a1a;
}

.tsa-article-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.tsa-article-title {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.3;
    margin: 0 0 15px 0;
    color: #111827;
}

.tsa-article-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 14px;
    color: #6b7280;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.tsa-article-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.tsa-article-excerpt {
    font-size: 18px;
    color: #4b5563;
    font-style: italic;
    margin-top: 15px;
    padding-left: 20px;
    border-left: 3px solid #3b82f6;
}

.tsa-article-body {
    font-size: 17px;
}

.tsa-article-body h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 40px 0 20px 0;
    color: #111827;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
}

.tsa-article-body h3 {
    font-size: 20px;
    font-weight: 600;
    margin: 30px 0 15px 0;
    color: #374151;
}

.tsa-article-body p {
    margin: 0 0 20px 0;
    text-align: justify;
}

.tsa-article-body ul,
.tsa-article-body ol {
    margin: 0 0 20px 0;
    padding-left: 25px;
}

.tsa-article-body li {
    margin-bottom: 10px;
}

.tsa-article-body a {
    color: #3b82f6;
    text-decoration: underline;
}

.tsa-article-body a:hover {
    color: #1d4ed8;
}

.tsa-article-body strong {
    font-weight: 700;
    color: #111827;
}

.tsa-article-body em {
    font-style: italic;
}

/* Tables in article */
.tsa-article-body table,
.tsa-table {
    width: 100%;
    border-collapse: collapse;
    margin: 25px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 15px;
}

.tsa-article-body table th,
.tsa-article-body table td,
.tsa-table th,
.tsa-table td {
    padding: 12px 15px;
    text-align: left;
    border: 1px solid #e5e7eb;
}

.tsa-article-body table th,
.tsa-table th {
    background: #f3f4f6;
    font-weight: 600;
    color: #374151;
}

.tsa-article-body table tr:nth-child(even),
.tsa-table tr:nth-child(even) {
    background: #f9fafb;
}

/* Blockquote */
.tsa-article-body blockquote {
    margin: 25px 0;
    padding: 20px 25px;
    background: #f3f4f6;
    border-left: 4px solid #3b82f6;
    font-style: italic;
    color: #4b5563;
}

/* Disclaimer */
.tsa-article-body .disclaimer,
.tsa-article-body em:last-child {
    display: block;
    margin-top: 30px;
    padding: 15px 20px;
    background: #fef3c7;
    border-radius: 6px;
    font-size: 14px;
    color: #92400e;
    font-style: italic;
}

/* HR */
.tsa-article-body hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 30px 0;
}

/* Internal Links Section */
.tsa-internal-links {
    margin-top: 30px;
    padding: 20px;
    background: #eff6ff;
    border-radius: 8px;
}

.tsa-internal-links h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #1e40af;
}

.tsa-internal-links ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.tsa-internal-links li {
    margin-bottom: 8px;
}

.tsa-internal-links a {
    color: #3b82f6;
    text-decoration: none;
}

.tsa-internal-links a:hover {
    text-decoration: underline;
}

/* Word Count Badge */
.tsa-word-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

/* QA Score Cards */
.tsa-qa-scores {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tsa-qa-score-card {
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.tsa-qa-score-value {
    font-size: 36px;
    font-weight: 700;
    color: #10b981;
}

.tsa-qa-score-value.warning {
    color: #f59e0b;
}

.tsa-qa-score-value.danger {
    color: #ef4444;
}

.tsa-qa-score-label {
    font-size: 14px;
    color: #6b7280;
    margin-top: 5px;
}

/* Empty State */
.tsa-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.tsa-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Action Buttons */
.tsa-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tsa-action-buttons .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
</style>

<div class="wrap tsa-job-detail">
    <h1 class="wp-heading-inline">
        <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs' ); ?>" class="tsa-back-link" style="text-decoration:none;">
            ← Back
        </a>
        &nbsp;|&nbsp;
        Job #<?php echo esc_html( $job->id ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- Status Bar -->
    <div class="tsa-status-bar">
        <div class="tsa-status-info" style="display:flex;align-items:center;gap:15px;">
            <span class="tsa-status-badge">
                <span class="dashicons dashicons-<?php echo esc_attr( $status_info['icon'] ); ?>"></span>
                <?php echo esc_html( $status_info['label'] ); ?>
            </span>
            <?php if ( ! empty( $draft_pack['word_count'] ) ) : ?>
                <span class="tsa-word-count-badge">
                    <span class="dashicons dashicons-editor-paragraph"></span>
                    <?php echo number_format( $draft_pack['word_count'] ); ?> kata
                </span>
            <?php endif; ?>
            <span style="color:#6b7280;font-size:13px;">
                <?php echo esc_html( human_time_diff( strtotime( $job->created_at ) ) ); ?> ago
            </span>
        </div>
        
        <div class="tsa-action-buttons">
            <?php if ( $job->status === 'ready' ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) . '&publish_mode=draft' ); ?>" 
                   class="button button-secondary">
                    <span class="dashicons dashicons-edit"></span>
                    Save as Draft
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) . '&publish_mode=publish' ); ?>" 
                   class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    Publish Now
                </a>
            <?php endif; ?>
            
            <?php if ( $job->status === 'pushed' && $job->post_id ) : ?>
                <a href="<?php echo get_edit_post_link( $job->post_id ); ?>" class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-edit"></span>
                    Edit in WordPress
                </a>
                <a href="<?php echo get_permalink( $job->post_id ); ?>" class="button button-secondary" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    View Post
                </a>
            <?php endif; ?>
            
            <?php if ( $job->status === 'failed' ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=retry&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_retry_job' ) ); ?>" 
                   class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    Retry
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="tsa-progress-steps">
        <?php
        $steps = array(
            'queued' => 'Queued',
            'researching' => 'Research',
            'drafting' => 'Writing',
            'qa' => 'QA',
            'image_planning' => 'Images',
            'ready' => 'Ready',
        );
        
        $current_step = array_search( $job->status, array_keys( $steps ) );
        if ( $current_step === false ) {
            $current_step = $job->status === 'pushed' ? 6 : -1;
        }
        
        $step_index = 0;
        foreach ( $steps as $step_key => $step_label ) :
            $step_class = '';
            if ( $step_index < $current_step ) {
                $step_class = 'completed';
            } elseif ( $step_index == $current_step ) {
                $step_class = 'active';
            }
            if ( $job->status === 'failed' ) {
                $step_class = $step_index <= $current_step ? 'failed' : '';
            }
        ?>
            <div class="tsa-step <?php echo esc_attr( $step_class ); ?>">
                <div class="tsa-step-indicator">
                    <?php if ( $step_class === 'completed' ) : ?>
                        <span class="dashicons dashicons-yes"></span>
                    <?php elseif ( $step_class === 'failed' ) : ?>
                        <span class="dashicons dashicons-no"></span>
                    <?php else : ?>
                        <?php echo esc_html( $step_index + 1 ); ?>
                    <?php endif; ?>
                </div>
                <div class="tsa-step-label"><?php echo esc_html( $step_label ); ?></div>
            </div>
        <?php
            $step_index++;
        endforeach;
        ?>
    </div>

    <!-- Content Tabs -->
    <div class="tsa-tabs">
        <nav class="tsa-tab-nav">
            <a href="#preview" class="tsa-tab-link active">Preview</a>
            <a href="#metadata" class="tsa-tab-link">Metadata</a>
            <a href="#research" class="tsa-tab-link">Research Data</a>
            <a href="#images" class="tsa-tab-link">Images</a>
            <a href="#qa" class="tsa-tab-link">QA Results</a>
            <a href="#log" class="tsa-tab-link">Log</a>
        </nav>

        <!-- Preview Tab - Professional Article Style -->
        <div id="preview" class="tsa-tab-content active">
            <?php if ( ! empty( $content_html ) ) : ?>
                <article class="tsa-article-preview">
                    <header class="tsa-article-header">
                        <h1 class="tsa-article-title"><?php echo esc_html( $draft_pack['title'] ?? $job->title_input ); ?></h1>
                        
                        <div class="tsa-article-meta">
                            <?php if ( ! empty( $draft_pack['category_name'] ) ) : ?>
                                <span class="tsa-article-meta-item">
                                    <span class="dashicons dashicons-category"></span>
                                    <?php echo esc_html( $draft_pack['category_name'] ); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $draft_pack['word_count'] ) ) : ?>
                                <span class="tsa-article-meta-item">
                                    <span class="dashicons dashicons-editor-paragraph"></span>
                                    <?php echo number_format( $draft_pack['word_count'] ); ?> kata
                                </span>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $draft_pack['reading_time'] ) ) : ?>
                                <span class="tsa-article-meta-item">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php echo esc_html( $draft_pack['reading_time'] ); ?> baca
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ( ! empty( $draft_pack['meta_description'] ) ) : ?>
                            <p class="tsa-article-excerpt"><?php echo esc_html( $draft_pack['meta_description'] ); ?></p>
                        <?php endif; ?>
                    </header>
                    
                    <div class="tsa-article-body">
                        <?php echo wp_kses_post( $content_html ); ?>
                    </div>
                    
                    <?php if ( ! empty( $draft_pack['internal_links'] ) ) : ?>
                        <div class="tsa-internal-links">
                            <h4>Baca Juga:</h4>
                            <ul>
                                <?php foreach ( $draft_pack['internal_links'] as $link ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $link['url'] ); ?>">
                                            <?php echo esc_html( $link['title'] ); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $draft_pack['tag_names'] ) ) : ?>
                        <div style="margin-top:30px;padding-top:20px;border-top:1px solid #e5e7eb;">
                            <strong>Tags:</strong>
                            <?php foreach ( $draft_pack['tag_names'] as $tag ) : ?>
                                <span style="display:inline-block;background:#e5e7eb;padding:4px 10px;border-radius:4px;margin:3px;font-size:13px;">
                                    <?php echo esc_html( $tag ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-edit"></span>
                    <p>Konten belum dibuat. Status: <?php echo esc_html( $status_info['label'] ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Metadata Tab -->
        <div id="metadata" class="tsa-tab-content">
            <table class="widefat fixed striped">
                <tbody>
                    <tr>
                        <th style="width:200px;">Title</th>
                        <td><strong><?php echo esc_html( $draft_pack['title'] ?? $job->title_input ); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Slug</th>
                        <td><code><?php echo esc_html( $draft_pack['slug'] ?? sanitize_title( $job->title_input ) ); ?></code></td>
                    </tr>
                    <tr>
                        <th>Meta Title</th>
                        <td>
                            <?php echo esc_html( $draft_pack['meta_title'] ?? '' ); ?>
                            <?php if ( ! empty( $draft_pack['meta_title'] ) ) : ?>
                                <span style="color:#6b7280;font-size:12px;">(<?php echo strlen( $draft_pack['meta_title'] ); ?> chars)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Meta Description</th>
                        <td>
                            <?php echo esc_html( $draft_pack['meta_description'] ?? '' ); ?>
                            <?php if ( ! empty( $draft_pack['meta_description'] ) ) : ?>
                                <span style="color:#6b7280;font-size:12px;">(<?php echo strlen( $draft_pack['meta_description'] ); ?> chars)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Word Count</th>
                        <td><?php echo number_format( $draft_pack['word_count'] ?? 0 ); ?> kata</td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo esc_html( $draft_pack['category_name'] ?? '-' ); ?></td>
                    </tr>
                    <tr>
                        <th>Tags</th>
                        <td><?php echo esc_html( implode( ', ', $draft_pack['tag_names'] ?? array() ) ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Research Tab -->
        <div id="research" class="tsa-tab-content">
            <?php if ( ! empty( $research_pack ) ) : ?>
                <h3>Research Data</h3>
                <pre style="background:#f3f4f6;padding:20px;border-radius:8px;overflow:auto;max-height:500px;"><?php echo esc_html( json_encode( $research_pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-search"></span>
                    <p>Research data belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Images Tab -->
        <div id="images" class="tsa-tab-content">
            <?php if ( ! empty( $draft_pack['image_suggestions'] ) ) : ?>
                <h3>Image Suggestions</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                    <?php foreach ( $draft_pack['image_suggestions'] as $img ) : ?>
                        <div style="background:#f9fafb;padding:15px;border-radius:8px;">
                            <strong><?php echo esc_html( $img['position'] ?? 'Image' ); ?></strong>
                            <p style="color:#6b7280;font-size:14px;margin:10px 0 0 0;">
                                Search: "<?php echo esc_html( $img['search_query'] ?? $img['keyword'] ?? '' ); ?>"
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-format-image"></span>
                    <p>Image suggestions belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- QA Tab -->
        <div id="qa" class="tsa-tab-content">
            <?php 
            $qa_results = $draft_pack['qa_results'] ?? array();
            if ( ! empty( $qa_results ) ) : 
            ?>
                <div class="tsa-qa-scores">
                    <div class="tsa-qa-score-card">
                        <div class="tsa-qa-score-value <?php echo ( $qa_results['score'] ?? 0 ) < 60 ? 'warning' : ''; ?>">
                            <?php echo esc_html( $qa_results['score'] ?? 0 ); ?>%
                        </div>
                        <div class="tsa-qa-score-label">Overall Score</div>
                    </div>
                    <div class="tsa-qa-score-card">
                        <div class="tsa-qa-score-value <?php echo ( $qa_results['readability_score'] ?? 0 ) < 60 ? 'warning' : ''; ?>">
                            <?php echo esc_html( $qa_results['readability_score'] ?? 0 ); ?>%
                        </div>
                        <div class="tsa-qa-score-label">Readability</div>
                    </div>
                    <div class="tsa-qa-score-card">
                        <div class="tsa-qa-score-value <?php echo ( $qa_results['seo_score'] ?? 0 ) < 60 ? 'warning' : ''; ?>">
                            <?php echo esc_html( $qa_results['seo_score'] ?? 0 ); ?>%
                        </div>
                        <div class="tsa-qa-score-label">SEO Score</div>
                    </div>
                    <?php if ( ! empty( $qa_results['spin_applied'] ) ) : ?>
                        <div class="tsa-qa-score-card">
                            <div class="tsa-qa-score-value">
                                <?php echo esc_html( $qa_results['spin_percentage'] ?? 0 ); ?>%
                            </div>
                            <div class="tsa-qa-score-label">Spin Applied</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ( ! empty( $qa_results['issues_fixed'] ) ) : ?>
                    <h4>Issues Fixed:</h4>
                    <ul>
                        <?php foreach ( $qa_results['issues_fixed'] as $issue ) : ?>
                            <li style="color:#10b981;">✓ <?php echo esc_html( $issue ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-visibility"></span>
                    <p>QA results belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Log Tab -->
        <div id="log" class="tsa-tab-content">
            <?php if ( ! empty( $job->log ) ) : ?>
                <pre style="background:#1f2937;color:#e5e7eb;padding:20px;border-radius:8px;overflow:auto;max-height:500px;font-size:13px;line-height:1.6;"><?php echo esc_html( $job->log ); ?></pre>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-text-page"></span>
                    <p>Log belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabLinks = document.querySelectorAll('.tsa-tab-link');
    const tabContents = document.querySelectorAll('.tsa-tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href').substring(1);
            
            // Update active states
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });
});
</script>
