<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\AssignmentSubmissionsTable;

/**
 * Assignment submissions (custom table).
 *
 * @package Sikshya\Database\Repositories
 */
final class AssignmentSubmissionRepository
{
    private string $table_name;

    public function __construct()
    {
        $this->table_name = AssignmentSubmissionsTable::getTableName();
    }

    public function tableExists(): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name;
    }

    public function findByAssignmentAndUser(int $assignment_id, int $user_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE assignment_id = %d AND user_id = %d LIMIT 1",
                $assignment_id,
                $user_id
            )
        );

        return $row ?: null;
    }

    public function findById(int $submission_id): ?object
    {
        if ($submission_id <= 0) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
                $submission_id
            )
        );

        return $row ?: null;
    }

    /**
     * All submissions for one assignment (instructors / gradebook / reporting).
     *
     * @return array<int, object>
     */
    public function findByAssignment(int $assignment_id, int $limit = 500, int $offset = 0): array
    {
        if ($assignment_id <= 0) {
            return [];
        }
        global $wpdb;
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE assignment_id = %d ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $assignment_id,
                $limit,
                $offset
            )
        );
    }

    /**
     * @return array<int, object>
     */
    public function findByUserAndCourse(int $user_id, int $course_id, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $user_id,
                $course_id,
                $limit,
                $offset
            )
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertSubmission(array $row): int
    {
        global $wpdb;

        $defaults = [
            'assignment_id' => 0,
            'course_id' => 0,
            'user_id' => 0,
            'content' => null,
            'attachment_ids' => null,
            'status' => 'submitted',
            'grade' => null,
            'feedback' => null,
            'submitted_at' => current_time('mysql'),
            'graded_at' => null,
        ];
        $row = wp_parse_args($row, $defaults);

        $existing = $this->findByAssignmentAndUser((int) $row['assignment_id'], (int) $row['user_id']);
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                [
                    'course_id' => (int) $row['course_id'],
                    'content' => $row['content'],
                    'attachment_ids' => $row['attachment_ids'] !== null ? wp_json_encode($row['attachment_ids']) : null,
                    'status' => sanitize_text_field((string) $row['status']),
                    'grade' => $row['grade'],
                    'feedback' => $row['feedback'],
                    'submitted_at' => $row['submitted_at'],
                    'graded_at' => $row['graded_at'],
                ],
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s'],
                ['%d']
            );

            return (int) $existing->id;
        }

        $wpdb->insert(
            $this->table_name,
            [
                'assignment_id' => (int) $row['assignment_id'],
                'course_id' => (int) $row['course_id'],
                'user_id' => (int) $row['user_id'],
                'content' => $row['content'],
                'attachment_ids' => $row['attachment_ids'] !== null ? wp_json_encode($row['attachment_ids']) : null,
                'status' => sanitize_text_field((string) $row['status']),
                'grade' => $row['grade'],
                'feedback' => $row['feedback'],
                'submitted_at' => $row['submitted_at'],
                'graded_at' => $row['graded_at'],
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @param string|null $rubric_scores_json JSON payload or null to leave the column unchanged
     */
    public function gradeSubmission(
        int $submission_id,
        ?float $grade,
        string $feedback,
        string $status = 'graded',
        ?string $rubric_scores_json = null
    ): bool {
        global $wpdb;
        $data = [
            'grade' => $grade,
            'feedback' => $feedback,
            'status' => sanitize_text_field($status),
            'graded_at' => current_time('mysql'),
        ];
        $formats = ['%f', '%s', '%s', '%s'];
        if ($rubric_scores_json !== null) {
            $data['rubric_scores_json'] = $rubric_scores_json;
            $formats[] = '%s';
        }

        return false !== $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $submission_id],
            $formats,
            ['%d']
        );
    }
}

