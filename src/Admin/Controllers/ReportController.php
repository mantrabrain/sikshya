<?php

namespace Sikshya\Admin\Controllers;

use DateTimeImmutable;
use Sikshya\Admin\Views\BaseView;
use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Services\AnalyticsService;
use Sikshya\Services\CacheService;

/**
 * Report Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class ReportController extends BaseView
{
    /**
     * Request-level cache for reports snapshot (template + localize + controller).
     *
     * @var array|null
     */
    private static $reports_snapshot_cache = null;

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    protected Plugin $plugin;

    /**
     * Analytics service
     *
     * @var AnalyticsService|null
     */
    private ?AnalyticsService $analytics;

    /**
     * Cache service
     *
     * @var CacheService|null
     */
    private ?CacheService $cache;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->analytics = $plugin->getService('analytics') ?? null;
        $this->cache = $plugin->getService('cache') ?? null;
    }

    /**
     * Overview numbers + last-12-month enrollment series for the reports screen and Chart.js.
     *
     * @return array{stats: array<string, mixed>, chart: array{labels: string[], counts: int[]}}
     */
    public static function getReportsPageSnapshot(): array
    {
        if (null !== self::$reports_snapshot_cache) {
            return self::$reports_snapshot_cache;
        }

        self::$reports_snapshot_cache = [
            'stats' => self::queryReportsOverviewStats(),
            'chart' => self::queryEnrollmentChartLast12Months(),
        ];

        return self::$reports_snapshot_cache;
    }

    /**
     * Drop cached snapshot so the next read queries the database again (e.g. REST refresh).
     */
    public static function clearReportsSnapshotCache(): void
    {
        self::$reports_snapshot_cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function queryReportsOverviewStats(): array
    {
        global $wpdb;

        $enrollment_table = $wpdb->prefix . 'sikshya_enrollments';
        $payments_table = $wpdb->prefix . 'sikshya_payments';

        $course_counts = wp_count_posts(PostTypes::COURSE);
        $published_courses = isset($course_counts->publish) ? (int) $course_counts->publish : 0;

        $total_enrollments = 0;
        $distinct_learners = 0;
        $completed_enrollments = 0;

        if (self::reportsTableExists($enrollment_table)) {
            $total_enrollments = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$enrollment_table}");
            $distinct_learners = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$enrollment_table}");
            $completed_enrollments = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$enrollment_table} WHERE status = 'completed'"
            );
        }

        $revenue_total = 0.0;
        if (self::reportsTableExists($payments_table)) {
            $revenue_total = (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE status = 'completed'"
            );
        }

        $completion_rate = $total_enrollments > 0
            ? round(100 * ($completed_enrollments / $total_enrollments), 1)
            : 0.0;

        $student_users = count_users();
        $student_role_count = isset($student_users['avail_roles']['sikshya_student'])
            ? (int) $student_users['avail_roles']['sikshya_student']
            : 0;

        $currency = (string) get_option('sikshya_currency', 'USD');
        $revenue_html = function_exists('sikshya_format_price')
            ? sikshya_format_price($revenue_total, $currency)
            : esc_html(number_format_i18n($revenue_total, 2));

        return [
            'published_courses' => $published_courses,
            'total_enrollments' => $total_enrollments,
            'distinct_learners' => $distinct_learners > 0 ? $distinct_learners : $student_role_count,
            'completed_enrollments' => $completed_enrollments,
            'completion_rate' => $completion_rate,
            'revenue_total' => $revenue_total,
            'revenue_html' => $revenue_html,
            'has_enrollment_table' => self::reportsTableExists($enrollment_table),
            'has_payments_table' => self::reportsTableExists($payments_table),
        ];
    }

    /**
     * @return array{labels: string[], counts: int[]}
     */
    private static function queryEnrollmentChartLast12Months(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sikshya_enrollments';
        $map = [];

        if (self::reportsTableExists($table)) {
            $start = (new DateTimeImmutable('first day of this month', wp_timezone()))->modify('-11 months');
            $start_str = $start->format('Y-m-d 00:00:00');

            $table_sql = esc_sql($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped; values bound.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE_FORMAT(enrolled_date, '%%Y-%%m') AS ym, COUNT(*) AS c FROM `{$table_sql}` WHERE enrolled_date >= %s GROUP BY ym ORDER BY ym ASC",
                    $start_str
                )
            );

            foreach ($rows as $row) {
                $map[$row->ym] = (int) $row->c;
            }
        }

        $labels = [];
        $counts = [];
        $cursor = (new DateTimeImmutable('first day of this month', wp_timezone()))->modify('-11 months');

        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = wp_date('M', $cursor->getTimestamp());
            $counts[] = $map[$key] ?? 0;
            $cursor = $cursor->modify('+1 month');
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
        ];
    }

    private static function reportsTableExists(string $table_name): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
    }

    /**
     * Initialize controller
     */
    public function init(): void
    {
        add_action('wp_ajax_sikshya_report_action', [$this, 'handleAjax']);
    }

    /**
     * Main reports page
     */
    public function index(): void
    {
        $snapshot = self::getReportsPageSnapshot();
        $this->data = [
            'page_title' => __('Reports', 'sikshya'),
            'page_description' => __('Analytics and insights for your LMS', 'sikshya'),
            'report_snapshot' => $snapshot,
        ];

        $this->render('reports');
    }

    /**
     * Render reports page
     */
    public function renderReportsPage(): void
    {
        \Sikshya\Admin\ReactAdminView::render('reports', \Sikshya\Admin\ReactAdminConfig::reportsInitialData());
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-reports');
        wp_enqueue_script('sikshya-reports');
        wp_enqueue_script('sikshya-charts');
    }

    /**
     * Overview report
     */
    private function overviewReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $stats = $this->getOverviewStats($period);
        $charts = $this->getOverviewCharts($period);

        include $this->plugin->getTemplatePath('admin/reports/overview.php');
    }

    /**
     * Enrollment report
     */
    private function enrollmentReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $course_id = intval($_GET['course_id'] ?? 0);

        $enrollments = $this->getEnrollmentData($period, $course_id);
        $courses = $this->getCourses();

        include $this->plugin->getTemplatePath('admin/reports/enrollments.php');
    }

    /**
     * Revenue report
     */
    private function revenueReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $course_id = intval($_GET['course_id'] ?? 0);
        $instructor_id = intval($_GET['instructor_id'] ?? 0);

        $revenue = $this->getRevenueData($period, $course_id, $instructor_id);
        $courses = $this->getCourses();
        $instructors = $this->getInstructors();

        include $this->plugin->getTemplatePath('admin/reports/revenue.php');
    }

    /**
     * Course report
     */
    private function courseReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $category_id = intval($_GET['category_id'] ?? 0);

        $courses = $this->getCourseData($period, $category_id);
        $categories = $this->getCategories();

        include $this->plugin->getTemplatePath('admin/reports/courses.php');
    }

    /**
     * Student report
     */
    private function studentReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $status = sanitize_text_field($_GET['status'] ?? '');

        $students = $this->getStudentData($period, $status);

        include $this->plugin->getTemplatePath('admin/reports/students.php');
    }

    /**
     * Instructor report
     */
    private function instructorReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $status = sanitize_text_field($_GET['status'] ?? '');

        $instructors = $this->getInstructorData($period, $status);

        include $this->plugin->getTemplatePath('admin/reports/instructors.php');
    }

    /**
     * Quiz report
     */
    private function quizReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $course_id = intval($_GET['course_id'] ?? 0);

        $quizzes = $this->getQuizData($period, $course_id);
        $courses = $this->getCourses();

        include $this->plugin->getTemplatePath('admin/reports/quizzes.php');
    }

    /**
     * Certificate report
     */
    private function certificateReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $course_id = intval($_GET['course_id'] ?? 0);

        $certificates = $this->getCertificateData($period, $course_id);
        $courses = $this->getCourses();

        include $this->plugin->getTemplatePath('admin/reports/certificates.php');
    }

    /**
     * Analytics report
     */
    private function analyticsReport(): void
    {
        $period = sanitize_text_field($_GET['period'] ?? '30');
        $analytics = $this->getAnalyticsData($period);

        include $this->plugin->getTemplatePath('admin/reports/analytics.php');
    }

    /**
     * Get overview statistics
     *
     * @param string $period
     * @return array
     */
    private function getOverviewStats(string $period): array
    {
        $cache_key = "overview_stats_{$period}";
        $stats = $this->cache->get($cache_key);

        if ($stats === false) {
            global $wpdb;

            $date_filter = $this->getDateFilter($period);

            $stats = [
                'total_revenue' => $this->getTotalRevenue($date_filter),
                'total_enrollments' => $this->getTotalEnrollments($date_filter),
                'total_courses' => $this->getTotalCourses($date_filter),
                'total_students' => $this->getTotalStudents($date_filter),
                'total_instructors' => $this->getTotalInstructors($date_filter),
                'avg_course_rating' => $this->getAverageCourseRating($date_filter),
                'completion_rate' => $this->getCompletionRate($date_filter),
                'active_users' => $this->getActiveUsers($date_filter),
            ];

            $this->cache->set($cache_key, $stats, 300); // Cache for 5 minutes
        }

        return $stats;
    }

    /**
     * Get overview charts
     *
     * @param string $period
     * @return array
     */
    private function getOverviewCharts(string $period): array
    {
        $cache_key = "overview_charts_{$period}";
        $charts = $this->cache->get($cache_key);

        if ($charts === false) {
            global $wpdb;

            $date_filter = $this->getDateFilter($period);

            $charts = [
                'revenue_trend' => $this->getRevenueTrend($date_filter),
                'enrollment_trend' => $this->getEnrollmentTrend($date_filter),
                'course_performance' => $this->getCoursePerformance($date_filter),
                'user_activity' => $this->getUserActivity($date_filter),
            ];

            $this->cache->set($cache_key, $charts, 300); // Cache for 5 minutes
        }

        return $charts;
    }

    /**
     * Get enrollment data
     *
     * @param string $period
     * @param int $course_id
     * @return array
     */
    private function getEnrollmentData(string $period, int $course_id = 0): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $course_filter = $course_id > 0 ? $wpdb->prepare("AND e.course_id = %d", $course_id) : '';

        $sql = "SELECT 
                    e.*,
                    u.display_name as student_name,
                    u.user_email as student_email,
                    c.post_title as course_title,
                    i.display_name as instructor_name
                FROM {$wpdb->prefix}sikshya_enrollments e
                JOIN {$wpdb->users} u ON e.user_id = u.ID
                JOIN {$wpdb->prefix}sikshya_courses sc ON e.course_id = sc.id
                JOIN {$wpdb->posts} c ON sc.post_id = c.ID
                LEFT JOIN {$wpdb->users} i ON sc.instructor_id = i.ID
                WHERE e.enrolled_date >= %s {$course_filter}
                ORDER BY e.enrolled_date DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get revenue data
     *
     * @param string $period
     * @param int $course_id
     * @param int $instructor_id
     * @return array
     */
    private function getRevenueData(string $period, int $course_id = 0, int $instructor_id = 0): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $course_filter = $course_id > 0 ? $wpdb->prepare("AND p.course_id = %d", $course_id) : '';
        $instructor_filter = $instructor_id > 0 ? $wpdb->prepare("AND p.instructor_id = %d", $instructor_id) : '';

        $sql = "SELECT 
                    p.*,
                    u.display_name as student_name,
                    c.post_title as course_title,
                    i.display_name as instructor_name
                FROM {$wpdb->prefix}sikshya_payments p
                JOIN {$wpdb->users} u ON p.user_id = u.ID
                JOIN {$wpdb->prefix}sikshya_courses sc ON p.course_id = sc.id
                JOIN {$wpdb->posts} c ON sc.post_id = c.ID
                LEFT JOIN {$wpdb->users} i ON p.instructor_id = i.ID
                WHERE p.created_at >= %s AND p.status = 'completed' {$course_filter} {$instructor_filter}
                ORDER BY p.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get course data
     *
     * @param string $period
     * @param int $category_id
     * @return array
     */
    private function getCourseData(string $period, int $category_id = 0): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $category_filter = $category_id > 0 ? $wpdb->prepare("AND tt.term_id = %d", $category_id) : '';

        $sql = "SELECT 
                    c.*,
                    p.post_title,
                    p.post_status,
                    u.display_name as instructor_name,
                    COUNT(e.id) as enrollment_count,
                    AVG(e.progress_percentage) as avg_progress,
                    AVG(r.rating) as avg_rating
                FROM {$wpdb->prefix}sikshya_courses c
                JOIN {$wpdb->posts} p ON c.post_id = p.ID
                LEFT JOIN {$wpdb->users} u ON c.instructor_id = u.ID
                LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON c.id = e.course_id
                LEFT JOIN {$wpdb->prefix}sikshya_reviews r ON c.id = r.course_id
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE c.created_at >= %s {$category_filter}
                GROUP BY c.id
                ORDER BY enrollment_count DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get student data
     *
     * @param string $period
     * @param string $status
     * @return array
     */
    private function getStudentData(string $period, string $status = ''): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $status_filter = !empty($status) ? $wpdb->prepare("AND um.meta_value = %s", $status) : '';

        $sql = "SELECT 
                    u.*,
                    COUNT(e.id) as enrollment_count,
                    AVG(e.progress_percentage) as avg_progress,
                    COUNT(c.id) as certificate_count
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON u.ID = e.user_id
                LEFT JOIN {$wpdb->prefix}sikshya_certificates c ON u.ID = c.user_id
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'sikshya_student_status'
                WHERE u.user_registered >= %s AND u.roles LIKE '%sikshya_student%' {$status_filter}
                GROUP BY u.ID
                ORDER BY enrollment_count DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get instructor data
     *
     * @param string $period
     * @param string $status
     * @return array
     */
    private function getInstructorData(string $period, string $status = ''): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $status_filter = !empty($status) ? $wpdb->prepare("AND um.meta_value = %s", $status) : '';

        $sql = "SELECT 
                    u.*,
                    COUNT(c.id) as course_count,
                    COUNT(e.id) as enrollment_count,
                    SUM(p.amount) as total_earnings
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->prefix}sikshya_courses sc ON u.ID = sc.instructor_id
                LEFT JOIN {$wpdb->posts} c ON sc.post_id = c.ID
                LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON sc.id = e.course_id
                LEFT JOIN {$wpdb->prefix}sikshya_payments p ON u.ID = p.instructor_id AND p.status = 'completed'
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'sikshya_instructor_approved'
                WHERE u.user_registered >= %s AND u.roles LIKE '%sikshya_instructor%' {$status_filter}
                GROUP BY u.ID
                ORDER BY total_earnings DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get quiz data
     *
     * @param string $period
     * @param int $course_id
     * @return array
     */
    private function getQuizData(string $period, int $course_id = 0): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $course_filter = $course_id > 0 ? $wpdb->prepare("AND q.course_id = %d", $course_id) : '';

        $sql = "SELECT 
                    q.*,
                    p.post_title as quiz_title,
                    c.post_title as course_title,
                    COUNT(r.id) as attempt_count,
                    AVG(r.score) as avg_score,
                    COUNT(CASE WHEN r.passed = 1 THEN 1 END) as passed_count
                FROM {$wpdb->prefix}sikshya_quizzes q
                JOIN {$wpdb->posts} p ON q.post_id = p.ID
                LEFT JOIN {$wpdb->prefix}sikshya_courses sc ON q.course_id = sc.id
                LEFT JOIN {$wpdb->posts} c ON sc.post_id = c.ID
                LEFT JOIN {$wpdb->prefix}sikshya_quiz_results r ON q.id = r.quiz_id
                WHERE q.created_at >= %s {$course_filter}
                GROUP BY q.id
                ORDER BY attempt_count DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get certificate data
     *
     * @param string $period
     * @param int $course_id
     * @return array
     */
    private function getCertificateData(string $period, int $course_id = 0): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);
        $course_filter = $course_id > 0 ? $wpdb->prepare("AND c.course_id = %d", $course_id) : '';

        $sql = "SELECT 
                    c.*,
                    u.display_name as student_name,
                    co.post_title as course_title,
                    i.display_name as instructor_name
                FROM {$wpdb->prefix}sikshya_certificates c
                JOIN {$wpdb->users} u ON c.user_id = u.ID
                JOIN {$wpdb->prefix}sikshya_courses sc ON c.course_id = sc.id
                JOIN {$wpdb->posts} co ON sc.post_id = co.ID
                LEFT JOIN {$wpdb->users} i ON sc.instructor_id = i.ID
                WHERE c.created_at >= %s {$course_filter}
                ORDER BY c.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $date_filter));
    }

    /**
     * Get analytics data
     *
     * @param string $period
     * @return array
     */
    private function getAnalyticsData(string $period): array
    {
        global $wpdb;

        $date_filter = $this->getDateFilter($period);

        $analytics = [];

        // Page views
        $analytics['page_views'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                COUNT(*) as count,
                DATE(created_at) as date
             FROM {$wpdb->prefix}sikshya_analytics
             WHERE created_at >= %s AND event_type = 'page_view'
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));

        // User engagement
        $analytics['engagement'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
             FROM {$wpdb->prefix}sikshya_analytics
             WHERE created_at >= %s
             GROUP BY event_type
             ORDER BY count DESC",
            $date_filter
        ));

        // Device analytics
        $analytics['devices'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.device') as device,
                COUNT(*) as count
             FROM {$wpdb->prefix}sikshya_analytics
             WHERE created_at >= %s
             GROUP BY device
             ORDER BY count DESC",
            $date_filter
        ));

        return $analytics;
    }

    /**
     * Get date filter
     *
     * @param string $period
     * @return string
     */
    private function getDateFilter(string $period): string
    {
        switch ($period) {
            case '7':
                return date('Y-m-d', strtotime('-7 days'));
            case '30':
                return date('Y-m-d', strtotime('-30 days'));
            case '90':
                return date('Y-m-d', strtotime('-90 days'));
            case '365':
                return date('Y-m-d', strtotime('-365 days'));
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }

    /**
     * Get total revenue
     *
     * @param string $date_filter
     * @return float
     */
    private function getTotalRevenue(string $date_filter): float
    {
        global $wpdb;

        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}sikshya_payments 
             WHERE created_at >= %s AND status = 'completed'",
            $date_filter
        ));
    }

    /**
     * Get total enrollments
     *
     * @param string $date_filter
     * @return int
     */
    private function getTotalEnrollments(string $date_filter): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments 
             WHERE enrolled_date >= %s",
            $date_filter
        ));
    }

    /**
     * Get total courses
     *
     * @param string $date_filter
     * @return int
     */
    private function getTotalCourses(string $date_filter): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_courses 
             WHERE created_at >= %s",
            $date_filter
        ));
    }

    /**
     * Get total students
     *
     * @param string $date_filter
     * @return int
     */
    private function getTotalStudents(string $date_filter): int
    {
        return count(get_users([
            'role' => 'sikshya_student',
            'date_query' => [
                [
                    'after' => $date_filter,
                ]
            ]
        ]));
    }

    /**
     * Get total instructors
     *
     * @param string $date_filter
     * @return int
     */
    private function getTotalInstructors(string $date_filter): int
    {
        return count(get_users([
            'role' => 'sikshya_instructor',
            'date_query' => [
                [
                    'after' => $date_filter,
                ]
            ]
        ]));
    }

    /**
     * Get average course rating
     *
     * @param string $date_filter
     * @return float
     */
    private function getAverageCourseRating(string $date_filter): float
    {
        global $wpdb;

        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}sikshya_reviews r
             JOIN {$wpdb->prefix}sikshya_courses c ON r.course_id = c.id
             WHERE c.created_at >= %s AND r.status = 'approved'",
            $date_filter
        ));
    }

    /**
     * Get completion rate
     *
     * @param string $date_filter
     * @return float
     */
    private function getCompletionRate(string $date_filter): float
    {
        global $wpdb;

        $total_enrollments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments 
             WHERE enrolled_date >= %s",
            $date_filter
        ));

        $completed_enrollments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments 
             WHERE enrolled_date >= %s AND status = 'completed'",
            $date_filter
        ));

        if ($total_enrollments > 0) {
            return round(($completed_enrollments / $total_enrollments) * 100, 2);
        }

        return 0;
    }

    /**
     * Get active users
     *
     * @param string $date_filter
     * @return int
     */
    private function getActiveUsers(string $date_filter): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}sikshya_analytics 
             WHERE created_at >= %s",
            $date_filter
        ));
    }

    /**
     * Get revenue trend
     *
     * @param string $date_filter
     * @return array
     */
    private function getRevenueTrend(string $date_filter): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                SUM(amount) as total
             FROM {$wpdb->prefix}sikshya_payments
             WHERE created_at >= %s AND status = 'completed'
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
    }

    /**
     * Get enrollment trend
     *
     * @param string $date_filter
     * @return array
     */
    private function getEnrollmentTrend(string $date_filter): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(enrolled_date) as date,
                COUNT(*) as count
             FROM {$wpdb->prefix}sikshya_enrollments
             WHERE enrolled_date >= %s
             GROUP BY DATE(enrolled_date)
             ORDER BY date",
            $date_filter
        ));
    }

    /**
     * Get course performance
     *
     * @param string $date_filter
     * @return array
     */
    private function getCoursePerformance(string $date_filter): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.post_title,
                COUNT(e.id) as enrollments,
                AVG(e.progress_percentage) as avg_progress,
                AVG(r.rating) as avg_rating
             FROM {$wpdb->prefix}sikshya_courses sc
             JOIN {$wpdb->posts} c ON sc.post_id = c.ID
             LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON sc.id = e.course_id
             LEFT JOIN {$wpdb->prefix}sikshya_reviews r ON sc.id = r.course_id
             WHERE sc.created_at >= %s
             GROUP BY sc.id
             ORDER BY enrollments DESC
             LIMIT 10",
            $date_filter
        ));
    }

    /**
     * Get user activity
     *
     * @param string $date_filter
     * @return array
     */
    private function getUserActivity(string $date_filter): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                COUNT(*) as count
             FROM {$wpdb->prefix}sikshya_analytics
             WHERE created_at >= %s
             GROUP BY event_type
             ORDER BY count DESC",
            $date_filter
        ));
    }

    /**
     * Get courses
     *
     * @return array
     */
    private function getCourses(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT c.id, p.post_title
             FROM {$wpdb->prefix}sikshya_courses c
             JOIN {$wpdb->posts} p ON c.post_id = p.ID
             WHERE c.status = 'published'
             ORDER BY p.post_title ASC"
        );
    }

    /**
     * Get instructors
     *
     * @return array
     */
    private function getInstructors(): array
    {
        return get_users(['role' => 'sikshya_instructor']);
    }

    /**
     * Get categories
     *
     * @return array
     */
    private function getCategories(): array
    {
        return get_terms(['taxonomy' => 'sikshya_course_category', 'hide_empty' => false]);
    }

    /**
     * Handle AJAX requests
     *
     * @param string $action
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'export_report':
                $this->exportReport();
                break;
            case 'get_chart_data':
                $this->getChartData();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Export report
     */
    private function exportReport(): void
    {
        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        if (empty($report_type)) {
            wp_send_json_error(__('Report type is required.', 'sikshya'));
        }

        $data = [];
        switch ($report_type) {
            case 'enrollments':
                $data = $this->getEnrollmentData($period);
                break;
            case 'revenue':
                $data = $this->getRevenueData($period);
                break;
            case 'courses':
                $data = $this->getCourseData($period);
                break;
            case 'students':
                $data = $this->getStudentData($period);
                break;
            case 'instructors':
                $data = $this->getInstructorData($period);
                break;
            default:
                wp_send_json_error(__('Invalid report type.', 'sikshya'));
        }

        if ($format === 'csv') {
            $this->exportToCsv($data, $report_type);
        } else {
            $this->exportToExcel($data, $report_type);
        }
    }

    /**
     * Export to CSV
     *
     * @param array $data
     * @param string $report_type
     */
    private function exportToCsv(array $data, string $report_type): void
    {
        $filename = "sikshya_{$report_type}_report_" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys((array) $data[0]));

            // Write data
            foreach ($data as $row) {
                fputcsv($output, (array) $row);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export to Excel
     *
     * @param array $data
     * @param string $report_type
     */
    private function exportToExcel(array $data, string $report_type): void
    {
        // This would require a library like PhpSpreadsheet
        // For now, we'll just return an error
        wp_send_json_error(__('Excel export not implemented yet.', 'sikshya'));
    }

    /**
     * Get chart data
     */
    private function getChartData(): void
    {
        $chart_type = sanitize_text_field($_POST['chart_type'] ?? '');
        $period = sanitize_text_field($_POST['period'] ?? '30');

        $data = [];
        switch ($chart_type) {
            case 'revenue_trend':
                $data = $this->getRevenueTrend($this->getDateFilter($period));
                break;
            case 'enrollment_trend':
                $data = $this->getEnrollmentTrend($this->getDateFilter($period));
                break;
            case 'course_performance':
                $data = $this->getCoursePerformance($this->getDateFilter($period));
                break;
            case 'user_activity':
                $data = $this->getUserActivity($this->getDateFilter($period));
                break;
            default:
                wp_send_json_error(__('Invalid chart type.', 'sikshya'));
        }

        wp_send_json_success($data);
    }
}
