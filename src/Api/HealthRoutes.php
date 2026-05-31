<?php

declare(strict_types=1);

namespace Sikshya\Api;

use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\OrdersTable;
use Sikshya\Database\Tables\ProgressTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public health-check endpoint for uptime monitors and load balancers.
 *
 * `GET /sikshya/v1/health` returns:
 *
 *   {
 *     "status": "ok" | "degraded" | "down",
 *     "version": "<plugin version>",
 *     "checks": {
 *       "db": "ok" | "down",
 *       "tables": { "enrollments": true, "progress": true, "orders": true },
 *       "last_cron": "<mysql datetime>" | null,
 *       "scheduler": "ok" | "degraded"
 *     }
 *   }
 *
 * Returns 200 for `ok`/`degraded` and 503 for `down` so a monitor only needs
 * to read the HTTP status to know whether to alert.
 *
 * The endpoint is intentionally unauthenticated — it returns aggregate
 * presence/connectivity signals only, no user data, no internal version
 * detail beyond `SIKSHYA_VERSION`. Sites that want to gate it further can
 * use the `sikshya_health_check_permission` filter (return WP_Error to deny).
 *
 * @package Sikshya\Api
 */
final class HealthRoutes
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'health'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);
    }

    /**
     * @return bool|\WP_Error
     */
    public function permission(WP_REST_Request $request)
    {
        /**
         * Filter the health-check permission.
         *
         * Default: public (true). Return WP_Error to deny.
         *
         * @param bool|\WP_Error  $allow
         * @param WP_REST_Request $request
         */
        $allow = apply_filters('sikshya_health_check_permission', true, $request);
        if ($allow === true) {
            return true;
        }
        if ($allow instanceof \WP_Error) {
            return $allow;
        }
        return new \WP_Error('rest_forbidden', __('Forbidden', 'sikshya'), ['status' => 403]);
    }

    public function health(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        global $wpdb;
        $db_ok = false;
        try {
            $row = $wpdb->get_var('SELECT 1');
            $db_ok = (string) $row === '1';
        } catch (\Throwable $e) {
            $db_ok = false;
        }

        $tables = [
            'enrollments' => self::tableExists($wpdb, EnrollmentsTable::getTableName()),
            'progress' => self::tableExists($wpdb, ProgressTable::getTableName()),
            'orders' => self::tableExists($wpdb, OrdersTable::getTableName()),
        ];

        $cron = self::lastCronTimestamp();
        // Scheduler is degraded when the earliest queued event is more than
        // an hour overdue, which is a strong "wp-cron isn't firing" signal.
        $scheduler_ok = $cron !== null && ($cron > time() - HOUR_IN_SECONDS);

        $tables_ok = !in_array(false, $tables, true);

        /**
         * Allow Pro / addons to contribute extra checks. Each filter callback
         * receives the array `['key' => bool]` and should return the same
         * shape with additional entries. The final status is `down` when any
         * required check fails; checks added here are advisory unless they
         * also flip `$status` via {@see 'sikshya_health_check_status'}.
         *
         * @param array<string, bool> $extra Existing `addons` check map.
         */
        $extra_checks = (array) apply_filters('sikshya_health_check_extra', []);
        $extra_checks_ok = $extra_checks === [] || !in_array(false, $extra_checks, true);

        $status = ($db_ok && $tables_ok && $extra_checks_ok) ? ($scheduler_ok ? 'ok' : 'degraded') : 'down';

        /**
         * Allow Pro / addons to override the final aggregate status (e.g.
         * promote `ok → degraded` when license is expiring).
         *
         * @param string             $status      One of `ok`, `degraded`, `down`.
         * @param array<string, mixed> $checks
         */
        $status = (string) apply_filters(
            'sikshya_health_check_status',
            $status,
            [
                'db' => $db_ok,
                'tables' => $tables,
                'scheduler' => $scheduler_ok,
                'extra' => $extra_checks,
            ]
        );
        if (!in_array($status, ['ok', 'degraded', 'down'], true)) {
            $status = 'ok';
        }

        $http_status = $status === 'down' ? 503 : 200;

        $checks = [
            'db' => $db_ok ? 'ok' : 'down',
            'tables' => $tables,
            'last_cron' => $cron ? gmdate('Y-m-d H:i:s', $cron) : null,
            'scheduler' => $scheduler_ok ? 'ok' : 'degraded',
        ];
        if ($extra_checks !== []) {
            $checks['addons'] = $extra_checks;
        }

        return new WP_REST_Response(
            [
                'status' => $status,
                'version' => defined('SIKSHYA_VERSION') ? SIKSHYA_VERSION : 'unknown',
                'checks' => $checks,
            ],
            $http_status
        );
    }

    private static function tableExists($wpdb, string $name): bool
    {
        if (!is_string($name) || $name === '') {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name));
        return $found === $name;
    }

    /**
     * Most recent cron timestamp from `_transient_doing_cron` or the cron array.
     */
    private static function lastCronTimestamp(): ?int
    {
        // Pluck the soonest scheduled event from the cron array; if cron is
        // healthy this is in the past or near future. If `wp-cron.php` hasn't
        // fired in days, the array still lists events but their timestamps
        // are far in the past — `scheduler_ok` then turns false.
        $cron = _get_cron_array();
        if (!is_array($cron) || $cron === []) {
            return null;
        }
        $first_ts = (int) array_keys($cron)[0];
        return $first_ts > 0 ? $first_ts : null;
    }
}
