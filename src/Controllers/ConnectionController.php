<?php

namespace VectorBridge\MVDBIndexer\Controllers;

/**
 * Connection Controller
 *
 * Handles MVDB connection validation AJAX requests.
 * Consolidates duplicate handlers from Plugin.php and Settings.php.
 */
class ConnectionController extends AbstractController {

    /**
     * Validate MVDB connection
     *
     * AJAX: wp_ajax_vector_bridge_validate_connection
     *
     * @return void
     */
    public function validateConnection(): void {
        $this->verifyRequest();

        try {
            $result = $this->service('mvdb')->validateConnection();

            $this->success([
                'message' => __('Connection successful!', 'vector-bridge-mvdb-indexer'),
                'details' => $result,
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Connection failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }
}
