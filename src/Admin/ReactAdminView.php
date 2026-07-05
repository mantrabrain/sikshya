<?php

namespace Sikshya\Admin;

use Sikshya\Constants\PostTypes;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Renders the React admin mount point and injects bootstrap config.
 */
final class ReactAdminView
{
    /**
     * Full-page certificate builder: skip the default Sikshya boot placeholder so an
     * outer/host loading UI (or blank paint) is the only pre-hydration state.
     *
     * Matches `post_type` on the URL and a fallback when only `post_id` is present (deep links).
     */
    private static function shouldSkipBootLoader(string $pageKey): bool
    {
        if ($pageKey !== 'edit-content') {
            return false;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
        if ($post_type === PostTypes::CERTIFICATE) {
            return true;
        }

        $post_id = isset($_GET['post_id']) ? absint(wp_unslash((string) ($_GET['post_id'] ?? ''))) : 0;
        if ($post_id > 0 && get_post_type($post_id) === PostTypes::CERTIFICATE) {
            return true;
        }

        $legacy_id = isset($_GET['id']) ? absint(wp_unslash((string) ($_GET['id'] ?? ''))) : 0;
        if ($legacy_id > 0 && get_post_type($legacy_id) === PostTypes::CERTIFICATE) {
            return true;
        }

        return false;
    }

    /**
     * Output root + inline config (must run while sikshya-react-admin is enqueued).
     *
     * @param string               $pageKey
     * @param array<string, mixed> $initialData
     */
    public static function render(string $pageKey, array $initialData = []): void
    {
        $config = ReactAdminConfig::build($pageKey, $initialData);
        $skip_boot_loader = self::shouldSkipBootLoader($pageKey);

        /*
         * Boot splash styles live in the enqueued `sikshya-react-admin` bundle (`client/src/index.css`) so the same
         * `.sikshya-admin-boot-loader` / `.sikshya-admin-boot-spinner` classes match React `Suspense` fallbacks (single UI).
         * The same rules are also inlined below so the splash is centered the *moment* HTML paints — before the
         * bundled CSS finishes downloading.
         */

        echo '<div class="wrap sikshya-react-wrap" style="margin:0;max-width:none;padding:0;">';
        echo '<div id="sikshya-admin-root">';
        if (!$skip_boot_loader) {
            echo '<style id="sikshya-admin-boot-loader-inline">'
                . '.sikshya-admin-boot-loader{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;margin:0;box-sizing:border-box;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.4;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;min-height:calc(100vh - 46px);width:100%}'
                . '.sikshya-admin-boot-spinner{box-sizing:border-box;width:40px;height:40px;border-radius:50%;border:3px solid #e2e8f0;border-top-color:#5078b7;animation:sikshya-admin-boot-spin .75s linear infinite;display:block}'
                . '@keyframes sikshya-admin-boot-spin{to{transform:rotate(360deg)}}'
                . '.sikshya-admin-boot-loader .screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
                . '</style>';
            echo '<div class="sikshya-admin-boot-loader" role="status" aria-busy="true" aria-live="polite">';
            echo '<span class="sikshya-admin-boot-spinner" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__('Loading Sikshya…', 'sikshya') . '</span>';
            echo '</div>';
        }
        echo '</div></div>';

        wp_add_inline_script(
            'sikshya-react-admin',
            'window.sikshyaReact=window.sikshyaReact||{};window.sikshyaReact=Object.assign(window.sikshyaReact,' . wp_json_encode($config) . ');window.sikshyaReact.user=window.sikshyaReact.user||{name:"Admin",avatarUrl:""};window.sikshyaReact.user.name=window.sikshyaReact.user.name||"Admin";window.sikshyaReact.user.avatarUrl=window.sikshyaReact.user.avatarUrl||"";',
            'before'
        );
    }
}
