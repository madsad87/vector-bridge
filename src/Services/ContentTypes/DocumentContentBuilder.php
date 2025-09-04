<?php

namespace VectorBridge\MVDBIndexer\Services\ContentTypes;

use VectorBridge\MVDBIndexer\Services\ContentTypeBuilderInterface;

/**
 * Document Content Builder
 * 
 * Builds document data structures for PDF, DOCX, TXT, and MD files.
 * Handles document-specific fields like file size, page count, and author.
 */
class DocumentContentBuilder implements ContentTypeBuilderInterface {
    
    /**
     * Build document data structure for document content
     * 
     * @param array $chunk Content chunk with metadata
     * @param string $collection Collection name
     * @param array $metadata Additional metadata for building
     * @return array Document data structure ready for MVDB
     */
    public function buildDocumentData(array $chunk, string $collection, array $metadata = []): array {
        // Base data structure
        $data = [
            'post_type' => 'document',
            'url_source' => $metadata['url_source'] ?? $chunk['source'] ?? '',
            'chunk_index' => $chunk['chunk_index'] ?? 0,
            'indexed_by' => 'vector-bridge',
            'wordpress_site' => get_site_url(),
            'tenant' => \VectorBridge\MVDBIndexer\Admin\Settings::get('tenant', '')
        ];
        
        // Document-specific fields
        $data['document_title'] = $this->extractTitle($chunk, $metadata);
        $data['document_content'] = $chunk['content'] ?? '';
        
        // Optional document metadata
        if (isset($metadata['file_size'])) {
            $data['file_size'] = (int) $metadata['file_size'];
        }
        
        if (isset($metadata['page_count'])) {
            $data['page_count'] = (int) $metadata['page_count'];
        }
        
        if (isset($metadata['author']) && !empty($metadata['author'])) {
            $data['author'] = sanitize_text_field($metadata['author']);
        }
        
        if (isset($metadata['creation_date']) && !empty($metadata['creation_date'])) {
            $data['creation_date'] = sanitize_text_field($metadata['creation_date']);
        }
        
        if (isset($metadata['file_type']) && !empty($metadata['file_type'])) {
            $data['file_type'] = sanitize_text_field($metadata['file_type']);
        }
        
        if (isset($metadata['description']) && !empty($metadata['description'])) {
            $data['description'] = sanitize_textarea_field($metadata['description']);
        }
        
        // Extract file extension from source
        $source = $chunk['source'] ?? '';
        if (!empty($source)) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (!empty($extension)) {
                $data['file_extension'] = $extension;
            }
        }
        
        // Add timestamp for indexing
        $data['indexed_at'] = current_time('c');
        
        return $data;
    }
    
    /**
     * Get required fields for document content type
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array {
        return ['document_title', 'document_content', 'url_source'];
    }
    
    /**
     * Get optional fields for document content type
     * 
     * @return array Array of optional field names
     */
    public function getOptionalFields(): array {
        return ['file_size', 'page_count', 'author', 'creation_date', 'file_type', 'description', 'file_extension'];
    }
    
    /**
     * Validate chunk data for document content type
     * 
     * @param array $chunk Content chunk to validate
     * @param array $metadata Additional metadata
     * @return bool True if valid
     * @throws \Exception If validation fails
     */
    public function validateChunkData(array $chunk, array $metadata = []): bool {
        // Check required content
        if (empty($chunk['content'])) {
            throw new \Exception(__('Document content is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Check URL source
        $url_source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (empty($url_source)) {
            throw new \Exception(__('Document URL source is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate file size if provided
        if (isset($metadata['file_size']) && $metadata['file_size'] < 0) {
            throw new \Exception(__('Invalid file size', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate page count if provided
        if (isset($metadata['page_count']) && $metadata['page_count'] < 0) {
            throw new \Exception(__('Invalid page count', 'vector-bridge-mvdb-indexer'));
        }
        
        return true;
    }
    
    /**
     * Get content type identifier
     * 
     * @return string Content type identifier
     */
    public function getContentType(): string {
        return 'document';
    }
    
    /**
     * Extract title from document chunk content
     * 
     * @param array $chunk Content chunk
     * @param array $metadata Additional metadata
     * @return string Extracted title
     */
    public function extractTitle(array $chunk, array $metadata = []): string {
        // Use provided title if available
        if (isset($metadata['title']) && !empty($metadata['title'])) {
            return sanitize_text_field($metadata['title']);
        }
        
        if (isset($metadata['document_title']) && !empty($metadata['document_title'])) {
            return sanitize_text_field($metadata['document_title']);
        }
        
        // Extract from content (look for title patterns)
        $content = $chunk['content'] ?? '';
        if (!empty($content)) {
            // Look for markdown-style headers
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = trim($matches[1]);
                if (strlen($title) > 5 && strlen($title) < 100) {
                    return $title;
                }
            }
            
            // Look for first meaningful line
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip empty lines and very short/long lines
                if (!empty($line) && strlen($line) > 10 && strlen($line) < 100) {
                    // Skip lines that look like metadata or timestamps
                    if (!preg_match('/^\d+[\.\)]\s|^[\d\-\/\s:]+$|^page\s+\d+/i', $line)) {
                        return $line;
                    }
                }
            }
        }
        
        // Generate title from source filename
        $source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (!empty($source)) {
            // Extract filename from URL or path
            $filename = basename(parse_url($source, PHP_URL_PATH));
            if (!empty($filename)) {
                // Remove extension and clean up
                $title = pathinfo($filename, PATHINFO_FILENAME);
                $title = str_replace(['_', '-'], ' ', $title);
                $title = ucwords($title);
                return $title;
            }
        }
        
        // Fallback title with chunk info
        $chunk_info = isset($chunk['chunk_index']) ? ' (Part ' . ($chunk['chunk_index'] + 1) . ')' : '';
        return 'Document' . $chunk_info;
    }
    
    /**
     * Process document content before chunking
     * 
     * @param string $content Raw document content
     * @param array $metadata Additional metadata
     * @return string Processed content
     */
    public function preprocessContent(string $content, array $metadata = []): string {
        // Remove common document artifacts
        $content = $this->removeDocumentArtifacts($content);
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Clean up common formatting issues
        $content = $this->cleanFormattingIssues($content);
        
        return trim($content);
    }
    
    /**
     * Remove common document artifacts
     * 
     * @param string $content Document content
     * @return string Cleaned content
     */
    private function removeDocumentArtifacts(string $content): string {
        // Remove page numbers
        $content = preg_replace('/^page\s+\d+\s*$/mi', '', $content);
        $content = preg_replace('/^\d+\s*$/m', '', $content);
        
        // Remove headers and footers (common patterns)
        $content = preg_replace('/^[\-=]{3,}.*$/m', '', $content);
        
        // Remove table of contents patterns
        $content = preg_replace('/^.*\.{3,}\s*\d+\s*$/m', '', $content);
        
        // Remove excessive punctuation
        $content = preg_replace('/[\.]{4,}/', '...', $content);
        
        return $content;
    }
    
    /**
     * Clean up common formatting issues
     * 
     * @param string $content Document content
     * @return string Cleaned content
     */
    private function cleanFormattingIssues(string $content): string {
        // Fix broken words (common in PDF extraction)
        $content = preg_replace('/(\w+)-\s*\n\s*(\w+)/', '$1$2', $content);
        
        // Fix spacing around punctuation
        $content = preg_replace('/\s+([,.;:!?])/', '$1', $content);
        $content = preg_replace('/([,.;:!?])\s*([A-Z])/', '$1 $2', $content);
        
        // Fix multiple spaces
        $content = preg_replace('/\s{2,}/', ' ', $content);
        
        // Fix paragraph breaks
        $content = preg_replace('/\.\s*\n\s*([A-Z])/', '. $1', $content);
        
        return $content;
    }
    
    /**
     * Extract document metadata from file
     * 
     * @param string $file_path Path to document file
     * @return array Document metadata
     */
    public function extractDocumentMetadata(string $file_path): array {
        $metadata = [];
        
        if (!file_exists($file_path)) {
            return $metadata;
        }
        
        // Basic file information
        $metadata['file_size'] = filesize($file_path);
        $metadata['file_type'] = mime_content_type($file_path);
        $metadata['file_extension'] = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // File timestamps
        $metadata['creation_date'] = date('Y-m-d H:i:s', filectime($file_path));
        $metadata['modified_date'] = date('Y-m-d H:i:s', filemtime($file_path));
        
        // Try to extract PDF metadata
        if ($metadata['file_extension'] === 'pdf') {
            $metadata = array_merge($metadata, $this->extractPdfMetadata($file_path));
        }
        
        return $metadata;
    }
    
    /**
     * Extract PDF-specific metadata
     * 
     * @param string $file_path Path to PDF file
     * @return array PDF metadata
     */
    private function extractPdfMetadata(string $file_path): array {
        $metadata = [];
        
        try {
            // Use the same PDF parser as ExtractionService
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file_path);
            
            // Extract PDF details
            $details = $pdf->getDetails();
            
            if (isset($details['Title'])) {
                $metadata['document_title'] = $details['Title'];
            }
            
            if (isset($details['Author'])) {
                $metadata['author'] = $details['Author'];
            }
            
            if (isset($details['CreationDate'])) {
                $metadata['creation_date'] = $details['CreationDate'];
            }
            
            if (isset($details['Subject'])) {
                $metadata['description'] = $details['Subject'];
            }
            
            // Get page count
            $pages = $pdf->getPages();
            $metadata['page_count'] = count($pages);
            
        } catch (\Exception $e) {
            // If PDF metadata extraction fails, continue without it
            error_log('Vector Bridge: Failed to extract PDF metadata: ' . $e->getMessage());
        }
        
        return $metadata;
    }
    
    /**
     * Get document type from file extension
     * 
     * @param string $extension File extension
     * @return string Document type description
     */
    private function getDocumentType(string $extension): string {
        $types = [
            'pdf' => 'PDF Document',
            'docx' => 'Word Document',
            'doc' => 'Word Document',
            'txt' => 'Text Document',
            'md' => 'Markdown Document',
            'rtf' => 'Rich Text Document'
        ];
        
        return $types[strtolower($extension)] ?? 'Document';
    }
}
