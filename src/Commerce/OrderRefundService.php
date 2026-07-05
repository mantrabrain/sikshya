<?php

namespace Sikshya\Commerce;

use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\CourseService;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reverses a paid Sikshya order in response to a payment refund.
 *
 * Mirrors {@see OrderFulfillmentService::fulfillPaidOrder()} but in the
 * other direction: take a `paid` order, remove the learner enrolments it
 * created, mark the payment rows refunded, transition the order to
 * `refunded`. Wrapped in a single DB transaction so a partial failure
 * doesn't leave the system in a half-refunded state (the order row's
 * `FOR UPDATE` lock also serialises concurrent refund webhooks for the
 * same order — Stripe retries `charge.refunded` if the first delivery
 * 5xx's, and we don't want to double-process).
 *
 * Idempotent by design: re-running on an already-`refunded` order is a
 * no-op success, so the gateway can safely retry.
 *
 * Wires a `sikshya_order_refunded` action AFTER the transaction commits
 * so listeners (Pro multi-instructor revenue rollback, email notifications,
 * Slack integrations) see a consistent state when they read back.
 *
 * @package Sikshya\Commerce
 */
final class OrderRefundService
{
    private OrderRepository $orders;
    private PaymentRepository $payments;
    private CourseService $courseService;

    public function __construct(OrderRepository $orders, PaymentRepository $payments, CourseService $courseService)
    {
        $this->orders = $orders;
        $this->payments = $payments;
        $this->courseService = $courseService;
    }

    /**
     * Refund and reverse a `paid` order.
     *
     * @param int    $order_id          Local order ID.
     * @param string $reason            Free-text label propagated to listeners
     *                                  (e.g., 'stripe_charge_refunded',
     *                                  'admin_manual', 'dispute_won').
     * @param ?float $refunded_amount   Optional; for partial refunds (not yet
     *                                  acted on in v1 — flagged via the
     *                                  action arg so future logic can branch).
     * @return bool                     True on success or no-op idempotent
     *                                  retry. False on transactional failure
     *                                  (gives the gateway something to retry).
     */
    public function refundFullOrder(int $order_id, string $reason = '', ?float $refunded_amount = null): bool
    {
        global $wpdb;

        if ($order_id <= 0) {
            return false;
        }

        $wpdb->query('START TRANSACTION');

        $user_id = 0;
        $refunded_courses = [];
        $order_snapshot = null;

        try {
            // Same row-level lock pattern as fulfilment — serialises concurrent
            // refund deliveries for the same order so we don't double-unenrol.
            $row = $this->orders->findByIdForUpdate($order_id);
            if (!$row) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            $order_snapshot = $row;

            $current_status = (string) ($row->status ?? '');
            if ($current_status === 'refunded') {
                // Already refunded by a previous webhook delivery — succeed
                // silently so the gateway stops retrying.
                $wpdb->query('COMMIT');
                return true;
            }
            if ($current_status !== 'paid') {
                // Refunding a pending / cancelled / failed order doesn't make
                // sense. Bail rather than silently flipping status from an
                // unexpected state.
                $wpdb->query('ROLLBACK');
                return false;
            }

            $user_id = (int) ($row->user_id ?? 0);
            $gateway_intent_id = (string) ($row->gateway_intent_id ?? '');
            $items = $this->orders->getItems($order_id);

            // Reverse enrolments. Each course is independent; a failure on
            // one (e.g., learner already manually unenrolled) shouldn't
            // abort the refund — log and continue so the order can still
            // transition to `refunded`.
            if ($user_id > 0 && is_array($items)) {
                foreach ($items as $item) {
                    $course_id = (int) ($item->course_id ?? 0);
                    if ($course_id <= 0) {
                        continue;
                    }
                    try {
                        if ($this->courseService->forceUnenrollForRefund($user_id, $course_id, $order_id)) {
                            $refunded_courses[] = $course_id;
                        }
                    } catch (\Throwable $e) {
                        // Best-effort: log so admin can investigate, but don't
                        // abort. A failed unenrol is preferable to a stuck
                        // order in `paid` state with a real refund recorded
                        // upstream.
                        error_log(sprintf(
                            'Sikshya refund: could not unenrol user %d from course %d on order %d: %s',
                            $user_id,
                            $course_id,
                            $order_id,
                            $e->getMessage()
                        ));
                    }
                }
            }

            // Flip the payment row(s) attached to this order.
            if ($gateway_intent_id !== '' && $this->payments->tableExists()) {
                $this->payments->markRefundedByOrder($order_id, $gateway_intent_id);
            }

            // Finally, transition the order itself.
            $this->orders->updateOrder($order_id, ['status' => 'refunded']);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        /**
         * Fires after a paid order has been refunded and the local state
         * (enrolments removed, payments + order marked refunded) has
         * committed. Listeners can safely read back consistent state.
         *
         * Pro's multi-instructor revenue-share rollback hooks this action
         * to reverse the per-instructor commission rows.
         *
         * @param int     $order_id          Local order ID.
         * @param int     $user_id           Learner who lost access (0 for guest orders that never resolved a user).
         * @param int[]   $refunded_courses  Courses the learner was unenrolled from.
         * @param string  $reason            Free-text origin label.
         * @param ?float  $refunded_amount   Total refunded; null for "full" refunds.
         * @param object  $order_snapshot    The pre-refund order row, for listeners that need price/gateway data.
         */
        do_action(
            'sikshya_order_refunded',
            $order_id,
            $user_id,
            $refunded_courses,
            $reason,
            $refunded_amount,
            $order_snapshot
        );

        return true;
    }
}
