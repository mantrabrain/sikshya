<?php

namespace Sikshya\Models;

use WP_Post;

/**
 * Lesson Model
 * 
 * Handles all lesson-related data operations
 * 
 * @package Sikshya\Models
 */
class Lesson
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // No dependencies needed for this model
    }

    /**
     * Get all lessons
     * 
     * @param array $args Query arguments
     * @return array Array of lessons
     */
    public function getAll(array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);
        
        $query = new \WP_Query($args);
        
        return $query->posts;
    }

    /**
     * Get lesson by ID
     * 
     * @param int $lesson_id Lesson ID
     * @return WP_Post|null Lesson post or null
     */
    public function getById(int $lesson_id): ?WP_Post
    {
        $lesson = get_post($lesson_id);
        
        if (!$lesson || $lesson->post_type !== 'sikshya_lesson') {
            return null;
        }

        return $lesson;
    }

    /**
     * Create a new lesson
     * 
     * @param array $data Lesson data
     * @return int|WP_Error Lesson ID or error
     */
    public function create(array $data)
    {
        $defaults = [
            'post_title' => '',
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'meta_input' => []
        ];

        $data = wp_parse_args($data, $defaults);
        
        // Set post type
        $data['post_type'] = 'sikshya_lesson';
        
        // Create the lesson
        $lesson_id = wp_insert_post($data);
        
        if (is_wp_error($lesson_id)) {
            return $lesson_id;
        }

        // Set default meta values
        $this->setDefaultMeta($lesson_id);
        
        return $lesson_id;
    }

    /**
     * Update lesson
     * 
     * @param int $lesson_id Lesson ID
     * @param array $data Lesson data
     * @return int|WP_Error Lesson ID or error
     */
    public function update(int $lesson_id, array $data)
    {
        $data['ID'] = $lesson_id;
        $data['post_type'] = 'sikshya_lesson';
        
        return wp_update_post($data);
    }

    /**
     * Delete lesson
     * 
     * @param int $lesson_id Lesson ID
     * @return bool|WP_Error Success or error
     */
    public function delete(int $lesson_id)
    {
        return wp_delete_post($lesson_id, true);
    }

    /**
     * Get lesson meta
     * 
     * @param int $lesson_id Lesson ID
     * @param string $key Meta key
     * @param bool $single Whether to return a single value
     * @return mixed Meta value
     */
    public function getMeta(int $lesson_id, string $key, bool $single = true)
    {
        return get_post_meta($lesson_id, $key, $single);
    }

    /**
     * Set lesson meta
     * 
     * @param int $lesson_id Lesson ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return int|bool Meta ID or false
     */
    public function setMeta(int $lesson_id, string $key, $value)
    {
        return update_post_meta($lesson_id, $key, $value);
    }

    /**
     * Get lesson course
     * 
     * @param int $lesson_id Lesson ID
     * @return int Course ID
     */
    public function getCourse(int $lesson_id): int
    {
        $course = $this->getMeta($lesson_id, '_sikshya_lesson_course', true);
        return (int) ($course ?: 0);
    }

    /**
     * Set lesson course
     * 
     * @param int $lesson_id Lesson ID
     * @param int $course_id Course ID
     * @return int|bool Meta ID or false
     */
    public function setCourse(int $lesson_id, int $course_id)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_course', $course_id);
    }

    /**
     * Get lesson type
     * 
     * @param int $lesson_id Lesson ID
     * @return string Lesson type
     */
    public function getType(int $lesson_id): string
    {
        $type = $this->getMeta($lesson_id, '_sikshya_lesson_type', true);
        return $type ?: 'text';
    }

    /**
     * Set lesson type
     * 
     * @param int $lesson_id Lesson ID
     * @param string $type Lesson type
     * @return int|bool Meta ID or false
     */
    public function setType(int $lesson_id, string $type)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_type', $type);
    }

    /**
     * Get lesson duration
     * 
     * @param int $lesson_id Lesson ID
     * @return int Lesson duration in minutes
     */
    public function getDuration(int $lesson_id): int
    {
        $duration = $this->getMeta($lesson_id, '_sikshya_lesson_duration', true);
        return (int) ($duration ?: 0);
    }

    /**
     * Set lesson duration
     * 
     * @param int $lesson_id Lesson ID
     * @param int $duration Lesson duration in minutes
     * @return int|bool Meta ID or false
     */
    public function setDuration(int $lesson_id, int $duration)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_duration', $duration);
    }

    /**
     * Get lesson order
     * 
     * @param int $lesson_id Lesson ID
     * @return int Lesson order
     */
    public function getOrder(int $lesson_id): int
    {
        $order = $this->getMeta($lesson_id, '_sikshya_lesson_order', true);
        return (int) ($order ?: 0);
    }

    /**
     * Set lesson order
     * 
     * @param int $lesson_id Lesson ID
     * @param int $order Lesson order
     * @return int|bool Meta ID or false
     */
    public function setOrder(int $lesson_id, int $order)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_order', $order);
    }

    /**
     * Get lesson prerequisites
     * 
     * @param int $lesson_id Lesson ID
     * @return array Prerequisite lesson IDs
     */
    public function getPrerequisites(int $lesson_id): array
    {
        $prerequisites = $this->getMeta($lesson_id, '_sikshya_lesson_prerequisites', true);
        return is_array($prerequisites) ? $prerequisites : [];
    }

    /**
     * Set lesson prerequisites
     * 
     * @param int $lesson_id Lesson ID
     * @param array $prerequisites Prerequisite lesson IDs
     * @return int|bool Meta ID or false
     */
    public function setPrerequisites(int $lesson_id, array $prerequisites)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_prerequisites', $prerequisites);
    }

    /**
     * Get lesson attachments
     * 
     * @param int $lesson_id Lesson ID
     * @return array Lesson attachments
     */
    public function getAttachments(int $lesson_id): array
    {
        $attachments = $this->getMeta($lesson_id, '_sikshya_lesson_attachments', true);
        return is_array($attachments) ? $attachments : [];
    }

    /**
     * Set lesson attachments
     * 
     * @param int $lesson_id Lesson ID
     * @param array $attachments Lesson attachments
     * @return int|bool Meta ID or false
     */
    public function setAttachments(int $lesson_id, array $attachments)
    {
        return $this->setMeta($lesson_id, '_sikshya_lesson_attachments', $attachments);
    }

    /**
     * Get lessons by course
     * 
     * @param int $course_id Course ID
     * @return array Lessons
     */
    public function getByCourse(int $course_id): array
    {
        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_sikshya_lesson_order',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_sikshya_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Check if lesson is completed by user
     * 
     * @param int $lesson_id Lesson ID
     * @param int $user_id User ID
     * @return bool True if completed
     */
    public function isCompleted(int $lesson_id, int $user_id): bool
    {
        $completed = get_user_meta($user_id, '_sikshya_completed_lessons', true);
        return is_array($completed) && in_array($lesson_id, $completed);
    }

    /**
     * Mark lesson as completed
     * 
     * @param int $lesson_id Lesson ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public function markCompleted(int $lesson_id, int $user_id): bool
    {
        $completed = get_user_meta($user_id, '_sikshya_completed_lessons', true);
        
        if (!is_array($completed)) {
            $completed = [];
        }

        if (!in_array($lesson_id, $completed)) {
            $completed[] = $lesson_id;
            return update_user_meta($user_id, '_sikshya_completed_lessons', $completed);
        }

        return true;
    }

    /**
     * Mark lesson as incomplete
     * 
     * @param int $lesson_id Lesson ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public function markIncomplete(int $lesson_id, int $user_id): bool
    {
        $completed = get_user_meta($user_id, '_sikshya_completed_lessons', true);
        
        if (is_array($completed)) {
            $completed = array_diff($completed, [$lesson_id]);
            return update_user_meta($user_id, '_sikshya_completed_lessons', $completed);
        }

        return true;
    }

    /**
     * Set default meta values for new lesson
     * 
     * @param int $lesson_id Lesson ID
     */
    private function setDefaultMeta(int $lesson_id): void
    {
        $defaults = [
            '_sikshya_lesson_course' => 0,
            '_sikshya_lesson_type' => 'text',
            '_sikshya_lesson_duration' => 0,
            '_sikshya_lesson_order' => 0,
            '_sikshya_lesson_prerequisites' => [],
            '_sikshya_lesson_attachments' => [],
        ];

        foreach ($defaults as $key => $value) {
            $this->setMeta($lesson_id, $key, $value);
        }
    }
} 