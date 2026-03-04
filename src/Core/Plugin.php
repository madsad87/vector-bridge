<?php

namespace VectorBridge\MVDBIndexer\Core;

/**
 * Main Plugin Class
 *
 * Bootstrap only — delegates all responsibilities to specialized classes:
 * - ServiceProvider: DI container with lazy service instantiation
 * - HookManager: WordPress hook registration
 * - Controllers: AJAX request handling
 * - CronManager: Scheduled job processing
 *
 * Singleton pattern to ensure only one instance of the plugin runs.
 */
class Plugin {

    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Service container
     *
     * @var ServiceProvider
     */
    private ServiceProvider $container;

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

        // Create and register the service container
        $this->container = new ServiceProvider();
        $this->container->register();

        // Register all WordPress hooks via HookManager
        (new HookManager($this->container))->register();

        // Initialize admin interface if in admin with proper capabilities
        if (is_admin() && current_user_can('manage_options')) {
            $this->container->get('admin_menu');
        }

        $this->initialized = true;
    }

    /**
     * Get the service container
     *
     * @return ServiceProvider
     */
    public function getContainer(): ServiceProvider {
        return $this->container;
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
