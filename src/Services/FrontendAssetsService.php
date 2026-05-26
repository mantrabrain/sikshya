<?php

namespace Sikshya\Services;

use Sikshya\Blocks\ContentHasSikshyaBlock;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Services\PermalinkService;
use Sikshya\Shortcodes\AuthShortcodes;

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

        if ($this->shouldEnqueueCourseListingStyles()) {
            wp_enqueue_style(
                'sikshya-course-listing',
                $this->plugin->getAssetUrl('css/course-listing.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                SIKSHYA_VERSION
            );
        }

        if ($this->shouldEnqueueAuthAssets()) {
            AuthShortcodes::registerPublicScript();
            wp_enqueue_script('sikshya-auth-public');
        }

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
        if ($custom_css === '') {
            return;
        }

        if (wp_style_is('sikshya-frontend', 'enqueued')) {
            wp_add_inline_style('sikshya-frontend', $custom_css);
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Site-admin CSS from settings.
        echo '<style id="sikshya-custom-css">' . $custom_css . '</style>';
    }

    /**
     * Add custom JavaScript
     */
    public function addCustomJs(): void
    {
        $custom_js = Settings::getRaw('sikshya_custom_js', '');
        if ($custom_js === '') {
            return;
        }

        if (wp_script_is('sikshya-frontend', 'registered')) {
            wp_enqueue_script('sikshya-frontend');
            wp_add_inline_script('sikshya-frontend', $custom_js);
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Site-admin JS from settings.
        echo '<script id="sikshya-custom-js">' . $custom_js . '</script>';
    }

    private function shouldEnqueueCourseListingStyles(): bool
    {
        if (is_post_type_archive(PostTypes::COURSE)) {
            return true;
        }

        if (is_tax([Taxonomies::COURSE_CATEGORY, Taxonomies::COURSE_TAG])) {
            return true;
        }

        if (is_page('sikshya-courses')) {
            return true;
        }

        if ((string) get_query_var(PermalinkService::INSTRUCTOR_VAR) !== '') {
            return true;
        }

        return ContentHasSikshyaBlock::hasCoursesListing();
    }

    private function shouldEnqueueAuthAssets(): bool
    {
        if (PublicPageUrls::isCurrentVirtualPage('checkout')
            || PublicPageUrls::isCurrentVirtualPage('login')
        ) {
            return true;
        }

        return ContentHasSikshyaBlock::hasAuth();
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
