<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\LessonRepository;

class LessonService
{
    private LessonRepository $lessonRepository;

    public function __construct()
    {
        $this->lessonRepository = new LessonRepository();
    }

    public function getAllLessons(array $args = []): array
    {
        return $this->lessonRepository->findAll($args);
    }

    public function getLesson(int $id): ?object
    {
        return $this->lessonRepository->findById($id);
    }

    public function createLesson(array $data): int
    {
        // Validate required fields
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Lesson title is required');
        }

        if (empty($data['course_id'])) {
            throw new \InvalidArgumentException('Course ID is required');
        }

        // Set default values
        $data['status'] = $data['status'] ?? 'draft';
        $data['author_id'] = $data['author_id'] ?? get_current_user_id();

        return $this->lessonRepository->create($data);
    }

    public function updateLesson(int $id, array $data): bool
    {
        // Check if lesson exists
        $lesson = $this->lessonRepository->findById($id);
        if (!$lesson) {
            throw new \InvalidArgumentException('Lesson not found');
        }

        return $this->lessonRepository->update($id, $data);
    }

    public function deleteLesson(int $id): bool
    {
        // Check if lesson exists
        $lesson = $this->lessonRepository->findById($id);
        if (!$lesson) {
            throw new \InvalidArgumentException('Lesson not found');
        }

        return $this->lessonRepository->delete($id);
    }

    public function getLessonsByCourse(int $course_id, array $args = []): array
    {
        return $this->lessonRepository->findByCourse($course_id, $args);
    }

    public function getLessonsByType(string $type, array $args = []): array
    {
        return $this->lessonRepository->findByType($type, $args);
    }

    public function getLessonsByStatus(string $status, array $args = []): array
    {
        return $this->lessonRepository->findByStatus($status, $args);
    }

    public function searchLessons(string $search_term, array $args = []): array
    {
        if (empty($search_term)) {
            return [];
        }

        return $this->lessonRepository->search($search_term, $args);
    }

    public function getLessonDuration(int $lesson_id): int
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        return $lesson ? ($lesson->duration ?? 0) : 0;
    }

    public function getLessonType(int $lesson_id): string
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        return $lesson ? ($lesson->type ?? 'text') : 'text';
    }

    public function isLessonFree(int $lesson_id): bool
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        return $lesson ? (bool) ($lesson->is_free ?? false) : false;
    }

    public function getLessonMediaUrl(int $lesson_id): string
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        return $lesson ? ($lesson->media_url ?? '') : '';
    }

    public function getLessonOrder(int $lesson_id): int
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        return $lesson ? ($lesson->order ?? 0) : 0;
    }

    public function updateLessonOrder(int $lesson_id, int $order): bool
    {
        return $this->lessonRepository->update($lesson_id, ['order' => $order]);
    }

    public function getNextLesson(int $course_id, int $current_order): ?object
    {
        return $this->lessonRepository->findNextByCourse($course_id, $current_order);
    }

    public function getPreviousLesson(int $course_id, int $current_order): ?object
    {
        return $this->lessonRepository->findPreviousByCourse($course_id, $current_order);
    }

    public function getLessonStats(int $lesson_id): array
    {
        $lesson = $this->lessonRepository->findById($lesson_id);
        if (!$lesson) {
            return [];
        }

        return [
            'id' => $lesson->ID,
            'title' => $lesson->post_title,
            'type' => $this->getLessonType($lesson_id),
            'duration' => $this->getLessonDuration($lesson_id),
            'order' => $this->getLessonOrder($lesson_id),
            'status' => $lesson->post_status,
            'created' => $lesson->post_date,
            'modified' => $lesson->post_modified,
        ];
    }
} 