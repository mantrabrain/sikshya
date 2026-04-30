<?php

namespace Sikshya\Core;

use Sikshya\Database\Repositories\PluginLifecycleRepository;
use Sikshya\Services\Settings;

/**
 * Plugin Uninstaller
 *
 * @package Sikshya\Core
 */
class Uninstaller
{
    private const OPT_ERASE_DATA = '_sikshya_erase_data_on_uninstall';
    private const OPT_ERASE_FILES = '_sikshya_erase_files_on_uninstall';

    /**
     * Uninstall the plugin
     */
    public static function uninstall(): void
    {
        // Check if user has permission
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $erase_data = Settings::isTruthy(get_option(self::OPT_ERASE_DATA, '0'));
        $erase_files = Settings::isTruthy(get_option(self::OPT_ERASE_FILES, '0'));

        // Remove custom post types and their data
        if ($erase_data) {
            self::removePostTypes();
        }

        // Remove custom database tables
        if ($erase_data) {
            self::removeTables();
        }

        // Remove plugin options
        if ($erase_data) {
            self::removeOptions();
        }

        // Remove uploaded files
        if ($erase_data && $erase_files) {
            self::removeFiles();
        }

        // Clear any cached data
        self::clearCache();

        // Remove user roles and capabilities
        self::removeRoles();

        // Remove scheduled events
        self::removeScheduledEvents();
    }

    /**
     * Remove custom post types and their data
     */
    private static function removePostTypes(): void
    {
        $post_types = [
            'sikshya_course',
            'sikshya_lesson',
            'sikshya_quiz',
            'sikshya_assignment',
        ];

        foreach ($post_types as $post_type) {
            // Get all posts of this type
            $posts = get_posts([
                'post_type' => $post_type,
                'numberposts' => -1,
                'post_status' => 'any',
            ]);

            // Delete each post
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }

        // Remove taxonomies
        $taxonomies = [
            'sikshya_course_category',
            'sikshya_course_tag',
            'sikshya_lesson_category',
            'sikshya_quiz_category',
        ];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            foreach ($terms as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
    }

    /**
     * Remove custom database tables
     */
    private static function removeTables(): void
    {
        (new PluginLifecycleRepository())->dropAllFreeCustomTables();
    }

    /**
     * Remove plugin options
     */
    private static function removeOptions(): void
    {
        $options = [
            'sikshya_version',
            'sikshya_db_version',
            'sikshya_settings',
            'sikshya_activation_time',
            'sikshya_license_key',
            'sikshya_license_status',
            'sikshya_license_expires',
            'sikshya_analytics_settings',
            'sikshya_email_settings',
            'sikshya_payment_settings',
            'sikshya_certificate_settings',
            'sikshya_quiz_settings',
            'sikshya_lesson_settings',
            'sikshya_course_settings',
            'sikshya_user_settings',
            'sikshya_instructor_settings',
            'sikshya_admin_settings',
            'sikshya_frontend_settings',
            'sikshya_api_settings',
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        delete_option(self::OPT_ERASE_DATA);
        delete_option(self::OPT_ERASE_FILES);

        // Remove site options for multisite
        if (is_multisite()) {
            foreach ($options as $option) {
                delete_site_option($option);
            }

            delete_site_option(self::OPT_ERASE_DATA);
            delete_site_option(self::OPT_ERASE_FILES);
        }
    }

    /**
     * Remove uploaded files
     */
    private static function removeFiles(): void
    {
        $upload_dir = wp_upload_dir();
        $sikshya_dir = $upload_dir['basedir'] . '/sikshya';

        if (is_dir($sikshya_dir)) {
            self::deleteDirectory($sikshya_dir);
        }
    }

    /**
     * Clear cache
     */
    private static function clearCache(): void
    {
        // Clear WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sikshya');
        }

        (new PluginLifecycleRepository())->deleteSikshyaTransients();
    }

    /**
     * Remove user roles and capabilities
     */
    private static function removeRoles(): void
    {
        // Remove custom roles
        $roles = [
            'sikshya_instructor',
            'sikshya_student',
            'sikshya_assistant',
        ];

        foreach ($roles as $role) {
            remove_role($role);
        }

        // Remove capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'sikshya_access_admin_app',
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
                $admin_role->remove_cap($cap);
            }
        }
    }

    /**
     * Remove scheduled events
     */
    private static function removeScheduledEvents(): void
    {
        $events = [
            'sikshya_cleanup_expired_certificates',
            'sikshya_send_reminder_emails',
            'sikshya_update_course_statistics',
            'sikshya_backup_progress_data',
        ];

        foreach ($events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Delete directory recursively
     */
    private static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
