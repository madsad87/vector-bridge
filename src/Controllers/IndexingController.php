<?php

namespace VectorBridge\MVDBIndexer\Controllers;

/**
 * Indexing Controller
 *
 * Handles content ingestion AJAX requests: dry run, URL processing,
 * file processing, video processing, and file upload.
 * Consolidates duplicate handlers from Plugin.php and Settings.php.
 */
class IndexingController extends AbstractController {

    /**
     * Execute a dry run with sample fixtures
     *
     * AJAX: wp_ajax_vector_bridge_dry_run
     *
     * @return void
     */
    public function dryRun(): void {
        $this->verifyRequest();

        try {
            $extraction_service = $this->service('extraction');
            $chunking_service = $this->service('chunking');

            $fixtures = $this->loadSampleFixtures();
            $results = [];

            foreach ($fixtures as $fixture) {
                $content = $extraction_service->extractFromText($fixture['content']);
                $chunks = $chunking_service->chunkContent($content);

                $results[] = [
                    'title'           => $fixture['title'],
                    'type'            => $fixture['type'],
                    'original_length' => strlen($content),
                    'chunk_count'     => count($chunks),
                    'chunks'          => array_slice($chunks, 0, 3),
                ];
            }

            $this->success([
                'message' => __('Dry run completed successfully!', 'vector-bridge-mvdb-indexer'),
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Dry run failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Schedule URL processing via WordPress cron
     *
     * AJAX: wp_ajax_vector_bridge_process_url
     *
     * @return void
     */
    public function processUrl(): void {
        $this->verifyRequest();

        $url = sanitize_url($_POST['url'] ?? '');
        $collection = sanitize_text_field($_POST['collection'] ?? '');

        if (empty($url) || empty($collection)) {
            $this->error(
                __('URL and collection are required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$url, $collection, 'url']
            );

            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }

            $this->success([
                'message' => __('URL processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id'  => time(),
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Failed to schedule job: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Schedule file processing via WordPress cron
     *
     * AJAX: wp_ajax_vector_bridge_process_file
     *
     * @return void
     */
    public function processFile(): void {
        $this->verifyRequest();

        if (empty($_FILES['file'])) {
            $this->error(
                __('No file uploaded.', 'vector-bridge-mvdb-indexer')
            );
        }

        $collection = sanitize_text_field($_POST['collection'] ?? 'default');
        $url_source = sanitize_url($_POST['url_source'] ?? '');

        try {
            $uploaded_file = wp_handle_upload($_FILES['file'], ['test_form' => false]);

            if (isset($uploaded_file['error'])) {
                throw new \Exception($uploaded_file['error']);
            }

            $metadata = [
                'url_source'        => $url_source,
                'original_filename' => $_FILES['file']['name'],
                'file_type'         => pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION),
            ];

            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_file['file'], $collection, 'document', $metadata]
            );

            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }

            $this->success([
                'message'  => __('File processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id'   => time(),
                'filename' => basename($uploaded_file['file']),
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('File processing failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Schedule video processing via WordPress cron
     *
     * AJAX: wp_ajax_vector_bridge_process_video
     *
     * @return void
     */
    public function processVideo(): void {
        $this->verifyRequest();

        $video_url   = sanitize_url($_POST['video_url'] ?? '');
        $collection  = sanitize_text_field($_POST['collection'] ?? 'default');
        $video_title = sanitize_text_field($_POST['video_title'] ?? '');
        $speaker     = sanitize_text_field($_POST['speaker'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (empty($video_url)) {
            $this->error(
                __('Video URL is required.', 'vector-bridge-mvdb-indexer')
            );
        }

        if (empty($_FILES['vtt_file'])) {
            $this->error(
                __('VTT transcript file is required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $uploaded_vtt = wp_handle_upload($_FILES['vtt_file'], ['test_form' => false]);

            if (isset($uploaded_vtt['error'])) {
                throw new \Exception($uploaded_vtt['error']);
            }

            $metadata = [
                'video_url'             => $video_url,
                'video_title'           => $video_title,
                'speaker'               => $speaker,
                'description'           => $description,
                'vtt_file'              => $uploaded_vtt['file'],
                'original_vtt_filename' => $_FILES['vtt_file']['name'],
            ];

            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_vtt['file'], $collection, 'video', $metadata]
            );

            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }

            $this->success([
                'message'      => __('Video processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id'       => time(),
                'video_url'    => $video_url,
                'vtt_filename' => basename($uploaded_vtt['file']),
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Video processing failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Handle file upload and schedule processing
     *
     * AJAX: wp_ajax_vector_bridge_upload_file
     *
     * @return void
     */
    public function uploadFile(): void {
        $this->verifyRequest();

        if (empty($_FILES['file'])) {
            $this->error(
                __('No file uploaded.', 'vector-bridge-mvdb-indexer')
            );
        }

        $collection = sanitize_text_field($_POST['collection'] ?? '');
        if (empty($collection)) {
            $this->error(
                __('Collection is required.', 'vector-bridge-mvdb-indexer')
            );
        }

        try {
            $uploaded_file = wp_handle_upload($_FILES['file'], ['test_form' => false]);

            if (isset($uploaded_file['error'])) {
                throw new \Exception($uploaded_file['error']);
            }

            $job_id = wp_schedule_single_event(
                time(),
                'vector_bridge_process_content',
                [$uploaded_file['file'], $collection, 'file']
            );

            if ($job_id === false) {
                throw new \Exception('Failed to schedule WordPress cron event');
            }

            $this->success([
                'message'  => __('File upload and processing job scheduled successfully!', 'vector-bridge-mvdb-indexer'),
                'job_id'   => time(),
                'filename' => basename($uploaded_file['file']),
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('File upload failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }

    /**
     * Load sample fixtures for dry run
     *
     * @return array Sample content fixtures
     */
    private function loadSampleFixtures(): array {
        return [
            [
                'title'   => 'Sample HTML Content',
                'type'    => 'html',
                'content' => '<h1>Sample Article</h1><p>This is a sample article with multiple paragraphs. It contains various HTML elements that need to be processed and cleaned up before chunking.</p><p>The content extraction service will convert this HTML to clean markdown format, removing unnecessary tags while preserving the structure and meaning of the content.</p><p>This sample demonstrates how the chunking algorithm will split longer content into manageable pieces while maintaining context and readability.</p>',
            ],
            [
                'title'   => 'Sample PDF Content',
                'type'    => 'pdf',
                'content' => 'Sample PDF Document\n\nThis represents extracted text from a PDF document. PDF extraction can be challenging due to formatting issues, but our extraction service handles common PDF structures effectively.\n\nThe text may contain line breaks and spacing that need to be normalized during the extraction process. The chunking service will then split this content into appropriate segments for vector indexing.\n\nThis sample shows how different document types are processed through the same chunking pipeline, ensuring consistent results regardless of the original format.',
            ],
        ];
    }
}
