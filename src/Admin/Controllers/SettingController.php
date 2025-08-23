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
        add_action('wp_ajax_sikshya_reset_all_settings', [$this, 'handleResetAllSettings']);
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
     * Handle resetting ALL settings to defaults
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

            $reset_count = 0;
            $all_settings = $this->settingsManager->getAllSettings();

            // Reset ALL settings across ALL tabs to their default values
            foreach ($all_settings as $tab_name => $tab_settings) {
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
            }

            wp_send_json_success([
                'message' => sprintf(__('%d settings reset to defaults across all tabs.', 'sikshya'), $reset_count),
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
     * Handle resetting all settings across all tabs
     */
    public function handleResetAllSettings(): void
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

            $reset_count = 0;
            $all_settings = $this->settingsManager->getAllSettings();

            // Reset all fields across all tabs to their default values
            foreach ($all_settings as $tab_name => $tab_settings) {
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
            }

            wp_send_json_success([
                'message' => sprintf(__('%d settings across all tabs have been reset to their default values.', 'sikshya'), $reset_count),
                'reset_count' => $reset_count
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Error resetting all settings.', 'sikshya'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle settings save AJAX request
     */
    public function handleSettingsSave(): void
    {
        error_log('Sikshya Settings Save - AJAX handler called');
        error_log('Sikshya Settings Save - POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_settings_nonce')) {
            error_log('Sikshya Settings Save - Nonce verification failed');
            wp_send_json_error(['message' => __('Security check failed.', 'sikshya')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to save settings.', 'sikshya')]);
            return;
        }

        // Debug: Check current user and capabilities
        error_log('Sikshya Settings Save - Current user ID: ' . get_current_user_id());
        error_log('Sikshya Settings Save - User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        error_log('Sikshya Settings Save - Database prefix: ' . $GLOBALS['wpdb']->prefix);

        $tab = sanitize_text_field($_POST['tab'] ?? 'general');
        
        // Get settings data directly from POST (excluding system fields)
        $settings_data = [];
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && $key !== 'nonce' && $key !== 'current_tab' && $key !== 'tab' && $key !== 'settings' && $key !== '_wp_http_referer') {
                $settings_data[$key] = $value;
            }
        }
        
        error_log('Sikshya Settings Save - Tab: ' . $tab);
        error_log('Sikshya Settings Save - Settings data: ' . print_r($settings_data, true));
        error_log('Sikshya Settings Save - Processing only current tab fields');

        // Validate tab exists
        $valid_tabs = array_keys($this->settingsManager->getAllSettings());
        if (!in_array($tab, $valid_tabs)) {
            wp_send_json_error(['message' => __('Invalid settings tab.', 'sikshya')]);
            return;
        }

        try {
            $saved_count = 0;
            $field_errors = [];
            $general_errors = [];



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
            
            error_log('Sikshya Settings Save - Valid fields: ' . print_r($valid_fields, true));
            error_log('Sikshya Settings Save - Settings data: ' . print_r($settings_data, true));

            // Test basic WordPress option saving first
            $test_option = 'sikshya_test_option_' . time();
            $test_result = update_option($test_option, 'test_value');
            error_log('Sikshya Settings Save - Test option save result: ' . ($test_result ? 'success' : 'failed'));
            
            // Process each setting
            foreach ($settings_data as $key => $value) {
                error_log('Sikshya Settings Save - Processing field: ' . $key . ' = ' . $value);
                
                // Only save valid fields
                if (in_array($key, $valid_fields)) {
                    error_log('Sikshya Settings Save - Field ' . $key . ' is valid');
                    
                    // Validate the value first
                    $validation_result = $this->validateSettingValue($key, $value, $tab_settings);
                    if ($validation_result !== true) {
                        error_log('Sikshya Settings Save - Field ' . $key . ' validation failed: ' . $validation_result);
                        $field_errors[$key] = $validation_result;
                        continue;
                    }

                    // Sanitize the value
                    $sanitized_value = $this->sanitizeSettingValue($key, $value, $tab_settings);
                    error_log('Sikshya Settings Save - Field ' . $key . ' sanitized value: ' . $sanitized_value);
                    
                    if ($this->settingsManager->saveSetting($key, $sanitized_value)) {
                        error_log('Sikshya Settings Save - Field ' . $key . ' saved successfully');
                        $saved_count++;
                    } else {
                        error_log('Sikshya Settings Save - Field ' . $key . ' save failed');
                        $general_errors[] = sprintf(__('Failed to save setting: %s', 'sikshya'), $key);
                    }
                } else {
                    error_log('Sikshya Settings Save - Field ' . $key . ' is not valid');
                }
            }

            // Handle unchecked checkboxes in the current tab
            $tab_checkbox_fields = $this->getCheckboxFieldsInTab($tab_settings);
            foreach ($tab_checkbox_fields as $checkbox_field) {
                if (!isset($settings_data[$checkbox_field])) {
                    error_log('Sikshya Settings Save - Processing unchecked checkbox: ' . $checkbox_field);
                    
                    // Save unchecked checkbox as '0'
                    if ($this->settingsManager->saveSetting($checkbox_field, '0')) {
                        error_log('Sikshya Settings Save - Unchecked checkbox ' . $checkbox_field . ' saved as 0');
                        $saved_count++;
                    } else {
                        error_log('Sikshya Settings Save - Unchecked checkbox ' . $checkbox_field . ' save failed');
                        $general_errors[] = sprintf(__('Failed to save setting: %s', 'sikshya'), $checkbox_field);
                    }
                }
            }

            if (empty($field_errors) && empty($general_errors)) {
                wp_send_json_success([
                    'message' => sprintf(__('%d settings saved successfully.', 'sikshya'), $saved_count),
                    'saved_count' => $saved_count
                ]);
            } else {
                // Re-render the form with error states
                $updated_content = $this->settingsManager->renderTabSettings($tab, $field_errors);
                
                wp_send_json_error([
                    'message' => __('Some settings could not be saved.', 'sikshya'),
                    'field_errors' => $field_errors,
                    'general_errors' => $general_errors,
                    'saved_count' => $saved_count,
                    'updated_content' => $updated_content
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
     * Sanitize setting value based on field configuration
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

        // Use custom sanitize callback if provided
        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return call_user_func($field['sanitize_callback'], $value);
        }

        // Fallback to type-based sanitization
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
     * Validate setting value based on field configuration
     *
     * @param string $key
     * @param mixed $value
     * @param array $tab_settings
     * @return string|bool True if valid, error message if invalid
     */
    private function validateSettingValue(string $key, $value, array $tab_settings): string|bool
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
            return true;
        }

        // Use custom validate callback if provided
        if (isset($field['validate_callback']) && is_callable($field['validate_callback'])) {
            return call_user_func($field['validate_callback'], $value);
        }

        // Fallback to basic validation
        if (isset($field['required']) && $field['required'] && empty($value)) {
            return sprintf(__('Field "%s" is required.', 'sikshya'), $field['label'] ?? $key);
        }

        return true;
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

    /**
     * Get field type from tab settings
     *
     * @param string $field_key
     * @param array $tab_settings
     * @return string
     */
    private function getFieldType(string $field_key, array $tab_settings): string
    {
        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field_def) {
                    if (isset($field_def['key']) && $field_def['key'] === $field_key) {
                        return $field_def['type'] ?? 'text';
                    }
                }
            }
        }
        return 'text';
    }

    /**
     * Get all checkbox fields in the current tab
     *
     * @param array $tab_settings
     * @return array
     */
    private function getCheckboxFieldsInTab(array $tab_settings): array
    {
        $checkbox_fields = [];
        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field_def) {
                    if (isset($field_def['key']) && ($field_def['type'] ?? 'text') === 'checkbox') {
                        $checkbox_fields[] = $field_def['key'];
                    }
                }
            }
        }
        return $checkbox_fields;
    }
} 