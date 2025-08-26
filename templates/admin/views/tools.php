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
                <div class="sikshya-tools-grid">
                    <!-- Export Section -->
                    <div class="sikshya-tool-section">
                        <h4><?php esc_html_e('Export Data', 'sikshya'); ?></h4>
                        <p><?php esc_html_e('Export your courses, students, and instructors data', 'sikshya'); ?></p>
                        
                        <div class="sikshya-export-options">
                            <div class="sikshya-form-group">
                                <label for="export-type"><?php esc_html_e('Select data type:', 'sikshya'); ?></label>
                                <select id="export-type" class="sikshya-select">
                                    <option value="courses"><?php esc_html_e('Courses', 'sikshya'); ?></option>
                                    <option value="students"><?php esc_html_e('Students', 'sikshya'); ?></option>
                                    <option value="instructors"><?php esc_html_e('Instructors', 'sikshya'); ?></option>
                                </select>
                            </div>
                            <button type="button" class="sikshya-btn sikshya-btn-primary" id="export-data">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <?php esc_html_e('Export', 'sikshya'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Import Section -->
                    <div class="sikshya-tool-section">
                        <h4><?php esc_html_e('Import Data', 'sikshya'); ?></h4>
                        <p><?php esc_html_e('Import data from CSV or JSON files', 'sikshya'); ?></p>
                        
                        <div class="sikshya-import-options">
                            <div class="sikshya-form-group">
                                <label for="import-file"><?php esc_html_e('Select file:', 'sikshya'); ?></label>
                                <input type="file" id="import-file" class="sikshya-file-input" accept=".csv,.json">
                            </div>
                            <button type="button" class="sikshya-btn sikshya-btn-secondary" id="import-data">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <?php esc_html_e('Import', 'sikshya'); ?>
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
        exportData(exportType);
    });
    
    // Import data
    $('#import-data').on('click', function() {
        const fileInput = $('#import-file')[0];
        if (fileInput.files.length > 0) {
            importData(fileInput.files[0]);
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
    
    function exportData(type) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sikshya_tools_action',
                action_type: 'export_data',
                export_type: type,
                nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
            },
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
    
    function importData(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = JSON.parse(e.target.result);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sikshya_tools_action',
                        action_type: 'import_data',
                        file_data: e.target.result,
                        nonce: '<?php echo wp_create_nonce('sikshya_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Data imported successfully', 'sikshya'); ?>');
                            $('#import-file').val('');
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
