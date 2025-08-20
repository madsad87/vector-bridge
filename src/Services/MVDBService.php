<?php

namespace VectorBridge\MVDBIndexer\Services;

use VectorBridge\MVDBIndexer\Admin\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * MVDB Service
 * 
 * Handles communication with WP Engine's Managed Vector Database via GraphQL.
 * Uses the correct API structure based on working RAG examples.
 */
class MVDBService {
    
    /**
     * HTTP client
     * 
     * @var Client
     */
    private Client $client;
    
    /**
     * Rate limiter state
     * 
     * @var array
     */
    private array $rateLimiter = [
        'last_request' => 0,
        'request_count' => 0
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Vector-Bridge-MVDB-Indexer/' . VECTOR_BRIDGE_VERSION
            ]
        ]);
    }
    
    /**
     * Validate connection to MVDB
     * 
     * @return array Connection validation result
     * @throws \Exception If connection fails
     */
    public function validateConnection(): array {
        $endpoint = Settings::get('mvdb_endpoint');
        $token = Settings::get('mvdb_token');
        
        if (empty($endpoint) || empty($token)) {
            throw new \Exception(__('MVDB endpoint and token are required', 'vector-bridge-mvdb-indexer'));
        }
        
        // Simple introspection query to test connection
        $query = '
            query {
                __schema {
                    queryType {
                        name
                    }
                }
            }
        ';
        
        try {
            $response = $this->executeQuery($query);
            
            return [
                'status' => 'connected',
                'endpoint' => $this->maskEndpoint($endpoint),
                'schema_available' => isset($response['data']['__schema']),
                'timestamp' => current_time('mysql')
            ];
        } catch (\Exception $e) {
            throw new \Exception(sprintf(
                __('Connection failed: %s', 'vector-bridge-mvdb-indexer'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Index chunks into MVDB using correct WP Engine API
     * 
     * @param array $chunks Content chunks to index
     * @param string $collection Collection name (used as post_type)
     * @param string $source Source URL or filename
     * @return array Indexing results
     * @throws \Exception If indexing fails
     */
    public function indexChunks(array $chunks, string $collection, string $source = ''): array {
        if (empty($chunks)) {
            return ['indexed' => 0, 'errors' => []];
        }
        
        $batch_size = (int) Settings::get('batch_size', 100);
        $batches = array_chunk($chunks, $batch_size);
        $results = ['indexed' => 0, 'errors' => []];
        
        foreach ($batches as $batch_index => $batch) {
            try {
                $this->enforceRateLimit();
                $batch_result = $this->indexBatch($batch, $collection, $source);
                $results['indexed'] += $batch_result['indexed'];
                
                if (!empty($batch_result['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $batch_result['errors']);
                }
                
            } catch (\Exception $e) {
                $error_msg = sprintf(
                    __('Batch %d failed: %s', 'vector-bridge-mvdb-indexer'),
                    $batch_index + 1,
                    $e->getMessage()
                );
                $results['errors'][] = $error_msg;
                
                // Log the error
                error_log('Vector Bridge MVDB indexing error: ' . $error_msg);
            }
        }
        
        return $results;
    }
    
    /**
     * Index a batch of chunks using WP Engine bulkIndex mutation
     * 
     * @param array $chunks Chunk batch
     * @param string $collection Collection name (used as post_type)
     * @param string $source Source URL or filename
     * @return array Batch indexing result
     * @throws \Exception If batch indexing fails
     */
    private function indexBatch(array $chunks, string $collection, string $source = ''): array {
        $documents = [];
        
        foreach ($chunks as $index => $chunk) {
            $document_id = $this->generateDocumentId($chunk['source'], $collection, $index);
            
                $documents[] = [
                    'id' => $document_id,
                    'data' => [
                        'post_title' => $this->extractTitle($chunk),
                        'post_content' => $chunk['content'],
                        'post_type' => $collection, // This is how we organize content
                        'post_status' => 'publish',
                        'post_date' => current_time('c'), // ISO 8601 format with timezone
                        'chunk_index' => $index,
                        'source_origin' => $chunk['source'],
                        'indexed_by' => 'vector-bridge',
                        'wordpress_site' => get_site_url(),
                        'tenant' => Settings::get('tenant', '')
                    ],
                'meta' => [
                    'system' => 'Vector Bridge MVDB Indexer v' . VECTOR_BRIDGE_VERSION,
                    'action' => 'bulk-index',
                    'source' => get_site_url()
                ]
            ];
        }
        
        // Use WP Engine's bulkIndex mutation
        $mutation = '
            mutation CreateBulkIndexDocuments($input: BulkIndexInput!) {
                bulkIndex(input: $input) {
                    code
                    success
                    message
                    documents {
                        id
                    }
                }
            }
        ';
        
        $variables = [
            'input' => [
                'documents' => $documents
            ]
        ];
        
        try {
            $response = $this->executeQuery($mutation, $variables);
            
            if (isset($response['errors'])) {
                throw new \Exception('GraphQL errors: ' . json_encode($response['errors']));
            }
            
            $bulk_result = $response['data']['bulkIndex'] ?? null;
            
            if (!$bulk_result || !$bulk_result['success']) {
                throw new \Exception($bulk_result['message'] ?? 'Bulk indexing failed');
            }
            
            return [
                'indexed' => count($documents),
                'errors' => []
            ];
            
        } catch (\Exception $e) {
            return [
                'indexed' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Get all documents and group by post_type (simplified approach)
     * 
     * @return array List of post_types with document counts
     * @throws \Exception If query fails
     */
    public function getCollections(): array {
        // Use the working syntax from RAG example
        $query = '
            query GetAllDocuments($field: String!) {
                similarity(
                    input: {
                        nearest: {
                            text: "content",
                            field: $field
                        }
                    }
                ) {
                    total
                    docs {
                        id
                        data
                    }
                }
            }
        ';
        
        $variables = [
            'field' => 'post_content'
        ];
        
        try {
            $response = $this->executeQuery($query, $variables);
            
            if (isset($response['errors'])) {
                error_log('Vector Bridge GraphQL errors: ' . json_encode($response['errors']));
                // Return empty collections on error
                return [];
            }
            
            $similarity_result = $response['data']['similarity'] ?? null;
            $documents = $similarity_result['docs'] ?? [];
            
            // Group documents by post_type
            $collections = [];
            $collection_counts = [];
            
            foreach ($documents as $doc) {
                $data = $doc['data'] ?? [];
                $post_type = $data['post_type'] ?? 'unknown';
                
                if (!isset($collection_counts[$post_type])) {
                    $collection_counts[$post_type] = 0;
                }
                $collection_counts[$post_type]++;
            }
            
            foreach ($collection_counts as $post_type => $count) {
                $collections[] = [
                    'name' => $post_type,
                    'document_count' => $count,
                    'created_at' => 'Unknown',
                    'updated_at' => current_time('mysql')
                ];
            }
            
            return $collections;
            
        } catch (\Exception $e) {
            error_log('Vector Bridge getCollections error: ' . $e->getMessage());
            // Return empty array on error
            return [];
        }
    }
    
    /**
     * Get all documents (simplified - no collections concept)
     * 
     * @param string $post_type Optional post_type filter
     * @return array All documents or filtered by post_type
     * @throws \Exception If query fails
     */
    public function getAllDocuments(string $post_type = ''): array {
        // Use the working syntax from RAG example
        $query = '
            query GetAllDocuments($field: String!) {
                similarity(
                    input: {
                        nearest: {
                            text: "content",
                            field: $field
                        }
                    }
                ) {
                    total
                    docs {
                        id
                        data
                        score
                    }
                }
            }
        ';
        
        $variables = [
            'field' => 'post_content'
        ];
        
        try {
            $response = $this->executeQuery($query, $variables);
            
            if (isset($response['errors'])) {
                error_log('Vector Bridge GraphQL errors: ' . json_encode($response['errors']));
                return [
                    'documents' => [],
                    'stats' => [
                        'document_count' => 0,
                        'total_size' => 0,
                        'last_updated' => 'Error: ' . json_encode($response['errors'])
                    ]
                ];
            }
            
            $similarity_result = $response['data']['similarity'] ?? null;
            $docs = $similarity_result['docs'] ?? [];
            $total = $similarity_result['total'] ?? 0;
            
            // Filter by post_type if specified
            if (!empty($post_type)) {
                $docs = array_filter($docs, function($doc) use ($post_type) {
                    $data = $doc['data'] ?? [];
                    return ($data['post_type'] ?? '') === $post_type;
                });
            }
            
            // Process documents for display
            $documents = [];
            $total_size = 0;
            
            foreach ($docs as $doc) {
                $data = $doc['data'] ?? [];
                $content = $data['post_content'] ?? '';
                $size = strlen($content);
                $total_size += $size;
                
                $documents[] = [
                    'id' => $doc['id'],
                    'title' => $data['post_title'] ?? 'Untitled',
                    'origin' => $data['source_origin'] ?? 'Unknown',
                    'post_type' => $data['post_type'] ?? 'unknown',
                    'size' => $size,
                    'created' => $data['post_date'] ?? 'Unknown',
                    'content_preview' => substr($content, 0, 200) . '...'
                ];
            }
            
            return [
                'documents' => $documents,
                'stats' => [
                    'document_count' => count($documents),
                    'total_size' => $total_size,
                    'last_updated' => current_time('mysql')
                ]
            ];
            
        } catch (\Exception $e) {
            error_log('Vector Bridge getAllDocuments error: ' . $e->getMessage());
            return [
                'documents' => [],
                'stats' => [
                    'document_count' => 0,
                    'total_size' => 0,
                    'last_updated' => 'Error: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Search documents using WP Engine similarity API
     * 
     * @param string $query_text Search query
     * @param string $post_type Optional post_type filter
     * @param int $limit Number of results to return
     * @return array Search results
     * @throws \Exception If search fails
     */
    public function searchDocuments(string $query_text, string $post_type = '', int $limit = 5): array {
        // Use the working syntax from RAG example
        $query = '
            query SearchDocuments($text: String!, $field: String!) {
                similarity(
                    input: {
                        nearest: {
                            text: $text,
                            field: $field
                        }
                    }
                ) {
                    total
                    docs {
                        id
                        score
                        data
                    }
                }
            }
        ';
        
        $variables = [
            'text' => $query_text,
            'field' => 'post_content'
        ];
        
        try {
            $response = $this->executeQuery($query, $variables);
            
            if (isset($response['errors'])) {
                error_log('Vector Bridge search errors: ' . json_encode($response['errors']));
                return [];
            }
            
            $similarity_result = $response['data']['similarity'] ?? null;
            $docs = $similarity_result['docs'] ?? [];
            
            // Filter by post_type if specified
            if (!empty($post_type)) {
                $docs = array_filter($docs, function($doc) use ($post_type) {
                    $data = $doc['data'] ?? [];
                    return ($data['post_type'] ?? '') === $post_type;
                });
            }
            
            // Limit results
            $docs = array_slice($docs, 0, $limit);
            
            // Format results for display
            $results = [];
            foreach ($docs as $doc) {
                $data = $doc['data'] ?? [];
                $results[] = [
                    'document_id' => $doc['id'],
                    'title' => $data['post_title'] ?? 'Untitled',
                    'content' => $data['post_content'] ?? '',
                    'score' => $doc['score'] ?? 0,
                    'post_type' => $data['post_type'] ?? 'unknown',
                    'source' => $data['source_origin'] ?? 'unknown',
                    'chunk_number' => $data['chunk_index'] ?? 0
                ];
            }
            
            return $results;
            
        } catch (\Exception $e) {
            error_log('Vector Bridge search error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete documents by post_type
     * 
     * @param string $post_type Post type to delete
     * @return array Deletion result
     * @throws \Exception If deletion fails
     */
    public function deleteDocumentsByType(string $post_type): array {
        // First, get all documents of this type
        $all_docs = $this->getAllDocuments($post_type);
        $documents = $all_docs['documents'];
        
        if (empty($documents)) {
            return [
                'success' => true,
                'message' => 'No documents found to delete',
                'deleted_documents' => 0
            ];
        }
        
        $deleted_count = 0;
        $errors = [];
        
        // Delete each document individually
        foreach ($documents as $doc) {
            try {
                $this->deleteDocument($doc['id']);
                $deleted_count++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete {$doc['id']}: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            throw new \Exception('Some deletions failed: ' . implode(', ', $errors));
        }
        
        return [
            'success' => true,
            'message' => "Deleted {$deleted_count} documents with post_type '{$post_type}'",
            'deleted_documents' => $deleted_count
        ];
    }
    
    /**
     * Delete a single document by ID
     * 
     * @param string $document_id Document ID
     * @return array Deletion result
     * @throws \Exception If deletion fails
     */
    private function deleteDocument(string $document_id): array {
        $mutation = '
            mutation DeleteDocument($id: ID!, $meta: MetaInput) {
                delete(id: $id, meta: $meta) {
                    code
                    success
                    message
                    document {
                        id
                    }
                }
            }
        ';
        
        $variables = [
            'id' => $document_id,
            'meta' => [
                'system' => 'Vector Bridge MVDB Indexer v' . VECTOR_BRIDGE_VERSION,
                'action' => 'delete',
                'source' => get_site_url()
            ]
        ];
        
        $response = $this->executeQuery($mutation, $variables);
        
        if (isset($response['errors'])) {
            throw new \Exception('GraphQL errors: ' . json_encode($response['errors']));
        }
        
        $delete_result = $response['data']['delete'] ?? null;
        
        if (!$delete_result || !$delete_result['success']) {
            throw new \Exception($delete_result['message'] ?? 'Document deletion failed');
        }
        
        return $delete_result;
    }
    
    /**
     * Execute GraphQL query
     * 
     * @param string $query GraphQL query
     * @param array $variables Query variables
     * @return array Response data
     * @throws \Exception If query fails
     */
    private function executeQuery(string $query, array $variables = []): array {
        $endpoint = Settings::get('mvdb_endpoint');
        $token = Settings::get('mvdb_token');
        
        if (empty($endpoint) || empty($token)) {
            throw new \Exception(__('MVDB endpoint and token must be configured', 'vector-bridge-mvdb-indexer'));
        }
        
        $payload = [
            'query' => $query
        ];
        
        if (!empty($variables)) {
            $payload['variables'] = $variables;
        }
        
        // Debug logging
        error_log('Vector Bridge GraphQL Request: ' . json_encode([
            'endpoint' => $this->maskEndpoint($endpoint),
            'query_type' => $this->extractQueryType($query),
            'variables' => $variables
        ]));
        
        try {
            $response = $this->client->post($endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);
            
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Vector Bridge JSON decode error: ' . json_last_error_msg());
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            // Debug logging for response
            if (isset($data['errors'])) {
                error_log('Vector Bridge GraphQL Response Errors: ' . json_encode($data['errors']));
            } else {
                $response_summary = $this->summarizeResponse($data);
                error_log('Vector Bridge GraphQL Response: ' . json_encode($response_summary));
            }
            
            return $data;
            
        } catch (RequestException $e) {
            $error_msg = $e->getMessage();
            $status_code = $e->getCode();
            
            if ($e->hasResponse()) {
                $response_body = $e->getResponse()->getBody()->getContents();
                $status_code = $e->getResponse()->getStatusCode();
                
                error_log('Vector Bridge HTTP Error: ' . $status_code . ' - ' . $response_body);
                
                $error_data = json_decode($response_body, true);
                
                if (isset($error_data['message'])) {
                    $error_msg = $error_data['message'];
                } elseif (isset($error_data['errors'])) {
                    $error_msg = 'GraphQL errors: ' . json_encode($error_data['errors']);
                }
            }
            
            error_log('Vector Bridge Request Exception: ' . $error_msg);
            throw new \Exception($error_msg);
        }
    }
    
    /**
     * Extract query type for debugging
     * 
     * @param string $query GraphQL query
     * @return string Query type
     */
    private function extractQueryType(string $query): string {
        if (strpos($query, 'mutation') !== false) {
            return 'mutation';
        } elseif (strpos($query, 'similarity') !== false) {
            return 'similarity_query';
        } elseif (strpos($query, '__schema') !== false) {
            return 'introspection';
        } else {
            return 'query';
        }
    }
    
    /**
     * Summarize response for debugging
     * 
     * @param array $data Response data
     * @return array Summary
     */
    private function summarizeResponse(array $data): array {
        $summary = ['type' => 'unknown'];
        
        if (isset($data['data']['similarity'])) {
            $similarity = $data['data']['similarity'];
            $summary = [
                'type' => 'similarity',
                'total' => $similarity['total'] ?? 0,
                'docs_count' => count($similarity['docs'] ?? [])
            ];
        } elseif (isset($data['data']['bulkIndex'])) {
            $bulkIndex = $data['data']['bulkIndex'];
            $summary = [
                'type' => 'bulkIndex',
                'success' => $bulkIndex['success'] ?? false,
                'message' => $bulkIndex['message'] ?? 'No message'
            ];
        } elseif (isset($data['data']['__schema'])) {
            $summary = [
                'type' => 'introspection',
                'schema_available' => true
            ];
        }
        
        return $summary;
    }
    
    /**
     * Mask token for logging
     * 
     * @param string $token Token to mask
     * @return string Masked token
     */
    private function maskToken(string $token): string {
        if (strlen($token) <= 6) {
            return str_repeat('*', strlen($token));
        }
        
        return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
    }
    
    /**
     * Generate stable document ID
     * 
     * @param string $source Source identifier
     * @param string $collection Collection name
     * @param int $chunk_index Chunk index
     * @return string Document ID
     */
    private function generateDocumentId(string $source, string $collection, int $chunk_index): string {
        $data = $source . '|' . $collection . '|' . $chunk_index;
        return 'vb_' . hash('sha256', $data);
    }
    
    /**
     * Extract title from chunk content
     * 
     * @param array $chunk Content chunk
     * @return string Extracted title
     */
    private function extractTitle(array $chunk): string {
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
     * Enforce rate limiting
     * 
     * @return void
     */
    private function enforceRateLimit(): void {
        $qps = (float) Settings::get('qps', 2.0);
        $min_interval = 1.0 / $qps; // Minimum seconds between requests
        
        $now = microtime(true);
        $time_since_last = $now - $this->rateLimiter['last_request'];
        
        if ($time_since_last < $min_interval) {
            $sleep_time = $min_interval - $time_since_last;
            usleep((int) ($sleep_time * 1000000)); // Convert to microseconds
        }
        
        $this->rateLimiter['last_request'] = microtime(true);
        $this->rateLimiter['request_count']++;
    }
    
    /**
     * Mask endpoint for display
     * 
     * @param string $endpoint Endpoint URL
     * @return string Masked endpoint
     */
    private function maskEndpoint(string $endpoint): string {
        $parsed = parse_url($endpoint);
        
        if (!$parsed || !isset($parsed['host'])) {
            return '***';
        }
        
        $host = $parsed['host'];
        $scheme = $parsed['scheme'] ?? 'https';
        $path = $parsed['path'] ?? '';
        
        // Mask the middle part of the hostname
        $host_parts = explode('.', $host);
        if (count($host_parts) > 2) {
            $host_parts[0] = substr($host_parts[0], 0, 3) . '***';
        }
        
        return $scheme . '://' . implode('.', $host_parts) . $path;
    }
    
    /**
     * Get rate limiter statistics
     * 
     * @return array Rate limiter stats
     */
    public function getRateLimiterStats(): array {
        return [
            'requests_made' => $this->rateLimiter['request_count'],
            'last_request' => $this->rateLimiter['last_request'],
            'configured_qps' => (float) Settings::get('qps', 2.0)
        ];
    }
    
    // Legacy methods for backward compatibility - these now use the simplified approach
    
    /**
     * @deprecated Use getAllDocuments() instead
     */
    public function getCollectionContent(string $collection): array {
        return $this->getAllDocuments($collection);
    }
    
    /**
     * @deprecated Use searchDocuments() instead
     */
    public function searchCollection(string $collection, string $query_text, int $limit = 5): array {
        return $this->searchDocuments($query_text, $collection, $limit);
    }
    
    /**
     * @deprecated Use deleteDocumentsByType() instead
     */
    public function deleteCollection(string $collection): array {
        return $this->deleteDocumentsByType($collection);
    }
}
