<?php

namespace VectorBridge\MVDBIndexer\Core;

/**
 * Hook Manager
 *
 * Single place for ALL WordPress hook registration.
 * No business logic — just wiring controllers/services to hooks.
 * Eliminates duplicate AJAX registrations that existed in Plugin.php and Settings.php.
 */
class HookManager {

    private ServiceProvider $container;

    public function __construct(ServiceProvider $container) {
        $this->container = $container;
    }

    /**
     * Register all WordPress hooks
     *
     * @return void
     */
    public function register(): void {
        // Admin assets
        add_action('admin_enqueue_scripts', [$this->container->get('asset_manager'), 'enqueue']);

        // Settings registration
        add_action('admin_init', [$this->container->get('settings'), 'initSettings']);

        // AJAX — Connection
        add_action(
            'wp_ajax_vector_bridge_validate_connection',
            [$this->container->get('controller.connection'), 'validateConnection']
        );

        // AJAX — Indexing
        add_action(
            'wp_ajax_vector_bridge_dry_run',
            [$this->container->get('controller.indexing'), 'dryRun']
        );
        add_action(
            'wp_ajax_vector_bridge_process_url',
            [$this->container->get('controller.indexing'), 'processUrl']
        );
        add_action(
            'wp_ajax_vector_bridge_process_file',
            [$this->container->get('controller.indexing'), 'processFile']
        );
        add_action(
            'wp_ajax_vector_bridge_process_video',
            [$this->container->get('controller.indexing'), 'processVideo']
        );
        add_action(
            'wp_ajax_vector_bridge_upload_file',
            [$this->container->get('controller.indexing'), 'uploadFile']
        );

        // AJAX — Collections (from Plugin.php)
        add_action(
            'wp_ajax_vector_bridge_get_collections',
            [$this->container->get('controller.collection'), 'getCollections']
        );
        add_action(
            'wp_ajax_vector_bridge_get_collection_content',
            [$this->container->get('controller.collection'), 'getCollectionContent']
        );
        add_action(
            'wp_ajax_vector_bridge_test_query',
            [$this->container->get('controller.collection'), 'testQuery']
        );
        add_action(
            'wp_ajax_vector_bridge_export_collection',
            [$this->container->get('controller.collection'), 'exportCollection']
        );
        add_action(
            'wp_ajax_vector_bridge_delete_collection',
            [$this->container->get('controller.collection'), 'deleteCollection']
        );

        // AJAX — Content Browser (consolidated from Settings.php)
        add_action(
            'wp_ajax_vector_bridge_get_all_documents',
            [$this->container->get('controller.collection'), 'getAllDocuments']
        );
        add_action(
            'wp_ajax_vector_bridge_search_documents',
            [$this->container->get('controller.collection'), 'searchDocuments']
        );
        add_action(
            'wp_ajax_vector_bridge_get_document_details',
            [$this->container->get('controller.collection'), 'getDocumentDetails']
        );
        add_action(
            'wp_ajax_vector_bridge_delete_document',
            [$this->container->get('controller.collection'), 'deleteDocument']
        );
        add_action(
            'wp_ajax_vector_bridge_clear_all_documents',
            [$this->container->get('controller.collection'), 'clearAllDocuments']
        );

        // AJAX — Jobs
        add_action(
            'wp_ajax_vector_bridge_get_jobs',
            [$this->container->get('controller.job'), 'getJobs']
        );

        // Cron callbacks
        add_action(
            'vector_bridge_process_content',
            [$this->container->get('cron'), 'processContent'],
            10,
            4
        );
        add_action(
            'vector_bridge_index_chunks',
            [$this->container->get('cron'), 'indexChunks'],
            10,
            2
        );

        // Plugin action links (settings link on plugins page)
        add_filter(
            'plugin_action_links_' . VECTOR_BRIDGE_PLUGIN_BASENAME,
            [$this, 'addSettingsLink']
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing plugin action links
     * @return array Modified links
     */
    public function addSettingsLink(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=vector-bridge-settings'),
            __('Settings', 'vector-bridge-mvdb-indexer')
        );

        array_unshift($links, $settings_link);
        return $links;
    }
}
