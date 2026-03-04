<?php

namespace VectorBridge\MVDBIndexer\Admin;

use VectorBridge\MVDBIndexer\Support\Validation;

/**
 * Settings Handler
 *
 * Manages plugin settings registration, field rendering, and the settings page.
 * All AJAX handling has been moved to dedicated controllers (Task #3).
 *
 * Reduced from ~890 lines to ~340 lines by removing duplicate AJAX handlers.
 */
class Settings {

    /**
     * Settings option prefix
     */
    private const OPTION_PREFIX = 'vector_bridge_';

    /**
     * Constructor
     *
     * Hook registration is handled by HookManager — no add_action calls here.
     */
    public function __construct() {
        // Intentionally empty — HookManager registers admin_init -> initSettings()
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

    // --- Section Descriptions ---

    public function renderMvdbSectionDescription(): void {
        echo '<p>' . esc_html__('Configure your WP Engine Managed Vector Database connection settings.', 'vector-bridge-mvdb-indexer') . '</p>';
    }

    public function renderProcessingSectionDescription(): void {
        echo '<p>' . esc_html__('Configure how content is processed and chunked before indexing.', 'vector-bridge-mvdb-indexer') . '</p>';
    }

    public function renderPerformanceSectionDescription(): void {
        echo '<p>' . esc_html__('Configure performance and rate limiting settings.', 'vector-bridge-mvdb-indexer') . '</p>';
    }

    // --- Field Rendering ---

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

    private function renderTextField(array $args, string $value, string $input_type, bool $required): void {
        $masked_value = $input_type === 'password' && !empty($value) ? Validation::maskToken($value) : $value;

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

    // --- Sanitization Callbacks ---

    public function sanitizeChunkSize($value): int {
        return Validation::clampInt(intval($value), 100, 5000);
    }

    public function sanitizeOverlapPercentage($value): int {
        return Validation::clampInt(intval($value), 0, 50);
    }

    public function sanitizeBatchSize($value): int {
        return Validation::clampInt(intval($value), 1, 1000);
    }

    public function sanitizeQps($value): float {
        return Validation::clampFloat(floatval($value), 0.1, 100.0);
    }

    public function sanitizeToken($value): string {
        $value = sanitize_text_field($value);

        // If the value contains asterisks, it's masked - don't update
        if (empty($value) || str_contains($value, '*')) {
            return get_option(self::OPTION_PREFIX . 'mvdb_token', '');
        }

        return $value;
    }

    // --- Static Accessors ---

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

    // --- Settings Page Rendering ---

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
}
