<?php

namespace Sikshya\Database\Repositories;

/**
 * Checkout orders + line items.
 *
 * @package Sikshya\Database\Repositories
 */
class OrderRepository
{
    private string $orders;

    private string $items;

    public function __construct()
    {
        global $wpdb;
        $this->orders = $wpdb->prefix . 'sikshya_orders';
        $this->items = $wpdb->prefix . 'sikshya_order_items';
    }

    /**
     * @param array<string, mixed> $order
     */
    public function createOrder(array $order): int
    {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal' => 0.00,
            'discount_total' => 0.00,
            'total' => 0.00,
            'gateway' => '',
            'gateway_intent_id' => null,
            'coupon_id' => null,
            'meta' => null,
        ];
        $order = wp_parse_args($order, $defaults);

        $wpdb->insert(
            $this->orders,
            [
                'user_id' => (int) $order['user_id'],
                'status' => sanitize_key((string) $order['status']),
                'currency' => strtoupper(sanitize_text_field((string) $order['currency'])),
                'subtotal' => (float) $order['subtotal'],
                'discount_total' => (float) $order['discount_total'],
                'total' => (float) $order['total'],
                'gateway' => sanitize_key((string) $order['gateway']),
                'gateway_intent_id' => $order['gateway_intent_id'] ? sanitize_text_field((string) $order['gateway_intent_id']) : null,
                'coupon_id' => $order['coupon_id'] ? (int) $order['coupon_id'] : null,
                'meta' => $order['meta'] !== null ? wp_json_encode($order['meta']) : null,
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function addOrderItem(int $order_id, int $course_id, int $quantity, float $unit_price, float $line_total): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->items,
            [
                'order_id' => $order_id,
                'course_id' => $course_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $line_total,
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->orders} WHERE id = %d", $id));

        return $row ?: null;
    }

    public function findByGatewayIntent(string $gateway, string $intent_id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders} WHERE gateway = %s AND gateway_intent_id = %s LIMIT 1",
                $gateway,
                $intent_id
            )
        );

        return $row ?: null;
    }

    /**
     * @return array<int, object>
     */
    public function getItems(int $order_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->items} WHERE order_id = %d", $order_id));
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function updateOrder(int $id, array $patch): bool
    {
        global $wpdb;

        $allowed = ['status', 'gateway_intent_id', 'meta', 'total', 'discount_total', 'subtotal'];
        $data = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }
            $val = $patch[$key];
            if ($key === 'meta' && $val !== null && !is_string($val)) {
                $val = wp_json_encode($val);
            }
            if ($key === 'meta') {
                $data['meta'] = $val;
            } elseif (in_array($key, ['total', 'discount_total', 'subtotal'], true)) {
                $data[$key] = (float) $val;
            } else {
                $data[$key] = sanitize_text_field((string) $val);
            }
        }

        if ($data === []) {
            return false;
        }

        return false !== $wpdb->update($this->orders, $data, ['id' => $id]);
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->orders)) === $this->orders;
    }

    /**
     * Paginated orders with payer display name (admin list).
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findAllPaged(int $per_page, int $offset): array
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return ['rows' => [], 'total' => 0];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->orders}");

        $sql = $wpdb->prepare(
            "SELECT o.*, u.display_name AS payer_name, u.user_email AS payer_email
            FROM {$this->orders} o
            LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
            ORDER BY o.id DESC
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($sql);

        return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
    }

    /**
     * Recent orders for a learner (frontend account / order history).
     *
     * @return array<int, object>
     */
    public function findRecentForUser(int $user_id, int $limit = 50): array
    {
        global $wpdb;

        if ($user_id <= 0 || !$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->orders} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
            $user_id,
            $limit
        );

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : [];
    }

    public function findByIdForUser(int $order_id, int $user_id): ?object
    {
        $row = $this->findById($order_id);
        if (!$row || (int) $row->user_id !== $user_id) {
            return null;
        }

        return $row;
    }
}
