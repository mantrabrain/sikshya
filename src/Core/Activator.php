<?php

namespace Sikshya\Core;

/**
 * Plugin Activator
 *
 * @package Sikshya\Core
 */
class Activator
{
    /**
     * Activate the plugin
     */
    public static function activate(): void
    {
        // Check requirements
        if (!Requirements::check()) {
            deactivate_plugins(plugin_basename(SIKSHYA_PLUGIN_FILE));
            wp_die(__('Sikshya LMS requires WordPress 6.0+ and PHP 8.1+', 'sikshya'));
        }

        // Create database tables
        $database = new \Sikshya\Database\Database(Plugin::getInstance());
        $database->createTables();

        // Create user roles
        self::createRoles();

        // Set default options
        self::setDefaultOptions();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation time
        update_option('sikshya_activation_time', current_time('timestamp'));

        // Trigger activation hook
        do_action('sikshya_activated');
    }

    /**
     * Create user roles
     */
    private static function createRoles(): void
    {
        // Instructor role
        add_role('sikshya_instructor', __('Instructor', 'sikshya'), [
            'read' => true,
            'edit_sikshya_courses' => true,
            'edit_published_sikshya_courses' => true,
            'publish_sikshya_courses' => true,
            'delete_sikshya_courses' => true,
            'delete_published_sikshya_courses' => true,
            'edit_sikshya_lessons' => true,
            'edit_published_sikshya_lessons' => true,
            'publish_sikshya_lessons' => true,
            'delete_sikshya_lessons' => true,
            'delete_published_sikshya_lessons' => true,
            'edit_sikshya_quizzes' => true,
            'edit_published_sikshya_quizzes' => true,
            'publish_sikshya_quizzes' => true,
            'delete_sikshya_quizzes' => true,
            'delete_published_sikshya_quizzes' => true,
            'upload_files' => true,
            'manage_sikshya_students' => true,
            'view_sikshya_reports' => true,
        ]);

        // Student role
        add_role('sikshya_student', __('Student', 'sikshya'), [
            'read' => true,
            'enroll_sikshya_courses' => true,
            'access_sikshya_courses' => true,
            'submit_sikshya_assignments' => true,
            'take_sikshya_quizzes' => true,
            'view_sikshya_certificates' => true,
        ]);

        // Assistant role
        add_role('sikshya_assistant', __('Assistant', 'sikshya'), [
            'read' => true,
            'edit_sikshya_courses' => true,
            'edit_published_sikshya_courses' => true,
            'edit_sikshya_lessons' => true,
            'edit_published_sikshya_lessons' => true,
            'edit_sikshya_quizzes' => true,
            'edit_published_sikshya_quizzes' => true,
            'upload_files' => true,
            'view_sikshya_reports' => true,
        ]);

        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'edit_sikshya_courses',
                'edit_others_sikshya_courses',
                'publish_sikshya_courses',
                'read_private_sikshya_courses',
                'delete_sikshya_courses',
                'delete_private_sikshya_courses',
                'delete_published_sikshya_courses',
                'delete_others_sikshya_courses',
                'edit_private_sikshya_courses',
                'edit_published_sikshya_courses',
                'edit_sikshya_lessons',
                'edit_others_sikshya_lessons',
                'publish_sikshya_lessons',
                'read_private_sikshya_lessons',
                'delete_sikshya_lessons',
                'delete_private_sikshya_lessons',
                'delete_published_sikshya_lessons',
                'delete_others_sikshya_lessons',
                'edit_private_sikshya_lessons',
                'edit_published_sikshya_lessons',
                'edit_sikshya_quizzes',
                'edit_others_sikshya_quizzes',
                'publish_sikshya_quizzes',
                'read_private_sikshya_quizzes',
                'delete_sikshya_quizzes',
                'delete_private_sikshya_quizzes',
                'delete_published_sikshya_quizzes',
                'delete_others_sikshya_quizzes',
                'edit_private_sikshya_quizzes',
                'edit_published_sikshya_quizzes',
            ];

            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Set default options
     */
    private static function setDefaultOptions(): void
    {
        // General settings
        $general_settings = [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'currency' => 'USD',
            'currency_symbol' => '$',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i a',
            'timezone' => wp_timezone_string(),
        ];

        update_option('sikshya_general_settings', $general_settings);

        // Course settings
        $course_settings = [
            'courses_per_page' => 12,
            'featured_courses_count' => 6,
            'popular_courses_count' => 6,
            'enable_reviews' => true,
            'enable_ratings' => true,
            'enable_certificates' => true,
            'enable_progress_tracking' => true,
            'enable_discussions' => true,
            'enable_assignments' => true,
        ];

        update_option('sikshya_course_settings', $course_settings);

        // Email settings
        $email_settings = [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'welcome_email' => true,
            'course_completion_email' => true,
            'certificate_email' => true,
            'reminder_emails' => true,
        ];

        update_option('sikshya_email_settings', $email_settings);

        // Payment settings
        $payment_settings = [
            'enable_payments' => false,
            'payment_methods' => ['stripe', 'paypal'],
            'test_mode' => true,
            'currency' => 'USD',
        ];

        update_option('sikshya_payment_settings', $payment_settings);

        // Uninstall options
        $uninstall_options = [
            'remove_data' => false,
            'remove_tables' => false,
            'remove_options' => false,
            'remove_files' => false,
        ];

        update_option('sikshya_uninstall_options', $uninstall_options);
    }
} 