<?php

namespace VectorBridge\MVDBIndexer\Services;

/**
 * Content Type Builder Interface
 * 
 * Interface for content type-specific data builders.
 * Each content type (video, document, webpage, etc.) should implement
 * this interface to provide custom data structure building.
 */
interface ContentTypeBuilderInterface {
    
    /**
     * Build document data structure for MVDB indexing
     * 
     * @param array $chunk Content chunk with metadata
     * @param string $collection Collection name
     * @param array $metadata Additional metadata for building
     * @return array Document data structure ready for MVDB
     */
    public function buildDocumentData(array $chunk, string $collection, array $metadata = []): array;
    
    /**
     * Get required fields for this content type
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array;
    
    /**
     * Get optional fields for this content type
     * 
     * @return array Array of optional field names
     */
    public function getOptionalFields(): array;
    
    /**
     * Validate chunk data for this content type
     * 
     * @param array $chunk Content chunk to validate
     * @param array $metadata Additional metadata
     * @return bool True if valid
     * @throws \Exception If validation fails
     */
    public function validateChunkData(array $chunk, array $metadata = []): bool;
    
    /**
     * Get content type identifier
     * 
     * @return string Content type identifier
     */
    public function getContentType(): string;
    
    /**
     * Extract title from chunk content
     * 
     * @param array $chunk Content chunk
     * @param array $metadata Additional metadata
     * @return string Extracted title
     */
    public function extractTitle(array $chunk, array $metadata = []): string;
    
    /**
     * Process content before chunking (content type-specific preprocessing)
     * 
     * @param string $content Raw content
     * @param array $metadata Additional metadata
     * @return string Processed content
     */
    public function preprocessContent(string $content, array $metadata = []): string;
}
