<?php

namespace Sikshya\Admin;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\Settings;

/**
 * Self-diagnosing admin notices for configuration that silently breaks the LMS.
 *
 * Each notice answers three questions a bare warning does not: what is actually
 * broken for a learner right now, where the setting lives, and — where it is
 * safe to do so — offers to fix it in one click.
 *
 * These deliberately hook `admin_notices` rather than the React shell, because
 * {@see Admin} strips core notices inside Sikshya's own screens to keep the app
 * chrome clean. The audience for a "your store cannot take money" warning is
 * someone on Plugins or Dashboard, not someone already deep in the builder.
 *
 * Every check is cheap or cached; none runs an unbounded query.
 *
 * @package Sikshya\Admin
 */
final class SiteHealthNotices
{
    private const ACTION_ENABLE_REGISTRATION = 'sikshya_enable_registration';
    private const NONCE_ENABLE_REGISTRATION = 'sikshya_enable_registration_nonce';
    private const DISMISS_META_PREFIX = 'sikshya_dismissed_notice_';
    private const PAID_COURSE_CACHE = 'sikshya_has_paid_courses';
    private const ACTION_FLUSH_REWRITES = 'sikshya_flush_rewrites';

    /**
     * The Sikshya Pro version this build of the free plugin expects.
     *
     * Free updates arrive from wordpress.org, often automatically; Pro updates
     * arrive from the store. So "free is ahead of Pro" is the ordinary state
     * for a while after every release, not an edge case — and in that window
     * any screen the free plugin renders for a *newer* Pro add-on finds no REST
     * route behind it, because a route registered in an add-on's boot does not
     * exist until that add-on ships.
     */
    public const EXPECTED_PRO_VERSION = '1.0.11';
    private const NONCE_FLUSH_REWRITES = 'sikshya_flush_rewrites_nonce';

    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('admin_notices', [self::class, 'render']);
        add_action('admin_post_' . self::ACTION_ENABLE_REGISTRATION, [self::class, 'handleEnableRegistration']);
        add_action('admin_post_sikshya_dismiss_health_notice', [self::class, 'handleDismiss']);
        add_action('admin_post_' . self::ACTION_FLUSH_REWRITES, [self::class, 'handleFlushRewrites']);

        // A new or re-priced course can flip the "can you take money?" answer.
        add_action('save_post_' . PostTypes::COURSE, static function (): void {
            delete_transient(self::PAID_COURSE_CACHE);
        });
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        foreach (self::checks() as $check) {
            if (self::isDismissed($check['id'])) {
                continue;
            }
            self::renderNotice($check);
        }
    }

    /**
     * @return array<int, array{id:string, severity:string, title:string, body:string, settings_label:string, settings_url:string, action_label?:string, action_url?:string}>
     */
    private static function checks(): array
    {
        $out = [];

        // 1. Pretty permalinks are a hard requirement for the virtual pages.
        if (PermalinkService::isPlainPermalinks()) {
            $out[] = [
                'id' => 'plain_permalinks',
                'severity' => 'error',
                'title' => __('Sikshya pages are falling back to plain URLs', 'sikshya'),
                'body' => __('Your site uses Plain permalinks, so Sikshya cannot register its page routes. The cart, checkout, login, learner dashboard and course player are only reachable through query-string URLs like <code>?sikshya_page=cart</code>, which look broken to learners and are not shareable. Choosing any other permalink structure fixes all of them at once.', 'sikshya'),
                'settings_label' => __('Settings → Permalinks', 'sikshya'),
                'settings_url' => admin_url('options-permalink.php'),
            ];
        }

        // 2. The virtual routes exist only as rewrite rules. Another plugin
        //    flushing them in a request where Sikshya was not loaded silently
        //    404s every Sikshya page, and nothing else reports it.
        if (!PermalinkService::isPlainPermalinks() && !self::virtualRoutesRegistered()) {
            $out[] = [
                'id' => 'missing_rewrite_rules',
                'severity' => 'error',
                'title' => __('Sikshya page URLs are returning 404', 'sikshya'),
                'body' => __('Sikshya registers its cart, checkout, login, learner dashboard and course player as URL rules rather than as WordPress pages, and those rules are currently missing. Every one of those pages will return "Not Found" for your learners. This usually happens when another plugin rewrites the URL rules without Sikshya active. Refreshing them restores all of the pages at once.', 'sikshya'),
                'settings_label' => __('Settings → Permalinks', 'sikshya'),
                'settings_url' => admin_url('options-permalink.php'),
                'action_label' => __('Refresh URL rules', 'sikshya'),
                'action_url' => wp_nonce_url(
                    admin_url('admin-post.php?action=' . self::ACTION_FLUSH_REWRITES),
                    self::NONCE_FLUSH_REWRITES
                ),
            ];
        }

        // 3. Learners cannot create an account at all.
        if (!get_option('users_can_register')) {
            $out[] = [
                'id' => 'registration_disabled',
                'severity' => 'warning',
                'title' => __('Learners cannot sign up', 'sikshya'),
                'body' => __('WordPress registration is turned off, so nobody can create a student account — enrolment, checkout and the learner dashboard are all unreachable for new visitors. Existing accounts are unaffected.', 'sikshya'),
                'settings_label' => __('Settings → General → Membership', 'sikshya'),
                'settings_url' => admin_url('options-general.php#users_can_register'),
                'action_label' => __('Enable registration', 'sikshya'),
                'action_url' => wp_nonce_url(
                    admin_url('admin-post.php?action=' . self::ACTION_ENABLE_REGISTRATION),
                    self::NONCE_ENABLE_REGISTRATION
                ),
            ];
        }

        // 4. Paid courses exist but there is no way to pay for them.
        if (self::hasPaidCourses() && !self::hasAnyGatewayEnabled()) {
            $out[] = [
                'id' => 'no_payment_gateway',
                'severity' => 'warning',
                'title' => __('You have paid courses but no way to take payment', 'sikshya'),
                'body' => __('At least one course has a price, but every payment method is switched off. Learners reaching checkout cannot complete an order. Enable offline payment, PayPal or Stripe — all three are included in the free plugin.', 'sikshya'),
                'settings_label' => __('Sikshya → Settings → Commerce', 'sikshya'),
                'settings_url' => admin_url('admin.php?page=' . \Sikshya\Constants\AdminPages::DASHBOARD . '&view=settings'),
            ];
        }

        // 5. Pro is installed but older than this build of Sikshya expects.
        if (self::proIsBehind()) {
            $out[] = [
                'id' => 'pro_version_behind',
                'severity' => 'warning',
                'title' => __('Sikshya Pro is older than this version of Sikshya', 'sikshya'),
                'body' => sprintf(
                    /* translators: 1: installed Pro version, 2: expected Pro version */
                    __(
                        'Sikshya Pro %1$s is active, but this version of Sikshya expects %2$s or newer. Features added in this release that are powered by Pro will show their screen and then fail to load — nothing already working is affected, and updating Pro fixes it. Free updates come from WordPress.org while Pro comes from your account, so they do not always arrive together.',
                        'sikshya'
                    ),
                    self::installedProVersion(),
                    self::EXPECTED_PRO_VERSION
                ),
                'settings_label' => __('Sikshya → License', 'sikshya'),
                'settings_url' => admin_url('admin.php?page=' . \Sikshya\Constants\AdminPages::DASHBOARD . '&view=license'),
            ];
        }

        return $out;
    }

    /**
     * The active Pro plugin's version, or '' when Pro is absent or silent.
     */
    private static function installedProVersion(): string
    {
        $version = defined('SIKSHYA_PRO_VERSION') ? trim((string) \constant('SIKSHYA_PRO_VERSION')) : '';

        /**
         * Filter the Pro version this check compares against.
         *
         * The real source is a constant, which a site cannot correct and a test
         * cannot vary. A bundled or white-labelled build that reports its
         * version elsewhere can answer here instead; returning '' silences the
         * check entirely.
         *
         * @param string $version Version string, or '' when Pro is absent.
         */
        return trim((string) apply_filters('sikshya_installed_pro_version', $version));
    }

    /**
     * True only when Pro is present *and* states a version below the expected
     * one.
     *
     * Deliberately silent in both unknown cases. No Pro at all is the ordinary
     * free install and must never be nagged. A Pro that does not define its
     * version cannot be compared, and guessing "probably old" would warn people
     * whose setup is fine — a false alarm here costs more than the real one it
     * would catch, because the symptom it describes is a single screen failing
     * to load rather than anything breaking.
     */
    private static function proIsBehind(): bool
    {
        $installed = self::installedProVersion();
        if ($installed === '') {
            return false;
        }

        return version_compare($installed, self::EXPECTED_PRO_VERSION, '<');
    }

    /**
     * @param array<string, mixed> $check
     */
    private static function renderNotice(array $check): void
    {
        $class = $check['severity'] === 'error' ? 'notice notice-error' : 'notice notice-warning';
        $dismiss = wp_nonce_url(
            admin_url('admin-post.php?action=sikshya_dismiss_health_notice&notice=' . rawurlencode((string) $check['id'])),
            'sikshya_dismiss_health_notice'
        );

        echo '<div class="' . esc_attr($class) . '" style="padding:12px 14px;">';
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Sikshya LMS', 'sikshya') . ' — ' . esc_html($check['title']) . '</strong></p>';

        // Body is plugin-authored copy containing only <code>; wp_kses keeps it inert.
        echo '<p style="margin:0 0 10px;">' . wp_kses((string) $check['body'], ['code' => []]) . '</p>';

        echo '<p style="margin:0;">';
        if (!empty($check['action_label']) && !empty($check['action_url'])) {
            echo '<a class="button button-primary" href="' . esc_url((string) $check['action_url']) . '">'
                . esc_html((string) $check['action_label']) . '</a> ';
        }
        echo '<a class="button" href="' . esc_url((string) $check['settings_url']) . '">'
            . esc_html((string) $check['settings_label']) . '</a> ';
        echo '<a href="' . esc_url($dismiss) . '" style="margin-left:8px;">' . esc_html__('Dismiss', 'sikshya') . '</a>';
        echo '</p></div>';
    }

    public static function handleEnableRegistration(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change this setting.', 'sikshya'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE_ENABLE_REGISTRATION);

        update_option('users_can_register', 1);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public static function handleDismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'sikshya'), '', ['response' => 403]);
        }
        check_admin_referer('sikshya_dismiss_health_notice');

        $notice = isset($_GET['notice']) ? sanitize_key((string) wp_unslash($_GET['notice'])) : '';
        if ($notice !== '') {
            update_user_meta(get_current_user_id(), self::DISMISS_META_PREFIX . $notice, 1);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * True when at least one `sikshya_page` rule is present in the rewrite table.
     *
     * Only meaningful with pretty permalinks; PermalinkService registers no rules
     * at all on plain permalinks, which the dedicated check above reports.
     */
    private static function virtualRoutesRegistered(): bool
    {
        $rules = get_option('rewrite_rules');
        if (!is_array($rules) || $rules === []) {
            // No rules at all usually means they have never been generated;
            // the permalink screen will build them on next save.
            return false;
        }

        foreach ($rules as $target) {
            if (is_string($target) && strpos($target, PermalinkService::QUERY_VAR . '=') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function handleFlushRewrites(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'sikshya'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE_FLUSH_REWRITES);

        // Register in this request before flushing, otherwise the rebuilt table
        // would again be written without Sikshya's rules.
        PermalinkService::registerRewriteRules();
        flush_rewrite_rules(true);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    private static function isDismissed(string $id): bool
    {
        return (bool) get_user_meta(get_current_user_id(), self::DISMISS_META_PREFIX . sanitize_key($id), true);
    }

    /**
     * Bounded + cached: asks only whether at least one priced course exists.
     */
    private static function hasPaidCourses(): bool
    {
        $cached = get_transient(self::PAID_COURSE_CACHE);
        if ($cached === '1') {
            return true;
        }
        if ($cached === '0') {
            return false;
        }

        $found = get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query' => [
                [
                    'key' => '_sikshya_course_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ]);

        $has = !empty($found);
        set_transient(self::PAID_COURSE_CACHE, $has ? '1' : '0', HOUR_IN_SECONDS);

        return $has;
    }

    private static function hasAnyGatewayEnabled(): bool
    {
        foreach (['enable_offline_payment', 'enable_paypal_payment', 'enable_stripe_payment'] as $key) {
            if (Settings::isTruthy(Settings::get($key, $key === 'enable_offline_payment' ? '1' : '0'))) {
                return true;
            }
        }

        return false;
    }
}
