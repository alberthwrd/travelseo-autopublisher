<?php
/**
 * Settings View
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form submission
if ( isset( $_POST['tsa_save_settings'] ) && check_admin_referer( 'tsa_settings_nonce' ) ) {
    $settings = array(
        // AI Settings
        'openai_api_key' => sanitize_text_field( $_POST['openai_api_key'] ?? '' ),
        'openai_endpoint' => esc_url_raw( $_POST['openai_endpoint'] ?? 'https://api.openai.com/v1/chat/completions' ),
        'openai_model' => sanitize_text_field( $_POST['openai_model'] ?? 'gpt-3.5-turbo' ),
        
        // SERP API
        'serp_api_key' => sanitize_text_field( $_POST['serp_api_key'] ?? '' ),
        
        // Image APIs
        'unsplash_api_key' => sanitize_text_field( $_POST['unsplash_api_key'] ?? '' ),
        'pexels_api_key' => sanitize_text_field( $_POST['pexels_api_key'] ?? '' ),
        
        // Default Settings
        'default_target_words' => absint( $_POST['default_target_words'] ?? 2000 ),
        'default_publish_mode' => sanitize_text_field( $_POST['default_publish_mode'] ?? 'draft' ),
        'default_language' => sanitize_text_field( $_POST['default_language'] ?? 'id' ),
        'default_tone' => sanitize_text_field( $_POST['default_tone'] ?? 'informative' ),
        
        // Scraping Settings
        'respect_robots' => isset( $_POST['respect_robots'] ) ? true : false,
        'rate_limit' => absint( $_POST['rate_limit'] ?? 5 ),
        'request_timeout' => absint( $_POST['request_timeout'] ?? 15 ),
        
        // Content Settings
        'add_disclosure' => isset( $_POST['add_disclosure'] ) ? true : false,
        'image_mode' => sanitize_text_field( $_POST['image_mode'] ?? 'recommend' ),
        
        // SEO Integration
        'seo_plugin' => sanitize_text_field( $_POST['seo_plugin'] ?? 'auto' ),
        
        // Debug
        'debug_mode' => isset( $_POST['debug_mode'] ) ? true : false,
        
        // GitHub Update Settings
        'github_username' => sanitize_text_field( $_POST['github_username'] ?? '' ),
        'github_repo' => sanitize_text_field( $_POST['github_repo'] ?? 'travelseo-autopublisher' ),
        'github_token' => sanitize_text_field( $_POST['github_token'] ?? '' ),
        
        // Advanced SEO Automation Feature Flags
        'feature_topical_cluster' => isset( $_POST['feature_topical_cluster'] ) ? true : false,
        'feature_auto_internal_link' => isset( $_POST['feature_auto_internal_link'] ) ? true : false,
        'feature_local_facts_table' => isset( $_POST['feature_local_facts_table'] ) ? true : false,
        'feature_safety_guard' => isset( $_POST['feature_safety_guard'] ) ? true : false,
        'feature_schema_jsonld' => isset( $_POST['feature_schema_jsonld'] ) ? true : false,
        'feature_paa_harvester' => isset( $_POST['feature_paa_harvester'] ) ? true : false,
        'feature_cannibalization_checker' => isset( $_POST['feature_cannibalization_checker'] ) ? true : false,
        'feature_freshness_updater' => isset( $_POST['feature_freshness_updater'] ) ? true : false,
        'feature_template_variations' => isset( $_POST['feature_template_variations'] ) ? true : false,
        'feature_editorial_workflow' => isset( $_POST['feature_editorial_workflow'] ) ? true : false,
        'feature_image_pipeline_pro' => isset( $_POST['feature_image_pipeline_pro'] ) ? true : false,
        'feature_image_optimization' => isset( $_POST['feature_image_optimization'] ) ? true : false,
        'feature_map_embed' => isset( $_POST['feature_map_embed'] ) ? true : false,
        'feature_nearby_places' => isset( $_POST['feature_nearby_places'] ) ? true : false,
        'feature_intent_cta' => isset( $_POST['feature_intent_cta'] ) ? true : false,
        'feature_robots_cache' => isset( $_POST['feature_robots_cache'] ) ? true : false,
        'feature_job_priorities' => isset( $_POST['feature_job_priorities'] ) ? true : false,
        'feature_crash_safe' => isset( $_POST['feature_crash_safe'] ) ? true : false,
        'feature_quality_gate' => isset( $_POST['feature_quality_gate'] ) ? true : false,
        'feature_deep_seo_integration' => isset( $_POST['feature_deep_seo_integration'] ) ? true : false,
    );
    
    update_option( 'tsa_settings', $settings );
    $success_message = 'Settings saved successfully.';
}

// Get current settings
$settings = get_option( 'tsa_settings', array() );

// Defaults
$defaults = array(
    'openai_api_key' => '',
    'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',
    'openai_model' => 'gpt-3.5-turbo',
    'serp_api_key' => '',
    'unsplash_api_key' => '',
    'pexels_api_key' => '',
    'default_target_words' => 2000,
    'default_publish_mode' => 'draft',
    'default_language' => 'id',
    'default_tone' => 'informative',
    'respect_robots' => true,
    'rate_limit' => 5,
    'request_timeout' => 15,
    'add_disclosure' => true,
    'image_mode' => 'recommend',
    'seo_plugin' => 'auto',
    'debug_mode' => false,
    // GitHub
    'github_username' => '',
    'github_repo' => 'travelseo-autopublisher',
    'github_token' => '',
    // Feature Flags (all OFF by default)
    'feature_topical_cluster' => false,
    'feature_auto_internal_link' => false,
    'feature_local_facts_table' => false,
    'feature_safety_guard' => false,
    'feature_schema_jsonld' => false,
    'feature_paa_harvester' => false,
    'feature_cannibalization_checker' => false,
    'feature_freshness_updater' => false,
    'feature_template_variations' => false,
    'feature_editorial_workflow' => false,
    'feature_image_pipeline_pro' => false,
    'feature_image_optimization' => false,
    'feature_map_embed' => false,
    'feature_nearby_places' => false,
    'feature_intent_cta' => false,
    'feature_robots_cache' => false,
    'feature_job_priorities' => false,
    'feature_crash_safe' => false,
    'feature_quality_gate' => false,
    'feature_deep_seo_integration' => false,
);

$settings = wp_parse_args( $settings, $defaults );
?>

<div class="wrap tsa-settings">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-settings"></span>
        TravelSEO Settings
    </h1>
    <hr class="wp-header-end">

    <?php if ( isset( $success_message ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $success_message ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'tsa_settings_nonce' ); ?>

        <!-- API Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-cloud"></span>
                AI API Settings (Optional)
            </h2>
            <p class="description">
                <strong>Note:</strong> API keys are optional. Without them, the plugin will use free methods (web scraping + template-based content generation).
                With API keys, you get higher quality AI-generated content.
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="openai_api_key">OpenAI API Key</label>
                    </th>
                    <td>
                        <input type="password" name="openai_api_key" id="openai_api_key" class="regular-text" 
                               value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>">
                        <p class="description">
                            Your OpenAI API key. Also works with OpenAI-compatible endpoints (DeepSeek, OpenRouter, etc.)
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="openai_endpoint">API Endpoint</label>
                    </th>
                    <td>
                        <input type="url" name="openai_endpoint" id="openai_endpoint" class="regular-text" 
                               value="<?php echo esc_attr( $settings['openai_endpoint'] ); ?>">
                        <p class="description">
                            Default: https://api.openai.com/v1/chat/completions<br>
                            For DeepSeek: https://api.deepseek.com/v1/chat/completions<br>
                            For OpenRouter: https://openrouter.ai/api/v1/chat/completions
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="openai_model">Model Name</label>
                    </th>
                    <td>
                        <input type="text" name="openai_model" id="openai_model" class="regular-text" 
                               value="<?php echo esc_attr( $settings['openai_model'] ); ?>">
                        <p class="description">
                            Examples: gpt-3.5-turbo, gpt-4, deepseek-chat, etc.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="serp_api_key">SERP API Key</label>
                    </th>
                    <td>
                        <input type="password" name="serp_api_key" id="serp_api_key" class="regular-text" 
                               value="<?php echo esc_attr( $settings['serp_api_key'] ); ?>">
                        <p class="description">
                            Optional. For better search results. Get from <a href="https://serpapi.com" target="_blank">serpapi.com</a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Image API Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-format-image"></span>
                Image API Settings (Optional)
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="unsplash_api_key">Unsplash API Key</label>
                    </th>
                    <td>
                        <input type="password" name="unsplash_api_key" id="unsplash_api_key" class="regular-text" 
                               value="<?php echo esc_attr( $settings['unsplash_api_key'] ); ?>">
                        <p class="description">
                            For auto-fetching royalty-free images. Get from <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pexels_api_key">Pexels API Key</label>
                    </th>
                    <td>
                        <input type="password" name="pexels_api_key" id="pexels_api_key" class="regular-text" 
                               value="<?php echo esc_attr( $settings['pexels_api_key'] ); ?>">
                        <p class="description">
                            Alternative image source. Get from <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="image_mode">Image Mode</label>
                    </th>
                    <td>
                        <select name="image_mode" id="image_mode">
                            <option value="recommend" <?php selected( $settings['image_mode'], 'recommend' ); ?>>Recommend Only</option>
                            <option value="media_library" <?php selected( $settings['image_mode'], 'media_library' ); ?>>Search Media Library</option>
                            <option value="auto_fetch" <?php selected( $settings['image_mode'], 'auto_fetch' ); ?>>Auto-fetch from API</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Default Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-generic"></span>
                Default Article Settings
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_target_words">Target Word Count</label>
                    </th>
                    <td>
                        <input type="number" name="default_target_words" id="default_target_words" class="small-text" 
                               value="<?php echo esc_attr( $settings['default_target_words'] ); ?>" min="500" max="5000">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_publish_mode">Default Publish Mode</label>
                    </th>
                    <td>
                        <select name="default_publish_mode" id="default_publish_mode">
                            <option value="draft" <?php selected( $settings['default_publish_mode'], 'draft' ); ?>>Draft</option>
                            <option value="publish" <?php selected( $settings['default_publish_mode'], 'publish' ); ?>>Publish</option>
                            <option value="pending" <?php selected( $settings['default_publish_mode'], 'pending' ); ?>>Pending Review</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_language">Default Language</label>
                    </th>
                    <td>
                        <select name="default_language" id="default_language">
                            <option value="id" <?php selected( $settings['default_language'], 'id' ); ?>>Bahasa Indonesia</option>
                            <option value="en" <?php selected( $settings['default_language'], 'en' ); ?>>English</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_tone">Default Tone</label>
                    </th>
                    <td>
                        <select name="default_tone" id="default_tone">
                            <option value="informative" <?php selected( $settings['default_tone'], 'informative' ); ?>>Informative</option>
                            <option value="casual" <?php selected( $settings['default_tone'], 'casual' ); ?>>Casual</option>
                            <option value="professional" <?php selected( $settings['default_tone'], 'professional' ); ?>>Professional</option>
                            <option value="enthusiastic" <?php selected( $settings['default_tone'], 'enthusiastic' ); ?>>Enthusiastic</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Scraping Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-site"></span>
                Web Scraping Settings
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Respect robots.txt</th>
                    <td>
                        <label>
                            <input type="checkbox" name="respect_robots" value="1" <?php checked( $settings['respect_robots'] ); ?>>
                            Follow robots.txt rules when scraping
                        </label>
                        <p class="description">Recommended to keep enabled for ethical scraping.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rate_limit">Rate Limit (requests/minute)</label>
                    </th>
                    <td>
                        <input type="number" name="rate_limit" id="rate_limit" class="small-text" 
                               value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" min="1" max="30">
                        <p class="description">Maximum requests per minute to external sites.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="request_timeout">Request Timeout (seconds)</label>
                    </th>
                    <td>
                        <input type="number" name="request_timeout" id="request_timeout" class="small-text" 
                               value="<?php echo esc_attr( $settings['request_timeout'] ); ?>" min="5" max="60">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Content Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-editor-alignleft"></span>
                Content Settings
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Disclosure Note</th>
                    <td>
                        <label>
                            <input type="checkbox" name="add_disclosure" value="1" <?php checked( $settings['add_disclosure'] ); ?>>
                            Add disclaimer note at the end of articles
                        </label>
                        <p class="description">Adds a note: "Informasi dapat berubah, cek sumber resmi."</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SEO Integration -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-chart-line"></span>
                SEO Integration
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="seo_plugin">SEO Plugin</label>
                    </th>
                    <td>
                        <select name="seo_plugin" id="seo_plugin">
                            <option value="auto" <?php selected( $settings['seo_plugin'], 'auto' ); ?>>Auto-detect</option>
                            <option value="yoast" <?php selected( $settings['seo_plugin'], 'yoast' ); ?>>Yoast SEO</option>
                            <option value="rankmath" <?php selected( $settings['seo_plugin'], 'rankmath' ); ?>>Rank Math</option>
                            <option value="none" <?php selected( $settings['seo_plugin'], 'none' ); ?>>None</option>
                        </select>
                        <p class="description">Plugin will automatically set meta title/description if SEO plugin is detected.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- GitHub Update Settings -->
        <div class="tsa-settings-section" id="github-update">
            <h2>
                <span class="dashicons dashicons-update"></span>
                GitHub Auto-Update Settings
            </h2>
            <p class="description">Configure automatic updates from your GitHub repository.</p>
            
            <!-- Update Notification Box -->
            <div id="tsa-update-notification" class="tsa-update-box" style="display: none;">
                <div class="tsa-update-header">
                    <span class="dashicons dashicons-info"></span>
                    <strong>Update Available!</strong>
                </div>
                <div class="tsa-update-content">
                    <p>
                        <strong>Current Version:</strong> <span id="tsa-current-ver"><?php echo esc_html( TSA_VERSION ); ?></span><br>
                        <strong>Latest Version:</strong> <span id="tsa-latest-ver">-</span><br>
                        <strong>Released:</strong> <span id="tsa-release-date">-</span>
                    </p>
                    <div class="tsa-update-actions">
                        <button type="button" class="button button-primary" id="tsa-do-update">
                            <span class="dashicons dashicons-update"></span>
                            Update Now
                        </button>
                        <a href="#" id="tsa-view-changelog" class="button button-secondary">View Changelog</a>
                        <a href="#" id="tsa-release-link" class="button button-link" target="_blank">View on GitHub</a>
                    </div>
                </div>
                <div id="tsa-changelog-box" class="tsa-changelog" style="display: none;">
                    <h4>Changelog</h4>
                    <div id="tsa-changelog-content"></div>
                </div>
            </div>
            
            <!-- Up to Date Box -->
            <div id="tsa-uptodate-notification" class="tsa-uptodate-box" style="display: none;">
                <span class="dashicons dashicons-yes-alt"></span>
                <strong>You're up to date!</strong> Version <?php echo esc_html( TSA_VERSION ); ?> is the latest version.
            </div>
            
            <!-- Update Progress Box -->
            <div id="tsa-update-progress" class="tsa-progress-box" style="display: none;">
                <div class="tsa-progress-spinner">
                    <span class="spinner is-active"></span>
                </div>
                <div class="tsa-progress-text">
                    <strong id="tsa-progress-title">Updating...</strong>
                    <p id="tsa-progress-message">Please wait while the plugin is being updated.</p>
                </div>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="github_username">GitHub Username</label>
                    </th>
                    <td>
                        <input type="text" name="github_username" id="github_username" class="regular-text" 
                               value="<?php echo esc_attr( $settings['github_username'] ); ?>" placeholder="alberthwrd">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github_repo">Repository Name</label>
                    </th>
                    <td>
                        <input type="text" name="github_repo" id="github_repo" class="regular-text" 
                               value="<?php echo esc_attr( $settings['github_repo'] ); ?>" placeholder="travelseo-autopublisher">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github_token">Access Token (Optional)</label>
                    </th>
                    <td>
                        <input type="password" name="github_token" id="github_token" class="regular-text" 
                               value="<?php echo esc_attr( $settings['github_token'] ); ?>">
                        <p class="description">Required only for private repositories. <a href="https://github.com/settings/tokens" target="_blank">Generate token</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Version Status</th>
                    <td>
                        <div class="tsa-version-info">
                            <strong>Installed:</strong> v<?php echo esc_html( TSA_VERSION ); ?>
                            <span id="tsa-version-badge" class="tsa-badge"></span>
                        </div>
                        <div class="tsa-version-actions">
                            <button type="button" class="button button-secondary" id="tsa-check-update">
                                <span class="dashicons dashicons-update"></span>
                                Check for Updates
                            </button>
                            <span id="tsa-check-status"></span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced SEO Automation Feature Flags -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-superhero"></span>
                Advanced SEO Automation (Feature Flags)
            </h2>
            <p class="description">Enable or disable advanced features. All features are OFF by default for stability. Enable them one by one after testing.</p>
            
            <table class="form-table tsa-feature-flags">
                <tr>
                    <th scope="row">1. Topical Authority Cluster</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_topical_cluster" value="1" <?php checked( $settings['feature_topical_cluster'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Automatically group related articles into topic clusters for better SEO authority.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">2. Auto Internal Link Insert</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_auto_internal_link" value="1" <?php checked( $settings['feature_auto_internal_link'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Automatically insert internal links with natural anchor texts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">3. Local Facts Table "Info Cepat"</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_local_facts_table" value="1" <?php checked( $settings['feature_local_facts_table'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Add a quick facts table with location, hours, price, etc.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">4. Safety Guard (NEED_REVIEW)</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_safety_guard" value="1" <?php checked( $settings['feature_safety_guard'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Flag low-quality or suspicious content for manual review.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">5. Schema JSON-LD Generator</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_schema_jsonld" value="1" <?php checked( $settings['feature_schema_jsonld'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Auto-generate structured data (TouristAttraction, Restaurant, FAQ).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">6. PAA Harvester → FAQ</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_paa_harvester" value="1" <?php checked( $settings['feature_paa_harvester'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Scrape "People Also Ask" questions and add to FAQ section.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">7. Cannibalization Checker</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_cannibalization_checker" value="1" <?php checked( $settings['feature_cannibalization_checker'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Detect keyword cannibalization with existing posts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">8. Freshness Updater Scheduler</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_freshness_updater" value="1" <?php checked( $settings['feature_freshness_updater'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Schedule automatic content refresh (30/60/90 days).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">9. Template Variations</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_template_variations" value="1" <?php checked( $settings['feature_template_variations'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Use multiple article templates to avoid repetitive structure.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">10. Editorial Workflow Mode</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_editorial_workflow" value="1" <?php checked( $settings['feature_editorial_workflow'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Add review/approve workflow before publishing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">11. Image Pipeline Pro</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_image_pipeline_pro" value="1" <?php checked( $settings['feature_image_pipeline_pro'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Enhanced image handling with featured image, alt text, and captions.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">12. Image Optimization</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_image_optimization" value="1" <?php checked( $settings['feature_image_optimization'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Auto-convert images to WebP and compress for performance.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">13. Map Embed + Directions</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_map_embed" value="1" <?php checked( $settings['feature_map_embed'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Embed Google Maps with directions query.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">14. Nearby Places Generator</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_nearby_places" value="1" <?php checked( $settings['feature_nearby_places'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Auto-generate nearby places section with cluster linking.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">15. Intent-based CTA Placement</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_intent_cta" value="1" <?php checked( $settings['feature_intent_cta'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Smart CTA placement based on user intent.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">16. Robots Cache + Rate Limit</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_robots_cache" value="1" <?php checked( $settings['feature_robots_cache'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Cache robots.txt and enforce per-domain rate limits.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">17. Job Priorities + Concurrency</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_job_priorities" value="1" <?php checked( $settings['feature_job_priorities'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Set job priorities and control concurrent processing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">18. Crash-safe Resume</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_crash_safe" value="1" <?php checked( $settings['feature_crash_safe'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Resume interrupted jobs with idempotency keys.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">19. Quality Gate Score</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_quality_gate" value="1" <?php checked( $settings['feature_quality_gate'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Set minimum quality score threshold for auto-publish.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">20. Deep SEO Plugin Integration</th>
                    <td>
                        <label class="tsa-toggle">
                            <input type="checkbox" name="feature_deep_seo_integration" value="1" <?php checked( $settings['feature_deep_seo_integration'] ); ?>>
                            <span class="tsa-toggle-slider"></span>
                        </label>
                        <p class="description">Full Yoast/RankMath integration with OG tags and advanced settings.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Debug Settings -->
        <div class="tsa-settings-section">
            <h2>
                <span class="dashicons dashicons-info"></span>
                Debug Settings
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Debug Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'] ); ?>>
                            Enable detailed logging
                        </label>
                        <p class="description">Logs detailed information for troubleshooting.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="tsa_save_settings" class="button button-primary button-hero">
                <span class="dashicons dashicons-yes"></span>
                Save Settings
            </button>
        </p>
    </form>
</div>
