<?php

namespace Sikshya\Api;

use Sikshya\Commerce\CheckoutService;
use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CouponRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\CourseService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout session + confirm (logged-in user).
 *
 * @package Sikshya\Api
 */
class CheckoutRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/checkout/session', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createSession'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/quote', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createQuote'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/confirm', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'confirm'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);
    }

    /**
     * @return bool|\WP_Error
     */
    public function requireLoginOrJwt(WP_REST_Request $request)
    {
        $public = new PublicRestRoutes($this->plugin);

        return $public->requireLoginOrJwt($request);
    }

    public function createSession(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $course_ids = isset($params['course_ids']) && is_array($params['course_ids'])
            ? array_values(array_filter(array_map('intval', $params['course_ids'])))
            : [];
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $gateway = isset($params['gateway']) ? sanitize_key((string) $params['gateway']) : '';
        $coupon = isset($params['coupon_code']) ? trim(sanitize_text_field((string) $params['coupon_code'])) : '';

        $has_courses = $course_ids !== [] || $course_id > 0;
        $allowed_gateways = ['stripe', 'paypal', 'offline'];
        if (!$has_courses || !in_array($gateway, $allowed_gateways, true)) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_params', 'message' => __('Invalid parameters.', 'sikshya')],
                400
            );
        }

        $uid = get_current_user_id();

        try {
            $checkout = $this->checkoutService();
            if ($course_ids !== []) {
                $order = $checkout->createPendingOrderForCourses($uid, $course_ids, $coupon);
            } else {
                $order = $checkout->createPendingOrder($uid, $course_id, $coupon);
            }
            $gateway_payload = $checkout->startGatewaySession($order['order_id'], $gateway);

            if ($gateway === 'offline') {
                $oid = (int) $order['order_id'];
                $total = (float) $order['total'];
                if ($checkout->isOfflineAutoFulfillEnabled() || $total <= 0.00001) {
                    $this->fulfillmentService()->fulfillPaidOrder($oid);
                } else {
                    (new OrderRepository())->updateOrder($oid, ['status' => 'on-hold']);
                }
                $gateway_payload['redirect_url'] = PublicPageUrls::orderView((string) ($order['public_token'] ?? ''));
            }
        } catch (\Exception $e) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'checkout_error', 'message' => $e->getMessage()],
                400
            );
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => array_merge(
                    [
                        'order_id' => $order['order_id'],
                        'public_token' => $order['public_token'] ?? '',
                        'total' => $order['total'],
                        'currency' => $order['currency'],
                        'gateway' => $gateway,
                    ],
                    $gateway_payload
                ),
            ],
            200
        );
    }

    /**
     * Preview subtotal / discount / total without creating an order (checkout UI).
     */
    public function createQuote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $course_ids = isset($params['course_ids']) && is_array($params['course_ids'])
            ? array_values(array_filter(array_map('intval', $params['course_ids'])))
            : [];
        $coupon = isset($params['coupon_code']) ? trim(sanitize_text_field((string) $params['coupon_code'])) : '';

        if ($course_ids === []) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_params', 'message' => __('Invalid parameters.', 'sikshya')],
                400
            );
        }

        $uid = get_current_user_id();

        try {
            $quote = $this->checkoutService()->quoteTotalsForCourses($uid, $course_ids, $coupon);
        } catch (\Exception $e) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'quote_error', 'message' => $e->getMessage()],
                400
            );
        }

        return new WP_REST_Response(['ok' => true, 'data' => $quote], 200);
    }

    public function confirm(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $order_id = isset($params['order_id']) ? (int) $params['order_id'] : 0;
        $gateway = isset($params['gateway']) ? sanitize_key((string) $params['gateway']) : '';
        $pi = isset($params['payment_intent_id']) ? (string) $params['payment_intent_id'] : '';
        $paypal_order = isset($params['paypal_order_id']) ? (string) $params['paypal_order_id'] : '';

        if ($order_id <= 0) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_order', 'message' => __('Invalid order.', 'sikshya')],
                400
            );
        }

        $orders = new OrderRepository();
        $order = $orders->findById($order_id);
        if (!$order || (int) $order->user_id !== get_current_user_id()) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'forbidden', 'message' => __('Invalid order.', 'sikshya')],
                403
            );
        }

        if ($gateway === 'stripe') {
            if ($pi === '') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_intent', 'message' => __('Missing payment intent.', 'sikshya')],
                    400
                );
            }
            $json = $this->checkoutService()->retrieveStripePaymentIntent($pi);
            $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
            if ($status !== 'succeeded') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('Payment not completed.', 'sikshya')],
                    400
                );
            }
        } elseif ($gateway === 'paypal') {
            if ($paypal_order === '') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_paypal', 'message' => __('Missing PayPal order.', 'sikshya')],
                    400
                );
            }
            $cap = $this->checkoutService()->capturePayPalOrder($paypal_order);
            $cap_status = is_array($cap) ? (string) ($cap['status'] ?? '') : '';
            if ($cap_status !== 'COMPLETED') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('PayPal capture failed.', 'sikshya')],
                    400
                );
            }
        } else {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'bad_gateway', 'message' => __('Unsupported gateway.', 'sikshya')],
                400
            );
        }

        $fulfill = $this->fulfillmentService();
        $fulfill->fulfillPaidOrder($order_id);

        return new WP_REST_Response(['ok' => true, 'message' => __('Enrollment complete.', 'sikshya')], 200);
    }

    private function checkoutService(): CheckoutService
    {
        return new CheckoutService(
            $this->plugin,
            new OrderRepository(),
            new CouponRepository()
        );
    }

    private function fulfillmentService(): OrderFulfillmentService
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
}
