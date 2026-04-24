<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\QuizAttemptsTable;
use Sikshya\Database\Tables\PaymentsTable;

/**
 * Admin-focused table listings (joins custom tables with WP users/posts).
 *
 * Keeps raw SQL out of REST route handlers.
 *
 * @package Sikshya\Database\Repositories
 */
final class AdminTablesRepository
{
    /**
     * @param array{
     *   per_page:int,
     *   page:int,
     *   status?:string,
     *   course_id?:int,
     *   search?:string
     * } $args
     * @return array{items: array<int, array<string, mixed>>, total:int, pages:int, page:int, per_page:int, table_missing?:bool}
     */
    public function enrollmentsPaged(array $args): array
    {
        global $wpdb;

        $table = EnrollmentsTable::getTableName();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'page' => 1,
                'per_page' => max(1, (int) ($args['per_page'] ?? 20)),
                'table_missing' => true,
            ];
        }

        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 20)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $per_page;
        $status = isset($args['status']) ? (string) $args['status'] : '';
        $course_id = isset($args['course_id']) ? (int) $args['course_id'] : 0;
        $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $course_type = PostTypes::COURSE;

        $where = ['1=1'];
        $prepare = [];

        if ($status !== '') {
            $where[] = 'e.status = %s';
            $prepare[] = sanitize_key($status);
        }
        if ($course_id > 0) {
            $where[] = 'e.course_id = %d';
            $prepare[] = $course_id;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR p.post_title LIKE %s)';
            array_push($prepare, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(DISTINCT e.id) FROM {$table} e
            LEFT JOIN {$users_table} u ON e.user_id = u.ID
            LEFT JOIN {$posts_table} p ON e.course_id = p.ID AND p.post_type = %s
            WHERE {$where_sql}";
        $count_prepare = array_merge([$course_type], $prepare);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from validated fragments above.
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_prepare));

        $list_prepare = array_merge([$course_type], $prepare, [$per_page, $offset]);
        $list_sql = "SELECT e.id, e.user_id, e.course_id, e.status, e.enrolled_date, e.payment_method, e.amount,
            u.display_name AS learner_name, u.user_email AS learner_email, p.post_title AS course_title
            FROM {$table} e
            LEFT JOIN {$users_table} u ON e.user_id = u.ID
            LEFT JOIN {$posts_table} p ON e.course_id = p.ID AND p.post_type = %s
            WHERE {$where_sql}
            ORDER BY e.enrolled_date DESC
            LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from validated fragments above.
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_prepare));

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'status' => (string) $row->status,
                'enrolled_date' => (string) $row->enrolled_date,
                'payment_method' => (string) $row->payment_method,
                'amount' => isset($row->amount) ? (float) $row->amount : 0.0,
                'learner_name' => (string) ($row->learner_name ?? ''),
                'learner_email' => (string) ($row->learner_email ?? ''),
                'course_title' => (string) ($row->course_title ?? ''),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * @param array{
     *   per_page:int,
     *   page:int,
     *   quiz_id?:int,
     *   course_id?:int,
     *   user_id?:int,
     *   status?:string,
     *   search?:string
     * } $args
     * @return array{items: array<int, array<string, mixed>>, total:int, pages:int, page:int, per_page:int, table_missing?:bool}
     */
    public function quizAttemptsPaged(array $args): array
    {
        global $wpdb;

        $table = QuizAttemptsTable::getTableName();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'page' => 1,
                'per_page' => max(1, (int) ($args['per_page'] ?? 30)),
                'table_missing' => true,
            ];
        }

        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $per_page;

        $quiz_id = isset($args['quiz_id']) ? (int) $args['quiz_id'] : 0;
        $course_id = isset($args['course_id']) ? (int) $args['course_id'] : 0;
        $user_id = isset($args['user_id']) ? (int) $args['user_id'] : 0;
        $status = isset($args['status']) ? sanitize_key((string) $args['status']) : '';
        $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;

        $where = ['1=1'];
        $prepare = [];

        if ($quiz_id > 0) {
            $where[] = 'a.quiz_id = %d';
            $prepare[] = $quiz_id;
        }
        if ($course_id > 0) {
            $where[] = 'a.course_id = %d';
            $prepare[] = $course_id;
        }
        if ($user_id > 0) {
            $where[] = 'a.user_id = %d';
            $prepare[] = $user_id;
        }
        if ($status !== '') {
            $where[] = 'a.status = %s';
            $prepare[] = $status;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR q.post_title LIKE %s OR c.post_title LIKE %s)';
            array_push($prepare, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);
        $table_sql = esc_sql($table);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped; values bound.
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_sql}` a LEFT JOIN {$users_table} u ON u.ID = a.user_id LEFT JOIN {$posts_table} q ON q.ID = a.quiz_id LEFT JOIN {$posts_table} c ON c.ID = a.course_id WHERE {$where_sql}", ...$prepare));
        $pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

        $prepare_rows = array_merge($prepare, [$per_page, $offset]);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped; values bound.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.user_id, a.quiz_id, a.course_id, a.attempt_number, a.score, a.status, a.started_at, a.completed_at,
                        u.display_name AS user_name, u.user_email AS user_email,
                        q.post_title AS quiz_title,
                        c.post_title AS course_title
                 FROM `{$table_sql}` a
                 LEFT JOIN {$users_table} u ON u.ID = a.user_id
                 LEFT JOIN {$posts_table} q ON q.ID = a.quiz_id
                 LEFT JOIN {$posts_table} c ON c.ID = a.course_id
                 WHERE {$where_sql}
                 ORDER BY a.completed_at DESC, a.id DESC
                 LIMIT %d OFFSET %d",
                ...$prepare_rows
            ),
            ARRAY_A
        );

        $global_attempts_limit = (int) \Sikshya\Services\Settings::get('quiz_attempts_limit', 1);
        if ($global_attempts_limit < 0) {
            $global_attempts_limit = 0;
        }
        $repo = new QuizAttemptRepository();

        $items = [];
        foreach ($rows as $r) {
            $qid = isset($r['quiz_id']) ? (int) $r['quiz_id'] : 0;
            $uid = isset($r['user_id']) ? (int) $r['user_id'] : 0;
            $per_quiz = $qid > 0 ? (int) get_post_meta($qid, '_sikshya_quiz_attempts_allowed', true) : 0;
            $limit = $per_quiz > 0 ? $per_quiz : $global_attempts_limit;
            if ($limit < 0) {
                $limit = 0;
            }
            $used = ($uid > 0 && $qid > 0) ? $repo->countAttemptsForUserQuiz($uid, $qid) : 0;
            $remaining = $limit > 0 ? max(0, $limit - $used) : null;

            $items[] = [
                'id' => (int) ($r['id'] ?? 0),
                'user_id' => $uid,
                'user_name' => (string) ($r['user_name'] ?? ''),
                'user_email' => (string) ($r['user_email'] ?? ''),
                'quiz_id' => $qid,
                'quiz_title' => (string) ($r['quiz_title'] ?? ''),
                'course_id' => (int) ($r['course_id'] ?? 0),
                'course_title' => (string) ($r['course_title'] ?? ''),
                'attempt_number' => (int) ($r['attempt_number'] ?? 0),
                'score' => (float) ($r['score'] ?? 0),
                'status' => (string) ($r['status'] ?? ''),
                'started_at' => (string) ($r['started_at'] ?? ''),
                'completed_at' => (string) ($r['completed_at'] ?? ''),
                'attempts_used' => (int) $used,
                'attempts_limit' => (int) $limit,
                'attempts_remaining' => $remaining,
                'is_locked' => $limit > 0 ? $used >= $limit : false,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
            'table_missing' => false,
        ];
    }

    /**
     * @param array{per_page:int,page:int} $args
     * @return array{items: array<int, array<string, mixed>>, total:int, pages:int, page:int, per_page:int, table_missing?:bool}
     */
    public function paymentsPaged(array $args): array
    {
        global $wpdb;

        $table = PaymentsTable::getTableName();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'page' => 1,
                'per_page' => max(1, (int) ($args['per_page'] ?? 30)),
                'table_missing' => true,
            ];
        }

        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $course_type = PostTypes::COURSE;

        $sql = $wpdb->prepare(
            "SELECT py.id, py.user_id, py.course_id, py.amount, py.currency, py.payment_method, py.transaction_id, py.status, py.payment_date,
                u.display_name AS payer_name, u.user_email AS payer_email, p.post_title AS course_title
            FROM {$table} py
            LEFT JOIN {$wpdb->users} u ON py.user_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON py.course_id = p.ID AND p.post_type = %s
            ORDER BY py.payment_date DESC
            LIMIT %d OFFSET %d",
            $course_type,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($sql);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'amount' => isset($row->amount) ? (float) $row->amount : 0.0,
                'currency' => (string) ($row->currency ?? ''),
                'payment_method' => (string) ($row->payment_method ?? ''),
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'status' => (string) ($row->status ?? ''),
                'payment_date' => (string) ($row->payment_date ?? ''),
                'payer_name' => (string) ($row->payer_name ?? ''),
                'payer_email' => (string) ($row->payer_email ?? ''),
                'course_title' => (string) ($row->course_title ?? ''),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
}

