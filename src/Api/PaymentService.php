<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class PaymentService
{
    public function getPayments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_payments';
        $user_id = $request->get_param('user_id');
        $course_id = $request->get_param('course_id');
        
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
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY payment_date DESC";
        
        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, ...$prepare_values);
        }
        
        $payments = $wpdb->get_results($query);
        
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