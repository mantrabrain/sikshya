<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

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
     * The course a curriculum object belongs to, or 0 when it is unattached.
     *
     * Ownership of a lesson, quiz, chapter or question is not a property of
     * that post — it is inherited from the course it hangs off. Every branch
     * delegates to {@see LessonCourseLink}, the canonical (and memoised)
     * resolver the Learn shell and REST readers already share, so an
     * authorization decision can never disagree with what the player renders.
     */
    public static function owningCourseId(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        switch (get_post_type($post_id)) {
            case PostTypes::COURSE:
                return $post_id;
            case PostTypes::LESSON:
                return LessonCourseLink::resolvedCourseIdForLesson($post_id);
            case PostTypes::QUIZ:
                return LessonCourseLink::resolvedCourseIdForQuiz($post_id);
            case PostTypes::ASSIGNMENT:
                return LessonCourseLink::resolvedCourseIdForAssignment($post_id);
            case PostTypes::CHAPTER:
                foreach (['_sikshya_chapter_course_id', '_sikshya_course_id'] as $key) {
                    $course_id = (int) get_post_meta($post_id, $key, true);
                    if ($course_id > 0) {
                        return $course_id;
                    }
                }

                return max(0, (int) get_post_field('post_parent', $post_id));
            case PostTypes::QUESTION:
                $quiz_id = (int) get_post_meta($post_id, '_sikshya_quiz_id', true);

                return $quiz_id > 0 ? LessonCourseLink::resolvedCourseIdForQuiz($quiz_id) : 0;
        }

        return 0;
    }

    /**
     * Whether this user may edit a single course-owned object.
     *
     * This is the object-level check the REST layer needs and WordPress's own
     * `edit_post` meta cap cannot supply here. The Sikshya post types register
     * with `capability_type => 'post'`, so core maps `edit_post` onto
     * `edit_published_posts` / `edit_others_posts` — neither of which the
     * `sikshya_instructor` role is granted (it holds the parallel
     * `edit_published_sikshya_courses` family instead). The result is that
     * `current_user_can('edit_post', $id)` is false for an instructor's *own*
     * published course, so using it as a gate denies legitimate authoring
     * while a missing gate allows cross-account writes.
     *
     * Non-instructor staff keep their existing unrestricted behaviour, matching
     * {@see self::canEditCourse()}; only the instructor role is scoped.
     */
    public static function canEditObject(int $user_id, int $post_id): bool
    {
        if ($user_id <= 0 || $post_id <= 0) {
            return false;
        }
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_sikshya')) {
            return true;
        }

        // Baseline: the user must be able to author this post type under *either*
        // capability model, because Sikshya runs two in parallel. The post types
        // register with `capability_type => 'post'`, so core's `edit_post` meta
        // cap is what lets a stock `author`/`editor` own and edit a course; the
        // `sikshya_instructor` role instead holds the parallel
        // `edit_published_sikshya_courses` family, which `edit_post` never
        // consults. Requiring only one of the two locks out half the roles —
        // and requiring neither would answer "yes" for a logged-in learner,
        // which callers that use this as a standalone gate (the front-end admin
        // bar) rely on it not doing.
        if (!self::hasAnyAuthoringCap($user_id) && !user_can($user_id, 'edit_post', $post_id)) {
            return false;
        }

        // Non-instructor staff (custom editor-style roles) keep their existing
        // unrestricted behaviour; only the instructor role is scoped to its own
        // courses, matching {@see self::canEditCourse()}.
        if (!self::isInstructorUser($user_id)) {
            return true;
        }

        $course_id = self::owningCourseId($post_id);
        if ($course_id > 0) {
            return self::filter(self::canEditCourse($user_id, $course_id), $user_id, $post_id, $course_id);
        }

        // Unattached object (a draft not yet linked to a course): fall back to
        // authorship, and fail closed when it belongs to somebody else.
        if (!Settings::isTruthy(Settings::get('instructors_can_edit_courses', '1'))) {
            return false;
        }
        $post = get_post($post_id);

        return self::filter(
            $post !== null && (int) $post->post_author === $user_id,
            $user_id,
            $post_id,
            0
        );
    }

    /**
     * Let add-ons and sites extend (or narrow) the ownership decision.
     *
     * This replaced `current_user_can('edit_post', $id)` at the REST boundary, and
     * that meta cap was extensible — a site or add-on could grant access through
     * `map_meta_cap`. Routing through a concrete method instead silently removed
     * that extension point, which matters for exactly the case the Multi-instructor
     * add-on exists to serve: a co-teacher who is not the post author. This filter
     * puts it back.
     *
     * @param bool $allowed   The ownership decision so far.
     * @param int  $user_id   User being checked.
     * @param int  $post_id   Object being checked.
     * @param int  $course_id Owning course, or 0 when the object is unattached.
     */
    private static function filter(bool $allowed, int $user_id, int $post_id, int $course_id): bool
    {
        /**
         * Filters whether a user may edit a course-owned object.
         *
         * @param bool $allowed
         * @param int  $user_id
         * @param int  $post_id
         * @param int  $course_id
         */
        return (bool) apply_filters(
            'sikshya_user_can_edit_course_object',
            $allowed,
            $user_id,
            $post_id,
            $course_id
        );
    }

    /**
     * Whether the user holds any Sikshya content-authoring capability.
     *
     * @return bool
     */
    private static function hasAnyAuthoringCap(int $user_id): bool
    {
        foreach (
            [
                'edit_sikshya_courses',
                'edit_sikshya_lessons',
                'edit_sikshya_quizzes',
                'edit_sikshya_assignments',
            ] as $cap
        ) {
            if (user_can($user_id, $cap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * REST guard: null when the write is allowed, a 403 `WP_Error` when not.
     *
     * Returning `WP_Error` rather than `false` keeps the response an explicit
     * "forbidden" instead of the generic REST permission message, so the admin
     * app can tell a scoping denial apart from a session problem.
     */
    public static function guardEditObject(int $post_id, ?int $user_id = null): ?\WP_Error
    {
        $user_id = $user_id ?? get_current_user_id();
        if (self::canEditObject((int) $user_id, $post_id)) {
            return null;
        }

        return new \WP_Error(
            'sikshya_forbidden_object',
            __('You are not allowed to modify this item.', 'sikshya'),
            ['status' => 403]
        );
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
