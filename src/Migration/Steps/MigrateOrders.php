<?php

/**
 * Project legacy `sik_orders` posts into the rewrite's `sikshya_orders`
 * and `sikshya_order_items` tables.
 *
 * Legacy orders carry a serialized `sikshya_order_meta` post-meta blob
 * with shape:
 *
 *   [
 *     'cart' => [course_id => ['quantity'=>1,'unit_price'=>..,'total'=>..]],
 *     'currency' => 'USD',
 *     'student_id' => 42,
 *     'total_order_amount' => 199.0,
 *   ]
 *
 * Status maps directly: legacy `sikshya-pending` -> `pending`, etc. We
 * preserve the legacy post ID as `meta.legacy_post_id` so admins can
 * cross-reference.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Database\Tables\OrderItemsTable;
use Sikshya\Database\Tables\OrdersTable;
use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateOrders extends AbstractStep
{
    /** @var array<string,string> */
    private const STATUS_MAP = [
        'sikshya-pending' => 'pending',
        'sikshya-processing' => 'processing',
        'sikshya-on-hold' => 'on_hold',
        'sikshya-completed' => 'completed',
        'sikshya-cancelled' => 'cancelled',
    ];

    public function id(): string
    {
        return 'orders';
    }

    public function description(): string
    {
        return __('Project legacy sik_orders posts into the new orders table.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'sik_orders')
        );
    }

    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int {
        global $wpdb;
        if (!isset($wpdb)) {
            $this->markComplete($state);
            return 0;
        }
        if (!class_exists(OrdersTable::class) || !class_exists(OrderItemsTable::class)) {
            $this->markComplete($state);
            $logger->warning('OrdersTable / OrderItemsTable missing — skipping order migration.');
            return 0;
        }

        $this->markRunning($state);
        $cursor = $state->getStepCursor($this->id());
        $orders_table = OrdersTable::name();
        $items_table = OrderItemsTable::name();

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_status, post_date_gmt, post_author"
                . " FROM {$wpdb->posts}"
                . " WHERE post_type = %s AND ID > %d"
                . " ORDER BY ID ASC LIMIT %d",
                'sik_orders',
                $cursor,
                max(1, $batchSize)
            )
        );

        if (!is_array($posts) || count($posts) === 0) {
            $this->markComplete($state);
            return 0;
        }

        $processed = 0;
        $last_cursor = $cursor;

        foreach ($posts as $post) {
            $last_cursor = (int) $post->ID;

            $marker_meta = (int) get_post_meta($post->ID, '_sikshya_migrated_order_id', true);
            if ($marker_meta > 0) {
                $state->incrementStepCount($this->id(), 'already_migrated', 1);
                $processed++;
                continue;
            }

            $blob = get_post_meta($post->ID, 'sikshya_order_meta', true);
            $cart = is_array($blob) && isset($blob['cart']) && is_array($blob['cart']) ? $blob['cart'] : [];
            $currency = is_array($blob) && !empty($blob['currency']) ? (string) $blob['currency'] : 'USD';
            $student_id = is_array($blob) && !empty($blob['student_id'])
                ? (int) $blob['student_id']
                : (int) $post->post_author;
            $total = is_array($blob) && isset($blob['total_order_amount'])
                ? (float) $blob['total_order_amount']
                : 0.0;

            $status = self::STATUS_MAP[(string) $post->post_status] ?? 'pending';

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Would create order for legacy post #%d (user=%d, total=%.2f %s, %d items).',
                    $post->ID,
                    $student_id,
                    $total,
                    $currency,
                    count($cart)
                ));
                $processed++;
                continue;
            }

            $meta_json = wp_json_encode([
                'legacy_post_id' => (int) $post->ID,
                'gateway_response' => get_post_meta($post->ID, '_gateway_response', true),
                'txn_id' => get_post_meta($post->ID, 'txn_id', true),
                'sikshya_payment_id' => get_post_meta($post->ID, 'sikshya_payment_id', true),
            ]);

            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$orders_table}"
                    . " (user_id, status, currency, subtotal, discount_total, total, gateway, gateway_intent_id, public_token, meta, created_at)"
                    . " VALUES (%d, %s, %s, %f, %f, %f, %s, %s, %s, %s, %s)",
                    $student_id,
                    $status,
                    strtoupper(substr($currency, 0, 3)),
                    $total,
                    0.0,
                    $total,
                    'legacy',
                    null,
                    'legacy_' . (int) $post->ID,
                    (string) $meta_json,
                    $this->normalizeDateTime((string) $post->post_date_gmt)
                )
            );

            if (!$inserted) {
                $state->incrementStepCount($this->id(), 'errors', 1);
                $logger->warning(sprintf('Failed to insert legacy order #%d: %s', $post->ID, $wpdb->last_error));
                continue;
            }

            $new_order_id = (int) $wpdb->insert_id;
            update_post_meta($post->ID, '_sikshya_migrated_order_id', $new_order_id);
            $state->incrementStepCount($this->id(), 'orders', 1);

            foreach ($cart as $course_id => $line) {
                $course_id = (int) $course_id;
                if ($course_id <= 0) {
                    continue;
                }
                $qty = is_array($line) && !empty($line['quantity']) ? (int) $line['quantity'] : 1;
                $unit = is_array($line) && isset($line['unit_price']) ? (float) $line['unit_price'] : 0.0;
                $line_total = is_array($line) && isset($line['total']) ? (float) $line['total'] : ($unit * $qty);

                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO {$items_table}"
                        . " (order_id, course_id, quantity, unit_price, line_total)"
                        . " VALUES (%d, %d, %d, %f, %f)",
                        $new_order_id,
                        $course_id,
                        max(1, $qty),
                        $unit,
                        $line_total
                    )
                );
                $state->incrementStepCount($this->id(), 'order_items', 1);
            }

            $processed++;
        }

        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        // Dry-runs run a single batch then complete (cursor not persisted)
        // so a follow-up real run starts from a clean slate.
        if ($dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return current_time('mysql', true);
        }
        return $value;
    }
}
