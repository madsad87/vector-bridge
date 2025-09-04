<?php

namespace VectorBridge\MVDBIndexer\Services\ContentTypes;

use VectorBridge\MVDBIndexer\Services\ContentTypeBuilderInterface;

/**
 * Webpage Content Builder
 * 
 * Builds document data structures for web pages and URLs.
 * Handles webpage-specific fields like meta descriptions and publish dates.
 */
class WebpageContentBuilder implements ContentTypeBuilderInterface {
    
    /**
     * Build document data structure for webpage content
     * 
     * @param array $chunk Content chunk with metadata
     * @param string $collection Collection name
     * @param array $metadata Additional metadata for building
     * @return array Document data structure ready for MVDB
     */
    public function buildDocumentData(array $chunk, string $collection, array $metadata = []): array {
        // Base data structure
        $data = [
            'post_type' => 'webpage',
            'url_source' => $metadata['url_source'] ?? $chunk['source'] ?? '',
            'chunk_index' => $chunk['chunk_index'] ?? 0,
            'indexed_by' => 'vector-bridge',
            'wordpress_site' => get_site_url(),
            'tenant' => \VectorBridge\MVDBIndexer\Admin\Settings::get('tenant', '')
        ];
        
        // Webpage-specific fields
        $data['post_title'] = $this->extractTitle($chunk, $metadata);
        $data['post_content'] = $chunk['content'] ?? '';
        
        // Optional webpage metadata
        if (isset($metadata['meta_description']) && !empty($metadata['meta_description'])) {
            $data['meta_description'] = sanitize_textarea_field($metadata['meta_description']);
        }
        
        if (isset($metadata['publish_date']) && !empty($metadata['publish_date'])) {
            $data['publish_date'] = sanitize_text_field($metadata['publish_date']);
        }
        
        if (isset($metadata['author']) && !empty($metadata['author'])) {
            $data['author'] = sanitize_text_field($metadata['author']);
        }
        
        if (isset($metadata['site_name']) && !empty($metadata['site_name'])) {
            $data['site_name'] = sanitize_text_field($metadata['site_name']);
        }
        
        if (isset($metadata['language']) && !empty($metadata['language'])) {
            $data['language'] = sanitize_text_field($metadata['language']);
        }
        
        // Extract domain from URL
        $url_source = $data['url_source'];
        if (!empty($url_source) && filter_var($url_source, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url($url_source);
            if (isset($parsed_url['host'])) {
                $data['domain'] = $parsed_url['host'];
            }
        }
        
        // Add timestamp for indexing
        $data['indexed_at'] = current_time('c');
        
        return $data;
    }
    
    /**
     * Get required fields for webpage content type
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array {
        return ['post_title', 'post_content', 'url_source'];
    }
    
    /**
     * Get optional fields for webpage content type
     * 
     * @return array Array of optional field names
     */
    public function getOptionalFields(): array {
        return ['meta_description', 'publish_date', 'author', 'site_name', 'language', 'domain'];
    }
    
    /**
     * Validate chunk data for webpage content type
     * 
     * @param array $chunk Content chunk to validate
     * @param array $metadata Additional metadata
     * @return bool True if valid
     * @throws \Exception If validation fails
     */
    public function validateChunkData(array $chunk, array $metadata = []): bool {
        // Check required content
        if (empty($chunk['content'])) {
            throw new \Exception(__('Webpage content is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Check URL source
        $url_source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (empty($url_source)) {
            throw new \Exception(__('Webpage URL source is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate URL format
        if (!filter_var($url_source, FILTER_VALIDATE_URL)) {
            throw new \Exception(__('Invalid webpage URL format', 'vector-bridge-mvdb-indexer'));
        }
        
        return true;
    }
    
    /**
     * Get content type identifier
     * 
     * @return string Content type identifier
     */
    public function getContentType(): string {
        return 'webpage';
    }
    
    /**
     * Extract title from webpage chunk content
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
            
            // Look for first meaningful line that could be a title
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip empty lines and very short/long lines
                if (!empty($line) && strlen($line) > 10 && strlen($line) < 100) {
                    // Skip lines that look like navigation or metadata
                    if (!preg_match('/^(home|about|contact|menu|navigation|breadcrumb)/i', $line)) {
                        return $line;
                    }
                }
            }
        }
        
        // Generate title from URL
        $url_source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (!empty($url_source)) {
            $parsed_url = parse_url($url_source);
            
            // Use path for title generation
            if (isset($parsed_url['path']) && $parsed_url['path'] !== '/') {
                $path = trim($parsed_url['path'], '/');
                $title = str_replace(['/', '-', '_'], ' ', $path);
                $title = ucwords($title);
                if (!empty($title)) {
                    return $title;
                }
            }
            
            // Use domain as fallback
            if (isset($parsed_url['host'])) {
                $domain = $parsed_url['host'];
                $domain = preg_replace('/^www\./', '', $domain);
                return ucwords(str_replace('.', ' ', $domain));
            }
        }
        
        // Fallback title with chunk info
        $chunk_info = isset($chunk['chunk_index']) ? ' (Part ' . ($chunk['chunk_index'] + 1) . ')' : '';
        return 'Web Page' . $chunk_info;
    }
    
    /**
     * Process webpage content before chunking
     * 
     * @param string $content Raw webpage content
     * @param array $metadata Additional metadata
     * @return string Processed content
     */
    public function preprocessContent(string $content, array $metadata = []): string {
        // Remove common webpage artifacts
        $content = $this->removeWebpageArtifacts($content);
        
        // Clean up navigation and UI elements
        $content = $this->removeNavigationElements($content);
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        return trim($content);
    }
    
    /**
     * Remove common webpage artifacts
     * 
     * @param string $content Webpage content
     * @return string Cleaned content
     */
    private function removeWebpageArtifacts(string $content): string {
        // Remove common navigation patterns
        $content = preg_replace('/^(home|about|contact|services|products|blog)\s*$/mi', '', $content);
        
        // Remove breadcrumb patterns
        $content = preg_replace('/home\s*>\s*[^>]+(\s*>\s*[^>]+)*/', '', $content);
        
        // Remove "read more" and similar patterns
        $content = preg_replace('/\b(read more|continue reading|learn more|click here)\b/i', '', $content);
        
        // Remove social media patterns
        $content = preg_replace('/\b(share|tweet|like|follow us|subscribe)\b/i', '', $content);
        
        // Remove copyright and footer patterns
        $content = preg_replace('/©\s*\d{4}.*$/m', '', $content);
        $content = preg_replace('/copyright\s+\d{4}.*$/mi', '', $content);
        
        return $content;
    }
    
    /**
     * Remove navigation and UI elements
     * 
     * @param string $content Webpage content
     * @return string Cleaned content
     */
    private function removeNavigationElements(string $content): string {
        // Remove menu items and navigation
        $content = preg_replace('/^(menu|navigation|nav)\s*$/mi', '', $content);
        
        // Remove form labels and buttons
        $content = preg_replace('/\b(submit|send|search|go|login|register|sign up|sign in)\b/i', '', $content);
        
        // Remove pagination
        $content = preg_replace('/\b(previous|next|page \d+|\d+ of \d+)\b/i', '', $content);
        
        // Remove sidebar content indicators
        $content = preg_replace('/^(sidebar|widget|advertisement|ad)\s*$/mi', '', $content);
        
        return $content;
    }
    
    /**
     * Extract webpage metadata from HTML content
     * 
     * @param string $html_content Raw HTML content
     * @return array Webpage metadata
     */
    public function extractWebpageMetadata(string $html_content): array {
        $metadata = [];
        
        // Extract title tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html_content, $matches)) {
            $metadata['post_title'] = trim($matches[1]);
        }
        
        // Extract meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['meta_description'] = trim($matches[1]);
        }
        
        // Extract meta author
        if (preg_match('/<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['author'] = trim($matches[1]);
        }
        
        // Extract language
        if (preg_match('/<html[^>]+lang=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['language'] = trim($matches[1]);
        }
        
        // Extract Open Graph data
        $metadata = array_merge($metadata, $this->extractOpenGraphData($html_content));
        
        // Extract JSON-LD structured data
        $metadata = array_merge($metadata, $this->extractJsonLdData($html_content));
        
        return $metadata;
    }
    
    /**
     * Extract Open Graph metadata
     * 
     * @param string $html_content HTML content
     * @return array Open Graph metadata
     */
    private function extractOpenGraphData(string $html_content): array {
        $metadata = [];
        
        // Extract og:title
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['post_title'] = trim($matches[1]);
        }
        
        // Extract og:description
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['meta_description'] = trim($matches[1]);
        }
        
        // Extract og:site_name
        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches)) {
            $metadata['site_name'] = trim($matches[1]);
        }
        
        return $metadata;
    }
    
    /**
     * Extract JSON-LD structured data
     * 
     * @param string $html_content HTML content
     * @return array Structured data metadata
     */
    private function extractJsonLdData(string $html_content): array {
        $metadata = [];
        
        // Find JSON-LD scripts
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>([^<]+)<\/script>/i', $html_content, $matches)) {
            foreach ($matches[1] as $json_content) {
                $data = json_decode(trim($json_content), true);
                if ($data && is_array($data)) {
                    // Extract relevant fields
                    if (isset($data['name'])) {
                        $metadata['post_title'] = $data['name'];
                    }
                    if (isset($data['description'])) {
                        $metadata['meta_description'] = $data['description'];
                    }
                    if (isset($data['author'])) {
                        if (is_string($data['author'])) {
                            $metadata['author'] = $data['author'];
                        } elseif (is_array($data['author']) && isset($data['author']['name'])) {
                            $metadata['author'] = $data['author']['name'];
                        }
                    }
                    if (isset($data['datePublished'])) {
                        $metadata['publish_date'] = $data['datePublished'];
                    }
                }
            }
        }
        
        return $metadata;
    }
}
