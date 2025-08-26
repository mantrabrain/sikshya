<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;

/**
 * Tools Controller Class
 *
 * @package Sikshya\Admin\Controllers
 * @since 1.0.0
 */
class ToolsController extends BaseView
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
        add_action('wp_ajax_sikshya_tools_action', [$this, 'handleAjax']);
    }

    /**
     * Render tools page
     */
    public function tools(): void
    {
        $this->data = [
            'page_title' => __('Tools', 'sikshya'),
            'page_description' => __('Manage and maintain your Sikshya LMS installation', 'sikshya'),
        ];
        
        $this->render('tools');
    }

    /**
     * Render help and support page
     */
    public function help(): void
    {
        $this->data = [
            'page_title' => __('Help & Support', 'sikshya'),
            'page_description' => __('Get help and support for Sikshya LMS', 'sikshya'),
        ];
        
        $this->render('help');
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(): void
    {
        error_log('Sikshya ToolsController: handleAjax called');
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        error_log('Sikshya ToolsController: action_type = ' . $action);
        
        switch ($action) {
            case 'clear_cache':
                $this->clearCache();
                break;
            case 'export_data':
                $this->exportData();
                break;
            case 'import_data':
                $this->importData();
                break;
            case 'reset_settings':
                $this->resetSettings();
                break;
            case 'system_info':
                error_log('Sikshya ToolsController: Calling getSystemInfo');
                $this->getSystemInfo();
                break;
            default:
                error_log('Sikshya ToolsController: Invalid action = ' . $action);
                wp_send_json_error(['message' => __('Invalid action', 'sikshya')]);
        }
    }

    /**
     * Clear cache
     */
    private function clearCache(): void
    {
        // Clear WordPress cache
        wp_cache_flush();
        
        // Clear Sikshya specific cache
        delete_transient('sikshya_course_stats');
        delete_transient('sikshya_user_stats');
        delete_transient('sikshya_revenue_stats');
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'sikshya')]);
    }

    /**
     * Export data
     */
    private function exportData(): void
    {
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'courses');
        
        switch ($export_type) {
            case 'courses':
                $data = $this->exportCourses();
                break;
            case 'students':
                $data = $this->exportStudents();
                break;
            case 'instructors':
                $data = $this->exportInstructors();
                break;
            default:
                wp_send_json_error(['message' => __('Invalid export type', 'sikshya')]);
                return;
        }
        
        wp_send_json_success([
            'message' => __('Data exported successfully', 'sikshya'),
            'data' => $data
        ]);
    }

    /**
     * Import data
     */
    private function importData(): void
    {
        $import_type = sanitize_text_field($_POST['import_type'] ?? 'courses');
        $file_data = $_POST['file_data'] ?? '';
        
        if (empty($file_data)) {
            wp_send_json_error(['message' => __('No file data provided', 'sikshya')]);
        }
        
        // TODO: Implement import logic
        wp_send_json_success(['message' => __('Data imported successfully', 'sikshya')]);
    }

    /**
     * Reset settings
     */
    private function resetSettings(): void
    {
        // Reset to default settings
        delete_option('sikshya_settings');
        
        wp_send_json_success(['message' => __('Settings reset to default', 'sikshya')]);
    }

    /**
     * Get system info
     */
    private function getSystemInfo(): void
    {
        error_log('Sikshya ToolsController: getSystemInfo called');
        
        global $wpdb;
        
        $system_info = [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'sikshya_version' => SIKSHYA_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
        
        error_log('Sikshya ToolsController: system_info = ' . print_r($system_info, true));
        
        wp_send_json_success($system_info);
    }

    /**
     * Export courses data
     */
    private function exportCourses(): array
    {
        $courses = get_posts([
            'post_type' => 'sik_course',
            'numberposts' => -1,
            'post_status' => 'any'
        ]);
        
        $data = [];
        foreach ($courses as $course) {
            $data[] = [
                'ID' => $course->ID,
                'title' => $course->post_title,
                'status' => $course->post_status,
                'date_created' => $course->post_date,
                'price' => get_post_meta($course->ID, '_course_price', true),
                'instructor' => get_post_meta($course->ID, '_course_instructor', true),
            ];
        }
        
        return $data;
    }

    /**
     * Export students data
     */
    private function exportStudents(): array
    {
        $students = get_users(['role' => 'subscriber']);
        
        $data = [];
        foreach ($students as $student) {
            $data[] = [
                'ID' => $student->ID,
                'username' => $student->user_login,
                'email' => $student->user_email,
                'display_name' => $student->display_name,
                'date_registered' => $student->user_registered,
            ];
        }
        
        return $data;
    }

    /**
     * Export instructors data
     */
    private function exportInstructors(): array
    {
        $instructors = get_users(['role' => 'author']);
        
        $data = [];
        foreach ($instructors as $instructor) {
            $data[] = [
                'ID' => $instructor->ID,
                'username' => $instructor->user_login,
                'email' => $instructor->user_email,
                'display_name' => $instructor->display_name,
                'date_registered' => $instructor->user_registered,
            ];
        }
        
        return $data;
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-tools');
        wp_enqueue_script('sikshya-tools');
    }
}
