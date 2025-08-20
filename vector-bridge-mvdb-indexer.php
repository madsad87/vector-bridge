<?php
/**
 * Plugin Name: Vector Bridge – MVDB External Indexer
 * Plugin URI: https://github.com/madsad87/vector-bridge-mvdb-indexer
 * Description: Admin-only interface for ingesting external content into WP Engine's Managed Vector Database (MVDB).
 * Version: 1.0.0
 * Author: Madison Sadler
 * Author URI: https://github.com/madsad87
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vector-bridge-mvdb-indexer
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 *
 * @package VectorBridge\MVDBIndexer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VECTOR_BRIDGE_VERSION', '1.0.0');
define('VECTOR_BRIDGE_PLUGIN_FILE', __FILE__);
define('VECTOR_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VECTOR_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VECTOR_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Admin notice for PHP version requirement
 */
function vector_bridge_php_version_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Vector Bridge MVDB Indexer requires PHP 8.1 or higher. You are running PHP ', 'vector-bridge-mvdb-indexer');
    echo esc_html(PHP_VERSION);
    echo '</p></div>';
}

/**
 * Admin notice for WordPress version requirement
 */
function vector_bridge_wp_version_notice() {
    global $wp_version;
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Vector Bridge MVDB Indexer requires WordPress 6.5 or higher. You are running WordPress ', 'vector-bridge-mvdb-indexer');
    echo esc_html($wp_version);
    echo '</p></div>';
}

/**
 * Admin notice for missing Composer dependencies
 */
function vector_bridge_composer_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Vector Bridge MVDB Indexer: Composer dependencies not found. Please run "composer install" in the plugin directory.', 'vector-bridge-mvdb-indexer');
    echo '</p></div>';
}

/**
 * Admin notice for plugin initialization error
 */
function vector_bridge_init_error_notice() {
    $error = get_transient('vector_bridge_init_error');
    if ($error) {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Vector Bridge MVDB Indexer failed to initialize: ', 'vector-bridge-mvdb-indexer');
        echo esc_html($error);
        echo '</p></div>';
        delete_transient('vector_bridge_init_error');
    }
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', 'vector_bridge_php_version_notice');
    return;
}

// Check WordPress version
global $wp_version;
if (version_compare($wp_version, '6.5', '<')) {
    add_action('admin_notices', 'vector_bridge_wp_version_notice');
    return;
}

// Load Composer autoloader
$autoloader = VECTOR_BRIDGE_PLUGIN_DIR . 'vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', 'vector_bridge_composer_notice');
    return;
}

require_once $autoloader;

/**
 * Initialize the plugin
 */
function vector_bridge_init_plugin() {
    try {
        \VectorBridge\MVDBIndexer\Core\Plugin::getInstance();
    } catch (Exception $e) {
        set_transient('vector_bridge_init_error', $e->getMessage(), 300);
        add_action('admin_notices', 'vector_bridge_init_error_notice');
        
        // Log the error
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Vector Bridge MVDB Indexer initialization error: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'vector_bridge_init_plugin');

/**
 * Plugin activation callback
 */
function vector_bridge_activate_plugin() {
    // Check requirements again on activation
    if (version_compare(PHP_VERSION, '8.1', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Vector Bridge MVDB Indexer requires PHP 8.1 or higher.', 'vector-bridge-mvdb-indexer'),
            esc_html__('Plugin Activation Error', 'vector-bridge-mvdb-indexer'),
            array('back_link' => true)
        );
    }
    
    global $wp_version;
    if (version_compare($wp_version, '6.5', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Vector Bridge MVDB Indexer requires WordPress 6.5 or higher.', 'vector-bridge-mvdb-indexer'),
            esc_html__('Plugin Activation Error', 'vector-bridge-mvdb-indexer'),
            array('back_link' => true)
        );
    }
    
    // Check for Composer autoloader
    if (!file_exists(VECTOR_BRIDGE_PLUGIN_DIR . 'vendor/autoload.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Vector Bridge MVDB Indexer: Composer dependencies not found. Please run "composer install" in the plugin directory before activating.', 'vector-bridge-mvdb-indexer'),
            esc_html__('Plugin Activation Error', 'vector-bridge-mvdb-indexer'),
            array('back_link' => true)
        );
    }
    
    
    // Set default options
    $default_options = array(
        'mvdb_endpoint' => '',
        'mvdb_token' => '',
        'default_collection' => 'default',
        'tenant' => '',
        'chunk_size' => 1000,
        'overlap_percentage' => 15,
        'batch_size' => 100,
        'qps' => 2.0,
    );
    
    foreach ($default_options as $option_name => $default_value) {
        $full_option_name = 'vector_bridge_' . $option_name;
        if (get_option($full_option_name) === false) {
            add_option($full_option_name, $default_value, '', 'no');
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation callback
 */
function vector_bridge_deactivate_plugin() {
    // Clear all scheduled WordPress cron events for our plugin
    wp_clear_scheduled_hook('vector_bridge_process_content');
    wp_clear_scheduled_hook('vector_bridge_index_chunks');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Activation hook
register_activation_hook(__FILE__, 'vector_bridge_activate_plugin');

// Deactivation hook
register_deactivation_hook(__FILE__, 'vector_bridge_deactivate_plugin');

// Uninstall hook handled by uninstall.php file
