<?php

namespace Sikshya\Database\Repositories;

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
        global $wpdb;
        $this->attempts_table = $wpdb->prefix . 'sikshya_quiz_attempts';
        $this->items_table = $wpdb->prefix . 'sikshya_quiz_attempt_items';
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
