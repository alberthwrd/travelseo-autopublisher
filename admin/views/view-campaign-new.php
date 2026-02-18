<?php
/**
 * New Campaign View V2
 *
 * Enhanced with AI Title Suggester, content type selection,
 * and advanced article settings.
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
    $target_words_min = absint( $_POST['target_words_min'] );
    $target_words_max = absint( $_POST['target_words_max'] );
    $language = sanitize_text_field( $_POST['language'] );
    $tone = sanitize_text_field( $_POST['tone'] );
    $content_type = sanitize_text_field( $_POST['content_type'] );
    $spin_content = isset( $_POST['spin_content'] ) ? 1 : 0;
    $spin_intensity = absint( $_POST['spin_intensity'] );

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
            'target_words_min' => $target_words_min,
            'target_words_max' => $target_words_max,
            'language' => $language,
            'tone' => $tone,
            'content_type' => $content_type,
            'spin_content' => $spin_content,
            'spin_intensity' => $spin_intensity,
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
$default_words_min = isset( $settings['default_target_words_min'] ) ? $settings['default_target_words_min'] : 700;
$default_words_max = isset( $settings['default_target_words_max'] ) ? $settings['default_target_words_max'] : 2000;
$default_publish_mode = isset( $settings['default_publish_mode'] ) ? $settings['default_publish_mode'] : 'draft';
$default_spin_intensity = isset( $settings['default_spin_intensity'] ) ? $settings['default_spin_intensity'] : 50;
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

        <!-- AI TITLE SUGGESTER SECTION -->
        <div class="tsa-form-section tsa-title-suggester">
            <h2>
                <span class="dashicons dashicons-lightbulb"></span>
                AI Title Suggester
            </h2>
            <p class="description">Enter a keyword to get AI-powered title suggestions for your articles.</p>

            <div class="tsa-suggester-box">
                <div class="tsa-suggester-input">
                    <input type="text" id="keyword_input" class="regular-text"
                           placeholder="Enter keyword, e.g., 'pantai bali' or 'kuliner bandung'">

                    <select id="content_type_suggest">
                        <option value="auto">Auto Detect</option>
                        <option value="destinasi">Destinasi Wisata</option>
                        <option value="kuliner">Kuliner</option>
                        <option value="hotel">Hotel/Penginapan</option>
                        <option value="aktivitas">Aktivitas</option>
                    </select>

                    <input type="text" id="location_input" class="small-text"
                           placeholder="Location (optional)">

                    <button type="button" class="button button-primary" id="suggest_titles">
                        <span class="dashicons dashicons-admin-generic"></span>
                        Generate Suggestions
                    </button>
                </div>

                <div id="title_suggestions" class="tsa-suggestions-list" style="display: none;">
                    <h4>Click to add titles to your list:</h4>
                    <div id="suggestions_container"></div>
                    <button type="button" class="button" id="add_all_suggestions">Add All Suggestions</button>
                </div>
            </div>
        </div>

        <div class="tsa-form-section">
            <h2>Article Titles</h2>
            <p class="description">Enter one title per line. Each title will become a separate article with 700-2000+ words.</p>

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
                            <strong>Tips:</strong> Use descriptive titles. The 5-AI system will research and write comprehensive articles (700-2000+ words) for each title.
                        </p>
                        <p class="tsa-title-count">
                            <span id="title_count">0</span> title(s) entered
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
            <h2>Content Settings</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="content_type">Content Type</label>
                    </th>
                    <td>
                        <select name="content_type" id="content_type">
                            <option value="auto" selected>Auto Detect from Title</option>
                            <option value="destinasi">Destinasi Wisata</option>
                            <option value="kuliner">Kuliner & Restoran</option>
                            <option value="hotel">Hotel & Penginapan</option>
                        </select>
                        <p class="description">Content type determines the article structure. Auto-detect analyzes each title.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>Target Word Count</label>
                    </th>
                    <td>
                        <input type="number" name="target_words_min" id="target_words_min" class="small-text"
                               value="<?php echo esc_attr( $default_words_min ); ?>" min="500" max="1500" step="100">
                        <span>to</span>
                        <input type="number" name="target_words_max" id="target_words_max" class="small-text"
                               value="<?php echo esc_attr( $default_words_max ); ?>" min="1000" max="5000" step="100">
                        <span>words</span>
                        <p class="description">Target word count range. 5-AI system will generate comprehensive content within this range.</p>
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

        <!-- SPINNER SETTINGS -->
        <div class="tsa-form-section">
            <h2>
                <span class="dashicons dashicons-randomize"></span>
                Content Spinner Settings
            </h2>
            <p class="description">Professional Indonesian text spinner to make content unique and pass AI detection.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="spin_content">Enable Spinner</label>
                    </th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="spin_content" id="spin_content" value="1" checked>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <span class="description">Spin content to make it unique and human-like.</span>
                    </td>
                </tr>

                <tr id="spin_intensity_row">
                    <th scope="row">
                        <label for="spin_intensity">Spin Intensity</label>
                    </th>
                    <td>
                        <input type="range" name="spin_intensity" id="spin_intensity"
                               min="10" max="90" value="<?php echo esc_attr( $default_spin_intensity ); ?>" step="10">
                        <span id="spin_intensity_value"><?php echo esc_attr( $default_spin_intensity ); ?>%</span>
                        <p class="description">
                            <strong>Low (10-30%):</strong> Minimal changes, preserve original style<br>
                            <strong>Medium (40-60%):</strong> Balanced uniqueness and readability<br>
                            <strong>High (70-90%):</strong> Maximum uniqueness, more synonym replacements
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tsa-form-section">
            <h2>Publish Settings</h2>

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
                        <p class="description">Auto-detect will analyze content and create appropriate categories.</p>
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

        <!-- 5-AI WORKFLOW INFO -->
        <div class="tsa-form-section tsa-workflow-info">
            <h2>
                <span class="dashicons dashicons-admin-users"></span>
                5-AI Writer Workflow
            </h2>
            <div class="tsa-ai-workflow">
                <div class="tsa-ai-step">
                    <div class="tsa-ai-icon">🎯</div>
                    <div class="tsa-ai-label">AI #1</div>
                    <div class="tsa-ai-desc">Hook Master</div>
                    <small>Intro & Overview</small>
                </div>
                <div class="tsa-ai-arrow">→</div>
                <div class="tsa-ai-step">
                    <div class="tsa-ai-icon">📚</div>
                    <div class="tsa-ai-label">AI #2</div>
                    <div class="tsa-ai-desc">Storyteller</div>
                    <small>History & Culture</small>
                </div>
                <div class="tsa-ai-arrow">→</div>
                <div class="tsa-ai-step">
                    <div class="tsa-ai-icon">🗺️</div>
                    <div class="tsa-ai-label">AI #3</div>
                    <div class="tsa-ai-desc">Practical Guide</div>
                    <small>Location, Price, Hours</small>
                </div>
                <div class="tsa-ai-arrow">→</div>
                <div class="tsa-ai-step">
                    <div class="tsa-ai-icon">🏠</div>
                    <div class="tsa-ai-label">AI #4</div>
                    <div class="tsa-ai-desc">Local Expert</div>
                    <small>Tips, Food, Activities</small>
                </div>
                <div class="tsa-ai-arrow">→</div>
                <div class="tsa-ai-step">
                    <div class="tsa-ai-icon">✅</div>
                    <div class="tsa-ai-label">AI #5</div>
                    <div class="tsa-ai-desc">SEO Closer</div>
                    <small>Conclusion & FAQ</small>
                </div>
            </div>
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

<style>
/* Title Suggester Styles */
.tsa-title-suggester {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 20px;
    border-radius: 8px;
}
.tsa-title-suggester h2 {
    color: #fff;
    margin-top: 0;
}
.tsa-title-suggester .description {
    color: rgba(255,255,255,0.9);
}
.tsa-suggester-box {
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
}
.tsa-suggester-input {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.tsa-suggester-input input,
.tsa-suggester-input select {
    padding: 8px 12px;
}
.tsa-suggestions-list {
    margin-top: 20px;
    background: #fff;
    color: #333;
    padding: 15px;
    border-radius: 8px;
}
.tsa-suggestions-list h4 {
    margin-top: 0;
    color: #667eea;
}
.tsa-suggestion-item {
    padding: 10px 15px;
    margin: 5px 0;
    background: #f8f9fa;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}
.tsa-suggestion-item:hover {
    background: #e9ecef;
    border-left-color: #667eea;
}
.tsa-suggestion-item.added {
    background: #d4edda;
    border-left-color: #28a745;
}

/* Spinner Styles */
.tsa-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    vertical-align: middle;
    margin-right: 10px;
}
.tsa-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.tsa-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 26px;
}
.tsa-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
.tsa-toggle input:checked + .tsa-toggle-slider {
    background-color: #667eea;
}
.tsa-toggle input:checked + .tsa-toggle-slider:before {
    transform: translateX(24px);
}

/* 5-AI Workflow Styles */
.tsa-ai-workflow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 10px;
}
.tsa-ai-step {
    text-align: center;
    padding: 15px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 120px;
}
.tsa-ai-icon {
    font-size: 32px;
    margin-bottom: 5px;
}
.tsa-ai-label {
    font-weight: bold;
    color: #667eea;
}
.tsa-ai-desc {
    font-size: 12px;
    color: #666;
}
.tsa-ai-step small {
    display: block;
    font-size: 10px;
    color: #999;
    margin-top: 5px;
}
.tsa-ai-arrow {
    font-size: 24px;
    color: #667eea;
}

/* Title Count */
.tsa-title-count {
    margin-top: 10px;
    padding: 8px 12px;
    background: #e7f3ff;
    border-radius: 4px;
    display: inline-block;
}
.tsa-title-count #title_count {
    font-weight: bold;
    color: #0073aa;
}

/* Range slider */
input[type="range"] {
    width: 200px;
    vertical-align: middle;
}
#spin_intensity_value {
    display: inline-block;
    min-width: 40px;
    text-align: center;
    font-weight: bold;
    color: #667eea;
}
</style>

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

    // Toggle spin intensity row
    $('#spin_content').on('change', function() {
        if ($(this).is(':checked')) {
            $('#spin_intensity_row').show();
        } else {
            $('#spin_intensity_row').hide();
        }
    });

    // Update spin intensity value display
    $('#spin_intensity').on('input', function() {
        $('#spin_intensity_value').text($(this).val() + '%');
    });

    // Count titles
    function updateTitleCount() {
        var titles = $('#titles_input').val().trim();
        var count = titles ? titles.split('\n').filter(function(t) { return t.trim().length > 0; }).length : 0;
        $('#title_count').text(count);
    }
    $('#titles_input').on('input', updateTitleCount);
    updateTitleCount();

    // AI Title Suggester
    $('#suggest_titles').on('click', function() {
        var keyword = $('#keyword_input').val().trim();
        if (!keyword) {
            alert('Please enter a keyword first.');
            return;
        }

        var contentType = $('#content_type_suggest').val();
        var location = $('#location_input').val().trim();

        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Generating...');

        // AJAX call to get suggestions
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tsa_suggest_titles',
                keyword: keyword,
                content_type: contentType,
                location: location,
                nonce: '<?php echo wp_create_nonce( 'tsa_suggest_titles' ); ?>'
            },
            success: function(response) {
                if (response.success && response.data.suggestions) {
                    displaySuggestions(response.data.suggestions);
                } else {
                    // Fallback to template-based suggestions
                    var fallbackSuggestions = generateFallbackSuggestions(keyword, location);
                    displaySuggestions(fallbackSuggestions);
                }
            },
            error: function() {
                // Fallback to template-based suggestions
                var fallbackSuggestions = generateFallbackSuggestions(keyword, location);
                displaySuggestions(fallbackSuggestions);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Generate Suggestions');
            }
        });
    });

    function generateFallbackSuggestions(keyword, location) {
        var year = new Date().getFullYear();
        var loc = location || 'Indonesia';
        var templates = [
            keyword + ' ' + year + ': Panduan Lengkap, Harga & Tips',
            'Wisata ' + keyword + ' - Info Lengkap ' + year,
            keyword + ': Harga Tiket, Jam Buka & Fasilitas',
            'Review ' + keyword + ' - Pengalaman Nyata ' + year,
            'Menjelajahi ' + keyword + ' - Destinasi Impian',
            '10 Hal yang Harus Anda Tahu Tentang ' + keyword,
            keyword + ' - Hidden Gem yang Wajib Dikunjungi',
            'Tips Berkunjung ke ' + keyword + ' untuk Pemula',
            keyword + ' vs Destinasi Lain: Mana yang Lebih Worth It?',
            'Liburan ke ' + keyword + ' - Itinerary & Budget Lengkap'
        ];
        return templates;
    }

    function displaySuggestions(suggestions) {
        var $container = $('#suggestions_container');
        $container.empty();

        suggestions.forEach(function(title) {
            var $item = $('<div class="tsa-suggestion-item">' + title + '</div>');
            $item.on('click', function() {
                addTitleToList(title);
                $(this).addClass('added');
            });
            $container.append($item);
        });

        $('#title_suggestions').show();
    }

    function addTitleToList(title) {
        var currentTitles = $('#titles_input').val().trim();
        if (currentTitles) {
            // Check if title already exists
            if (currentTitles.toLowerCase().indexOf(title.toLowerCase()) === -1) {
                $('#titles_input').val(currentTitles + '\n' + title);
            }
        } else {
            $('#titles_input').val(title);
        }
        updateTitleCount();
    }

    $('#add_all_suggestions').on('click', function() {
        $('.tsa-suggestion-item:not(.added)').each(function() {
            addTitleToList($(this).text());
            $(this).addClass('added');
        });
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
            updateTitleCount();

            alert(titles.length + ' titles imported successfully!');
        };

        reader.readAsText(file);
    });
});
</script>
