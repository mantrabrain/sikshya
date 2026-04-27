<?php

namespace Sikshya\Api;

use Sikshya\Commerce\CheckoutService;
use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CouponRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Frontend\Public\CartStorage;
use Sikshya\Frontend\Public\CheckoutTemplateData;
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

        $bundle_id = (int) CartStorage::getBundleId();
        if (class_exists(\SikshyaPro\Services\BundleCatalogService::class)) {
            $bundle_id = (int) apply_filters('sikshya_checkout_resolve_bundle_id', $bundle_id, $course_ids);
        } elseif ($bundle_id > 0) {
            CartStorage::setBundleIdOnly(0);
            $bundle_id = 0;
        }

        $has_courses = $course_ids !== [] || $course_id > 0;
        $configured = CheckoutTemplateData::gatewaysConfigured();
        $allowed_gateways = array_keys($configured);
        if (!$has_courses || !in_array($gateway, $allowed_gateways, true) || empty($configured[$gateway])) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_params', 'message' => __('Invalid parameters.', 'sikshya')],
                400
            );
        }

        $uid = get_current_user_id();
        $marketingOptIn = null;
        if (is_array($params) && array_key_exists('marketing_opt_in', $params)) {
            $marketingOptIn = (bool) $params['marketing_opt_in'];
        }
        if ($marketingOptIn !== null) {
            /**
             * Capture the buyer's marketing opt-in preference at checkout start.
             *
             * Add-ons (Email marketing) may persist this on the user and use it to decide
             * whether a contact should be synced into providers on enrollment/purchase events.
             */
            do_action('sikshya_checkout_marketing_opt_in', $uid, $marketingOptIn);
        }

        try {
            $checkout = $this->checkoutService();
            if ($course_ids !== []) {
                $order = $checkout->createPendingOrderForCourses($uid, $course_ids, $coupon, $bundle_id);
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
            } elseif ($gateway === 'bank_transfer') {
                $oid = (int) $order['order_id'];
                $total = (float) $order['total'];
                if ($total <= 0.00001) {
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

        $bundle_id = (int) CartStorage::getBundleId();
        if (class_exists(\SikshyaPro\Services\BundleCatalogService::class)) {
            $bundle_id = (int) apply_filters('sikshya_checkout_resolve_bundle_id', $bundle_id, $course_ids);
        } elseif ($bundle_id > 0) {
            CartStorage::setBundleIdOnly(0);
            $bundle_id = 0;
        }

        try {
            $quote = $this->checkoutService()->quoteTotalsForCourses($uid, $course_ids, $coupon, $bundle_id);
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
        $mollie_payment_id = isset($params['mollie_payment_id']) ? (string) $params['mollie_payment_id'] : '';
        $paystack_reference = isset($params['paystack_reference']) ? (string) $params['paystack_reference'] : '';
        $razorpay_payment_link_id = isset($params['razorpay_payment_link_id']) ? (string) $params['razorpay_payment_link_id'] : '';

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

        // Integrity guard: only allow confirming orders still awaiting payment.
        $status = isset($order->status) ? (string) $order->status : '';
        if (!in_array($status, ['pending', 'on-hold'], true)) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'order_not_confirmable', 'message' => __('Order is not awaiting payment.', 'sikshya')],
                400
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
            $pi_status = is_array($json) ? (string) ($json['status'] ?? '') : '';
            if ($pi_status !== 'succeeded') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('Payment not completed.', 'sikshya')],
                    400
                );
            }
            // Bind intent → order: verify metadata and money.
            $md_order = is_array($json) && isset($json['metadata']['order_id']) ? (int) $json['metadata']['order_id'] : 0;
            if ($md_order > 0 && $md_order !== (int) $order->id) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'intent_mismatch', 'message' => __('Payment intent does not match this order.', 'sikshya')],
                    400
                );
            }
            $pi_amount = is_array($json) && isset($json['amount_received']) ? (int) $json['amount_received'] : 0;
            if ($pi_amount <= 0 && is_array($json) && isset($json['amount'])) {
                $pi_amount = (int) $json['amount'];
            }
            $pi_cur = is_array($json) && isset($json['currency']) ? strtoupper((string) $json['currency']) : '';
            $expected_minor = \Sikshya\Commerce\CheckoutService::toMinorUnits((float) $order->total, (string) $order->currency);
            if ($pi_cur !== '' && $pi_cur !== strtoupper((string) $order->currency)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                    400
                );
            }
            if ($pi_amount > 0 && $expected_minor > 0 && $pi_amount !== $expected_minor) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
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
            // Bind capture → order: check purchase_units money and optional custom_id.
            $pu0 = is_array($cap) && isset($cap['purchase_units'][0]) && is_array($cap['purchase_units'][0]) ? $cap['purchase_units'][0] : null;
            $custom_id = is_array($pu0) && isset($pu0['custom_id']) ? (string) $pu0['custom_id'] : '';
            if ($custom_id !== '' && $custom_id !== (string) $order->id) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'paypal_order_mismatch', 'message' => __('PayPal order does not match this order.', 'sikshya')],
                    400
                );
            }
            $amt = is_array($pu0) && isset($pu0['amount']['value']) ? (float) $pu0['amount']['value'] : null;
            $cur = is_array($pu0) && isset($pu0['amount']['currency_code']) ? (string) $pu0['amount']['currency_code'] : '';
            if (is_string($cur) && $cur !== '' && strtoupper($cur) !== strtoupper((string) $order->currency)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                    400
                );
            }
            if (is_float($amt) && $amt >= 0 && abs($amt - (float) $order->total) > 0.009) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                    400
                );
            }
        } elseif ($gateway === 'mollie') {
            $pid = $mollie_payment_id !== '' ? $mollie_payment_id : (string) ($order->gateway_intent_id ?? '');
            if ($pid === '') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_mollie', 'message' => __('Missing Mollie payment id.', 'sikshya')],
                    400
                );
            }
            $m = $this->checkoutService()->getMolliePayment($pid);
            $status = is_array($m) ? (string) ($m['status'] ?? '') : '';
            if ($status !== 'paid') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('Mollie payment not completed.', 'sikshya')],
                    400
                );
            }
            // Bind Mollie payment → order by metadata + money.
            $md_oid = is_array($m) && isset($m['metadata']['order_id']) ? (string) $m['metadata']['order_id'] : '';
            if ($md_oid !== '' && $md_oid !== (string) $order->id) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'mollie_order_mismatch', 'message' => __('Mollie payment does not match this order.', 'sikshya')],
                    400
                );
            }
            $mcur = is_array($m) && isset($m['amount']['currency']) ? strtoupper((string) $m['amount']['currency']) : '';
            $mval = is_array($m) && isset($m['amount']['value']) ? (float) $m['amount']['value'] : null;
            if ($mcur !== '' && $mcur !== strtoupper((string) $order->currency)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                    400
                );
            }
            if (is_float($mval) && $mval >= 0 && abs($mval - (float) $order->total) > 0.009) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                    400
                );
            }
        } elseif ($gateway === 'paystack') {
            $ref = $paystack_reference !== '' ? $paystack_reference : (string) ($order->gateway_intent_id ?? '');
            if ($ref === '') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_paystack', 'message' => __('Missing Paystack reference.', 'sikshya')],
                    400
                );
            }
            $data = $this->checkoutService()->verifyPaystackReference($ref);
            if (!$data || (string) ($data['status'] ?? '') !== 'success') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('Paystack payment not verified.', 'sikshya')],
                    400
                );
            }
            // Bind Paystack tx → order by metadata + money.
            $md_oid = is_array($data) && isset($data['metadata']['order_id']) ? (string) $data['metadata']['order_id'] : '';
            if ($md_oid !== '' && $md_oid !== (string) $order->id) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'paystack_order_mismatch', 'message' => __('Paystack payment does not match this order.', 'sikshya')],
                    400
                );
            }
            $pcur = is_array($data) && isset($data['currency']) ? strtoupper((string) $data['currency']) : '';
            $pamt = is_array($data) && isset($data['amount']) ? (int) $data['amount'] : 0; // minor units
            $expected_minor = \Sikshya\Commerce\CheckoutService::toMinorUnits((float) $order->total, (string) $order->currency);
            if ($pcur !== '' && $pcur !== strtoupper((string) $order->currency)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                    400
                );
            }
            if ($pamt > 0 && $expected_minor > 0 && $pamt !== $expected_minor) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                    400
                );
            }
        } elseif ($gateway === 'razorpay') {
            $lid = $razorpay_payment_link_id !== '' ? $razorpay_payment_link_id : (string) ($order->gateway_intent_id ?? '');
            if ($lid === '') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_razorpay', 'message' => __('Missing Razorpay payment link.', 'sikshya')],
                    400
                );
            }
            $link = $this->checkoutService()->getRazorpayPaymentLink($lid);
            $st = is_array($link) ? (string) ($link['status'] ?? '') : '';
            if ($st !== 'paid') {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'payment_pending', 'message' => __('Razorpay payment link not paid yet.', 'sikshya')],
                    400
                );
            }
            // Bind Razorpay link → order by reference/notes + money.
            $refId = is_array($link) && isset($link['reference_id']) ? (string) $link['reference_id'] : '';
            $noteId = is_array($link) && isset($link['notes']['order_id']) ? (string) $link['notes']['order_id'] : '';
            $bound = $refId !== '' ? $refId : $noteId;
            if ($bound !== '' && $bound !== (string) $order->id) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'razorpay_order_mismatch', 'message' => __('Razorpay payment does not match this order.', 'sikshya')],
                    400
                );
            }
            $rcur = is_array($link) && isset($link['currency']) ? strtoupper((string) $link['currency']) : '';
            $ramt = is_array($link) && isset($link['amount']) ? (int) $link['amount'] : 0; // minor units
            $expected_minor = \Sikshya\Commerce\CheckoutService::toMinorUnits((float) $order->total, (string) $order->currency);
            if ($rcur !== '' && $rcur !== strtoupper((string) $order->currency)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                    400
                );
            }
            if ($ramt > 0 && $expected_minor > 0 && $ramt !== $expected_minor) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                    400
                );
            }
        } else {
            /**
             * Pro/addons can verify/capture payments for additional gateways.
             *
             * Return true when payment is verified as complete, false to let core reject, or a WP_REST_Response to short-circuit.
             */
            $handled = apply_filters('sikshya_checkout_confirm_gateway', false, $gateway, $order, $params);
            if ($handled instanceof \WP_REST_Response) {
                return $handled;
            }
            if ($handled !== true) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'bad_gateway', 'message' => __('Unsupported gateway.', 'sikshya')],
                    400
                );
            }
        }

        $fulfill = $this->fulfillmentService();
        $fulfill->fulfillPaidOrder($order_id);

        $public_token = '';
        if (isset($order->public_token) && is_string($order->public_token)) {
            $public_token = (string) $order->public_token;
        }
        if ($public_token === '') {
            $public_token = (new OrderRepository())->ensurePublicToken($order_id);
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'message' => __('Enrollment complete.', 'sikshya'),
                'data' => [
                    'redirect_url' => PublicPageUrls::orderView($public_token),
                ],
            ],
            200
        );
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
