<?php

namespace VectorBridge\MVDBIndexer\Core;

use VectorBridge\MVDBIndexer\Admin\AdminMenu;
use VectorBridge\MVDBIndexer\Admin\AssetManager;
use VectorBridge\MVDBIndexer\Admin\Settings;
use VectorBridge\MVDBIndexer\Controllers\CollectionController;
use VectorBridge\MVDBIndexer\Controllers\ConnectionController;
use VectorBridge\MVDBIndexer\Controllers\IndexingController;
use VectorBridge\MVDBIndexer\Controllers\JobController;
use VectorBridge\MVDBIndexer\Cron\CronManager;
use VectorBridge\MVDBIndexer\Services\ChunkingService;
use VectorBridge\MVDBIndexer\Services\ExtractionService;
use VectorBridge\MVDBIndexer\Services\MVDBService;

/**
 * Service Provider
 *
 * Simple closure-based DI container with lazy instantiation.
 * Services are only created when first requested via get().
 */
class ServiceProvider {

    /**
     * Resolved service instances (singletons within request)
     *
     * @var array<string, mixed>
     */
    private array $services = [];

    /**
     * Factory closures for lazy instantiation
     *
     * @var array<string, \Closure>
     */
    private array $factories = [];

    /**
     * Register all service factories
     *
     * @return void
     */
    public function register(): void {
        // Core services (lazy — instantiated on first get())
        $this->factories['settings']   = fn() => new Settings();
        $this->factories['mvdb']       = fn() => new MVDBService();
        $this->factories['chunking']   = fn() => new ChunkingService();
        $this->factories['extraction'] = fn() => new ExtractionService();

        // Admin
        $this->factories['admin_menu']    = fn() => new AdminMenu();
        $this->factories['asset_manager'] = fn() => new AssetManager();

        // Controllers (receive container for service access)
        $this->factories['controller.connection'] = fn() => new ConnectionController($this);
        $this->factories['controller.indexing']   = fn() => new IndexingController($this);
        $this->factories['controller.collection'] = fn() => new CollectionController($this);
        $this->factories['controller.job']        = fn() => new JobController($this);

        // Cron
        $this->factories['cron'] = fn() => new CronManager($this);
    }

    /**
     * Get a service instance (lazy-loaded, singleton per request)
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws \Exception If service is not registered
     */
    public function get(string $id): mixed {
        if (!isset($this->services[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \Exception("Service '{$id}' not registered");
            }
            $this->services[$id] = ($this->factories[$id])();
        }

        return $this->services[$id];
    }

    /**
     * Check if a service is registered
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->factories[$id]) || isset($this->services[$id]);
    }
}
