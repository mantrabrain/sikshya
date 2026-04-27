<?php

namespace Sikshya\Database\Repositories;

/**
 * User-meta queries for instructor applications (pending / approved / rejected).
 *
 * @package Sikshya\Database\Repositories
 */
final class InstructorApplicationsRepository
{
    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listPaged(int $page, int $per_page, string $status, string $search): array
    {
        global $wpdb;

        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;

        $status = sanitize_key($status);
        $allowed = ['', 'pending', 'active', 'inactive', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $status = '';
        }

        $search_like = '';
        $search_args = [];
        $s = trim($search);
        if ($s !== '') {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $search_like = ' AND (u.user_email LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s)';
            $search_args[] = $like;
            $search_args[] = $like;
            $search_args[] = $like;
        }

        $status_sql = '';
        $status_args = [];
        if ($status !== '') {
            $status_sql = ' AND st.meta_value = %s';
            $status_args[] = $status;
        }

        $from = "FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} st ON st.user_id = u.ID AND st.meta_key = %s
            LEFT JOIN {$wpdb->usermeta} ap ON ap.user_id = u.ID AND ap.meta_key = %s
            LEFT JOIN {$wpdb->usermeta} app ON app.user_id = u.ID AND app.meta_key = %s
            WHERE 1=1{$status_sql}{$search_like}";

        $base_args = array_merge(
            ['_sikshya_instructor_status', '_sikshya_instructor_applied_at', '_sikshya_instructor_application'],
            $status_args,
            $search_args
        );

        $count_sql = "SELECT COUNT(DISTINCT u.ID) {$from}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $base_args));

        $select_sql = "SELECT u.ID AS user_id, u.user_email, u.display_name, u.user_registered,
                st.meta_value AS instructor_status,
                ap.meta_value AS applied_at,
                app.meta_value AS application_json
            {$from}
            ORDER BY COALESCE(ap.meta_value, u.user_registered) DESC
            LIMIT %d OFFSET %d";

        $list_args = array_merge($base_args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($select_sql, $list_args), ARRAY_A);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }
}
