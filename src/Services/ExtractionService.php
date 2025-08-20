<?php

namespace VectorBridge\MVDBIndexer\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use League\HTMLToMarkdown\HtmlConverter;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

/**
 * Extraction Service
 * 
 * Handles content extraction from various sources (URLs, files).
 */
class ExtractionService {
    
    /**
     * HTTP client
     * 
     * @var Client
     */
    private Client $client;
    
    /**
     * HTML to Markdown converter
     * 
     * @var HtmlConverter
     */
    private HtmlConverter $htmlConverter;
    
    /**
     * PDF parser
     * 
     * @var PdfParser
     */
    private PdfParser $pdfParser;
    
    /**
     * Supported file types
     */
    private const SUPPORTED_TYPES = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'txt' => ['text/plain'],
        'md' => ['text/markdown', 'text/x-markdown']
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Vector-Bridge-MVDB-Indexer/' . VECTOR_BRIDGE_VERSION . ' (WordPress)'
            ]
        ]);
        
        $this->htmlConverter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style nav footer header aside',
            'preserve_comments' => false
        ]);
        
        $this->pdfParser = new PdfParser();
        
        // Configure PhpWord
        Settings::setOutputEscapingEnabled(true);
    }
    
    /**
     * Extract content from URL
     * 
     * @param string $url URL to extract from
     * @return string Extracted content
     * @throws \Exception If extraction fails
     */
    public function extractFromUrl(string $url): string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception(__('Invalid URL provided', 'vector-bridge-mvdb-indexer'));
        }
        
        // Check robots.txt compliance
        if (!$this->isUrlAllowed($url)) {
            throw new \Exception(__('URL is disallowed by robots.txt', 'vector-bridge-mvdb-indexer'));
        }
        
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ]
            ]);
            
            $content_type = $response->getHeaderLine('Content-Type');
            $html = $response->getBody()->getContents();
            
            if (empty($html)) {
                throw new \Exception(__('No content found at URL', 'vector-bridge-mvdb-indexer'));
            }
            
            // Extract and clean HTML content
            $cleaned_content = $this->extractFromHtml($html);
            
            if (empty(trim($cleaned_content))) {
                throw new \Exception(__('No extractable text content found', 'vector-bridge-mvdb-indexer'));
            }
            
            return $cleaned_content;
            
        } catch (RequestException $e) {
            throw new \Exception(sprintf(
                __('Failed to fetch URL: %s', 'vector-bridge-mvdb-indexer'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Extract content from file
     * 
     * @param string $file_path Path to file
     * @return string Extracted content
     * @throws \Exception If extraction fails
     */
    public function extractFromFile(string $file_path): string {
        if (!file_exists($file_path)) {
            throw new \Exception(__('File not found', 'vector-bridge-mvdb-indexer'));
        }
        
        if (!is_readable($file_path)) {
            throw new \Exception(__('File is not readable', 'vector-bridge-mvdb-indexer'));
        }
        
        // Detect file type
        $mime_type = mime_content_type($file_path);
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Extract based on file type
        switch ($extension) {
            case 'pdf':
                return $this->extractFromPdf($file_path);
            
            case 'docx':
                return $this->extractFromDocx($file_path);
            
            case 'txt':
                return $this->extractFromTxt($file_path);
            
            case 'md':
                return $this->extractFromMarkdown($file_path);
            
            default:
                throw new \Exception(sprintf(
                    __('Unsupported file type: %s', 'vector-bridge-mvdb-indexer'),
                    $extension
                ));
        }
    }
    
    /**
     * Extract content from HTML
     * 
     * @param string $html HTML content
     * @return string Extracted text content
     */
    public function extractFromHtml(string $html): string {
        if (empty($html)) {
            return '';
        }
        
        // Remove script and style tags completely
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Convert HTML to Markdown for better structure preservation
        $markdown = $this->htmlConverter->convert($html);
        
        // Clean up the markdown
        $cleaned = $this->cleanMarkdown($markdown);
        
        return $cleaned;
    }
    
    /**
     * Extract content from text (passthrough with cleaning)
     * 
     * @param string $text Text content
     * @return string Cleaned text content
     */
    public function extractFromText(string $text): string {
        return $this->cleanText($text);
    }
    
    /**
     * Extract content from PDF file
     * 
     * @param string $file_path Path to PDF file
     * @return string Extracted text content
     * @throws \Exception If extraction fails
     */
    private function extractFromPdf(string $file_path): string {
        try {
            $pdf = $this->pdfParser->parseFile($file_path);
            $text = $pdf->getText();
            
            if (empty(trim($text))) {
                throw new \Exception(__('No text content found in PDF', 'vector-bridge-mvdb-indexer'));
            }
            
            return $this->cleanText($text);
            
        } catch (\Exception $e) {
            throw new \Exception(sprintf(
                __('Failed to extract PDF content: %s', 'vector-bridge-mvdb-indexer'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Extract content from DOCX file
     * 
     * @param string $file_path Path to DOCX file
     * @return string Extracted text content
     * @throws \Exception If extraction fails
     */
    private function extractFromDocx(string $file_path): string {
        try {
            $phpWord = IOFactory::load($file_path);
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        // Handle nested elements (paragraphs, etc.)
                        $text .= $this->extractFromWordElement($element) . "\n";
                    }
                }
            }
            
            if (empty(trim($text))) {
                throw new \Exception(__('No text content found in DOCX', 'vector-bridge-mvdb-indexer'));
            }
            
            return $this->cleanText($text);
            
        } catch (\Exception $e) {
            throw new \Exception(sprintf(
                __('Failed to extract DOCX content: %s', 'vector-bridge-mvdb-indexer'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Extract text from Word element recursively
     * 
     * @param mixed $element Word element
     * @return string Extracted text
     */
    private function extractFromWordElement($element): string {
        $text = '';
        
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }
        
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractFromWordElement($child) . ' ';
            }
        }
        
        return $text;
    }
    
    /**
     * Extract content from text file
     * 
     * @param string $file_path Path to text file
     * @return string File content
     * @throws \Exception If extraction fails
     */
    private function extractFromTxt(string $file_path): string {
        $content = file_get_contents($file_path);
        
        if ($content === false) {
            throw new \Exception(__('Failed to read text file', 'vector-bridge-mvdb-indexer'));
        }
        
        if (empty(trim($content))) {
            throw new \Exception(__('Text file is empty', 'vector-bridge-mvdb-indexer'));
        }
        
        return $this->cleanText($content);
    }
    
    /**
     * Extract content from Markdown file
     * 
     * @param string $file_path Path to Markdown file
     * @return string Cleaned content
     * @throws \Exception If extraction fails
     */
    private function extractFromMarkdown(string $file_path): string {
        $content = file_get_contents($file_path);
        
        if ($content === false) {
            throw new \Exception(__('Failed to read Markdown file', 'vector-bridge-mvdb-indexer'));
        }
        
        if (empty(trim($content))) {
            throw new \Exception(__('Markdown file is empty', 'vector-bridge-mvdb-indexer'));
        }
        
        return $this->cleanMarkdown($content);
    }
    
    /**
     * Clean markdown content
     * 
     * @param string $markdown Markdown content
     * @return string Cleaned content
     */
    private function cleanMarkdown(string $markdown): string {
        // Remove excessive markdown formatting while preserving structure
        $cleaned = $markdown;
        
        // Remove multiple consecutive newlines
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        
        // Clean up list formatting
        $cleaned = preg_replace('/^\s*[\*\-\+]\s+/m', '• ', $cleaned);
        $cleaned = preg_replace('/^\s*\d+\.\s+/m', '', $cleaned);
        
        // Remove excessive whitespace
        $cleaned = preg_replace('/[ \t]+/', ' ', $cleaned);
        
        // Trim and normalize
        return trim($cleaned);
    }
    
    /**
     * Clean text content
     * 
     * @param string $text Raw text
     * @return string Cleaned text
     */
    private function cleanText(string $text): string {
        // Normalize line endings
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remove multiple consecutive newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Trim and return
        return trim($text);
    }
    
    /**
     * Check if URL is allowed by robots.txt
     * 
     * @param string $url URL to check
     * @return bool True if allowed
     */
    private function isUrlAllowed(string $url): bool {
        try {
            $parsed_url = parse_url($url);
            if (!$parsed_url || !isset($parsed_url['host'])) {
                return false;
            }
            
            $robots_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/robots.txt';
            
            $response = $this->client->get($robots_url, [
                'timeout' => 10,
                'http_errors' => false
            ]);
            
            if ($response->getStatusCode() !== 200) {
                // If robots.txt doesn't exist, assume allowed
                return true;
            }
            
            $robots_content = $response->getBody()->getContents();
            return $this->parseRobotsTxt($robots_content, $url);
            
        } catch (\Exception $e) {
            // If we can't check robots.txt, assume allowed
            return true;
        }
    }
    
    /**
     * Parse robots.txt content
     * 
     * @param string $robots_content Robots.txt content
     * @param string $url URL to check
     * @return bool True if allowed
     */
    private function parseRobotsTxt(string $robots_content, string $url): bool {
        $lines = explode("\n", $robots_content);
        $user_agent_match = false;
        $disallowed_paths = [];
        
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $agent = trim($matches[1]);
                $user_agent_match = ($agent === '*' || stripos('Vector-Bridge-MVDB-Indexer', $agent) !== false);
                continue;
            }
            
            if ($user_agent_match && preg_match('/^Disallow:\s*(.+)$/i', $line, $matches)) {
                $disallowed_path = trim($matches[1]);
                if (!empty($disallowed_path)) {
                    $disallowed_paths[] = $disallowed_path;
                }
            }
        }
        
        // Check if current path is disallowed
        foreach ($disallowed_paths as $disallowed_path) {
            if ($disallowed_path === '/') {
                return false; // Entire site disallowed
            }
            
            if (strpos($path, $disallowed_path) === 0) {
                return false; // Path matches disallowed pattern
            }
        }
        
        return true;
    }
    
    /**
     * Get supported file types
     * 
     * @return array Supported file types and MIME types
     */
    public function getSupportedTypes(): array {
        return self::SUPPORTED_TYPES;
    }
    
    /**
     * Validate file type
     * 
     * @param string $file_path File path
     * @return bool True if supported
     */
    public function isFileTypeSupported(string $file_path): bool {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return array_key_exists($extension, self::SUPPORTED_TYPES);
    }
    
    /**
     * Get extraction statistics
     * 
     * @param string $content Extracted content
     * @return array Extraction statistics
     */
    public function getExtractionStats(string $content): array {
        return [
            'character_count' => strlen($content),
            'word_count' => str_word_count($content),
            'line_count' => substr_count($content, "\n") + 1,
            'paragraph_count' => substr_count($content, "\n\n") + 1,
            'estimated_reading_time' => max(1, (int) ceil(str_word_count($content) / 200)) // 200 WPM average
        ];
    }
}
