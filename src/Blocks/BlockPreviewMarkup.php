<?php

namespace Sikshya\Blocks;

/**
 * Wraps dynamic block HTML for editor SSR previews.
 *
 * @package Sikshya\Blocks
 */
final class BlockPreviewMarkup
{
    public static function wrap(string $html, string $context = 'default'): string
    {
        if ($html === '') {
            return $html;
        }

        if (!self::isEditorServerRender()) {
            return $html;
        }

        $classes = 'sikshya-block-editor-canvas sik-f-scope';

        if ($context === 'auth') {
            $classes .= ' sikshya-block-editor-canvas--auth-card';
            $html = '<div class="sikshya-login-page__card">' . $html . '</div>';
        }

        return '<div class="' . esc_attr($classes) . '">' . $html . '</div>';
    }

    private static function isEditorServerRender(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        return function_exists('wp_is_block_editor') && wp_is_block_editor();
    }
}
