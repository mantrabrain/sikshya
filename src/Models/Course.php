<?php

namespace Sikshya\Models;

use WP_Post;
use Sikshya\Constants\PostTypes;

/**
 * Course Model
 * 
 * Handles all course-related data operations
 * 
 * @package Sikshya\Models
 */
class Course
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // No dependencies needed for this model
    }

    /**
     * Get all courses
     * 
     * @param array $args Query arguments
     * @return array Array of courses
     */
    public function getAll(array $args = []): array
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        
        $query = new \WP_Query($args);
        
        return $query->posts;
    }

    /**
     * Get course by ID
     * 
     * @param int $course_id Course ID
     * @return WP_Post|null Course post or null
     */
    public function getById(int $course_id): ?WP_Post
    {
        $course = get_post($course_id);
        
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            return null;
        }

        return $course;
    }

    /**
     * Create a new course
     * 
     * @param array $data Course data
     * @return int|WP_Error Course ID or error
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
        $data['post_type'] = PostTypes::COURSE;
        
        // Create the course
        $course_id = wp_insert_post($data);
        
        if (is_wp_error($course_id)) {
            return $course_id;
        }

        // Set default meta values
        $this->setDefaultMeta($course_id);
        
        return $course_id;
    }

    /**
     * Update course
     * 
     * @param int $course_id Course ID
     * @param array $data Course data
     * @return int|WP_Error Course ID or error
     */
    public function update(int $course_id, array $data)
    {
        $data['ID'] = $course_id;
        $data['post_type'] = PostTypes::COURSE;
        
        return wp_update_post($data);
    }

    /**
     * Delete course
     * 
     * @param int $course_id Course ID
     * @return bool|WP_Error Success or error
     */
    public function delete(int $course_id)
    {
        return wp_delete_post($course_id, true);
    }

    /**
     * Get course meta
     * 
     * @param int $course_id Course ID
     * @param string $key Meta key
     * @param bool $single Whether to return a single value
     * @return mixed Meta value
     */
    public function getMeta(int $course_id, string $key, bool $single = true)
    {
        return get_post_meta($course_id, $key, $single);
    }

    /**
     * Set course meta
     * 
     * @param int $course_id Course ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return int|bool Meta ID or false
     */
    public function setMeta(int $course_id, string $key, $value)
    {
        return update_post_meta($course_id, $key, $value);
    }

    /**
     * Get course price
     * 
     * @param int $course_id Course ID
     * @return float Course price
     */
    public function getPrice(int $course_id): float
    {
        $price = $this->getMeta($course_id, '_sikshya_course_price', true);
        return (float) ($price ?: 0);
    }

    /**
     * Set course price
     * 
     * @param int $course_id Course ID
     * @param float $price Course price
     * @return int|bool Meta ID or false
     */
    public function setPrice(int $course_id, float $price)
    {
        return $this->setMeta($course_id, '_sikshya_course_price', $price);
    }

    /**
     * Get course duration
     * 
     * @param int $course_id Course ID
     * @return int Course duration in minutes
     */
    public function getDuration(int $course_id): int
    {
        $duration = $this->getMeta($course_id, '_sikshya_course_duration', true);
        return (int) ($duration ?: 0);
    }

    /**
     * Set course duration
     * 
     * @param int $course_id Course ID
     * @param int $duration Course duration in minutes
     * @return int|bool Meta ID or false
     */
    public function setDuration(int $course_id, int $duration)
    {
        return $this->setMeta($course_id, '_sikshya_course_duration', $duration);
    }

    /**
     * Get course difficulty
     * 
     * @param int $course_id Course ID
     * @return string Course difficulty
     */
    public function getDifficulty(int $course_id): string
    {
        $difficulty = $this->getMeta($course_id, '_sikshya_course_difficulty', true);
        return $difficulty ?: 'beginner';
    }

    /**
     * Set course difficulty
     * 
     * @param int $course_id Course ID
     * @param string $difficulty Course difficulty
     * @return int|bool Meta ID or false
     */
    public function setDifficulty(int $course_id, string $difficulty)
    {
        return $this->setMeta($course_id, '_sikshya_course_difficulty', $difficulty);
    }

    /**
     * Get course enrollment count
     * 
     * @param int $course_id Course ID
     * @return int Enrollment count
     */
    public function getEnrollmentCount(int $course_id): int
    {
        $count = $this->getMeta($course_id, '_sikshya_enrollment_count', true);
        return (int) ($count ?: 0);
    }

    /**
     * Increment enrollment count
     * 
     * @param int $course_id Course ID
     * @return int|bool Meta ID or false
     */
    public function incrementEnrollmentCount(int $course_id)
    {
        $current_count = $this->getEnrollmentCount($course_id);
        return $this->setMeta($course_id, '_sikshya_enrollment_count', $current_count + 1);
    }

    /**
     * Get course instructor
     * 
     * @param int $course_id Course ID
     * @return int Instructor user ID
     */
    public function getInstructor(int $course_id): int
    {
        $instructor = $this->getMeta($course_id, '_sikshya_course_instructor', true);
        return (int) ($instructor ?: get_post_field('post_author', $course_id));
    }

    /**
     * Set course instructor
     * 
     * @param int $course_id Course ID
     * @param int $instructor_id Instructor user ID
     * @return int|bool Meta ID or false
     */
    public function setInstructor(int $course_id, int $instructor_id)
    {
        return $this->setMeta($course_id, '_sikshya_course_instructor', $instructor_id);
    }

    /**
     * Get course categories
     * 
     * @param int $course_id Course ID
     * @return array Course categories
     */
    public function getCategories(int $course_id): array
    {
        return wp_get_post_terms($course_id, 'sikshya_course_category', ['fields' => 'all']);
    }

    /**
     * Set course categories
     * 
     * @param int $course_id Course ID
     * @param array $category_ids Category IDs
     * @return array|WP_Error Term IDs or error
     */
    public function setCategories(int $course_id, array $category_ids)
    {
        return wp_set_post_terms($course_id, $category_ids, 'sikshya_course_category');
    }

    /**
     * Get course tags
     * 
     * @param int $course_id Course ID
     * @return array Course tags
     */
    public function getTags(int $course_id): array
    {
        return wp_get_post_terms($course_id, 'sikshya_course_tag', ['fields' => 'all']);
    }

    /**
     * Set course tags
     * 
     * @param int $course_id Course ID
     * @param array $tag_ids Tag IDs
     * @return array|WP_Error Term IDs or error
     */
    public function setTags(int $course_id, array $tag_ids)
    {
        return wp_set_post_terms($course_id, $tag_ids, 'sikshya_course_tag');
    }

    /**
     * Check if course is free
     * 
     * @param int $course_id Course ID
     * @return bool True if free
     */
    public function isFree(int $course_id): bool
    {
        return $this->getPrice($course_id) <= 0;
    }

    /**
     * Check if course is published
     * 
     * @param int $course_id Course ID
     * @return bool True if published
     */
    public function isPublished(int $course_id): bool
    {
        $course = $this->getById($course_id);
        return $course && $course->post_status === 'publish';
    }

    /**
     * Get course statistics
     * 
     * @param int $course_id Course ID
     * @return array Course statistics
     */
    public function getStatistics(int $course_id): array
    {
        return [
            'enrollment_count' => $this->getEnrollmentCount($course_id),
            'completion_count' => $this->getCompletionCount($course_id),
            'average_rating' => $this->getAverageRating($course_id),
            'total_lessons' => $this->getTotalLessons($course_id),
            'total_quizzes' => $this->getTotalQuizzes($course_id),
        ];
    }

    /**
     * Get completion count
     * 
     * @param int $course_id Course ID
     * @return int Completion count
     */
    public function getCompletionCount(int $course_id): int
    {
        $count = $this->getMeta($course_id, '_sikshya_completion_count', true);
        return (int) ($count ?: 0);
    }

    /**
     * Get average rating
     * 
     * @param int $course_id Course ID
     * @return float Average rating
     */
    public function getAverageRating(int $course_id): float
    {
        $rating = $this->getMeta($course_id, '_sikshya_average_rating', true);
        return (float) ($rating ?: 0);
    }

    /**
     * Get total lessons
     * 
     * @param int $course_id Course ID
     * @return int Total lessons
     */
    public function getTotalLessons(int $course_id): int
    {
        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sikshya_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get total quizzes
     * 
     * @param int $course_id Course ID
     * @return int Total quizzes
     */
    public function getTotalQuizzes(int $course_id): int
    {
        $args = [
            'post_type' => 'sikshya_quiz',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sikshya_quiz_course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Set default meta values for new course
     * 
     * @param int $course_id Course ID
     */
    private function setDefaultMeta(int $course_id): void
    {
        $defaults = [
            '_sikshya_course_price' => 0,
            '_sikshya_course_duration' => 0,
            '_sikshya_course_difficulty' => 'beginner',
            '_sikshya_enrollment_count' => 0,
            '_sikshya_completion_count' => 0,
            '_sikshya_average_rating' => 0,
            '_sikshya_course_instructor' => get_current_user_id(),
        ];

        foreach ($defaults as $key => $value) {
            $this->setMeta($course_id, $key, $value);
        }
    }
} 