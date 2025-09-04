<?php

namespace VectorBridge\MVDBIndexer\Core;

use VectorBridge\MVDBIndexer\Admin\AdminMenu;
use VectorBridge\MVDBIndexer\Admin\Settings;
use VectorBridge\MVDBIndexer\Services\MVDBService;
use VectorBridge\MVDBIndexer\Services\ChunkingService;
use VectorBridge\MVDBIndexer\Services\ExtractionService;

/**
 * Main Plugin Class
 * 
 * Singleton pattern to ensure only one instance of the plugin runs.
 * Handles initialization, hooks, and service registration.
 */
class Plugin {
    
    /**
     * Plugin instance
     * 
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;
    
    /**
     * Services container
     * 
     * @var array
     */
    private array $services = [];
    
    /**
     * Plugin initialization status
     * 
     * @var bool
     */
    private bool $initialized = false;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Private constructor for singleton
    }
    
    /**
     * Get plugin instance
     * 
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }
        
        return self::$instance;
    }
    
    /**
     * Initialize the plugin
     * 
     * @return void
     */
    private function init(): void {
        if ($this->initialized) {
            return;
        }
        
        // Register services
        $this->registerServices();
        
        // Setup hooks
        $this->setupHooks();
        
        // Initialize admin interface if in admin
        if (is_admin()) {
            $this->initAdmin();
        }
        
        $this->initialized = true;
    }
    
    /**
     * Register plugin services
     * 
     * @return void
     */
    private function registerServices(): void {
        // Register core services
        $this->services['mvdb'] = new MVDBService();
        $this->services['chunking'] = new ChunkingService();
        $this->services['extraction'] = new ExtractionService();
        $this->services['settings'] = new Settings();
    }
    
    /**
     * Setup WordPress hooks
     * 
     * @return void
     */
    private function setupHooks(): void {
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_vector_bridge_validate_connection', [$this, 'handleValidateConnection']);
        add_action('wp_ajax_vector_bridge_dry_run', [$this, 'handleDryRun']);
        add_action('wp_ajax_vector_bridge_process_url', [$this, 'handleProcessUrl']);
        add_action('wp_ajax_vector_bridge_process_file', [$this, 'handleProcessFile']);
        add_action('wp_ajax_vector_bridge_process_video', [$this, 'handleProcessVideo']);
        add_action('wp_ajax_vector_bridge_upload_file', [$this, 'handleUploadFile']);
        add_action('wp_ajax_vector_bridge_get_jobs', [$this, 'handleGetJobs']);
        
        // Content Browser AJAX handlers
        add_action('wp_ajax_vector_bridge_get_collections', [$this, 'handleGetCollections']);
        add_action('wp_ajax_vector_bridge_get_collection_content', [$this, 'handleGetCollectionContent']);
        add_action('wp_ajax_vector_bridge_test_query', [$this, 'handleTestQuery']);
        add_action('wp_ajax_vector_bridge_export_collection', [$this, 'handleExportCollection']);
        add_action('wp_ajax_vector_bridge_delete_collection', [$this, 'handleDeleteCollection']);
        
        // Action Scheduler hooks
        add_action('vector_bridge_process_content', [$this, 'processContent'], 10, 4);
        add_action('vector_bridge_index_chunks', [$this, 'indexChunks'], 10, 2);
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . VECTOR_BRIDGE_PLUGIN_BASENAME, [$this, 'addSettingsLink']);
    }
    
    /**
     * Initialize admin interface
     * 
     * @return void
     */
    private function initAdmin(): void {
        // Only initialize admin for users with proper capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $this->services['admin_menu'] = new AdminMenu();
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueueAdminAssets(string $hook_suffix): void {
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
            'nonce' => wp_create_nonce('vector_bridge_nonce'),
            'strings' => [
                'processing' => __('Processing...', 'vector-bridge-mvdb-indexer'),
                'error' => __('An error occurred. Please try again.', 'vector-bridge-mvdb-indexer'),
                'success' => __('Operation completed successfully.', 'vector-bridge-mvdb-indexer'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'vector-bridge-mvdb-indexer'),
            ]
        ]);
    }
    
    /**
     * Handle AJAX request to validate MVDB connection
     * 
     * @return void
     */
    public function handleValidateConnection(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $result = $mvdb_service->validateConnection();
            
            wp_send_json_success([
                'message' => __('Connection successful!', 'vector-bridge-mvdb-indexer'),
                'details' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Connection failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request for dry run
     * 
     * @return void
     */
    public function handleDryRun(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $extraction_service = $this->getService('extraction');
            $chunking_service = $this->getService('chunking');
            
            // Load sample fixtures
            $fixtures = $this->loadSampleFixtures();
            $results = [];
            
            foreach ($fixtures as $fixture) {
                $content = $extraction_service->extractFromText($fixture['content']);
                $chunks = $chunking_service->chunkContent($content);
                
                $results[] = [
                    'title' => $fixture['title'],
                    'type' => $fixture['type'],
                    'original_length' => strlen($content),
                    'chunk_count' => count($chunks),
                    'chunks' => array_slice($chunks, 0, 3) // Show first 3 chunks as preview
                ];
            }
            
            wp_send_json_success([
                'message' => __('Dry run completed successfully!', 'vector-bridge-mvdb-indexer'),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Dry run failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to process URL
     * 
     * @return void
     */
    public function handleProcessUrl(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        $collection = sanitize_text_field($_POST['collection'] ?? '');
        
        if (empty($url) || empty($collection)) {
            wp_send_json_error([
                'message' => __('URL and collection are required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            // Schedule background job using WordPress cron
            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$url, $collection, 'url']
            );
            
            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }
            
            wp_send_json_success([
                'message' => __('URL processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id' => time() // Use timestamp as job ID for WordPress cron
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to schedule job: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to process file (File tab)
     * 
     * @return void
     */
    public function handleProcessFile(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error([
                'message' => __('No file uploaded.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        $collection = sanitize_text_field($_POST['collection'] ?? 'default');
        $url_source = sanitize_url($_POST['url_source'] ?? '');
        
        try {
            // Handle file upload
            $uploaded_file = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            
            if (isset($uploaded_file['error'])) {
                throw new \Exception($uploaded_file['error']);
            }
            
            // Prepare metadata for content type processing
            $metadata = [
                'url_source' => $url_source,
                'original_filename' => $_FILES['file']['name'],
                'file_type' => pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)
            ];
            
            // Schedule background job using WordPress cron
            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_file['file'], $collection, 'document', $metadata]
            );
            
            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }
            
            wp_send_json_success([
                'message' => __('File processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id' => time(),
                'filename' => basename($uploaded_file['file'])
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('File processing failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to process video (Video tab)
     * 
     * @return void
     */
    public function handleProcessVideo(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $video_url = sanitize_url($_POST['video_url'] ?? '');
        $collection = sanitize_text_field($_POST['collection'] ?? 'default');
        $video_title = sanitize_text_field($_POST['video_title'] ?? '');
        $speaker = sanitize_text_field($_POST['speaker'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($video_url)) {
            wp_send_json_error([
                'message' => __('Video URL is required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        if (empty($_FILES['vtt_file'])) {
            wp_send_json_error([
                'message' => __('VTT transcript file is required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            // Handle VTT file upload
            $uploaded_vtt = wp_handle_upload($_FILES['vtt_file'], ['test_form' => false]);
            
            if (isset($uploaded_vtt['error'])) {
                throw new \Exception($uploaded_vtt['error']);
            }
            
            // Prepare metadata for video processing
            $metadata = [
                'video_url' => $video_url,
                'video_title' => $video_title,
                'speaker' => $speaker,
                'description' => $description,
                'vtt_file' => $uploaded_vtt['file'],
                'original_vtt_filename' => $_FILES['vtt_file']['name']
            ];
            
            // Schedule background job using WordPress cron
            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_vtt['file'], $collection, 'video', $metadata]
            );
            
            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }
            
            wp_send_json_success([
                'message' => __('Video processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id' => time(),
                'video_url' => $video_url,
                'vtt_filename' => basename($uploaded_vtt['file'])
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Video processing failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to upload and process file
     * 
     * @return void
     */
    public function handleUploadFile(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error([
                'message' => __('No file uploaded.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            wp_send_json_error([
                'message' => __('Collection is required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            // Handle file upload
            $uploaded_file = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            
            if (isset($uploaded_file['error'])) {
                throw new \Exception($uploaded_file['error']);
            }
            
            // Schedule background job using WordPress cron
            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_file['file'], $collection, 'file']
            );
            
            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }
            
            wp_send_json_success([
                'message' => __('File upload and processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id' => time(), // Use timestamp as job ID for WordPress cron
                'filename' => basename($uploaded_file['file'])
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('File upload failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to get job status
     * 
     * @return void
     */
    public function handleGetJobs(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $formatted_jobs = [];
            
            // Get all WordPress cron events
            $cron_array = _get_cron_array();
            
            if ($cron_array) {
                foreach ($cron_array as $timestamp => $cron) {
                    foreach ($cron as $hook => $events) {
                        // Only show our plugin's events
                        if (in_array($hook, ['vector_bridge_process_content', 'vector_bridge_index_chunks'])) {
                            foreach ($events as $key => $event) {
                                $formatted_jobs[] = [
                                    'id' => 'cron_' . $hook . '_' . $timestamp . '_' . $key,
                                    'hook' => $hook,
                                    'status' => $timestamp <= time() ? 'running' : 'scheduled',
                                    'scheduled' => date('Y-m-d H:i:s', $timestamp),
                                    'args' => $event['args'] ?? []
                                ];
                            }
                        }
                    }
                }
            }
            
            // Get recent job history from options (last 10 jobs)
            $job_history = get_option('vector_bridge_job_history', []);
            foreach ($job_history as $job) {
                $formatted_jobs[] = $job;
            }
            
            // Sort by scheduled time (newest first)
            usort($formatted_jobs, function($a, $b) {
                return strtotime($b['scheduled']) - strtotime($a['scheduled']);
            });
            
            wp_send_json_success([
                'jobs' => array_slice($formatted_jobs, 0, 20) // Show last 20 jobs
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to retrieve jobs: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process content (WordPress cron callback)
     * 
     * @param string $source Source URL or file path
     * @param string $collection Collection name
     * @param string $type Type: 'url', 'file', 'document', or 'video'
     * @param array $metadata Optional metadata for content processing
     * @return void
     */
    public function processContent(string $source, string $collection, string $type, array $metadata = []): void {
        $job_id = 'process_' . time() . '_' . substr(md5($source), 0, 8);
        
        try {
            // Log job start
            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'running', [
                'source' => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection' => $collection,
                'type' => $type,
                'metadata' => $metadata
            ]);
            
            $extraction_service = $this->getService('extraction');
            
            // Extract content based on type
            switch ($type) {
                case 'url':
                    $content = $extraction_service->extractFromUrl($source);
                    $content_type = 'webpage';
                    break;
                    
                case 'document':
                case 'file':
                    $content = $extraction_service->extractFromFile($source);
                    $content_type = 'document';
                    break;
                    
                case 'video':
                    // For video, the source is the VTT file path
                    $content = $extraction_service->extractFromVtt($source);
                    $content_type = 'video';
                    break;
                    
                default:
                    throw new \Exception("Unsupported content type: {$type}");
            }
            
            // Use ContentTypeFactory to create appropriate builder
            $factory = new \VectorBridge\MVDBIndexer\Services\ContentTypeFactory();
            $builder = $factory->createDataBuilder($content_type);
            
            // Process content into chunks with appropriate data structure
            $processed_chunks = [];
            foreach ($content as $chunk) {
                $document_data = $builder->buildDocumentData($chunk, $collection, $metadata);
                $processed_chunks[] = $document_data;
            }
            
            // Log processing completion
            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'completed', [
                'source' => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection' => $collection,
                'type' => $type,
                'content_type' => $content_type,
                'chunks_created' => count($processed_chunks)
            ]);
            
            // Schedule indexing job using WordPress cron
            wp_schedule_single_event(
                time(),
                'vector_bridge_index_chunks',
                [$processed_chunks, $collection]
            );
            
        } catch (\Exception $e) {
            // Log job failure
            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'failed', [
                'source' => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection' => $collection,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            error_log('Vector Bridge content processing error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Index chunks into MVDB (WordPress cron callback)
     * 
     * @param array $chunks Content chunks
     * @param string $collection Collection name
     * @return void
     */
    public function indexChunks(array $chunks, string $collection): void {
        $job_id = 'index_' . time() . '_' . substr(md5($collection), 0, 8);
        
        try {
            // Log job start
            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'running', [
                'collection' => $collection,
                'chunk_count' => count($chunks)
            ]);
            
            $mvdb_service = $this->getService('mvdb');
            $mvdb_service->indexChunks($chunks, $collection);
            
            // Log job completion
            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'completed', [
                'collection' => $collection,
                'chunk_count' => count($chunks),
                'indexed_successfully' => true
            ]);
            
        } catch (\Exception $e) {
            // Log job failure
            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'failed', [
                'collection' => $collection,
                'chunk_count' => count($chunks),
                'error' => $e->getMessage()
            ]);
            
            error_log('Vector Bridge indexing error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log job status to WordPress options
     * 
     * @param string $job_id Job identifier
     * @param string $hook Hook name
     * @param string $status Job status
     * @param array $args Job arguments/details
     * @return void
     */
    private function logJobStatus(string $job_id, string $hook, string $status, array $args = []): void {
        $job_history = get_option('vector_bridge_job_history', []);
        
        // Add or update job entry
        $job_entry = [
            'id' => $job_id,
            'hook' => $hook,
            'status' => $status,
            'scheduled' => date('Y-m-d H:i:s'),
            'args' => $args
        ];
        
        // Find existing entry or add new one
        $found = false;
        foreach ($job_history as $index => $job) {
            if ($job['id'] === $job_id) {
                $job_history[$index] = $job_entry;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            array_unshift($job_history, $job_entry);
        }
        
        // Keep only last 50 jobs
        $job_history = array_slice($job_history, 0, 50);
        
        update_option('vector_bridge_job_history', $job_history, false);
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
    
    /**
     * Get a registered service
     * 
     * @param string $service_name Service name
     * @return mixed Service instance
     * @throws \Exception If service not found
     */
    public function getService(string $service_name) {
        if (!isset($this->services[$service_name])) {
            throw new \Exception("Service '{$service_name}' not found");
        }
        
        return $this->services[$service_name];
    }
    
    /**
     * Handle AJAX request to get collections
     * 
     * @return void
     */
    public function handleGetCollections(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $collections = $mvdb_service->getCollections();
            
            wp_send_json_success([
                'collections' => $collections
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to retrieve collections: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to get collection content
     * 
     * @return void
     */
    public function handleGetCollectionContent(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            wp_send_json_error([
                'message' => __('Collection name is required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $content = $mvdb_service->getCollectionContent($collection);
            
            wp_send_json_success([
                'documents' => $content['documents'],
                'stats' => $content['stats']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to retrieve collection content: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to test query
     * 
     * @return void
     */
    public function handleTestQuery(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $collection = sanitize_text_field($_POST['collection'] ?? '');
        $query = sanitize_text_field($_POST['query'] ?? '');
        $limit = intval($_POST['limit'] ?? 5);
        
        if (empty($collection) || empty($query)) {
            wp_send_json_error([
                'message' => __('Collection and query are required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $results = $mvdb_service->searchCollection($collection, $query, $limit);
            
            wp_send_json_success([
                'results' => $results
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Query failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle AJAX request to export collection
     * 
     * @return void
     */
    public function handleExportCollection(): void {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $collection = sanitize_text_field($_GET['collection'] ?? '');
        if (empty($collection)) {
            wp_die('Collection name is required');
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $content = $mvdb_service->getCollectionContent($collection);
            
            // Prepare export data
            $export_data = [
                'collection' => $collection,
                'exported_at' => date('Y-m-d H:i:s'),
                'stats' => $content['stats'],
                'documents' => $content['documents']
            ];
            
            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $collection . '_export_' . date('Y-m-d_H-i-s') . '.json"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo json_encode($export_data, JSON_PRETTY_PRINT);
            exit;
        } catch (\Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request to delete collection
     * 
     * @return void
     */
    public function handleDeleteCollection(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            wp_send_json_error([
                'message' => __('Collection name is required.', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = $this->getService('mvdb');
            $result = $mvdb_service->deleteCollection($collection);
            
            wp_send_json_success([
                'message' => __('Collection deleted successfully.', 'vector-bridge-mvdb-indexer'),
                'result' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to delete collection: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Load sample fixtures for dry run
     * 
     * @return array Sample content fixtures
     */
    private function loadSampleFixtures(): array {
        return [
            [
                'title' => 'Sample HTML Content',
                'type' => 'html',
                'content' => '<h1>Sample Article</h1><p>This is a sample article with multiple paragraphs. It contains various HTML elements that need to be processed and cleaned up before chunking.</p><p>The content extraction service will convert this HTML to clean markdown format, removing unnecessary tags while preserving the structure and meaning of the content.</p><p>This sample demonstrates how the chunking algorithm will split longer content into manageable pieces while maintaining context and readability.</p>'
            ],
            [
                'title' => 'Sample PDF Content',
                'type' => 'pdf',
                'content' => 'Sample PDF Document\n\nThis represents extracted text from a PDF document. PDF extraction can be challenging due to formatting issues, but our extraction service handles common PDF structures effectively.\n\nThe text may contain line breaks and spacing that need to be normalized during the extraction process. The chunking service will then split this content into appropriate segments for vector indexing.\n\nThis sample shows how different document types are processed through the same chunking pipeline, ensuring consistent results regardless of the original format.'
            ]
        ];
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
