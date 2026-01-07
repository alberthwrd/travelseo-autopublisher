<?php
/**
 * Plugin Name:       TravelSEO Autopublisher
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Automate SEO content creation for travel blogs with a multi-agent pipeline, from research to publishing.
 * Version:           1.2.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       travelseo-autopublisher
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
define( 'TSA_VERSION', '1.2.0' );
define( 'TSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks, this
 * kicks off the plugin from this point in the file.
 *
 * @since    1.0.0
 */
function run_travelseo_autopublisher() {

	$plugin = new \TravelSEO_Autopublisher\Plugin();
	$plugin->run();

}
run_travelseo_autopublisher();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-db.php
 */
register_activation_hook( __FILE__, array( 'TravelSEO_Autopublisher\DB', 'activate' ) );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-db.php
 */
register_deactivation_hook( __FILE__, array( 'TravelSEO_Autopublisher\DB', 'deactivate' ) );
