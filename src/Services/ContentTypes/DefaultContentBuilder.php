<?php

namespace VectorBridge\MVDBIndexer\Services\ContentTypes;

use VectorBridge\MVDBIndexer\Services\ContentTypeBuilderInterface;

/**
 * Default Content Builder
 * 
 * Builds document data structures for default/legacy WordPress post content.
 * Maintains backward compatibility with existing content processing.
 */
class DefaultContentBuilder implements ContentTypeBuilderInterface {
    
    /**
     * Build document data structure for default content
     * 
     * @param array $chunk Content chunk with metadata
     * @param string $collection Collection name
     * @param array $metadata Additional metadata for building
     * @return array Document data structure ready for MVDB
     */
    public function buildDocumentData(array $chunk, string $collection, array $metadata = []): array {
        // Base data structure (maintains existing format for backward compatibility)
        $data = [
            'post_title' => $this->extractTitle($chunk, $metadata),
            'post_content' => $chunk['content'] ?? '',
            'post_type' => $collection,
            'post_status' => 'publish',
            'post_date' => current_time('c'),
            'chunk_index' => $chunk['chunk_index'] ?? 0,
            'source_origin' => $chunk['source'] ?? '',
            'indexed_by' => 'vector-bridge',
            'wordpress_site' => get_site_url(),
            'tenant' => \VectorBridge\MVDBIndexer\Admin\Settings::get('tenant', '')
        ];
        
        // Add url_source field for consistency with new content types
        if (isset($metadata['url_source'])) {
            $data['url_source'] = $metadata['url_source'];
        } elseif (!empty($chunk['source'])) {
            $data['url_source'] = $chunk['source'];
        }
        
        // Optional fields that may be provided
        if (isset($metadata['post_excerpt']) && !empty($metadata['post_excerpt'])) {
            $data['post_excerpt'] = sanitize_textarea_field($metadata['post_excerpt']);
        }
        
        if (isset($metadata['post_author']) && !empty($metadata['post_author'])) {
            $data['post_author'] = sanitize_text_field($metadata['post_author']);
        }
        
        if (isset($metadata['post_category']) && !empty($metadata['post_category'])) {
            $data['post_category'] = sanitize_text_field($metadata['post_category']);
        }
        
        if (isset($metadata['post_tags']) && !empty($metadata['post_tags'])) {
            $data['post_tags'] = sanitize_text_field($metadata['post_tags']);
        }
        
        return $data;
    }
    
    /**
     * Get required fields for default content type
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array {
        return ['post_title', 'post_content'];
    }
    
    /**
     * Get optional fields for default content type
     * 
     * @return array Array of optional field names
     */
    public function getOptionalFields(): array {
        return ['post_status', 'post_date', 'post_excerpt', 'post_author', 'post_category', 'post_tags', 'url_source'];
    }
    
    /**
     * Validate chunk data for default content type
     * 
     * @param array $chunk Content chunk to validate
     * @param array $metadata Additional metadata
     * @return bool True if valid
     * @throws \Exception If validation fails
     */
    public function validateChunkData(array $chunk, array $metadata = []): bool {
        // Check required content
        if (empty($chunk['content'])) {
            throw new \Exception(__('Content is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Very minimal validation for backward compatibility
        return true;
    }
    
    /**
     * Get content type identifier
     * 
     * @return string Content type identifier
     */
    public function getContentType(): string {
        return 'post';
    }
    
    /**
     * Extract title from chunk content (legacy method)
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
        
        if (isset($metadata['post_title']) && !empty($metadata['post_title'])) {
            return sanitize_text_field($metadata['post_title']);
        }
        
        // Extract from content (original logic from MVDBService)
        $content = $chunk['content'] ?? '';
        
        // Try to extract first line as title
        $lines = explode("\n", trim($content));
        $first_line = trim($lines[0] ?? '');
        
        if (!empty($first_line) && strlen($first_line) < 100) {
            return $first_line;
        }
        
        // Fallback to truncated content
        return substr($content, 0, 50) . '...';
    }
    
    /**
     * Process content before chunking (minimal processing for backward compatibility)
     * 
     * @param string $content Raw content
     * @param array $metadata Additional metadata
     * @return string Processed content
     */
    public function preprocessContent(string $content, array $metadata = []): string {
        // Minimal processing to maintain backward compatibility
        // Just basic cleanup that was already happening
        
        // Normalize line endings
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        
        // Remove excessive whitespace
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        // Remove multiple consecutive newlines
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        return trim($content);
    }
    
    /**
     * Convert legacy chunk format to new format if needed
     * 
     * @param array $chunk Legacy chunk data
     * @return array Normalized chunk data
     */
    public function normalizeLegacyChunk(array $chunk): array {
        // Ensure chunk has required fields for new system
        if (!isset($chunk['chunk_index'])) {
            $chunk['chunk_index'] = 0;
        }
        
        if (!isset($chunk['source']) && isset($chunk['source_origin'])) {
            $chunk['source'] = $chunk['source_origin'];
        }
        
        if (!isset($chunk['created_at'])) {
            $chunk['created_at'] = current_time('mysql');
        }
        
        return $chunk;
    }
    
    /**
     * Extract WordPress post metadata if available
     * 
     * @param int $post_id WordPress post ID
     * @return array Post metadata
     */
    public function extractWordPressPostMetadata(int $post_id): array {
        $metadata = [];
        
        if (!function_exists('get_post')) {
            return $metadata;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return $metadata;
        }
        
        $metadata['post_title'] = $post->post_title;
        $metadata['post_content'] = $post->post_content;
        $metadata['post_excerpt'] = $post->post_excerpt;
        $metadata['post_status'] = $post->post_status;
        $metadata['post_date'] = $post->post_date;
        $metadata['post_author'] = get_the_author_meta('display_name', $post->post_author);
        $metadata['url_source'] = get_permalink($post_id);
        
        // Get categories
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            $metadata['post_category'] = implode(', ', wp_list_pluck($categories, 'name'));
        }
        
        // Get tags
        $tags = get_the_tags($post_id);
        if (!empty($tags)) {
            $metadata['post_tags'] = implode(', ', wp_list_pluck($tags, 'name'));
        }
        
        return $metadata;
    }
    
    /**
     * Check if content appears to be from WordPress
     * 
     * @param string $content Content to check
     * @param array $metadata Content metadata
     * @return bool True if appears to be WordPress content
     */
    public function isWordPressContent(string $content, array $metadata = []): bool {
        // Check for WordPress-specific patterns
        $wp_patterns = [
            '/\[caption[^\]]*\]/',  // WordPress caption shortcodes
            '/\[gallery[^\]]*\]/',  // WordPress gallery shortcodes
            '/<!--more-->/',        // WordPress more tag
            '/\[embed[^\]]*\]/',    // WordPress embed shortcodes
        ];
        
        foreach ($wp_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        // Check metadata for WordPress indicators
        if (isset($metadata['post_id']) || isset($metadata['wordpress_site'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Process WordPress shortcodes in content
     * 
     * @param string $content Content with potential shortcodes
     * @return string Content with shortcodes processed or removed
     */
    public function processWordPressShortcodes(string $content): string {
        // Remove or process common WordPress shortcodes
        
        // Remove caption shortcodes but keep content
        $content = preg_replace('/\[caption[^\]]*\](.*?)\[\/caption\]/s', '$1', $content);
        
        // Remove gallery shortcodes
        $content = preg_replace('/\[gallery[^\]]*\]/', '', $content);
        
        // Remove embed shortcodes
        $content = preg_replace('/\[embed[^\]]*\](.*?)\[\/embed\]/s', '$1', $content);
        
        // Remove more tag
        $content = str_replace('<!--more-->', '', $content);
        
        // Remove other common shortcodes
        $content = preg_replace('/\[[^\]]+\]/', '', $content);
        
        return $content;
    }
}
