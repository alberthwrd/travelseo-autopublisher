<?php
/**
 * Job Detail View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
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
    'queued' => array( 'label' => 'Queued', 'icon' => 'clock', 'color' => 'gray' ),
    'researching' => array( 'label' => 'Researching', 'icon' => 'search', 'color' => 'blue' ),
    'drafting' => array( 'label' => 'Drafting', 'icon' => 'edit', 'color' => 'blue' ),
    'qa' => array( 'label' => 'QA Review', 'icon' => 'visibility', 'color' => 'orange' ),
    'image_planning' => array( 'label' => 'Image Planning', 'icon' => 'format-image', 'color' => 'purple' ),
    'ready' => array( 'label' => 'Ready', 'icon' => 'yes-alt', 'color' => 'green' ),
    'pushed' => array( 'label' => 'Published', 'icon' => 'admin-post', 'color' => 'teal' ),
    'failed' => array( 'label' => 'Failed', 'icon' => 'warning', 'color' => 'red' ),
);

$status_info = isset( $statuses[ $job->status ] ) ? $statuses[ $job->status ] : array( 'label' => ucfirst( $job->status ), 'icon' => 'marker', 'color' => 'gray' );

// Get campaign name
$table_campaigns = $wpdb->prefix . 'tsa_campaigns';
$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_campaigns WHERE id = %d", $job->campaign_id ) );
?>

<div class="wrap tsa-job-detail">
    <h1 class="wp-heading-inline">
        <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs' ); ?>" class="tsa-back-link">
            <span class="dashicons dashicons-arrow-left-alt"></span>
        </a>
        Job #<?php echo esc_html( $job->id ); ?>: <?php echo esc_html( $job->title_input ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- Status Bar -->
    <div class="tsa-status-bar">
        <div class="tsa-status-info">
            <span class="tsa-status-badge tsa-status-<?php echo esc_attr( $job->status ); ?>">
                <span class="dashicons dashicons-<?php echo esc_attr( $status_info['icon'] ); ?>"></span>
                <?php echo esc_html( $status_info['label'] ); ?>
            </span>
            <span class="tsa-meta">
                Created: <?php echo esc_html( $job->created_at ); ?>
            </span>
            <?php if ( $campaign ) : ?>
                <span class="tsa-meta">
                    Campaign: <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&campaign_id=' . $campaign->id ); ?>">
                        <?php echo esc_html( $campaign->name ); ?>
                    </a>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="tsa-status-actions">
            <?php if ( $job->status === 'ready' ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce( 'tsa_push_job' ) . '&publish_mode=draft' ); ?>" 
                   class="button button-secondary">
                    <span class="dashicons dashicons-edit"></span>
                    Create Draft
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

        <!-- Preview Tab -->
        <div id="preview" class="tsa-tab-content active">
            <?php if ( ! empty( $draft_pack['content'] ) ) : ?>
                <div class="tsa-preview-header">
                    <h2><?php echo esc_html( $draft_pack['title'] ?? $job->title_input ); ?></h2>
                    <?php if ( ! empty( $draft_pack['meta_description'] ) ) : ?>
                        <p class="tsa-meta-desc"><?php echo esc_html( $draft_pack['meta_description'] ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="tsa-preview-content">
                    <?php echo wp_kses_post( $draft_pack['content'] ); ?>
                </div>
                
                <?php if ( ! empty( $draft_pack['faq'] ) ) : ?>
                    <div class="tsa-faq-section">
                        <h2>FAQ - Pertanyaan yang Sering Diajukan</h2>
                        <?php foreach ( $draft_pack['faq'] as $faq ) : ?>
                            <div class="tsa-faq-item">
                                <h3><?php echo esc_html( $faq['question'] ); ?></h3>
                                <p><?php echo esc_html( $faq['answer'] ); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-edit"></span>
                    <p>Content not yet generated. Job is currently: <?php echo esc_html( $status_info['label'] ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Metadata Tab -->
        <div id="metadata" class="tsa-tab-content">
            <table class="widefat fixed">
                <tbody>
                    <tr>
                        <th>Title</th>
                        <td><?php echo esc_html( $draft_pack['title'] ?? $job->title_input ); ?></td>
                    </tr>
                    <tr>
                        <th>Slug</th>
                        <td><code><?php echo esc_html( $draft_pack['slug'] ?? '' ); ?></code></td>
                    </tr>
                    <tr>
                        <th>Meta Title</th>
                        <td>
                            <?php echo esc_html( $draft_pack['meta_title'] ?? '' ); ?>
                            <?php if ( ! empty( $draft_pack['meta_title'] ) ) : ?>
                                <span class="tsa-char-count">(<?php echo strlen( $draft_pack['meta_title'] ); ?> chars)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Meta Description</th>
                        <td>
                            <?php echo esc_html( $draft_pack['meta_description'] ?? '' ); ?>
                            <?php if ( ! empty( $draft_pack['meta_description'] ) ) : ?>
                                <span class="tsa-char-count">(<?php echo strlen( $draft_pack['meta_description'] ); ?> chars)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Word Count</th>
                        <td><?php echo esc_html( $draft_pack['word_count'] ?? 0 ); ?> words</td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo esc_html( $draft_pack['category_name'] ?? 'Not set' ); ?></td>
                    </tr>
                    <tr>
                        <th>Tags</th>
                        <td>
                            <?php if ( ! empty( $draft_pack['tag_names'] ) ) : ?>
                                <?php foreach ( $draft_pack['tag_names'] as $tag ) : ?>
                                    <span class="tsa-tag"><?php echo esc_html( $tag ); ?></span>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <em>No tags</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Internal Links</th>
                        <td>
                            <?php if ( ! empty( $draft_pack['internal_links'] ) ) : ?>
                                <ul>
                                    <?php foreach ( $draft_pack['internal_links'] as $link ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank">
                                                <?php echo esc_html( $link['title'] ); ?>
                                            </a>
                                            <small>(keyword: <?php echo esc_html( $link['keyword'] ); ?>)</small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <em>No internal links suggested</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Research Tab -->
        <div id="research" class="tsa-tab-content">
            <?php if ( ! empty( $research_pack ) ) : ?>
                <h3>Sources</h3>
                <?php if ( ! empty( $research_pack['sources'] ) ) : ?>
                    <ul class="tsa-sources-list">
                        <?php foreach ( $research_pack['sources'] as $source ) : ?>
                            <li>
                                <strong><?php echo esc_html( $source['title'] ?? $source['original_title'] ?? 'Unknown' ); ?></strong>
                                <br>
                                <a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html( $source['url'] ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Extracted Facts</h3>
                <?php if ( ! empty( $research_pack['facts'] ) ) : ?>
                    <ul>
                        <?php foreach ( $research_pack['facts'] as $fact ) : ?>
                            <li><?php echo esc_html( $fact ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><em>No facts extracted</em></p>
                <?php endif; ?>

                <h3>Keywords</h3>
                <?php if ( ! empty( $research_pack['keywords'] ) ) : ?>
                    <div class="tsa-keywords">
                        <?php foreach ( $research_pack['keywords'] as $keyword ) : ?>
                            <span class="tsa-tag"><?php echo esc_html( $keyword ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3>Location Info</h3>
                <p><?php echo esc_html( $research_pack['location_info'] ?? 'Not available' ); ?></p>

                <h3>Pricing Info</h3>
                <p><?php echo esc_html( $research_pack['pricing_info'] ?? 'Not available' ); ?></p>

                <h3>Operating Hours</h3>
                <p><?php echo esc_html( $research_pack['hours_info'] ?? 'Not available' ); ?></p>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-search"></span>
                    <p>Research data not yet available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Images Tab -->
        <div id="images" class="tsa-tab-content">
            <?php if ( ! empty( $draft_pack['image_recommendations'] ) ) : ?>
                <div class="tsa-image-recommendations">
                    <?php foreach ( $draft_pack['image_recommendations'] as $rec ) : ?>
                        <div class="tsa-image-rec">
                            <h4><?php echo esc_html( $rec['section'] ); ?></h4>
                            <p><strong>Search Keywords:</strong> <?php echo esc_html( $rec['primary_keyword'] ?? '' ); ?></p>
                            <p><strong>Suggested Alt Text:</strong> <?php echo esc_html( $rec['suggested_alt_text'] ?? '' ); ?></p>
                            <?php if ( isset( $rec['fetched_image'] ) ) : ?>
                                <div class="tsa-fetched-image">
                                    <img src="<?php echo esc_url( $rec['fetched_image']['thumb'] ?? $rec['fetched_image']['url'] ); ?>" alt="">
                                    <p><small>Photo by <?php echo esc_html( $rec['fetched_image']['photographer'] ?? 'Unknown' ); ?></small></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-format-image"></span>
                    <p>Image recommendations not yet available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- QA Tab -->
        <div id="qa" class="tsa-tab-content">
            <?php if ( ! empty( $draft_pack['qa_results'] ) ) : ?>
                <?php $qa = $draft_pack['qa_results']; ?>
                
                <div class="tsa-qa-score">
                    <div class="tsa-score-circle <?php echo $qa['score'] >= 60 ? 'good' : 'poor'; ?>">
                        <span class="score"><?php echo esc_html( $qa['score'] ); ?></span>
                        <span class="label">Score</span>
                    </div>
                </div>

                <?php if ( ! empty( $qa['checks'] ) ) : ?>
                    <h3>Checks</h3>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th>Check</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $qa['checks'] as $check_name => $check_data ) : ?>
                                <?php if ( is_array( $check_data ) && isset( $check_data['passed'] ) ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $check_name ) ) ); ?></td>
                                        <td>
                                            <?php if ( $check_data['passed'] ) : ?>
                                                <span class="tsa-status-badge tsa-status-ready">Passed</span>
                                            <?php else : ?>
                                                <span class="tsa-status-badge tsa-status-failed">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $check_data['message'] ?? '' ); ?></td>
                                    </tr>
                                <?php elseif ( is_array( $check_data ) ) : ?>
                                    <?php foreach ( $check_data as $sub_check_name => $sub_check ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $check_name ) ) ); ?> - <?php echo esc_html( ucfirst( str_replace( '_', ' ', $sub_check_name ) ) ); ?></td>
                                            <td>
                                                <?php if ( $sub_check['passed'] ) : ?>
                                                    <span class="tsa-status-badge tsa-status-ready">Passed</span>
                                                <?php else : ?>
                                                    <span class="tsa-status-badge tsa-status-failed">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html( $sub_check['message'] ?? '' ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ( ! empty( $qa['improvements'] ) ) : ?>
                    <h3>Suggested Improvements</h3>
                    <ul class="tsa-improvements">
                        <?php foreach ( $qa['improvements'] as $improvement ) : ?>
                            <li><?php echo esc_html( $improvement ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ( ! empty( $qa['warnings'] ) ) : ?>
                    <h3>Warnings</h3>
                    <ul class="tsa-warnings">
                        <?php foreach ( $qa['warnings'] as $warning ) : ?>
                            <li><?php echo esc_html( $warning ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-visibility"></span>
                    <p>QA results not yet available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Log Tab -->
        <div id="log" class="tsa-tab-content">
            <div class="tsa-log-viewer">
                <?php if ( ! empty( $job->log ) ) : ?>
                    <pre><?php echo esc_html( $job->log ); ?></pre>
                <?php else : ?>
                    <p><em>No log entries yet.</em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.tsa-tab-link').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.tsa-tab-link').removeClass('active');
        $(this).addClass('active');
        
        $('.tsa-tab-content').removeClass('active');
        $(target).addClass('active');
    });
});
</script>
