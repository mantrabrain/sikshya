<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Frontend Asset Management Service
 *
 * @package Sikshya\Services
 */
class FrontendAssetsService
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
     * Initialize frontend assets
     */
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
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
}
