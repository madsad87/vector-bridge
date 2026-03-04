<?php

namespace VectorBridge\MVDBIndexer\Controllers;

/**
 * Job Controller
 *
 * Handles job status AJAX requests.
 * Consolidates duplicate handlers from Plugin.php (full implementation)
 * and Settings.php (placeholder that returned empty array).
 */
class JobController extends AbstractController {

    /**
     * Get job status and history
     *
     * AJAX: wp_ajax_vector_bridge_get_jobs
     *
     * @return void
     */
    public function getJobs(): void {
        $this->verifyRequest();

        try {
            $formatted_jobs = [];

            // Get all WordPress cron events
            $cron_array = _get_cron_array();

            if ($cron_array) {
                foreach ($cron_array as $timestamp => $cron) {
                    foreach ($cron as $hook => $events) {
                        if (in_array($hook, ['vector_bridge_process_content', 'vector_bridge_index_chunks'])) {
                            foreach ($events as $key => $event) {
                                $formatted_jobs[] = [
                                    'id'        => 'cron_' . $hook . '_' . $timestamp . '_' . $key,
                                    'hook'      => $hook,
                                    'status'    => $timestamp <= time() ? 'running' : 'scheduled',
                                    'scheduled' => date('Y-m-d H:i:s', $timestamp),
                                    'args'      => $event['args'] ?? [],
                                ];
                            }
                        }
                    }
                }
            }

            // Get recent job history from options
            $job_history = get_option('vector_bridge_job_history', []);
            foreach ($job_history as $job) {
                $formatted_jobs[] = $job;
            }

            // Sort by scheduled time (newest first)
            usort($formatted_jobs, function ($a, $b) {
                return strtotime($b['scheduled']) - strtotime($a['scheduled']);
            });

            $this->success([
                'jobs' => array_slice($formatted_jobs, 0, 20),
            ]);
        } catch (\Exception $e) {
            $this->error(
                __('Failed to retrieve jobs: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
            );
        }
    }
}
