<?php

namespace VectorBridge\MVDBIndexer\Services;

use VectorBridge\MVDBIndexer\Services\ContentTypes\VideoContentBuilder;
use VectorBridge\MVDBIndexer\Services\ContentTypes\DocumentContentBuilder;
use VectorBridge\MVDBIndexer\Services\ContentTypes\WebpageContentBuilder;
use VectorBridge\MVDBIndexer\Services\ContentTypes\DefaultContentBuilder;

/**
 * Content Type Factory
 * 
 * Factory class for creating content type-specific builders and processors.
 * Provides a centralized way to handle different content types with their
 * specific data structures and processing requirements.
 */
class ContentTypeFactory {
    
    /**
     * Supported content types
     */
    public const CONTENT_TYPES = [
        'video' => 'Video content with VTT transcripts',
        'document' => 'PDF, DOCX, TXT, MD documents',
        'webpage' => 'Web pages and URLs',
        'post' => 'WordPress posts (default)'
    ];
    
    /**
     * Create content type-specific data builder
     * 
     * @param string $content_type Content type identifier
     * @return ContentTypeBuilderInterface Data builder instance
     * @throws \Exception If content type is not supported
     */
    public static function createDataBuilder(string $content_type): ContentTypeBuilderInterface {
        return match($content_type) {
            'video' => new VideoContentBuilder(),
            'document' => new DocumentContentBuilder(),
            'webpage' => new WebpageContentBuilder(),
            'post', 'default' => new DefaultContentBuilder(),
            default => throw new \Exception("Unsupported content type: {$content_type}")
        };
    }
    
    /**
     * Create content type-specific extractor
     * 
     * @param string $content_type Content type identifier
     * @return ExtractionServiceInterface Extractor instance
     * @throws \Exception If content type is not supported
     */
    public static function createExtractor(string $content_type): ExtractionServiceInterface {
        // For now, return the main extraction service
        // In future phases, we can create type-specific extractors
        return new ExtractionService();
    }
    
    /**
     * Create content type-specific chunker
     * 
     * @param string $content_type Content type identifier
     * @return ChunkingServiceInterface Chunker instance
     * @throws \Exception If content type is not supported
     */
    public static function createChunker(string $content_type): ChunkingServiceInterface {
        // For now, return the main chunking service
        // In future phases, we can create type-specific chunkers
        return new ChunkingService();
    }
    
    /**
     * Detect content type from source
     * 
     * @param string $source Source URL or file path
     * @param array $metadata Additional metadata for detection
     * @return string Detected content type
     */
    public static function detectContentType(string $source, array $metadata = []): string {
        // Check if VTT file is present in metadata (indicates video content)
        if (isset($metadata['vtt_file']) && !empty($metadata['vtt_file'])) {
            return 'video';
        }
        
        // Check file extension for documents
        if (is_file($source)) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (in_array($extension, ['pdf', 'docx', 'doc', 'txt', 'md'])) {
                return 'document';
            }
            if (in_array($extension, ['vtt', 'srt'])) {
                return 'video';
            }
        }
        
        // Check URL patterns
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            // Video platform URLs
            if (preg_match('/(?:youtube\.com|youtu\.be|vimeo\.com|drive\.google\.com)/i', $source)) {
                return isset($metadata['vtt_file']) ? 'video' : 'webpage';
            }
            
            // Direct video file URLs
            if (preg_match('/\.(mp4|avi|mov|wmv|flv|webm)$/i', $source)) {
                return 'video';
            }
            
            // PDF URLs
            if (preg_match('/\.pdf$/i', $source)) {
                return 'document';
            }
            
            // Default to webpage for URLs
            return 'webpage';
        }
        
        // Default to post type
        return 'post';
    }
    
    /**
     * Get supported content types
     * 
     * @return array Array of supported content types with descriptions
     */
    public static function getSupportedTypes(): array {
        return self::CONTENT_TYPES;
    }
    
    /**
     * Validate content type
     * 
     * @param string $content_type Content type to validate
     * @return bool True if supported
     */
    public static function isValidContentType(string $content_type): bool {
        return array_key_exists($content_type, self::CONTENT_TYPES);
    }
    
    /**
     * Get content type configuration
     * 
     * @param string $content_type Content type identifier
     * @return array Configuration array for the content type
     */
    public static function getContentTypeConfig(string $content_type): array {
        $configs = [
            'video' => [
                'required_fields' => ['video_title', 'transcript_content', 'url_source'],
                'optional_fields' => ['video_cue', 'duration', 'speaker', 'video_file_url'],
                'chunking_strategy' => 'time_based',
                'file_types' => ['vtt', 'srt'],
                'max_file_size' => 10 * 1024 * 1024 // 10MB
            ],
            'document' => [
                'required_fields' => ['document_title', 'document_content', 'url_source'],
                'optional_fields' => ['file_size', 'page_count', 'author', 'creation_date'],
                'chunking_strategy' => 'text_based',
                'file_types' => ['pdf', 'docx', 'doc', 'txt', 'md'],
                'max_file_size' => 50 * 1024 * 1024 // 50MB
            ],
            'webpage' => [
                'required_fields' => ['post_title', 'post_content', 'url_source'],
                'optional_fields' => ['meta_description', 'publish_date'],
                'chunking_strategy' => 'text_based',
                'file_types' => [],
                'max_file_size' => 0
            ],
            'post' => [
                'required_fields' => ['post_title', 'post_content'],
                'optional_fields' => ['post_status', 'post_date', 'post_excerpt'],
                'chunking_strategy' => 'text_based',
                'file_types' => [],
                'max_file_size' => 0
            ]
        ];
        
        return $configs[$content_type] ?? $configs['post'];
    }
}
