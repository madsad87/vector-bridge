<?php

namespace VectorBridge\MVDBIndexer\Admin;

/**
 * Admin Menu Handler
 * 
 * Manages WordPress admin menu pages and navigation for the plugin.
 */
class AdminMenu {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPages']);
    }
    
    /**
     * Add admin menu pages
     * 
     * @return void
     */
    public function addMenuPages(): void {
        // Main menu page
        add_menu_page(
            __('Vector Bridge', 'vector-bridge-mvdb-indexer'),
            __('Vector Bridge', 'vector-bridge-mvdb-indexer'),
            'manage_options',
            'vector-bridge',
            [$this, 'renderMainPage'],
            'dashicons-database-import',
            30
        );
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            'vector-bridge',
            __('Dashboard', 'vector-bridge-mvdb-indexer'),
            __('Dashboard', 'vector-bridge-mvdb-indexer'),
            'manage_options',
            'vector-bridge',
            [$this, 'renderMainPage']
        );
        
        // Settings submenu
        add_submenu_page(
            'vector-bridge',
            __('Settings', 'vector-bridge-mvdb-indexer'),
            __('Settings', 'vector-bridge-mvdb-indexer'),
            'manage_options',
            'vector-bridge-settings',
            [$this, 'renderSettingsPage']
        );
        
        // Jobs submenu - Hidden for now (not needed for single URL processing)
        // add_submenu_page(
        //     'vector-bridge',
        //     __('Jobs', 'vector-bridge-mvdb-indexer'),
        //     __('Jobs', 'vector-bridge-mvdb-indexer'),
        //     'manage_options',
        //     'vector-bridge-jobs',
        //     [$this, 'renderJobsPage']
        // );
        
        // Content Browser submenu
        add_submenu_page(
            'vector-bridge',
            __('Content Browser', 'vector-bridge-mvdb-indexer'),
            __('Content Browser', 'vector-bridge-mvdb-indexer'),
            'manage_options',
            'vector-bridge-content',
            [$this, 'renderContentBrowserPage']
        );
    }
    
    /**
     * Render main dashboard page
     * 
     * @return void
     */
    public function renderMainPage(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vector-bridge-dashboard">
                <!-- Connection Status Card -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('MVDB Connection Status', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="connection-status">
                            <p><?php esc_html_e('Click "Validate Connection" to test your MVDB settings.', 'vector-bridge-mvdb-indexer'); ?></p>
                        </div>
                        <button type="button" id="validate-connection" class="button button-secondary">
                            <?php esc_html_e('Validate Connection', 'vector-bridge-mvdb-indexer'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Quick Actions Card -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Quick Actions', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <!-- Test Configuration Section -->
                        <div class="action-group test-config-section">
                            <h3><?php esc_html_e('Test Configuration', 'vector-bridge-mvdb-indexer'); ?></h3>
                            <p><?php esc_html_e('Run a dry test with sample content to verify your chunking settings.', 'vector-bridge-mvdb-indexer'); ?></p>
                            <button type="button" id="dry-run" class="button button-secondary">
                                <?php esc_html_e('Dry Run with Fixtures', 'vector-bridge-mvdb-indexer'); ?>
                            </button>
                        </div>
                        
                        <!-- Tabbed Content Processing -->
                        <div class="vector-bridge-tabs">
                            <div class="tab-nav">
                                <button type="button" class="tab-button active" data-tab="url">
                                    <?php esc_html_e('URL', 'vector-bridge-mvdb-indexer'); ?>
                                </button>
                                <button type="button" class="tab-button" data-tab="file">
                                    <?php esc_html_e('File', 'vector-bridge-mvdb-indexer'); ?>
                                </button>
                                <button type="button" class="tab-button" data-tab="video">
                                    <?php esc_html_e('Video', 'vector-bridge-mvdb-indexer'); ?>
                                </button>
                                <button type="button" class="tab-button" data-tab="bulk">
                                    <?php esc_html_e('Bulk', 'vector-bridge-mvdb-indexer'); ?> <span class="coming-soon"><?php esc_html_e('Coming Soon', 'vector-bridge-mvdb-indexer'); ?></span>
                                </button>
                            </div>
                            
                            <div class="tab-content">
                                <!-- URL Tab -->
                                <div id="tab-url" class="tab-panel active">
                                    <div class="tab-description">
                                        <h3><?php esc_html_e('Process URL', 'vector-bridge-mvdb-indexer'); ?></h3>
                                        <p><?php esc_html_e('Extract and index content from a web page or sitemap. Content type will be automatically determined from the URL.', 'vector-bridge-mvdb-indexer'); ?></p>
                                    </div>
                                    <form id="url-form" class="vector-bridge-form">
                                        <div class="form-row">
                                            <label for="url-input"><?php esc_html_e('URL:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="url" id="url-input" name="url" placeholder="<?php esc_attr_e('Enter URL...', 'vector-bridge-mvdb-indexer'); ?>" required>
                                        </div>
                                        <div class="form-row">
                                            <label for="url-collection"><?php esc_html_e('Collection:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="text" id="url-collection" name="collection" placeholder="<?php esc_attr_e('Collection name (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="button button-primary">
                                                <?php esc_html_e('Process URL', 'vector-bridge-mvdb-indexer'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- File Tab -->
                                <div id="tab-file" class="tab-panel">
                                    <div class="tab-description">
                                        <h3><?php esc_html_e('Upload File', 'vector-bridge-mvdb-indexer'); ?></h3>
                                        <p><?php esc_html_e('Upload and process PDF, DOCX, TXT, or MD files. Content type will be automatically determined from the file extension.', 'vector-bridge-mvdb-indexer'); ?></p>
                                    </div>
                                    <form id="file-form" class="vector-bridge-form" enctype="multipart/form-data">
                                        <div class="form-row">
                                            <label for="file-input"><?php esc_html_e('File:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="file" id="file-input" name="file" accept=".pdf,.docx,.txt,.md" required>
                                            <small class="form-help"><?php esc_html_e('Supported formats: PDF, DOCX, TXT, MD', 'vector-bridge-mvdb-indexer'); ?></small>
                                        </div>
                                        <div class="form-row">
                                            <label for="file-collection"><?php esc_html_e('Collection:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="text" id="file-collection" name="collection" placeholder="<?php esc_attr_e('Collection name (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-row">
                                            <label for="file-url-source"><?php esc_html_e('Source URL:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="url" id="file-url-source" name="url_source" placeholder="<?php esc_attr_e('Where users can access this document (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="button button-primary">
                                                <?php esc_html_e('Upload & Process', 'vector-bridge-mvdb-indexer'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Video Tab -->
                                <div id="tab-video" class="tab-panel">
                                    <div class="tab-description">
                                        <h3><?php esc_html_e('Process Video', 'vector-bridge-mvdb-indexer'); ?></h3>
                                        <p><?php esc_html_e('Index video content using VTT transcript files. Supports YouTube, Vimeo, Google Drive, and direct video URLs.', 'vector-bridge-mvdb-indexer'); ?></p>
                                    </div>
                                    <form id="video-form" class="vector-bridge-form" enctype="multipart/form-data">
                                        <div class="form-row">
                                            <label for="video-url"><?php esc_html_e('Video URL:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="url" id="video-url" name="video_url" placeholder="<?php esc_attr_e('YouTube, Vimeo, Google Drive, or direct video URL', 'vector-bridge-mvdb-indexer'); ?>" required>
                                            <small class="form-help"><?php esc_html_e('Where users can watch the video', 'vector-bridge-mvdb-indexer'); ?></small>
                                        </div>
                                        <div class="form-row">
                                            <label for="vtt-file"><?php esc_html_e('VTT Transcript File:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="file" id="vtt-file" name="vtt_file" accept=".vtt" required>
                                            <small class="form-help"><?php esc_html_e('WebVTT subtitle/transcript file with timestamps', 'vector-bridge-mvdb-indexer'); ?></small>
                                        </div>
                                        <div class="form-row">
                                            <label for="video-title"><?php esc_html_e('Video Title:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="text" id="video-title" name="video_title" placeholder="<?php esc_attr_e('Video title (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-row">
                                            <label for="video-speaker"><?php esc_html_e('Speaker:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="text" id="video-speaker" name="speaker" placeholder="<?php esc_attr_e('Speaker name (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-row">
                                            <label for="video-description"><?php esc_html_e('Description:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <textarea id="video-description" name="description" rows="3" placeholder="<?php esc_attr_e('Video description (optional)', 'vector-bridge-mvdb-indexer'); ?>"></textarea>
                                        </div>
                                        <div class="form-row">
                                            <label for="video-collection"><?php esc_html_e('Collection:', 'vector-bridge-mvdb-indexer'); ?></label>
                                            <input type="text" id="video-collection" name="collection" placeholder="<?php esc_attr_e('Collection name (optional)', 'vector-bridge-mvdb-indexer'); ?>">
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="button button-primary">
                                                <?php esc_html_e('Process Video & VTT', 'vector-bridge-mvdb-indexer'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Bulk Tab -->
                                <div id="tab-bulk" class="tab-panel">
                                    <div class="tab-description">
                                        <h3><?php esc_html_e('Bulk Processing', 'vector-bridge-mvdb-indexer'); ?></h3>
                                        <p><?php esc_html_e('Process multiple items at once using CSV upload or batch operations.', 'vector-bridge-mvdb-indexer'); ?></p>
                                    </div>
                                    <div class="coming-soon-content">
                                        <div class="coming-soon-icon">🚧</div>
                                        <h4><?php esc_html_e('Coming Soon', 'vector-bridge-mvdb-indexer'); ?></h4>
                                        <p><?php esc_html_e('Bulk processing functionality is planned for a future release. This will include:', 'vector-bridge-mvdb-indexer'); ?></p>
                                        <ul>
                                            <li><?php esc_html_e('CSV upload for multiple URLs', 'vector-bridge-mvdb-indexer'); ?></li>
                                            <li><?php esc_html_e('Batch file processing', 'vector-bridge-mvdb-indexer'); ?></li>
                                            <li><?php esc_html_e('Sitemap bulk import', 'vector-bridge-mvdb-indexer'); ?></li>
                                            <li><?php esc_html_e('Progress tracking for large jobs', 'vector-bridge-mvdb-indexer'); ?></li>
                                        </ul>
                                        <p><?php esc_html_e('For now, please use the individual URL, File, or Video tabs for processing content.', 'vector-bridge-mvdb-indexer'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Card -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Recent Activity', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="recent-activity">
                            <p><?php esc_html_e('URL processing activity will appear here...', 'vector-bridge-mvdb-indexer'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Dry Run Results -->
                <div id="dry-run-results" class="postbox" style="display: none;">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Dry Run Results', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="dry-run-content"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vector-bridge-tabs {
            margin-top: 20px;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 1px solid #ccd0d4;
            margin-bottom: 0;
            background: #f9f9f9;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
            color: #646970;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .tab-button:hover {
            color: #0073aa;
            background: #f0f0f0;
        }
        
        .tab-button.active {
            color: #0073aa;
            border-bottom-color: #0073aa;
            background: #fff;
        }
        
        .tab-button .coming-soon {
            font-size: 10px;
            background: #f56e28;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: normal;
        }
        
        .tab-content {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-top: none;
            min-height: 300px;
        }
        
        .tab-panel {
            display: none;
            padding: 20px;
        }
        
        .tab-panel.active {
            display: block;
        }
        
        .tab-description {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .tab-description h3 {
            margin: 0 0 8px 0;
            color: #1d2327;
            font-size: 16px;
        }
        
        .tab-description p {
            margin: 0;
            color: #646970;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .vector-bridge-form {
            max-width: 600px;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #1d2327;
        }
        
        .form-row input[type="text"],
        .form-row input[type="url"],
        .form-row input[type="file"],
        .form-row textarea,
        .form-row select {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-row input[type="file"] {
            padding: 6px;
        }
        
        .form-row textarea {
            resize: vertical;
            font-family: inherit;
        }
        
        .form-help {
            display: block;
            margin-top: 5px;
            color: #646970;
            font-size: 12px;
            font-style: italic;
        }
        
        .form-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .coming-soon-content {
            text-align: center;
            padding: 40px 20px;
            color: #646970;
        }
        
        .coming-soon-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .coming-soon-content h4 {
            margin: 0 0 15px 0;
            color: #1d2327;
            font-size: 18px;
        }
        
        .coming-soon-content p {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .coming-soon-content ul {
            text-align: left;
            display: inline-block;
            margin: 15px 0;
        }
        
        .coming-soon-content li {
            margin-bottom: 8px;
            color: #646970;
        }
        
        /* Action group styling for test config section */
        .action-group {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .action-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .action-group h3 {
            margin: 0 0 8px 0;
            color: #1d2327;
            font-size: 16px;
        }
        
        .action-group p {
            margin: 0 0 15px 0;
            color: #646970;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Loading states */
        .form-actions .button.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .form-actions .button.loading::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-left: 8px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .tab-nav {
                flex-wrap: wrap;
            }
            
            .tab-button {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
            
            .vector-bridge-form {
                max-width: 100%;
            }
            
            .form-row input[type="text"],
            .form-row input[type="url"],
            .form-row input[type="file"],
            .form-row textarea,
            .form-row select {
                max-width: 100%;
            }
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Tab switching functionality
            $('.tab-button').on('click', function() {
                const tabId = $(this).data('tab');
                
                // Don't switch to bulk tab (coming soon)
                if (tabId === 'bulk') {
                    return;
                }
                
                // Update active tab button
                $('.tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Update active tab panel
                $('.tab-panel').removeClass('active');
                $('#tab-' + tabId).addClass('active');
            });
            
            // Form submission handlers
            $('#url-form').on('submit', function(e) {
                e.preventDefault();
                processUrl();
            });
            
            $('#file-form').on('submit', function(e) {
                e.preventDefault();
                processFile();
            });
            
            $('#video-form').on('submit', function(e) {
                e.preventDefault();
                processVideo();
            });
            
            // URL processing
            function processUrl() {
                const $form = $('#url-form');
                const $button = $form.find('button[type="submit"]');
                const formData = new FormData($form[0]);
                
                // Add loading state
                $button.addClass('loading').prop('disabled', true);
                
                // Add AJAX data
                formData.append('action', 'vector_bridge_process_url');
                formData.append('nonce', vectorBridge.nonce);
                
                $.ajax({
                    url: vectorBridge.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showSuccess('URL processed successfully!');
                            $form[0].reset();
                            updateRecentActivity('URL: ' + formData.get('url'));
                        } else {
                            showError('Error: ' + (response.data.message || 'Unknown error occurred'));
                        }
                    },
                    error: function() {
                        showError('Request failed. Please try again.');
                    },
                    complete: function() {
                        $button.removeClass('loading').prop('disabled', false);
                    }
                });
            }
            
            // File processing
            function processFile() {
                const $form = $('#file-form');
                const $button = $form.find('button[type="submit"]');
                const formData = new FormData($form[0]);
                
                // Validate file
                const fileInput = $form.find('input[type="file"]')[0];
                if (!fileInput.files.length) {
                    showError('Please select a file to upload.');
                    return;
                }
                
                // Add loading state
                $button.addClass('loading').prop('disabled', true);
                
                // Add AJAX data
                formData.append('action', 'vector_bridge_process_file');
                formData.append('nonce', vectorBridge.nonce);
                
                $.ajax({
                    url: vectorBridge.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showSuccess('File processed successfully!');
                            $form[0].reset();
                            updateRecentActivity('File: ' + fileInput.files[0].name);
                        } else {
                            showError('Error: ' + (response.data.message || 'Unknown error occurred'));
                        }
                    },
                    error: function() {
                        showError('Request failed. Please try again.');
                    },
                    complete: function() {
                        $button.removeClass('loading').prop('disabled', false);
                    }
                });
            }
            
            // Video processing
            function processVideo() {
                const $form = $('#video-form');
                const $button = $form.find('button[type="submit"]');
                const formData = new FormData($form[0]);
                
                // Validate required fields
                const videoUrl = $form.find('input[name="video_url"]').val();
                const vttFile = $form.find('input[name="vtt_file"]')[0];
                
                if (!videoUrl) {
                    showError('Please enter a video URL.');
                    return;
                }
                
                if (!vttFile.files.length) {
                    showError('Please select a VTT transcript file.');
                    return;
                }
                
                // Add loading state
                $button.addClass('loading').prop('disabled', true);
                
                // Add AJAX data
                formData.append('action', 'vector_bridge_process_video');
                formData.append('nonce', vectorBridge.nonce);
                
                $.ajax({
                    url: vectorBridge.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showSuccess('Video processed successfully!');
                            $form[0].reset();
                            updateRecentActivity('Video: ' + videoUrl);
                        } else {
                            showError('Error: ' + (response.data.message || 'Unknown error occurred'));
                        }
                    },
                    error: function() {
                        showError('Request failed. Please try again.');
                    },
                    complete: function() {
                        $button.removeClass('loading').prop('disabled', false);
                    }
                });
            }
            
            // Helper functions
            function showSuccess(message) {
                // Create or update success notice
                let $notice = $('.vector-bridge-notice.success');
                if (!$notice.length) {
                    $notice = $('<div class="vector-bridge-notice success"></div>');
                    $('.vector-bridge-dashboard').prepend($notice);
                }
                $notice.html('<p>' + message + '</p>').show();
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            function showError(message) {
                // Create or update error notice
                let $notice = $('.vector-bridge-notice.error');
                if (!$notice.length) {
                    $notice = $('<div class="vector-bridge-notice error"></div>');
                    $('.vector-bridge-dashboard').prepend($notice);
                }
                $notice.html('<p>' + message + '</p>').show();
                
                // Auto-hide after 8 seconds
                setTimeout(function() {
                    $notice.fadeOut();
                }, 8000);
            }
            
            function updateRecentActivity(activity) {
                const $activityDiv = $('#recent-activity');
                const timestamp = new Date().toLocaleString();
                const activityHtml = '<div class="activity-item"><strong>' + timestamp + ':</strong> ' + activity + '</div>';
                
                // Add to top of activity list
                if ($activityDiv.find('.activity-item').length === 0) {
                    $activityDiv.html(activityHtml);
                } else {
                    $activityDiv.prepend(activityHtml);
                }
                
                // Keep only last 5 activities
                $activityDiv.find('.activity-item:gt(4)').remove();
            }
        });
        </script>
        
        <style>
        /* Notice styling */
        .vector-bridge-notice {
            margin: 15px 0;
            padding: 12px;
            border-left: 4px solid;
            background: #fff;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .vector-bridge-notice.success {
            border-left-color: #00a32a;
            background: #f0f6fc;
        }
        
        .vector-bridge-notice.error {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        
        .vector-bridge-notice p {
            margin: 0;
            font-size: 14px;
        }
        
        /* Activity styling */
        .activity-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item strong {
            color: #646970;
            font-weight: 500;
        }
        </style>
        <?php
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    public function renderSettingsPage(): void {
        // Get settings instance
        $settings = new Settings();
        $settings->renderPage();
    }
    
    /**
     * Render jobs page
     * 
     * @return void
     */
    public function renderJobsPage(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vector-bridge-jobs">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <button type="button" id="refresh-jobs" class="button">
                            <?php esc_html_e('Refresh', 'vector-bridge-mvdb-indexer'); ?>
                        </button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-id">
                                <?php esc_html_e('ID', 'vector-bridge-mvdb-indexer'); ?>
                            </th>
                            <th scope="col" class="manage-column column-hook">
                                <?php esc_html_e('Action', 'vector-bridge-mvdb-indexer'); ?>
                            </th>
                            <th scope="col" class="manage-column column-status">
                                <?php esc_html_e('Status', 'vector-bridge-mvdb-indexer'); ?>
                            </th>
                            <th scope="col" class="manage-column column-scheduled">
                                <?php esc_html_e('Scheduled', 'vector-bridge-mvdb-indexer'); ?>
                            </th>
                            <th scope="col" class="manage-column column-args">
                                <?php esc_html_e('Arguments', 'vector-bridge-mvdb-indexer'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="jobs-table-body">
                        <tr>
                            <td colspan="5"><?php esc_html_e('Loading jobs...', 'vector-bridge-mvdb-indexer'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <p class="description">
                            <?php esc_html_e('Jobs are processed in the background using Action Scheduler. Completed jobs may be automatically cleaned up after a period of time.', 'vector-bridge-mvdb-indexer'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Load jobs on page load
            loadJobs();
            
            // Refresh button
            $('#refresh-jobs').on('click', function() {
                loadJobs();
            });
            
            // Auto-refresh every 30 seconds
            setInterval(loadJobs, 30000);
            
            function loadJobs() {
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_get_jobs',
                    nonce: vectorBridge.nonce
                }, function(response) {
                    if (response.success) {
                        renderJobsTable(response.data.jobs);
                    } else {
                        $('#jobs-table-body').html('<tr><td colspan="5">' + (response.data.message || 'Error loading jobs') + '</td></tr>');
                    }
                }).fail(function() {
                    $('#jobs-table-body').html('<tr><td colspan="5">Failed to load jobs</td></tr>');
                });
            }
            
            function renderJobsTable(jobs) {
                if (jobs.length === 0) {
                    $('#jobs-table-body').html('<tr><td colspan="5"><?php esc_html_e('No jobs found.', 'vector-bridge-mvdb-indexer'); ?></td></tr>');
                    return;
                }
                
                var html = '';
                jobs.forEach(function(job) {
                    var statusClass = 'status-' + job.status.toLowerCase();
                    var argsText = job.args.length > 0 ? job.args.join(', ') : '—';
                    
                    html += '<tr>';
                    html += '<td>' + job.id + '</td>';
                    html += '<td>' + job.hook + '</td>';
                    html += '<td><span class="' + statusClass + '">' + job.status + '</span></td>';
                    html += '<td>' + job.scheduled + '</td>';
                    html += '<td>' + argsText + '</td>';
                    html += '</tr>';
                });
                
                $('#jobs-table-body').html(html);
            }
        });
        </script>
        
        <style>
        .status-pending { color: #f56e28; }
        .status-complete { color: #00a32a; }
        .status-failed { color: #d63638; }
        .status-running { color: #0073aa; }
        </style>
        <?php
    }
    
    /**
     * Render content browser page
     * 
     * @return void
     */
    public function renderContentBrowserPage(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vector-bridge-content-browser">
                <!-- Search and Filter Controls -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Document Browser', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="browser-controls">
                            <div class="search-controls">
                                <input type="text" id="document-search" class="regular-text" placeholder="<?php esc_attr_e('Search documents...', 'vector-bridge-mvdb-indexer'); ?>">
                                <select id="type-filter" class="regular-text">
                                    <option value=""><?php esc_html_e('All Types', 'vector-bridge-mvdb-indexer'); ?></option>
                                </select>
                                <button type="button" id="refresh-documents" class="button button-secondary">
                                    <?php esc_html_e('Refresh', 'vector-bridge-mvdb-indexer'); ?>
                                </button>
                            </div>
                            
                            <div class="action-controls">
                                <button type="button" id="clear-all" class="button button-link-delete">
                                    <?php esc_html_e('Clear All Documents', 'vector-bridge-mvdb-indexer'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="document-stats" class="stats-bar">
                            <span id="total-docs">0 documents</span>
                            <span id="total-size">0 bytes</span>
                            <span id="last-updated">Never updated</span>
                        </div>
                    </div>
                </div>
                
                <!-- Documents Table -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Indexed Documents', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="documents-table-container">
                            <p class="description"><?php esc_html_e('Loading documents...', 'vector-bridge-mvdb-indexer'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Vector Search Testing -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Vector Search Testing', 'vector-bridge-mvdb-indexer'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="search-controls">
                            <input type="text" id="vector-query" class="regular-text" placeholder="<?php esc_attr_e('Enter search query to test similarity...', 'vector-bridge-mvdb-indexer'); ?>">
                            <select id="search-type-filter" class="regular-text">
                                <option value=""><?php esc_html_e('Search All Types', 'vector-bridge-mvdb-indexer'); ?></option>
                            </select>
                            <input type="number" id="search-limit" class="small-text" value="5" min="1" max="20" placeholder="Limit">
                            <button type="button" id="execute-search" class="button button-primary">
                                <?php esc_html_e('Search', 'vector-bridge-mvdb-indexer'); ?>
                            </button>
                        </div>
                        
                        <div id="search-results" style="display: none; margin-top: 15px;">
                            <h4><?php esc_html_e('Search Results', 'vector-bridge-mvdb-indexer'); ?></h4>
                            <div id="search-results-content"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Preview Modal -->
            <div id="document-modal" class="vector-bridge-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modal-title"><?php esc_html_e('Document Details', 'vector-bridge-mvdb-indexer'); ?></h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-content"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let allDocuments = [];
            let filteredDocuments = [];
            let documentTypes = [];
            
            // Load documents on page load
            loadDocuments();
            
            // Search input
            $('#document-search').on('input', function() {
                filterDocuments();
            });
            
            // Type filter
            $('#type-filter, #search-type-filter').on('change', function() {
                filterDocuments();
            });
            
            // Refresh button
            $('#refresh-documents').on('click', function() {
                loadDocuments();
            });
            
            // Clear all button
            $('#clear-all').on('click', function() {
                if (confirm('<?php esc_js(__('Are you sure you want to delete ALL documents? This action cannot be undone.', 'vector-bridge-mvdb-indexer')); ?>')) {
                    clearAllDocuments();
                }
            });
            
            // Vector search
            $('#execute-search').on('click', function() {
                executeVectorSearch();
            });
            
            // Modal close
            $('.modal-close, #document-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#document-modal').hide();
                }
            });
            
            function loadDocuments() {
                $('#documents-table-container').html('<p><?php esc_js(__('Loading documents...', 'vector-bridge-mvdb-indexer')); ?></p>');
                
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_get_all_documents',
                    nonce: vectorBridge.nonce
                }, function(response) {
                    if (response.success) {
                        allDocuments = response.data.documents;
                        updateStats(response.data.stats);
                        updateTypeFilters();
                        filterDocuments();
                    } else {
                        $('#documents-table-container').html('<p class="error">' + (response.data.message || '<?php esc_js(__('Error loading documents', 'vector-bridge-mvdb-indexer')); ?>') + '</p>');
                    }
                }).fail(function() {
                    $('#documents-table-container').html('<p class="error"><?php esc_js(__('Failed to load documents', 'vector-bridge-mvdb-indexer')); ?></p>');
                });
            }
            
            function updateStats(stats) {
                $('#total-docs').text(stats.document_count + ' documents');
                $('#total-size').text(formatBytes(stats.total_size));
                $('#last-updated').text(stats.last_updated);
            }
            
            function updateTypeFilters() {
                // Get unique types
                documentTypes = [...new Set(allDocuments.map(doc => doc.post_type))].sort();
                
                let typeOptions = '<option value=""><?php esc_js(__('All Types', 'vector-bridge-mvdb-indexer')); ?></option>';
                let searchTypeOptions = '<option value=""><?php esc_js(__('Search All Types', 'vector-bridge-mvdb-indexer')); ?></option>';
                
                documentTypes.forEach(function(type) {
                    const count = allDocuments.filter(doc => doc.post_type === type).length;
                    typeOptions += '<option value="' + type + '">' + type + ' (' + count + ')</option>';
                    searchTypeOptions += '<option value="' + type + '">' + type + '</option>';
                });
                
                $('#type-filter').html(typeOptions);
                $('#search-type-filter').html(searchTypeOptions);
            }
            
            function filterDocuments() {
                const searchTerm = $('#document-search').val().toLowerCase();
                const typeFilter = $('#type-filter').val();
                
                filteredDocuments = allDocuments.filter(function(doc) {
                    const matchesSearch = !searchTerm || 
                        doc.title.toLowerCase().includes(searchTerm) ||
                        doc.origin.toLowerCase().includes(searchTerm) ||
                        doc.id.toLowerCase().includes(searchTerm);
                    
                    const matchesType = !typeFilter || doc.post_type === typeFilter;
                    
                    return matchesSearch && matchesType;
                });
                
                renderDocumentsTable(filteredDocuments);
            }
            
            function renderDocumentsTable(documents) {
                if (documents.length === 0) {
                    $('#documents-table-container').html('<p><?php esc_js(__('No documents found.', 'vector-bridge-mvdb-indexer')); ?></p>');
                    return;
                }
                
                let html = '<table class="wp-list-table widefat fixed striped documents-table">';
                html += '<thead><tr>';
                html += '<th class="column-title"><?php esc_js(__('Title', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '<th class="column-type"><?php esc_js(__('Type', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '<th class="column-source"><?php esc_js(__('Source', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '<th class="column-size"><?php esc_js(__('Size', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '<th class="column-date"><?php esc_js(__('Created', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '<th class="column-actions"><?php esc_js(__('Actions', 'vector-bridge-mvdb-indexer')); ?></th>';
                html += '</tr></thead><tbody>';
                
                documents.forEach(function(doc) {
                    html += '<tr>';
                    html += '<td class="column-title"><strong>' + escapeHtml(doc.title) + '</strong><br><small>' + escapeHtml(doc.id) + '</small></td>';
                    html += '<td class="column-type"><span class="type-badge">' + escapeHtml(doc.post_type) + '</span></td>';
                    html += '<td class="column-source">' + escapeHtml(doc.origin) + '</td>';
                    html += '<td class="column-size">' + formatBytes(doc.size) + '</td>';
                    html += '<td class="column-date">' + escapeHtml(doc.created) + '</td>';
                    html += '<td class="column-actions">';
                    html += '<button class="button button-small view-document" data-doc-id="' + escapeHtml(doc.id) + '"><?php esc_js(__('View', 'vector-bridge-mvdb-indexer')); ?></button> ';
                    html += '<button class="button button-small button-link-delete delete-document" data-doc-id="' + escapeHtml(doc.id) + '"><?php esc_js(__('Delete', 'vector-bridge-mvdb-indexer')); ?></button>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#documents-table-container').html(html);
                
                // Bind action buttons
                $('.view-document').on('click', function() {
                    const docId = $(this).data('doc-id');
                    viewDocument(docId);
                });
                
                $('.delete-document').on('click', function() {
                    const docId = $(this).data('doc-id');
                    if (confirm('<?php esc_js(__('Are you sure you want to delete this document?', 'vector-bridge-mvdb-indexer')); ?>')) {
                        deleteDocument(docId);
                    }
                });
            }
            
            function executeVectorSearch() {
                const query = $('#vector-query').val().trim();
                const typeFilter = $('#search-type-filter').val();
                const limit = parseInt($('#search-limit').val()) || 5;
                
                if (!query) {
                    alert('<?php esc_js(__('Please enter a search query', 'vector-bridge-mvdb-indexer')); ?>');
                    return;
                }
                
                $('#search-results').show();
                $('#search-results-content').html('<p><?php esc_js(__('Searching...', 'vector-bridge-mvdb-indexer')); ?></p>');
                
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_search_documents',
                    nonce: vectorBridge.nonce,
                    query: query,
                    post_type: typeFilter,
                    limit: limit
                }, function(response) {
                    if (response.success) {
                        renderSearchResults(response.data.results);
                    } else {
                        $('#search-results-content').html('<p class="error">' + (response.data.message || '<?php esc_js(__('Search failed', 'vector-bridge-mvdb-indexer')); ?>') + '</p>');
                    }
                }).fail(function() {
                    $('#search-results-content').html('<p class="error"><?php esc_js(__('Search request failed', 'vector-bridge-mvdb-indexer')); ?></p>');
                });
            }
            
            function renderSearchResults(results) {
                if (results.length === 0) {
                    $('#search-results-content').html('<p><?php esc_js(__('No results found.', 'vector-bridge-mvdb-indexer')); ?></p>');
                    return;
                }
                
                let html = '<div class="search-results-list">';
                results.forEach(function(result, index) {
                    html += '<div class="search-result-item">';
                    html += '<div class="result-header">';
                    html += '<h4><?php esc_js(__('Result', 'vector-bridge-mvdb-indexer')); ?> ' + (index + 1) + '</h4>';
                    html += '<span class="similarity-score"><?php esc_js(__('Score:', 'vector-bridge-mvdb-indexer')); ?> ' + result.score.toFixed(3) + '</span>';
                    html += '</div>';
                    html += '<div class="result-meta">';
                    html += '<strong><?php esc_js(__('Title:', 'vector-bridge-mvdb-indexer')); ?></strong> ' + escapeHtml(result.title) + '<br>';
                    html += '<strong><?php esc_js(__('Type:', 'vector-bridge-mvdb-indexer')); ?></strong> ' + escapeHtml(result.post_type) + '<br>';
                    html += '<strong><?php esc_js(__('Source:', 'vector-bridge-mvdb-indexer')); ?></strong> ' + escapeHtml(result.source);
                    html += '</div>';
                    html += '<div class="result-content">' + escapeHtml(result.content.substring(0, 300));
                    if (result.content.length > 300) {
                        html += '...';
                    }
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                
                $('#search-results-content').html(html);
            }
            
            function viewDocument(docId) {
                $('#modal-title').text('<?php esc_js(__('Document Details', 'vector-bridge-mvdb-indexer')); ?>');
                $('#modal-content').html('<p><?php esc_js(__('Loading document...', 'vector-bridge-mvdb-indexer')); ?></p>');
                $('#document-modal').show();
                
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_get_document_details',
                    nonce: vectorBridge.nonce,
                    document_id: docId
                }, function(response) {
                    if (response.success) {
                        renderDocumentDetails(response.data.document);
                    } else {
                        $('#modal-content').html('<p class="error">' + (response.data.message || '<?php esc_js(__('Error loading document', 'vector-bridge-mvdb-indexer')); ?>') + '</p>');
                    }
                }).fail(function() {
                    $('#modal-content').html('<p class="error"><?php esc_js(__('Failed to load document', 'vector-bridge-mvdb-indexer')); ?></p>');
                });
            }
            
            function renderDocumentDetails(document) {
                let html = '<div class="document-details">';
                html += '<div class="detail-section">';
                html += '<h4><?php esc_js(__('Document Information', 'vector-bridge-mvdb-indexer')); ?></h4>';
                html += '<table class="document-info-table">';
                html += '<tr><td><strong><?php esc_js(__('ID:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + escapeHtml(document.id) + '</td></tr>';
                html += '<tr><td><strong><?php esc_js(__('Title:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + escapeHtml(document.title) + '</td></tr>';
                html += '<tr><td><strong><?php esc_js(__('Type:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + escapeHtml(document.post_type) + '</td></tr>';
                html += '<tr><td><strong><?php esc_js(__('Source:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + escapeHtml(document.origin) + '</td></tr>';
                html += '<tr><td><strong><?php esc_js(__('Size:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + formatBytes(document.size) + '</td></tr>';
                html += '<tr><td><strong><?php esc_js(__('Created:', 'vector-bridge-mvdb-indexer')); ?></strong></td><td>' + escapeHtml(document.created) + '</td></tr>';
                html += '</table>';
                html += '</div>';
                
                html += '<div class="detail-section">';
                html += '<h4><?php esc_js(__('Content Preview', 'vector-bridge-mvdb-indexer')); ?></h4>';
                html += '<div class="content-preview-full">' + escapeHtml(document.content_preview) + '</div>';
                html += '</div>';
                
                html += '</div>';
                $('#modal-content').html(html);
            }
            
            function deleteDocument(docId) {
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_delete_document',
                    nonce: vectorBridge.nonce,
                    document_id: docId
                }, function(response) {
                    if (response.success) {
                        // Remove from local arrays
                        allDocuments = allDocuments.filter(doc => doc.id !== docId);
                        filterDocuments();
                        updateStats({
                            document_count: allDocuments.length,
                            total_size: allDocuments.reduce((sum, doc) => sum + doc.size, 0),
                            last_updated: new Date().toLocaleString()
                        });
                        updateTypeFilters();
                    } else {
                        alert('<?php esc_js(__('Failed to delete document:', 'vector-bridge-mvdb-indexer')); ?> ' + (response.data.message || '<?php esc_js(__('Unknown error', 'vector-bridge-mvdb-indexer')); ?>'));
                    }
                }).fail(function() {
                    alert('<?php esc_js(__('Delete request failed', 'vector-bridge-mvdb-indexer')); ?>');
                });
            }
            
            function clearAllDocuments() {
                $.post(vectorBridge.ajaxUrl, {
                    action: 'vector_bridge_clear_all_documents',
                    nonce: vectorBridge.nonce
                }, function(response) {
                    if (response.success) {
                        allDocuments = [];
                        filteredDocuments = [];
                        documentTypes = [];
                        updateStats({ document_count: 0, total_size: 0, last_updated: new Date().toLocaleString() });
                        updateTypeFilters();
                        renderDocumentsTable([]);
                    } else {
                        alert('<?php esc_js(__('Failed to clear documents:', 'vector-bridge-mvdb-indexer')); ?> ' + (response.data.message || '<?php esc_js(__('Unknown error', 'vector-bridge-mvdb-indexer')); ?>'));
                    }
                }).fail(function() {
                    alert('<?php esc_js(__('Clear request failed', 'vector-bridge-mvdb-indexer')); ?>');
                });
            }
            
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
        </script>
        
        <style>
        .vector-bridge-content-browser .browser-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .search-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .action-controls {
            display: flex;
            gap: 10px;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .stats-bar span {
            color: #666;
        }
        
        .documents-table .column-title {
            width: 25%;
        }
        
        .documents-table .column-type {
            width: 12%;
        }
        
        .documents-table .column-source {
            width: 20%;
        }
        
        .documents-table .column-size {
            width: 10%;
        }
        
        .documents-table .column-date {
            width: 15%;
        }
        
        .documents-table .column-actions {
            width: 18%;
        }
        
        .type-badge {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .search-results-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .search-result-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .result-header h4 {
            margin: 0;
            color: #0073aa;
        }
        
        .similarity-score {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .result-meta {
            margin-bottom: 10px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .result-content {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
        }
        
        .vector-bridge-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 4px;
            max-width: 800px;
            max-height: 80vh;
            width: 90%;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .document-details .detail-section {
            margin-bottom: 20px;
        }
        
        .document-details h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        
        .document-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .document-info-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .document-info-table td:first-child {
            width: 120px;
            font-weight: 500;
        }
        
        .content-preview-full {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .error {
            color: #d63638;
        }
        </style>
        <?php
    }
}
