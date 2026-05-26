<?php

namespace Sikshya\Blocks;

use Sikshya\Core\Plugin;
use Sikshya\Shortcodes\AuthShortcodes;
use Sikshya\Shortcodes\CoursesShortcode;

/**
 * Registers Sikshya Gutenberg blocks (dynamic; same output as shortcodes).
 *
 * @package Sikshya\Blocks
 */
final class BlocksRegistrar
{
    private static bool $booted = false;

    /**
     * @var Plugin
     */
    private $plugin;

    public static function init(Plugin $plugin): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $instance = new self($plugin);
        PublicBlockStyles::init($plugin);
        add_action('init', [$instance, 'registerBlocks']);
        add_filter('block_categories_all', [$instance, 'registerBlockCategory'], 10, 2);
        add_action('enqueue_block_editor_assets', [$instance, 'enqueueEditorAssets']);
    }

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function registerBlockCategory(array $categories, $editor_context): array
    {
        unset($editor_context);

        $exists = false;
        foreach ($categories as $cat) {
            if (is_array($cat) && ($cat['slug'] ?? '') === 'sikshya') {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            return $categories;
        }

        return array_merge(
            [
                [
                    'slug' => 'sikshya',
                    'title' => __('Sikshya', 'sikshya'),
                    'icon' => null,
                ],
            ],
            $categories
        );
    }

    public function registerBlocks(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        $this->registerEditorScript();

        $base = $this->plugin->getPluginPath() . 'blocks';

        register_block_type(
            $base . '/sikshya-courses',
            [
                'render_callback' => static function (array $attributes): string {
                    return BlockPreviewMarkup::wrap(CoursesShortcode::render($attributes));
                },
            ]
        );

        register_block_type(
            $base . '/sikshya-login',
            [
                'render_callback' => static function (array $attributes): string {
                    return BlockPreviewMarkup::wrap(AuthShortcodes::renderLogin($attributes), 'auth');
                },
            ]
        );

        register_block_type(
            $base . '/sikshya-registration',
            [
                'render_callback' => static function (array $attributes): string {
                    return BlockPreviewMarkup::wrap(AuthShortcodes::renderRegistration($attributes), 'auth');
                },
            ]
        );
    }

    public function enqueueEditorAssets(): void
    {
        $this->registerEditorScript();
        PublicBlockStyles::registerStyles();

        wp_enqueue_script('sikshya-blocks-editor');
        wp_enqueue_style('sikshya-blocks-editor');

        /*
         * Public course/auth CSS is attached via PublicBlockStyles (iframe + front end).
         * Enqueue here as well so ServerSideRender in older editor shells still picks up styles.
         */
        wp_enqueue_style('sikshya-public-blocks');
        wp_enqueue_style('sikshya-blocks-editor-preview');
    }

    private function registerEditorScript(): void
    {
        if (wp_script_is('sikshya-blocks-editor', 'registered')) {
            return;
        }

        wp_register_script(
            'sikshya-blocks-editor',
            $this->plugin->getAssetUrl('blocks/editor.js'),
            [
                'wp-blocks',
                'wp-block-editor',
                'wp-components',
                'wp-element',
                'wp-server-side-render',
                'wp-i18n',
                'wp-hooks',
            ],
            SIKSHYA_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('sikshya-blocks-editor', 'sikshya', $this->plugin->getPluginPath() . 'languages');
        }

        wp_register_style(
            'sikshya-blocks-editor',
            $this->plugin->getAssetUrl('blocks/editor.css'),
            ['wp-edit-blocks'],
            SIKSHYA_VERSION
        );
    }
}
