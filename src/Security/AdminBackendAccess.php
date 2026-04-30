<?php

namespace Sikshya\Security;

/**
 * Shared capability checks for Sikshya's wp-admin React shell and staff-only REST endpoints.
 *
 * Users with only the `sikshya_student` role must remain on frontend account flows only.
 * Arbitrary WP roles with only generic post caps (for example `edit_posts`) must not impersonate LMS staff.
 *
 * @package Sikshya\Security
 */
final class AdminBackendAccess
{
    public static function registerFilters(): void
    {
        add_filter('user_has_cap', [self::class, 'grantReactMenuCapabilityForLmsStaff'], 10, 4);
    }

    /**
     * Lets `current_user_can( 'sikshya_access_admin_app' )` succeed for LMS staff roles that use
     * Sikshya caps but do not yet have this meta-cap persisted (custom roles / pre-migration).
     *
     * @param mixed $allcaps Primitive caps keyed by slug.
     * @param mixed $caps Caps being asserted.
     *
     * @return mixed
     */
    public static function grantReactMenuCapabilityForLmsStaff($allcaps, $caps, $args, $user)
    {
        unset($args, $user);
        if (!is_array($allcaps) || !is_array($caps)) {
            return $allcaps;
        }
        if (!in_array('sikshya_access_admin_app', $caps, true)) {
            return $allcaps;
        }
        if (
            !empty($allcaps['manage_options'])
            || !empty($allcaps['manage_sikshya'])
            || !empty($allcaps['edit_sikshya_courses'])
            || !empty($allcaps['edit_sikshya_lessons'])
            || !empty($allcaps['edit_sikshya_quizzes'])
            || !empty($allcaps['sikshya_access_admin_app'])
        ) {
            $allcaps['sikshya_access_admin_app'] = true;
        }

        return $allcaps;
    }

    /**
     * True when the user may load the Sikshya React wp-admin shell and staff APIs
     * (excluding pure commerce/settings routes guarded separately).
     */
    public static function canAccessStaffBackend(): bool
    {
        return self::userCanAccessStaffBackend(get_current_user_id());
    }

    /**
     * Capability decision for LMS staff React shell / staff REST (excluding commerce/settings-only routes).
     *
     * Filter `sikshya_user_can_access_staff_backend` receives the boolean decision and user id.
     */
    public static function userCanAccessStaffBackend(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $allowed = false;

        if (user_can($user_id, 'manage_options')) {
            $allowed = true;
        } elseif (user_can($user_id, 'manage_sikshya')) {
            $allowed = true;
        } elseif (user_can($user_id, 'sikshya_access_admin_app')) {
            /** Primary gate aligned with Sikshya React wp-admin menu capability. */
            $allowed = true;
        } elseif (user_can($user_id, 'edit_sikshya_courses')) {
            $allowed = true;
        } elseif (user_can($user_id, 'edit_sikshya_lessons')) {
            $allowed = true;
        } elseif (user_can($user_id, 'edit_sikshya_quizzes')) {
            $allowed = true;
        }

        return (bool) apply_filters('sikshya_user_can_access_staff_backend', $allowed, $user_id);
    }

    /**
     * Site-level commerce/settings (payments, orders, licences, Sikshya settings UI).
     * Mirrors wp-admin guards in {@see \Sikshya\Admin\Admin::renderSikshyaApp()}.
     */
    public static function canManageSalesAndSettings(): bool
    {
        return current_user_can('manage_options');
    }

    public static function userCanManageSalesAndSettings(int $user_id): bool
    {
        return $user_id > 0 && user_can($user_id, 'manage_options');
    }
}
