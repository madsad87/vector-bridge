<?php

namespace VectorBridge\MVDBIndexer\Cron;

use VectorBridge\MVDBIndexer\Core\ServiceProvider;
use VectorBridge\MVDBIndexer\Services\ContentTypeFactory;

/**
 * Cron Manager
 *
 * Handles WordPress cron callbacks for content processing and indexing.
 * Extracted from Plugin.php: processContent(), indexChunks(), logJobStatus().
 */
class CronManager {

    private ServiceProvider $container;

    public function __construct(ServiceProvider $container) {
        $this->container = $container;
    }

    /**
     * Process content (WordPress cron callback)
     *
     * @param string $source   Source URL or file path
     * @param string $collection Collection name
     * @param string $type     Type: 'url', 'file', 'document', or 'video'
     * @param array  $metadata Optional metadata for content processing
     * @return void
     */
    public function processContent(string $source, string $collection, string $type, array $metadata = []): void {
        $job_id = 'process_' . time() . '_' . substr(md5($source), 0, 8);

        try {
            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'running', [
                'source'     => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection' => $collection,
                'type'       => $type,
                'metadata'   => $metadata,
            ]);

            $extraction_service = $this->container->get('extraction');

            // Extract content based on type
            switch ($type) {
                case 'url':
                    $content = $extraction_service->extractFromUrl($source);
                    $content_type = 'webpage';
                    break;

                case 'document':
                case 'file':
                    $content = $extraction_service->extractFromFile($source);
                    $content_type = 'document';
                    break;

                case 'video':
                    $content = $extraction_service->extractFromVtt($source);
                    $content_type = 'video';
                    break;

                default:
                    throw new \Exception("Unsupported content type: {$type}");
            }

            // Use ContentTypeFactory to create appropriate builder
            $factory = new ContentTypeFactory();
            $builder = $factory->createDataBuilder($content_type);

            // Process content into chunks with appropriate data structure
            $processed_chunks = [];
            foreach ($content as $chunk) {
                $document_data = $builder->buildDocumentData($chunk, $collection, $metadata);
                $processed_chunks[] = $document_data;
            }

            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'completed', [
                'source'         => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection'     => $collection,
                'type'           => $type,
                'content_type'   => $content_type,
                'chunks_created' => count($processed_chunks),
            ]);

            // Schedule indexing job
            wp_schedule_single_event(
                time(),
                'vector_bridge_index_chunks',
                [$processed_chunks, $collection]
            );
        } catch (\Exception $e) {
            $this->logJobStatus($job_id, 'vector_bridge_process_content', 'failed', [
                'source'     => in_array($type, ['file', 'document', 'video']) ? basename($source) : $source,
                'collection' => $collection,
                'type'       => $type,
                'error'      => $e->getMessage(),
            ]);

            error_log('Vector Bridge content processing error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Index chunks into MVDB (WordPress cron callback)
     *
     * @param array  $chunks     Content chunks
     * @param string $collection Collection name
     * @return void
     */
    public function indexChunks(array $chunks, string $collection): void {
        $job_id = 'index_' . time() . '_' . substr(md5($collection), 0, 8);

        try {
            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'running', [
                'collection'  => $collection,
                'chunk_count' => count($chunks),
            ]);

            $mvdb_service = $this->container->get('mvdb');
            $mvdb_service->indexChunks($chunks, $collection);

            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'completed', [
                'collection'           => $collection,
                'chunk_count'          => count($chunks),
                'indexed_successfully' => true,
            ]);
        } catch (\Exception $e) {
            $this->logJobStatus($job_id, 'vector_bridge_index_chunks', 'failed', [
                'collection'  => $collection,
                'chunk_count' => count($chunks),
                'error'       => $e->getMessage(),
            ]);

            error_log('Vector Bridge indexing error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Log job status to WordPress options
     *
     * @param string $job_id Job identifier
     * @param string $hook   Hook name
     * @param string $status Job status
     * @param array  $args   Job arguments/details
     * @return void
     */
    private function logJobStatus(string $job_id, string $hook, string $status, array $args = []): void {
        $job_history = get_option('vector_bridge_job_history', []);

        $job_entry = [
            'id'        => $job_id,
            'hook'      => $hook,
            'status'    => $status,
            'scheduled' => date('Y-m-d H:i:s'),
            'args'      => $args,
        ];

        // Find existing entry or add new one
        $found = false;
        foreach ($job_history as $index => $job) {
            if ($job['id'] === $job_id) {
                $job_history[$index] = $job_entry;
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($job_history, $job_entry);
        }

        // Keep only last 50 jobs
        $job_history = array_slice($job_history, 0, 50);

        update_option('vector_bridge_job_history', $job_history, false);
    }
}
