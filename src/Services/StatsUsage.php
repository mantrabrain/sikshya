<?php

declare(strict_types=1);

namespace Sikshya\Services;

use WP_Error;

/**
 * Sikshya usage telemetry (opt-in, privacy-safe).
 *
 * Mirrors the Sikshya opt-in pattern:
 * - Admin-controlled consent flag
 * - Instance id
 * - Weekly cron + admin fallback send
 * - Minimal payload: environment + coarse usage signals (no PII)
 */
final class StatsUsage
{
    public const OPT_CONSENT = '_sikshya_allow_usage_tracking';
    public const OPT_INSTANCE_ID = '_sikshya_usage_instance_id';
    public const OPT_LAST_SYNC = '_sikshya_usage_last_sync';
    public const OPT_RETRY_COUNT = '_sikshya_usage_retry_count';
    public const OPT_NEXT_RETRY = '_sikshya_usage_next_retry';
    public const OPT_LAST_SEND_ERROR = '_sikshya_usage_last_send_error';
    public const OPT_ONBOARDING_META = '_sikshya_usage_onboarding_meta';

    public const TRANSIENT_ADMIN_FALLBACK = 'sikshya_usage_admin_fallback_throttle';

    public const CRON_HOOK = 'sikshya_usage_tracking_event';
    public const IMMEDIATE_HOOK = 'sikshya_usage_tracking_immediate';

    /**
     * Default ingest URL (query rest_route to survive missing rewrites/proxies).
     */
    public const ENDPOINT = 'https://usage.mantrabrain.com/index.php?rest_route=/mantrabrain/v1/collect';

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void
    {
        add_filter('cron_schedules', [self::class, 'addCronSchedules']);
        add_action(self::CRON_HOOK, [$this, 'cron_sync']);
        add_action(self::IMMEDIATE_HOOK, [$this, 'cron_sync']);
        add_action('admin_init', [$this, 'maybe_fallback_sync'], 30);

        add_action('sikshya_usage_setup_wizard_completed', [$this, 'on_wizard_completed']);
    }

    /**
     * @param array<string, array<string, int>> $schedules
     * @return array<string, array<string, int>>
     */
    public static function addCronSchedules(array $schedules): array
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => 7 * DAY_IN_SECONDS,
                'display' => __('Once Weekly', 'sikshya'),
            ];
        }

        return $schedules;
    }

    private static function remote_endpoint(): string
    {
        if (defined('SIKSHYA_USAGE_TRACKING_ENDPOINT') && is_string(SIKSHYA_USAGE_TRACKING_ENDPOINT) && SIKSHYA_USAGE_TRACKING_ENDPOINT !== '') {
            return (string) SIKSHYA_USAGE_TRACKING_ENDPOINT;
        }

        return (string) apply_filters('sikshya_usage_tracking_endpoint', self::ENDPOINT);
    }

    public function is_enabled(): bool
    {
        return (bool) get_option(self::OPT_CONSENT, false);
    }

    public function ensure_instance_id(): string
    {
        $id = (string) get_option(self::OPT_INSTANCE_ID, '');
        if ($id !== '') {
            return $id;
        }

        $id = wp_generate_uuid4();
        update_option(self::OPT_INSTANCE_ID, $id, false);

        return $id;
    }

    public function enable(bool $send_immediate = true): void
    {
        update_option(self::OPT_CONSENT, true, false);
        $this->ensure_instance_id();
        $this->schedule_weekly();
        delete_option(self::OPT_RETRY_COUNT);
        delete_option(self::OPT_NEXT_RETRY);
        if ($send_immediate) {
            wp_unschedule_hook(self::IMMEDIATE_HOOK);
            wp_schedule_single_event(time() + 2, self::IMMEDIATE_HOOK);
            if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
                spawn_cron();
            }
        }
    }

    public function disable(): void
    {
        update_option(self::OPT_CONSENT, false, false);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::IMMEDIATE_HOOK);
        delete_option(self::OPT_RETRY_COUNT);
        delete_option(self::OPT_NEXT_RETRY);
    }

    public function schedule_weekly(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK);
        }
    }

    public function on_wizard_completed(): void
    {
        $meta = get_option(self::OPT_ONBOARDING_META, []);
        if (!is_array($meta)) {
            $meta = [];
        }
        $meta['completed_at'] = time();
        $meta['onboarding_completed'] = true;
        update_option(self::OPT_ONBOARDING_META, $meta, false);
    }

    public function cron_sync(): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $next = (int) get_option(self::OPT_NEXT_RETRY, 0);
        if ($next > time()) {
            return;
        }

        $this->sync();
    }

    public function maybe_fallback_sync(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (!$this->is_enabled()) {
            return;
        }
        if (get_transient(self::TRANSIENT_ADMIN_FALLBACK)) {
            return;
        }
        $last = (int) get_option(self::OPT_LAST_SYNC, 0);
        if ($last > 0 && (time() - $last) < 9 * DAY_IN_SECONDS) {
            return;
        }
        set_transient(self::TRANSIENT_ADMIN_FALLBACK, 1, 12 * HOUR_IN_SECONDS);
        $this->sync();
    }

    /**
     * @return array<string, mixed>
     */
    public function build_payload(): array
    {
        global $wpdb, $wp_version;

        $courses = wp_count_posts('sikshya_course');
        $lessons = wp_count_posts('sikshya_lesson');

        $metricDate = gmdate('Y-m-d');

        $pluginVersion = defined('SIKSHYA_VERSION') ? (string) SIKSHYA_VERSION : '';
        $siteUrl = untrailingslashit(site_url());

        // Addons: enabled/disabled inventory (non-sensitive).
        $addonEnabled = [];
        $addonCatalog = [];
        if (class_exists('\\Sikshya\\Addons\\Addons') && class_exists('\\Sikshya\\Addons\\AddonManager')) {
            $addonEnabled = \Sikshya\Addons\Addons::enabledIds();
            try {
                $mgr = new \Sikshya\Addons\AddonManager();
                $addonCatalog = $mgr->registry();
            } catch (\Throwable $e) {
                unset($e);
                $addonCatalog = [];
            }
        }
        $addonEnabled = is_array($addonEnabled) ? array_values(array_filter(array_map('sanitize_key', $addonEnabled))) : [];

        // Only report the official Sikshya addon catalog (keep in sync with Addons UI order).
        $allowedAddonIds = [
            'email_advanced_customization',
            'subscriptions',
            'content_drip',
            'course_bundles',
            'coupons_advanced',
            'multi_instructor',
            'prerequisites',
            'drip_notifications',
            'reports_advanced',
            'gradebook',
            'certificates_advanced',
            'activity_log',
            'assignments_advanced',
            'quiz_advanced',
            'instructor_dashboard',
            'email_marketing',
            'live_classes',
            'calendar',
            'social_login',
            'scorm_h5p_pro',
            'marketplace_multivendor',
            'white_label',
            'webhooks',
            'zapier',
            'public_api_keys',
            'enterprise_reports',
            'multilingual_enterprise',
            'multisite_scale',
        ];
        $allowedSet = array_fill_keys($allowedAddonIds, true);

        $addonEnabled = array_values(array_filter($addonEnabled, static function (string $id) use ($allowedSet): bool {
            return isset($allowedSet[$id]);
        }));

        $addonCatalogIds = is_array($addonCatalog) ? array_keys($addonCatalog) : [];
        $addonCatalogIds = array_values(array_filter(array_map('sanitize_key', $addonCatalogIds), static function (string $id) use ($allowedSet): bool {
            return isset($allowedSet[$id]);
        }));

        $addonEnabledCount = count($addonEnabled);
        $addonCatalogCount = count($allowedAddonIds);
        $addonDisabledCount = max(0, $addonCatalogCount - $addonEnabledCount);

        // Active plugins/themes (non-sensitive). These become products in the warehouse.
        $activePlugins = [];
        if (function_exists('get_plugins')) {
            $all = (array) get_plugins();
            $active = (array) get_option('active_plugins', []);
            foreach ($active as $file) {
                $file = is_string($file) ? $file : '';
                if ($file === '' || !isset($all[$file]) || !is_array($all[$file])) {
                    continue;
                }
                $slug = sanitize_title(dirname($file));
                if ($slug === '.' || $slug === '') {
                    $slug = sanitize_title(basename($file, '.php'));
                }
                if ($slug === '') {
                    continue;
                }
                // Sikshya is already represented as the primary product row (with parameters).
                if ($slug === 'sikshya') {
                    continue;
                }
                $meta = $all[$file];
                $activePlugins[] = [
                    'product_slug' => $slug,
                    'product_name' => isset($meta['Name']) ? (string) $meta['Name'] : $slug,
                    'product_type' => 'plugin',
                    'product_version' => isset($meta['Version']) ? (string) $meta['Version'] : '',
                    'parameters' => [],
                ];
            }
        }

        $activeThemes = [];
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            if ($theme && $theme->exists()) {
                $slug = sanitize_title((string) $theme->get_stylesheet());
                if ($slug === '') {
                    $slug = sanitize_title((string) $theme->get('Name'));
                }
                if ($slug !== '') {
                    $activeThemes[] = [
                        'product_slug' => $slug,
                        'product_name' => (string) $theme->get('Name'),
                        'product_type' => 'theme',
                        'product_version' => (string) $theme->get('Version'),
                        'parameters' => [],
                    ];
                }
                $parent = $theme->parent();
                if ($parent && $parent->exists()) {
                    $pslug = sanitize_title((string) $parent->get_stylesheet());
                    if ($pslug === '') {
                        $pslug = sanitize_title((string) $parent->get('Name'));
                    }
                    if ($pslug !== '') {
                        $activeThemes[] = [
                            'product_slug' => $pslug,
                            'product_name' => (string) $parent->get('Name'),
                            'product_type' => 'theme',
                            'product_version' => (string) $parent->get('Version'),
                            'parameters' => [],
                        ];
                    }
                }
            }
        }

        // Addon rows as separate "products" (lets the warehouse query adoption across sites).
        $addonProducts = [];
        if (is_array($addonCatalog) && $addonCatalog !== []) {
            foreach ($allowedAddonIds as $id) {
                if (!isset($addonCatalog[$id]) || !$addonCatalog[$id] instanceof \Sikshya\Addons\AddonInterface) {
                    continue;
                }
                /** @var \Sikshya\Addons\AddonInterface $addon */
                $addon = $addonCatalog[$id];
                $enabled = in_array($id, $addonEnabled, true);
                $addonProducts[] = [
                    'product_slug' => $id,
                    'product_name' => (string) $addon->label(),
                    'product_type' => 'addon',
                    'parent_slug' => 'sikshya',
                    'parent_name' => function_exists('sikshya_brand_name') ? (string) sikshya_brand_name('admin') : 'Sikshya',
                    'product_version' => $pluginVersion,
                    'parameters' => [
                        [
                            'parameter_id' => 'addon.enabled',
                            'parameter_name' => 'Enabled',
                            'value' => $enabled ? 1 : 0,
                            'is_json' => false,
                            'metric_date' => $metricDate,
                        ],
                        [
                            'parameter_id' => 'addon.tier',
                            'parameter_name' => 'Tier',
                            'value' => (string) $addon->tier(),
                            'is_json' => false,
                            'metric_date' => $metricDate,
                        ],
                        [
                            'parameter_id' => 'addon.group',
                            'parameter_name' => 'Group',
                            'value' => (string) $addon->group(),
                            'is_json' => false,
                            'metric_date' => $metricDate,
                        ],
                    ],
                ];
            }
        }

        return [
            // Canonical warehouse shape (consumed by `mantrabrain-usage-stats` collector):
            // website + products[].parameters[].
            'website' => [
                'website_url' => $siteUrl,
                'php_version' => PHP_VERSION,
                'mysql_version' => is_object($wpdb) ? (string) $wpdb->db_version() : '',
                'wordpress_version' => (string) ($wp_version ?? ''),
                'language' => (string) get_locale(),
                'multisite' => is_multisite(),
                'instance_id' => $this->ensure_instance_id(),
                // Helps the collector attribute the sender (also set via headers).
                'ingest_source_plugin' => 'sikshya',
                'ingest_source_plugin_version' => $pluginVersion,
                'telemetry_schema_version' => 1,
                'telemetry_sent_at' => gmdate('c'),
                'blog_id' => is_multisite() ? get_current_blog_id() : 1,
            ],
            'products' => $this->dedupeProducts(
                array_values(
                    array_filter(
                        array_merge(
                            [
                                [
                                    'product_slug' => 'sikshya',
                                    'product_name' => function_exists('sikshya_brand_name') ? (string) sikshya_brand_name('admin') : 'Sikshya',
                                    'product_type' => 'plugin',
                                    'product_version' => $pluginVersion,
                                    'parameters' => [
                                        [
                                            'parameter_id' => 'usage.courses_published',
                                            'parameter_name' => 'Courses published',
                                            'value' => isset($courses->publish) ? (int) $courses->publish : 0,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'usage.lessons_published',
                                            'parameter_name' => 'Lessons published',
                                            'value' => isset($lessons->publish) ? (int) $lessons->publish : 0,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'usage.setup_completed',
                                            'parameter_name' => 'Setup completed',
                                            'value' => Settings::isTruthy(Settings::get('setup_completed', '0')) ? 1 : 0,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'addons.enabled_ids',
                                            'parameter_name' => 'Enabled addons (IDs)',
                                            'value' => $addonEnabled,
                                            'is_json' => true,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'addons.enabled_count',
                                            'parameter_name' => 'Enabled addons count',
                                            'value' => $addonEnabledCount,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'addons.catalog_count',
                                            'parameter_name' => 'Addon catalog count',
                                            'value' => $addonCatalogCount,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                        [
                                            'parameter_id' => 'addons.disabled_count',
                                            'parameter_name' => 'Disabled addons count',
                                            'value' => $addonDisabledCount,
                                            'is_json' => false,
                                            'metric_date' => $metricDate,
                                        ],
                                    ],
                                ],
                            ],
                            $addonProducts,
                            $activePlugins,
                            $activeThemes
                        ),
                        static function ($row): bool {
                            return is_array($row) && !empty($row['product_slug']);
                        }
                    )
                )
            ),
        ];
    }

    /**
     * Prevent duplicate product rows in a single payload.
     *
     * @param array<int, array<string,mixed>> $products
     * @return array<int, array<string,mixed>>
     */
    private function dedupeProducts(array $products): array
    {
        $seen = [];
        $out = [];
        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $slug = isset($p['product_slug']) ? sanitize_title((string) $p['product_slug']) : '';
            $type = isset($p['product_type']) ? sanitize_key((string) $p['product_type']) : '';
            if ($slug === '') {
                continue;
            }
            $key = $type . ':' . $slug;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
        }

        return $out;
    }

    public function get_last_send_error()
    {
        $v = get_option(self::OPT_LAST_SEND_ERROR, null);

        return is_array($v) ? $v : null;
    }

    public function sync(): bool
    {
        $payload = $this->build_payload();
        $endpoint = self::remote_endpoint();

        $res = wp_remote_post(
            $endpoint,
            [
                'timeout' => 12,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Plugin' => 'sikshya',
                    'X-Version' => defined('SIKSHYA_VERSION') ? (string) SIKSHYA_VERSION : '',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($res)) {
            $this->set_backoff($res);

            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            update_option(
                self::OPT_LAST_SEND_ERROR,
                [
                    'code' => $code,
                    'body' => substr($body, 0, 300),
                ],
                false
            );
            $this->set_backoff(null, $code);

            return false;
        }

        // The collector can accept the request but ignore it (e.g. local/dev host blocklist).
        $decoded = json_decode($body, true);
        if (is_array($decoded) && !empty($decoded['ignored'])) {
            update_option(
                self::OPT_LAST_SEND_ERROR,
                [
                    'code' => $code,
                    'ignored' => true,
                    'reason' => isset($decoded['reason']) ? (string) $decoded['reason'] : '',
                ],
                false
            );
            $this->set_backoff(null, 200);
            return false;
        }

        delete_option(self::OPT_LAST_SEND_ERROR);
        delete_option(self::OPT_RETRY_COUNT);
        delete_option(self::OPT_NEXT_RETRY);
        update_option(self::OPT_LAST_SYNC, time(), false);

        return true;
    }

    private function set_backoff(?WP_Error $err = null, int $http_code = 0): void
    {
        $retry = (int) get_option(self::OPT_RETRY_COUNT, 0);
        $retry = max(0, min(12, $retry + 1));
        update_option(self::OPT_RETRY_COUNT, $retry, false);

        // Exponential backoff: 5m, 10m, 20m, ..., capped at 6h.
        $delay = min(6 * HOUR_IN_SECONDS, (int) (5 * MINUTE_IN_SECONDS * pow(2, $retry - 1)));
        $next = time() + max(5 * MINUTE_IN_SECONDS, $delay);
        update_option(self::OPT_NEXT_RETRY, $next, false);

        $detail = [
            'retry' => $retry,
            'next_retry' => $next,
        ];
        if ($http_code > 0) {
            $detail['code'] = $http_code;
        }
        if ($err) {
            $detail['wp_error'] = $err->get_error_message();
        }
        update_option(self::OPT_LAST_SEND_ERROR, $detail, false);
    }
}

