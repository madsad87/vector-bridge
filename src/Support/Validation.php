<?php

namespace VectorBridge\MVDBIndexer\Support;

/**
 * Shared Validation & Utility Functions
 *
 * Centralizes sanitization, validation, and masking logic used
 * across controllers and services. Eliminates duplicate implementations
 * of token masking, content normalization, timestamp parsing, etc.
 */
class Validation {

    /**
     * Validate that a string value is non-empty after sanitization
     *
     * @param string $value Value to validate
     * @param string $field_name Human-readable field name for error messages
     * @return string Sanitized value
     * @throws \InvalidArgumentException If value is empty
     */
    public static function requireNonEmpty(string $value, string $field_name): string {
        $value = sanitize_text_field($value);
        if (empty($value)) {
            throw new \InvalidArgumentException(
                sprintf(__('%s is required.', 'vector-bridge-mvdb-indexer'), $field_name)
            );
        }
        return $value;
    }

    /**
     * Validate that a URL is non-empty after sanitization
     *
     * @param string $url URL to validate
     * @param string $field_name Human-readable field name
     * @return string Sanitized URL
     * @throws \InvalidArgumentException If URL is empty
     */
    public static function requireUrl(string $url, string $field_name = 'URL'): string {
        $url = sanitize_url($url);
        if (empty($url)) {
            throw new \InvalidArgumentException(
                sprintf(__('%s is required.', 'vector-bridge-mvdb-indexer'), $field_name)
            );
        }
        return $url;
    }

    /**
     * Validate that a file upload exists
     *
     * @param string $files_key Key in $_FILES
     * @return array The $_FILES entry
     * @throws \InvalidArgumentException If no file uploaded
     */
    public static function requireFile(string $files_key): array {
        if (empty($_FILES[$files_key])) {
            throw new \InvalidArgumentException(
                __('No file uploaded.', 'vector-bridge-mvdb-indexer')
            );
        }
        return $_FILES[$files_key];
    }

    /**
     * Clamp an integer to a range
     *
     * @param int $value Value to clamp
     * @param int $min Minimum
     * @param int $max Maximum
     * @return int Clamped value
     */
    public static function clampInt(int $value, int $min, int $max): int {
        return max($min, min($max, $value));
    }

    /**
     * Clamp a float to a range
     *
     * @param float $value Value to clamp
     * @param float $min Minimum
     * @param float $max Maximum
     * @return float Clamped value
     */
    public static function clampFloat(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }

    /**
     * Mask a token/secret for display
     *
     * Shows the first 6 characters and masks the rest.
     * Replaces duplicate implementations in Settings.php and MVDBService.php.
     *
     * @param string $token Token to mask
     * @return string Masked token
     */
    public static function maskToken(string $token): string {
        if (empty($token)) {
            return '';
        }

        if (strlen($token) <= 6) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
    }

    /**
     * Mask an endpoint URL for logging
     *
     * Preserves the scheme and partially masks the hostname.
     * Replaces duplicate implementation in MVDBService.php.
     *
     * @param string $endpoint Endpoint URL
     * @return string Masked endpoint
     */
    public static function maskEndpoint(string $endpoint): string {
        if (empty($endpoint)) {
            return '***';
        }

        $parsed = parse_url($endpoint);

        if (!$parsed || !isset($parsed['host'])) {
            return '***';
        }

        $host = $parsed['host'];
        $scheme = $parsed['scheme'] ?? 'https';
        $path = $parsed['path'] ?? '';

        $host_parts = explode('.', $host);
        if (count($host_parts) > 2) {
            $host_parts[0] = substr($host_parts[0], 0, 3) . '***';
        }

        return $scheme . '://' . implode('.', $host_parts) . $path;
    }

    /**
     * Normalize content for processing
     *
     * Replaces duplicate normalization in ChunkingService, ExtractionService,
     * and DefaultContentBuilder.
     *
     * @param string $content Raw content
     * @return string Normalized content
     */
    public static function normalizeContent(string $content): string {
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return trim($content);
    }

    /**
     * Format seconds into HH:MM:SS.mmm timestamp
     *
     * Replaces duplicate implementations in VttExtractionService and VideoContentBuilder.
     *
     * @param float $seconds Seconds to format
     * @return string Formatted timestamp
     */
    public static function formatTimestamp(float $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }

    /**
     * Parse a timestamp string into seconds
     *
     * Handles HH:MM:SS.mmm, MM:SS.mmm, and plain seconds formats.
     * Replaces duplicate implementations in VttExtractionService and VideoContentBuilder.
     *
     * @param string $timestamp Timestamp string
     * @return float Seconds
     */
    public static function parseTimestamp(string $timestamp): float {
        $parts = explode(':', $timestamp);
        if (count($parts) === 3) {
            return (float) $parts[0] * 3600 + (float) $parts[1] * 60 + (float) $parts[2];
        }
        if (count($parts) === 2) {
            return (float) $parts[0] * 60 + (float) $parts[1];
        }
        return (float) $timestamp;
    }
}
