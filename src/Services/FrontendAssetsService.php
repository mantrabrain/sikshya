<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Frontend\Public\PublicPageUrls;

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
        if (PublicPageUrls::isCurrentVirtualPage('account')) {
            return;
        }

        wp_enqueue_style(
            'sikshya-public-ds',
            $this->plugin->getAssetUrl('css/public-design-system.css'),
            [],
            SIKSHYA_VERSION
        );

        wp_enqueue_style(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('css/frontend.css'),
            ['sikshya-public-ds'],
            SIKSHYA_VERSION
        );

        // Course listings (archive, taxonomy, shortcodes/blocks that render cards)
        wp_enqueue_style(
            'sikshya-course-listing',
            $this->plugin->getAssetUrl('css/course-listing.css'),
            ['sikshya-public-ds', 'sikshya-frontend'],
            SIKSHYA_VERSION
        );

        if (is_singular(PostTypes::COURSE)) {
            wp_enqueue_style(
                'sikshya-single-course',
                $this->plugin->getAssetUrl('css/single-course.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('cart')) {
            wp_enqueue_style(
                'sikshya-cart',
                $this->plugin->getAssetUrl('css/cart.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('checkout')) {
            wp_enqueue_style(
                'sikshya-checkout',
                $this->plugin->getAssetUrl('css/checkout.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('order')) {
            wp_enqueue_style(
                'sikshya-order',
                $this->plugin->getAssetUrl('css/order.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        // Course Category CSS - only on course category pages
        if (is_tax(Taxonomies::COURSE_CATEGORY)) {
            wp_enqueue_style(
                'sikshya-course-category',
                $this->plugin->getAssetUrl('frontend/css/course-category.css'),
                ['sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        // Main JavaScript
        wp_enqueue_script(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('js/frontend.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );
        // Localization for this handle lives in Frontend::enqueueFrontendAssets (sikshyaFrontend + sikshya_frontend).
    }

    /**
     * Add custom CSS
     */
    public function addCustomCss(): void
    {
        $custom_css = Settings::getRaw('sikshya_custom_css', '');
        if (!empty($custom_css)) {
            echo '<style type="text/css">' . esc_html($custom_css) . '</style>';
        }
    }

    /**
     * Add custom JavaScript
     */
    public function addCustomJs(): void
    {
        $custom_js = Settings::getRaw('sikshya_custom_js', '');
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
