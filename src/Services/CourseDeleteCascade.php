<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Tables\CertificatesTable;
use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\ProgressTable;
use Sikshya\Database\Tables\QuizAttemptsTable;

/**
 * Cascade cleanup when a course post is permanently deleted.
 *
 * Without this, deleting a course via `wp_delete_post( $id, true )` (admin
 * "Delete permanently", REST `DELETE /wp/v2/sik_course/<id>`, or
 * `CourseRepository::delete()`) leaves orphan rows everywhere:
 *   - `sikshya_enrollments` (still tied to a now-missing course post)
 *   - `sikshya_progress`    (lessons gone, rows linger)
 *   - `sikshya_quiz_attempts` (course gone, attempts linger)
 *   - `sikshya_certificates` (issued certs point at a tombstone)
 *   - child posts: chapters, lessons, quizzes, assignments
 *
 * Those orphans then surface in reports as "course #123" with a blank
 * title, break dashboards (`count() == 0` but enrolments are non-zero),
 * and inflate billing/usage metrics tied to enrolment counts.
 *
 * This service hooks `before_delete_post` (priority 5, so we run before
 * WP's own row deletes). For each post-type kind we read the affected
 * IDs, do a single bulk `wpdb->query(DELETE …)` per table, then walk the
 * child posts so their own `before_delete_post` hooks fire (giving Pro
 * addons a chance to listen for `delete_post` on `sik_lesson`/`sik_quiz`
 * /etc. without us having to also fire a `sikshya_lesson_deleted`
 * action — WP already does the dispatch).
 *
 * After everything is gone we fire `sikshya_course_deleted` with the
 * course ID + the original `WP_Post` snapshot so addons that key off
 * course-level events (Pro multi-instructor revenue rollback, marketplace
 * commission ledger, activity log, webhooks) have a single, named hook
 * to attach to.
 *
 * **Idempotency:** the hook listener short-circuits on non-course posts
 * and uses a static `$visited` set to avoid re-entry if `wp_delete_post`
 * is called for the same ID twice in one request.
 *
 * **Performance:** child-post deletion uses `wp_delete_post( …, true )`
 * which is N WP calls — fine for the typical course (low tens of
 * lessons). The DB row deletes are O(1) per table (single bulk query).
 */
final class CourseDeleteCascade
{
    /**
     * @var array<int, true> Per-request guard against re-entry.
     */
    private static array $visited = [];

    public static function init(): void
    {
        // Priority 5: run before WP's own post-and-meta delete (priority 10).
        add_action('before_delete_post', [self::class, 'onBeforeDeletePost'], 5, 2);
    }

    /**
     * @param int      $post_id ID of the post being permanently deleted.
     * @param \WP_Post $post    Post object (provided by WP since 5.5).
     */
    public static function onBeforeDeletePost($post_id, $post = null): void
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }
        if (isset(self::$visited[$post_id])) {
            return;
        }

        // WP <5.5 doesn't pass $post; resolve defensively.
        if (!$post instanceof \WP_Post) {
            $post = get_post($post_id);
        }
        if (!$post instanceof \WP_Post || $post->post_type !== PostTypes::COURSE) {
            return;
        }

        self::$visited[$post_id] = true;

        /**
         * Fires BEFORE the cascade runs. Use this to capture state that
         * needs to be archived before child rows disappear (e.g. write
         * an activity-log entry referencing the soon-to-be-deleted
         * enrolment IDs).
         */
        do_action('sikshya_before_course_cascade_delete', $post_id, $post);

        self::deleteEnrollments($post_id);
        self::deleteProgress($post_id);
        self::deleteQuizAttempts($post_id);
        self::deleteCertificates($post_id);
        self::deleteChildPosts($post_id);

        /**
         * Fires AFTER the cascade completes but before WP's own
         * `wp_delete_post` finishes removing the course post itself.
         * The course is effectively gone from a learner's POV at this
         * point — all enrolments, progress, certificates, and child
         * posts have been purged. Pro addons that maintain shadow
         * tables (multi-instructor revenue shares, marketplace
         * commissions, webhooks queue, activity log) should listen here.
         *
         * @param int      $post_id Course post ID (now empty of children).
         * @param \WP_Post $post    Course post snapshot taken before deletion.
         */
        do_action('sikshya_course_deleted', $post_id, $post);
    }

    /**
     * Bulk-delete all enrolment rows for this course in one query.
     */
    private static function deleteEnrollments(int $course_id): void
    {
        global $wpdb;
        $table = EnrollmentsTable::getTableName();
        if (!self::tableExists($table)) {
            return;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE course_id = %d", $course_id));
    }

    /**
     * Bulk-delete all progress rows for this course in one query.
     */
    private static function deleteProgress(int $course_id): void
    {
        global $wpdb;
        $table = ProgressTable::getTableName();
        if (!self::tableExists($table)) {
            return;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE course_id = %d", $course_id));
    }

    /**
     * Bulk-delete all quiz-attempt rows for this course. The attempts
     * table denormalises `course_id` directly (alongside `quiz_id`),
     * so this is a single-key delete — no postmeta lookup needed.
     */
    private static function deleteQuizAttempts(int $course_id): void
    {
        global $wpdb;
        $table = QuizAttemptsTable::getTableName();
        if (!self::tableExists($table)) {
            return;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE course_id = %d", $course_id));
    }

    /**
     * Bulk-delete all certificate rows for this course in one query.
     */
    private static function deleteCertificates(int $course_id): void
    {
        global $wpdb;
        $table = CertificatesTable::getTableName();
        if (!self::tableExists($table)) {
            return;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE course_id = %d", $course_id));
    }

    /**
     * Walk and force-delete child posts (chapters, lessons, quizzes,
     * assignments) so any addon listening on `delete_post` for those
     * post types gets fired. We use `wp_delete_post( …, true )` rather
     * than raw SQL because:
     *   - Postmeta cleanup is automatic (WP handles _thumbnail_id, etc.)
     *   - Pro addons (ContentDrip access grants, gradebook, prerequisites)
     *     hook into `delete_post` for these CPTs and need to run.
     *
     * The deletion order is: chapters → lessons → quizzes → assignments.
     * Chapters first because they hold the `_sikshya_contents` ordering
     * array; deleting them first means subsequent lesson/quiz/assignment
     * deletes don't fire pointless chapter-reorder hooks.
     */
    private static function deleteChildPosts(int $course_id): void
    {
        $kinds = [
            [PostTypes::CHAPTER,    '_sikshya_chapter_course_id'],
            [PostTypes::LESSON,     '_sikshya_lesson_course'],
            [PostTypes::QUIZ,       '_sikshya_quiz_course'],
            [PostTypes::ASSIGNMENT, '_sikshya_assignment_course'],
        ];

        foreach ($kinds as [$post_type, $meta_key]) {
            $ids = self::queryChildPostIds($course_id, $meta_key, $post_type);
            foreach ($ids as $cid) {
                wp_delete_post($cid, true);
            }
        }
    }

    /**
     * Resolve child post IDs by a course-linkage meta key.
     *
     * Reads the `meta_key = $course_id` index directly. We don't go
     * through `WP_Query` here because we want IDs of posts in ANY
     * status (including trash and auto-draft) — admins commonly want to
     * remove the orphans those would leave too.
     *
     * @return int[]
     */
    private static function queryChildPostIds(int $course_id, string $meta_key, string $post_type): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT pm.post_id
               FROM {$wpdb->postmeta} pm
               INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = %s
                AND pm.meta_value = %s
                AND p.post_type = %s",
            $meta_key,
            (string) $course_id,
            $post_type
        );

        $ids = $wpdb->get_col($sql);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * Lightweight table-exists check. Custom tables are not always
     * present on fresh installs or during plugin-update transitions.
     */
    private static function tableExists(string $table): bool
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }
}
