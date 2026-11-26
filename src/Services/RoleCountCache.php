<?php

declare(strict_types=1);

namespace Sikshya\Services;

/**
 * Cached "how many users hold this role" counts.
 *
 * `count_users()` answers by grouping every `*_capabilities` row in usermeta.
 * The role is stored inside a serialized array, so no index can help: the
 * query reads the table. WordPress caches nothing, and Sikshya asked three
 * times per admin page load — the dashboard KPIs, the reports snapshot, and
 * the stats widget — so the cost scaled with how many users the customer had,
 * on a screen that has nothing to do with users.
 *
 * The counts only change when somebody registers, is deleted, or has a role
 * changed, all of which fire an action. So they are cached until one does, with
 * a TTL as the backstop for the one path that fires nothing: a direct write to
 * `wp_capabilities` in usermeta.
 *
 * Same defect class as the per-row postmeta scan in the question list and the
 * per-item cron work: cost that follows the customer's data rather than the
 * page. Here the fix is a cache rather than a batched query, because there is
 * no cheaper way to ask the question.
 *
 * @package Sikshya\Services
 */
final class RoleCountCache
{
    public const TRANSIENT = 'sikshya_role_counts';

    public const TTL = 12 * HOUR_IN_SECONDS;

    /**
     * Request-level memo, so one page never asks twice even on a cache miss.
     *
     * @var array<string, int>|null
     */
    private static ?array $memo = null;

    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        foreach (
            [
                'set_user_role',
                'add_user_role',
                'remove_user_role',
                'user_register',
                'deleted_user',
                'wpmu_new_user',
                'wpmu_delete_user',
                // A role added or removed by name (Sikshya's own instructor
                // approval path goes through WP_User::add_role, but an add-on
                // may edit the role object itself).
                'granted_super_admin',
                'revoked_super_admin',
            ] as $hook
        ) {
            add_action($hook, [self::class, 'flush'], 10, 0);
        }
    }

    /**
     * Role slug => number of users, as `count_users()['avail_roles']` returns it.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        if (is_array(self::$memo)) {
            return self::$memo;
        }

        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            self::$memo = self::normalize($cached);

            return self::$memo;
        }

        $counted = count_users();
        $roles = (is_array($counted) && isset($counted['avail_roles']) && is_array($counted['avail_roles']))
            ? $counted['avail_roles']
            : [];

        self::$memo = self::normalize($roles);
        set_transient(self::TRANSIENT, self::$memo, self::TTL);

        return self::$memo;
    }

    public static function forRole(string $role): int
    {
        $counts = self::counts();

        return isset($counts[$role]) ? (int) $counts[$role] : 0;
    }

    public static function flush(): void
    {
        self::$memo = null;
        delete_transient(self::TRANSIENT);
    }

    /**
     * @param array<string|int, mixed> $roles
     * @return array<string, int>
     */
    private static function normalize(array $roles): array
    {
        $out = [];
        foreach ($roles as $role => $count) {
            $role = (string) $role;
            if ($role === '') {
                continue;
            }
            $out[$role] = max(0, (int) $count);
        }

        return $out;
    }
}
