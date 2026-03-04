<?php

namespace VectorBridge\MVDBIndexer\Admin;

/**
 * Asset Manager
 *
 * Handles admin CSS and JavaScript enqueuing.
 * Extracted from Plugin.php::enqueueAdminAssets().
 */
class AssetManager {

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueue(string $hook_suffix): void {
        // Only load on our admin pages
        if (strpos($hook_suffix, 'vector-bridge') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'vector-bridge-admin',
            VECTOR_BRIDGE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            VECTOR_BRIDGE_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'vector-bridge-admin',
            VECTOR_BRIDGE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            VECTOR_BRIDGE_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('vector-bridge-admin', 'vectorBridge', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('vector_bridge_nonce'),
            'strings' => [
                'processing'    => __('Processing...', 'vector-bridge-mvdb-indexer'),
                'error'         => __('An error occurred. Please try again.', 'vector-bridge-mvdb-indexer'),
                'success'       => __('Operation completed successfully.', 'vector-bridge-mvdb-indexer'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'vector-bridge-mvdb-indexer'),
            ],
        ]);
    }
}
