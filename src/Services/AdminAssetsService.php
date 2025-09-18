<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\AdminPages;

/**
 * Admin Asset Management Service
 *
 * @package Sikshya\Services
 */
class AdminAssetsService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Initialize admin assets
     */
    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_init', [$this, 'registerAdminAssets']);
    }

    /**
     * Register admin assets
     */
    public function registerAdminAssets(): void
    {
        // Register toast assets
        wp_register_style(
            'sikshya-admin',
            $this->plugin->getAssetUrl('admin/css/admin.css'),
            [],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-admin',
            $this->plugin->getAssetUrl('admin/js/admin.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );
        
        
        
        wp_register_style(
            'sikshya-toast',
            $this->plugin->getAssetUrl('admin/css/toast.css'),
            [],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-toast',
            $this->plugin->getAssetUrl('admin/js/toast.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register course builder save script
        wp_register_script(
            'sikshya-course-builder-save',
            $this->plugin->getAssetUrl('admin/js/course-builder-save.js'),
            ['jquery', 'sikshya-toast'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register list table assets
        wp_register_style(
            'sikshya-admin-list-table',
            $this->plugin->getAssetUrl('admin/css/list-table.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-admin-list-table',
            $this->plugin->getAssetUrl('admin/js/list-table.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register reports assets
        wp_register_style(
            'sikshya-reports',
            $this->plugin->getAssetUrl('admin/css/reports.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-reports',
            $this->plugin->getAssetUrl('admin/js/reports.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register tools assets
        wp_register_style(
            'sikshya-tools',
            $this->plugin->getAssetUrl('admin/css/tools.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-tools',
            $this->plugin->getAssetUrl('admin/js/tools.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register help assets
        wp_register_style(
            'sikshya-help',
            $this->plugin->getAssetUrl('admin/css/help.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-help',
            $this->plugin->getAssetUrl('admin/js/help.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register categories assets
        wp_register_style(
            'sikshya-categories',
            $this->plugin->getAssetUrl('admin/css/categories.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-categories',
            $this->plugin->getAssetUrl('admin/js/categories.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register lessons assets
        wp_register_style(
            'sikshya-lessons',
            $this->plugin->getAssetUrl('admin/css/lessons.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-lessons',
            $this->plugin->getAssetUrl('admin/js/lessons.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );

        // Register modal assets
        wp_register_style(
            'sikshya-modal',
            $this->plugin->getAssetUrl('admin/css/modal.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );
        
        wp_register_script(
            'sikshya-modal',
            $this->plugin->getAssetUrl('admin/js/modal.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );
        
        // Register Chart.js
        wp_register_script(
            'sikshya-charts',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '3.9.1',
            true
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        
        // Check if we're on a Sikshya admin page
        if (!$screen) {
            return;
        }
        
        if (strpos($screen->id, 'sikshya') === false && strpos($screen->base, 'sikshya') === false) {
            return;
        }
       
         // Settings CSS - only load on settings page
        if (strpos($screen->id, AdminPages::SETTINGS) !== false) {
            wp_enqueue_style(
                'sikshya-settings',
                $this->plugin->getAssetUrl('admin/css/settings.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );
            wp_enqueue_script(
                'sikshya-settings',
                $this->plugin->getAssetUrl('admin/js/settings.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );
        }

    

        // Dashboard CSS
        wp_enqueue_style(
            'sikshya-dashboard',
            $this->plugin->getAssetUrl('admin/css/dashboard.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );

        // Tools CSS
        if (strpos($screen->id, AdminPages::TOOLS) !== false) {
            wp_enqueue_style(
                'sikshya-tools',
                $this->plugin->getAssetUrl('admin/css/tools.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );
        }

        // Help CSS
        if (strpos($screen->id, AdminPages::HELP) !== false) {
            wp_enqueue_style(
                'sikshya-help',
                $this->plugin->getAssetUrl('admin/css/help.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );
        }

        // Reports CSS
        if (strpos($screen->id, AdminPages::REPORTS) !== false) {
            wp_enqueue_style(
                'sikshya-reports',
                $this->plugin->getAssetUrl('admin/css/reports.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );
        }

        // List table assets for list table pages
        if (in_array($screen->id, [
            'sikshya-lms_page_' . AdminPages::COURSES,
            'sikshya-lms_page_' . AdminPages::LESSONS,
            'sikshya-lms_page_' . AdminPages::QUIZZES,
            'sikshya-lms_page_' . AdminPages::STUDENTS,
            'sikshya-lms_page_' . AdminPages::INSTRUCTORS
        ])) {
            wp_enqueue_style('sikshya-admin-list-table');
            wp_enqueue_script('sikshya-admin-list-table');
            
            // Localize list table script
            wp_localize_script('sikshya-admin-list-table', 'sikshya_list_table', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_list_table_nonce'),
                'confirm_delete_message' => __('Are you sure you want to delete this item?', 'sikshya'),
                'error_message' => __('An error occurred. Please try again.', 'sikshya'),
            ]);
        }
        
        // Categories assets for categories page
        if (strpos($screen->id, AdminPages::COURSE_CATEGORIES) !== false) {
            // Enqueue dashboard styles for consistent header styling
            wp_enqueue_style('sikshya-dashboard');
            wp_enqueue_style('sikshya-categories');
            wp_enqueue_script('sikshya-categories');
            
            // Localize categories script
            wp_localize_script('sikshya-categories', 'sikshyaAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_admin_nonce'),
            ]);
        }

        // Lessons assets for lesson pages
        if (strpos($screen->id, AdminPages::LESSONS) !== false || strpos($screen->id, AdminPages::ADD_LESSON) !== false) {
            wp_enqueue_style('sikshya-lessons');
            wp_enqueue_script('sikshya-lessons');
            
            // Enqueue modal assets for lessons
            wp_enqueue_style('sikshya-modal');
            wp_enqueue_script('sikshya-modal');
            
            // Localize lessons script
            wp_localize_script('sikshya-lessons', 'sikshya_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_lesson'),
                'admin_url' => admin_url(),
                'add_lesson_page' => AdminPages::ADD_LESSON,
            ]);
        }

        // Settings JavaScript - only load on settings page
        if (strpos($screen->id, AdminPages::SETTINGS) !== false) {
           
        }

        // Course Builder CSS and JS - load on Sikshya admin pages
        if (strpos($screen->id, AdminPages::ADD_COURSE) !== false) {
            wp_enqueue_style(
                'sikshya-course-builder',
                $this->plugin->getAssetUrl('admin/css/course-builder.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );

            // Enqueue lessons CSS for lesson forms in course builder
            wp_enqueue_style(
                'sikshya-lessons',
                $this->plugin->getAssetUrl('admin/css/lessons.css'),
                ['sikshya-course-builder'],
                SIKSHYA_VERSION
            );

            wp_enqueue_script(
                'sikshya-course-builder',
                $this->plugin->getAssetUrl('admin/js/course-builder.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );

        // Enqueue centralized modal system
        wp_enqueue_style(
            'sikshya-modal',
            $this->plugin->getAssetUrl('admin/css/modal.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );

        wp_enqueue_script(
            'sikshya-modal',
            $this->plugin->getAssetUrl('admin/js/modal.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        // Enqueue alert system
        wp_enqueue_style(
            'sikshya-alert-system',
            $this->plugin->getAssetUrl('admin/css/alert-system.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );

        wp_enqueue_script(
            'sikshya-alert-system',
            $this->plugin->getAssetUrl('admin/js/alert-system.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

            // Enqueue lessons JavaScript for lesson forms in course builder
            wp_enqueue_script(
                'sikshya-lessons',
                $this->plugin->getAssetUrl('admin/js/lessons.js'),
                ['jquery', 'sikshya-course-builder', 'sikshya-modal'],
                SIKSHYA_VERSION,
                true
            );

            // Enqueue toast assets for course builder
            wp_enqueue_style('sikshya-toast');
            wp_enqueue_script('sikshya-toast');
            
            // Enqueue course builder save script
            wp_enqueue_script('sikshya-course-builder-save');

            // Localize course builder script
            wp_localize_script('sikshya-course-builder-save', 'sikshya_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_course_builder_nonce'),
                'plugin_url' => $this->plugin->getPluginUrl(),
                'debug' => true,
            ]);
        }

        // DataTable JavaScript
        wp_enqueue_script(
            'sikshya-datatable',
            $this->plugin->getAssetUrl('js/admin.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );

        // Dashboard JavaScript
        wp_enqueue_script(
            'sikshya-dashboard',
            $this->plugin->getAssetUrl('js/admin.js'),
            ['jquery', 'sikshya-admin'],
            SIKSHYA_VERSION,
            true
        );

        // Tools JavaScript
        if (strpos($screen->id, AdminPages::TOOLS) !== false) {
            wp_enqueue_script(
                'sikshya-tools',
                $this->plugin->getAssetUrl('admin/js/tools.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );
        }

        // Help JavaScript
        if (strpos($screen->id, AdminPages::HELP) !== false) {
            wp_enqueue_script(
                'sikshya-help',
                $this->plugin->getAssetUrl('admin/js/help.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );
        }

        // Reports JavaScript
        if (strpos($screen->id, AdminPages::REPORTS) !== false) {
            wp_enqueue_script(
                'sikshya-reports',
                $this->plugin->getAssetUrl('admin/js/reports.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );

            // Enqueue Chart.js for reports
            wp_enqueue_script(
                'sikshya-charts',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.9.1',
                true
            );
        }

        // Localize script
        wp_localize_script('sikshya-admin', 'sikshya', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sikshya_nonce'),
            'plugin_url' => $this->plugin->getPluginUrl(),
            'admin_url' => admin_url(),
            'is_admin' => is_admin(),
        ]);

        // Localize settings script if on settings page
        if (strpos($screen->id, AdminPages::SETTINGS) !== false) {
            wp_localize_script('sikshya-settings', 'sikshya_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_settings_nonce'),
            ]);
        }
    }

    /**
     * Get asset URL
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path = ''): string
    {
        return $this->plugin->getAssetUrl($path);
    }

    /**
     * Get asset path
     *
     * @param string $path
     * @return string
     */
    public function getAssetPath(string $path = ''): string
    {
        return $this->plugin->getAssetPath($path);
    }
}
