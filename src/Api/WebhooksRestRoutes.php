<?php

namespace Sikshya\Api;

use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\CourseService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment provider webhooks (signature verification where configured).
 *
 * @package Sikshya\Api
 */

class WebhooksRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/webhooks/stripe', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'stripe'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route($namespace, '/webhooks/paypal', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'paypal'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function stripe(WP_REST_Request $request): WP_REST_Response
    {
        $settings = $this->plugin->getService('settings');
        $secret = '';
        if (is_object($settings) && method_exists($settings, 'getSetting')) {
            $secret = (string) $settings->getSetting('stripe_webhook_secret', '');
        }

        $payload = $request->get_body();
        $sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string) $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if ($secret !== '' && !$this->verifyStripeSignature($payload, $sig, $secret)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || ($event['type'] ?? '') !== 'payment_intent.succeeded') {
            return new WP_REST_Response(['ok' => true, 'ignored' => true], 200);
        }

        $pi = $event['data']['object'] ?? [];
        if (!is_array($pi)) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $order_id = 0;
        if (!empty($pi['metadata']['order_id'])) {
            $order_id = (int) $pi['metadata']['order_id'];
        }

        if ($order_id <= 0 && !empty($pi['id'])) {
            $orders = new OrderRepository();
            $row = $orders->findByGatewayIntent('stripe', (string) $pi['id']);
            if ($row) {
                $order_id = (int) $row->id;
            }
        }

        if ($order_id > 0) {
            $this->fulfillment()->fulfillPaidOrder($order_id);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function paypal(WP_REST_Request $request): WP_REST_Response
    {
        // Verify webhook signature when configured.
        $settings = $this->plugin->getService('settings');
        $paypal_client_id = '';
        $paypal_secret = '';
        $paypal_mode = '';
        $paypal_webhook_id = '';
        if (is_object($settings) && method_exists($settings, 'getSetting')) {
            $paypal_client_id = (string) $settings->getSetting('paypal_client_id', '');
            $paypal_secret = (string) $settings->getSetting('paypal_secret', '');
            $paypal_mode = (string) $settings->getSetting('paypal_mode', '');
            $paypal_webhook_id = (string) $settings->getSetting('paypal_webhook_id', '');
        }

        $payload = $request->get_body();
        if ($paypal_webhook_id !== '' && $paypal_client_id !== '' && $paypal_secret !== '' && $payload !== '') {
            $ok = $this->verifyPayPalSignature(
                $payload,
                $paypal_client_id,
                $paypal_secret,
                $paypal_mode,
                $paypal_webhook_id
            );
            if (!$ok) {
                return new WP_REST_Response(['ok' => false, 'message' => 'Invalid signature'], 400);
            }
        }

        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $type = (string) ($json['event_type'] ?? '');
        if ($type !== 'PAYMENT.CAPTURE.COMPLETED') {
            return new WP_REST_Response(['ok' => true, 'ignored' => true], 200);
        }

        $resource = $json['resource'] ?? [];
        if (!is_array($resource)) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $suppl = $resource['supplementary_data'] ?? [];
        $related = is_array($suppl) ? ($suppl['related_ids']['order_id'] ?? '') : '';
        $paypal_order_id = is_string($related) ? $related : '';

        if ($paypal_order_id === '') {
            // Some payloads include custom_id / invoice_id — fall back to amount only in production.
            return new WP_REST_Response(['ok' => true], 200);
        }

        $orders = new OrderRepository();
        $row = $orders->findByGatewayIntent('paypal', $paypal_order_id);
        if ($row) {
            $this->fulfillment()->fulfillPaidOrder((int) $row->id);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    private function fulfillment(): OrderFulfillmentService
    {
        $course = $this->plugin->getService('course');
        if (!$course instanceof CourseService) {
            throw new \RuntimeException('Course service missing');
        }

        return new OrderFulfillmentService(
            new OrderRepository(),
            new PaymentRepository(),
            $course
        );
    }

    private function verifyStripeSignature(string $payload, string $sig_header, string $secret): bool
    {
        if ($sig_header === '' || $payload === '') {
            return false;
        }

        $parts = explode(',', $sig_header);
        $timestamp = '';
        $signatures = [];
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            }
            if ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }

        if ($timestamp === '' || $signatures === []) {
            return false;
        }

        // Reject stale signatures (5 minute tolerance).
        $ts = (int) $timestamp;
        if ($ts > 0) {
            $age = abs(time() - $ts);
            if ($age > 300) {
                return false;
            }
        }

        $signed = $timestamp . '.' . $payload;

        // Stripe expects the signing secret as-is (the full `whsec_...` string).
        $expected = hash_hmac('sha256', $signed, $secret, false);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function paypalRestBase(string $mode): string
    {
        $m = strtolower(trim($mode));
        return in_array($m, ['live', 'production'], true)
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function verifyPayPalSignature(
        string $payload,
        string $client_id,
        string $secret,
        string $mode,
        string $webhook_id
    ): bool {
        $transmission_id = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID']) ? (string) $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] : '';
        $transmission_time = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME']) ? (string) $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] : '';
        $transmission_sig = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']) ? (string) $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] : '';
        $cert_url = isset($_SERVER['HTTP_PAYPAL_CERT_URL']) ? (string) $_SERVER['HTTP_PAYPAL_CERT_URL'] : '';
        $auth_algo = isset($_SERVER['HTTP_PAYPAL_AUTH_ALGO']) ? (string) $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] : '';

        if ($transmission_id === '' || $transmission_time === '' || $transmission_sig === '' || $cert_url === '' || $auth_algo === '') {
            return false;
        }

        $base = $this->paypalRestBase($mode);

        // OAuth token.
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
            return false;
        }
        $token_body = json_decode((string) wp_remote_retrieve_body($token_res), true);
        $access = is_array($token_body) ? (string) ($token_body['access_token'] ?? '') : '';
        if ($access === '') {
            return false;
        }

        $verify_payload = [
            'auth_algo' => $auth_algo,
            'cert_url' => $cert_url,
            'transmission_id' => $transmission_id,
            'transmission_sig' => $transmission_sig,
            'transmission_time' => $transmission_time,
            'webhook_id' => $webhook_id,
            'webhook_event' => json_decode($payload, true),
        ];
        if (!is_array($verify_payload['webhook_event'])) {
            return false;
        }

        $verify_res = wp_remote_post(
            $base . '/v1/notifications/verify-webhook-signature',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($verify_payload),
            ]
        );
        if (is_wp_error($verify_res)) {
            return false;
        }
        $dec = json_decode((string) wp_remote_retrieve_body($verify_res), true);
        $status = is_array($dec) ? (string) ($dec['verification_status'] ?? '') : '';

        return $status === 'SUCCESS';
    }
}
