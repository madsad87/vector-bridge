<?php

namespace VectorBridge\MVDBIndexer\Controllers;

/**
 * Collection Controller
 *
 * Handles all collection and document browsing AJAX requests.
 * Consolidates handlers from both Plugin.php and Settings.php:
 * - Plugin.php: getCollections, getCollectionContent, testQuery, exportCollection, deleteCollection
 * - Settings.php: getAllDocuments, searchDocuments, getDocumentDetails, deleteDocument, clearAllDocuments
 */
class CollectionController extends AbstractController {

    /**
     * Get all collections (grouped by post_type)
     *
     * AJAX: wp_ajax_vector_bridge_get_collections
     *
     * @return void
     */
    public function getCollections(): void {
        $this->verifyRequest();

        try {
            $collections = $this->service('mvdb')->getCollections();

            $this->success([
                'collections' => $collections,
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Failed to retrieve collections: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Get content of a specific collection
     *
     * AJAX: wp_ajax_vector_bridge_get_collection_content
     *
     * @return void
     */
    public function getCollectionContent(): void {
        $this->verifyRequest();

        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            $this->error(
                __('Collection name is required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $content = $this->service('mvdb')->getCollectionContent($collection);

            $this->success([
                'documents' => $content['documents'],
                'stats'     => $content['stats'],
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Failed to retrieve collection content: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Test a similarity query against a collection
     *
     * AJAX: wp_ajax_vector_bridge_test_query
     *
     * @return void
     */
    public function testQuery(): void {
        $this->verifyRequest();

        $collection = sanitize_text_field($_POST['collection'] ?? '');
        $query      = sanitize_text_field($_POST['query'] ?? '');
        $limit      = intval($_POST['limit'] ?? 5);

        if (empty($collection) || empty($query)) {
            $this->error(
                __('Collection and query are required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $results = $this->service('mvdb')->searchCollection($collection, $query, $limit);

            $this->success([
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Query failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Export a collection as JSON download
     *
     * AJAX: wp_ajax_vector_bridge_export_collection
     *
     * @return void
     */
    public function exportCollection(): void {
        // Export uses GET params
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'vector_bridge_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $collection = sanitize_text_field($_GET['collection'] ?? '');
        if (empty($collection)) {
            wp_die('Collection name is required');
        }

        try {
            $content = $this->service('mvdb')->getCollectionContent($collection);

            $export_data = [
                'collection'  => $collection,
                'exported_at' => date('Y-m-d H:i:s'),
                'stats'       => $content['stats'],
                'documents'   => $content['documents'],
            ];

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
     * Delete a collection (all documents of a post_type)
     *
     * AJAX: wp_ajax_vector_bridge_delete_collection
     *
     * @return void
     */
    public function deleteCollection(): void {
        $this->verifyRequest();

        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            $this->error(
                __('Collection name is required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $result = $this->service('mvdb')->deleteCollection($collection);

            $this->success([
                'message' => __('Collection deleted successfully.', 'vector-bridge-mvdb-indexer'),
                'result'  => $result,
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Failed to delete collection: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    // ---------------------------------------------------------------
    // Handlers consolidated from Settings.php (Content Browser)
    // ---------------------------------------------------------------

    /**
     * Get all documents
     *
     * AJAX: wp_ajax_vector_bridge_get_all_documents
     *
     * @return void
     */
    public function getAllDocuments(): void {
        $this->verifyRequest();

        try {
            $result = $this->service('mvdb')->getAllDocuments();

            $this->success($result);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Search documents by similarity
     *
     * AJAX: wp_ajax_vector_bridge_search_documents
     *
     * @return void
     */
    public function searchDocuments(): void {
        $this->verifyRequest();

        $query     = sanitize_text_field($_POST['query'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $limit     = intval($_POST['limit'] ?? 5);

        if (empty($query)) {
            $this->error(
                __('Search query is required', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $results = $this->service('mvdb')->searchDocuments($query, $post_type, $limit);

            $this->success([
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Get details for a specific document
     *
     * AJAX: wp_ajax_vector_bridge_get_document_details
     *
     * @return void
     */
    public function getDocumentDetails(): void {
        $this->verifyRequest();

        $document_id = sanitize_text_field($_POST['document_id'] ?? '');

        if (empty($document_id)) {
            $this->error(
                __('Document ID is required', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $documents = $this->service('mvdb')->getAllDocuments();

            $document = null;
            foreach ($documents['documents'] as $doc) {
                if ($doc['id'] === $document_id) {
                    $document = $doc;
                    break;
                }
            }

            if (!$document) {
                $this->error(
                    __('Document not found', 'vector-bridge-mvdb-indexer'),
                    404
                );
            }

            $this->success([
                'document' => $document,
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Delete a single document by ID
     *
     * AJAX: wp_ajax_vector_bridge_delete_document
     * Eliminates the reflection hack from Settings.php — uses public interface method.
     *
     * @return void
     */
    public function deleteDocument(): void {
        $this->verifyRequest();

        $document_id = sanitize_text_field($_POST['document_id'] ?? '');

        if (empty($document_id)) {
            $this->error(
                __('Document ID is required', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $this->service('mvdb')->deleteDocument($document_id);

            $this->success([
                'message' => __('Document deleted successfully', 'vector-bridge-mvdb-indexer'),
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Clear all documents from all collections
     *
     * AJAX: wp_ajax_vector_bridge_clear_all_documents
     *
     * @return void
     */
    public function clearAllDocuments(): void {
        $this->verifyRequest();

        try {
            $mvdb_service = $this->service('mvdb');
            $collections = $mvdb_service->getCollections();
            $deleted_total = 0;

            foreach ($collections as $collection) {
                $result = $mvdb_service->deleteDocumentsByType($collection['name']);
                $deleted_total += $result['deleted_documents'];
            }

            $this->success([
                'message' => sprintf(
                    __('Successfully deleted %d documents', 'vector-bridge-mvdb-indexer'),
                    $deleted_total
                ),
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
