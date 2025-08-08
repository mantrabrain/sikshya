<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;

/**
 * Setting Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class SettingController extends BaseView
{
    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);
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

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $tab = sanitize_text_field($_POST['tab'] ?? 'general');
            
            // Define setting groups (same as in settings.php)
            $setting_groups = [
                'general' => [
                    'title' => __('General', 'sikshya'),
                    'icon' => 'fas fa-cog',
                    'description' => __('Basic LMS configuration settings', 'sikshya')
                ],
                'courses' => [
                    'title' => __('Courses', 'sikshya'),
                    'icon' => 'fas fa-graduation-cap',
                    'description' => __('Course management and display settings', 'sikshya')
                ],
                'enrollment' => [
                    'title' => __('Enrollment', 'sikshya'),
                    'icon' => 'fas fa-user-plus',
                    'description' => __('Student enrollment and access settings', 'sikshya')
                ],
                'payment' => [
                    'title' => __('Payment', 'sikshya'),
                    'icon' => 'fas fa-credit-card',
                    'description' => __('Payment gateway and pricing settings', 'sikshya')
                ],
                'certificates' => [
                    'title' => __('Certificates', 'sikshya'),
                    'icon' => 'fas fa-certificate',
                    'description' => __('Certificate generation and design settings', 'sikshya')
                ],
                'email' => [
                    'title' => __('Email', 'sikshya'),
                    'icon' => 'fas fa-envelope',
                    'description' => __('Email notification and template settings', 'sikshya')
                ],
                'instructors' => [
                    'title' => __('Instructors', 'sikshya'),
                    'icon' => 'fas fa-chalkboard-teacher',
                    'description' => __('Instructor management and permissions', 'sikshya')
                ],
                'students' => [
                    'title' => __('Students', 'sikshya'),
                    'icon' => 'fas fa-users',
                    'description' => __('Student management and profile settings', 'sikshya')
                ],
                'quizzes' => [
                    'title' => __('Quizzes', 'sikshya'),
                    'icon' => 'fas fa-question-circle',
                    'description' => __('Quiz and assessment settings', 'sikshya')
                ],
                'assignments' => [
                    'title' => __('Assignments', 'sikshya'),
                    'icon' => 'fas fa-tasks',
                    'description' => __('Assignment submission and grading settings', 'sikshya')
                ],
                'progress' => [
                    'title' => __('Progress', 'sikshya'),
                    'icon' => 'fas fa-chart-line',
                    'description' => __('Progress tracking and completion settings', 'sikshya')
                ],
                'notifications' => [
                    'title' => __('Notifications', 'sikshya'),
                    'icon' => 'fas fa-bell',
                    'description' => __('In-app and push notification settings', 'sikshya')
                ],
                'integrations' => [
                    'title' => __('Integrations', 'sikshya'),
                    'icon' => 'fas fa-plug',
                    'description' => __('Third-party integrations and APIs', 'sikshya')
                ],
                'security' => [
                    'title' => __('Security', 'sikshya'),
                    'icon' => 'fas fa-shield-alt',
                    'description' => __('Security and privacy settings', 'sikshya')
                ],
                'advanced' => [
                    'title' => __('Advanced', 'sikshya'),
                    'icon' => 'fas fa-tools',
                    'description' => __('Advanced configuration and debugging', 'sikshya')
                ]
            ];
            
            // Get tab data
            $tab_data = $setting_groups[$tab] ?? $setting_groups['general'];
            
            // Load the appropriate settings template
            $template = 'settings/tabs/' . $tab;
            
            ob_start();
            $this->render($template);
            $html = ob_get_clean();

            wp_send_json_success([
                'html' => $html,
                'header' => [
                    'title' => $tab_data['title'] . ' Settings',
                    'icon' => $tab_data['icon'],
                    'description' => $tab_data['description']
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Sikshya Settings Tab Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load settings tab: ' . $e->getMessage());
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

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $tab = sanitize_text_field($_POST['tab'] ?? 'general');
            
            // Reset settings for the specific tab
            $this->resetTabSettings($tab);

            wp_send_json_success('Settings reset successfully');
        } catch (\Exception $e) {
            error_log('Sikshya Reset Settings Error: ' . $e->getMessage());
            wp_send_json_error('Failed to reset settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle settings save AJAX request
     */
    public function handleSettingsSave(): void
    {
        check_ajax_referer('sikshya_settings_nonce', 'sikshya_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to save settings.', 'sikshya'));
        }

        try {
            $current_tab = sanitize_text_field($_POST['current_tab'] ?? 'general');
            
            // Save settings based on the current tab
            $this->saveTabSettings($current_tab, $_POST);

            wp_send_json_success(__('Settings saved successfully!', 'sikshya'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save settings for a specific tab
     */
    private function saveTabSettings(string $tab, array $data): void
    {
        switch ($tab) {
            case 'general':
                $this->saveGeneralSettings($data);
                break;
            case 'courses':
                $this->saveCourseSettings($data);
                break;
            case 'enrollment':
                $this->saveEnrollmentSettings($data);
                break;
            case 'payment':
                $this->savePaymentSettings($data);
                break;
            case 'certificates':
                $this->saveCertificateSettings($data);
                break;
            case 'email':
                $this->saveEmailSettings($data);
                break;
            case 'instructors':
                $this->saveInstructorSettings($data);
                break;
            case 'students':
                $this->saveStudentSettings($data);
                break;
            case 'quizzes':
                $this->saveQuizSettings($data);
                break;
            case 'assignments':
                $this->saveAssignmentSettings($data);
                break;
            case 'progress':
                $this->saveProgressSettings($data);
                break;
            case 'notifications':
                $this->saveNotificationSettings($data);
                break;
            case 'integrations':
                $this->saveIntegrationSettings($data);
                break;
            case 'security':
                $this->saveSecuritySettings($data);
                break;
            case 'advanced':
                $this->saveAdvancedSettings($data);
                break;
            default:
                throw new \Exception('Invalid settings tab');
        }
    }

    /**
     * Reset settings for a specific tab
     */
    private function resetTabSettings(string $tab): void
    {
        switch ($tab) {
            case 'general':
                $this->resetGeneralSettings();
                break;
            case 'courses':
                $this->resetCourseSettings();
                break;
            case 'enrollment':
                $this->resetEnrollmentSettings();
                break;
            case 'payment':
                $this->resetPaymentSettings();
                break;
            case 'certificates':
                $this->resetCertificateSettings();
                break;
            case 'email':
                $this->resetEmailSettings();
                break;
            case 'instructors':
                $this->resetInstructorSettings();
                break;
            case 'students':
                $this->resetStudentSettings();
                break;
            case 'quizzes':
                $this->resetQuizSettings();
                break;
            case 'assignments':
                $this->resetAssignmentSettings();
                break;
            case 'progress':
                $this->resetProgressSettings();
                break;
            case 'notifications':
                $this->resetNotificationSettings();
                break;
            case 'integrations':
                $this->resetIntegrationSettings();
                break;
            case 'security':
                $this->resetSecuritySettings();
                break;
            case 'advanced':
                $this->resetAdvancedSettings();
                break;
            default:
                throw new \Exception('Invalid settings tab');
        }
    }

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