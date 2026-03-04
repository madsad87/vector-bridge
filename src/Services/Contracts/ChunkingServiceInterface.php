<?php

namespace VectorBridge\MVDBIndexer\Services\Contracts;

/**
 * Chunking Service Interface
 *
 * Contract for content chunking with configurable overlap.
 */
interface ChunkingServiceInterface {

    /**
     * Chunk content into overlapping segments
     *
     * @param string $content Content to chunk
     * @param string $source Source identifier
     * @return array Array of chunks with metadata
     */
    public function chunkContent(string $content, string $source = ''): array;

    /**
     * Get chunking statistics for content
     *
     * @param string $content Content to analyze
     * @return array Chunking statistics
     */
    public function getChunkingStats(string $content): array;

    /**
     * Validate chunking configuration
     *
     * @return array Validation results
     */
    public function validateConfiguration(): array;
}
