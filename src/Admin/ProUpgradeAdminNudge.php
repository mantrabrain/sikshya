<?php

namespace Sikshya\Admin;

use Sikshya\Constants\AdminPages;

/**
 * “Upgrade to Pro” entry points when no commercial Sikshya tier is active.
 *
 * @package Sikshya\Admin
 */
final class ProUpgradeAdminNudge
{
    private const MENU_SLUG = 'sikshya-upgrade-pro';

    public static function register(): void
    {
        if (!defined('SIKSHYA_PLUGIN_BASENAME')) {
            return;
        }

        add_action('admin_menu', [self::class, 'registerSubmenu'], 100);
        add_action('admin_head', [self::class, 'printAdminStyles'], 99);
        add_action('admin_print_footer_scripts', [self::class, 'printFooterMenuLinkPatch'], 5);
        add_filter('plugin_action_links_' . SIKSHYA_PLUGIN_BASENAME, [self::class, 'pluginActionLinks'], 10, 2);
        add_filter('network_admin_plugin_action_links_' . SIKSHYA_PLUGIN_BASENAME, [self::class, 'pluginActionLinks'], 10, 2);
    }

    /**
     * Whether to show upgrade CTAs.
     *
     * Uses presence of the Sikshya Pro plugin bootstrap (not license / TierCapabilities::isActive()),
     * so “Upgrade to Pro” hides as soon as Pro is activated.
     */
    private static function shouldShow(): bool
    {
        if (defined('SIKSHYA_PRO_FILE') && SIKSHYA_PRO_FILE) {
            return false;
        }

        /**
         * Commercial extension present without the default `SIKSHYA_PRO_FILE` constant (custom builds).
         *
         * @param bool $installed Default false.
         */
        if ((bool) apply_filters('sikshya_commercial_extension_installed', false)) {
            return false;
        }

        return true;
    }

    /**
     * Public pricing / upgrade destination (filterable).
     *
     * Defaults align with commercial upgrade URL filters used elsewhere (`sikshya_commercial_upgrade_url`).
     */
    public static function upgradeUrl(): string
    {
        $default = 'https://mantrabrain.com/plugins/sikshya/#pricing';
        $from_commercial = apply_filters('sikshya_commercial_upgrade_url', $default);
        $base = is_string($from_commercial) && $from_commercial !== '' ? $from_commercial : $default;

        /**
         * URL for “Upgrade to Pro” admin menu / plugin row links.
         *
         * @param string $url Resolved from `sikshya_commercial_upgrade_url` first.
         */
        $url = apply_filters('sikshya_upgrade_pro_url', $base);
        $url = is_string($url) ? $url : $base;
        $out = esc_url_raw($url);

        return $out !== '' ? $out : esc_url_raw($default);
    }

    public static function registerSubmenu(): void
    {
        if (!self::shouldShow()) {
            return;
        }

        $react_menu_cap = (string) apply_filters(
            'sikshya_react_admin_menu_capability',
            'sikshya_access_admin_app'
        );

        add_submenu_page(
            AdminPages::DASHBOARD,
            __('Upgrade to Pro', 'sikshya'),
            __('Upgrade to Pro', 'sikshya'),
            $react_menu_cap,
            self::MENU_SLUG,
            '__return_empty_string'
        );
    }

    /**
     * Point the submenu anchor at the marketing URL (avoids an internal admin redirect).
     */
    public static function printFooterMenuLinkPatch(): void
    {
        if (!is_admin() || !self::shouldShow()) {
            return;
        }

        $url = self::upgradeUrl();
        if ($url === '') {
            return;
        }

        $sel = '#adminmenu a[href*="page=' . self::MENU_SLUG . '"]';
        $sel_json = wp_json_encode($sel);
        $url_json = wp_json_encode($url);
        if (!is_string($sel_json) || !is_string($url_json)) {
            return;
        }

        echo '<script id="sikshya-upgrade-pro-menu-href">';
        printf(
            'document.querySelectorAll(%1$s).forEach(function(a){a.setAttribute("href",%2$s);a.setAttribute("target","_blank");a.setAttribute("rel","noopener noreferrer");a.classList.add("sikshya-upgrade-pro-menu-link");});',
            $sel_json,
            $url_json
        );
        echo '</script>';
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public static function pluginActionLinks(array $links, string $file): array
    {
        unset($file);

        if (!self::shouldShow()) {
            return $links;
        }

        $url = self::upgradeUrl();
        $label = esc_html__('Upgrade to Pro', 'sikshya');
        $links['sikshya_upgrade_pro'] = sprintf(
            '<a href="%1$s" class="sikshya-upgrade-pro-action-link" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url($url),
            $label
        );

        return $links;
    }

    public static function printAdminStyles(): void
    {
        if (!is_admin() || !self::shouldShow()) {
            return;
        }

        $slug = self::MENU_SLUG;
        // Submenu link: match internal slug before JS runs, and `.sikshya-upgrade-pro-menu-link` after href is rewritten to the marketing URL.
        $css = <<<CSS
#adminmenu #toplevel_page_sikshya .wp-submenu a.sikshya-upgrade-pro-menu-link,
#adminmenu #toplevel_page_sikshya .wp-submenu a.sikshya-upgrade-pro-menu-link:focus,
#adminmenu #toplevel_page_sikshya .wp-submenu a.sikshya-upgrade-pro-menu-link:hover,
#adminmenu #toplevel_page_sikshya .wp-submenu a[href*="page={$slug}"],
#adminmenu #toplevel_page_sikshya .wp-submenu a[href*="page={$slug}"]:focus {
    color: #d97706 !important;
    font-weight: 600;
}
#adminmenu #toplevel_page_sikshya .wp-submenu a.sikshya-upgrade-pro-menu-link:hover,
#adminmenu #toplevel_page_sikshya .wp-submenu a[href*="page={$slug}"]:hover {
    color: #b45309 !important;
}
body.folded #adminmenu #toplevel_page_sikshya .wp-submenu a.sikshya-upgrade-pro-menu-link {
    color: #d97706 !important;
}
a.sikshya-upgrade-pro-action-link,
a.sikshya-upgrade-pro-action-link:focus {
    color: #d97706 !important;
    font-weight: 600;
}
a.sikshya-upgrade-pro-action-link:hover {
    color: #b45309 !important;
}
CSS;

        echo '<style id="sikshya-upgrade-pro-nudge">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS only.
    }
}
