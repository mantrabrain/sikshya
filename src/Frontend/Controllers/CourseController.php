<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Services\CourseService;

class CourseController
{
    private Plugin $plugin;
    private CourseService $courseService;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->courseService = new CourseService();
        $this->initHooks();
    }

    private function initHooks(): void
    {
        add_action('wp_ajax_sikshya_enroll_course', [$this, 'handleEnrollment']);
        add_action('wp_ajax_nopriv_sikshya_enroll_course', [$this, 'handleEnrollment']);
        add_action('wp_ajax_sikshya_search_courses', [$this, 'handleSearch']);
        add_action('wp_ajax_nopriv_sikshya_search_courses', [$this, 'handleSearch']);
    }

    public function index(): void
    {
        $args = [
            'posts_per_page' => get_query_var('posts_per_page', 12),
            'paged' => get_query_var('paged', 1),
        ];

        $courses = $this->courseService->getAllCourses($args);
        $featured_courses = $this->courseService->getFeaturedCourses(6);
        $popular_courses = $this->courseService->getPopularCourses(6);

        $this->render('courses/index', [
            'courses' => $courses,
            'featured_courses' => $featured_courses,
            'popular_courses' => $popular_courses,
            'pagination' => $this->getPagination($args),
        ]);
    }

    public function single(): void
    {
        $course_id = get_the_ID();
        $course = $this->courseService->getCourse($course_id);

        if (!$course) {
            wp_die(__('Course not found', 'sikshya'));
        }

        $user_id = get_current_user_id();
        $is_enrolled = $this->courseService->isUserEnrolled($user_id, $course_id);
        $course_progress = $is_enrolled ? $this->courseService->getCourseProgress($user_id, $course_id) : [];
        $course_stats = $this->courseService->getCourseStats($course_id);

        $this->render('courses/single', [
            'course' => $course,
            'is_enrolled' => $is_enrolled,
            'course_progress' => $course_progress,
            'course_stats' => $course_stats,
            'course_price' => $this->courseService->getCoursePrice($course_id),
            'course_sale_price' => $this->courseService->getCourseSalePrice($course_id),
            'course_duration' => $this->courseService->getCourseDuration($course_id),
            'course_difficulty' => $this->courseService->getCourseDifficulty($course_id),
        ]);
    }

    public function category(): void
    {
        $category = get_queried_object();
        $args = [
            'posts_per_page' => get_query_var('posts_per_page', 12),
            'paged' => get_query_var('paged', 1),
            'tax_query' => [
                [
                    'taxonomy' => 'sikshya_course_category',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
                ],
            ],
        ];

        $courses = $this->courseService->getAllCourses($args);

        $this->render('courses/category', [
            'category' => $category,
            'courses' => $courses,
            'pagination' => $this->getPagination($args),
        ]);
    }

    public function search(): void
    {
        $search_term = sanitize_text_field($_GET['s'] ?? '');
        $args = [
            'posts_per_page' => get_query_var('posts_per_page', 12),
            'paged' => get_query_var('paged', 1),
        ];

        $courses = $this->courseService->searchCourses($search_term, $args);

        $this->render('courses/search', [
            'search_term' => $search_term,
            'courses' => $courses,
            'pagination' => $this->getPagination($args),
        ]);
    }

    public function handleEnrollment(): void
    {
        check_ajax_referer('sikshya_enroll_nonce', 'nonce');

        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in to enroll in a course', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Invalid course ID', 'sikshya'));
        }

        if (function_exists('sikshya_get_course_pricing')) {
            $p = sikshya_get_course_pricing($course_id);
            $paid = null !== $p['effective'] && (float) $p['effective'] > 0.00001;
            if ($paid) {
                $bypass = function_exists('sikshya_enroll_paid_course_as_admin') ? sikshya_enroll_paid_course_as_admin($course_id) : 0;
                if ($bypass <= 0) {
                    wp_send_json_error(__('This course requires purchase.', 'sikshya'));
                }
                wp_send_json_success([
                    'message' => __('Enrolled without purchase (administrator access).', 'sikshya'),
                    'enrollment_id' => $bypass,
                    'redirect_url' => get_permalink($course_id),
                ]);
            }
        }

        try {
            $enrollment_id = $this->courseService->enrollUser($user_id, $course_id, [
                'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'free'),
                'amount' => floatval($_POST['amount'] ?? 0),
            ]);

            wp_send_json_success([
                'message' => __('Successfully enrolled in course', 'sikshya'),
                'enrollment_id' => $enrollment_id,
                'redirect_url' => get_permalink($course_id),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleSearch(): void
    {
        check_ajax_referer('sikshya_search_nonce', 'nonce');

        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        $args = [
            'posts_per_page' => intval($_POST['per_page'] ?? 12),
            'paged' => intval($_POST['page'] ?? 1),
        ];

        if (empty($search_term)) {
            wp_send_json_error(__('Search term is required', 'sikshya'));
        }

        $courses = $this->courseService->searchCourses($search_term, $args);

        wp_send_json_success([
            'courses' => $courses,
            'total' => count($courses),
        ]);
    }

    public function dashboard(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url());
            exit;
        }

        $user_id = get_current_user_id();
        $enrollments = $this->courseService->getUserEnrollments($user_id, ['limit' => 10]);

        $this->render('dashboard/index', [
            'enrollments' => $enrollments,
            'user' => wp_get_current_user(),
        ]);
    }

    public function myCourses(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url());
            exit;
        }

        $user_id = get_current_user_id();
        $enrollments = $this->courseService->getUserEnrollments($user_id);

        $this->render('dashboard/my-courses', [
            'enrollments' => $enrollments,
        ]);
    }

    private function render(string $template, array $data = []): void
    {
        $template_path = $this->plugin->getTemplatePath($template . '.php');

        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        } else {
            // Fallback to default template
            $this->renderDefault($template, $data);
        }
    }

    private function renderDefault(string $template, array $data = []): void
    {
        $template_path = $this->plugin->getTemplatePath('default/' . $template . '.php');

        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        } else {
            echo '<p>' . __('Template not found', 'sikshya') . '</p>';
        }
    }

    private function getPagination(array $args): array
    {
        global $wp_query;

        $total_posts = $wp_query->found_posts;
        $posts_per_page = $args['posts_per_page'] ?? 12;
        $current_page = $args['paged'] ?? 1;
        $total_pages = ceil($total_posts / $posts_per_page);

        return [
            'total_posts' => $total_posts,
            'posts_per_page' => $posts_per_page,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'has_previous' => $current_page > 1,
            'has_next' => $current_page < $total_pages,
            'previous_page' => $current_page - 1,
            'next_page' => $current_page + 1,
        ];
    }
}
