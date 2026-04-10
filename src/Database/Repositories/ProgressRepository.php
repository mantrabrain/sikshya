<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;

class ProgressRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sikshya_progress';
    }

    public function findAll(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->table_name}";
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
        $result = $wpdb->get_row($sql);

        return $result ?: null;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'lesson_id' => null,
            'quiz_id' => null,
            'status' => 'in_progress',
            'percentage' => 0.00,
            'time_spent' => 0,
            'completed_date' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($this->table_name, [
            'user_id' => intval($data['user_id']),
            'course_id' => intval($data['course_id']),
            'lesson_id' => $data['lesson_id'] ? intval($data['lesson_id']) : null,
            'quiz_id' => $data['quiz_id'] ? intval($data['quiz_id']) : null,
            'status' => sanitize_text_field($data['status']),
            'percentage' => floatval($data['percentage']),
            'time_spent' => intval($data['time_spent']),
            'completed_date' => $data['completed_date'],
        ]);

        return $result ? $wpdb->insert_id : 0;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update_data = [];

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['percentage'])) {
            $update_data['percentage'] = floatval($data['percentage']);
        }

        if (isset($data['time_spent'])) {
            $update_data['time_spent'] = intval($data['time_spent']);
        }

        if (isset($data['completed_date'])) {
            $update_data['completed_date'] = $data['completed_date'];
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update($this->table_name, $update_data, ['id' => $id]);

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table_name, ['id' => $id]);

        return $result !== false;
    }

    public function findByUser(int $user_id, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE user_id = %d", $user_id);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findByCourse(int $course_id, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE course_id = %d", $course_id);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findByUserAndCourse(int $user_id, int $course_id): ?object
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        );

        $result = $wpdb->get_row($sql);

        return $result ?: null;
    }

    public function getCourseProgress(int $user_id, int $course_id): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE user_id = %d AND course_id = %d 
             ORDER BY updated_at DESC",
            $user_id,
            $course_id
        );

        return $wpdb->get_results($sql);
    }

    public function updateCourseProgress(int $user_id, int $course_id, array $progress_data): bool
    {
        $progress = $this->findByUserAndCourse($user_id, $course_id);

        if ($progress) {
            return $this->update($progress->id, $progress_data);
        } else {
            $progress_data['user_id'] = $user_id;
            $progress_data['course_id'] = $course_id;
            return $this->create($progress_data) > 0;
        }
    }

    public function initializeProgress(int $user_id, int $course_id): bool
    {
        $existing = $this->findByUserAndCourse($user_id, $course_id);

        if (!$existing) {
            $result = $this->create([
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'not_started',
                'percentage' => 0.00,
            ]);

            return $result > 0;
        }

        return true;
    }

    public function deleteProgress(int $user_id, int $course_id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table_name, [
            'user_id' => $user_id,
            'course_id' => $course_id,
        ]);

        return $result !== false;
    }

    public function markLessonComplete(int $user_id, int $course_id, int $lesson_id): bool
    {
        if ($this->hasLessonCompletion($user_id, $course_id, $lesson_id)) {
            return true;
        }

        $progress_data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'status' => 'completed',
            'percentage' => 100.00,
            'completed_date' => current_time('mysql'),
        ];

        return $this->create($progress_data) > 0;
    }

    /**
     * Whether the learner has a completed progress row for this lesson (lesson rows use quiz_id NULL).
     */
    public function hasLessonCompletion(int $user_id, int $course_id, int $lesson_id): bool
    {
        global $wpdb;

        $n = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE user_id = %d AND course_id = %d AND lesson_id = %d 
                AND quiz_id IS NULL AND status = %s",
                $user_id,
                $course_id,
                $lesson_id,
                'completed'
            )
        );

        return $n > 0;
    }

    /**
     * Whether the learner has a completed quiz attempt row for this quiz.
     */
    public function hasQuizCompletion(int $user_id, int $course_id, int $quiz_id): bool
    {
        global $wpdb;

        $n = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE user_id = %d AND course_id = %d AND quiz_id = %d AND status = %s",
                $user_id,
                $course_id,
                $quiz_id,
                'completed'
            )
        );

        return $n > 0;
    }

    /**
     * Count distinct lessons marked completed for a course.
     */
    public function countCompletedLessons(int $user_id, int $course_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT lesson_id) FROM {$this->table_name}
                WHERE user_id = %d AND course_id = %d AND lesson_id IS NOT NULL
                AND quiz_id IS NULL AND status = %s",
                $user_id,
                $course_id,
                'completed'
            )
        );
    }

    public function markQuizComplete(int $user_id, int $course_id, int $quiz_id, float $score): bool
    {
        $progress_data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'quiz_id' => $quiz_id,
            'status' => 'completed',
            'percentage' => $score,
            'completed_date' => current_time('mysql'),
        ];

        return $this->create($progress_data) > 0;
    }

    public function getOverallProgress(int $user_id, int $course_id): float
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT AVG(percentage) as overall_progress 
             FROM {$this->table_name} 
             WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        );

        $result = $wpdb->get_var($sql);

        return $result ? floatval($result) : 0.00;
    }

    public function countByUser(int $user_id): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d", $user_id);

        return (int) $wpdb->get_var($sql);
    }

    public function countByCourse(int $course_id): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE course_id = %d", $course_id);

        return (int) $wpdb->get_var($sql);
    }

    public function countByStatus(string $status): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status);

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Progress rows for a user + lesson (any quiz/lesson rows).
     *
     * @return array<int, object>
     */
    public function findByUserAndLesson(int $user_id, int $lesson_id): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND lesson_id = %d ORDER BY updated_at DESC",
            $user_id,
            $lesson_id
        );

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Remove completed lesson rows (not quiz rows) for a lesson.
     */
    public function deleteLessonCompletionRows(int $user_id, int $course_id, int $lesson_id): bool
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE user_id = %d AND course_id = %d AND lesson_id = %d AND quiz_id IS NULL",
            $user_id,
            $course_id,
            $lesson_id
        );

        return false !== $wpdb->query($sql);
    }
}
