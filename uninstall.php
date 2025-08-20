<?php
/**
 * Uninstall Vector Bridge MVDB Indexer
 * 
 * This file is called when the plugin is uninstalled via WordPress admin.
 * It handles cleanup of all plugin data.
 * 
 * @package VectorBridge\MVDBIndexer
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load the autoloader if available
$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

/**
 * Clean up plugin data on uninstall
 */
function vector_bridge_uninstall_cleanup() {
    // Cancel all scheduled actions
    if (function_exists('as_get_scheduled_actions') && function_exists('as_unschedule_action')) {
        $actions = as_get_scheduled_actions(array(
            'group' => 'vector-bridge-mvdb-indexer',
            'per_page' => -1, // Get all actions
        ));
        
        foreach ($actions as $action_id => $action) {
            as_unschedule_action($action['hook'], $action['args'], 'vector-bridge-mvdb-indexer');
        }
    }
    
    // Remove plugin options
    $plugin_options = array(
        'vector_bridge_mvdb_endpoint',
        'vector_bridge_mvdb_token',
        'vector_bridge_default_collection',
        'vector_bridge_tenant',
        'vector_bridge_chunk_size',
        'vector_bridge_overlap_percentage',
        'vector_bridge_batch_size',
        'vector_bridge_qps',
    );
    
    foreach ($plugin_options as $option_name) {
        delete_option($option_name);
    }
    
    // Clean up any transients
    delete_transient('vector_bridge_connection_status');
    delete_transient('vector_bridge_last_validation');
    
    // Remove any custom database tables if they were created
    // (None in this plugin, but this is where you'd clean them up)
    
    // Clear any cached data
    wp_cache_flush();
}

// Run the cleanup
vector_bridge_uninstall_cleanup();
