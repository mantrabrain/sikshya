<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Asset Management Service
 *
 * @package Sikshya\Services
 */
class AssetService
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
     * Initialize assets
     */
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_head', [$this, 'addCustomCss']);
        add_action('wp_footer', [$this, 'addCustomJs']);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        // Main CSS
        wp_enqueue_style(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('css/frontend.css'),
            [],
            SIKSHYA_VERSION
        );

        // Main JavaScript
        wp_enqueue_script(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('js/frontend.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sikshya-frontend', 'sikshya', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sikshya_nonce'),
            'plugin_url' => $this->plugin->getPluginUrl(),
            'is_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        
        // Check if we're on a Sikshya admin page
        if (!$screen || (!strpos($screen->id, 'sikshya') && !strpos($screen->base, 'sikshya'))) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'sikshya-admin',
            $this->plugin->getAssetUrl('css/admin.css'),
            [],
            SIKSHYA_VERSION
        );

        // Settings CSS - only load on settings page
        if (strpos($screen->id, 'sikshya-settings') !== false) {
            wp_enqueue_style(
                'sikshya-settings',
                $this->plugin->getAssetUrl('css/settings.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );
        }

        // DataTable CSS
        wp_enqueue_style(
            'sikshya-datatable',
            $this->plugin->getAssetUrl('css/admin.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );



        // FormBuilder CSS
        wp_enqueue_style(
            'sikshya-form-builder',
            $this->plugin->getAssetUrl('css/admin.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );

        // Dashboard CSS
        wp_enqueue_style(
            'sikshya-dashboard',
            $this->plugin->getAssetUrl('admin/css/dashboard.css'),
            ['sikshya-admin'],
            SIKSHYA_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'sikshya-admin',
            $this->plugin->getAssetUrl('js/admin.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        // Settings JavaScript - only load on settings page
        if (strpos($screen->id, 'sikshya-settings') !== false) {
            wp_enqueue_script(
                'sikshya-settings',
                $this->plugin->getAssetUrl('js/settings.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );
        }

        // Course Builder CSS and JS - load on Sikshya admin pages
        if (strpos($screen->id, 'sikshya') !== false || strpos($screen->base, 'sikshya') !== false) {
            wp_enqueue_style(
                'sikshya-course-builder',
                $this->plugin->getAssetUrl('css/course-builder.css'),
                ['sikshya-admin'],
                SIKSHYA_VERSION
            );

            wp_enqueue_script(
                'sikshya-course-builder',
                $this->plugin->getAssetUrl('js/course-builder.js'),
                ['jquery', 'sikshya-admin'],
                SIKSHYA_VERSION,
                true
            );

            // Localize course builder script
            wp_localize_script('sikshya-course-builder', 'sikshya_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_course_builder'),
                'plugin_url' => $this->plugin->getPluginUrl(),
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



        // FormBuilder JavaScript
        wp_enqueue_script(
            'sikshya-form-builder',
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

        // Localize script
        wp_localize_script('sikshya-admin', 'sikshya', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sikshya_nonce'),
            'plugin_url' => $this->plugin->getPluginUrl(),
            'admin_url' => admin_url(),
            'is_admin' => is_admin(),
        ]);

        // Localize settings script if on settings page
        if (strpos($screen->id, 'sikshya-settings') !== false || strpos($screen->id, 'sikshya-lms_page_sikshya-settings') !== false) {
            wp_localize_script('sikshya-settings', 'sikshya_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_settings_nonce'),
            ]);
        }
    }

    /**
     * Add custom CSS
     */
    public function addCustomCss(): void
    {
        $custom_css = get_option('sikshya_custom_css', '');
        if (!empty($custom_css)) {
            echo '<style type="text/css">' . esc_html($custom_css) . '</style>';
        }
    }

    /**
     * Add custom JavaScript
     */
    public function addCustomJs(): void
    {
        $custom_js = get_option('sikshya_custom_js', '');
        if (!empty($custom_js)) {
            echo '<script type="text/javascript">' . esc_html($custom_js) . '</script>';
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

    /**
     * Check if asset exists
     *
     * @param string $path
     * @return bool
     */
    public function assetExists(string $path): bool
    {
        return file_exists($this->getAssetPath($path));
    }

    /**
     * Get asset version
     *
     * @param string $path
     * @return string
     */
    public function getAssetVersion(string $path): string
    {
        if ($this->assetExists($path)) {
            return (string) filemtime($this->getAssetPath($path));
        }
        return SIKSHYA_VERSION;
    }
} 