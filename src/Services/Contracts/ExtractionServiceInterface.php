<?php

namespace VectorBridge\MVDBIndexer\Services\Contracts;

/**
 * Extraction Service Interface
 *
 * Contract for content extraction from various sources.
 */
interface ExtractionServiceInterface {

    /**
     * Extract content from URL
     *
     * @param string $url URL to extract from
     * @return string Extracted content
     * @throws \Exception If extraction fails
     */
    public function extractFromUrl(string $url): string;

    /**
     * Extract content from file
     *
     * @param string $file_path Path to file
     * @return string Extracted content
     * @throws \Exception If extraction fails
     */
    public function extractFromFile(string $file_path): string;

    /**
     * Extract content from HTML
     *
     * @param string $html HTML content
     * @return string Extracted text content
     */
    public function extractFromHtml(string $html): string;

    /**
     * Extract content from text (passthrough with cleaning)
     *
     * @param string $text Text content
     * @return string Cleaned text content
     */
    public function extractFromText(string $text): string;

    /**
     * Get supported file types
     *
     * @return array Supported file types and MIME types
     */
    public function getSupportedTypes(): array;

    /**
     * Validate file type
     *
     * @param string $file_path File path
     * @return bool True if supported
     */
    public function isFileTypeSupported(string $file_path): bool;
}
