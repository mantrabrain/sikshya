<?php

namespace Sikshya\Admin;

/**
 * Renders the React admin mount point and injects bootstrap config.
 */
final class ReactAdminView
{
    /**
     * Critical CSS so a centered spinner appears before executed JS/CSS (avoids empty whitespace flash).
     */
    private static function bootSpinnerInlineCss(): string
    {
        return '@keyframes sikshya-admin-boot-spin{to{transform:rotate(360deg)}}'
            . '.sikshya-admin-boot-loader{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;margin:0;box-sizing:border-box;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.4;font-family:system-ui,-apple-system,sans-serif;}'
            . 'body.sikshya-react-shell .sikshya-admin-boot-loader{min-height:100vh;}body:not(.sikshya-react-shell) .sikshya-admin-boot-loader{min-height:calc(100vh - 32px);}'
            . 'body.admin-bar:not(.sikshya-react-shell) .sikshya-admin-boot-loader{min-height:calc(100vh - 46px);}'
            . '.sikshya-admin-boot-spinner{box-sizing:border-box;width:40px;height:40px;border-radius:50%;border:3px solid #e2e8f0;border-top-color:#3b82f6;animation:sikshya-admin-boot-spin .75s linear infinite;}';
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

        /*
         * Print boot CSS inline in `<body>` (not wp_add_inline_style on the stylesheet handle): the React view
         * wrapper renders after `<head>` has already flushed, so head-attached extras would arrive too late for FCP.
         */
        echo '<style id="sikshya-admin-boot-loader-css">' . self::bootSpinnerInlineCss() . '</style>';

        echo '<div class="wrap sikshya-react-wrap" style="margin:0;max-width:none;padding:0;">';
        echo '<div id="sikshya-admin-root">';
        echo '<div class="sikshya-admin-boot-loader" role="status" aria-busy="true" aria-live="polite">';
        echo '<span class="sikshya-admin-boot-spinner" aria-hidden="true"></span>';
        echo '<span class="screen-reader-text">' . esc_html__('Loading Sikshya…', 'sikshya') . '</span>';
        echo '</div></div></div>';

        wp_add_inline_script(
            'sikshya-react-admin',
            'window.sikshyaReact=window.sikshyaReact||{};window.sikshyaReact=Object.assign(window.sikshyaReact,' . wp_json_encode($config) . ');window.sikshyaReact.user=window.sikshyaReact.user||{name:"Admin",avatarUrl:""};window.sikshyaReact.user.name=window.sikshyaReact.user.name||"Admin";window.sikshyaReact.user.avatarUrl=window.sikshyaReact.user.avatarUrl||"";',
            'before'
        );
    }
}
