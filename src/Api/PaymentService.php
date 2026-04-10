<?php

namespace Sikshya\Api;

use Sikshya\Database\Repositories\PaymentRepository;
use WP_REST_Request;
use WP_REST_Response;

class PaymentService
{
    private PaymentRepository $payments;

    public function __construct(?PaymentRepository $payments = null)
    {
        $this->payments = $payments ?? new PaymentRepository();
    }

    public function getPayments(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->payments->tableExists()) {
            return new WP_REST_Response(
                [
                    'payments' => [],
                    'message' => 'Payments table not found.',
                ],
                200
            );
        }

        $user_id = $request->get_param('user_id') ? (int) $request->get_param('user_id') : null;
        $course_id = $request->get_param('course_id') ? (int) $request->get_param('course_id') : null;

        $payments = $this->payments->findFiltered($user_id, $course_id);

        return new WP_REST_Response([
            'payments' => array_map([$this, 'formatPayment'], $payments),
        ]);
    }

    private function formatPayment($payment): array
    {
        return [
            'id' => $payment->id,
            'user_id' => $payment->user_id,
            'course_id' => $payment->course_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'status' => $payment->status,
            'payment_date' => $payment->payment_date,
        ];
    }
}
