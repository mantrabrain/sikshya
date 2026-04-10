<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\AdminPages;
use Sikshya\Constants\PostTypes;

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

        wp_register_style(
            'sikshya-admin-shell',
            $this->plugin->getAssetUrl('admin/css/admin-shell.css'),
            ['sikshya-admin', 'dashicons'],
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

        wp_register_script(
            'sikshya-course-builder-fields',
            $this->plugin->getAssetUrl('admin/js/course-builder-fields.js'),
            [],
            SIKSHYA_VERSION,
            true
        );

        // Register course builder save script
        wp_register_script(
            'sikshya-course-builder-save',
            $this->plugin->getAssetUrl('admin/js/course-builder-save.js'),
            ['jquery', 'sikshya-toast', 'sikshya-course-builder-fields'],
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
            ['jquery'],
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

        $react_css = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.css';
        $react_js = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.js';
        wp_register_style(
            'sikshya-react-admin',
            $this->plugin->getAssetUrl('admin/react/sikshya-admin.css'),
            [],
            file_exists($react_css) ? (string) filemtime($react_css) : SIKSHYA_VERSION
        );
        // Load after `media-audiovideo` so `wp.media` + `media-views` + `media-editor` are fully initialized
        // (see wp_enqueue_media() in core — do not depend on `media-editor` alone).
        wp_register_script(
            'sikshya-react-admin',
            $this->plugin->getAssetUrl('admin/react/sikshya-admin.js'),
            ['jquery', 'media-audiovideo'],
            file_exists($react_js) ? (string) filemtime($react_js) : SIKSHYA_VERSION,
            true
        );

        wp_register_style(
            'sikshya-react-shell',
            $this->plugin->getAssetUrl('admin/css/react-shell.css'),
            [],
            SIKSHYA_VERSION
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

        $sikshya_post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];
        $is_sikshya_post_screen = isset($screen->post_type)
            && in_array($screen->post_type, $sikshya_post_types, true);

        $screen_id = (string) ($screen->id ?? '');
        $screen_base = (string) ($screen->base ?? '');

        // React shell bundle: only the unified Sikshya app screen (subpages use `view=`).
        if ($screen_id === 'toplevel_page_sikshya') {
            // Register media scripts + localize `media-views` before our bundle (core comment in media.php #24724).
            wp_enqueue_media();
            wp_enqueue_style('sikshya-react-shell');
            wp_enqueue_style('sikshya-react-admin');
            wp_enqueue_script('sikshya-react-admin');

            wp_enqueue_style('sikshya-toast');
            wp_enqueue_script('sikshya-toast');

            return;
        }

        if (
            strpos($screen_id, 'sikshya') === false
            && strpos($screen_base, 'sikshya') === false
            && !$is_sikshya_post_screen
        ) {
            return;
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
