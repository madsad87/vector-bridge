<?php

namespace VectorBridge\MVDBIndexer\Controllers;

use VectorBridge\MVDBIndexer\Core\ServiceProvider;

/**
 * Abstract Controller
 *
 * Base class for all AJAX controllers. Provides standardized
 * nonce verification, capability checks, and JSON response helpers.
 */
abstract class AbstractController {

    protected ServiceProvider $container;

    public function __construct(ServiceProvider $container) {
        $this->container = $container;
    }

    /**
     * Verify AJAX request security (nonce + capability)
     *
     * Uses check_ajax_referer (WordPress best practice) and
     * standardized capability check. Terminates on failure.
     *
     * @return void
     */
    protected function verifyRequest(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Insufficient permissions', 'vector-bridge-mvdb-indexer')],
                403
            );
        }
    }

    /**
     * Send success JSON response
     *
     * @param array $data Response data
     * @return void
     */
    protected function success(array $data): void {
        wp_send_json_success($data);
    }

    /**
     * Send error JSON response
     *
     * @param string $message Error message
     * @param int    $status  HTTP status code
     * @return void
     */
    protected function error(string $message, int $status = 400): void {
        wp_send_json_error(['message' => $message], $status);
    }

    /**
     * Get a service from the container
     *
     * @param string $name Service identifier
     * @return mixed Service instance
     */
    protected function service(string $name): mixed {
        return $this->container->get($name);
    }
}
