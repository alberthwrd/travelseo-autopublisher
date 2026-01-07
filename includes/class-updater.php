<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates for the plugin directly from GitHub releases.
 * This class hooks into WordPress's plugin update system to check for
 * new versions on GitHub and provide seamless updates.
 *
 * @link       https://yourwebsite.com
 * @since      1.1.0
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
     * Cache expiration in seconds (6 hours)
     *
     * @var int
     */
    private $cache_expiration = 21600;

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
        $this->github_username = isset( $settings['github_username'] ) ? $settings['github_username'] : 'your-github-username';
        $this->github_repo = isset( $settings['github_repo'] ) ? $settings['github_repo'] : 'travelseo-autopublisher';
        $this->access_token = isset( $settings['github_token'] ) ? $settings['github_token'] : '';
        
        // Initialize hooks
        add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        
        // Add action to clear cache when checking for updates manually
        add_action( 'wp_ajax_tsa_force_update_check', array( $this, 'force_update_check' ) );
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
     * @return object|false
     */
    private function get_repository_info() {
        // Check cache first
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) {
            $this->github_response = $cached;
            return $cached;
        }
        
        // Build API URL
        $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        
        // Prepare request args
        $args = array(
            'timeout' => 10,
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
            'download_link' => $release->zipball_url,
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
        
        // Reactivate the plugin
        if ( is_plugin_active( $this->basename ) ) {
            activate_plugin( $this->basename );
        }
        
        return $result;
    }

    /**
     * Force update check (clear cache)
     */
    public function force_update_check() {
        check_ajax_referer( 'tsa_force_update_check', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        // Clear cache
        delete_transient( $this->cache_key );
        
        // Clear WordPress update cache
        delete_site_transient( 'update_plugins' );
        
        // Trigger update check
        wp_update_plugins();
        
        wp_send_json_success( 'Update check completed' );
    }

    /**
     * Get current version info
     *
     * @return array
     */
    public function get_version_info() {
        $release = $this->get_repository_info();
        
        $info = array(
            'current_version' => $this->plugin['Version'] ?? TSA_VERSION,
            'latest_version' => null,
            'update_available' => false,
            'release_date' => null,
            'release_notes' => null,
            'download_url' => null,
        );
        
        if ( $release ) {
            $github_version = ltrim( $release->tag_name, 'v' );
            $info['latest_version'] = $github_version;
            $info['update_available'] = version_compare( $github_version, $info['current_version'], '>' );
            $info['release_date'] = $release->published_at;
            $info['release_notes'] = $release->body;
            $info['download_url'] = $release->zipball_url;
        }
        
        return $info;
    }
}
