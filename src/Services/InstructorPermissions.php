<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global Settings → Instructors: permission toggles for the instructor role.
 *
 * @package Sikshya\Services
 */
final class InstructorPermissions
{
    public static function isInstructorUser(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        $u = get_userdata($user_id);

        return $u && in_array('sikshya_instructor', (array) $u->roles, true);
    }

    public static function canCreateCourses(int $user_id): bool
    {
        if (!self::isInstructorUser($user_id)) {
            return true;
        }

        return Settings::isTruthy(Settings::get('instructors_can_create_courses', '1'));
    }

    /**
     * Course ids an admin-screen listing should be limited to for this user.
     *
     * Returns `null` when the user is not restricted (site administrators and
     * non-instructor staff), mirroring the convention the Pro review
     * moderation repository already uses: `null` means "no restriction",
     * an empty array means "no courses" and must produce zero rows rather
     * than falling through to an unfiltered query.
     *
     * @return int[]|null
     */
    public static function scopedCourseIds(int $user_id): ?array
    {
        if ($user_id <= 0) {
            return [];
        }
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_sikshya')) {
            return null;
        }
        if (!self::isInstructorUser($user_id)) {
            return null;
        }

        $ids = get_posts(
            [
                'post_type' => \Sikshya\Constants\PostTypes::COURSE,
                'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
                'author' => $user_id,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        return array_values(array_map('intval', (array) $ids));
    }

    public static function canEditCourse(int $user_id, int $course_id): bool
    {
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        if (!self::isInstructorUser($user_id)) {
            return true;
        }
        if (!Settings::isTruthy(Settings::get('instructors_can_edit_courses', '1'))) {
            return false;
        }
        $post = get_post($course_id);

        return $post && (int) $post->post_author === $user_id;
    }

    public static function canDeleteCourse(int $user_id, int $course_id): bool
    {
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        if (!self::isInstructorUser($user_id)) {
            return true;
        }
        if (!Settings::isTruthy(Settings::get('instructors_can_delete_courses', '0'))) {
            return false;
        }
        $post = get_post($course_id);

        return $post && (int) $post->post_author === $user_id;
    }

    /**
     * Block trash/delete in WP admin for instructors when setting is off.
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $user_id
     * @param mixed[]  $args
     * @return string[]
     */
    public static function mapMetaCap($caps, $cap, $user_id, $args)
    {
        $user_id = (int) $user_id;
        $args = is_array($args) ? $args : [];
        if (!in_array($cap, ['delete_post', 'edit_post'], true) || empty($args[0])) {
            return $caps;
        }
        $post_id = (int) $args[0];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== PostTypes::COURSE) {
            return $caps;
        }
        if (!self::isInstructorUser($user_id)) {
            return $caps;
        }
        if ($cap === 'delete_post') {
            return self::canDeleteCourse($user_id, $post_id) ? $caps : ['do_not_allow'];
        }

        return self::canEditCourse($user_id, $post_id) ? $caps : ['do_not_allow'];
    }
}
