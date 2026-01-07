<?php
/**
 * Test: Topical Authority Cluster Builder
 *
 * Manual test checklist for the Topical Cluster feature.
 * Run these tests after enabling the feature flag.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/tests
 */

/**
 * TEST CHECKLIST - Topical Authority Cluster Builder
 * ==================================================
 * 
 * PREREQUISITES:
 * - Plugin is activated
 * - Feature flag 'feature_topical_cluster' is enabled in Settings
 * 
 * TEST 1: Feature Flag Toggle
 * ---------------------------
 * [ ] Navigate to TravelSEO > Settings
 * [ ] Find "Advanced SEO Automation" section
 * [ ] Toggle "Topical Authority Cluster" ON
 * [ ] Save settings
 * [ ] Verify "Clusters" menu appears in TravelSEO menu
 * [ ] Toggle OFF and verify menu disappears
 * 
 * TEST 2: Create New Cluster
 * --------------------------
 * [ ] Navigate to TravelSEO > Clusters
 * [ ] Click "Add New Cluster"
 * [ ] Fill in:
 *     - Cluster Name: "Wisata Bali"
 *     - Pillar Keyword: "wisata bali"
 *     - Description: "Artikel tentang destinasi wisata di Bali"
 * [ ] Click "Create Cluster"
 * [ ] Verify success message appears
 * [ ] Verify cluster appears in list
 * 
 * TEST 3: View Cluster Detail
 * ---------------------------
 * [ ] Click on cluster name in list
 * [ ] Verify cluster settings are displayed correctly
 * [ ] Verify statistics show 0 articles initially
 * [ ] Verify internal linking map is displayed
 * 
 * TEST 4: Update Cluster
 * ----------------------
 * [ ] Change cluster name to "Wisata Pulau Bali"
 * [ ] Change status to "Inactive"
 * [ ] Click "Save Changes"
 * [ ] Verify success message
 * [ ] Verify changes are persisted
 * 
 * TEST 5: Cluster Suggestion (Integration)
 * ----------------------------------------
 * [ ] Create a new campaign with title "Pantai Kuta Bali"
 * [ ] Verify cluster suggestion shows "Wisata Pulau Bali"
 * [ ] Confirm adding to cluster
 * [ ] Verify article appears in cluster detail page
 * 
 * TEST 6: Delete Cluster
 * ----------------------
 * [ ] Navigate to Clusters list
 * [ ] Click "Delete" on a cluster
 * [ ] Confirm deletion
 * [ ] Verify cluster is removed from list
 * [ ] Verify related jobs are unlinked (not deleted)
 * 
 * TEST 7: Search and Filter
 * -------------------------
 * [ ] Create multiple clusters
 * [ ] Test search by name
 * [ ] Test filter by status
 * [ ] Verify pagination works with 20+ clusters
 * 
 * TEST 8: Error Handling
 * ----------------------
 * [ ] Try creating cluster without name (should fail)
 * [ ] Try creating cluster without pillar keyword (should fail)
 * [ ] Try creating duplicate slug (should auto-increment)
 * 
 * TEST 9: Database Integrity
 * --------------------------
 * [ ] Check wp_tsa_clusters table exists
 * [ ] Check wp_tsa_cluster_jobs table exists
 * [ ] Verify foreign key relationships work
 * [ ] Test cascade delete behavior
 * 
 * TEST 10: UI/UX
 * --------------
 * [ ] Verify responsive design on mobile
 * [ ] Verify all buttons have proper hover states
 * [ ] Verify loading states work correctly
 * [ ] Verify error messages are user-friendly
 */

// Simple unit test functions (can be run via WP-CLI or admin)
class TSA_Cluster_Test {

    /**
     * Run all tests
     */
    public static function run_all() {
        $results = array();
        
        $results['test_feature_flag'] = self::test_feature_flag();
        $results['test_create_cluster'] = self::test_create_cluster();
        $results['test_get_cluster'] = self::test_get_cluster();
        $results['test_update_cluster'] = self::test_update_cluster();
        $results['test_suggest_cluster'] = self::test_suggest_cluster();
        $results['test_delete_cluster'] = self::test_delete_cluster();
        
        return $results;
    }

    /**
     * Test feature flag
     */
    public static function test_feature_flag() {
        // Disable feature
        $settings = get_option( 'tsa_settings', array() );
        $settings['feature_topical_cluster'] = false;
        update_option( 'tsa_settings', $settings );
        
        $enabled = \TravelSEO_Autopublisher\Modules\Cluster::is_enabled();
        if ( $enabled ) {
            return array( 'status' => 'FAIL', 'message' => 'Feature should be disabled' );
        }
        
        // Enable feature
        $settings['feature_topical_cluster'] = true;
        update_option( 'tsa_settings', $settings );
        
        $enabled = \TravelSEO_Autopublisher\Modules\Cluster::is_enabled();
        if ( ! $enabled ) {
            return array( 'status' => 'FAIL', 'message' => 'Feature should be enabled' );
        }
        
        return array( 'status' => 'PASS', 'message' => 'Feature flag works correctly' );
    }

    /**
     * Test create cluster
     */
    public static function test_create_cluster() {
        $cluster = new \TravelSEO_Autopublisher\Modules\Cluster();
        
        $cluster_id = $cluster->create( array(
            'name' => 'Test Cluster ' . time(),
            'pillar_keyword' => 'test keyword',
            'description' => 'Test description',
        ) );
        
        if ( ! $cluster_id ) {
            return array( 'status' => 'FAIL', 'message' => 'Failed to create cluster' );
        }
        
        // Store for cleanup
        update_option( 'tsa_test_cluster_id', $cluster_id );
        
        return array( 'status' => 'PASS', 'message' => "Cluster created with ID: {$cluster_id}" );
    }

    /**
     * Test get cluster
     */
    public static function test_get_cluster() {
        $cluster_id = get_option( 'tsa_test_cluster_id' );
        if ( ! $cluster_id ) {
            return array( 'status' => 'SKIP', 'message' => 'No test cluster available' );
        }
        
        $cluster_module = new \TravelSEO_Autopublisher\Modules\Cluster();
        $cluster = $cluster_module->get( $cluster_id );
        
        if ( ! $cluster ) {
            return array( 'status' => 'FAIL', 'message' => 'Failed to retrieve cluster' );
        }
        
        if ( empty( $cluster->name ) || empty( $cluster->pillar_keyword ) ) {
            return array( 'status' => 'FAIL', 'message' => 'Cluster data incomplete' );
        }
        
        return array( 'status' => 'PASS', 'message' => "Retrieved cluster: {$cluster->name}" );
    }

    /**
     * Test update cluster
     */
    public static function test_update_cluster() {
        $cluster_id = get_option( 'tsa_test_cluster_id' );
        if ( ! $cluster_id ) {
            return array( 'status' => 'SKIP', 'message' => 'No test cluster available' );
        }
        
        $cluster_module = new \TravelSEO_Autopublisher\Modules\Cluster();
        
        $new_name = 'Updated Test Cluster ' . time();
        $result = $cluster_module->update( $cluster_id, array(
            'name' => $new_name,
            'status' => 'inactive',
        ) );
        
        if ( ! $result ) {
            return array( 'status' => 'FAIL', 'message' => 'Failed to update cluster' );
        }
        
        $cluster = $cluster_module->get( $cluster_id );
        if ( $cluster->name !== $new_name || $cluster->status !== 'inactive' ) {
            return array( 'status' => 'FAIL', 'message' => 'Update not persisted correctly' );
        }
        
        return array( 'status' => 'PASS', 'message' => 'Cluster updated successfully' );
    }

    /**
     * Test cluster suggestion
     */
    public static function test_suggest_cluster() {
        $cluster_module = new \TravelSEO_Autopublisher\Modules\Cluster();
        
        // Create a cluster for testing
        $cluster_id = $cluster_module->create( array(
            'name' => 'Wisata Bali Test',
            'pillar_keyword' => 'wisata bali',
        ) );
        
        // Test suggestion
        $suggestion = $cluster_module->suggest_cluster( 'Pantai Kuta Bali yang Indah' );
        
        // Cleanup
        $cluster_module->delete( $cluster_id );
        
        if ( ! $suggestion ) {
            return array( 'status' => 'FAIL', 'message' => 'No cluster suggested' );
        }
        
        if ( strpos( strtolower( $suggestion->pillar_keyword ), 'bali' ) === false ) {
            return array( 'status' => 'FAIL', 'message' => 'Wrong cluster suggested' );
        }
        
        return array( 'status' => 'PASS', 'message' => "Suggested cluster: {$suggestion->name}" );
    }

    /**
     * Test delete cluster
     */
    public static function test_delete_cluster() {
        $cluster_id = get_option( 'tsa_test_cluster_id' );
        if ( ! $cluster_id ) {
            return array( 'status' => 'SKIP', 'message' => 'No test cluster available' );
        }
        
        $cluster_module = new \TravelSEO_Autopublisher\Modules\Cluster();
        $result = $cluster_module->delete( $cluster_id );
        
        if ( ! $result ) {
            return array( 'status' => 'FAIL', 'message' => 'Failed to delete cluster' );
        }
        
        $cluster = $cluster_module->get( $cluster_id );
        if ( $cluster ) {
            return array( 'status' => 'FAIL', 'message' => 'Cluster still exists after deletion' );
        }
        
        delete_option( 'tsa_test_cluster_id' );
        
        return array( 'status' => 'PASS', 'message' => 'Cluster deleted successfully' );
    }
}

// Add admin page for running tests
add_action( 'admin_menu', function() {
    add_submenu_page(
        null,
        'TSA Tests',
        'TSA Tests',
        'manage_options',
        'tsa-run-tests',
        function() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Unauthorized' );
            }
            
            echo '<div class="wrap"><h1>TravelSEO Autopublisher - Test Results</h1>';
            
            if ( isset( $_GET['run'] ) && $_GET['run'] === 'cluster' ) {
                require_once TSA_PLUGIN_DIR . 'includes/modules/class-cluster.php';
                $results = TSA_Cluster_Test::run_all();
                
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Test</th><th>Status</th><th>Message</th></tr></thead><tbody>';
                
                foreach ( $results as $test => $result ) {
                    $status_class = $result['status'] === 'PASS' ? 'style="color:green"' : ($result['status'] === 'FAIL' ? 'style="color:red"' : '');
                    echo "<tr><td>{$test}</td><td {$status_class}>{$result['status']}</td><td>{$result['message']}</td></tr>";
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>Select a test suite to run:</p>';
                echo '<a href="?page=tsa-run-tests&run=cluster" class="button button-primary">Run Cluster Tests</a>';
            }
            
            echo '</div>';
        }
    );
} );
