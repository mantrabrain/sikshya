<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Tables\EnrollmentsTable;

/**
 * Instructor dashboard metrics (core posts + enrollments custom table).
 *
 * @package Sikshya\Database\Repositories
 */
final class InstructorMetricsRepository
{
    public function countPublishedCoursesByAuthor(int $user_id): int
    {
        global $wpdb;
        if ($user_id <= 0) {
            return 0;
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status = 'publish'",
                $user_id,
                PostTypes::COURSE
            )
        );
    }

    /**
     * @return int[]
     */
    public function getAuthoredCourseIds(int $user_id): array
    {
        global $wpdb;
        if ($user_id <= 0) {
            return [];
        }
        $course_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status NOT IN ('trash', 'auto-draft')",
                $user_id,
                PostTypes::COURSE
            )
        );
        return array_values(array_filter(array_map('intval', (array) $course_ids)));
    }

    /**
     * True if the user has at least one non-trash course post as author.
     */
    public function userHasAuthoredCourse(int $user_id): bool
    {
        global $wpdb;
        if ($user_id <= 0) {
            return false;
        }
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status NOT IN ('trash', 'auto-draft') LIMIT 1",
                $user_id,
                PostTypes::COURSE
            )
        );
        return $count > 0;
    }

    /**
     * Enrollment aggregates for a set of course IDs (empty course list → zeros).
     *
     * @param int[] $course_ids
     * @return array{enrollments_total: int, enrollments_completed: int, recent_courses: array<int, array<string, mixed>>}
     */
    public function getEnrollmentStatsForCourseIds(array $course_ids): array
    {
        global $wpdb;

        $enr_table = EnrollmentsTable::getTableName();
        $enrollments_total = 0;
        $enrollments_completed = 0;
        $recent_courses = [];

        if ($course_ids === []) {
            return [
                'enrollments_total' => 0,
                'enrollments_completed' => 0,
                'recent_courses' => [],
            ];
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $enr_table)) !== $enr_table) {
            return [
                'enrollments_total' => 0,
                'enrollments_completed' => 0,
                'recent_courses' => [],
            ];
        }

        $t = esc_sql($enr_table);
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $enrollments_total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$t}` WHERE course_id IN ({$placeholders})",
                ...$course_ids
            )
        );
        $enrollments_completed = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$t}` WHERE status = 'completed' AND course_id IN ({$placeholders})",
                ...$course_ids
            )
        );

        $recent_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT course_id, COUNT(*) AS total FROM `{$t}` WHERE course_id IN ({$placeholders}) GROUP BY course_id ORDER BY total DESC LIMIT 6",
                ...$course_ids
            )
        );

        foreach ((array) $recent_rows as $row) {
            $cid = isset($row->course_id) ? (int) $row->course_id : 0;
            if ($cid <= 0) {
                continue;
            }
            $recent_courses[] = [
                'course_id' => $cid,
                'title' => get_the_title($cid),
                'enrollments' => isset($row->total) ? (int) $row->total : 0,
                'edit_url' => current_user_can('edit_post', $cid) ? get_edit_post_link($cid, '') : '',
                'view_url' => (string) get_permalink($cid),
            ];
        }

        return [
            'enrollments_total' => $enrollments_total,
            'enrollments_completed' => $enrollments_completed,
            'recent_courses' => $recent_courses,
        ];
    }
}
