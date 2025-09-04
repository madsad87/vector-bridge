<?php

namespace VectorBridge\MVDBIndexer\Services;

/**
 * VTT Extraction Service
 * 
 * Handles parsing and processing of VTT (WebVTT) subtitle files.
 * Extracts timestamped text segments for video content indexing.
 */
class VttExtractionService {
    
    /**
     * VTT timestamp pattern
     */
    private const TIMESTAMP_PATTERN = '/(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})/';
    
    /**
     * Parse VTT file and extract timestamped segments
     * 
     * @param string $file_path Path to VTT file
     * @return array Array of timestamped segments
     * @throws \Exception If file cannot be parsed
     */
    public function parseVttFile(string $file_path): array {
        if (!file_exists($file_path)) {
            throw new \Exception(__('VTT file not found', 'vector-bridge-mvdb-indexer'));
        }
        
        if (!is_readable($file_path)) {
            throw new \Exception(__('VTT file is not readable', 'vector-bridge-mvdb-indexer'));
        }
        
        $content = file_get_contents($file_path);
        if ($content === false) {
            throw new \Exception(__('Failed to read VTT file', 'vector-bridge-mvdb-indexer'));
        }
        
        return $this->parseVttContent($content);
    }
    
    /**
     * Parse VTT content string
     * 
     * @param string $vtt_content VTT file content
     * @return array Array of timestamped segments
     * @throws \Exception If content cannot be parsed
     */
    public function parseVttContent(string $vtt_content): array {
        if (empty($vtt_content)) {
            throw new \Exception(__('VTT content is empty', 'vector-bridge-mvdb-indexer'));
        }
        
        // Validate VTT format
        if (!$this->validateVttFormat($vtt_content)) {
            throw new \Exception(__('Invalid VTT format', 'vector-bridge-mvdb-indexer'));
        }
        
        // Normalize line endings
        $vtt_content = preg_replace('/\r\n|\r/', "\n", $vtt_content);
        
        // Split into blocks
        $blocks = preg_split('/\n\s*\n/', $vtt_content);
        $segments = [];
        
        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }
            
            // Skip WEBVTT header and NOTE blocks
            if (preg_match('/^(WEBVTT|NOTE)/i', $block)) {
                continue;
            }
            
            $segment = $this->parseVttBlock($block);
            if ($segment) {
                $segments[] = $segment;
            }
        }
        
        return $segments;
    }
    
    /**
     * Extract timestamps from VTT content
     * 
     * @param string $vtt_content VTT content
     * @return array Array of timestamp ranges
     */
    public function extractTimestamps(string $vtt_content): array {
        $timestamps = [];
        
        if (preg_match_all(self::TIMESTAMP_PATTERN, $vtt_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $timestamps[] = [
                    'start' => $this->parseTimestamp($match[1]),
                    'end' => $this->parseTimestamp($match[2]),
                    'start_formatted' => $match[1],
                    'end_formatted' => $match[2],
                    'cue' => $match[0]
                ];
            }
        }
        
        return $timestamps;
    }
    
    /**
     * Chunk VTT segments by time duration or content length
     * 
     * @param array $vtt_segments Parsed VTT segments
     * @param array $options Chunking options
     * @return array Chunked segments
     */
    public function chunkByTimeSegments(array $vtt_segments, array $options = []): array {
        $chunk_duration = $options['chunk_duration'] ?? 60; // 60 seconds default
        $max_chunk_size = $options['max_chunk_size'] ?? 1000; // 1000 characters default
        $overlap_duration = $options['overlap_duration'] ?? 5; // 5 seconds overlap
        
        $chunks = [];
        $current_chunk = [];
        $current_start_time = null;
        $current_content = '';
        
        foreach ($vtt_segments as $segment) {
            // Initialize first chunk
            if ($current_start_time === null) {
                $current_start_time = $segment['start_time'];
            }
            
            // Check if we should start a new chunk
            $duration = $segment['start_time'] - $current_start_time;
            $content_length = strlen($current_content . ' ' . $segment['text']);
            
            if ($duration >= $chunk_duration || $content_length >= $max_chunk_size) {
                // Finalize current chunk
                if (!empty($current_chunk)) {
                    $chunks[] = $this->createTimeBasedChunk($current_chunk, $current_content);
                }
                
                // Start new chunk with overlap
                $overlap_segments = $this->getOverlapSegments($current_chunk, $overlap_duration);
                $current_chunk = $overlap_segments;
                $current_start_time = $segment['start_time'];
                $current_content = implode(' ', array_column($overlap_segments, 'text'));
            }
            
            // Add segment to current chunk
            $current_chunk[] = $segment;
            $current_content .= ' ' . $segment['text'];
        }
        
        // Add final chunk
        if (!empty($current_chunk)) {
            $chunks[] = $this->createTimeBasedChunk($current_chunk, $current_content);
        }
        
        return $chunks;
    }
    
    /**
     * Validate VTT file format
     * 
     * @param string $content VTT content to validate
     * @return bool True if valid VTT format
     */
    public function validateVttFormat(string $content): bool {
        // Check for WEBVTT header
        if (!preg_match('/^WEBVTT/i', trim($content))) {
            return false;
        }
        
        // Check for at least one timestamp
        if (!preg_match(self::TIMESTAMP_PATTERN, $content)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Parse individual VTT block
     * 
     * @param string $block VTT block content
     * @return array|null Parsed segment or null if invalid
     */
    private function parseVttBlock(string $block): ?array {
        $lines = explode("\n", $block);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // Remove empty lines
        
        if (count($lines) < 2) {
            return null;
        }
        
        $timestamp_line = '';
        $text_lines = [];
        $cue_id = null;
        
        // Check if first line is a cue ID (optional)
        if (!preg_match(self::TIMESTAMP_PATTERN, $lines[0])) {
            $cue_id = array_shift($lines);
        }
        
        // Next line should be timestamp
        if (!empty($lines)) {
            $timestamp_line = array_shift($lines);
        }
        
        // Remaining lines are text
        $text_lines = $lines;
        
        // Parse timestamp
        if (!preg_match(self::TIMESTAMP_PATTERN, $timestamp_line, $matches)) {
            return null;
        }
        
        $start_time = $this->parseTimestamp($matches[1]);
        $end_time = $this->parseTimestamp($matches[2]);
        $text = implode(' ', $text_lines);
        
        // Clean up text (remove cue settings)
        $text = $this->cleanVttText($text);
        
        return [
            'cue_id' => $cue_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'start_formatted' => $matches[1],
            'end_formatted' => $matches[2],
            'duration' => $end_time - $start_time,
            'text' => $text,
            'cue' => $matches[0]
        ];
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
     * Format seconds to VTT timestamp
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
     * Clean VTT text content
     * 
     * @param string $text Raw VTT text
     * @return string Cleaned text
     */
    private function cleanVttText(string $text): string {
        // Remove cue settings (position, align, etc.)
        $text = preg_replace('/\s+align:\w+|\s+position:\d+%|\s+size:\d+%/', '', $text);
        
        // Remove HTML-like tags
        $text = preg_replace('/<[^>]+>/', '', $text);
        
        // Remove speaker labels in format "Speaker: text"
        $text = preg_replace('/^[A-Za-z\s]+:\s*/', '', $text);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Create time-based chunk from segments
     * 
     * @param array $segments Array of VTT segments
     * @param string $content Combined content
     * @return array Chunk data
     */
    private function createTimeBasedChunk(array $segments, string $content): array {
        if (empty($segments)) {
            return [];
        }
        
        $first_segment = reset($segments);
        $last_segment = end($segments);
        
        return [
            'content' => trim($content),
            'start_time' => $first_segment['start_time'],
            'end_time' => $last_segment['end_time'],
            'video_cue' => $first_segment['start_formatted'] . ' --> ' . $last_segment['end_formatted'],
            'duration' => $last_segment['end_time'] - $first_segment['start_time'],
            'segment_count' => count($segments),
            'segments' => $segments
        ];
    }
    
    /**
     * Get overlap segments for chunk continuity
     * 
     * @param array $segments Current chunk segments
     * @param float $overlap_duration Overlap duration in seconds
     * @return array Overlap segments
     */
    private function getOverlapSegments(array $segments, float $overlap_duration): array {
        if (empty($segments) || $overlap_duration <= 0) {
            return [];
        }
        
        $last_segment = end($segments);
        $cutoff_time = $last_segment['end_time'] - $overlap_duration;
        
        $overlap_segments = [];
        foreach (array_reverse($segments) as $segment) {
            if ($segment['start_time'] >= $cutoff_time) {
                array_unshift($overlap_segments, $segment);
            } else {
                break;
            }
        }
        
        return $overlap_segments;
    }
    
    /**
     * Extract speaker information from VTT content
     * 
     * @param array $segments VTT segments
     * @return array Speaker information
     */
    public function extractSpeakers(array $segments): array {
        $speakers = [];
        
        foreach ($segments as $segment) {
            $text = $segment['text'];
            
            // Look for speaker patterns: "Speaker Name: text"
            if (preg_match('/^([A-Za-z\s]+):\s*(.+)/', $text, $matches)) {
                $speaker_name = trim($matches[1]);
                if (!in_array($speaker_name, $speakers)) {
                    $speakers[] = $speaker_name;
                }
            }
        }
        
        return $speakers;
    }
    
    /**
     * Get VTT file statistics
     * 
     * @param array $segments Parsed VTT segments
     * @return array Statistics
     */
    public function getVttStatistics(array $segments): array {
        if (empty($segments)) {
            return [
                'total_segments' => 0,
                'total_duration' => 0,
                'total_words' => 0,
                'average_segment_duration' => 0,
                'speakers' => []
            ];
        }
        
        $total_duration = 0;
        $total_words = 0;
        $speakers = $this->extractSpeakers($segments);
        
        foreach ($segments as $segment) {
            $total_duration += $segment['duration'];
            $total_words += str_word_count($segment['text']);
        }
        
        return [
            'total_segments' => count($segments),
            'total_duration' => $total_duration,
            'total_words' => $total_words,
            'average_segment_duration' => $total_duration / count($segments),
            'speakers' => $speakers,
            'words_per_minute' => $total_duration > 0 ? ($total_words / ($total_duration / 60)) : 0
        ];
    }
}
