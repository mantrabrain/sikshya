<?php

namespace Sikshya\Blocks;

use Sikshya\Core\Plugin;

/**
 * Registers and attaches Sikshya public CSS to Gutenberg blocks.
 *
 * WordPress 6.3+ renders the post editor in an iframe; styles from
 * enqueue_block_editor_assets load in the parent document only. Per-block
 * styles via wp_enqueue_block_style() load inside the editor canvas iframe
 * and on the front end when a block is present.
 *
 * @package Sikshya\Blocks
 */
final class PublicBlockStyles
{
    private const BLOCK_NAMES = [
        'sikshya/courses',
        'sikshya/login',
        'sikshya/registration',
    ];

    /**
     * @var Plugin|null
     */
    private static $plugin;

    public static function init(Plugin $plugin): void
    {
        self::$plugin = $plugin;

        add_action('init', [self::class, 'registerStyles'], 9);
        add_action('init', [self::class, 'attachBlockStyles'], 20);
        add_action('enqueue_block_assets', [self::class, 'enqueueInBlockEditorCanvas']);
    }

    public static function registerStyles(): void
    {
        $plugin = self::plugin();
        $version = $plugin->version ?? SIKSHYA_VERSION;

        if (!wp_style_is('sikshya-public-ds', 'registered')) {
            wp_register_style(
                'sikshya-public-ds',
                $plugin->getAssetUrl('css/public-design-system.css'),
                [],
                $version
            );
        }

        if (!wp_style_is('sikshya-frontend', 'registered')) {
            wp_register_style(
                'sikshya-frontend',
                $plugin->getAssetUrl('css/frontend.css'),
                ['sikshya-public-ds'],
                $version
            );
        }

        if (!wp_style_is('sikshya-course-listing', 'registered')) {
            wp_register_style(
                'sikshya-course-listing',
                $plugin->getAssetUrl('css/course-listing.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $version
            );
        }

        if (!wp_style_is('sikshya-public-blocks', 'registered')) {
            wp_register_style(
                'sikshya-public-blocks',
                false,
                [
                    'sikshya-public-ds',
                    'sikshya-frontend',
                    'sikshya-course-listing',
                    'dashicons',
                ],
                $version
            );
        }

        if (!wp_style_is('sikshya-blocks-editor-preview', 'registered')) {
            wp_register_style(
                'sikshya-blocks-editor-preview',
                $plugin->getAssetUrl('blocks/editor-preview.css'),
                ['sikshya-public-blocks'],
                $version
            );
        }
    }

    public static function attachBlockStyles(): void
    {
        if (!function_exists('wp_enqueue_block_style')) {
            return;
        }

        self::registerStyles();

        $plugin = self::plugin();
        $args = [
            'handle' => 'sikshya-public-blocks',
            'src' => $plugin->getAssetUrl('css/public-design-system.css'),
            'path' => $plugin->getAssetPath('css/public-design-system.css'),
            'version' => $plugin->version ?? SIKSHYA_VERSION,
        ];

        foreach (self::BLOCK_NAMES as $block_name) {
            wp_enqueue_block_style($block_name, $args);
        }
    }

    /**
     * Ensures preview CSS is available inside the editor canvas iframe.
     */
    public static function enqueueInBlockEditorCanvas(): void
    {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('wp_should_load_block_editor_scripts_and_styles')
            || !wp_should_load_block_editor_scripts_and_styles()
        ) {
            return;
        }

        self::registerStyles();
        wp_enqueue_style('sikshya-public-blocks');
        wp_enqueue_style('sikshya-blocks-editor-preview');
    }

    private static function plugin(): Plugin
    {
        if (!self::$plugin instanceof Plugin) {
            throw new \RuntimeException('PublicBlockStyles was not initialized.');
        }

        return self::$plugin;
    }
}
