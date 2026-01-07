<?php
/**
 * New Campaign View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form submission
if ( isset( $_POST['tsa_create_campaign'] ) && check_admin_referer( 'tsa_create_campaign_nonce' ) ) {
    global $wpdb;
    
    $campaign_name = sanitize_text_field( $_POST['campaign_name'] );
    $titles_input = sanitize_textarea_field( $_POST['titles_input'] );
    $publish_mode = sanitize_text_field( $_POST['publish_mode'] );
    $target_words = absint( $_POST['target_words'] );
    $language = sanitize_text_field( $_POST['language'] );
    $tone = sanitize_text_field( $_POST['tone'] );
    
    // Create campaign
    $table_campaigns = $wpdb->prefix . 'tsa_campaigns';
    $wpdb->insert( $table_campaigns, array( 'name' => $campaign_name ) );
    $campaign_id = $wpdb->insert_id;
    
    // Parse titles
    $titles = array_filter( array_map( 'trim', explode( "\n", $titles_input ) ) );
    
    // Create jobs for each title
    $table_jobs = $wpdb->prefix . 'tsa_jobs';
    $jobs_created = 0;
    
    foreach ( $titles as $title ) {
        if ( empty( $title ) ) {
            continue;
        }
        
        $settings = wp_json_encode( array(
            'publish_mode' => $publish_mode,
            'target_words' => $target_words,
            'language' => $language,
            'tone' => $tone,
        ) );
        
        $wpdb->insert( $table_jobs, array(
            'campaign_id' => $campaign_id,
            'title_input' => $title,
            'status' => 'queued',
            'settings' => $settings,
        ) );
        
        $jobs_created++;
        
        // Schedule the job for background processing
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( time(), 'tsa_process_job', array( 'job_id' => $wpdb->insert_id ), 'travelseo-autopublisher' );
        } else {
            wp_schedule_single_event( time(), 'tsa_process_job_cron', array( $wpdb->insert_id ) );
        }
    }
    
    $success_message = sprintf( 
        'Campaign "%s" created successfully with %d job(s). <a href="%s">View Jobs</a>', 
        esc_html( $campaign_name ), 
        $jobs_created,
        admin_url( 'admin.php?page=travelseo-autopublisher-jobs&campaign_id=' . $campaign_id )
    );
}

// Get categories
$categories = get_categories( array( 'hide_empty' => false ) );

// Get default settings
$settings = get_option( 'tsa_settings', array() );
$default_words = isset( $settings['default_target_words'] ) ? $settings['default_target_words'] : 2000;
$default_publish_mode = isset( $settings['default_publish_mode'] ) ? $settings['default_publish_mode'] : 'draft';
?>

<div class="wrap tsa-new-campaign">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-plus-alt"></span>
        Create New Campaign
    </h1>
    <hr class="wp-header-end">

    <?php if ( isset( $success_message ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="tsa-campaign-form">
        <?php wp_nonce_field( 'tsa_create_campaign_nonce' ); ?>
        
        <div class="tsa-form-section">
            <h2>Campaign Details</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="campaign_name">Campaign Name</label>
                    </th>
                    <td>
                        <input type="text" name="campaign_name" id="campaign_name" class="regular-text" required 
                               placeholder="e.g., Wisata Bali Januari 2026" 
                               value="Campaign <?php echo date( 'Y-m-d H:i' ); ?>">
                        <p class="description">A name to identify this batch of articles.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tsa-form-section">
            <h2>Article Titles</h2>
            <p class="description">Enter one title per line. Each title will become a separate article.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="titles_input">Titles (one per line)</label>
                    </th>
                    <td>
                        <textarea name="titles_input" id="titles_input" rows="10" class="large-text code" required
                                  placeholder="Pantai Kuta Bali
Tanah Lot Bali
Ubud Monkey Forest
Nusa Penida Island
Tirta Empul Temple"></textarea>
                        <p class="description">
                            <strong>Tips:</strong> Use descriptive titles like "Pantai Kuta Bali" or "Wisata Candi Borobudur".
                            The plugin will research and write comprehensive articles for each title.
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="tsa-bulk-import">
                <h3>Bulk Import</h3>
                <p>
                    <label for="csv_file">Import from CSV file:</label>
                    <input type="file" id="csv_file" accept=".csv,.txt">
                    <button type="button" class="button" id="import_csv">Import</button>
                </p>
            </div>
        </div>

        <div class="tsa-form-section">
            <h2>Article Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="publish_mode">Publish Mode</label>
                    </th>
                    <td>
                        <select name="publish_mode" id="publish_mode">
                            <option value="draft" <?php selected( $default_publish_mode, 'draft' ); ?>>Save as Draft</option>
                            <option value="publish" <?php selected( $default_publish_mode, 'publish' ); ?>>Publish Immediately</option>
                            <option value="pending" <?php selected( $default_publish_mode, 'pending' ); ?>>Pending Review</option>
                            <option value="schedule" <?php selected( $default_publish_mode, 'schedule' ); ?>>Schedule</option>
                        </select>
                        <p class="description">How articles should be published when pushed to WordPress.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="target_words">Target Word Count</label>
                    </th>
                    <td>
                        <input type="number" name="target_words" id="target_words" class="small-text" 
                               value="<?php echo esc_attr( $default_words ); ?>" min="500" max="5000" step="100">
                        <p class="description">Target number of words for each article (500-5000).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="language">Language</label>
                    </th>
                    <td>
                        <select name="language" id="language">
                            <option value="id" selected>Bahasa Indonesia</option>
                            <option value="en">English</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tone">Writing Tone</label>
                    </th>
                    <td>
                        <select name="tone" id="tone">
                            <option value="informative" selected>Informative</option>
                            <option value="casual">Casual/Friendly</option>
                            <option value="professional">Professional</option>
                            <option value="enthusiastic">Enthusiastic</option>
                        </select>
                        <p class="description">The tone of voice for the articles.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tsa-form-section">
            <h2>Category & Tags</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="category_mode">Category Mode</label>
                    </th>
                    <td>
                        <select name="category_mode" id="category_mode">
                            <option value="auto" selected>Auto-detect & Create</option>
                            <option value="select">Select Existing Category</option>
                        </select>
                    </td>
                </tr>
                
                <tr id="category_select_row" style="display: none;">
                    <th scope="row">
                        <label for="category_id">Select Category</label>
                    </th>
                    <td>
                        <select name="category_id" id="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tag_count">Number of Tags</label>
                    </th>
                    <td>
                        <input type="number" name="tag_count" id="tag_count" class="small-text" 
                               value="5" min="3" max="10">
                        <p class="description">Number of tags to generate per article (3-10).</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tsa-form-section">
            <h2>Image Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="image_mode">Image Mode</label>
                    </th>
                    <td>
                        <select name="image_mode" id="image_mode">
                            <option value="recommend" selected>Recommend Only (Show suggestions)</option>
                            <option value="media_library">Search Media Library</option>
                            <option value="auto_fetch">Auto-fetch from Unsplash/Pexels</option>
                        </select>
                        <p class="description">How images should be handled. Auto-fetch requires API keys in Settings.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tsa-form-actions">
            <button type="submit" name="tsa_create_campaign" class="button button-primary button-hero">
                <span class="dashicons dashicons-yes"></span>
                Create Campaign & Start Processing
            </button>
            <a href="<?php echo admin_url( 'admin.php?page=travelseo-autopublisher' ); ?>" class="button button-secondary button-hero">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle category select
    $('#category_mode').on('change', function() {
        if ($(this).val() === 'select') {
            $('#category_select_row').show();
        } else {
            $('#category_select_row').hide();
        }
    });
    
    // CSV Import
    $('#import_csv').on('click', function() {
        var fileInput = $('#csv_file')[0];
        if (fileInput.files.length === 0) {
            alert('Please select a CSV file first.');
            return;
        }
        
        var file = fileInput.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var content = e.target.result;
            var lines = content.split('\n');
            var titles = [];
            
            lines.forEach(function(line) {
                line = line.trim();
                if (line && line.length > 0) {
                    // Handle CSV with quotes
                    line = line.replace(/^"(.*)"$/, '$1');
                    titles.push(line);
                }
            });
            
            var currentTitles = $('#titles_input').val().trim();
            if (currentTitles) {
                currentTitles += '\n';
            }
            $('#titles_input').val(currentTitles + titles.join('\n'));
            
            alert(titles.length + ' titles imported successfully!');
        };
        
        reader.readAsText(file);
    });
});
</script>
