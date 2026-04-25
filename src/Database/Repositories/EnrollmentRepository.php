<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;
use Sikshya\Database\Tables\EnrollmentsTable;

class EnrollmentRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        $this->table_name = EnrollmentsTable::getTableName();
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name;
    }

    public function findAll(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->table_name}";
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
        $result = $wpdb->get_row($sql);

        return $result ?: null;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'status' => 'enrolled',
            'enrolled_date' => current_time('mysql'),
            'payment_method' => '',
            'amount' => 0.00,
            'transaction_id' => '',
            'progress' => 0.00,
            'notes' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        $insert = [
            'user_id' => intval($data['user_id']),
            'course_id' => intval($data['course_id']),
            'status' => sanitize_text_field($data['status']),
            'enrolled_date' => $data['enrolled_date'],
            'payment_method' => sanitize_text_field($data['payment_method']),
            'amount' => floatval($data['amount']),
            'transaction_id' => sanitize_text_field($data['transaction_id']),
            'progress' => floatval($data['progress']),
        ];
        if (array_key_exists('completed_date', $data) && $data['completed_date'] !== null) {
            $insert['completed_date'] = $data['completed_date'];
        }
        if ($data['notes'] !== null && $data['notes'] !== '') {
            $insert['notes'] = sanitize_textarea_field((string) $data['notes']);
        }

        $result = $wpdb->insert($this->table_name, $insert);

        return $result ? $wpdb->insert_id : 0;
    }

    public function countForUserByStatus(int $user_id, string $status): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND status = %s",
                $user_id,
                $status
            )
        );
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update_data = [];

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['completed_date'])) {
            $update_data['completed_date'] = $data['completed_date'];
        }

        if (isset($data['payment_method'])) {
            $update_data['payment_method'] = sanitize_text_field($data['payment_method']);
        }

        if (isset($data['amount'])) {
            $update_data['amount'] = floatval($data['amount']);
        }

        if (isset($data['transaction_id'])) {
            $update_data['transaction_id'] = sanitize_text_field($data['transaction_id']);
        }

        if (isset($data['progress'])) {
            $update_data['progress'] = floatval($data['progress']);
        }

        if (array_key_exists('notes', $data)) {
            $update_data['notes'] = $data['notes'] === null ? null : sanitize_textarea_field((string) $data['notes']);
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update($this->table_name, $update_data, ['id' => $id]);

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table_name, ['id' => $id]);

        return $result !== false;
    }

    public function findByUser(int $user_id, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE user_id = %d", $user_id);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findByCourse(int $course_id, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE course_id = %d", $course_id);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findByStatus(string $status, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE status = %s", $status);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        return $wpdb->get_results($sql);
    }

    public function findByUserAndCourse(int $user_id, int $course_id): ?object
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        );

        $result = $wpdb->get_row($sql);

        return $result ?: null;
    }

    /**
     * @param int[] $course_ids
     * @return array<int, object> rows with user_id, course_id, enrolled_date
     */
    public function listRecentByCourses(array $course_ids, int $limit = 10, int $days = 30): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $course_ids = array_values(array_filter(array_map('intval', $course_ids), static fn($v) => $v > 0));
        if ($course_ids === []) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $days = max(1, min(3650, $days));

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));

        $sql = "SELECT user_id, course_id, enrolled_date
                FROM {$this->table_name}
                WHERE course_id IN ({$placeholders})
                  AND enrolled_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                ORDER BY enrolled_date DESC
                LIMIT %d";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                array_merge($course_ids, [$limit])
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * @return array<int, object>
     */
    public function listPaged(int $per_page, int $offset): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $per_page = max(1, min(200, $per_page));
        $offset = max(0, $offset);

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY enrolled_date DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public function countByUser(int $user_id): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d", $user_id);

        return (int) $wpdb->get_var($sql);
    }

    public function countByCourse(int $course_id): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE course_id = %d", $course_id);

        return (int) $wpdb->get_var($sql);
    }

    public function countByStatus(string $status): int
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status);

        return (int) $wpdb->get_var($sql);
    }

    public function getTotalRevenue(): float
    {
        global $wpdb;

        $sql = "SELECT SUM(amount) FROM {$this->table_name} WHERE status = 'completed'";

        return (float) $wpdb->get_var($sql);
    }

    public function getRevenueByDateRange(string $start_date, string $end_date): float
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->table_name} 
             WHERE status = 'completed' 
             AND enrolled_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        );

        return (float) $wpdb->get_var($sql);
    }

    /**
     * Filtered list (used by legacy {@see \Sikshya\Models\Enrollment} and admin tooling).
     *
     * @param array{user_id?: int, course_id?: int, status?: string, limit?: int, offset?: int, orderby?: string, order?: string} $args
     * @return array<int, object>
     */
    public function searchWithFilters(array $args = []): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'status' => '',
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        $where_conditions = [];
        $where_values = [];
        if ((int) $args['user_id'] > 0) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = (int) $args['user_id'];
        }
        if ((int) $args['course_id'] > 0) {
            $where_conditions[] = 'course_id = %d';
            $where_values[] = (int) $args['course_id'];
        }
        if ((string) $args['status'] !== '') {
            $where_conditions[] = 'status = %s';
            $where_values[] = (string) $args['status'];
        }

        $where_clause = $where_conditions !== [] ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $order_clause = 'ORDER BY ' . esc_sql((string) $args['orderby']) . ' ' . esc_sql((string) $args['order']);
        $query = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause}";

        if ((int) $args['limit'] > 0) {
            $query .= ' LIMIT ' . (int) $args['limit'];
            if ((int) $args['offset'] > 0) {
                $query .= ' OFFSET ' . (int) $args['offset'];
            }
        }

        if ($where_values !== []) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $rows = $wpdb->get_results($query);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{total_enrollments: int, active_enrollments: int, completed_enrollments: int, average_progress: float}
     */
    public function getStatisticsForCourse(int $course_id): array
    {
        if (!$this->tableExists() || $course_id <= 0) {
            return [
                'total_enrollments' => 0,
                'active_enrollments' => 0,
                'completed_enrollments' => 0,
                'average_progress' => 0.0,
            ];
        }
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                AVG(progress) as average_progress
            FROM {$this->table_name}
            WHERE course_id = %d",
            $course_id
        );
        $stats = $wpdb->get_row($query);

        return [
            'total_enrollments' => (int) ($stats->total_enrollments ?? 0),
            'active_enrollments' => (int) ($stats->active_enrollments ?? 0),
            'completed_enrollments' => (int) ($stats->completed_enrollments ?? 0),
            'average_progress' => (float) ($stats->average_progress ?? 0),
        ];
    }

    /**
     * @return array{total_enrollments: int, active_enrollments: int, completed_enrollments: int, average_progress: float}
     */
    public function getStatisticsForUser(int $user_id): array
    {
        if (!$this->tableExists() || $user_id <= 0) {
            return [
                'total_enrollments' => 0,
                'active_enrollments' => 0,
                'completed_enrollments' => 0,
                'average_progress' => 0.0,
            ];
        }
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                AVG(progress) as average_progress
            FROM {$this->table_name}
            WHERE user_id = %d",
            $user_id
        );
        $stats = $wpdb->get_row($query);

        return [
            'total_enrollments' => (int) ($stats->total_enrollments ?? 0),
            'active_enrollments' => (int) ($stats->active_enrollments ?? 0),
            'completed_enrollments' => (int) ($stats->completed_enrollments ?? 0),
            'average_progress' => (float) ($stats->average_progress ?? 0),
        ];
    }
}
