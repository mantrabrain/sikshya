<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Database\Repositories\EnrollmentRepository;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * What happens to an LMS when a person is removed from WordPress.
 *
 * Until this existed, nothing did. Deleting a user — by hand, through
 * `UserService`, or by an identity provider deprovisioning them through a SCIM
 * plugin — left their enrolments open, their group seat consumed, and their
 * mandatory training assigned to somebody who no longer exists. The seat was
 * the expensive part: an organisation that bought a hundred seats lost one
 * every time an employee left, and the only way to get it back was to find the
 * row by hand.
 *
 * This service gives the LMS one named event for a person leaving:
 *
 *   do_action( 'sikshya_user_deprovisioned', int $user_id, array $details )
 *
 * `$details` carries who it was — login, email, display name, roles — because
 * by the time the action fires the user record is gone and a row that says only
 * "user 412" answers no audit question at all. Sikshya Pro listens to it to
 * release group seats and to write the audit-log entry; it is also the hook a
 * site should use for anything of its own that tracks people.
 *
 * Open enrolments are closed, and only open ones: a `completed` enrolment is
 * the evidence that the person did the training before they left. Nothing is
 * deleted here.
 *
 * @package Sikshya\Services
 */
final class UserDeprovisionService
{
    public static function init(): void
    {
        // `deleted_user` hands us the `WP_User` object as it was before the
        // delete (WordPress 5.5+, on both the single-site and the multisite
        // path), so there is no need to snapshot the identity on `delete_user`
        // beforehand — a first draft that did was proved redundant by removing
        // it and watching no test fail.
        add_action('deleted_user', [self::class, 'onDeletedUser'], 5, 3);
    }

    /**
     * @param int|string    $user_id
     * @param int|null      $reassign
     * @param \WP_User|null $user
     */
    public static function onDeletedUser($user_id, $reassign = null, $user = null): void
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        $identity = [
            'user_login' => $user instanceof \WP_User ? (string) $user->user_login : '',
            'user_email' => $user instanceof \WP_User ? (string) $user->user_email : '',
            'display_name' => $user instanceof \WP_User ? (string) $user->display_name : '',
            'roles' => $user instanceof \WP_User ? array_values(array_map('strval', (array) $user->roles)) : [],
        ];

        $cancelled = 0;
        /**
         * Whether to close a deprovisioned person's open enrolments.
         *
         * Filter to false only if something else on the site owns enrolment
         * lifecycle; the rows are then left exactly as they were.
         *
         * @param bool $cancel
         * @param int  $user_id
         */
        if ((bool) apply_filters('sikshya_cancel_enrollments_on_user_delete', true, $user_id)) {
            $cancelled = (new EnrollmentRepository())->cancelOpenForUser($user_id);
        }

        $details = $identity + [
            'enrollments_cancelled' => $cancelled,
            'reassigned_to' => is_numeric($reassign) ? (int) $reassign : 0,
            'actor_id' => get_current_user_id(),
        ];

        /**
         * A person was removed from the site.
         *
         * @param int                  $user_id
         * @param array<string, mixed> $details user_login, user_email, display_name, roles,
         *                                      enrollments_cancelled, reassigned_to, actor_id.
         */
        do_action('sikshya_user_deprovisioned', $user_id, $details);
    }
}
