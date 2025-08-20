<?php

namespace VectorBridge\MVDBIndexer\Services;

use VectorBridge\MVDBIndexer\Admin\Settings;

/**
 * Chunking Service
 * 
 * Handles intelligent content chunking with configurable overlap.
 */
class ChunkingService {
    
    /**
     * Approximate tokens per character ratio
     */
    private const TOKENS_PER_CHAR = 0.25;
    
    /**
     * Sentence boundary patterns
     */
    private const SENTENCE_BOUNDARIES = [
        '/(?<=[.!?])\s+(?=[A-Z])/',  // Period, exclamation, question mark followed by space and capital
        '/(?<=\.)\s+(?=\d)/',        // Period followed by space and number (for lists)
        '/\n\s*\n/',                 // Double newlines (paragraph breaks)
    ];
    
    /**
     * Word boundary pattern
     */
    private const WORD_BOUNDARY = '/\s+/';
    
    /**
     * Chunk content into overlapping segments
     * 
     * @param string $content Content to chunk
     * @param string $source Source identifier
     * @return array Array of chunks
     */
    public function chunkContent(string $content, string $source = ''): array {
        if (empty($content)) {
            return [];
        }
        
        // Get settings
        $chunk_size = (int) Settings::get('chunk_size', 1000);
        $overlap_percentage = (int) Settings::get('overlap_percentage', 15);
        
        // Clean and normalize content
        $content = $this->normalizeContent($content);
        
        // Calculate target character count based on token size
        $target_chars = (int) ($chunk_size / self::TOKENS_PER_CHAR);
        $overlap_chars = (int) ($target_chars * $overlap_percentage / 100);
        
        // Try different chunking strategies
        $chunks = $this->chunkBySentences($content, $target_chars, $overlap_chars);
        
        // If sentence-based chunking produces chunks that are too large, fall back to word-based
        if ($this->hasOversizedChunks($chunks, $target_chars * 1.5)) {
            $chunks = $this->chunkByWords($content, $target_chars, $overlap_chars);
        }
        
        // Format chunks with metadata
        return $this->formatChunks($chunks, $source);
    }
    
    /**
     * Normalize content for chunking
     * 
     * @param string $content Raw content
     * @return string Normalized content
     */
    private function normalizeContent(string $content): string {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Normalize line breaks
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        
        // Remove multiple consecutive newlines but preserve paragraph breaks
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Trim whitespace
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Chunk content by sentences
     * 
     * @param string $content Content to chunk
     * @param int $target_chars Target characters per chunk
     * @param int $overlap_chars Overlap characters
     * @return array Array of content chunks
     */
    private function chunkBySentences(string $content, int $target_chars, int $overlap_chars): array {
        // Split content into sentences
        $sentences = $this->splitIntoSentences($content);
        
        if (empty($sentences)) {
            return [$content];
        }
        
        $chunks = [];
        $current_chunk = '';
        $current_length = 0;
        $sentence_buffer = [];
        
        foreach ($sentences as $sentence) {
            $sentence_length = strlen($sentence);
            
            // If adding this sentence would exceed target, finalize current chunk
            if ($current_length > 0 && ($current_length + $sentence_length) > $target_chars) {
                $chunks[] = trim($current_chunk);
                
                // Start new chunk with overlap
                $overlap_content = $this->createOverlap($sentence_buffer, $overlap_chars);
                $current_chunk = $overlap_content;
                $current_length = strlen($overlap_content);
                
                // Clear old sentences, keep recent ones for potential overlap
                $sentence_buffer = array_slice($sentence_buffer, -3);
            }
            
            // Add sentence to current chunk
            if ($current_length > 0) {
                $current_chunk .= ' ';
                $current_length++;
            }
            
            $current_chunk .= $sentence;
            $current_length += $sentence_length;
            $sentence_buffer[] = $sentence;
        }
        
        // Add final chunk if it has content
        if ($current_length > 0) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }
    
    /**
     * Chunk content by words (fallback method)
     * 
     * @param string $content Content to chunk
     * @param int $target_chars Target characters per chunk
     * @param int $overlap_chars Overlap characters
     * @return array Array of content chunks
     */
    private function chunkByWords(string $content, int $target_chars, int $overlap_chars): array {
        $words = preg_split(self::WORD_BOUNDARY, $content, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words)) {
            return [$content];
        }
        
        $chunks = [];
        $current_chunk = '';
        $current_length = 0;
        $word_buffer = [];
        
        foreach ($words as $word) {
            $word_length = strlen($word) + 1; // +1 for space
            
            // If adding this word would exceed target, finalize current chunk
            if ($current_length > 0 && ($current_length + $word_length) > $target_chars) {
                $chunks[] = trim($current_chunk);
                
                // Start new chunk with overlap
                $overlap_content = $this->createWordOverlap($word_buffer, $overlap_chars);
                $current_chunk = $overlap_content;
                $current_length = strlen($overlap_content);
                
                // Keep recent words for potential overlap
                $word_buffer = array_slice($word_buffer, -20);
            }
            
            // Add word to current chunk
            if ($current_length > 0) {
                $current_chunk .= ' ';
                $current_length++;
            }
            
            $current_chunk .= $word;
            $current_length += strlen($word);
            $word_buffer[] = $word;
        }
        
        // Add final chunk if it has content
        if ($current_length > 0) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }
    
    /**
     * Split content into sentences
     * 
     * @param string $content Content to split
     * @return array Array of sentences
     */
    private function splitIntoSentences(string $content): array {
        $sentences = [];
        
        // Try each sentence boundary pattern
        foreach (self::SENTENCE_BOUNDARIES as $pattern) {
            $parts = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
            
            if (count($parts) > 1) {
                // Use the pattern that produces the most splits
                if (count($parts) > count($sentences)) {
                    $sentences = $parts;
                }
            }
        }
        
        // If no sentence boundaries found, return the whole content
        if (empty($sentences)) {
            $sentences = [$content];
        }
        
        // Clean up sentences
        return array_map('trim', array_filter($sentences, function($sentence) {
            return !empty(trim($sentence));
        }));
    }
    
    /**
     * Create overlap content from sentence buffer
     * 
     * @param array $sentence_buffer Recent sentences
     * @param int $target_chars Target overlap characters
     * @return string Overlap content
     */
    private function createOverlap(array $sentence_buffer, int $target_chars): string {
        if (empty($sentence_buffer) || $target_chars <= 0) {
            return '';
        }
        
        $overlap = '';
        $overlap_length = 0;
        
        // Add sentences from the end until we reach target overlap
        for ($i = count($sentence_buffer) - 1; $i >= 0; $i--) {
            $sentence = $sentence_buffer[$i];
            $sentence_length = strlen($sentence);
            
            if ($overlap_length + $sentence_length > $target_chars && $overlap_length > 0) {
                break;
            }
            
            if ($overlap_length > 0) {
                $overlap = ' ' . $overlap;
                $overlap_length++;
            }
            
            $overlap = $sentence . $overlap;
            $overlap_length += $sentence_length;
        }
        
        return $overlap;
    }
    
    /**
     * Create overlap content from word buffer
     * 
     * @param array $word_buffer Recent words
     * @param int $target_chars Target overlap characters
     * @return string Overlap content
     */
    private function createWordOverlap(array $word_buffer, int $target_chars): string {
        if (empty($word_buffer) || $target_chars <= 0) {
            return '';
        }
        
        $overlap_words = [];
        $overlap_length = 0;
        
        // Add words from the end until we reach target overlap
        for ($i = count($word_buffer) - 1; $i >= 0; $i--) {
            $word = $word_buffer[$i];
            $word_length = strlen($word) + 1; // +1 for space
            
            if ($overlap_length + $word_length > $target_chars && $overlap_length > 0) {
                break;
            }
            
            array_unshift($overlap_words, $word);
            $overlap_length += $word_length;
        }
        
        return implode(' ', $overlap_words);
    }
    
    /**
     * Check if chunks contain oversized content
     * 
     * @param array $chunks Content chunks
     * @param int $max_chars Maximum allowed characters
     * @return bool True if any chunk is oversized
     */
    private function hasOversizedChunks(array $chunks, int $max_chars): bool {
        foreach ($chunks as $chunk) {
            if (strlen($chunk) > $max_chars) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Format chunks with metadata
     * 
     * @param array $chunks Raw content chunks
     * @param string $source Source identifier
     * @return array Formatted chunks with metadata
     */
    private function formatChunks(array $chunks, string $source): array {
        $formatted_chunks = [];
        
        foreach ($chunks as $index => $content) {
            $formatted_chunks[] = [
                'content' => $content,
                'source' => $source,
                'chunk_index' => $index,
                'character_count' => strlen($content),
                'estimated_tokens' => (int) (strlen($content) * self::TOKENS_PER_CHAR),
                'created_at' => current_time('mysql')
            ];
        }
        
        return $formatted_chunks;
    }
    
    /**
     * Get chunking statistics for content
     * 
     * @param string $content Content to analyze
     * @return array Chunking statistics
     */
    public function getChunkingStats(string $content): array {
        if (empty($content)) {
            return [
                'total_characters' => 0,
                'estimated_tokens' => 0,
                'estimated_chunks' => 0,
                'chunk_size' => Settings::get('chunk_size', 1000),
                'overlap_percentage' => Settings::get('overlap_percentage', 15)
            ];
        }
        
        $chunk_size = (int) Settings::get('chunk_size', 1000);
        $target_chars = (int) ($chunk_size / self::TOKENS_PER_CHAR);
        
        $total_chars = strlen($content);
        $estimated_tokens = (int) ($total_chars * self::TOKENS_PER_CHAR);
        $estimated_chunks = max(1, (int) ceil($total_chars / $target_chars));
        
        return [
            'total_characters' => $total_chars,
            'estimated_tokens' => $estimated_tokens,
            'estimated_chunks' => $estimated_chunks,
            'chunk_size' => $chunk_size,
            'overlap_percentage' => Settings::get('overlap_percentage', 15),
            'target_chars_per_chunk' => $target_chars
        ];
    }
    
    /**
     * Validate chunking configuration
     * 
     * @return array Validation results
     */
    public function validateConfiguration(): array {
        $chunk_size = (int) Settings::get('chunk_size', 1000);
        $overlap_percentage = (int) Settings::get('overlap_percentage', 15);
        
        $issues = [];
        $warnings = [];
        
        // Check chunk size
        if ($chunk_size < 100) {
            $issues[] = __('Chunk size is too small (minimum 100 tokens)', 'vector-bridge-mvdb-indexer');
        } elseif ($chunk_size > 5000) {
            $issues[] = __('Chunk size is too large (maximum 5000 tokens)', 'vector-bridge-mvdb-indexer');
        } elseif ($chunk_size > 2000) {
            $warnings[] = __('Large chunk sizes may exceed some model context limits', 'vector-bridge-mvdb-indexer');
        }
        
        // Check overlap percentage
        if ($overlap_percentage < 0) {
            $issues[] = __('Overlap percentage cannot be negative', 'vector-bridge-mvdb-indexer');
        } elseif ($overlap_percentage > 50) {
            $issues[] = __('Overlap percentage is too high (maximum 50%)', 'vector-bridge-mvdb-indexer');
        } elseif ($overlap_percentage > 25) {
            $warnings[] = __('High overlap percentages increase storage requirements', 'vector-bridge-mvdb-indexer');
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'configuration' => [
                'chunk_size' => $chunk_size,
                'overlap_percentage' => $overlap_percentage,
                'target_chars_per_chunk' => (int) ($chunk_size / self::TOKENS_PER_CHAR)
            ]
        ];
    }
}
