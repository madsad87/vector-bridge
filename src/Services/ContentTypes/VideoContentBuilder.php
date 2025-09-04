<?php

namespace VectorBridge\MVDBIndexer\Services\ContentTypes;

use VectorBridge\MVDBIndexer\Services\ContentTypeBuilderInterface;

/**
 * Video Content Builder
 * 
 * Builds document data structures for video content with VTT transcripts.
 * Handles video-specific fields like timestamps, speakers, and video URLs.
 */
class VideoContentBuilder implements ContentTypeBuilderInterface {
    
    /**
     * Build document data structure for video content
     * 
     * @param array $chunk Content chunk with metadata
     * @param string $collection Collection name
     * @param array $metadata Additional metadata for building
     * @return array Document data structure ready for MVDB
     */
    public function buildDocumentData(array $chunk, string $collection, array $metadata = []): array {
        // Base data structure
        $data = [
            'post_type' => 'video',
            'url_source' => $metadata['url_source'] ?? $chunk['source'] ?? '',
            'chunk_index' => $chunk['chunk_index'] ?? 0,
            'indexed_by' => 'vector-bridge',
            'wordpress_site' => get_site_url(),
            'tenant' => \VectorBridge\MVDBIndexer\Admin\Settings::get('tenant', '')
        ];
        
        // Video-specific fields
        $data['video_title'] = $this->extractTitle($chunk, $metadata);
        $data['transcript_content'] = $chunk['content'] ?? '';
        
        // Add video cue if available (timestamp information)
        if (isset($chunk['video_cue'])) {
            $data['video_cue'] = $chunk['video_cue'];
        } elseif (isset($chunk['start_time']) && isset($chunk['end_time'])) {
            $data['video_cue'] = $this->formatTimestamp($chunk['start_time']) . ' --> ' . $this->formatTimestamp($chunk['end_time']);
        }
        
        // Optional video metadata
        if (isset($metadata['duration'])) {
            $data['duration'] = (int) $metadata['duration'];
        }
        
        if (isset($metadata['speaker']) && !empty($metadata['speaker'])) {
            $data['speaker'] = sanitize_text_field($metadata['speaker']);
        }
        
        if (isset($metadata['video_file_url']) && !empty($metadata['video_file_url'])) {
            $data['video_file_url'] = esc_url_raw($metadata['video_file_url']);
        }
        
        if (isset($metadata['description']) && !empty($metadata['description'])) {
            $data['description'] = sanitize_textarea_field($metadata['description']);
        }
        
        // Add timestamp for indexing
        $data['indexed_at'] = current_time('c');
        
        return $data;
    }
    
    /**
     * Get required fields for video content type
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array {
        return ['video_title', 'transcript_content', 'url_source'];
    }
    
    /**
     * Get optional fields for video content type
     * 
     * @return array Array of optional field names
     */
    public function getOptionalFields(): array {
        return ['video_cue', 'duration', 'speaker', 'video_file_url', 'description'];
    }
    
    /**
     * Validate chunk data for video content type
     * 
     * @param array $chunk Content chunk to validate
     * @param array $metadata Additional metadata
     * @return bool True if valid
     * @throws \Exception If validation fails
     */
    public function validateChunkData(array $chunk, array $metadata = []): bool {
        // Check required content
        if (empty($chunk['content'])) {
            throw new \Exception(__('Video transcript content is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Check URL source
        $url_source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (empty($url_source)) {
            throw new \Exception(__('Video URL source is required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate URL format if provided
        if (!empty($url_source) && !filter_var($url_source, FILTER_VALIDATE_URL)) {
            throw new \Exception(__('Invalid video URL format', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate video cue format if provided
        if (isset($chunk['video_cue']) && !$this->isValidTimestamp($chunk['video_cue'])) {
            throw new \Exception(__('Invalid video cue timestamp format', 'vector-bridge-mvdb-indexer'));
        }
        
        return true;
    }
    
    /**
     * Get content type identifier
     * 
     * @return string Content type identifier
     */
    public function getContentType(): string {
        return 'video';
    }
    
    /**
     * Extract title from video chunk content
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
        
        if (isset($metadata['video_title']) && !empty($metadata['video_title'])) {
            return sanitize_text_field($metadata['video_title']);
        }
        
        // Extract from content (first meaningful line)
        $content = $chunk['content'] ?? '';
        if (!empty($content)) {
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 10 && strlen($line) < 100) {
                    return $line;
                }
            }
        }
        
        // Generate title from URL source
        $url_source = $metadata['url_source'] ?? $chunk['source'] ?? '';
        if (!empty($url_source)) {
            // Extract from YouTube URL
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url_source, $matches)) {
                return 'YouTube Video: ' . $matches[1];
            }
            
            // Extract from Vimeo URL
            if (preg_match('/vimeo\.com\/(\d+)/', $url_source, $matches)) {
                return 'Vimeo Video: ' . $matches[1];
            }
            
            // Extract from filename
            $filename = basename(parse_url($url_source, PHP_URL_PATH));
            if (!empty($filename)) {
                return pathinfo($filename, PATHINFO_FILENAME);
            }
        }
        
        // Fallback title with timestamp
        $timestamp = isset($chunk['video_cue']) ? ' (' . $chunk['video_cue'] . ')' : '';
        return 'Video Transcript' . $timestamp;
    }
    
    /**
     * Process video content before chunking
     * 
     * @param string $content Raw VTT content
     * @param array $metadata Additional metadata
     * @return string Processed content
     */
    public function preprocessContent(string $content, array $metadata = []): string {
        // Remove VTT header if present
        $content = preg_replace('/^WEBVTT\s*\n/i', '', $content);
        
        // Remove timestamp lines (they'll be preserved in chunk metadata)
        $content = preg_replace('/^\d{2}:\d{2}:\d{2}\.\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}\.\d{3}\s*$/m', '', $content);
        
        // Remove cue settings
        $content = preg_replace('/\s+align:\w+|\s+position:\d+%|\s+size:\d+%/', '', $content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Format timestamp for VTT format
     * 
     * @param float $seconds Timestamp in seconds
     * @return string Formatted timestamp (HH:MM:SS.mmm)
     */
    private function formatTimestamp(float $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }
    
    /**
     * Validate timestamp format
     * 
     * @param string $timestamp Timestamp string to validate
     * @return bool True if valid VTT timestamp format
     */
    private function isValidTimestamp(string $timestamp): bool {
        // VTT timestamp format: HH:MM:SS.mmm --> HH:MM:SS.mmm
        $pattern = '/^\d{2}:\d{2}:\d{2}\.\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}\.\d{3}$/';
        return preg_match($pattern, $timestamp) === 1;
    }
    
    /**
     * Parse VTT timestamp to seconds
     * 
     * @param string $timestamp VTT timestamp (HH:MM:SS.mmm)
     * @return float Timestamp in seconds
     */
    private function parseTimestamp(string $timestamp): float {
        if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $timestamp, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];
            $milliseconds = (int) $matches[4];
            
            return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
        }
        
        return 0.0;
    }
    
    /**
     * Extract video platform information from URL
     * 
     * @param string $url Video URL
     * @return array Platform information
     */
    private function extractPlatformInfo(string $url): array {
        $info = ['platform' => 'unknown', 'id' => ''];
        
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $info['platform'] = 'youtube';
            $info['id'] = $matches[1];
        }
        // Vimeo
        elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            $info['platform'] = 'vimeo';
            $info['id'] = $matches[1];
        }
        // Google Drive
        elseif (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $info['platform'] = 'google_drive';
            $info['id'] = $matches[1];
        }
        
        return $info;
    }
}
