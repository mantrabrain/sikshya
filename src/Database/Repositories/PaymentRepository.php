<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\PaymentsTable;

/**
 * Legacy payment rows (sikshya_payments).
 *
 * @package Sikshya\Database\Repositories
 */
class PaymentRepository
{
    private string $table_name;

    public function __construct()
    {
        $this->table_name = PaymentsTable::getTableName();
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name;
    }

    /**
     * @return array<int, object>
     */
    public function findFiltered(?int $user_id, ?int $course_id): array
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return [];
        }

        $where = [];
        $prepare_values = [];

        if ($user_id) {
            $where[] = 'user_id = %d';
            $prepare_values[] = $user_id;
        }

        if ($course_id) {
            $where[] = 'course_id = %d';
            $prepare_values[] = $course_id;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY payment_date DESC";

        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, ...$prepare_values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return 0;
        }

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'amount' => 0.00,
            'currency' => 'USD',
            'payment_method' => '',
            'transaction_id' => '',
            'status' => 'completed',
            'payment_date' => current_time('mysql'),
            'gateway_response' => null,
        ];
        $data = wp_parse_args($data, $defaults);

        $charge_kind = sanitize_key((string) ($data['charge_kind'] ?? 'checkout'));
        if ($charge_kind !== 'renewal') {
            $charge_kind = 'checkout';
        }

        $wpdb->insert(
            $this->table_name,
            [
                'user_id' => (int) $data['user_id'],
                'course_id' => (int) $data['course_id'],
                'amount' => (float) $data['amount'],
                'currency' => sanitize_text_field((string) $data['currency']),
                'payment_method' => sanitize_text_field((string) $data['payment_method']),
                'transaction_id' => sanitize_text_field((string) $data['transaction_id']),
                'status' => sanitize_text_field((string) $data['status']),
                'charge_kind' => $charge_kind,
                'payment_date' => $data['payment_date'],
                'gateway_response' => $data['gateway_response'] !== null ? wp_json_encode($data['gateway_response']) : null,
            ],
            ['%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Idempotency: return payment row id if this gateway transaction id was already recorded.
     */
    public function findIdByTransactionId(string $transaction_id): ?int
    {
        global $wpdb;

        $transaction_id = sanitize_text_field($transaction_id);
        if ($transaction_id === '' || !$this->tableExists()) {
            return null;
        }

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE transaction_id = %s LIMIT 1",
                $transaction_id
            )
        );

        if ($id === null || $id === '') {
            return null;
        }

        $n = (int) $id;
        return $n > 0 ? $n : null;
    }

    public function deleteById(int $id): bool
    {
        if ($id <= 0 || !$this->tableExists()) {
            return false;
        }

        global $wpdb;
        $ok = $wpdb->delete($this->table_name, ['id' => $id], ['%d']);

        return $ok !== false && (int) $ok > 0;
    }
}
