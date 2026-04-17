<?php

namespace Sikshya\Admin;

use Sikshya\Admin\Controllers\ReportController;
use Sikshya\Constants\AdminPages;
use Sikshya\Constants\PostTypes;
use Sikshya\Licensing\Pro;
use Sikshya\Services\PermalinkService;

/**
 * Bootstrap payload for the React admin shell (URL-based pages, full-width layout).
 */
final class ReactAdminConfig
{
    /**
     * Build config for window.sikshyaReact.
     *
     * @param string               $pageKey     Logical page (dashboard, courses, add-course, …).
     * @param array<string, mixed> $initialData Page-specific data (stats, chart, …).
     * @return array<string, mixed>
     */
    public static function build(string $pageKey, array $initialData = []): array
    {
        $user = wp_get_current_user();
        $query = [];
        foreach (['tab', 'course_id', 'id', 'view', 'post_type', 'post_id'] as $key) {
            if (isset($_GET[$key])) {
                $query[$key] = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            }
        }

        $avatar = get_avatar_url($user->ID, ['size' => 64]);

        return [
            'page' => $pageKey,
            'version' => SIKSHYA_VERSION,
            'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('/'),
            /** `admin.php?page=sikshya` (append `&view=` for subpages). */
            'appAdminBase' => add_query_arg(['page' => AdminPages::DASHBOARD], admin_url('admin.php')),
            'siteUrl' => home_url('/'),
            'pluginUrl' => SIKSHYA_PLUGIN_URL,
            // Frontend permalink bases (must mirror global Sikshya permalink settings).
            'permalinks' => PermalinkService::get(),
            'plainPermalinks' => PermalinkService::isPlainPermalinks(),
            'postTypes' => [
                'course' => PostTypes::COURSE,
                'lesson' => PostTypes::LESSON,
                'quiz' => PostTypes::QUIZ,
                'assignment' => PostTypes::ASSIGNMENT,
            ],
            'user' => [
                'name' => $user->display_name ?: $user->user_login,
                'avatarUrl' => $avatar ? (string) $avatar : '',
            ],
            'navigation' => self::navigationItems(),
            'initialData' => $initialData,
            'query' => $query,
            /** Feature catalog + gates; all admin UIs read this for upsell / locks. */
            'licensing' => Pro::getClientPayload(),
        ];
    }

    /**
     * In-app URL: one wp-admin page (`page=sikshya`) with a `view` sub-route.
     *
     * @param array<string, string|int> $extra Query args (merged; overwrites reserved keys if duplicated).
     */
    public static function reactAppUrl(string $view, array $extra = []): string
    {
        $args = [
            'page' => AdminPages::DASHBOARD,
            'view' => $view,
        ];

        foreach ($extra as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $k = sanitize_key((string) $key);
            if ($k === '') {
                continue;
            }
            $args[$k] = is_scalar($value) ? (string) $value : '';
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Nested nav for React sidebar (Course group with Lessons, Quizzes, etc.).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navigationItems(): array
    {
        $items = [];

        if (current_user_can('edit_posts')) {
            $items[] = [
                'id' => 'dashboard',
                'label' => __('Dashboard', 'sikshya'),
                'icon' => 'dashboard',
                'href' => self::reactAppUrl('dashboard'),
            ];
        }

        $course_children = [
            [
                'id' => 'courses',
                'label' => __('Courses', 'sikshya'),
                'icon' => 'table',
                'href' => self::reactAppUrl('courses'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'course-categories',
                'label' => __('Categories', 'sikshya'),
                'icon' => 'tag',
                'href' => self::reactAppUrl('course-categories'),
                'cap' => 'manage_categories',
            ],
            [
                'id' => 'lessons',
                'label' => __('Lessons', 'sikshya'),
                'icon' => 'bookOpen',
                'href' => self::reactAppUrl('lessons'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'quizzes',
                'label' => __('Quizzes', 'sikshya'),
                'icon' => 'puzzle',
                'href' => self::reactAppUrl('quizzes'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'assignments',
                'label' => __('Assignments', 'sikshya'),
                'icon' => 'clipboard',
                'href' => self::reactAppUrl('assignments'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'questions',
                'label' => __('Questions', 'sikshya'),
                'icon' => 'helpCircle',
                'href' => self::reactAppUrl('questions'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'chapters',
                'label' => __('Chapters', 'sikshya'),
                'icon' => 'layers',
                'href' => self::reactAppUrl('chapters'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'content-drip',
                'label' => __('Scheduled access', 'sikshya'),
                'icon' => 'schedule',
                'href' => self::reactAppUrl('content-drip'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'course-team',
                'label' => __('Course staff', 'sikshya'),
                'icon' => 'users',
                'href' => self::reactAppUrl('course-team'),
                'cap' => 'edit_posts',
            ],
        ];

        $children = self::filterNavChildren($course_children);
        if ($children !== []) {
            $items[] = [
                'id' => 'course',
                'label' => __('Course', 'sikshya'),
                'icon' => 'course',
                'children' => $children,
            ];
        }

        $cert_children = [
            [
                'id' => 'certificates',
                'label' => __('Templates', 'sikshya'),
                'icon' => 'badge',
                'href' => self::reactAppUrl('certificates'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'issued-certificates',
                'label' => __('Issued', 'sikshya'),
                'icon' => 'clipboard',
                'href' => self::reactAppUrl('issued-certificates'),
                'cap' => 'edit_posts',
            ],
        ];
        $cert_children = self::filterNavChildren($cert_children);
        if ($cert_children !== []) {
            $items[] = [
                'id' => 'certificates-group',
                'label' => __('Certificates', 'sikshya'),
                'icon' => 'badge',
                'children' => $cert_children,
            ];
        }

        $people_children = [
            [
                'id' => 'students',
                'label' => __('Students', 'sikshya'),
                'icon' => 'users',
                'href' => self::reactAppUrl('students'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'instructors',
                'label' => __('Instructors', 'sikshya'),
                'icon' => 'userCircle',
                'href' => self::reactAppUrl('instructors'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'enrollments',
                'label' => __('Enrollments', 'sikshya'),
                'icon' => 'clipboard',
                'href' => self::reactAppUrl('enrollments'),
                'cap' => 'sikshya_enrollments_nav',
            ],
        ];
        $people_children = self::filterPeopleNavChildren($people_children);
        if ($people_children !== []) {
            $items[] = [
                'id' => 'people-group',
                'label' => __('People', 'sikshya'),
                'icon' => 'users',
                'children' => $people_children,
            ];
        }

        $reports_children = [
            [
                'id' => 'reports',
                'label' => __('Overview', 'sikshya'),
                'icon' => 'chart',
                'href' => self::reactAppUrl('reports'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'gradebook',
                'label' => __('Gradebook', 'sikshya'),
                'icon' => 'table',
                'href' => self::reactAppUrl('gradebook'),
                'cap' => 'edit_posts',
            ],
        ];
        $reports_children = self::filterNavChildren($reports_children);
        if ($reports_children !== []) {
            $items[] = [
                'id' => 'reports-group',
                'label' => __('Reports', 'sikshya'),
                'icon' => 'chart',
                'children' => $reports_children,
            ];
        }

        $commerce_children = [
            [
                'id' => 'payments',
                'label' => __('Payments', 'sikshya'),
                'icon' => 'columns',
                'href' => self::reactAppUrl('payments'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'orders',
                'label' => __('Orders', 'sikshya'),
                'icon' => 'table',
                'href' => self::reactAppUrl('orders'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'coupons',
                'label' => __('Coupons', 'sikshya'),
                'icon' => 'tag',
                'href' => self::reactAppUrl('coupons'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'subscriptions',
                'label' => __('Subscriptions', 'sikshya'),
                'icon' => 'plusCircle',
                'href' => self::reactAppUrl('subscriptions'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'marketplace',
                'label' => __('Marketplace', 'sikshya'),
                'icon' => 'course',
                'href' => self::reactAppUrl('marketplace'),
                'cap' => 'manage_options',
            ],
        ];
        $commerce_children = self::filterNavChildren($commerce_children);
        if ($commerce_children !== []) {
            $items[] = [
                'id' => 'commerce',
                'label' => __('Commerce', 'sikshya'),
                'icon' => 'columns',
                'children' => $commerce_children,
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'settings',
                'label' => __('Settings', 'sikshya'),
                'icon' => 'cog',
                'href' => self::reactAppUrl('settings'),
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'tools',
                'label' => __('Tools', 'sikshya'),
                'icon' => 'wrench',
                'href' => self::reactAppUrl('tools'),
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function filterNavChildren(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cap = isset($row['cap']) ? (string) $row['cap'] : 'edit_posts';
            if (!current_user_can($cap)) {
                continue;
            }
            unset($row['cap']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * People nav: same capability rules as legacy flat menu (enrollments uses Sikshya caps).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function filterPeopleNavChildren(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cap = isset($row['cap']) ? (string) $row['cap'] : 'edit_posts';
            if ($cap === 'sikshya_enrollments_nav') {
                if (!current_user_can('manage_sikshya') && !current_user_can('edit_sikshya_courses')) {
                    continue;
                }
            } elseif (!current_user_can($cap)) {
                continue;
            }
            unset($row['cap']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Dashboard KPIs: post counts, roles, enrollments/revenue when DB tables exist.
     *
     * @return array<string, int|string|bool>
     */
    public static function enrichedDashboardStats(): array
    {
        $course_counts = wp_count_posts(PostTypes::COURSE);
        $published = isset($course_counts->publish) ? (int) $course_counts->publish : 0;
        $draft = isset($course_counts->draft) ? (int) $course_counts->draft : 0;
        $user_counts = count_users();
        $students = isset($user_counts['avail_roles']['sikshya_student'])
            ? (int) $user_counts['avail_roles']['sikshya_student']
            : 0;
        $instructors = isset($user_counts['avail_roles']['sikshya_instructor'])
            ? (int) $user_counts['avail_roles']['sikshya_instructor']
            : 0;

        $snap = ReportController::getReportsPageSnapshot();
        $st = $snap['stats'] ?? [];

        $cert_counts = wp_count_posts(PostTypes::CERTIFICATE);

        return [
            'publishedCourses' => $published,
            'draftCourses' => $draft,
            'lessons' => self::count_published_posts(PostTypes::LESSON),
            'quizzes' => self::count_published_posts(PostTypes::QUIZ),
            'assignments' => self::count_published_posts(PostTypes::ASSIGNMENT),
            'questions' => self::count_published_posts(PostTypes::QUESTION),
            'chapters' => self::count_published_posts(PostTypes::CHAPTER),
            'certificateTemplates' => isset($cert_counts->publish) ? (int) $cert_counts->publish : 0,
            'students' => $students,
            'instructors' => $instructors,
            'revenue' => isset($st['revenue_html']) ? (string) $st['revenue_html'] : '$0.00',
            'enrollments' => isset($st['total_enrollments']) ? (int) $st['total_enrollments'] : 0,
            'completedEnrollments' => isset($st['completed_enrollments']) ? (int) $st['completed_enrollments'] : 0,
            'distinctLearners' => isset($st['distinct_learners']) ? (int) $st['distinct_learners'] : 0,
            'hasEnrollmentTable' => !empty($st['has_enrollment_table']),
            'hasPaymentsTable' => !empty($st['has_payments_table']),
        ];
    }

    /**
     * Dashboard stat cards (mirrors legacy dashboard widget data).
     *
     * @return array<string, mixed>
     */
    public static function dashboardInitialData(): array
    {
        return [
            'siteName' => get_bloginfo('name'),
            'stats' => self::enrichedDashboardStats(),
            'recentCourses' => self::dashboard_recent_courses(6),
            'dashboardLinks' => [
                'enrollments' => current_user_can('manage_sikshya') || current_user_can('edit_sikshya_courses'),
                'payments' => current_user_can('manage_options'),
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, status: string, modified: string}>
     */
    private static function dashboard_recent_courses(int $limit): array
    {
        $query = new \WP_Query(
            [
                'post_type' => PostTypes::COURSE,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ]
        );

        $rows = [];
        while ($query->have_posts()) {
            $query->the_post();
            $rows[] = [
                'id' => (int) get_the_ID(),
                'title' => get_the_title(),
                'status' => (string) get_post_status(),
                'modified' => get_post_modified_time('c', true),
            ];
        }
        wp_reset_postdata();

        return $rows;
    }

    private static function count_published_posts(string $post_type): int
    {
        $counts = wp_count_posts($post_type);

        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    /**
     * Reports chart payload for React.
     *
     * @return array<string, mixed>
     */
    public static function reportsInitialData(): array
    {
        $snap = ReportController::getReportsPageSnapshot();

        return [
            'chart' => $snap['chart'] ?? ['labels' => [], 'counts' => []],
            'stats' => $snap['stats'] ?? [],
        ];
    }
}
