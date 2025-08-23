<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;

/**
 * Setting Controller Class
 * 
 * @package Sikshya\Admin\Controllers
 * @since 1.0.0
 */
class SettingController extends BaseView
{
    /**
     * Settings Manager instance
     *
     * @var SettingsManager
     */
    protected SettingsManager $settingsManager;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);
        $this->settingsManager = new SettingsManager($plugin);
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('wp_ajax_sikshya_load_settings_tab', [$this, 'handleLoadSettingsTab']);
        add_action('wp_ajax_sikshya_save_settings', [$this, 'handleSettingsSave']);
        add_action('wp_ajax_sikshya_reset_settings', [$this, 'handleResetSettingsTab']);
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        // Get current tab from URL or default to general
        $current_tab = sanitize_text_field($_GET['tab'] ?? 'general');
        
        // Validate tab exists
        $valid_tabs = array_keys($this->settingsManager->getAllSettings());
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'general';
        }
        
        // Render initial tab content
        $initial_content = $this->settingsManager->renderTabSettings($current_tab);
        
        // Pass data to template
        $this->data = [
            'current_tab' => $current_tab,
            'initial_content' => $initial_content
        ];
        
        $this->render('settings');
    }

    /**
     * Handle loading settings tab content via AJAX
     */
    public function handleLoadSettingsTab(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Verify nonce
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'sikshya_settings_nonce')) {
                wp_send_json_error(['message' => __('Security check failed.', 'sikshya')]);
                return;
            }

            $tab = sanitize_text_field($_POST['tab'] ?? 'general');
            
            // Validate tab exists
            $valid_tabs = array_keys($this->settingsManager->getAllSettings());
            if (!in_array($tab, $valid_tabs)) {
                wp_send_json_error(['message' => __('Invalid settings tab.', 'sikshya')]);
                return;
            }

            // Render tab content using SettingsManager
            $content = $this->settingsManager->renderTabSettings($tab);
            
            wp_send_json_success([
                'content' => $content,
                'tab' => $tab
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Error loading settings content.', 'sikshya'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle resetting settings tab
     */
    public function handleResetSettingsTab(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Verify nonce
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'sikshya_settings_nonce')) {
                wp_send_json_error(['message' => __('Security check failed.', 'sikshya')]);
                return;
            }

            $tab = sanitize_text_field($_POST['tab'] ?? 'general');

            // Validate tab exists
            $valid_tabs = array_keys($this->settingsManager->getAllSettings());
            if (!in_array($tab, $valid_tabs)) {
                wp_send_json_error(['message' => __('Invalid settings tab.', 'sikshya')]);
                return;
            }

            $reset_count = 0;
            $tab_settings = $this->settingsManager->getTabSettings($tab);

            // Reset each field to its default value
            foreach ($tab_settings as $section) {
                if (isset($section['fields']) && is_array($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        if (isset($field['key']) && isset($field['default'])) {
                            if ($this->settingsManager->saveSetting($field['key'], $field['default'])) {
                                $reset_count++;
                            }
                        }
                    }
                }
            }

            wp_send_json_success([
                'message' => sprintf(__('%d settings reset to defaults.', 'sikshya'), $reset_count),
                'reset_count' => $reset_count
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Error resetting settings.', 'sikshya'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle settings save AJAX request
     */
    public function handleSettingsSave(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_settings_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'sikshya')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to save settings.', 'sikshya')]);
            return;
        }

        $tab = sanitize_text_field($_POST['tab'] ?? 'general');
        $settings_data = $_POST['settings'] ?? [];

        // Validate tab exists
        $valid_tabs = array_keys($this->settingsManager->getAllSettings());
        if (!in_array($tab, $valid_tabs)) {
            wp_send_json_error(['message' => __('Invalid settings tab.', 'sikshya')]);
            return;
        }

        try {
            $saved_count = 0;
            $errors = [];

            // Get all fields for this tab
            $tab_settings = $this->settingsManager->getTabSettings($tab);
            $valid_fields = [];

            // Extract all valid field keys from the tab settings
            foreach ($tab_settings as $section) {
                if (isset($section['fields']) && is_array($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        if (isset($field['key'])) {
                            $valid_fields[] = $field['key'];
                        }
                    }
                }
            }

            // Process each setting
            foreach ($settings_data as $key => $value) {
                // Only save valid fields
                if (in_array($key, $valid_fields)) {
                    // Sanitize based on field type
                    $sanitized_value = $this->sanitizeSettingValue($key, $value, $tab_settings);
                    
                    if ($this->settingsManager->saveSetting($key, $sanitized_value)) {
                        $saved_count++;
                    } else {
                        $errors[] = sprintf(__('Failed to save setting: %s', 'sikshya'), $key);
                    }
                }
            }

            if (empty($errors)) {
                wp_send_json_success([
                    'message' => sprintf(__('%d settings saved successfully.', 'sikshya'), $saved_count),
                    'saved_count' => $saved_count
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Some settings could not be saved.', 'sikshya'),
                    'errors' => $errors,
                    'saved_count' => $saved_count
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Error saving settings.', 'sikshya'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sanitize setting value based on field type
     *
     * @param string $key
     * @param mixed $value
     * @param array $tab_settings
     * @return mixed
     */
    private function sanitizeSettingValue(string $key, $value, array $tab_settings): mixed
    {
        // Find the field definition
        $field = null;
        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field_def) {
                    if (isset($field_def['key']) && $field_def['key'] === $key) {
                        $field = $field_def;
                        break 2;
                    }
                }
            }
        }

        if (!$field) {
            return $value;
        }

        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return $value === '1' || $value === true ? '1' : '0';
                
            case 'number':
                $value = floatval($value);
                if (isset($field['min']) && $value < $field['min']) {
                    $value = $field['min'];
                }
                if (isset($field['max']) && $value > $field['max']) {
                    $value = $field['max'];
                }
                return $value;
                
            case 'select':
                $options = $field['options'] ?? [];
                return in_array($value, array_keys($options)) ? $value : ($field['default'] ?? '');
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Get settings manager instance
     *
     * @return SettingsManager
     */
    public function getSettingsManager(): SettingsManager
    {
        return $this->settingsManager;
    }

    /**
     * Enqueue view assets
     */
    public function enqueueAssets(): void
    {
        // Assets will be enqueued by the main admin class
    }
} 