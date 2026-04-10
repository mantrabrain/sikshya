<?php

namespace Sikshya\Commerce;

use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\CourseService;

/**
 * Marks orders paid, enrolls learners, writes legacy payment rows.
 *
 * @package Sikshya\Commerce
 */
final class OrderFulfillmentService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private CourseService $courseService
    ) {
    }

    /**
     * Idempotent fulfillment for a paid order.
     */
    public function fulfillPaidOrder(int $order_id): bool
    {
        $order = $this->orders->findById($order_id);
        if (!$order) {
            return false;
        }

        if ($order->status === 'paid') {
            return true;
        }

        $items = $this->orders->getItems($order_id);
        $user_id = (int) $order->user_id;
        if ($user_id <= 0) {
            return false;
        }

        foreach ($items as $item) {
            $course_id = (int) $item->course_id;
            if ($course_id <= 0) {
                continue;
            }

            try {
                $this->courseService->enrollUser(
                    $user_id,
                    $course_id,
                    [
                        'payment_method' => (string) $order->gateway,
                        'amount' => (float) $item->line_total,
                        'transaction_id' => (string) ($order->gateway_intent_id ?? ''),
                    ]
                );
            } catch (\InvalidArgumentException $e) {
                // Already enrolled or invalid — continue.
            }

            if ($this->payments->tableExists()) {
                $this->payments->create(
                    [
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'amount' => (float) $item->line_total,
                        'currency' => (string) $order->currency,
                        'payment_method' => (string) $order->gateway,
                        'transaction_id' => (string) ($order->gateway_intent_id ?? ''),
                        'status' => 'completed',
                        'payment_date' => current_time('mysql'),
                        'gateway_response' => ['order_id' => $order_id],
                    ]
                );
            }
        }

        $this->orders->updateOrder($order_id, ['status' => 'paid']);

        return true;
    }
}
