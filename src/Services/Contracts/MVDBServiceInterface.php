<?php

namespace VectorBridge\MVDBIndexer\Services\Contracts;

/**
 * MVDB Service Interface
 *
 * Contract for communication with the Managed Vector Database.
 * Defines all public operations available for MVDB interaction.
 */
interface MVDBServiceInterface {

    /**
     * Validate connection to MVDB
     *
     * @return array Connection validation result
     * @throws \Exception If connection fails
     */
    public function validateConnection(): array;

    /**
     * Index chunks into MVDB
     *
     * @param array $chunks Content chunks to index
     * @param string $collection Collection name
     * @param string $source Source URL or filename
     * @return array Indexing results
     * @throws \Exception If indexing fails
     */
    public function indexChunks(array $chunks, string $collection, string $source = ''): array;

    /**
     * Get all collections (grouped by post_type)
     *
     * @return array List of collections with document counts
     */
    public function getCollections(): array;

    /**
     * Get all documents, optionally filtered by post_type
     *
     * @param string $post_type Optional post_type filter
     * @return array Documents and stats
     */
    public function getAllDocuments(string $post_type = ''): array;

    /**
     * Search documents using similarity
     *
     * @param string $query_text Search query
     * @param string $post_type Optional post_type filter
     * @param int $limit Number of results
     * @return array Search results
     */
    public function searchDocuments(string $query_text, string $post_type = '', int $limit = 5): array;

    /**
     * Delete a single document by ID
     *
     * @param string $document_id Document ID
     * @return array Deletion result
     * @throws \Exception If deletion fails
     */
    public function deleteDocument(string $document_id): array;

    /**
     * Delete all documents of a given post_type
     *
     * @param string $post_type Post type to delete
     * @return array Deletion result
     * @throws \Exception If deletion fails
     */
    public function deleteDocumentsByType(string $post_type): array;

    /**
     * Get rate limiter statistics
     *
     * @return array Rate limiter stats
     */
    public function getRateLimiterStats(): array;
}
