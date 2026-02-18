<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 */

namespace TravelSEO_Autopublisher;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes
 * @author     Your Name
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, TSA_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, TSA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, false );
        
        // Localize script for AJAX
        wp_localize_script( $this->plugin_name, 'tsaAdmin', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tsa_admin_nonce' ),
            'updateNonce' => wp_create_nonce( 'tsa_update_nonce' ),
        ) );

	}
    
    /**
     * Add admin menu
     */
    public function add_admin_menu(){
        add_menu_page(
            'TravelSEO Autopublisher',
            'TravelSEO',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page'),
            'dashicons-airplane',
            6
        );

        add_submenu_page(
            $this->plugin_name,
            'Dashboard',
            'Dashboard',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'New Campaign',
            'New Campaign',
            'manage_options',
            $this->plugin_name . '-new-campaign',
            array($this, 'display_new_campaign_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Jobs',
            'Jobs',
            'manage_options',
            $this->plugin_name . '-jobs',
            array($this, 'display_jobs_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
        
        // Topical Clusters (conditional based on feature flag)
        $settings = get_option( 'tsa_settings', array() );
        if ( ! empty( $settings['feature_topical_cluster'] ) ) {
            add_submenu_page(
                $this->plugin_name,
                'Topical Clusters',
                'Clusters',
                'manage_options',
                $this->plugin_name . '-clusters',
                array($this, 'display_clusters_page')
            );
        }
        
        // Hidden pages (no menu item)
        add_submenu_page(
            null, // No parent - hidden
            'Job Detail',
            'Job Detail',
            'manage_options',
            $this->plugin_name . '-job-detail',
            array($this, 'display_job_detail_page')
        );
        
        add_submenu_page(
            null, // No parent - hidden
            'Cluster Detail',
            'Cluster Detail',
            'manage_options',
            $this->plugin_name . '-cluster-detail',
            array($this, 'display_cluster_detail_page')
        );
    }

    public function display_dashboard_page(){
        require_once TSA_PLUGIN_DIR . 'admin/views/view-dashboard.php';
    }

    public function display_new_campaign_page(){
        require_once TSA_PLUGIN_DIR . 'admin/views/view-campaign-new.php';
    }

    public function display_jobs_page(){
        require_once TSA_PLUGIN_DIR . 'admin/views/view-jobs-list.php';
    }

    public function display_settings_page(){
        require_once TSA_PLUGIN_DIR . 'admin/views/view-settings.php';
    }
    
    public function display_job_detail_page(){
        require_once TSA_PLUGIN_DIR . 'admin/views/view-job-detail.php';
    }
    
    public function display_clusters_page(){
        require_once TSA_PLUGIN_DIR . 'includes/modules/class-cluster.php';
        require_once TSA_PLUGIN_DIR . 'admin/views/view-cluster-list.php';
    }
    
    public function display_cluster_detail_page(){
        require_once TSA_PLUGIN_DIR . 'includes/modules/class-cluster.php';
        require_once TSA_PLUGIN_DIR . 'admin/views/view-cluster-detail.php';
    }

}
