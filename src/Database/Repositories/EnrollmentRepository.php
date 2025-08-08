<?php

namespace Sikshya\Database\Repositories;

class EnrollmentRepository
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sikshya_enrollments';
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
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($this->table_name, [
            'user_id' => intval($data['user_id']),
            'course_id' => intval($data['course_id']),
            'status' => sanitize_text_field($data['status']),
            'enrolled_date' => $data['enrolled_date'],
            'payment_method' => sanitize_text_field($data['payment_method']),
            'amount' => floatval($data['amount']),
            'transaction_id' => sanitize_text_field($data['transaction_id']),
        ]);
        
        return $result ? $wpdb->insert_id : 0;
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
} 