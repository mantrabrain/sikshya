<?php

namespace Sikshya\Database\Repositories;

use DateTimeImmutable;
use Sikshya\Constants\PostTypes;
use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\PaymentsTable;
use Sikshya\Services\Settings;

/**
 * Admin reports / gradebook-style aggregates over custom tables.
 *
 * @package Sikshya\Database\Repositories
 */
final class ReportsRepository
{
    public function tableExists(string $full_name): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full_name)) === $full_name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewStats(): array
    {
        global $wpdb;

        $enrollment_table = EnrollmentsTable::getTableName();
        $payments_table = PaymentsTable::getTableName();

        $course_counts = wp_count_posts(PostTypes::COURSE);
        $published_courses = isset($course_counts->publish) ? (int) $course_counts->publish : 0;

        $total_enrollments = 0;
        $distinct_learners = 0;
        $completed_enrollments = 0;

        if ($this->tableExists($enrollment_table)) {
            $e = esc_sql($enrollment_table);
            $total_enrollments = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$e}`");
            $distinct_learners = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM `{$e}`");
            $completed_enrollments = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$e}` WHERE status = 'completed'"
            );
        }

        $revenue_total = 0.0;
        if ($this->tableExists($payments_table)) {
            $p = esc_sql($payments_table);
            $revenue_total = (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(amount), 0) FROM `{$p}` WHERE status = 'completed'"
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
            : (string) Settings::get('currency', 'USD');
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
            'has_enrollment_table' => $this->tableExists($enrollment_table),
            'has_payments_table' => $this->tableExists($payments_table),
        ];
    }

    /**
     * @return array{labels: string[], counts: int[]}
     */
    public function getEnrollmentChartLast12Months(): array
    {
        global $wpdb;

        $table = EnrollmentsTable::getTableName();
        $map = [];

        if ($this->tableExists($table)) {
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

            foreach ((array) $rows as $row) {
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
}
