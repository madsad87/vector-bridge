/**
 * Vector Bridge MVDB Indexer - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        VectorBridgeAdmin.init();
    });

    /**
     * Main admin functionality
     */
    const VectorBridgeAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Connection validation
            $('#validate-connection').on('click', this.validateConnection);
            
            // Dry run
            $('#dry-run').on('click', this.runDryRun);
            
            // URL processing
            $('#url-form').on('submit', this.processUrl);
            
            // File upload
            $('#file-form').on('submit', this.uploadFile);
            
            // Jobs refresh
            $('#refresh-jobs').on('click', this.refreshJobs);
        },

        /**
         * Load initial data
         */
        loadInitialData: function() {
            // Load recent jobs if on dashboard
            if ($('#recent-jobs').length) {
                this.loadRecentJobs();
            }
        },

        /**
         * Validate MVDB connection
         */
        validateConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $status = $('#connection-status');
            
            // Set loading state
            $button.addClass('loading').prop('disabled', true);
            $status.removeClass('success error').addClass('loading')
                   .html('<p>' + vectorBridge.strings.processing + '</p>');
            
            // Make AJAX request
            $.post(vectorBridge.ajaxUrl, {
                action: 'vector_bridge_validate_connection',
                nonce: vectorBridge.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $status.removeClass('loading error').addClass('success')
                           .html('<p><strong>✓ ' + response.data.message + '</strong></p>' +
                                '<p>Endpoint: ' + response.data.details.endpoint + '</p>' +
                                '<p>Schema Available: ' + (response.data.details.schema_available ? 'Yes' : 'No') + '</p>' +
                                '<p>Tested: ' + response.data.details.timestamp + '</p>');
                } else {
                    $status.removeClass('loading success').addClass('error')
                           .html('<p><strong>✗ ' + (response.data.message || vectorBridge.strings.error) + '</strong></p>');
                }
            })
            .fail(function() {
                $status.removeClass('loading success').addClass('error')
                       .html('<p><strong>✗ ' + vectorBridge.strings.error + '</strong></p>');
            })
            .always(function() {
                $button.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Run dry run with fixtures
         */
        runDryRun: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $results = $('#dry-run-results');
            const $content = $('#dry-run-content');
            
            // Set loading state
            $button.addClass('loading').prop('disabled', true);
            $results.show();
            $content.html('<p>' + vectorBridge.strings.processing + '</p>');
            
            // Make AJAX request
            $.post(vectorBridge.ajaxUrl, {
                action: 'vector_bridge_dry_run',
                nonce: vectorBridge.nonce
            })
            .done(function(response) {
                if (response.success) {
                    VectorBridgeAdmin.renderDryRunResults(response.data.results);
                } else {
                    $content.html('<div class="vector-bridge-notice error"><p>' + 
                                 (response.data.message || vectorBridge.strings.error) + '</p></div>');
                }
            })
            .fail(function() {
                $content.html('<div class="vector-bridge-notice error"><p>' + 
                             vectorBridge.strings.error + '</p></div>');
            })
            .always(function() {
                $button.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Render dry run results
         */
        renderDryRunResults: function(results) {
            const $content = $('#dry-run-content');
            let html = '<div class="vector-bridge-notice success"><p>' + vectorBridge.strings.success + '</p></div>';
            
            results.forEach(function(result) {
                html += '<div class="dry-run-result">';
                html += '<h4>' + result.title + ' (' + result.type.toUpperCase() + ')</h4>';
                
                // Stats
                html += '<div class="dry-run-stats">';
                html += '<div class="dry-run-stat"><span class="value">' + result.original_length + '</span><span class="label">Characters</span></div>';
                html += '<div class="dry-run-stat"><span class="value">' + result.chunk_count + '</span><span class="label">Chunks</span></div>';
                html += '</div>';
                
                // Chunk previews
                if (result.chunks && result.chunks.length > 0) {
                    html += '<h5>Chunk Previews (first 3):</h5>';
                    result.chunks.forEach(function(chunk, index) {
                        html += '<div class="chunk-preview">';
                        html += '<strong>Chunk ' + (index + 1) + ' (' + chunk.character_count + ' chars, ~' + chunk.estimated_tokens + ' tokens):</strong><br>';
                        html += VectorBridgeAdmin.escapeHtml(chunk.content.substring(0, 200));
                        if (chunk.content.length > 200) {
                            html += '...';
                        }
                        html += '</div>';
                    });
                }
                
                html += '</div>';
            });
            
            $content.html(html);
        },

        /**
         * Process URL
         */
        processUrl: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const url = $('#url-input').val();
            
            if (!url) {
                alert('Please enter a URL.');
                return;
            }
            
            // Set loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Make AJAX request
            $.post(vectorBridge.ajaxUrl, {
                action: 'vector_bridge_process_url',
                nonce: vectorBridge.nonce,
                url: url
            })
            .done(function(response) {
                if (response.success) {
                    VectorBridgeAdmin.showNotice('success', response.data.message);
                    $form[0].reset();
                    VectorBridgeAdmin.loadRecentJobs();
                } else {
                    VectorBridgeAdmin.showNotice('error', response.data.message || vectorBridge.strings.error);
                }
            })
            .fail(function() {
                VectorBridgeAdmin.showNotice('error', vectorBridge.strings.error);
            })
            .always(function() {
                $button.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Upload and process file
         */
        uploadFile: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const fileInput = document.getElementById('file-input');
            
            if (!fileInput.files.length) {
                alert('Please select a file.');
                return;
            }
            
            // Set loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Create FormData
            const formData = new FormData();
            formData.append('action', 'vector_bridge_upload_file');
            formData.append('nonce', vectorBridge.nonce);
            formData.append('file', fileInput.files[0]);
            
            // Make AJAX request
            $.ajax({
                url: vectorBridge.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function(response) {
                if (response.success) {
                    VectorBridgeAdmin.showNotice('success', response.data.message);
                    $form[0].reset();
                    VectorBridgeAdmin.loadRecentJobs();
                } else {
                    VectorBridgeAdmin.showNotice('error', response.data.message || vectorBridge.strings.error);
                }
            })
            .fail(function() {
                VectorBridgeAdmin.showNotice('error', vectorBridge.strings.error);
            })
            .always(function() {
                $button.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Load recent jobs
         */
        loadRecentJobs: function() {
            const $container = $('#recent-jobs');
            
            if (!$container.length) {
                return;
            }
            
            $container.html('<p>' + vectorBridge.strings.processing + '</p>');
            
            $.post(vectorBridge.ajaxUrl, {
                action: 'vector_bridge_get_jobs',
                nonce: vectorBridge.nonce
            })
            .done(function(response) {
                if (response.success) {
                    VectorBridgeAdmin.renderRecentJobs(response.data.jobs);
                } else {
                    $container.html('<p>Error loading jobs: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            })
            .fail(function() {
                $container.html('<p>Failed to load jobs.</p>');
            });
        },

        /**
         * Render recent jobs
         */
        renderRecentJobs: function(jobs) {
            const $container = $('#recent-jobs');
            
            if (!jobs || jobs.length === 0) {
                $container.html('<p>No recent jobs found.</p>');
                return;
            }
            
            // Show only the 5 most recent jobs
            const recentJobs = jobs.slice(0, 5);
            let html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Action</th><th>Status</th><th>Scheduled</th></tr></thead>';
            html += '<tbody>';
            
            recentJobs.forEach(function(job) {
                const statusClass = 'status-' + job.status.toLowerCase();
                html += '<tr>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(job.hook) + '</td>';
                html += '<td><span class="' + statusClass + '">' + VectorBridgeAdmin.escapeHtml(job.status) + '</span></td>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(job.scheduled) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            $container.html(html);
        },

        /**
         * Refresh jobs (for jobs page)
         */
        refreshJobs: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $tbody = $('#jobs-table-body');
            
            $button.addClass('loading').prop('disabled', true);
            $tbody.html('<tr><td colspan="5">' + vectorBridge.strings.processing + '</td></tr>');
            
            $.post(vectorBridge.ajaxUrl, {
                action: 'vector_bridge_get_jobs',
                nonce: vectorBridge.nonce
            })
            .done(function(response) {
                if (response.success) {
                    VectorBridgeAdmin.renderJobsTable(response.data.jobs);
                } else {
                    $tbody.html('<tr><td colspan="5">Error: ' + (response.data.message || 'Unknown error') + '</td></tr>');
                }
            })
            .fail(function() {
                $tbody.html('<tr><td colspan="5">Failed to load jobs</td></tr>');
            })
            .always(function() {
                $button.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Render jobs table
         */
        renderJobsTable: function(jobs) {
            const $tbody = $('#jobs-table-body');
            
            if (!jobs || jobs.length === 0) {
                $tbody.html('<tr><td colspan="5">No jobs found.</td></tr>');
                return;
            }
            
            let html = '';
            jobs.forEach(function(job) {
                const statusClass = 'status-' + job.status.toLowerCase();
                const argsText = job.args.length > 0 ? job.args.join(', ') : '—';
                
                html += '<tr>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(job.id) + '</td>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(job.hook) + '</td>';
                html += '<td><span class="' + statusClass + '">' + VectorBridgeAdmin.escapeHtml(job.status) + '</span></td>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(job.scheduled) + '</td>';
                html += '<td>' + VectorBridgeAdmin.escapeHtml(argsText) + '</td>';
                html += '</tr>';
            });
            
            $tbody.html(html);
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            const $notice = $('<div class="vector-bridge-notice ' + type + '"><p>' + VectorBridgeAdmin.escapeHtml(message) + '</p></div>');
            
            // Find a good place to insert the notice
            let $target = $('.wrap h1').first();
            if (!$target.length) {
                $target = $('.wrap').first();
            }
            
            $notice.insertAfter($target);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Auto-refresh jobs on jobs page
    if (window.location.href.indexOf('vector-bridge-jobs') !== -1) {
        setInterval(function() {
            if ($('#jobs-table-body').length) {
                VectorBridgeAdmin.refreshJobs({ preventDefault: function() {} });
            }
        }, 30000); // Refresh every 30 seconds
    }

})(jQuery);
