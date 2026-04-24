<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\QuizAttemptsTable;
use Sikshya\Database\Tables\QuizAttemptItemsTable;

/**
 * Table-backed quiz attempts + per-question rows.
 *
 * @package Sikshya\Database\Repositories
 */
class QuizAttemptRepository
{
    private string $attempts_table;

    private string $items_table;

    public function __construct()
    {
        $this->attempts_table = QuizAttemptsTable::getTableName();
        $this->items_table = QuizAttemptItemsTable::getTableName();
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->attempts_table)) === $this->attempts_table;
    }

    public function countAttemptsForUserQuiz(int $user_id, int $quiz_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->attempts_table} WHERE user_id = %d AND quiz_id = %d AND status IN ('completed','graded')",
                $user_id,
                $quiz_id
            )
        );
    }

    public function countDistinctQuizzesForUser(int $user_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT quiz_id) FROM {$this->attempts_table} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * @return array<int, object> rows with quiz_id, best_score
     */
    public function bestScoresByQuizForUser(int $user_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT quiz_id, MAX(score) AS best_score FROM {$this->attempts_table} WHERE user_id = %d GROUP BY quiz_id",
                $user_id
            )
        );
        return is_array($rows) ? $rows : [];
    }

    public function averageScoreForUser(int $user_id): float
    {
        global $wpdb;
        $avg = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(score) FROM {$this->attempts_table} WHERE user_id = %d",
                $user_id
            )
        );
        return $avg !== null ? (float) $avg : 0.0;
    }

    /**
     * Recent completed attempts for account dashboard panels (newest first).
     *
     * @return array<int, object>
     */
    public function listRecentCompletedForUser(int $user_id, int $limit = 5): array
    {
        global $wpdb;
        $limit = max(1, min(50, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT quiz_id, course_id, score, status, completed_at
                 FROM {$this->attempts_table}
                 WHERE user_id = %d AND completed_at IS NOT NULL
                 ORDER BY completed_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            )
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param int[] $course_ids
     */
    public function countPassedInCourses(array $course_ids): int
    {
        if ($course_ids === [] || !$this->tableExists()) {
            return 0;
        }
        $course_ids = array_values(array_filter(array_map('intval', $course_ids), static fn($id) => $id > 0));
        if ($course_ids === []) {
            return 0;
        }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $sql = "SELECT COUNT(*) FROM {$this->attempts_table} WHERE course_id IN ({$placeholders}) AND status = 'passed'";

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$course_ids));
    }

    /**
     * @return array<int, object>
     */
    public function listAttemptsForUserQuiz(int $user_id, int $quiz_id, int $limit = 50): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->attempts_table} WHERE user_id = %d AND quiz_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $quiz_id,
                max(1, min(200, $limit))
            )
        );
        return is_array($rows) ? $rows : [];
    }

    public function updateAnswersData(int $attempt_id, int $user_id, string $answers_json): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $this->attempts_table,
            ['answers_data' => $answers_json],
            ['id' => $attempt_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );
    }

    public function getAttemptForUser(int $attempt_id, int $user_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->attempts_table} WHERE id = %d AND user_id = %d LIMIT 1",
                $attempt_id,
                $user_id
            )
        );
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAttempt(int $attempt_id, int $user_id, array $data): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $this->attempts_table,
            $data,
            ['id' => $attempt_id, 'user_id' => $user_id]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function createAttempt(array $row): int
    {
        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'quiz_id' => 0,
            'course_id' => 0,
            'attempt_number' => 1,
            'score' => 0.00,
            'total_questions' => 0,
            'correct_answers' => 0,
            'time_taken' => 0,
            'status' => 'completed',
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
            'answers_data' => null,
        ];
        $row = wp_parse_args($row, $defaults);

        $wpdb->insert(
            $this->attempts_table,
            [
                'user_id' => (int) $row['user_id'],
                'quiz_id' => (int) $row['quiz_id'],
                'course_id' => (int) $row['course_id'],
                'attempt_number' => (int) $row['attempt_number'],
                'score' => (float) $row['score'],
                'total_questions' => (int) $row['total_questions'],
                'correct_answers' => (int) $row['correct_answers'],
                'time_taken' => (int) $row['time_taken'],
                'status' => sanitize_text_field((string) $row['status']),
                'started_at' => $row['started_at'],
                'completed_at' => $row['completed_at'],
                'answers_data' => $row['answers_data'] !== null ? wp_json_encode($row['answers_data']) : null,
            ],
            ['%d', '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function addItem(int $attempt_id, int $question_id, ?string $answer, bool $is_correct, float $points_earned): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->items_table,
            [
                'attempt_id' => $attempt_id,
                'question_id' => $question_id,
                'answer' => $answer,
                'is_correct' => $is_correct ? 1 : 0,
                'points_earned' => $points_earned,
            ],
            ['%d', '%d', '%s', '%d', '%f']
        );

        return (int) $wpdb->insert_id;
    }
}
