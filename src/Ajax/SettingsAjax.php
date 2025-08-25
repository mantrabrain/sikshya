<?php
/**
 * Settings AJAX Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SettingsAjax extends AjaxAbstract
{
    /**
     * Initialize hooks
     * 
     * @return void
     */
    protected function initHooks(): void
    {
        // Settings AJAX handlers
        add_action('wp_ajax_sikshya_save_settings', [$this, 'handleSaveSettings']);
        add_action('wp_ajax_sikshya_load_settings_tab', [$this, 'handleLoadSettingsTab']);
        add_action('wp_ajax_sikshya_reset_settings', [$this, 'handleResetSettings']);
        add_action('wp_ajax_sikshya_export_settings', [$this, 'handleExportSettings']);
        add_action('wp_ajax_sikshya_import_settings', [$this, 'handleImportSettings']);
    }

    /**
     * Handle save settings AJAX request
     */
    public function handleSaveSettings(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_settings_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability('manage_options')) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $tab = sanitize_text_field($this->getPostData('tab', ''));
            
            if (empty($tab)) {
                $this->sendError('Tab is required');
                return;
            }
            
            // Get settings manager first to access configuration
            $settings_manager = $this->plugin->getService('settings');
            
            // Get the settings configuration for this tab to identify checkbox fields
            $tab_settings = $settings_manager->getTabSettings($tab);
            $checkbox_fields = [];
            
            // Extract checkbox field names from the settings configuration
            foreach ($tab_settings as $section) {
                if (isset($section['fields']) && is_array($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        if (isset($field['key']) && isset($field['type']) && $field['type'] === 'checkbox') {
                            $checkbox_fields[] = $field['key'];
                        }
                    }
                }
            }
            
            // Get all form data except action, nonce, and tab
            $data = [];
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'nonce', 'tab', '_wp_http_referer'])) {
                    $data[$key] = $value;
                }
            }
            
            // Handle unchecked checkboxes - set them to '0' if not present in POST data
            foreach ($checkbox_fields as $checkbox_field) {
                if (!isset($data[$checkbox_field])) {
                    $data[$checkbox_field] = '0';
                }
            }
            
            // Save settings for the specific tab
            $result = $settings_manager->saveTabSettings($tab, $data);
            
            if ($result) {
                $this->sendSuccess(null, 'Settings saved successfully');
            } else {
                $this->sendError('Failed to save settings');
            }
            
        } catch (\Exception $e) {
            $this->logError('Save settings error', $e);
            $this->sendError('Failed to save settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle load settings tab AJAX request
     */
    public function handleLoadSettingsTab(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_settings_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability('manage_options')) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $tab = sanitize_text_field($this->getPostData('tab', ''));
            
            if (empty($tab)) {
                $this->sendError('Tab is required');
                return;
            }
            
            // Get settings manager
            $settings_manager = $this->plugin->getService('settings');
            
            // Render settings as HTML content
            $content = $settings_manager->renderTabSettings($tab);
            
            $this->sendSuccess(['content' => $content]);
            
        } catch (\Exception $e) {
            $this->logError('Load settings tab error', $e);
            $this->sendError('Failed to load settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle reset settings AJAX request
     */
    public function handleResetSettings(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_settings_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability('manage_options')) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $tab = sanitize_text_field($this->getPostData('tab', ''));
            
            // Get settings manager
            $settings_manager = $this->plugin->getService('settings');
            
            if (!empty($tab)) {
                // Reset specific tab
                $result = $settings_manager->resetTabSettings($tab);
                $message = 'Tab settings reset successfully';
            } else {
                // Reset all settings
                $result = $settings_manager->resetAllSettings();
                $message = 'All settings reset successfully';
            }
            
            if ($result) {
                $this->sendSuccess(null, $message);
            } else {
                $this->sendError('Failed to reset settings');
            }
            
        } catch (\Exception $e) {
            $this->logError('Reset settings error', $e);
            $this->sendError('Failed to reset settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle export settings AJAX request
     */
    public function handleExportSettings(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_settings_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability('manage_options')) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $tab = sanitize_text_field($this->getPostData('tab', ''));
            
            // Get settings manager
            $settings_manager = $this->plugin->getService('settings');
            
            if (!empty($tab)) {
                // Export specific tab
                $data = $settings_manager->exportTabSettings($tab);
                $filename = 'sikshya-settings-' . $tab . '-' . date('Y-m-d') . '.json';
            } else {
                // Export all settings
                $data = $settings_manager->exportAllSettings();
                $filename = 'sikshya-settings-all-' . date('Y-m-d') . '.json';
            }
            
            $this->sendSuccess([
                'data' => $data,
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Export settings error', $e);
            $this->sendError('Failed to export settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle import settings AJAX request
     */
    public function handleImportSettings(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_settings_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability('manage_options')) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $file_data = $this->getPostData('file_data', '');
            $overwrite = (bool) $this->getPostData('overwrite', false);
            
            if (empty($file_data)) {
                $this->sendError('No file data provided');
                return;
            }
            
            // Decode JSON data
            $data = json_decode($file_data, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Invalid JSON data');
                return;
            }
            
            // Get settings manager
            $settings_manager = $this->plugin->getService('settings');
            
            // Import settings
            $result = $settings_manager->importSettings($data, $overwrite);
            
            if ($result) {
                $this->sendSuccess(null, 'Settings imported successfully');
            } else {
                $this->sendError('Failed to import settings');
            }
            
        } catch (\Exception $e) {
            $this->logError('Import settings error', $e);
            $this->sendError('Failed to import settings: ' . $e->getMessage());
        }
    }
}
