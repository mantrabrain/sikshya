<?php

namespace Sikshya\Admin;

/**
 * Renders the React admin mount point and injects bootstrap config.
 */
final class ReactAdminView
{
    /**
     * Output root + inline config (must run while sikshya-react-admin is enqueued).
     *
     * @param string               $pageKey
     * @param array<string, mixed> $initialData
     */
    public static function render(string $pageKey, array $initialData = []): void
    {
        $config = ReactAdminConfig::build($pageKey, $initialData);

        echo '<div class="wrap sikshya-react-wrap" style="margin:0;max-width:none;padding:0;">';
        echo '<div id="sikshya-admin-root"></div></div>';

        wp_add_inline_script(
            'sikshya-react-admin',
            'window.sikshyaReact = ' . wp_json_encode($config) . ';',
            'before'
        );
    }
}
