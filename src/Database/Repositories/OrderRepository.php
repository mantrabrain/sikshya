<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\OrdersTable;
use Sikshya\Database\Tables\OrderItemsTable;

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
        $this->orders = OrdersTable::getTableName();
        $this->items = OrderItemsTable::getTableName();
    }

    /**
     * 32 hex chars (128-bit) — opaque public order link; never sequential IDs in URLs.
     */
    public static function generatePublicToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Normalize token from query string; returns empty if invalid.
     *
     * Accepts either raw 32-hex token or prefixed form: `SIK-ORD-<32hex>`.
     */
    public static function sanitizePublicToken(string $token): string
    {
        $raw = trim((string) $token);
        if ($raw === '') {
            return '';
        }

        // Allow prefix in any case.
        $raw = preg_replace('/^\\s*sik\\-ord\\-\\s*/i', '', $raw);

        // Be tolerant to copy/paste issues: extract the first 32-hex token in the string.
        // (e.g. "SIK-ORD-<token>View Receipt", accidental query concatenations, etc.)
        $candidate = strtolower((string) $raw);
        if (preg_match('/([a-f0-9]{32})/i', $candidate, $m) && isset($m[1])) {
            return strtolower((string) $m[1]);
        }

        // Fallback: strip non-hex and ensure exact length.
        $t = strtolower((string) preg_replace('/[^a-f0-9]/', '', (string) $raw));
        if (strlen($t) === 32) {
            return $t;
        }

        return '';
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

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $public_token = self::generatePublicToken();
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
                    'public_token' => $public_token,
                    'coupon_id' => $order['coupon_id'] ? (int) $order['coupon_id'] : null,
                    'meta' => $order['meta'] !== null ? wp_json_encode($order['meta']) : null,
                ]
            );
            $new_id = (int) $wpdb->insert_id;
            if ($new_id > 0) {
                return $new_id;
            }
            if (stripos((string) $wpdb->last_error, 'duplicate') === false) {
                break;
            }
        }

        return 0;
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

        $allowed = ['status', 'gateway', 'gateway_intent_id', 'public_token', 'meta', 'total', 'discount_total', 'subtotal', 'user_id'];
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
            } elseif ($key === 'gateway') {
                $data['gateway'] = sanitize_key((string) $val);
            } elseif ($key === 'public_token') {
                $tok = self::sanitizePublicToken((string) $val);
                $data['public_token'] = $tok !== '' ? $tok : null;
            } elseif ($key === 'user_id') {
                $data['user_id'] = (int) $val;
            } else {
                $data[$key] = sanitize_text_field((string) $val);
            }
        }

        if ($data === []) {
            return false;
        }

        $previous = null;
        if (array_key_exists('status', $data)) {
            $previous = $this->findById($id);
        }

        $updated = false !== $wpdb->update($this->orders, $data, ['id' => $id]);

        if ($updated && $previous !== null && array_key_exists('status', $data)) {
            $previous_status = (string) ($previous->status ?? '');
            $new_status = (string) $data['status'];
            if ($previous_status !== $new_status) {
                /**
                 * Fires whenever an order's status transitions. Generic event
                 * for any addon (commissions, notifications, audit) to react.
                 *
                 * @param int    $order_id
                 * @param string $new_status
                 * @param string $previous_status
                 * @param object $order_row Latest order row snapshot (post-update).
                 */
                $current = $this->findById($id);
                do_action(
                    'sikshya_order_status_changed',
                    $id,
                    $new_status,
                    $previous_status,
                    $current ?? $previous
                );
            }
        }

        return $updated;
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->orders)) === $this->orders;
    }

    /**
     * Total rows in the orders table (all statuses). Used for admin marketing notices.
     */
    public function countAll(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->orders}");
    }

    /**
     * Paid orders for this user (status {@see OrderFulfillmentService} sets to `paid`).
     */
    public function countPaidOrdersForUser(int $user_id): int
    {
        if ($user_id <= 0 || !$this->tableExists()) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->orders} WHERE user_id = %d AND status = %s",
                $user_id,
                'paid'
            )
        );
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

    /**
     * Resolve order for the receipt URL (must match logged-in user).
     */
    public function findByPublicTokenForUser(string $token, int $user_id): ?object
    {
        global $wpdb;

        $clean = self::sanitizePublicToken($token);
        if ($clean === '' || $user_id <= 0 || !$this->tableExists()) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders} WHERE public_token = %s AND user_id = %d LIMIT 1",
                $clean,
                $user_id
            )
        );

        return $row ?: null;
    }

    /**
     * Resolve order for the receipt URL (public token acts as bearer reference).
     *
     * This is used for guest order receipts and post-payment redirects.
     */
    public function findByPublicToken(string $token): ?object
    {
        global $wpdb;

        $clean = self::sanitizePublicToken($token);
        if ($clean === '' || !$this->tableExists()) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders} WHERE public_token = %s LIMIT 1",
                $clean
            )
        );

        return $row ?: null;
    }

    /**
     * Ensure a row has {@see public_token} (migrations + legacy rows).
     */
    public function ensurePublicToken(int $order_id): string
    {
        $row = $this->findById($order_id);
        if (!$row) {
            return '';
        }

        $existing = isset($row->public_token) ? (string) $row->public_token : '';
        $clean = self::sanitizePublicToken($existing);
        if ($clean !== '') {
            return $clean;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $new = self::generatePublicToken();
            if ($this->updateOrder($order_id, ['public_token' => $new])) {
                return self::sanitizePublicToken($new);
            }
        }

        return '';
    }

    /**
     * Permanently delete an order and its line items.
     */
    public function deleteOrder(int $order_id): bool
    {
        if ($order_id <= 0 || !$this->tableExists()) {
            return false;
        }

        global $wpdb;

        // Items first to avoid orphans.
        $wpdb->delete($this->items, ['order_id' => $order_id], ['%d']);
        $ok = $wpdb->delete($this->orders, ['id' => $order_id], ['%d']);

        return $ok !== false && (int) $ok > 0;
    }
}
