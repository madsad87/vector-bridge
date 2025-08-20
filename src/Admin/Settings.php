<?php

namespace VectorBridge\MVDBIndexer\Admin;

/**
 * Settings Handler
 * 
 * Manages plugin settings and configuration options.
 */
class Settings {
    
    /**
     * Settings option prefix
     */
    private const OPTION_PREFIX = 'vector_bridge_';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'initSettings']);
        
        // Register AJAX handlers
        add_action('wp_ajax_vector_bridge_validate_connection', [$this, 'handleValidateConnection']);
        add_action('wp_ajax_vector_bridge_dry_run', [$this, 'handleDryRun']);
        add_action('wp_ajax_vector_bridge_process_url', [$this, 'handleProcessUrl']);
        add_action('wp_ajax_vector_bridge_upload_file', [$this, 'handleUploadFile']);
        add_action('wp_ajax_vector_bridge_get_jobs', [$this, 'handleGetJobs']);
        
        // New Content Browser AJAX handlers
        add_action('wp_ajax_vector_bridge_get_all_documents', [$this, 'handleGetAllDocuments']);
        add_action('wp_ajax_vector_bridge_search_documents', [$this, 'handleSearchDocuments']);
        add_action('wp_ajax_vector_bridge_get_document_details', [$this, 'handleGetDocumentDetails']);
        add_action('wp_ajax_vector_bridge_delete_document', [$this, 'handleDeleteDocument']);
        add_action('wp_ajax_vector_bridge_clear_all_documents', [$this, 'handleClearAllDocuments']);
    }
    
    /**
     * Initialize settings
     * 
     * @return void
     */
    public function initSettings(): void {
        // Register settings sections
        add_settings_section(
            'vector_bridge_mvdb_section',
            __('MVDB Connection', 'vector-bridge-mvdb-indexer'),
            [$this, 'renderMvdbSectionDescription'],
            'vector-bridge-settings'
        );
        
        add_settings_section(
            'vector_bridge_processing_section',
            __('Content Processing', 'vector-bridge-mvdb-indexer'),
            [$this, 'renderProcessingSectionDescription'],
            'vector-bridge-settings'
        );
        
        add_settings_section(
            'vector_bridge_performance_section',
            __('Performance Settings', 'vector-bridge-mvdb-indexer'),
            [$this, 'renderPerformanceSectionDescription'],
            'vector-bridge-settings'
        );
        
        // Register MVDB settings
        $this->registerSetting('mvdb_endpoint', [
            'type' => 'string',
            'description' => __('MVDB GraphQL endpoint URL', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => 'sanitize_url',
            'section' => 'vector_bridge_mvdb_section',
            'label' => __('MVDB Endpoint URL', 'vector-bridge-mvdb-indexer'),
            'required' => true
        ]);
        
        $this->registerSetting('mvdb_token', [
            'type' => 'string',
            'description' => __('MVDB authentication token', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => [$this, 'sanitizeToken'],
            'section' => 'vector_bridge_mvdb_section',
            'label' => __('MVDB Token', 'vector-bridge-mvdb-indexer'),
            'required' => true,
            'input_type' => 'password'
        ]);
        
        // Register processing settings
        $this->registerSetting('tenant', [
            'type' => 'string',
            'description' => __('Optional tenant identifier for multi-tenant setups', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => 'sanitize_text_field',
            'section' => 'vector_bridge_processing_section',
            'label' => __('Tenant (Optional)', 'vector-bridge-mvdb-indexer'),
            'default' => ''
        ]);
        
        $this->registerSetting('chunk_size', [
            'type' => 'integer',
            'description' => __('Target size for content chunks in tokens', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => [$this, 'sanitizeChunkSize'],
            'section' => 'vector_bridge_processing_section',
            'label' => __('Chunk Size (tokens)', 'vector-bridge-mvdb-indexer'),
            'default' => 1000,
            'min' => 100,
            'max' => 5000
        ]);
        
        $this->registerSetting('overlap_percentage', [
            'type' => 'integer',
            'description' => __('Percentage of overlap between adjacent chunks', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => [$this, 'sanitizeOverlapPercentage'],
            'section' => 'vector_bridge_processing_section',
            'label' => __('Overlap Percentage', 'vector-bridge-mvdb-indexer'),
            'default' => 15,
            'min' => 0,
            'max' => 50
        ]);
        
        // Register performance settings
        $this->registerSetting('batch_size', [
            'type' => 'integer',
            'description' => __('Number of chunks to process in each batch', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => [$this, 'sanitizeBatchSize'],
            'section' => 'vector_bridge_performance_section',
            'label' => __('Batch Size', 'vector-bridge-mvdb-indexer'),
            'default' => 100,
            'min' => 1,
            'max' => 1000
        ]);
        
        $this->registerSetting('qps', [
            'type' => 'number',
            'description' => __('Maximum queries per second to MVDB', 'vector-bridge-mvdb-indexer'),
            'sanitize_callback' => [$this, 'sanitizeQps'],
            'section' => 'vector_bridge_performance_section',
            'label' => __('QPS (Queries Per Second)', 'vector-bridge-mvdb-indexer'),
            'default' => 2.0,
            'min' => 0.1,
            'max' => 100.0,
            'step' => 0.1
        ]);
    }
    
    /**
     * Register a setting
     * 
     * @param string $name Setting name
     * @param array $args Setting arguments
     * @return void
     */
    private function registerSetting(string $name, array $args): void {
        $option_name = self::OPTION_PREFIX . $name;
        
        register_setting(
            'vector-bridge-settings',
            $option_name,
            [
                'type' => $args['type'],
                'description' => $args['description'],
                'sanitize_callback' => $args['sanitize_callback'],
                'default' => $args['default'] ?? ''
            ]
        );
        
        add_settings_field(
            $option_name,
            $args['label'],
            [$this, 'renderField'],
            'vector-bridge-settings',
            $args['section'],
            array_merge($args, ['name' => $name, 'option_name' => $option_name])
        );
    }
    
    /**
     * Render MVDB section description
     * 
     * @return void
     */
    public function renderMvdbSectionDescription(): void {
        echo '<p>' . esc_html__('Configure your WP Engine Managed Vector Database connection settings.', 'vector-bridge-mvdb-indexer') . '</p>';
    }
    
    /**
     * Render processing section description
     * 
     * @return void
     */
    public function renderProcessingSectionDescription(): void {
        echo '<p>' . esc_html__('Configure how content is processed and chunked before indexing.', 'vector-bridge-mvdb-indexer') . '</p>';
    }
    
    /**
     * Render performance section description
     * 
     * @return void
     */
    public function renderPerformanceSectionDescription(): void {
        echo '<p>' . esc_html__('Configure performance and rate limiting settings.', 'vector-bridge-mvdb-indexer') . '</p>';
    }
    
    /**
     * Render a settings field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function renderField(array $args): void {
        $value = get_option($args['option_name'], $args['default'] ?? '');
        $input_type = $args['input_type'] ?? 'text';
        $required = $args['required'] ?? false;
        
        switch ($args['type']) {
            case 'integer':
            case 'number':
                $this->renderNumberField($args, $value);
                break;
            default:
                $this->renderTextField($args, $value, $input_type, $required);
                break;
        }
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render text field
     * 
     * @param array $args Field arguments
     * @param string $value Current value
     * @param string $input_type Input type
     * @param bool $required Whether field is required
     * @return void
     */
    private function renderTextField(array $args, string $value, string $input_type, bool $required): void {
        $masked_value = $input_type === 'password' && !empty($value) ? $this->maskToken($value) : $value;
        
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" %s />',
            esc_attr($input_type),
            esc_attr($args['option_name']),
            esc_attr($args['option_name']),
            esc_attr($masked_value),
            $required ? 'required' : ''
        );
        
        if ($input_type === 'password' && !empty($value)) {
            echo '<br><small>' . esc_html__('Token is masked for security. Leave blank to keep current value.', 'vector-bridge-mvdb-indexer') . '</small>';
        }
    }
    
    /**
     * Render number field
     * 
     * @param array $args Field arguments
     * @param mixed $value Current value
     * @return void
     */
    private function renderNumberField(array $args, $value): void {
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        $step = $args['step'] ?? ($args['type'] === 'number' ? 'any' : '1');
        
        printf(
            '<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
            esc_attr($args['option_name']),
            esc_attr($args['option_name']),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max),
            esc_attr($step)
        );
        
        if (!empty($min) && !empty($max)) {
            echo '<span class="description"> (' . sprintf(
                esc_html__('Range: %s - %s', 'vector-bridge-mvdb-indexer'),
                $min,
                $max
            ) . ')</span>';
        }
    }
    
    /**
     * Sanitize chunk size
     * 
     * @param mixed $value Input value
     * @return int Sanitized value
     */
    public function sanitizeChunkSize($value): int {
        $value = intval($value);
        return max(100, min(5000, $value));
    }
    
    /**
     * Sanitize overlap percentage
     * 
     * @param mixed $value Input value
     * @return int Sanitized value
     */
    public function sanitizeOverlapPercentage($value): int {
        $value = intval($value);
        return max(0, min(50, $value));
    }
    
    /**
     * Sanitize batch size
     * 
     * @param mixed $value Input value
     * @return int Sanitized value
     */
    public function sanitizeBatchSize($value): int {
        $value = intval($value);
        return max(1, min(1000, $value));
    }
    
    /**
     * Sanitize QPS
     * 
     * @param mixed $value Input value
     * @return float Sanitized value
     */
    public function sanitizeQps($value): float {
        $value = floatval($value);
        return max(0.1, min(100.0, $value));
    }
    
    /**
     * Sanitize token (special handling for masked tokens)
     * 
     * @param mixed $value Input value
     * @return string Sanitized value
     */
    public function sanitizeToken($value): string {
        $value = sanitize_text_field($value);
        
        // If the value contains asterisks, it's masked - don't update
        if (empty($value) || str_contains($value, '*')) {
            // Return the existing value from the database
            return get_option(self::OPTION_PREFIX . 'mvdb_token', '');
        }
        
        // Otherwise, it's a new token value
        return $value;
    }
    
    /**
     * Mask token for display
     * 
     * @param string $token Token to mask
     * @return string Masked token
     */
    private function maskToken(string $token): string {
        if (strlen($token) <= 6) {
            return str_repeat('*', strlen($token));
        }
        
        return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
    }
    
    /**
     * Get setting value
     * 
     * @param string $name Setting name
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public static function get(string $name, $default = '') {
        return get_option(self::OPTION_PREFIX . $name, $default);
    }
    
    /**
     * Update setting value
     * 
     * @param string $name Setting name
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function update(string $name, $value): bool {
        return update_option(self::OPTION_PREFIX . $name, $value, false);
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    public function renderPage(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('vector-bridge-settings');
                do_settings_sections('vector-bridge-settings');
                submit_button();
                ?>
            </form>
            
            <div class="vector-bridge-settings-help">
                <h2><?php esc_html_e('Configuration Help', 'vector-bridge-mvdb-indexer'); ?></h2>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h3 class="hndle"><?php esc_html_e('MVDB Connection', 'vector-bridge-mvdb-indexer'); ?></h3>
                    </div>
                    <div class="inside">
                        <p><?php esc_html_e('To connect to your WP Engine Managed Vector Database:', 'vector-bridge-mvdb-indexer'); ?></p>
                        <ol>
                            <li><?php esc_html_e('Obtain your GraphQL endpoint URL from WP Engine', 'vector-bridge-mvdb-indexer'); ?></li>
                            <li><?php esc_html_e('Generate an authentication token in your MVDB dashboard', 'vector-bridge-mvdb-indexer'); ?></li>
                            <li><?php esc_html_e('Enter both values above and click "Save Changes"', 'vector-bridge-mvdb-indexer'); ?></li>
                            <li><?php esc_html_e('Use the "Validate Connection" button on the main dashboard to test', 'vector-bridge-mvdb-indexer'); ?></li>
                        </ol>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h3 class="hndle"><?php esc_html_e('Chunking Configuration', 'vector-bridge-mvdb-indexer'); ?></h3>
                    </div>
                    <div class="inside">
                        <p><?php esc_html_e('Content chunking settings affect how your documents are split:', 'vector-bridge-mvdb-indexer'); ?></p>
                        <ul>
                            <li><strong><?php esc_html_e('Chunk Size:', 'vector-bridge-mvdb-indexer'); ?></strong> <?php esc_html_e('Larger chunks preserve more context but may exceed model limits', 'vector-bridge-mvdb-indexer'); ?></li>
                            <li><strong><?php esc_html_e('Overlap:', 'vector-bridge-mvdb-indexer'); ?></strong> <?php esc_html_e('Higher overlap improves context continuity but increases storage', 'vector-bridge-mvdb-indexer'); ?></li>
                        </ul>
                        <p><?php esc_html_e('Use the "Dry Run" feature to test your chunking settings before processing real content.', 'vector-bridge-mvdb-indexer'); ?></p>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h3 class="hndle"><?php esc_html_e('Performance Tuning', 'vector-bridge-mvdb-indexer'); ?></h3>
                    </div>
                    <div class="inside">
                        <p><?php esc_html_e('Adjust these settings based on your MVDB plan and usage:', 'vector-bridge-mvdb-indexer'); ?></p>
                        <ul>
                            <li><strong><?php esc_html_e('Batch Size:', 'vector-bridge-mvdb-indexer'); ?></strong> <?php esc_html_e('Larger batches are more efficient but use more memory', 'vector-bridge-mvdb-indexer'); ?></li>
                            <li><strong><?php esc_html_e('QPS:', 'vector-bridge-mvdb-indexer'); ?></strong> <?php esc_html_e('Lower values reduce load but slow processing', 'vector-bridge-mvdb-indexer'); ?></li>
                        </ul>
                        <p><?php esc_html_e('Monitor your MVDB usage and adjust these values if you encounter rate limiting.', 'vector-bridge-mvdb-indexer'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle validate connection AJAX request
     * 
     * @return void
     */
    public function handleValidateConnection(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $result = $mvdb_service->validateConnection();
            
            wp_send_json_success([
                'message' => __('Connection successful!', 'vector-bridge-mvdb-indexer'),
                'details' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle dry run AJAX request
     * 
     * @return void
     */
    public function handleDryRun(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        try {
            // Create sample fixtures
            $fixtures = [
                [
                    'title' => 'Sample HTML Content',
                    'type' => 'html',
                    'content' => '<h1>Sample Document</h1><p>This is a sample HTML document with some content to demonstrate chunking. It contains multiple paragraphs and sections to show how the content will be split into manageable chunks for vector indexing.</p><h2>Section 1</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>'
                ],
                [
                    'title' => 'Sample PDF Content',
                    'type' => 'pdf',
                    'content' => 'This is sample PDF content that would be extracted from a PDF file. It demonstrates how PDF text extraction works and how the content would be processed through the chunking system. The content includes various formatting and structure that would be preserved during extraction.'
                ]
            ];
            
            $chunking_service = new \VectorBridge\MVDBIndexer\Services\ChunkingService();
            $results = [];
            
            foreach ($fixtures as $fixture) {
                $chunks = $chunking_service->chunkContent($fixture['content'], $fixture['title']);
                
                $results[] = [
                    'title' => $fixture['title'],
                    'type' => $fixture['type'],
                    'original_length' => strlen($fixture['content']),
                    'chunk_count' => count($chunks),
                    'chunks' => array_slice($chunks, 0, 3) // Show first 3 chunks
                ];
            }
            
            wp_send_json_success([
                'results' => $results
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle get all documents AJAX request
     * 
     * @return void
     */
    public function handleGetAllDocuments(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $result = $mvdb_service->getAllDocuments();
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle search documents AJAX request
     * 
     * @return void
     */
    public function handleSearchDocuments(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $limit = intval($_POST['limit'] ?? 5);
        
        if (empty($query)) {
            wp_send_json_error([
                'message' => __('Search query is required', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $results = $mvdb_service->searchDocuments($query, $post_type, $limit);
            
            wp_send_json_success([
                'results' => $results
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle get document details AJAX request
     * 
     * @return void
     */
    public function handleGetDocumentDetails(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        $document_id = sanitize_text_field($_POST['document_id'] ?? '');
        
        if (empty($document_id)) {
            wp_send_json_error([
                'message' => __('Document ID is required', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $documents = $mvdb_service->getAllDocuments();
            
            // Find the specific document
            $document = null;
            foreach ($documents['documents'] as $doc) {
                if ($doc['id'] === $document_id) {
                    $document = $doc;
                    break;
                }
            }
            
            if (!$document) {
                wp_send_json_error([
                    'message' => __('Document not found', 'vector-bridge-mvdb-indexer')
                ]);
            }
            
            wp_send_json_success([
                'document' => $document
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle delete document AJAX request
     * 
     * @return void
     */
    public function handleDeleteDocument(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        $document_id = sanitize_text_field($_POST['document_id'] ?? '');
        
        if (empty($document_id)) {
            wp_send_json_error([
                'message' => __('Document ID is required', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            
            // Use the private deleteDocument method via reflection (since it's private)
            $reflection = new \ReflectionClass($mvdb_service);
            $method = $reflection->getMethod('deleteDocument');
            $method->setAccessible(true);
            $result = $method->invoke($mvdb_service, $document_id);
            
            wp_send_json_success([
                'message' => __('Document deleted successfully', 'vector-bridge-mvdb-indexer')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle clear all documents AJAX request
     * 
     * @return void
     */
    public function handleClearAllDocuments(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        try {
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            
            // Get all document types and delete them
            $collections = $mvdb_service->getCollections();
            $deleted_total = 0;
            
            foreach ($collections as $collection) {
                $result = $mvdb_service->deleteDocumentsByType($collection['name']);
                $deleted_total += $result['deleted_documents'];
            }
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully deleted %d documents', 'vector-bridge-mvdb-indexer'),
                    $deleted_total
                )
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle process URL AJAX request
     * 
     * @return void
     */
    public function handleProcessUrl(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error([
                'message' => __('URL is required', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        try {
            // Extract content from URL
            $extraction_service = new \VectorBridge\MVDBIndexer\Services\ExtractionService();
            $content = $extraction_service->extractFromUrl($url);
            
            if (empty($content)) {
                wp_send_json_error([
                    'message' => __('No content could be extracted from the URL', 'vector-bridge-mvdb-indexer')
                ]);
            }
            
            // Generate post_type from URL domain
            $parsed_url = parse_url($url);
            $domain = $parsed_url['host'] ?? 'unknown';
            $post_type = str_replace(['.', '-'], '_', $domain);
            
            // Chunk the content
            $chunking_service = new \VectorBridge\MVDBIndexer\Services\ChunkingService();
            $chunks = $chunking_service->chunkContent($content, $url);
            
            if (empty($chunks)) {
                wp_send_json_error([
                    'message' => __('Content could not be chunked', 'vector-bridge-mvdb-indexer')
                ]);
            }
            
            // Index to MVDB
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $result = $mvdb_service->indexChunks($chunks, $post_type, $url);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully processed URL and indexed %d chunks', 'vector-bridge-mvdb-indexer'),
                    count($chunks)
                ),
                'details' => [
                    'url' => $url,
                    'post_type' => $post_type,
                    'chunks_count' => count($chunks),
                    'indexed_count' => $result['indexed_count'] ?? count($chunks)
                ]
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error processing URL: %s', 'vector-bridge-mvdb-indexer'),
                    $e->getMessage()
                )
            ]);
        }
    }
    
    /**
     * Handle upload file AJAX request
     * 
     * @return void
     */
    public function handleUploadFile(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error([
                'message' => __('No file uploaded or upload error occurred', 'vector-bridge-mvdb-indexer')
            ]);
        }
        
        $file = $_FILES['file'];
        $allowed_types = ['pdf', 'docx', 'txt', 'md'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('File type not supported. Allowed types: %s', 'vector-bridge-mvdb-indexer'),
                    implode(', ', $allowed_types)
                )
            ]);
        }
        
        try {
            // Extract content from file
            $extraction_service = new \VectorBridge\MVDBIndexer\Services\ExtractionService();
            $content = $extraction_service->extractFromFile($file['tmp_name'], $file_extension);
            
            if (empty($content)) {
                wp_send_json_error([
                    'message' => __('No content could be extracted from the file', 'vector-bridge-mvdb-indexer')
                ]);
            }
            
            // Generate post_type from file extension
            $post_type = $file_extension;
            $title = pathinfo($file['name'], PATHINFO_FILENAME);
            
            // Chunk the content
            $chunking_service = new \VectorBridge\MVDBIndexer\Services\ChunkingService();
            $chunks = $chunking_service->chunkContent($content, $title);
            
            if (empty($chunks)) {
                wp_send_json_error([
                    'message' => __('Content could not be chunked', 'vector-bridge-mvdb-indexer')
                ]);
            }
            
            // Index to MVDB
            $mvdb_service = new \VectorBridge\MVDBIndexer\Services\MVDBService();
            $result = $mvdb_service->indexChunks($chunks, $post_type, $file['name']);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully processed file and indexed %d chunks', 'vector-bridge-mvdb-indexer'),
                    count($chunks)
                ),
                'details' => [
                    'filename' => $file['name'],
                    'post_type' => $post_type,
                    'chunks_count' => count($chunks),
                    'indexed_count' => $result['indexed_count'] ?? count($chunks)
                ]
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error processing file: %s', 'vector-bridge-mvdb-indexer'),
                    $e->getMessage()
                )
            ]);
        }
    }
    
    /**
     * Handle get jobs AJAX request (placeholder)
     * 
     * @return void
     */
    public function handleGetJobs(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
        }
        
        // Return empty jobs for now
        wp_send_json_success([
            'jobs' => []
        ]);
    }
    
}
