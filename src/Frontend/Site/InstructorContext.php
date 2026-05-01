<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Database\Repositories\InstructorMetricsRepository;

/**
 * Determines whether a user should see the instructor (teaching) account experience.
 *
 * A user qualifies as an instructor when at least one of:
 *  - they hold the `sikshya_instructor` role,
 *  - they can `edit_sikshya_courses` / `manage_sikshya`,
 *  - they have authored one or more courses (`sik_course` post type).
 *
 * Result is cached in a transient (5 minutes) keyed by user id, since this is
 * called on every account page render.
 *
 * @package Sikshya\Frontend\Site
 */
final class InstructorContext
{
    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    public static function isInstructor(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $cache_key = 'sikshya_is_instructor_' . $user_id;
        $cached = get_transient($cache_key);
        if ($cached === '1') {
            return true;
        }
        if ($cached === '0') {
            return false;
        }

        $is = self::detect($user_id);
        set_transient($cache_key, $is ? '1' : '0', self::CACHE_TTL);

        return $is;
    }

    /**
     * Bust the cached instructor flag for a user (call after role/cap changes
     * or after they author/delete their first course).
     */
    public static function flush(int $user_id): void
    {
        if ($user_id > 0) {
            delete_transient('sikshya_is_instructor_' . $user_id);
        }
    }

    private static function detect(int $user_id): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $roles = (array) ($user->roles ?? []);
        if (in_array('sikshya_instructor', $roles, true)
            || in_array('administrator', $roles, true)
            || in_array('editor', $roles, true)
        ) {
            return true;
        }

        if (user_can($user_id, 'manage_sikshya') || user_can($user_id, 'edit_sikshya_courses')) {
            return true;
        }

        return (new InstructorMetricsRepository())->userHasAuthoredCourse($user_id);
    }
}
