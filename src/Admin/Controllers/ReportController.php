<?php

namespace Sikshya\Admin\Controllers;

use DateTimeImmutable;
use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Admin\ReactAdminView;
use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;

/**
 * Reports data for the React admin (snapshot API). Legacy PHP report views and admin-ajax removed.
 *
 * @package Sikshya\Admin\Controllers
 */
class ReportController
{
    /**
     * @var array|null
     */
    private static $reports_snapshot_cache = null;

    protected Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
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

        $currency = function_exists('sikshya_get_store_currency_code')
            ? sikshya_get_store_currency_code()
            : (string) get_option('_sikshya_currency', 'USD');
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

    public function renderReportsPage(): void
    {
        ReactAdminView::render('reports', ReactAdminConfig::reportsInitialData());
    }
}
