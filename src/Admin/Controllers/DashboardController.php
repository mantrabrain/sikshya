<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Services\AnalyticsService;
use Sikshya\Services\CacheService;

/**
 * Dashboard Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class DashboardController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Analytics service
     *
     * @var AnalyticsService
     */
    private AnalyticsService $analytics;

    /**
     * Cache service
     *
     * @var CacheService
     */
    private CacheService $cache;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->analytics = $plugin->getService('analytics');
        $this->cache = $plugin->getService('cache');
    }

    /**
     * Initialize controller
     */
    public function init(): void
    {
        // Add dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidgets']);
    }

    /**
     * Main dashboard page
     */
    public function index(): void
    {
        $stats = $this->getDashboardStats();
        $recent_activities = $this->getRecentActivities();
        $top_courses = $this->getTopCourses();
        $recent_enrollments = $this->getRecentEnrollments();

        include $this->plugin->getTemplatePath('admin/dashboard.php');
    }

    /**
     * Add dashboard widgets
     */
    public function addDashboardWidgets(): void
    {
        wp_add_dashboard_widget(
            'sikshya_overview',
            sprintf(
                /* translators: %s: brand name */
                __('%s Overview', 'sikshya'),
                function_exists('sikshya_brand_name') ? sikshya_brand_name('admin') : __('Sikshya LMS', 'sikshya')
            ),
            [$this, 'renderOverviewWidget']
        );

        wp_add_dashboard_widget(
            'sikshya_recent_activities',
            __('Recent Activities', 'sikshya'),
            [$this, 'renderRecentActivitiesWidget']
        );
    }

    /**
     * Render overview widget
     */
    public function renderOverviewWidget(): void
    {
        $stats = $this->getDashboardStats();
        include $this->plugin->getTemplatePath('admin/widgets/overview.php');
    }

    /**
     * Render recent activities widget
     */
    public function renderRecentActivitiesWidget(): void
    {
        $activities = $this->getRecentActivities(5);
        include $this->plugin->getTemplatePath('admin/widgets/recent-activities.php');
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    private function getDashboardStats(): array
    {
        $cache_key = 'dashboard_stats';
        $stats = $this->cache->get($cache_key);

        if ($stats === false) {
            global $wpdb;

            $stats = [
                'total_courses' => $this->getTotalCourses(),
                'total_students' => $this->getTotalStudents(),
                'total_instructors' => $this->getTotalInstructors(),
                'total_enrollments' => $this->getTotalEnrollments(),
                'total_revenue' => $this->getTotalRevenue(),
                'active_courses' => $this->getActiveCourses(),
                'completed_courses' => $this->getCompletedCourses(),
                'pending_approvals' => $this->getPendingApprovals(),
            ];

            $this->cache->set($cache_key, $stats, 300); // Cache for 5 minutes
        }

        return $stats;
    }

    /**
     * Get total courses
     *
     * @return int
     */
    private function getTotalCourses(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sikshya_course' AND post_status = 'publish'"
        );
    }

    /**
     * Get total students
     *
     * @return int
     */
    private function getTotalStudents(): int
    {
        return count(get_users(['role' => 'sikshya_student']));
    }

    /**
     * Get total instructors
     *
     * @return int
     */
    private function getTotalInstructors(): int
    {
        return count(get_users(['role' => 'sikshya_instructor']));
    }

    /**
     * Get total enrollments
     *
     * @return int
     */
    private function getTotalEnrollments(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments WHERE status = 'active'"
        );
    }

    /**
     * Get total revenue
     *
     * @return float
     */
    private function getTotalRevenue(): float
    {
        global $wpdb;
        return (float) $wpdb->get_var(
            "SELECT SUM(amount) FROM {$wpdb->prefix}sikshya_payments WHERE status = 'completed'"
        );
    }

    /**
     * Get active courses
     *
     * @return int
     */
    private function getActiveCourses(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT course_id) FROM {$wpdb->prefix}sikshya_enrollments WHERE status = 'active'"
        );
    }

    /**
     * Get completed courses
     *
     * @return int
     */
    private function getCompletedCourses(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments WHERE status = 'completed'"
        );
    }

    /**
     * Get pending approvals
     *
     * @return int
     */
    private function getPendingApprovals(): int
    {
        return count(get_users([
            'meta_query' => [
                [
                    'key' => '_sikshya_instructor_status',
                    'value' => 'pending',
                    'compare' => '=',
                ],
            ],
        ]));
    }

    /**
     * Get recent activities
     *
     * @param int $limit
     * @return array
     */
    private function getRecentActivities(int $limit = 10): array
    {
        global $wpdb;

        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sikshya_analytics 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));

        return array_map([$this, 'formatActivity'], $activities);
    }

    /**
     * Format activity
     *
     * @param object $activity
     * @return array
     */
    private function formatActivity(object $activity): array
    {
        $event_data = json_decode($activity->event_data, true);

        return [
            'id' => $activity->id,
            'type' => $activity->event_type,
            'data' => $event_data,
            'user_id' => $activity->user_id,
            'course_id' => $activity->course_id,
            'created_at' => $activity->created_at,
            'formatted_time' => human_time_diff(strtotime($activity->created_at), current_time('timestamp')),
        ];
    }

    /**
     * Get top courses
     *
     * @param int $limit
     * @return array
     */
    private function getTopCourses(int $limit = 5): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title, COUNT(e.id) as enrollment_count
             FROM {$wpdb->prefix}sikshya_courses c
             JOIN {$wpdb->posts} p ON c.post_id = p.ID
             LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON c.id = e.course_id
             WHERE c.status = 'published'
             GROUP BY c.id
             ORDER BY enrollment_count DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get recent enrollments
     *
     * @param int $limit
     * @return array
     */
    private function getRecentEnrollments(int $limit = 5): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name, p.post_title as course_title
             FROM {$wpdb->prefix}sikshya_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             JOIN {$wpdb->prefix}sikshya_courses c ON e.course_id = c.id
             JOIN {$wpdb->posts} p ON c.post_id = p.ID
             ORDER BY e.enrolled_date DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Handle AJAX requests
     *
     * @param string $action
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'refresh_stats':
                $this->cache->delete('dashboard_stats');
                $stats = $this->getDashboardStats();
                wp_send_json_success($stats);
                break;

            case 'get_chart_data':
                $chart_data = $this->getChartData();
                wp_send_json_success($chart_data);
                break;

            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Get chart data
     *
     * @return array
     */
    private function getChartData(): array
    {
        global $wpdb;

        // Get enrollment data for the last 30 days
        $enrollments = $wpdb->get_results(
            "SELECT DATE(enrolled_date) as date, COUNT(*) as count
             FROM {$wpdb->prefix}sikshya_enrollments
             WHERE enrolled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(enrolled_date)
             ORDER BY date"
        );

        // Get revenue data for the last 30 days
        $revenue = $wpdb->get_results(
            "SELECT DATE(created_at) as date, SUM(amount) as total
             FROM {$wpdb->prefix}sikshya_payments
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND status = 'completed'
             GROUP BY DATE(created_at)
             ORDER BY date"
        );

        return [
            'enrollments' => $enrollments,
            'revenue' => $revenue,
        ];
    }
}
