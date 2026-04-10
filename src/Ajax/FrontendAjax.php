<?php

/**
 * Frontend AJAX Handler
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FrontendAjax extends AjaxAbstract
{
    /**
     * Initialize hooks
     *
     * @return void
     */
    protected function initHooks(): void
    {
        // Frontend AJAX handlers
        add_action('wp_ajax_sikshya_frontend_action', [$this, 'handleFrontendAction']);
        add_action('wp_ajax_nopriv_sikshya_frontend_action', [$this, 'handleFrontendAction']);
    }

    /**
     * Handle frontend action AJAX request
     */
    public function handleFrontendAction(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_frontend')) {
                $this->sendError('Invalid nonce');
                return;
            }

            $action = sanitize_text_field($this->getPostData('sub_action', ''));

            switch ($action) {
                case 'search_courses':
                    $this->handleSearchCourses();
                    break;
                case 'enroll_course':
                    $this->handleEnrollCourse();
                    break;
                case 'unenroll_course':
                    $this->handleUnenrollCourse();
                    break;
                case 'get_course_progress':
                    $this->handleGetCourseProgress();
                    break;
                default:
                    $this->sendError('Invalid action');
            }
        } catch (\Exception $e) {
            $this->logError('Frontend action error', $e);
            $this->sendError('Failed to process request: ' . $e->getMessage());
        }
    }

    /**
     * Handle search courses
     */
    private function handleSearchCourses(): void
    {
        $search_term = sanitize_text_field($this->getPostData('search_term', ''));
        $category = sanitize_text_field($this->getPostData('category', ''));
        $page = intval($this->getPostData('page', 1));

        $args = [
            'post_type' => 'sikshya_course',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'paged' => $page,
        ];

        if (!empty($search_term)) {
            $args['s'] = $search_term;
        }

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'sikshya_course_category',
                    'field' => 'slug',
                    'terms' => $category,
                ]
            ];
        }

        $courses = get_posts($args);
        $total = wp_count_posts('sikshya_course')->publish;

        $this->sendSuccess([
            'courses' => $courses,
            'total' => $total,
            'pages' => ceil($total / 12),
        ]);
    }

    /**
     * Handle enroll course
     */
    private function handleEnrollCourse(): void
    {
        if (!is_user_logged_in()) {
            $this->sendError('User must be logged in to enroll');
            return;
        }

        $course_id = intval($this->getPostData('course_id', 0));

        if ($course_id === 0) {
            $this->sendError('Invalid course ID');
            return;
        }

        $user_id = get_current_user_id();

        // Check if already enrolled
        $enrollment = get_user_meta($user_id, '_sikshya_enrolled_courses', true);
        if (is_array($enrollment) && in_array($course_id, $enrollment)) {
            $this->sendError('Already enrolled in this course');
            return;
        }

        // Add enrollment
        if (!is_array($enrollment)) {
            $enrollment = [];
        }
        $enrollment[] = $course_id;
        update_user_meta($user_id, '_sikshya_enrolled_courses', $enrollment);

        $this->sendSuccess(null, 'Successfully enrolled in course');
    }

    /**
     * Handle unenroll course
     */
    private function handleUnenrollCourse(): void
    {
        if (!is_user_logged_in()) {
            $this->sendError('User must be logged in to unenroll');
            return;
        }

        $course_id = intval($this->getPostData('course_id', 0));

        if ($course_id === 0) {
            $this->sendError('Invalid course ID');
            return;
        }

        $user_id = get_current_user_id();

        // Remove enrollment
        $enrollment = get_user_meta($user_id, '_sikshya_enrolled_courses', true);
        if (is_array($enrollment)) {
            $enrollment = array_diff($enrollment, [$course_id]);
            update_user_meta($user_id, '_sikshya_enrolled_courses', $enrollment);
        }

        $this->sendSuccess(null, 'Successfully unenrolled from course');
    }

    /**
     * Handle get course progress
     */
    private function handleGetCourseProgress(): void
    {
        if (!is_user_logged_in()) {
            $this->sendError('User must be logged in');
            return;
        }

        $course_id = intval($this->getPostData('course_id', 0));

        if ($course_id === 0) {
            $this->sendError('Invalid course ID');
            return;
        }

        $user_id = get_current_user_id();

        // Get progress from user meta
        $progress = get_user_meta($user_id, '_sikshya_course_progress_' . $course_id, true);

        if (!$progress) {
            $progress = [
                'completed_lessons' => [],
                'total_lessons' => 0,
                'percentage' => 0,
            ];
        }

        $this->sendSuccess($progress);
    }
}
