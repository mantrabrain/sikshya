<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
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

        // Modal for legacy PHP settings screen (see Admin.php).
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

        $react_css = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.css';
        $react_js = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.js';
        if (file_exists($react_css)) {
            wp_register_style(
                'sikshya-react-admin',
                $this->plugin->getAssetUrl('admin/react/sikshya-admin.css'),
                [],
                (string) filemtime($react_css)
            );
        }
        wp_register_script(
            'sikshya-react-admin',
            $this->plugin->getAssetUrl('admin/react/sikshya-admin.js'),
            ['jquery'],
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
            wp_enqueue_media();

            wp_enqueue_style('sikshya-react-shell');
            if (wp_style_is('sikshya-react-admin', 'registered')) {
                wp_enqueue_style('sikshya-react-admin');
            }
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
