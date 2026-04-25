<?php

declare(strict_types=1);

namespace Sikshya\Services;

use WP_Error;

/**
 * Sikshya usage telemetry (opt-in, privacy-safe).
 *
 * Mirrors the Yatra opt-in pattern:
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
        global $wp_version;

        $courses = wp_count_posts('sikshya_course');
        $lessons = wp_count_posts('sikshya_lesson');

        return [
            'product' => 'sikshya',
            'instance_id' => $this->ensure_instance_id(),
            'sent_at' => time(),
            'site' => [
                'home_url' => home_url('/'),
                'language' => (string) get_locale(),
            ],
            'env' => [
                'wp_version' => (string) $wp_version,
                'php_version' => PHP_VERSION,
                'multisite' => is_multisite(),
            ],
            'plugin' => [
                'version' => defined('SIKSHYA_VERSION') ? (string) SIKSHYA_VERSION : '',
            ],
            'usage' => [
                'courses_published' => isset($courses->publish) ? (int) $courses->publish : 0,
                'lessons_published' => isset($lessons->publish) ? (int) $lessons->publish : 0,
                'setup_completed' => Settings::isTruthy(Settings::get('setup_completed', '0')),
            ],
        ];
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

