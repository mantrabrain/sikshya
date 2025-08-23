<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;
use Sikshya\Admin\Settings\SettingsManager;

/**
 * Setting Controller
 *
 * @package Sikshya\Admin\Controllers
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
        add_action('wp_ajax_sikshya_settings_save', [$this, 'handleSettingsSave']);
        add_action('wp_ajax_sikshya_load_settings_tab', [$this, 'handleLoadSettingsTab']);
        add_action('wp_ajax_sikshya_reset_settings_tab', [$this, 'handleResetSettingsTab']);
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
     * Handle loading settings tab content
     */
    public function handleLoadSettingsTab(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_settings_nonce')) {
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
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_settings_nonce')) {
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
     * Note: All settings are now handled by the SettingsManager class
     * which uses the _sikshya_ prefix and array-based configuration.
     * The old save/reset methods have been removed in favor of the new system.
     */

    // General Settings Methods
    private function saveGeneralSettings(array $data): void
    {
        $settings = [
            'sikshya_site_title' => sanitize_text_field($data['site_title'] ?? ''),
            'sikshya_site_description' => sanitize_textarea_field($data['site_description'] ?? ''),
            'sikshya_currency' => sanitize_text_field($data['currency'] ?? 'USD'),
            'sikshya_currency_position' => sanitize_text_field($data['currency_position'] ?? 'left'),
            'sikshya_timezone' => sanitize_text_field($data['timezone'] ?? ''),
            'sikshya_date_format' => sanitize_text_field($data['date_format'] ?? ''),
            'sikshya_time_format' => sanitize_text_field($data['time_format'] ?? ''),
            'sikshya_language' => sanitize_text_field($data['language'] ?? 'en'),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetGeneralSettings(): void
    {
        $defaults = [
            'sikshya_site_title' => get_bloginfo('name'),
            'sikshya_site_description' => get_bloginfo('description'),
            'sikshya_currency' => 'USD',
            'sikshya_currency_position' => 'left',
            'sikshya_timezone' => get_option('timezone_string'),
            'sikshya_date_format' => get_option('date_format'),
            'sikshya_time_format' => get_option('time_format'),
            'sikshya_language' => 'en',
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Course Settings Methods
    private function saveCourseSettings(array $data): void
    {
        $settings = [
            'sikshya_courses_per_page' => intval($data['courses_per_page'] ?? 12),
            'sikshya_enable_reviews' => isset($data['enable_reviews']),
            'sikshya_enable_ratings' => isset($data['enable_ratings']),
            'sikshya_auto_enroll' => isset($data['auto_enroll']),
            'sikshya_course_archive_layout' => sanitize_text_field($data['course_archive_layout'] ?? 'grid'),
            'sikshya_course_single_layout' => sanitize_text_field($data['course_single_layout'] ?? 'default'),
            'sikshya_enable_course_categories' => isset($data['enable_course_categories']),
            'sikshya_enable_course_tags' => isset($data['enable_course_tags']),
            'sikshya_enable_course_search' => isset($data['enable_course_search']),
            'sikshya_enable_course_filters' => isset($data['enable_course_filters']),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetCourseSettings(): void
    {
        $defaults = [
            'sikshya_courses_per_page' => 12,
            'sikshya_enable_reviews' => true,
            'sikshya_enable_ratings' => true,
            'sikshya_auto_enroll' => true,
            'sikshya_course_archive_layout' => 'grid',
            'sikshya_course_single_layout' => 'default',
            'sikshya_enable_course_categories' => true,
            'sikshya_enable_course_tags' => true,
            'sikshya_enable_course_search' => true,
            'sikshya_enable_course_filters' => true,
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Enrollment Settings Methods
    private function saveEnrollmentSettings(array $data): void
    {
        $settings = [
            'sikshya_allow_guest_enrollment' => isset($data['allow_guest_enrollment']),
            'sikshya_require_login' => isset($data['require_login']),
            'sikshya_enable_waitlist' => isset($data['enable_waitlist']),
            'sikshya_max_students_per_course' => intval($data['max_students_per_course'] ?? 0),
            'sikshya_enrollment_expiry_days' => intval($data['enrollment_expiry_days'] ?? 0),
            'sikshya_allow_unenroll' => isset($data['allow_unenroll']),
            'sikshya_unenroll_refund' => isset($data['unenroll_refund']),
            'sikshya_enable_prerequisites' => isset($data['enable_prerequisites']),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetEnrollmentSettings(): void
    {
        $defaults = [
            'sikshya_allow_guest_enrollment' => false,
            'sikshya_require_login' => true,
            'sikshya_enable_waitlist' => false,
            'sikshya_max_students_per_course' => 0,
            'sikshya_enrollment_expiry_days' => 0,
            'sikshya_allow_unenroll' => true,
            'sikshya_unenroll_refund' => false,
            'sikshya_enable_prerequisites' => true,
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Payment Settings Methods
    private function savePaymentSettings(array $data): void
    {
        $settings = [
            'sikshya_payment_gateway' => sanitize_text_field($data['payment_gateway'] ?? ''),
            'sikshya_stripe_publishable_key' => sanitize_text_field($data['stripe_publishable_key'] ?? ''),
            'sikshya_stripe_secret_key' => sanitize_text_field($data['stripe_secret_key'] ?? ''),
            'sikshya_paypal_client_id' => sanitize_text_field($data['paypal_client_id'] ?? ''),
            'sikshya_paypal_secret' => sanitize_text_field($data['paypal_secret'] ?? ''),
            'sikshya_enable_test_mode' => isset($data['enable_test_mode']),
            'sikshya_tax_rate' => floatval($data['tax_rate'] ?? 0),
            'sikshya_enable_coupons' => isset($data['enable_coupons']),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetPaymentSettings(): void
    {
        $defaults = [
            'sikshya_payment_gateway' => '',
            'sikshya_stripe_publishable_key' => '',
            'sikshya_stripe_secret_key' => '',
            'sikshya_paypal_client_id' => '',
            'sikshya_paypal_secret' => '',
            'sikshya_enable_test_mode' => true,
            'sikshya_tax_rate' => 0,
            'sikshya_enable_coupons' => false,
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Certificate Settings Methods
    private function saveCertificateSettings(array $data): void
    {
        $settings = [
            'sikshya_enable_certificates' => isset($data['enable_certificates']),
            'sikshya_certificate_logo' => esc_url_raw($data['certificate_logo'] ?? ''),
            'sikshya_certificate_signature' => esc_url_raw($data['certificate_signature'] ?? ''),
            'sikshya_certificate_template' => sanitize_text_field($data['certificate_template'] ?? 'default'),
            'sikshya_certificate_font' => sanitize_text_field($data['certificate_font'] ?? 'Arial'),
            'sikshya_certificate_font_size' => intval($data['certificate_font_size'] ?? 12),
            'sikshya_certificate_color' => sanitize_hex_color($data['certificate_color'] ?? '#000000'),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetCertificateSettings(): void
    {
        $defaults = [
            'sikshya_enable_certificates' => true,
            'sikshya_certificate_logo' => '',
            'sikshya_certificate_signature' => '',
            'sikshya_certificate_template' => 'default',
            'sikshya_certificate_font' => 'Arial',
            'sikshya_certificate_font_size' => 12,
            'sikshya_certificate_color' => '#000000',
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Email Settings Methods
    private function saveEmailSettings(array $data): void
    {
        $settings = [
            'sikshya_from_name' => sanitize_text_field($data['from_name'] ?? ''),
            'sikshya_from_email' => sanitize_email($data['from_email'] ?? ''),
            'sikshya_enable_welcome_email' => isset($data['enable_welcome_email']),
            'sikshya_enable_completion_email' => isset($data['enable_completion_email']),
            'sikshya_enable_enrollment_email' => isset($data['enable_enrollment_email']),
            'sikshya_enable_reminder_email' => isset($data['enable_reminder_email']),
            'sikshya_email_template_header' => wp_kses_post($data['email_template_header'] ?? ''),
            'sikshya_email_template_footer' => wp_kses_post($data['email_template_footer'] ?? ''),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetEmailSettings(): void
    {
        $defaults = [
            'sikshya_from_name' => get_bloginfo('name'),
            'sikshya_from_email' => get_option('admin_email'),
            'sikshya_enable_welcome_email' => true,
            'sikshya_enable_completion_email' => true,
            'sikshya_enable_enrollment_email' => true,
            'sikshya_enable_reminder_email' => false,
            'sikshya_email_template_header' => '',
            'sikshya_email_template_footer' => '',
        ];

        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
    }

    // Placeholder methods for other settings tabs
    private function saveInstructorSettings(array $data): void { /* Implementation */ }
    private function resetInstructorSettings(): void { /* Implementation */ }
    private function saveStudentSettings(array $data): void { /* Implementation */ }
    private function resetStudentSettings(): void { /* Implementation */ }
    private function saveQuizSettings(array $data): void { /* Implementation */ }
    private function resetQuizSettings(): void { /* Implementation */ }
    private function saveAssignmentSettings(array $data): void { /* Implementation */ }
    private function resetAssignmentSettings(): void { /* Implementation */ }
    private function saveProgressSettings(array $data): void { /* Implementation */ }
    private function resetProgressSettings(): void { /* Implementation */ }
    private function saveNotificationSettings(array $data): void { /* Implementation */ }
    private function resetNotificationSettings(): void { /* Implementation */ }
    private function saveIntegrationSettings(array $data): void { /* Implementation */ }
    private function resetIntegrationSettings(): void { /* Implementation */ }
    private function saveSecuritySettings(array $data): void
    {
        $settings = [
            // Authentication & Access Control
            'sikshya_session_timeout' => intval($data['session_timeout'] ?? 120),
            'sikshya_force_ssl' => isset($data['force_ssl']) ? 1 : 0,
            'sikshya_max_login_attempts' => intval($data['max_login_attempts'] ?? 5),
            'sikshya_lockout_duration' => intval($data['lockout_duration'] ?? 30),
            
            // Content Security
            'sikshya_prevent_content_copy' => isset($data['prevent_content_copy']) ? 1 : 0,
            'sikshya_watermark_content' => isset($data['watermark_content']) ? 1 : 0,
            'sikshya_content_expiry_days' => intval($data['content_expiry_days'] ?? 0),
            'sikshya_disable_print' => isset($data['disable_print']) ? 1 : 0,
            
            // Privacy & Data Protection
            'sikshya_anonymize_data' => isset($data['anonymize_data']) ? 1 : 0,
            'sikshya_data_retention_days' => intval($data['data_retention_days'] ?? 2555),
            'sikshya_gdpr_compliance' => isset($data['gdpr_compliance']) ? 1 : 0,
            'sikshya_cookie_consent' => isset($data['cookie_consent']) ? 1 : 0,
            
            // File Upload Security
            'sikshya_max_file_size' => intval($data['max_file_size'] ?? 10),
            'sikshya_allowed_file_types' => sanitize_text_field($data['allowed_file_types'] ?? ''),
            'sikshya_scan_uploads' => isset($data['scan_uploads']) ? 1 : 0,
            'sikshya_rename_uploads' => isset($data['rename_uploads']) ? 1 : 0,
            
            // API & Integration Security
            'sikshya_rate_limit_api' => isset($data['rate_limit_api']) ? 1 : 0,
            'sikshya_api_rate_limit' => intval($data['api_rate_limit'] ?? 60),
            'sikshya_require_api_key' => isset($data['require_api_key']) ? 1 : 0,
            'sikshya_api_key_expiry_days' => intval($data['api_key_expiry_days'] ?? 365),
            
            // Security Monitoring
            'sikshya_security_logging' => isset($data['security_logging']) ? 1 : 0,
            'sikshya_email_security_alerts' => isset($data['email_security_alerts']) ? 1 : 0,
            'sikshya_security_alert_email' => sanitize_email($data['security_alert_email'] ?? ''),
            'sikshya_failed_login_threshold' => intval($data['failed_login_threshold'] ?? 10),
            
            // Advanced Security
            'sikshya_two_factor_auth' => isset($data['two_factor_auth']) ? 1 : 0,
            'sikshya_ip_whitelist' => isset($data['ip_whitelist']) ? 1 : 0,
            'sikshya_allowed_ip_addresses' => sanitize_textarea_field($data['allowed_ip_addresses'] ?? ''),
            'sikshya_disable_xmlrpc' => isset($data['disable_xmlrpc']) ? 1 : 0,
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    private function resetSecuritySettings(): void
    {
        $default_settings = [
            'sikshya_session_timeout' => 120,
            'sikshya_force_ssl' => 0,
            'sikshya_max_login_attempts' => 5,
            'sikshya_lockout_duration' => 30,
            'sikshya_prevent_content_copy' => 0,
            'sikshya_watermark_content' => 0,
            'sikshya_content_expiry_days' => 0,
            'sikshya_disable_print' => 0,
            'sikshya_anonymize_data' => 0,
            'sikshya_data_retention_days' => 2555,
            'sikshya_gdpr_compliance' => 1,
            'sikshya_cookie_consent' => 1,
            'sikshya_max_file_size' => 10,
            'sikshya_allowed_file_types' => 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,mp4,mov,avi,zip,rar',
            'sikshya_scan_uploads' => 1,
            'sikshya_rename_uploads' => 1,
            'sikshya_rate_limit_api' => 1,
            'sikshya_api_rate_limit' => 60,
            'sikshya_require_api_key' => 1,
            'sikshya_api_key_expiry_days' => 365,
            'sikshya_security_logging' => 1,
            'sikshya_email_security_alerts' => 1,
            'sikshya_security_alert_email' => get_option('admin_email'),
            'sikshya_failed_login_threshold' => 10,
            'sikshya_two_factor_auth' => 0,
            'sikshya_ip_whitelist' => 0,
            'sikshya_allowed_ip_addresses' => '',
            'sikshya_disable_xmlrpc' => 1,
        ];

        foreach ($default_settings as $key => $value) {
            update_option($key, $value);
        }
    }
    private function saveAdvancedSettings(array $data): void { /* Implementation */ }
    private function resetAdvancedSettings(): void { /* Implementation */ }

    /**
     * Enqueue view assets
     */
    public function enqueueAssets(): void
    {
        // Assets will be enqueued by the main admin class
    }
} 