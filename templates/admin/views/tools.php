<?php
/**
 * Tools Page Template
 *
 * @package Sikshya
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

$page_title = $this->data['page_title'] ?? __('Tools', 'sikshya');
$page_description = $this->data['page_description'] ?? __('Manage and maintain your Sikshya LMS installation', 'sikshya');
?>

<div class="sikshya-dashboard">
    <!-- Header -->
    <div class="sikshya-header">
        <div class="sikshya-header-title">
            <h1>
                <i class="fas fa-tools"></i>
                <?php echo esc_html($page_title); ?>
            </h1>
            <span class="sikshya-version">v1.0.0</span>
        </div>
    </div>

    <div class="sikshya-main-content">
        <!-- System Information Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <?php esc_html_e('System Information', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('View system information and requirements', 'sikshya'); ?></p>
                </div>
                <div class="sikshya-content-card-header-right">
                    <button type="button" class="sikshya-btn sikshya-btn-secondary" id="refresh-system-info">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <?php esc_html_e('Refresh', 'sikshya'); ?>
                    </button>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-system-info" id="system-info">
                    <div class="sikshya-loading">
                        <div class="sikshya-spinner"></div>
                        <span><?php esc_html_e('Loading system information...', 'sikshya'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Management Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                        <?php esc_html_e('Data Management', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Export and import your data', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-data-management-grid">
                    <!-- Export Section -->
                    <div class="sikshya-data-section sikshya-export-section">
                        <div class="sikshya-data-section-header">
                            <div class="sikshya-data-section-icon sikshya-export-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="sikshya-data-section-content">
                                <h4 class="sikshya-data-section-title"><?php esc_html_e('Export Data', 'sikshya'); ?></h4>
                                <p class="sikshya-data-section-description"><?php esc_html_e('Download your courses, students, and instructors data in various formats', 'sikshya'); ?></p>
                            </div>
                        </div>
                        
                        <div class="sikshya-export-options">
                            <div class="sikshya-form-group">
                                <label for="export-type" class="sikshya-form-label"><?php esc_html_e('Data Type', 'sikshya'); ?></label>
                                <select id="export-type" class="sikshya-select sikshya-select-lg">
                                    <option value="courses">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <?php esc_html_e('Courses', 'sikshya'); ?>
                                    </option>
                                    <option value="students">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                        </svg>
                                        <?php esc_html_e('Students', 'sikshya'); ?>
                                    </option>
                                    <option value="instructors">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <?php esc_html_e('Instructors', 'sikshya'); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="export-format" class="sikshya-form-label"><?php esc_html_e('Format', 'sikshya'); ?></label>
                                <select id="export-format" class="sikshya-select sikshya-select-lg">
                                    <option value="csv">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <?php esc_html_e('CSV', 'sikshya'); ?>
                                    </option>
                                    <option value="json">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <?php esc_html_e('JSON', 'sikshya'); ?>
                                    </option>
                                    <option value="excel">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                        <?php esc_html_e('Excel', 'sikshya'); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label class="sikshya-form-label"><?php esc_html_e('Include', 'sikshya'); ?></label>
                                <div class="sikshya-checkbox-group">
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="include-meta" checked>
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Meta Data', 'sikshya'); ?>
                                    </label>
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="include-settings" checked>
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Settings', 'sikshya'); ?>
                                    </label>
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="include-media">
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Media Files', 'sikshya'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-btn-lg" id="export-data">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <?php esc_html_e('Export Data', 'sikshya'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Import Section -->
                    <div class="sikshya-data-section sikshya-import-section">
                        <div class="sikshya-data-section-header">
                            <div class="sikshya-data-section-icon sikshya-import-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>
                            <div class="sikshya-data-section-content">
                                <h4 class="sikshya-data-section-title"><?php esc_html_e('Import Data', 'sikshya'); ?></h4>
                                <p class="sikshya-data-section-description"><?php esc_html_e('Import data from CSV, JSON, or Excel files', 'sikshya'); ?></p>
                            </div>
                        </div>
                        
                        <div class="sikshya-import-options">
                            <div class="sikshya-file-upload-area" id="file-upload-area">
                                <div class="sikshya-file-upload-content">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="sikshya-file-upload-icon">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <h5 class="sikshya-file-upload-title"><?php esc_html_e('Drop files here or click to upload', 'sikshya'); ?></h5>
                                    <p class="sikshya-file-upload-subtitle"><?php esc_html_e('Supports CSV, JSON, Excel files up to 10MB', 'sikshya'); ?></p>
                                    <input type="file" id="import-file" class="sikshya-file-input" accept=".csv,.json,.xlsx,.xls" style="display: none;">
                                    <button type="button" class="sikshya-btn sikshya-btn-secondary" id="browse-files">
                                        <?php esc_html_e('Browse Files', 'sikshya'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="import-type" class="sikshya-form-label"><?php esc_html_e('Import Type', 'sikshya'); ?></label>
                                <select id="import-type" class="sikshya-select sikshya-select-lg">
                                    <option value="auto">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <?php esc_html_e('Auto Detect', 'sikshya'); ?>
                                    </option>
                                    <option value="courses">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <?php esc_html_e('Courses', 'sikshya'); ?>
                                    </option>
                                    <option value="students">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                        </svg>
                                        <?php esc_html_e('Students', 'sikshya'); ?>
                                    </option>
                                    <option value="instructors">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <?php esc_html_e('Instructors', 'sikshya'); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label class="sikshya-form-label"><?php esc_html_e('Import Options', 'sikshya'); ?></label>
                                <div class="sikshya-checkbox-group">
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="overwrite-existing" checked>
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Overwrite Existing', 'sikshya'); ?>
                                    </label>
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="skip-duplicates">
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Skip Duplicates', 'sikshya'); ?>
                                    </label>
                                    <label class="sikshya-checkbox">
                                        <input type="checkbox" id="validate-data" checked>
                                        <span class="sikshya-checkbox-mark"></span>
                                        <?php esc_html_e('Validate Data', 'sikshya'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="button" class="sikshya-btn sikshya-btn-success sikshya-btn-lg" id="import-data" disabled>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <?php esc_html_e('Import Data', 'sikshya'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tools Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <?php esc_html_e('Maintenance Tools', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Keep your system running smoothly', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-tools-grid">
                    <!-- Cache Management -->
                    <div class="sikshya-tool-section">
                        <h4><?php esc_html_e('Cache Management', 'sikshya'); ?></h4>
                        <p><?php esc_html_e('Clear cached data to improve performance', 'sikshya'); ?></p>
                        <button type="button" class="sikshya-btn sikshya-btn-warning" id="clear-cache">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <?php esc_html_e('Clear Cache', 'sikshya'); ?>
                        </button>
                    </div>

                    <!-- Settings Reset -->
                    <div class="sikshya-tool-section">
                        <h4><?php esc_html_e('Reset Settings', 'sikshya'); ?></h4>
                        <p><?php esc_html_e('Reset all settings to default values', 'sikshya'); ?></p>
                        <button type="button" class="sikshya-btn sikshya-btn-danger" id="reset-settings">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <?php esc_html_e('Reset Settings', 'sikshya'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load system info on page load
    loadSystemInfo();
    
    // Refresh system info
    $('#refresh-system-info').on('click', function() {
        loadSystemInfo();
    });
    
    // Export data
    $('#export-data').on('click', function() {
        const exportType = $('#export-type').val();
        const exportFormat = $('#export-format').val();
        const includeMeta = $('#include-meta').is(':checked');
        const includeSettings = $('#include-settings').is(':checked');
        const includeMedia = $('#include-media').is(':checked');

        exportData(exportType, exportFormat, includeMeta, includeSettings, includeMedia);
    });
    
    // Import data
    $('#import-data').on('click', function() {
        const fileInput = $('#import-file')[0];
        if (fileInput.files.length > 0) {
            const importType = $('#import-type').val();
            const overwriteExisting = $('#overwrite-existing').is(':checked');
            const skipDuplicates = $('#skip-duplicates').is(':checked');
            const validateData = $('#validate-data').is(':checked');

            importData(fileInput.files[0], importType, overwriteExisting, skipDuplicates, validateData);
        } else {
            alert('<?php esc_html_e('Please select a file to import', 'sikshya'); ?>');
        }
    });
    
    // Clear cache
    $('#clear-cache').on('click', function() {
        if (confirm('<?php esc_html_e('Are you sure you want to clear the cache?', 'sikshya'); ?>')) {
            clearCache();
        }
    });
    
    // Reset settings
    $('#reset-settings').on('click', function() {
        if (confirm('<?php esc_html_e('Are you sure you want to reset all settings? This action cannot be undone.', 'sikshya'); ?>')) {
            resetSettings();
        }
    });

    // File upload area functionality
    $('#file-upload-area').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('sikshya-file-upload-over');
    });

    $('#file-upload-area').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('sikshya-file-upload-over');
    });

    $('#file-upload-area').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('sikshya-file-upload-over');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#import-file').val(files[0].name); // Display file name
            $('#import-data').prop('disabled', false); // Enable import button
        }
    });

    $('#browse-files').on('click', function() {
        $('#import-file').click();
    });
    
    function loadSystemInfo() {
        $('#system-info').html('<div class="sikshya-loading"><div class="sikshya-spinner"></div><span><?php esc_html_e('Loading system information...', 'sikshya'); ?></span></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sikshya_tools_action',
                action_type: 'system_info',
                nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displaySystemInfo(response.data);
                } else {
                    $('#system-info').html('<div class="sikshya-error"><?php esc_html_e('Failed to load system information', 'sikshya'); ?></div>');
                }
            },
            error: function() {
                $('#system-info').html('<div class="sikshya-error"><?php esc_html_e('Failed to load system information', 'sikshya'); ?></div>');
            }
        });
    }
    
    function displaySystemInfo(data) {
        const html = `
            <div class="sikshya-system-info-grid">
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('WordPress Version:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.wordpress_version}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('PHP Version:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.php_version}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('MySQL Version:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.mysql_version}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('Sikshya Version:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.sikshya_version}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('Memory Limit:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.memory_limit}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('Max Execution Time:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.max_execution_time}s</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('Upload Max Filesize:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.upload_max_filesize}</span>
                </div>
                <div class="sikshya-info-item">
                    <span class="sikshya-info-label"><?php esc_html_e('Post Max Size:', 'sikshya'); ?></span>
                    <span class="sikshya-info-value">${data.post_max_size}</span>
                </div>
            </div>
        `;
        $('#system-info').html(html);
    }
    
    function exportData(type, format, includeMeta, includeSettings, includeMedia) {
        const data = {
            action: 'sikshya_tools_action',
            action_type: 'export_data',
            export_type: type,
            export_format: format,
            include_meta: includeMeta,
            include_settings: includeSettings,
            include_media: includeMedia,
            nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Create and download file
                    const dataStr = JSON.stringify(response.data, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    const url = window.URL.createObjectURL(dataBlob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `sikshya-${type}-${new Date().toISOString().split('T')[0]}.json`;
                    link.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert(response.data.message || '<?php esc_html_e('Export failed', 'sikshya'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Export failed', 'sikshya'); ?>');
            }
        });
    }
    
    function importData(file, importType, overwriteExisting, skipDuplicates, validateData) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = JSON.parse(e.target.result);
                const importOptions = {
                    action: 'sikshya_tools_action',
                    action_type: 'import_data',
                    file_data: e.target.result,
                    import_type: importType,
                    overwrite_existing: overwriteExisting,
                    skip_duplicates: skipDuplicates,
                    validate_data: validateData,
                    nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: importOptions,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Data imported successfully', 'sikshya'); ?>');
                            $('#import-file').val('');
                            $('#import-data').prop('disabled', true); // Disable import button after successful import
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Import failed', 'sikshya'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Import failed', 'sikshya'); ?>');
                    }
                });
            } catch (error) {
                alert('<?php esc_html_e('Invalid file format', 'sikshya'); ?>');
            }
        };
        reader.readAsText(file);
    }
    
    function clearCache() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sikshya_tools_action',
                action_type: 'clear_cache',
                nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Cache cleared successfully', 'sikshya'); ?>');
                } else {
                    alert(response.data.message || '<?php esc_html_e('Failed to clear cache', 'sikshya'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to clear cache', 'sikshya'); ?>');
            }
        });
    }
    
    function resetSettings() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sikshya_tools_action',
                action_type: 'reset_settings',
                nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Settings reset successfully', 'sikshya'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_html_e('Failed to reset settings', 'sikshya'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to reset settings', 'sikshya'); ?>');
            }
        });
    }
});
</script>
