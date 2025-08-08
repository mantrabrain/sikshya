<?php

namespace Sikshya\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Models\Course;
use Sikshya\Models\Lesson;
use Sikshya\Models\Quiz;
use Sikshya\Models\Enrollment;

/**
 * Course Controller
 * 
 * Handles all course-related business logic and HTTP requests
 * 
 * @package Sikshya\Controllers
 */
class CourseController
{
    /**
     * Plugin instance
     */
    private Plugin $plugin;

    /**
     * Course model
     */
    private Course $courseModel;

    /**
     * Lesson model
     */
    private Lesson $lessonModel;

    /**
     * Quiz model
     */
    private Quiz $quizModel;

    /**
     * Enrollment model
     */
    private Enrollment $enrollmentModel;

    /**
     * Constructor
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->courseModel = new Course();
        $this->lessonModel = new Lesson();
        $this->quizModel = new Quiz();
        $this->enrollmentModel = new Enrollment();
        
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        // Admin hooks
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX hooks
        add_action('wp_ajax_sikshya_create_course', [$this, 'handleCreateCourse']);
        add_action('wp_ajax_sikshya_update_course', [$this, 'handleUpdateCourse']);
        add_action('wp_ajax_sikshya_delete_course', [$this, 'handleDeleteCourse']);
        add_action('wp_ajax_sikshya_get_course', [$this, 'handleGetCourse']);
        add_action('wp_ajax_sikshya_get_courses', [$this, 'handleGetCourses']);
        add_action('wp_ajax_sikshya_duplicate_course', [$this, 'handleDuplicateCourse']);
        
        // Frontend hooks
        add_action('wp_ajax_sikshya_enroll_course', [$this, 'handleEnrollCourse']);
        add_action('wp_ajax_sikshya_unenroll_course', [$this, 'handleUnenrollCourse']);
        add_action('wp_ajax_sikshya_get_course_progress', [$this, 'handleGetCourseProgress']);
        
        // REST API hooks
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        add_submenu_page(
            'sikshya-dashboard',
            __('Courses', 'sikshya'),
            __('Courses', 'sikshya'),
            'manage_options',
            'sikshya-courses',
            [$this, 'renderCoursesPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'sikshya-courses') !== false) {
            wp_enqueue_script(
                'sikshya-courses-admin',
                $this->plugin->getAssetUrl('js/courses-admin.js'),
                ['jquery'],
                SIKSHYA_VERSION,
                true
            );
            
            wp_localize_script('sikshya-courses-admin', 'sikshya_courses', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sikshya_courses_nonce'),
            ]);
        }
    }

    /**
     * Render courses page
     */
    public function renderCoursesPage(): void
    {
        $this->plugin->getView()->render('admin/courses/index');
    }

    /**
     * Handle create course AJAX request
     */
    public function handleCreateCourse(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $data = $this->sanitizeCourseData($_POST);
            
            $course_id = $this->courseModel->create($data);
            
            if (is_wp_error($course_id)) {
                wp_send_json_error($course_id->get_error_message());
                return;
            }

            wp_send_json_success([
                'course_id' => $course_id,
                'message' => __('Course created successfully', 'sikshya'),
                'redirect_url' => admin_url('admin.php?page=sikshya-courses&action=edit&id=' . $course_id)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle update course AJAX request
     */
    public function handleUpdateCourse(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            $data = $this->sanitizeCourseData($_POST);
            
            $result = $this->courseModel->update($course_id, $data);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            wp_send_json_success([
                'message' => __('Course updated successfully', 'sikshya')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle delete course AJAX request
     */
    public function handleDeleteCourse(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            
            $result = $this->courseModel->delete($course_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            wp_send_json_success([
                'message' => __('Course deleted successfully', 'sikshya')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle get course AJAX request
     */
    public function handleGetCourse(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            
            $course = $this->courseModel->getById($course_id);
            
            if (!$course) {
                wp_send_json_error(__('Course not found', 'sikshya'));
                return;
            }

            $course_data = $this->prepareCourseData($course);
            
            wp_send_json_success($course_data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle get courses AJAX request
     */
    public function handleGetCourses(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $args = [
                'posts_per_page' => (int) ($_POST['per_page'] ?? 10),
                'paged' => (int) ($_POST['page'] ?? 1),
                'orderby' => sanitize_text_field($_POST['orderby'] ?? 'date'),
                'order' => sanitize_text_field($_POST['order'] ?? 'DESC'),
            ];

            // Add search filter
            if (!empty($_POST['search'])) {
                $args['s'] = sanitize_text_field($_POST['search']);
            }

            // Add status filter
            if (!empty($_POST['status'])) {
                $args['post_status'] = sanitize_text_field($_POST['status']);
            }

            $courses = $this->courseModel->getAll($args);
            $total_courses = wp_count_posts('sikshya_course');
            
            $courses_data = [];
            foreach ($courses as $course) {
                $courses_data[] = $this->prepareCourseData($course);
            }

            wp_send_json_success([
                'courses' => $courses_data,
                'total' => $total_courses,
                'pagination' => [
                    'current_page' => $args['paged'],
                    'per_page' => $args['posts_per_page'],
                    'total_pages' => ceil(count($courses) / $args['posts_per_page'])
                ]
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle duplicate course AJAX request
     */
    public function handleDuplicateCourse(): void
    {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            
            $course = $this->courseModel->getById($course_id);
            
            if (!$course) {
                wp_send_json_error(__('Course not found', 'sikshya'));
                return;
            }

            // Create duplicate course
            $duplicate_data = [
                'post_title' => $course->post_title . ' (Copy)',
                'post_content' => $course->post_content,
                'post_excerpt' => $course->post_excerpt,
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
            ];

            $new_course_id = $this->courseModel->create($duplicate_data);
            
            if (is_wp_error($new_course_id)) {
                wp_send_json_error($new_course_id->get_error_message());
                return;
            }

            // Copy course meta
            $this->copyCourseMeta($course_id, $new_course_id);

            wp_send_json_success([
                'course_id' => $new_course_id,
                'message' => __('Course duplicated successfully', 'sikshya'),
                'redirect_url' => admin_url('admin.php?page=sikshya-courses&action=edit&id=' . $new_course_id)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle enroll course AJAX request
     */
    public function handleEnrollCourse(): void
    {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(__('You must be logged in to enroll', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            $user_id = get_current_user_id();
            
            // Check if course exists
            $course = $this->courseModel->getById($course_id);
            if (!$course) {
                wp_send_json_error(__('Course not found', 'sikshya'));
                return;
            }

            // Check if already enrolled
            if ($this->enrollmentModel->isEnrolled($user_id, $course_id)) {
                wp_send_json_error(__('You are already enrolled in this course', 'sikshya'));
                return;
            }

            // Enroll the user
            $enrollment_id = $this->enrollmentModel->enroll($user_id, $course_id);
            
            if (is_wp_error($enrollment_id)) {
                wp_send_json_error($enrollment_id->get_error_message());
                return;
            }

            // Increment enrollment count
            $this->courseModel->incrementEnrollmentCount($course_id);

            wp_send_json_success([
                'enrollment_id' => $enrollment_id,
                'message' => __('Successfully enrolled in course', 'sikshya')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle unenroll course AJAX request
     */
    public function handleUnenrollCourse(): void
    {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(__('You must be logged in to unenroll', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            $user_id = get_current_user_id();
            
            $result = $this->enrollmentModel->unenroll($user_id, $course_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            wp_send_json_success([
                'message' => __('Successfully unenrolled from course', 'sikshya')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle get course progress AJAX request
     */
    public function handleGetCourseProgress(): void
    {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(__('You must be logged in', 'sikshya'));
                return;
            }

            check_ajax_referer('sikshya_courses_nonce', 'nonce');

            $course_id = (int) $_POST['course_id'];
            $user_id = get_current_user_id();
            
            $progress = $this->enrollmentModel->getProgress($user_id, $course_id);
            
            wp_send_json_success($progress);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sikshya/v1', '/courses', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getCoursesApi'],
                'permission_callback' => [$this, 'getCoursesPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createCourseApi'],
                'permission_callback' => [$this, 'createCoursePermission'],
            ]
        ]);

        register_rest_route('sikshya/v1', '/courses/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getCourseApi'],
                'permission_callback' => [$this, 'getCoursePermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateCourseApi'],
                'permission_callback' => [$this, 'updateCoursePermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteCourseApi'],
                'permission_callback' => [$this, 'deleteCoursePermission'],
            ]
        ]);
    }

    /**
     * Sanitize course data
     */
    private function sanitizeCourseData(array $data): array
    {
        return [
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_status' => sanitize_text_field($data['status'] ?? 'draft'),
            'meta_input' => [
                '_sikshya_course_price' => (float) ($data['price'] ?? 0),
                '_sikshya_course_duration' => (int) ($data['duration'] ?? 0),
                '_sikshya_course_difficulty' => sanitize_text_field($data['difficulty'] ?? 'beginner'),
                '_sikshya_course_instructor' => (int) ($data['instructor'] ?? get_current_user_id()),
            ]
        ];
    }

    /**
     * Prepare course data for response
     */
    private function prepareCourseData($course): array
    {
        $course_id = $course->ID;
        
        return [
            'id' => $course_id,
            'title' => $course->post_title,
            'content' => $course->post_content,
            'excerpt' => $course->post_excerpt,
            'status' => $course->post_status,
            'date_created' => $course->post_date,
            'date_modified' => $course->post_modified,
            'author' => $course->post_author,
            'price' => $this->courseModel->getPrice($course_id),
            'duration' => $this->courseModel->getDuration($course_id),
            'difficulty' => $this->courseModel->getDifficulty($course_id),
            'instructor' => $this->courseModel->getInstructor($course_id),
            'enrollment_count' => $this->courseModel->getEnrollmentCount($course_id),
            'categories' => $this->courseModel->getCategories($course_id),
            'tags' => $this->courseModel->getTags($course_id),
            'statistics' => $this->courseModel->getStatistics($course_id),
            'is_free' => $this->courseModel->isFree($course_id),
            'is_published' => $this->courseModel->isPublished($course_id),
        ];
    }

    /**
     * Copy course meta from one course to another
     */
    private function copyCourseMeta(int $source_id, int $target_id): void
    {
        $meta_keys = [
            '_sikshya_course_price',
            '_sikshya_course_duration',
            '_sikshya_course_difficulty',
            '_sikshya_course_instructor',
        ];

        foreach ($meta_keys as $key) {
            $value = get_post_meta($source_id, $key, true);
            if ($value !== '') {
                update_post_meta($target_id, $key, $value);
            }
        }

        // Copy categories and tags
        $categories = wp_get_post_terms($source_id, 'sikshya_course_category', ['fields' => 'ids']);
        $tags = wp_get_post_terms($source_id, 'sikshya_course_tag', ['fields' => 'ids']);

        if (!empty($categories)) {
            wp_set_post_terms($target_id, $categories, 'sikshya_course_category');
        }

        if (!empty($tags)) {
            wp_set_post_terms($target_id, $tags, 'sikshya_course_tag');
        }
    }

    // REST API methods (implementations would go here)
    public function getCoursesApi($request) { /* Implementation */ }
    public function createCourseApi($request) { /* Implementation */ }
    public function getCourseApi($request) { /* Implementation */ }
    public function updateCourseApi($request) { /* Implementation */ }
    public function deleteCourseApi($request) { /* Implementation */ }
    
    // Permission callbacks
    public function getCoursesPermission() { return true; }
    public function createCoursePermission() { return current_user_can('manage_options'); }
    public function getCoursePermission() { return true; }
    public function updateCoursePermission() { return current_user_can('manage_options'); }
    public function deleteCoursePermission() { return current_user_can('manage_options'); }
} 