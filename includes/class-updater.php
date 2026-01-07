<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates for the plugin directly from GitHub releases.
 * This class hooks into WordPress's plugin update system to check for
 * new versions on GitHub and provide seamless updates.
 *
 * Features:
 * - Check for updates from GitHub releases
 * - One-click update from Settings page
 * - Update notification with changelog
 * - Support for private repositories
 *
 * @link       https://yourwebsite.com
 * @since      1.1.0
 * @updated    1.2.1
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * GitHub Updater Class
 */
class Updater {

    /**
     * Plugin file path
     *
     * @var string
     */
    private $file;

    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $basename;

    /**
     * GitHub username
     *
     * @var string
     */
    private $github_username;

    /**
     * GitHub repository name
     *
     * @var string
     */
    private $github_repo;

    /**
     * GitHub API response
     *
     * @var object
     */
    private $github_response;

    /**
     * GitHub access token (optional, for private repos)
     *
     * @var string
     */
    private $access_token;

    /**
     * Cache key for GitHub API response
     *
     * @var string
     */
    private $cache_key = 'tsa_github_update_check';

    /**
     * Cache expiration in seconds (1 hour)
     *
     * @var int
     */
    private $cache_expiration = 3600;

    /**
     * Constructor
     *
     * @param string $file Plugin file path
     */
    public function __construct( $file ) {
        $this->file = $file;

        // Get settings
        $settings = get_option( 'tsa_settings', array() );

        // GitHub repository info
        $this->github_username = isset( $settings['github_username'] ) ? $settings['github_username'] : 'alberthwrd';
        $this->github_repo = isset( $settings['github_repo'] ) ? $settings['github_repo'] : 'travelseo-autopublisher';
        $this->access_token = isset( $settings['github_token'] ) ? $settings['github_token'] : '';

        // Initialize hooks
        add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // AJAX handlers
        add_action( 'wp_ajax_tsa_force_update_check', array( $this, 'ajax_force_update_check' ) );
        add_action( 'wp_ajax_tsa_get_update_info', array( $this, 'ajax_get_update_info' ) );
        add_action( 'wp_ajax_tsa_do_plugin_update', array( $this, 'ajax_do_plugin_update' ) );

        // Admin notice for updates
        add_action( 'admin_notices', array( $this, 'admin_update_notice' ) );
    }

    /**
     * Set plugin properties
     */
    public function set_plugin_properties() {
        $this->plugin = get_plugin_data( $this->file );
        $this->basename = plugin_basename( $this->file );
    }

    /**
     * Get repository info from GitHub
     *
     * @param bool $force_refresh Force refresh cache
     * @return object|false
     */
    public function get_repository_info( $force_refresh = false ) {
        // Check cache first (unless force refresh)
        if ( ! $force_refresh ) {
            $cached = get_transient( $this->cache_key );
            if ( $cached !== false ) {
                $this->github_response = $cached;
                return $cached;
            }
        }

        // Build API URL
        $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";

        // Prepare request args
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ),
        );

        // Add authorization header if token is set
        if ( ! empty( $this->access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        // Make request
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( empty( $data ) || isset( $data->message ) ) {
            return false;
        }

        // Cache the response
        set_transient( $this->cache_key, $data, $this->cache_expiration );

        $this->github_response = $data;
        return $data;
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Update transient
     * @return object
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get repository info
        $release = $this->get_repository_info();

        if ( ! $release ) {
            return $transient;
        }

        // Get version from tag name (remove 'v' prefix if present)
        $github_version = ltrim( $release->tag_name, 'v' );
        $current_version = $this->plugin['Version'];

        // Compare versions
        if ( version_compare( $github_version, $current_version, '>' ) ) {
            // Find the zip asset
            $download_url = $this->get_download_url( $release );

            // Build update object
            $update = (object) array(
                'slug' => dirname( $this->basename ),
                'plugin' => $this->basename,
                'new_version' => $github_version,
                'url' => $this->plugin['PluginURI'],
                'package' => $download_url,
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => '',
                'requires_php' => $this->plugin['RequiresPHP'] ?? '7.4',
                'compatibility' => new \stdClass(),
            );

            $transient->response[ $this->basename ] = $update;
        }

        return $transient;
    }

    /**
     * Get download URL from release
     *
     * @param object $release GitHub release object
     * @return string
     */
    private function get_download_url( $release ) {
        $download_url = '';

        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( strpos( $asset->name, '.zip' ) !== false ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Fallback to zipball URL
        if ( empty( $download_url ) ) {
            $download_url = $release->zipball_url;
        }

        // Add authorization to download URL if needed
        if ( ! empty( $this->access_token ) && strpos( $download_url, 'api.github.com' ) !== false ) {
            $download_url = add_query_arg( 'access_token', $this->access_token, $download_url );
        }

        return $download_url;
    }

    /**
     * Plugin information popup
     *
     * @param false|object|array $result Result
     * @param string $action Action
     * @param object $args Arguments
     * @return false|object
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->basename ) ) {
            return $result;
        }

        $release = $this->get_repository_info();

        if ( ! $release ) {
            return $result;
        }

        $github_version = ltrim( $release->tag_name, 'v' );

        // Build plugin info object
        $plugin_info = (object) array(
            'name' => $this->plugin['Name'],
            'slug' => dirname( $this->basename ),
            'version' => $github_version,
            'author' => $this->plugin['Author'],
            'author_profile' => $this->plugin['AuthorURI'],
            'requires' => $this->plugin['RequiresWP'] ?? '5.2',
            'tested' => '',
            'requires_php' => $this->plugin['RequiresPHP'] ?? '7.4',
            'sections' => array(
                'description' => $this->plugin['Description'],
                'changelog' => $this->parse_changelog( $release->body ),
            ),
            'download_link' => $this->get_download_url( $release ),
            'last_updated' => $release->published_at,
            'homepage' => $this->plugin['PluginURI'],
        );

        return $plugin_info;
    }

    /**
     * Parse changelog from release body
     *
     * @param string $body Release body (Markdown)
     * @return string
     */
    private function parse_changelog( $body ) {
        if ( empty( $body ) ) {
            return '<p>No changelog available.</p>';
        }

        // Convert Markdown to HTML (basic conversion)
        $html = $body;

        // Convert headers
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $html );

        // Convert bold
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );

        // Convert italic
        $html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );

        // Convert lists
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html );

        // Convert numbered lists
        $html = preg_replace( '/^\d+\. (.+)$/m', '<li>$1</li>', $html );

        // Convert line breaks
        $html = nl2br( $html );

        return $html;
    }

    /**
     * After plugin install
     *
     * @param bool $response Response
     * @param array $hook_extra Hook extra
     * @param array $result Result
     * @return array
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Check if this is our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $result;
        }

        // Get the proper plugin directory name
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $this->basename );

        // Move the plugin to the correct location
        $wp_filesystem->move( $result['destination'], $plugin_folder );
        $result['destination'] = $plugin_folder;

        // Clear update cache
        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );

        // Reactivate the plugin
        if ( is_plugin_active( $this->basename ) ) {
            activate_plugin( $this->basename );
        }

        return $result;
    }

    /**
     * AJAX: Force update check (clear cache)
     */
    public function ajax_force_update_check() {
        check_ajax_referer( 'tsa_update_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        // Clear cache
        delete_transient( $this->cache_key );

        // Clear WordPress update cache
        delete_site_transient( 'update_plugins' );

        // Get fresh info
        $release = $this->get_repository_info( true );

        if ( ! $release ) {
            wp_send_json_error( array( 'message' => 'Failed to connect to GitHub. Please check your settings.' ) );
        }

        // Trigger update check
        wp_update_plugins();

        // Get version info
        $info = $this->get_version_info();

        wp_send_json_success( array(
            'message' => 'Update check completed',
            'info' => $info,
        ) );
    }

    /**
     * AJAX: Get update info
     */
    public function ajax_get_update_info() {
        check_ajax_referer( 'tsa_update_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $info = $this->get_version_info();

        wp_send_json_success( $info );
    }

    /**
     * AJAX: Do plugin update
     */
    public function ajax_do_plugin_update() {
        check_ajax_referer( 'tsa_update_nonce', 'nonce' );

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to update plugins.' ) );
        }

        // Include required files
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Get release info
        $release = $this->get_repository_info( true );

        if ( ! $release ) {
            wp_send_json_error( array( 'message' => 'Failed to get update information from GitHub.' ) );
        }

        $github_version = ltrim( $release->tag_name, 'v' );
        $current_version = TSA_VERSION;

        // Check if update is needed
        if ( ! version_compare( $github_version, $current_version, '>' ) ) {
            wp_send_json_error( array( 'message' => 'You already have the latest version.' ) );
        }

        // Get download URL
        $download_url = $this->get_download_url( $release );

        if ( empty( $download_url ) ) {
            wp_send_json_error( array( 'message' => 'Download URL not found.' ) );
        }

        // Create custom skin for AJAX
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );

        // Perform the upgrade
        $result = $upgrader->upgrade( $this->basename );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( $result === false ) {
            wp_send_json_error( array( 'message' => 'Update failed. Please try again or update manually.' ) );
        }

        // Clear caches
        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );

        wp_send_json_success( array(
            'message' => 'Plugin updated successfully to version ' . $github_version,
            'new_version' => $github_version,
            'reload' => true,
        ) );
    }

    /**
     * Admin notice for updates
     */
    public function admin_update_notice() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'travelseo-autopublisher' ) === false ) {
            return;
        }

        $info = $this->get_version_info();

        if ( ! $info['update_available'] ) {
            return;
        }

        $update_url = admin_url( 'admin.php?page=travelseo-autopublisher-settings#github-update' );

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>TravelSEO Autopublisher:</strong> A new version (%s) is available! <a href="%s">Update now</a></p></div>',
            esc_html( $info['latest_version'] ),
            esc_url( $update_url )
        );
    }

    /**
     * Get current version info
     *
     * @return array
     */
    public function get_version_info() {
        $release = $this->get_repository_info();

        $current_version = defined( 'TSA_VERSION' ) ? TSA_VERSION : '1.0.0';

        $info = array(
            'current_version' => $current_version,
            'latest_version' => null,
            'update_available' => false,
            'release_date' => null,
            'release_date_formatted' => null,
            'release_notes' => null,
            'release_notes_html' => null,
            'download_url' => null,
            'release_url' => null,
            'github_connected' => false,
        );

        if ( $release ) {
            $github_version = ltrim( $release->tag_name, 'v' );
            $info['latest_version'] = $github_version;
            $info['update_available'] = version_compare( $github_version, $current_version, '>' );
            $info['release_date'] = $release->published_at;
            $info['release_date_formatted'] = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $release->published_at ) );
            $info['release_notes'] = $release->body;
            $info['release_notes_html'] = $this->parse_changelog( $release->body );
            $info['download_url'] = $this->get_download_url( $release );
            $info['release_url'] = $release->html_url;
            $info['github_connected'] = true;
        }

        return $info;
    }

    /**
     * Get GitHub settings status
     *
     * @return array
     */
    public function get_github_status() {
        return array(
            'username' => $this->github_username,
            'repo' => $this->github_repo,
            'has_token' => ! empty( $this->access_token ),
            'repo_url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
        );
    }
}
