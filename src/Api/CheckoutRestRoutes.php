<?php

namespace Sikshya\Api;

use Sikshya\Commerce\CheckoutService;
use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CouponRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Frontend\Site\CartStorage;
use Sikshya\Frontend\Site\CheckoutTemplateData;
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
                'permission_callback' => [$this, 'requireCheckoutAuth'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/quote', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createQuote'],
                'permission_callback' => [$this, 'requireCheckoutAuth'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/confirm', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'confirm'],
                'permission_callback' => [$this, 'requireCheckoutConfirmAuth'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/stripe/intent', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'stripeIntent'],
                'permission_callback' => [$this, 'requireCheckoutAuth'],
            ],
        ]);

        register_rest_route($namespace, '/checkout/clear-cart', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'clearCart'],
                'permission_callback' => [$this, 'requireCheckoutConfirmAuth'],
            ],
        ]);
    }

    public function stripeIntent(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $uid = get_current_user_id();
        $is_guest = $uid <= 0;

        // Never trust client-provided course IDs for checkout/payment.
        // Always derive the authoritative list from server cart storage.
        $course_ids = array_values(array_filter(array_map('intval', CartStorage::getCourseIds())));

        if ($course_ids === []) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_params', 'message' => __('No courses in checkout.', 'sikshya')],
                400
            );
        }

        $coupon = isset($params['coupon_code']) ? trim(sanitize_text_field((string) $params['coupon_code'])) : '';
        $raw_bundle = (int) CartStorage::getBundleId();
        if (!(bool) apply_filters('sikshya_checkout_bundle_cart_supported', false) && $raw_bundle > 0) {
            CartStorage::setBundleIdOnly(0);
            $raw_bundle = 0;
        }
        $bundle_id = (int) apply_filters('sikshya_checkout_resolve_bundle_id', $raw_bundle, $course_ids);

        $guest_email = isset($params['guest_email']) ? sanitize_email((string) $params['guest_email']) : '';
        $guest_name = isset($params['guest_name']) ? sanitize_text_field((string) $params['guest_name']) : '';
        $dynamic_fields = isset($params['dynamic_fields']) && is_array($params['dynamic_fields']) ? $params['dynamic_fields'] : [];

        if ($is_guest) {
            if (!\Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('enable_guest_checkout', true))) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'login_required', 'message' => __('Please sign in to continue checkout.', 'sikshya')],
                    403
                );
            }
            if ($guest_email === '' || !is_email($guest_email)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'invalid_email', 'message' => __('Please enter a valid email to continue.', 'sikshya')],
                    400
                );
            }
            if (email_exists($guest_email)) {
                return new WP_REST_Response(
                    [
                        'ok' => false,
                        'code' => 'email_exists',
                        'message' => __('An account already exists for this email. Please sign in to continue checkout.', 'sikshya'),
                    ],
                    409
                );
            }
        }

        try {
            $checkout = $this->checkoutService();
            $order = $checkout->createPendingOrderForCourses($uid, $course_ids, $coupon, $bundle_id);

            $order_id = (int) ($order['order_id'] ?? 0);
            if ($order_id <= 0) {
                throw new \RuntimeException(__('Could not create order.', 'sikshya'));
            }

            if ($is_guest) {
                // Attach guest identity to order so we can auto-create an account at fulfillment time.
                $repo = new OrderRepository();
                $row = $repo->findById($order_id);
                $existing = [];
                if ($row && isset($row->meta) && is_string($row->meta) && $row->meta !== '') {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
                $existing['guest'] = [
                    'email' => $guest_email,
                    'name' => $guest_name,
                ];
                $repo->updateOrder($order_id, ['meta' => $existing]);
            }

            // Validate + persist dynamic checkout fields (Growth) the same way as /checkout/session.
            $df_result = apply_filters(
                'sikshya_checkout_dynamic_fields_validate',
                $dynamic_fields,
                $request,
                $order_id,
                (int) $uid,
                (bool) $is_guest
            );
            if (is_wp_error($df_result)) {
                return new WP_REST_Response(
                    [
                        'ok' => false,
                        'code' => (string) $df_result->get_error_code(),
                        'message' => (string) $df_result->get_error_message(),
                        'data' => $df_result->get_error_data(),
                    ],
                    400
                );
            }
            if (is_array($df_result) && ($df_result !== [])) {
                $repo = new OrderRepository();
                $row = $repo->findById($order_id);
                $existing = [];
                if ($row && isset($row->meta) && is_string($row->meta) && $row->meta !== '') {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
                $existing['dynamic_fields'] = [
                    'schema_version' => 1,
                    'schema' => isset($df_result['schema']) && is_array($df_result['schema']) ? $df_result['schema'] : [],
                    'values' => isset($df_result['values']) && is_array($df_result['values']) ? $df_result['values'] : [],
                ];
                $repo->updateOrder($order_id, ['meta' => $existing]);
            }

            $row = (new OrderRepository())->findById($order_id);
            if (!$row) {
                throw new \RuntimeException(__('Invalid order.', 'sikshya'));
            }

            $email = '';
            if ($is_guest) {
                $email = $guest_email;
            } else {
                $u = wp_get_current_user();
                $email = ($u && $u->exists()) ? (string) $u->user_email : '';
            }

            $payload = $checkout->createOrReuseStripePaymentIntent($row, $email);

            return new WP_REST_Response(
                [
                    'ok' => true,
                    'data' => [
                        'order_id' => $order_id,
                        'public_token' => $order['public_token'] ?? '',
                        'payment_intent_id' => $payload['payment_intent_id'] ?? '',
                        'client_secret' => $payload['client_secret'] ?? '',
                    ],
                ],
                200
            );
        } catch (\Exception $e) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'checkout_error', 'message' => $e->getMessage()],
                400
            );
        }
    }

    public function clearCart(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        CartStorage::clear();
        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * @return bool|\WP_Error
     */
    public function requireLoginOrJwt(WP_REST_Request $request)
    {
        $public = new PublicRestRoutes($this->plugin);

        return $public->requireLoginOrJwt($request);
    }

    /**
     * Guest checkout uses a separate nonce rendered on the checkout page to protect
     * against cross-site form submissions.
     *
     * @return bool|\WP_Error
     */
    private function requireGuestCheckoutNonce(WP_REST_Request $request)
    {
        if (!\Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('enable_guest_checkout', true))) {
            return new \WP_Error('sikshya_forbidden', __('Please sign in to continue checkout.', 'sikshya'), ['status' => 403]);
        }

        $nonce = (string) $request->get_header('X-Sikshya-Guest-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('guest_nonce');
        }

        if ($nonce === '' || !wp_verify_nonce($nonce, 'sikshya_guest_checkout')) {
            return new \WP_Error('sikshya_forbidden', __('Invalid request.', 'sikshya'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Allow checkout for logged-in users, or guest checkout when enabled.
     *
     * @return bool|\WP_Error
     */
    public function requireCheckoutAuth(WP_REST_Request $request)
    {
        $loggedIn = is_user_logged_in() && get_current_user_id() > 0;
        if ($loggedIn) {
            return $this->requireLoginOrJwt($request);
        }

        return $this->requireGuestCheckoutNonce($request);
    }

    /**
     * Confirm may run for guests returning from gateways. We allow that if:
     * - logged in (default), OR
     * - guest checkout enabled AND request includes a matching public_token.
     *
     * @return bool|\WP_Error
     */
    public function requireCheckoutConfirmAuth(WP_REST_Request $request)
    {
        $loggedIn = is_user_logged_in() && get_current_user_id() > 0;
        if ($loggedIn) {
            return $this->requireLoginOrJwt($request);
        }

        return $this->requireGuestCheckoutNonce($request);
    }

    public function createSession(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        // Never trust client-provided course IDs for checkout. Use server cart.
        $course_ids = array_values(array_filter(array_map('intval', CartStorage::getCourseIds())));
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $gateway = isset($params['gateway']) ? sanitize_key((string) $params['gateway']) : '';
        $coupon = isset($params['coupon_code']) ? trim(sanitize_text_field((string) $params['coupon_code'])) : '';

        $raw_bundle = (int) CartStorage::getBundleId();
        if (!(bool) apply_filters('sikshya_checkout_bundle_cart_supported', false) && $raw_bundle > 0) {
            CartStorage::setBundleIdOnly(0);
            $raw_bundle = 0;
        }
        $bundle_id = (int) apply_filters('sikshya_checkout_resolve_bundle_id', $raw_bundle, $course_ids);

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
        $is_guest = $uid <= 0;
        $guest_email = isset($params['guest_email']) ? sanitize_email((string) $params['guest_email']) : '';
        $guest_name = isset($params['guest_name']) ? sanitize_text_field((string) $params['guest_name']) : '';
        // Billing details are optional and can be collected via Dynamic Fields (Growth).
        $billing = isset($params['billing']) && is_array($params['billing']) ? $params['billing'] : [];
        $dynamic_fields = isset($params['dynamic_fields']) && is_array($params['dynamic_fields']) ? $params['dynamic_fields'] : [];
        $country_raw = isset($billing['country']) ? sanitize_text_field((string) $billing['country']) : '';
        $country_iso = function_exists('sikshya_normalize_billing_country_to_iso')
            ? sikshya_normalize_billing_country_to_iso($country_raw)
            : $country_raw;

        $billing_clean = [
            'phone' => isset($billing['phone']) ? sanitize_text_field((string) $billing['phone']) : '',
            'address_1' => isset($billing['address_1']) ? sanitize_text_field((string) $billing['address_1']) : '',
            'address_2' => isset($billing['address_2']) ? sanitize_text_field((string) $billing['address_2']) : '',
            'city' => isset($billing['city']) ? sanitize_text_field((string) $billing['city']) : '',
            'state' => isset($billing['state']) ? sanitize_text_field((string) $billing['state']) : '',
            'postcode' => isset($billing['postcode']) ? sanitize_text_field((string) $billing['postcode']) : '',
            'country' => $country_iso,
        ];
        if ($is_guest) {
            if (!\Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('enable_guest_checkout', true))) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'login_required', 'message' => __('Please sign in to continue checkout.', 'sikshya')],
                    403
                );
            }
            if ($guest_email === '' || !is_email($guest_email)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'invalid_email', 'message' => __('Please enter a valid email to continue.', 'sikshya')],
                    400
                );
            }
            if (email_exists($guest_email)) {
                return new WP_REST_Response(
                    [
                        'ok' => false,
                        'code' => 'email_exists',
                        'message' => __('An account already exists for this email. Please sign in to continue checkout.', 'sikshya'),
                    ],
                    409
                );
            }
        }
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

            if ($is_guest) {
                // Attach guest identity to order so we can auto-create an account at fulfillment time.
                $repo = new OrderRepository();
                $row = $repo->findById((int) $order['order_id']);
                $existing = [];
                if ($row && isset($row->meta) && is_string($row->meta) && $row->meta !== '') {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
                $existing['guest'] = [
                    'email' => $guest_email,
                    'name' => $guest_name,
                ];
                // Keep backward compatibility: store billing meta if client submits it.
                if (array_filter($billing_clean, static fn($v): bool => (string) $v !== '') !== []) {
                    $existing['billing'] = $billing_clean;
                }
                $repo->updateOrder((int) $order['order_id'], ['meta' => $existing]);
            } elseif (array_filter($billing_clean, static fn($v): bool => (string) $v !== '') !== []) {
                $repo = new OrderRepository();
                $row = $repo->findById((int) $order['order_id']);
                $existing = [];
                if ($row && isset($row->meta) && is_string($row->meta) && $row->meta !== '') {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
                $existing['billing'] = $billing_clean;
                $repo->updateOrder((int) $order['order_id'], ['meta' => $existing]);
            }

            /**
             * Allow add-ons to validate + sanitize dynamic checkout fields.
             *
             * Return value:
             * - WP_Error to reject checkout
             * - array{schema?: array<int, array<string, mixed>>, values?: array<string, mixed>} to persist
             * - null/false/[] to ignore
             *
             * @param array<string, mixed> $dynamic_fields Raw submitted values from the client.
             * @param WP_REST_Request      $request
             * @param int                 $order_id
             * @param int                 $user_id
             * @param bool                $is_guest
             */
            $df_result = apply_filters(
                'sikshya_checkout_dynamic_fields_validate',
                $dynamic_fields,
                $request,
                (int) $order['order_id'],
                (int) $uid,
                (bool) $is_guest
            );
            if (is_wp_error($df_result)) {
                return new WP_REST_Response(
                    [
                        'ok' => false,
                        'code' => (string) $df_result->get_error_code(),
                        'message' => (string) $df_result->get_error_message(),
                        'data' => $df_result->get_error_data(),
                    ],
                    400
                );
            }
            if (is_array($df_result) && ($df_result !== [])) {
                $repo = new OrderRepository();
                $row = $repo->findById((int) $order['order_id']);
                $existing = [];
                if ($row && isset($row->meta) && is_string($row->meta) && $row->meta !== '') {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
                $existing['dynamic_fields'] = [
                    'schema_version' => 1,
                    'schema' => isset($df_result['schema']) && is_array($df_result['schema']) ? $df_result['schema'] : [],
                    'values' => isset($df_result['values']) && is_array($df_result['values']) ? $df_result['values'] : [],
                ];
                $repo->updateOrder((int) $order['order_id'], ['meta' => $existing]);
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
            } elseif ($gateway === 'paypal') {
                // If PayPal Simple (Standard) mode is selected, the return flow is IPN-based.
                // Keep the order on-hold until IPN verifies payment, then fulfill.
                $settings = $this->plugin->getService('settings');
                $mode = '';
                if (is_object($settings) && method_exists($settings, 'getSetting')) {
                    $mode = sanitize_key((string) $settings->getSetting('paypal_integration_mode', 'advanced'));
                }
                if ($mode === 'simple') {
                    (new OrderRepository())->updateOrder((int) $order['order_id'], ['status' => 'on-hold']);
                }
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

        // Quote totals based on server cart; never trust client-provided course IDs.
        $course_ids = array_values(array_filter(array_map('intval', CartStorage::getCourseIds())));
        $coupon = isset($params['coupon_code']) ? trim(sanitize_text_field((string) $params['coupon_code'])) : '';

        if ($course_ids === []) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_params', 'message' => __('Invalid parameters.', 'sikshya')],
                400
            );
        }

        $uid = get_current_user_id();

        $raw_bundle = (int) CartStorage::getBundleId();
        if (!(bool) apply_filters('sikshya_checkout_bundle_cart_supported', false) && $raw_bundle > 0) {
            CartStorage::setBundleIdOnly(0);
            $raw_bundle = 0;
        }
        $bundle_id = (int) apply_filters('sikshya_checkout_resolve_bundle_id', $raw_bundle, $course_ids);

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
        $public_token = isset($params['public_token']) ? sanitize_text_field((string) $params['public_token']) : '';
        $gateway = isset($params['gateway']) ? sanitize_key((string) $params['gateway']) : '';
        $checkout_session_id = isset($params['checkout_session_id']) ? (string) $params['checkout_session_id'] : '';
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
        $uid = get_current_user_id();
        $is_guest = $uid <= 0;

        if (!$order) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'forbidden', 'message' => __('Invalid order.', 'sikshya')],
                403
            );
        }

        if (!$is_guest) {
            if ((int) $order->user_id !== $uid) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'forbidden', 'message' => __('Invalid order.', 'sikshya')],
                    403
                );
            }
        } else {
            $tok = isset($order->public_token) && is_string($order->public_token) ? (string) $order->public_token : '';
            if ($tok === '' || $public_token === '' || !hash_equals($tok, $public_token)) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'forbidden', 'message' => __('Invalid order.', 'sikshya')],
                    403
                );
            }
        }

        // Integrity guard: only allow confirming orders still awaiting payment.
        $status = isset($order->status) ? (string) $order->status : '';
        if (!in_array($status, ['pending', 'on-hold'], true)) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'order_not_confirmable', 'message' => __('Order is not awaiting payment.', 'sikshya')],
                400
            );
        }

        $transaction_id = '';
        $gateway_snapshot = null;

        // Subscription-mode confirm: gateways that implement recurring billing should verify/capture
        // their subscription activation here. Core one-time logic runs only when not subscription checkout.
        $metaDecoded = [];
        if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
            $decoded = json_decode((string) $order->meta, true);
            if (is_array($decoded)) {
                $metaDecoded = $decoded;
            }
        }
        $is_sub_checkout = is_array($metaDecoded)
            && isset($metaDecoded['checkout_mode'])
            && (string) $metaDecoded['checkout_mode'] === 'subscription'
            && isset($metaDecoded['subscription_plan_id'])
            && (int) $metaDecoded['subscription_plan_id'] > 0;
        if ($is_sub_checkout) {
            $handled = apply_filters('sikshya_checkout_confirm_subscription_gateway', false, $gateway, $order, $params);
            if ($handled instanceof \WP_REST_Response) {
                return $handled;
            }
            if ($handled !== true) {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'subscription_gateway_not_supported', 'message' => __('This gateway is not configured for recurring subscriptions.', 'sikshya')],
                    400
                );
            }
            // Handlers may update gateway_intent_id / meta; reload before persisting payment snapshot.
            $refreshed = $orders->findById($order_id);
            if ($refreshed) {
                $order = $refreshed;
            }
            // Subscription gateway has verified activation; proceed to fulfillment + redirect below.
        } else
        if ($gateway === 'stripe') {
            if ($pi !== '') {
                $intent = $this->checkoutService()->retrieveStripePaymentIntent($pi);
                $transaction_id = $pi;
                $gateway_snapshot = is_array($intent) ? $intent : null;
                $st = is_array($intent) ? (string) ($intent['status'] ?? '') : '';
                if ($st !== 'succeeded') {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'payment_pending', 'message' => __('Stripe payment not completed.', 'sikshya')],
                        400
                    );
                }
                $md = is_array($intent) && isset($intent['metadata']) && is_array($intent['metadata']) ? $intent['metadata'] : [];
                $refOrder = isset($md['order_id']) ? (int) $md['order_id'] : 0;
                if ($refOrder > 0 && $refOrder !== (int) $order->id) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'intent_mismatch', 'message' => __('Stripe intent does not match this order.', 'sikshya')],
                        400
                    );
                }
                $piCur = is_array($intent) && isset($intent['currency']) ? strtoupper((string) $intent['currency']) : '';
                $piAmt = is_array($intent) && isset($intent['amount']) ? (int) $intent['amount'] : 0;
                $expected_minor = \Sikshya\Commerce\CheckoutService::toMinorUnits((float) $order->total, (string) $order->currency);
                if ($piCur !== '' && $piCur !== strtoupper((string) $order->currency)) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                        400
                    );
                }
                if ($piAmt > 0 && $expected_minor > 0 && $piAmt !== $expected_minor) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                        400
                    );
                }
            } elseif ($checkout_session_id !== '') {
                $sess = $this->checkoutService()->retrieveStripeCheckoutSession($checkout_session_id);
                $transaction_id = $checkout_session_id;
                $gateway_snapshot = is_array($sess) ? $sess : null;
                $payStatus = is_array($sess) ? (string) ($sess['payment_status'] ?? '') : '';
                if ($payStatus !== 'paid') {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'payment_pending', 'message' => __('Stripe payment not completed.', 'sikshya')],
                        400
                    );
                }
                $refOrder = 0;
                if (is_array($sess) && isset($sess['metadata']['order_id'])) {
                    $refOrder = (int) $sess['metadata']['order_id'];
                }
                if ($refOrder <= 0 && is_array($sess) && isset($sess['client_reference_id'])) {
                    $refOrder = (int) $sess['client_reference_id'];
                }
                if ($refOrder > 0 && $refOrder !== (int) $order->id) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'session_mismatch', 'message' => __('Stripe session does not match this order.', 'sikshya')],
                        400
                    );
                }
                $sessCur = is_array($sess) && isset($sess['currency']) ? strtoupper((string) $sess['currency']) : '';
                $sessTotal = is_array($sess) && isset($sess['amount_total']) ? (int) $sess['amount_total'] : 0;
                $expected_minor = \Sikshya\Commerce\CheckoutService::toMinorUnits((float) $order->total, (string) $order->currency);
                if ($sessCur !== '' && $sessCur !== strtoupper((string) $order->currency)) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'currency_mismatch', 'message' => __('Currency mismatch for this order.', 'sikshya')],
                        400
                    );
                }
                if ($sessTotal > 0 && $expected_minor > 0 && $sessTotal !== $expected_minor) {
                    return new WP_REST_Response(
                        ['ok' => false, 'code' => 'amount_mismatch', 'message' => __('Payment amount does not match this order.', 'sikshya')],
                        400
                    );
                }
            } elseif ($pi !== '') {
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
            } else {
                return new WP_REST_Response(
                    ['ok' => false, 'code' => 'missing_stripe', 'message' => __('Missing Stripe session or payment intent.', 'sikshya')],
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
            $transaction_id = $paypal_order;
            $gateway_snapshot = is_array($cap) ? $cap : null;
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
            $transaction_id = $pid;
            $gateway_snapshot = is_array($m) ? $m : null;
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
            $transaction_id = $ref;
            $gateway_snapshot = is_array($data) ? $data : null;
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
            $transaction_id = $lid;
            $gateway_snapshot = is_array($link) ? $link : null;
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

        // Persist payment identifiers + gateway response for admin "Payment Details".
        try {
            $meta = [];
            if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
                $decoded = json_decode($order->meta, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $meta['payment'] = [
                'gateway' => $gateway,
                'transaction_id' => $transaction_id !== '' ? $transaction_id : (string) ($order->gateway_intent_id ?? ''),
                'verified_at' => current_time('mysql'),
                'gateway_response' => $gateway_snapshot,
            ];

            $patch = ['meta' => $meta];
            if ($transaction_id !== '') {
                $patch['gateway_intent_id'] = $transaction_id;
            }
            (new OrderRepository())->updateOrder($order_id, $patch);
        } catch (\Throwable $e) {
            // Ignore persistence failures; fulfillment can proceed.
        }

        // Payment verified. For guest orders, create/link a student account now.
        if ($is_guest && (int) ($order->user_id ?? 0) <= 0) {
            $new_uid = $this->ensureGuestOrderUser($order_id, $order);
            if ($new_uid <= 0) {
                return new WP_REST_Response(
                    [
                        'ok' => false,
                        'code' => 'account_required',
                        'message' => __('An account is required for this email. Please sign in to continue.', 'sikshya'),
                    ],
                    409
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

    /**
     * Ensure a guest order is linked to a real WP user.
     *
     * @param object $order
     */
    private function ensureGuestOrderUser(int $order_id, object $order): int
    {
        $meta = [];
        if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
            $decoded = json_decode($order->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $guest = isset($meta['guest']) && is_array($meta['guest']) ? $meta['guest'] : [];
        $email = isset($guest['email']) ? sanitize_email((string) $guest['email']) : '';
        $name = isset($guest['name']) ? sanitize_text_field((string) $guest['name']) : '';
        $billing = isset($meta['billing']) && is_array($meta['billing']) ? $meta['billing'] : [];
        if ($email === '' || !is_email($email)) {
            return 0;
        }

        $existing = (int) email_exists($email);
        if ($existing > 0) {
            // Never auto-link guest orders to an existing account — require the user to sign in.
            return 0;
        } else {
            $base = sanitize_user((string) preg_replace('/@.*/', '', $email), true);
            if ($base === '') {
                $base = 'student';
            }
            $username = $base;
            $i = 1;
            while (username_exists($username)) {
                $i++;
                $username = $base . $i;
                if ($i > 50) {
                    $username = $base . wp_rand(1000, 999999);
                    break;
                }
            }
            $password = wp_generate_password(20, true, true);
            $new_id = wp_create_user($username, $password, $email);
            if (is_wp_error($new_id) || (int) $new_id <= 0) {
                return 0;
            }
            $uid = (int) $new_id;
            $u = get_userdata($uid);
            if ($u) {
                $u->set_role('sikshya_student');
            }
            if ($name !== '') {
                wp_update_user(['ID' => $uid, 'display_name' => $name]);
            }
            wp_new_user_notification($uid, null, 'user');
        }

        // Persist billing fields for future purchases (works for new or existing users).
        if ($uid > 0 && is_array($billing)) {
            $map = [
                'phone' => '_sikshya_billing_phone',
                'address_1' => '_sikshya_billing_address_1',
                'address_2' => '_sikshya_billing_address_2',
                'city' => '_sikshya_billing_city',
                'state' => '_sikshya_billing_state',
                'postcode' => '_sikshya_billing_postcode',
                'country' => '_sikshya_billing_country',
            ];
            foreach ($map as $k => $metaKey) {
                if (!array_key_exists($k, $billing)) {
                    continue;
                }
                $val = sanitize_text_field((string) $billing[$k]);
                if ($val !== '') {
                    update_user_meta($uid, $metaKey, $val);
                }
            }
        }

        (new OrderRepository())->updateOrder($order_id, ['user_id' => $uid]);
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid, true, is_ssl());
        CartStorage::adoptGuestCartForUser($uid);

        return $uid;
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
