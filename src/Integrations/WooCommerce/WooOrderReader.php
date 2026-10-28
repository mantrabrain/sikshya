<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The only class here that talks to WooCommerce.
 *
 * It turns a WooCommerce order into an {@see OrderSnapshot} and stops. Keeping
 * the boundary this thin is deliberate: every rule about who gets access lives
 * in {@see CourseAccessService}, where it can be tested on a site that has
 * never had WooCommerce installed.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class WooOrderReader
{
    public static function available(): bool
    {
        return function_exists('wc_get_order');
    }

    public static function read(int $orderId): ?OrderSnapshot
    {
        if (!self::available() || $orderId <= 0) {
            return null;
        }

        $order = wc_get_order($orderId);
        if (!$order || !is_object($order) || !method_exists($order, 'get_items')) {
            return null;
        }

        $lines = [];

        foreach ($order->get_items() as $itemId => $item) {
            if (!is_object($item) || !method_exists($item, 'get_product_id')) {
                continue;
            }

            $productIds = [(int) $item->get_product_id()];
            if (method_exists($item, 'get_variation_id')) {
                $variationId = (int) $item->get_variation_id();
                if ($variationId > 0) {
                    // A variation can carry its own courses — "course plus
                    // workbook", say — and falls back to the parent's when it
                    // does not.
                    array_unshift($productIds, $variationId);
                }
            }

            $courses = [];
            foreach ($productIds as $productId) {
                $courses = ProductCourseLink::coursesFor($productId);
                if ($courses !== []) {
                    break;
                }
            }

            if ($courses === []) {
                continue;
            }

            $refunded = false;
            if (method_exists($order, 'get_qty_refunded_for_item')) {
                $refundedQty = (int) $order->get_qty_refunded_for_item((int) $itemId);
                $quantity = method_exists($item, 'get_quantity') ? (int) $item->get_quantity() : 1;
                // Only a fully refunded line loses access. Refunding one seat
                // of five should not shut the buyer out of the course.
                $refunded = $refundedQty > 0 && $refundedQty >= $quantity;
            }

            foreach ($courses as $courseId) {
                $lines[] = [
                    'course_id' => (int) $courseId,
                    'item_id' => (int) $itemId,
                    'product_id' => (int) $item->get_product_id(),
                    'refunded' => $refunded,
                ];
            }
        }

        return new OrderSnapshot(
            $orderId,
            method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0,
            method_exists($order, 'get_billing_email') ? (string) $order->get_billing_email() : '',
            method_exists($order, 'get_billing_first_name') ? (string) $order->get_billing_first_name() : '',
            method_exists($order, 'get_billing_last_name') ? (string) $order->get_billing_last_name() : '',
            method_exists($order, 'get_status') ? (string) $order->get_status() : '',
            $lines
        );
    }
}
