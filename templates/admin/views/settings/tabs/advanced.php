<?php
/**
 * Advanced Settings Tab Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-settings-tab-content">
    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-bug"></i>
            <?php _e('Debug & Development', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="debug_mode" value="1" 
                           <?php checked(get_option('sikshya_debug_mode', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Debug Mode', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable debug mode for troubleshooting and development', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="cache_enabled" value="1" 
                           <?php checked(get_option('sikshya_cache_enabled', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Caching', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable caching for better performance', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="log_level"><?php _e('Log Level', 'sikshya'); ?></label>
                <select id="log_level" name="log_level">
                    <option value="error" <?php selected(get_option('sikshya_log_level', 'error'), 'error'); ?>><?php _e('Error Only', 'sikshya'); ?></option>
                    <option value="warning" <?php selected(get_option('sikshya_log_level', 'error'), 'warning'); ?>><?php _e('Warning & Error', 'sikshya'); ?></option>
                    <option value="info" <?php selected(get_option('sikshya_log_level', 'error'), 'info'); ?>><?php _e('Info, Warning & Error', 'sikshya'); ?></option>
                    <option value="debug" <?php selected(get_option('sikshya_log_level', 'error'), 'debug'); ?>><?php _e('All Logs', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Level of detail for system logging', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-database"></i>
            <?php _e('Database & Cache', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="cache_expiry"><?php _e('Cache Expiry (hours)', 'sikshya'); ?></label>
                <input type="number" id="cache_expiry" name="cache_expiry" 
                       value="<?php echo esc_attr(get_option('sikshya_cache_expiry', 24)); ?>" 
                       min="1" max="168">
                <p class="description"><?php _e('How long to keep cached data (1-168 hours)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_cleanup" value="1" 
                           <?php checked(get_option('sikshya_auto_cleanup', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto Cleanup', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically clean up old data and logs', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="cleanup_frequency"><?php _e('Cleanup Frequency', 'sikshya'); ?></label>
                <select id="cleanup_frequency" name="cleanup_frequency">
                    <option value="daily" <?php selected(get_option('sikshya_cleanup_frequency', 'weekly'), 'daily'); ?>><?php _e('Daily', 'sikshya'); ?></option>
                    <option value="weekly" <?php selected(get_option('sikshya_cleanup_frequency', 'weekly'), 'weekly'); ?>><?php _e('Weekly', 'sikshya'); ?></option>
                    <option value="monthly" <?php selected(get_option('sikshya_cleanup_frequency', 'weekly'), 'monthly'); ?>><?php _e('Monthly', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How often to perform automatic cleanup', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-tools"></i>
            <?php _e('System Tools', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <button type="button" class="button sikshya-clear-cache" onclick="clearCache()">
                    <i class="fas fa-trash"></i>
                    <?php _e('Clear All Cache', 'sikshya'); ?>
                </button>
                <p class="description"><?php _e('Clear all cached data and temporary files', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <button type="button" class="button sikshya-export-settings" onclick="exportSettings()">
                    <i class="fas fa-download"></i>
                    <?php _e('Export Settings', 'sikshya'); ?>
                </button>
                <p class="description"><?php _e('Export all LMS settings as a backup file', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <button type="button" class="button sikshya-import-settings" onclick="importSettings()">
                    <i class="fas fa-upload"></i>
                    <?php _e('Import Settings', 'sikshya'); ?>
                </button>
                <p class="description"><?php _e('Import settings from a backup file', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.sikshya-settings-field .button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    height: auto;
    margin-top: 5px;
}

.sikshya-settings-field .button i {
    font-size: 14px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Cache management functions
    window.clearCache = function() {
        if (confirm('Are you sure you want to clear all cache? This may temporarily slow down the site.')) {
            jQuery.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_clear_cache',
                    nonce: sikshya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('Cache cleared successfully!', 'success');
                    } else {
                        showNotice('Error clearing cache: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotice('Failed to clear cache. Please try again.', 'error');
                }
            });
        }
    };

    window.exportSettings = function() {
        jQuery.ajax({
            url: sikshya_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sikshya_export_settings',
                nonce: sikshya_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(response.data));
                    link.download = 'sikshya-settings-' + new Date().toISOString().split('T')[0] + '.json';
                    link.click();
                    showNotice('Settings exported successfully!', 'success');
                } else {
                    showNotice('Error exporting settings: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Failed to export settings. Please try again.', 'error');
            }
        });
    };

    window.importSettings = function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const settings = JSON.parse(e.target.result);
                        jQuery.ajax({
                            url: sikshya_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'sikshya_import_settings',
                                settings: JSON.stringify(settings),
                                nonce: sikshya_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showNotice('Settings imported successfully!', 'success');
                                    // Reload the current tab
                                    loadSettingsContent('advanced');
                                } else {
                                    showNotice('Error importing settings: ' + response.data, 'error');
                                }
                            },
                            error: function() {
                                showNotice('Failed to import settings. Please try again.', 'error');
                            }
                        });
                    } catch (error) {
                        showNotice('Invalid settings file format.', 'error');
                    }
                };
                reader.readAsText(file);
            }
        };
        input.click();
    };
});
</script> 