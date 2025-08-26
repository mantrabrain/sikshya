/**
 * Tools Page JavaScript
 *
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SikshyaTools = {
        init: function() {
            this.bindEvents();
            this.loadSystemInfo();
        },

        bindEvents: function() {
            $('#refresh-system-info').on('click', this.loadSystemInfo.bind(this));
            $('#export-data').on('click', this.exportData.bind(this));
            $('#import-data').on('click', this.importData.bind(this));
            $('#clear-cache').on('click', this.clearCache.bind(this));
            $('#reset-settings').on('click', this.resetSettings.bind(this));
        },

        loadSystemInfo: function() {
            const $container = $('#system-info');
            
            // Show loading state
            $container.html(`
                <div class="sikshya-loading">
                    <div class="sikshya-spinner"></div>
                    <span>Loading system information...</span>
                </div>
            `);

            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_tools_action',
                    action_type: 'system_info',
                    nonce: sikshya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displaySystemInfo(response.data);
                    } else {
                        this.showError('Failed to load system information');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to load system information');
                }.bind(this)
            });
        },

        displaySystemInfo: function(data) {
            const html = `
                <div class="sikshya-system-info-grid">
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">WordPress Version:</span>
                        <span class="sikshya-info-value">${data.wordpress_version}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">PHP Version:</span>
                        <span class="sikshya-info-value">${data.php_version}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">MySQL Version:</span>
                        <span class="sikshya-info-value">${data.mysql_version}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">Sikshya Version:</span>
                        <span class="sikshya-info-value">${data.sikshya_version}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">Memory Limit:</span>
                        <span class="sikshya-info-value">${data.memory_limit}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">Max Execution Time:</span>
                        <span class="sikshya-info-value">${data.max_execution_time}s</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">Upload Max Filesize:</span>
                        <span class="sikshya-info-value">${data.upload_max_filesize}</span>
                    </div>
                    <div class="sikshya-info-item">
                        <span class="sikshya-info-label">Post Max Size:</span>
                        <span class="sikshya-info-value">${data.post_max_size}</span>
                    </div>
                </div>
            `;
            $('#system-info').html(html);
        },

        exportData: function() {
            const exportType = $('#export-type').val();
            const $button = $('#export-data');
            const originalText = $button.html();
            
            // Show loading state
            $button.html(`
                <div class="sikshya-spinner" style="width: 1rem; height: 1rem; margin: 0;"></div>
                Exporting...
            `).prop('disabled', true);

            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_tools_action',
                    action_type: 'export_data',
                    export_type: exportType,
                    nonce: sikshya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.downloadFile(response.data, exportType);
                        this.showSuccess('Data exported successfully');
                    } else {
                        this.showError(response.data.message || 'Export failed');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Export failed');
                }.bind(this),
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        downloadFile: function(data, type) {
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = window.URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `sikshya-${type}-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        },

        importData: function() {
            const fileInput = $('#import-file')[0];
            if (fileInput.files.length === 0) {
                this.showError('Please select a file to import');
                return;
            }

            const file = fileInput.files[0];
            const reader = new FileReader();
            const $button = $('#import-data');
            const originalText = $button.html();

            // Show loading state
            $button.html(`
                <div class="sikshya-spinner" style="width: 1rem; height: 1rem; margin: 0;"></div>
                Importing...
            `).prop('disabled', true);

            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: sikshya_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'sikshya_tools_action',
                            action_type: 'import_data',
                            file_data: e.target.result,
                            nonce: sikshya_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                this.showSuccess('Data imported successfully');
                                $('#import-file').val('');
                            } else {
                                this.showError(response.data.message || 'Import failed');
                            }
                        }.bind(this),
                        error: function() {
                            this.showError('Import failed');
                        }.bind(this),
                        complete: function() {
                            $button.html(originalText).prop('disabled', false);
                        }
                    });
                } catch (error) {
                    this.showError('Invalid file format');
                    $button.html(originalText).prop('disabled', false);
                }
            }.bind(this);

            reader.readAsText(file);
        },

        clearCache: function() {
            if (!confirm('Are you sure you want to clear the cache?')) {
                return;
            }

            const $button = $('#clear-cache');
            const originalText = $button.html();

            $button.html(`
                <div class="sikshya-spinner" style="width: 1rem; height: 1rem; margin: 0;"></div>
                Clearing...
            `).prop('disabled', true);

            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_tools_action',
                    action_type: 'clear_cache',
                    nonce: sikshya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Cache cleared successfully');
                    } else {
                        this.showError(response.data.message || 'Failed to clear cache');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to clear cache');
                }.bind(this),
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        resetSettings: function() {
            if (!confirm('Are you sure you want to reset all settings? This action cannot be undone.')) {
                return;
            }

            const $button = $('#reset-settings');
            const originalText = $button.html();

            $button.html(`
                <div class="sikshya-spinner" style="width: 1rem; height: 1rem; margin: 0;"></div>
                Resetting...
            `).prop('disabled', true);

            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_tools_action',
                    action_type: 'reset_settings',
                    nonce: sikshya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Settings reset successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showError(response.data.message || 'Failed to reset settings');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to reset settings');
                }.bind(this),
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        showSuccess: function(message) {
            this.showMessage(message, 'success');
        },

        showError: function(message) {
            this.showMessage(message, 'error');
        },

        showMessage: function(message, type) {
            const className = type === 'success' ? 'sikshya-message-success' : 'sikshya-message-error';
            const icon = type === 'success' ? '✓' : '✗';
            
            const $message = $(`
                <div class="sikshya-message ${className}">
                    <strong>${icon}</strong> ${message}
                </div>
            `);

            // Remove existing messages
            $('.sikshya-message').remove();
            
            // Add new message at the top of the main content
            $('.sikshya-main-content').prepend($message);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SikshyaTools.init();
    });

})(jQuery);
