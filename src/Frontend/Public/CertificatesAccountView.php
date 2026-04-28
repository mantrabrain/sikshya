<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Core\Plugin;
use Sikshya\Services\LearnerCertificateService;

/**
 * Learner account: Certificates view + sidebar link.
 *
 * @package Sikshya\Frontend\Public
 */
final class CertificatesAccountView
{
    private static bool $registered = false;

    public const VIEW_SLUG = 'certificates';

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_filter('sikshya_account_allowed_views', [self::class, 'registerView']);
        add_filter('sikshya_account_template_data', [self::class, 'inject'], 20);
        add_filter('sikshya_account_view_template', [self::class, 'overrideViewTemplate'], 10, 3);
        add_action('sikshya_account_sidebar_nav_after_learning_hub', [self::class, 'renderSidebarNavAfterLearningHub'], 10, 3);
        add_action('sikshya_account_dashboard_after_hero', [self::class, 'renderDashboardTeaser'], 6, 1);
    }

    /**
     * @param string[] $views
     * @return string[]
     */
    public static function registerView($views): array
    {
        $views = is_array($views) ? $views : [];
        if (get_current_user_id() > 0) {
            $views[] = self::VIEW_SLUG;
        }
        return array_values(array_unique($views));
    }

    /**
     * @param array<string, mixed> $acc
     * @return array<string, mixed>
     */
    public static function inject($acc): array
    {
        if (!is_array($acc)) {
            return [];
        }

        $uid = (int) ($acc['user_id'] ?? 0);
        if ($uid <= 0) {
            return $acc;
        }

        if (!isset($acc['urls']) || !is_array($acc['urls'])) {
            $acc['urls'] = [];
        }
        $acc['urls']['account_certificates'] = PublicPageUrls::accountViewUrl(self::VIEW_SLUG);

        $svc = new LearnerCertificateService();
        $certs = $svc->getUserCertificates($uid, 200, 1);
        $acc['certificates'] = $certs;
        $acc['certificates_count'] = $svc->getUserCertificatesCount($uid);

        $by_course = [];
        foreach ($certs as $c) {
            $cid = (int) ($c['course_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $by_course[$cid] = [
                'id' => (int) ($c['id'] ?? 0),
                'download_url' => (string) ($c['download_url'] ?? ''),
                'verification_code' => (string) ($c['verification_code'] ?? ''),
            ];
        }
        $acc['certificates_by_course'] = $by_course;

        return $acc;
    }

    /**
     * @param string               $path
     * @param string               $view
     * @param array<string, mixed> $acc
     */
    public static function overrideViewTemplate($path, $view, $acc): string
    {
        if ($view !== self::VIEW_SLUG) {
            return is_string($path) ? $path : '';
        }

        $candidate = Plugin::getInstance()->getTemplatePath('partials/account-view-certificates.php');
        if (is_readable($candidate)) {
            return $candidate;
        }

        return is_string($path) ? $path : '';
    }

    /**
     * @param array<string, mixed> $acc
     */
    public static function renderSidebarNavAfterLearningHub($acc, string $view, $page_model): void
    {
        if (!is_array($acc) || empty($acc['user_id'])) {
            return;
        }

        $url = is_array($acc['urls'] ?? null) ? (string) ($acc['urls']['account_certificates'] ?? '') : '';
        if ($url === '') {
            return;
        }

        echo '<a class="' . ($view === self::VIEW_SLUG ? 'is-active' : '') . '" href="' . esc_url($url) . '">';
        echo '<span class="sik-acc-nav__icon" aria-hidden="true">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">'
            . '<path d="M20 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/>'
            . '<rect x="4" y="7" width="16" height="14" rx="2"/>'
            . '<path d="M8 11h8"/>'
            . '<path d="M8 15h6"/>'
            . '</svg>'
            . '</span>';
        echo esc_html__('My certificates', 'sikshya');
        echo '</a>';
    }

    /**
     * Small dashboard card/shortcut.
     *
     * @param array<string, mixed> $acc
     */
    public static function renderDashboardTeaser($acc): void
    {
        if (!is_array($acc) || empty($acc['user_id'])) {
            return;
        }
        $url = is_array($acc['urls'] ?? null) ? (string) ($acc['urls']['account_certificates'] ?? '') : '';
        $n = (int) ($acc['certificates_count'] ?? 0);
        if ($url === '' || $n <= 0) {
            return;
        }

        echo '<div class="sik-acc-metric sik-acc-metric--link" style="margin-top:12px">';
        echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:block">';
        echo '<div class="sik-acc-metric__value">' . esc_html((string) $n) . '</div>';
        echo '<div class="sik-acc-metric__label">' . esc_html__('My certificates', 'sikshya') . '</div>';
        echo '<div class="sik-acc-metric__hint">' . esc_html__('Download your completion certificates', 'sikshya') . '</div>';
        echo '</a>';
        echo '</div>';
    }
}

