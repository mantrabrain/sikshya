<?php

namespace Sikshya\Commerce;

use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CouponRepository;
use Sikshya\Database\Repositories\OrderRepository;

/**
 * One-time checkout: Stripe PaymentIntent + PayPal order creation.
 *
 * @package Sikshya\Commerce
 */
final class CheckoutService
{
    public function __construct(
        private Plugin $plugin,
        private OrderRepository $orders,
        private CouponRepository $coupons
    ) {
    }

    private function settings(): SettingsManager
    {
        $s = $this->plugin->getService('settings');
        if (!$s instanceof SettingsManager) {
            throw new \RuntimeException('Settings unavailable');
        }

        return $s;
    }

    /**
     * @return array{order_id: int, subtotal: float, discount: float, total: float, currency: string}
     */
    public function createPendingOrder(int $user_id, int $course_id, string $coupon_code = ''): array
    {
        return $this->createPendingOrderForCourses($user_id, [$course_id], $coupon_code);
    }

    /**
     * One order with multiple course line items (cart checkout).
     *
     * @param array<int, int> $course_ids
     * @return array{order_id: int, subtotal: float, discount: float, total: float, currency: string}
     */
    public function createPendingOrderForCourses(int $user_id, array $course_ids, string $coupon_code = ''): array
    {
        $course_ids = array_values(array_unique(array_filter(array_map('intval', $course_ids))));
        if ($user_id <= 0 || $course_ids === []) {
            throw new \InvalidArgumentException(__('Invalid checkout parameters.', 'sikshya'));
        }

        $currency = strtoupper((string) $this->settings()->getSetting('currency', 'USD'));
        if (strlen($currency) !== 3) {
            $currency = 'USD';
        }

        $line_amounts = [];
        $subtotal = 0.0;
        foreach ($course_ids as $cid) {
            $line = $this->effectivePriceForCourse($cid);
            $line = round(max(0.0, $line), 2);
            $line_amounts[$cid] = $line;
            $subtotal += $line;
        }
        $subtotal = round($subtotal, 2);

        $discount = 0.00;
        $total = $subtotal;
        $coupon_id = null;

        if ($coupon_code !== '') {
            $coupon = $this->coupons->findActiveByCode($coupon_code);
            if ($coupon) {
                [$discount, $total] = $this->coupons->applyToAmount($coupon, $subtotal);
                $coupon_id = (int) $coupon->id;
            } else {
                throw new \InvalidArgumentException(__('Invalid or expired coupon.', 'sikshya'));
            }
        }

        $order_id = $this->orders->createOrder(
            [
                'user_id' => $user_id,
                'status' => 'pending',
                'currency' => $currency,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'total' => $total,
                'gateway' => '',
                'coupon_id' => $coupon_id,
                'meta' => ['course_ids' => $course_ids],
            ]
        );

        foreach ($line_amounts as $cid => $amt) {
            $this->orders->addOrderItem($order_id, $cid, 1, $amt, $amt);
        }

        if ($coupon_id) {
            $this->coupons->incrementUsedCount($coupon_id);
            $this->coupons->recordRedemption($coupon_id, $user_id, $order_id);
        }

        return [
            'order_id' => $order_id,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'currency' => $currency,
        ];
    }

    /**
     * Resolved sale/regular price from shared template helpers / meta keys.
     */
    private function effectivePriceForCourse(int $course_id): float
    {
        if (function_exists('sikshya_get_course_pricing')) {
            $p = sikshya_get_course_pricing($course_id);
            $e = $p['effective'] ?? null;

            return null !== $e ? (float) $e : 0.0;
        }

        $raw = (float) get_post_meta($course_id, '_sikshya_course_price', true);

        return max(0.0, $raw);
    }

    /**
     * @return array{client_secret?: string, payment_intent_id?: string, approval_url?: string, paypal_order_id?: string}
     */
    public function startGatewaySession(int $order_id, string $gateway): array
    {
        $order = $this->orders->findById($order_id);
        if (!$order || $order->status !== 'pending') {
            throw new \InvalidArgumentException(__('Order not available.', 'sikshya'));
        }

        $gateway = strtolower($gateway);
        $this->orders->updateOrder($order_id, ['gateway' => $gateway]);

        if ($gateway === 'stripe') {
            return $this->createStripePaymentIntent($order);
        }

        if ($gateway === 'paypal') {
            return $this->createPayPalOrder($order);
        }

        throw new \InvalidArgumentException(__('Unsupported gateway.', 'sikshya'));
    }

    private function createStripePaymentIntent(object $order): array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '') {
            throw new \RuntimeException(__('Stripe is not configured.', 'sikshya'));
        }

        $amount = (float) $order->total;
        $currency = strtolower((string) $order->currency);
        $cents = (int) round($amount * 100);

        $body = [
            'amount' => (string) $cents,
            'currency' => $currency,
            'metadata[order_id]' => (string) $order->id,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        $response = wp_remote_post(
            'https://api.stripe.com/v1/payment_intents',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                ],
                'body' => $body,
            ]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code >= 400 || !is_array($json) || empty($json['id'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? (string) $json['error']['message'] : 'Stripe error';
            throw new \RuntimeException($msg);
        }

        $pi_id = (string) $json['id'];
        $client_secret = (string) ($json['client_secret'] ?? '');
        $this->orders->updateOrder((int) $order->id, ['gateway_intent_id' => $pi_id]);

        return [
            'payment_intent_id' => $pi_id,
            'client_secret' => $client_secret,
        ];
    }

    private function createPayPalOrder(object $order): array
    {
        $client_id = (string) $this->settings()->getSetting('paypal_client_id', '');
        $secret = (string) $this->settings()->getSetting('paypal_secret', '');
        $mode = strtolower((string) $this->settings()->getSetting('paypal_mode', 'sandbox'));
        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        if ($client_id === '' || $secret === '') {
            throw new \RuntimeException(__('PayPal is not configured.', 'sikshya'));
        }

        $token_res = wp_remote_post(
            $base . '/v1/oauth2/token',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                ],
                'body' => ['grant_type' => 'client_credentials'],
            ]
        );

        if (is_wp_error($token_res)) {
            throw new \RuntimeException($token_res->get_error_message());
        }

        $token_body = json_decode((string) wp_remote_retrieve_body($token_res), true);
        $access = is_array($token_body) ? (string) ($token_body['access_token'] ?? '') : '';
        if ($access === '') {
            throw new \RuntimeException(__('PayPal token failed.', 'sikshya'));
        }

        $value = number_format((float) $order->total, 2, '.', '');
        $currency = strtoupper((string) $order->currency);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $value,
                    ],
                ],
            ],
        ];

        $order_res = wp_remote_post(
            $base . '/v2/checkout/orders',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($order_res)) {
            throw new \RuntimeException($order_res->get_error_message());
        }

        $order_json = json_decode((string) wp_remote_retrieve_body($order_res), true);
        if (!is_array($order_json) || empty($order_json['id'])) {
            throw new \RuntimeException(__('PayPal order creation failed.', 'sikshya'));
        }

        $paypal_id = (string) $order_json['id'];
        $approval = '';
        if (!empty($order_json['links']) && is_array($order_json['links'])) {
            foreach ($order_json['links'] as $link) {
                if (is_array($link) && ($link['rel'] ?? '') === 'approve') {
                    $approval = (string) ($link['href'] ?? '');
                    break;
                }
            }
        }

        $this->orders->updateOrder((int) $order->id, ['gateway_intent_id' => $paypal_id]);

        return [
            'paypal_order_id' => $paypal_id,
            'approval_url' => $approval,
        ];
    }

    /**
     * Confirm Stripe PaymentIntent server-side.
     */
    public function retrieveStripePaymentIntent(string $payment_intent_id): ?array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '') {
            return null;
        }

        $response = wp_remote_get(
            'https://api.stripe.com/v1/payment_intents/' . rawurlencode($payment_intent_id),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($json) ? $json : null;
    }

    /**
     * Capture PayPal order after buyer approval.
     */
    public function capturePayPalOrder(string $paypal_order_id): ?array
    {
        $client_id = (string) $this->settings()->getSetting('paypal_client_id', '');
        $secret = (string) $this->settings()->getSetting('paypal_secret', '');
        $mode = strtolower((string) $this->settings()->getSetting('paypal_mode', 'sandbox'));
        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $token_res = wp_remote_post(
            $base . '/v1/oauth2/token',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                ],
                'body' => ['grant_type' => 'client_credentials'],
            ]
        );

        if (is_wp_error($token_res)) {
            return null;
        }

        $token_body = json_decode((string) wp_remote_retrieve_body($token_res), true);
        $access = is_array($token_body) ? (string) ($token_body['access_token'] ?? '') : '';
        if ($access === '') {
            return null;
        }

        $cap = wp_remote_post(
            $base . '/v2/checkout/orders/' . rawurlencode($paypal_order_id) . '/capture',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access,
                    'Content-Type' => 'application/json',
                ],
                'body' => '{}',
            ]
        );

        if (is_wp_error($cap)) {
            return null;
        }

        $json = json_decode((string) wp_remote_retrieve_body($cap), true);

        return is_array($json) ? $json : null;
    }
}
