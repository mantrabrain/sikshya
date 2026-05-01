<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Services\CourseFrontendSettings;
use Sikshya\Services\CourseService;
use WP_Query;

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
        $per_page = CourseFrontendSettings::coursesPerPageDefault();
        $paged = max(1, (int) get_query_var('paged', 1));
        $list_query = $this->courseService->queryCourses(
            [
                'posts_per_page' => $per_page,
                'paged' => $paged,
            ]
        );

        $courses = $list_query->posts;
        $featured_courses = $this->courseService->getFeaturedCourses(6);
        $popular_courses = $this->courseService->getPopularCourses(6);

        $this->render('courses/index', [
            'courses' => $courses,
            'featured_courses' => $featured_courses,
            'popular_courses' => $popular_courses,
            'pagination' => $this->getPaginationFromQuery($list_query, $per_page, $paged),
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
        $per_page = CourseFrontendSettings::coursesPerPageDefault();
        $paged = max(1, (int) get_query_var('paged', 1));
        $list_query = $this->courseService->queryCourses(
            [
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'tax_query' => [
                    [
                        'taxonomy' => 'sikshya_course_category',
                        'field' => 'term_id',
                        'terms' => $category->term_id,
                    ],
                ],
            ]
        );

        $courses = $list_query->posts;

        $this->render('courses/category', [
            'category' => $category,
            'courses' => $courses,
            'pagination' => $this->getPaginationFromQuery($list_query, $per_page, $paged),
        ]);
    }

    public function search(): void
    {
        $search_term = sanitize_text_field($_GET['s'] ?? '');
        $per_page = CourseFrontendSettings::coursesPerPageDefault();
        $paged = max(1, (int) get_query_var('paged', 1));

        if ($search_term === '') {
            $this->render('courses/search', [
                'search_term' => '',
                'courses' => [],
                'pagination' => $this->paginationSnapshot(0, $per_page, $paged),
            ]);

            return;
        }

        $list_query = $this->courseService->querySearchCourses(
            $search_term,
            [
                'posts_per_page' => $per_page,
                'paged' => $paged,
            ]
        );

        $courses = $list_query->posts;

        $this->render('courses/search', [
            'search_term' => $search_term,
            'courses' => $courses,
            'pagination' => $this->getPaginationFromQuery($list_query, $per_page, $paged),
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
            $req = (string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/'));
            wp_safe_redirect(\Sikshya\Frontend\Site\PublicPageUrls::login($req));
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
            $req = (string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/'));
            wp_safe_redirect(\Sikshya\Frontend\Site\PublicPageUrls::login($req));
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

    /**
     * @return array<string, int|bool>
     */
    private function getPaginationFromQuery(WP_Query $query, int $posts_per_page, int $current_page): array
    {
        return $this->paginationSnapshot((int) $query->found_posts, $posts_per_page, $current_page);
    }

    /**
     * @return array<string, int|bool>
     */
    private function paginationSnapshot(int $total_posts, int $posts_per_page, int $current_page): array
    {
        $posts_per_page = max(1, $posts_per_page);
        $total_pages = $total_posts > 0 ? (int) ceil($total_posts / $posts_per_page) : 0;

        return [
            'total_posts' => $total_posts,
            'posts_per_page' => $posts_per_page,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'has_previous' => $current_page > 1,
            'has_next' => $total_pages > 0 && $current_page < $total_pages,
            'previous_page' => $current_page - 1,
            'next_page' => $current_page + 1,
        ];
    }
}
