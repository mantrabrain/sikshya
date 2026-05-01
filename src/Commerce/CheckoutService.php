<?php

namespace Sikshya\Commerce;

use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CouponRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Licensing\Pro;
use Sikshya\Services\Settings;

/**
 * One-time checkout: offline (manual), Stripe Checkout Session, PayPal order creation, etc.
 *
 * @package Sikshya\Commerce
 */
final class CheckoutService
{
    /**
     * Some currencies (JPY, KRW, VND, etc.) have no minor units.
     * Gateways expecting integer "minor units" must NOT multiply by 100 for these.
     *
     * @see https://stripe.com/docs/currencies#zero-decimal
     * @return array<string, true>
     */
    private static function zeroDecimalCurrencies(): array
    {
        return [
            'BIF' => true,
            'CLP' => true,
            'DJF' => true,
            'GNF' => true,
            'JPY' => true,
            'KMF' => true,
            'KRW' => true,
            'MGA' => true,
            'PYG' => true,
            'RWF' => true,
            'UGX' => true,
            'VND' => true,
            'VUV' => true,
            'XAF' => true,
            'XOF' => true,
            'XPF' => true,
        ];
    }

    private static function currencyMinorUnitFactor(string $currency): int
    {
        $cur = strtoupper(trim($currency));
        if ($cur === '' || strlen($cur) !== 3) {
            return 100;
        }
        return isset(self::zeroDecimalCurrencies()[$cur]) ? 1 : 100;
    }

    /**
     * Convert major-unit amount to gateway minor-units (integer), respecting zero-decimal currencies.
     */
    public static function toMinorUnits(float $amount, string $currency): int
    {
        $factor = self::currencyMinorUnitFactor($currency);
        return (int) round(max(0.0, $amount) * $factor);
    }

    /**
     * Convert gateway minor-units back to major units.
     */
    public static function fromMinorUnits(int $minor, string $currency): float
    {
        $factor = self::currencyMinorUnitFactor($currency);
        return round(((float) $minor) / max(1, $factor), 2);
    }

    private Plugin $plugin;

    private OrderRepository $orders;

    private CouponRepository $coupons;

    public function __construct(Plugin $plugin, OrderRepository $orders, CouponRepository $coupons)
    {
        $this->plugin = $plugin;
        $this->orders = $orders;
        $this->coupons = $coupons;
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
     * @return array{order_id: int, public_token: string, subtotal: float, discount: float, total: float, currency: string}
     */
    public function createPendingOrder(int $user_id, int $course_id, string $coupon_code = ''): array
    {
        return $this->createPendingOrderForCourses($user_id, [$course_id], $coupon_code);
    }

    /**
     * Price lines + subtotal + optional coupon (no DB). Used for checkout quote UI.
     *
     * @param array<int, int> $course_ids
     * @param int               $bundle_id Optional Pro bundle: pricing may be replaced via {@see 'sikshya_bundle_pricing_for_cart'}.
     * @param int               $user_id   Logged-in learner (0 if unknown). Passed to {@see 'sikshya_coupon_blocked_message'} and discount filters.
     * @return array{line_amounts: array<int, float>, subtotal: float, discount: float, total: float, currency: string, coupon_id: int|null}
     */
    public function computePricingForCourses(array $course_ids, string $coupon_code = '', int $bundle_id = 0, int $user_id = 0): array
    {
        $course_ids = array_values(array_unique(array_filter(array_map('intval', $course_ids))));
        if ($course_ids === []) {
            throw new \InvalidArgumentException(__('Invalid checkout parameters.', 'sikshya'));
        }

        $currency = strtoupper((string) $this->settings()->getSetting('currency', 'USD'));
        if (strlen($currency) !== 3) {
            $currency = 'USD';
        }

        /*
         * Subscription-only checkout mode (Pro add-on):
         * - the cart must contain only subscription courses
         * - all courses must require the same plan id
         * - pricing is derived from the plan, not per-course price
         *
         * This keeps core unaware of plans/subscriptions tables: the add-on supplies pricing via a filter.
         */
        $subPricing = apply_filters('sikshya_checkout_subscription_pricing', null, $course_ids, $currency);
        if (is_array($subPricing) && isset($subPricing['line_amounts'], $subPricing['subtotal']) && is_array($subPricing['line_amounts'])) {
            $line_amounts = $subPricing['line_amounts'];
            $subtotal = round(max(0.0, (float) $subPricing['subtotal']), 2);
        } else {
        $line_amounts = [];
        $subtotal = 0.0;
        foreach ($course_ids as $cid) {
            $line = $this->effectivePriceForCourse($cid);
            $line = round(max(0.0, $line), 2);
            $line_amounts[$cid] = $line;
            $subtotal += $line;
        }
        $subtotal = round($subtotal, 2);
        }

        if ($bundle_id > 0) {
            /*
             * Replace per-course line amounts with bundle pricing (Pro). Return null to keep summed course prices.
             */
            $adjusted = apply_filters('sikshya_bundle_pricing_for_cart', null, $bundle_id, $course_ids, $currency);
            if (is_array($adjusted) && isset($adjusted['line_amounts'], $adjusted['subtotal']) && is_array($adjusted['line_amounts'])) {
                $line_amounts = $adjusted['line_amounts'];
                $subtotal = round(max(0.0, (float) $adjusted['subtotal']), 2);
            }
        }

        $discount = 0.00;
        $total = $subtotal;
        $coupon_id = null;
        $coupon_code = trim($coupon_code);

        if ($coupon_code !== '' && !Settings::isTruthy($this->settings()->getSetting('enable_coupons', false))) {
            $coupon_code = '';
        }

        if ($coupon_code !== '') {
            $coupon = $this->coupons->findActiveByCode($coupon_code);
            if ($coupon) {
                /*
                 * Allow Pro / extensions to block a coupon for this cart (min spend, course scope, etc.).
                 * Return a non-empty string to block checkout with that message.
                 *
                 * Fifth argument: logged-in buyer id when known (checkout); `0` when unavailable.
                 */
                $blocked = apply_filters('sikshya_coupon_blocked_message', '', $coupon, $course_ids, $subtotal, $user_id);
                if (is_string($blocked) && $blocked !== '') {
                    throw new \InvalidArgumentException($blocked);
                }

                [$discount, $total] = $this->coupons->applyToAmount($coupon, $subtotal);
                $discount = (float) apply_filters('sikshya_coupon_discount_amount', $discount, $coupon, $course_ids, $subtotal, $user_id);
                $discount = round(max(0.0, min($subtotal, $discount)), 2);
                $total = max(0.0, round($subtotal - $discount, 2));
                $coupon_id = (int) $coupon->id;
            } else {
                throw new \InvalidArgumentException(__('Invalid or expired coupon.', 'sikshya'));
            }
        }

        return [
            'line_amounts' => $line_amounts,
            'subtotal' => $subtotal,
            'discount' => round((float) $discount, 2),
            'total' => round((float) $total, 2),
            'currency' => $currency,
            'coupon_id' => $coupon_id,
        ];
    }

    /**
     * Cart totals preview (no order row). Same math as {@see createPendingOrderForCourses}.
     *
     * @param array<int, int> $course_ids
     * @param int               $bundle_id Optional bundle id (see {@see computePricingForCourses}).
     * @return array{subtotal: float, discount: float, total: float, currency: string, formatted: array{subtotal: string, discount: string, total: string}}
     */
    public function quoteTotalsForCourses(int $user_id, array $course_ids, string $coupon_code = '', int $bundle_id = 0): array
    {
        if ($user_id <= 0 && !Settings::isTruthy(Settings::get('enable_guest_checkout', true))) {
            throw new \InvalidArgumentException(__('Please sign in to continue checkout.', 'sikshya'));
        }

        $this->assertCheckoutOrderEligibility($user_id, $course_ids);

        $p = $this->computePricingForCourses($course_ids, $coupon_code, $bundle_id, $user_id);
        $cur = $p['currency'];

        return [
            'subtotal' => $p['subtotal'],
            'discount' => $p['discount'],
            'total' => $p['total'],
            'currency' => $cur,
            'formatted' => [
                'subtotal' => number_format_i18n($p['subtotal'], 2) . ' ' . $cur,
                'discount' => $p['discount'] > 0.00001
                    ? '-' . number_format_i18n($p['discount'], 2) . ' ' . $cur
                    : '',
                'total' => number_format_i18n($p['total'], 2) . ' ' . $cur,
            ],
        ];
    }

    /**
     * One order with multiple course line items (cart checkout).
     *
     * @param array<int, int> $course_ids
     * @param int               $bundle_id Optional bundle id for order meta / pricing.
     * @return array{order_id: int, public_token: string, subtotal: float, discount: float, total: float, currency: string}
     */
    public function createPendingOrderForCourses(int $user_id, array $course_ids, string $coupon_code = '', int $bundle_id = 0): array
    {
        if ($user_id <= 0 && !Settings::isTruthy(Settings::get('enable_guest_checkout', true))) {
            throw new \InvalidArgumentException(__('Please sign in to continue checkout.', 'sikshya'));
        }

        $this->assertCheckoutOrderEligibility($user_id, $course_ids);

        $p = $this->computePricingForCourses($course_ids, $coupon_code, $bundle_id, $user_id);
        $line_amounts = $p['line_amounts'];
        $subtotal = $p['subtotal'];
        $discount = $p['discount'];
        $total = $p['total'];
        $currency = $p['currency'];
        $coupon_id = $p['coupon_id'];

        $meta = ['course_ids' => array_keys($line_amounts)];
        if ($bundle_id > 0) {
            $meta['bundle_id'] = $bundle_id;
        }
        /*
         * Allow add-ons to attach checkout-mode metadata (eg. subscription plan id).
         */
        $meta = apply_filters('sikshya_checkout_order_meta', $meta, array_keys($line_amounts), $currency);

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
                'meta' => $meta,
            ]
        );

        if ($order_id <= 0) {
            throw new \RuntimeException(__('Could not create order.', 'sikshya'));
        }

        foreach ($line_amounts as $cid => $amt) {
            $this->orders->addOrderItem($order_id, $cid, 1, $amt, $amt);
        }

        if ($coupon_id) {
            $this->coupons->incrementUsedCount($coupon_id);
            $this->coupons->recordRedemption($coupon_id, $user_id, $order_id);
        }

        $public_token = $this->orders->ensurePublicToken($order_id);

        return [
            'order_id' => $order_id,
            'public_token' => $public_token,
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
     * Bank transfer / cash / invoice — no external API. Always available unless disabled in settings.
     */
    public function isOfflinePaymentEnabled(): bool
    {
        $v = $this->settings()->getSetting('enable_offline_payment', '1');

        return $this->isTruthySetting($v);
    }

    public function isPayPalEnabled(): bool
    {
        return $this->isTruthySetting($this->settings()->getSetting('enable_paypal_payment', '0'));
    }

    public function isStripeEnabled(): bool
    {
        return Pro::isActive() && $this->isTruthySetting($this->settings()->getSetting('enable_stripe_payment', '0'));
    }

    public function isBankTransferEnabled(): bool
    {
        if (!Pro::isActive()) {
            return false;
        }

        return $this->isTruthySetting($this->settings()->getSetting('enable_bank_transfer_payment', '0'))
            && trim((string) $this->settings()->getSetting('bank_transfer_instructions', '')) !== '';
    }

    public function isMollieEnabled(): bool
    {
        if (!Pro::isActive()) {
            return false;
        }

        return $this->isTruthySetting($this->settings()->getSetting('enable_mollie_payment', '0'))
            && (string) $this->settings()->getSetting('mollie_api_key', '') !== '';
    }

    public function isPaystackEnabled(): bool
    {
        if (!Pro::isActive()) {
            return false;
        }

        return $this->isTruthySetting($this->settings()->getSetting('enable_paystack_payment', '0'))
            && (string) $this->settings()->getSetting('paystack_public_key', '') !== ''
            && (string) $this->settings()->getSetting('paystack_secret_key', '') !== '';
    }

    public function isRazorpayEnabled(): bool
    {
        if (!Pro::isActive()) {
            return false;
        }

        return $this->isTruthySetting($this->settings()->getSetting('enable_razorpay_payment', '0'))
            && (string) $this->settings()->getSetting('razorpay_key_id', '') !== ''
            && (string) $this->settings()->getSetting('razorpay_key_secret', '') !== '';
    }

    /**
     * When true, choosing offline immediately enrolls (honor system). When false, order stays on-hold until an admin marks it paid.
     */
    public function isOfflineAutoFulfillEnabled(): bool
    {
        return $this->isTruthySetting($this->settings()->getSetting('offline_payment_auto_fulfill', false));
    }

    /**
     * @param mixed $v
     */
    private function isTruthySetting($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /**
     * @return array{client_secret?: string, payment_intent_id?: string, approval_url?: string, paypal_order_id?: string, offline?: bool}
     */
    public function startGatewaySession(int $order_id, string $gateway): array
    {
        $order = $this->orders->findById($order_id);
        if (!$order || $order->status !== 'pending') {
            throw new \InvalidArgumentException(__('Order not available.', 'sikshya'));
        }

        $gateway = strtolower($gateway);

        // Subscription-mode checkout: allow Pro/addons to create true recurring subscriptions
        // for gateways before core falls back to one-time payment sessions.
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
            $payload = apply_filters('sikshya_checkout_start_gateway_subscription_session', null, $order, $gateway, $order_id, $this);
            if (is_array($payload)) {
                return $payload;
            }
        }

        if ($gateway === 'offline') {
            if (!$this->isOfflinePaymentEnabled()) {
                throw new \RuntimeException(__('Offline payment is disabled.', 'sikshya'));
            }
            $this->orders->updateOrder($order_id, [
                'gateway' => 'offline',
                'gateway_intent_id' => 'offline-' . $order_id,
            ]);

            return ['offline' => true];
        }

        if ($gateway === 'bank_transfer') {
            if (!$this->isBankTransferEnabled()) {
                throw new \RuntimeException(__('Bank transfer is not available.', 'sikshya'));
            }
            $this->orders->updateOrder($order_id, [
                'gateway' => 'bank_transfer',
                'gateway_intent_id' => 'bank-transfer-' . $order_id,
            ]);

            return ['offline' => true, 'bank_transfer' => true];
        }

        $this->orders->updateOrder($order_id, ['gateway' => $gateway]);

        if ($gateway === 'stripe') {
            if (!$this->isStripeEnabled()) {
                throw new \RuntimeException(__('Stripe is not available on this site.', 'sikshya'));
            }
            return $this->createStripeCheckoutSession($order);
        }

        if ($gateway === 'paypal') {
            if (!$this->isPayPalEnabled()) {
                throw new \RuntimeException(__('PayPal is disabled.', 'sikshya'));
            }
            $mode = sanitize_key((string) $this->settings()->getSetting('paypal_integration_mode', 'advanced'));
            if (!in_array($mode, ['simple', 'advanced'], true)) {
                $mode = 'advanced';
            }
            // If email is configured, use simple flow even if mode is still "advanced".
            $email = trim((string) $this->settings()->getSetting('paypal_email', ''));
            if ($email !== '' && is_email($email)) {
                $mode = 'simple';
            }
            if ($mode === 'simple') {
                return $this->createPayPalStandardRedirect($order);
            }

            return $this->createPayPalOrder($order);
        }

        if ($gateway === 'mollie') {
            if (!$this->isMollieEnabled()) {
                throw new \RuntimeException(__('Mollie is not available.', 'sikshya'));
            }

            return $this->createMolliePayment($order);
        }

        if ($gateway === 'paystack') {
            if (!$this->isPaystackEnabled()) {
                throw new \RuntimeException(__('Paystack is not available.', 'sikshya'));
            }

            return $this->createPaystackTransaction($order);
        }

        if ($gateway === 'razorpay') {
            if (!$this->isRazorpayEnabled()) {
                throw new \RuntimeException(__('Razorpay is not available.', 'sikshya'));
            }

            return $this->createRazorpayPaymentLink($order);
        }

        /**
         * Pro/addons can start sessions for additional gateways.
         *
         * Return an array payload like Stripe/PayPal, or null to ignore.
         */
        $payload = apply_filters('sikshya_checkout_start_gateway_session', null, $order, $gateway, $order_id, $this);
        if (is_array($payload)) {
            return $payload;
        }

        if (in_array($gateway, ['square', 'authorize_net'], true)) {
            throw new \RuntimeException(
                __(
                    'Checkout for Square and Authorize.Net is not wired in core yet. Use another gateway or extend via sikshya_checkout_start_gateway_session.',
                    'sikshya'
                )
            );
        }

        throw new \InvalidArgumentException(__('Unsupported gateway.', 'sikshya'));
    }

    /**
     * Stripe Checkout hosted page (redirect). Matches the redirect pattern used for PayPal / Mollie.
     */
    private function createStripeCheckoutSession(object $order): array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '') {
            throw new \RuntimeException(__('Stripe is not configured.', 'sikshya'));
        }

        $amount = (float) $order->total;
        $currency = strtolower((string) $order->currency);
        if (strlen($currency) !== 3) {
            $currency = 'usd';
        }
        $minor = self::toMinorUnits($amount, $currency);

        $checkoutBase = PublicPageUrls::url('checkout');

        // Guests return from Stripe without a WP session. Include public_token so
        // the checkout return page can confirm payment and redirect to receipt.
        $publicToken = isset($order->public_token) && is_string($order->public_token) ? (string) $order->public_token : '';
        if ($publicToken === '') {
            $publicToken = (new \Sikshya\Database\Repositories\OrderRepository())->ensurePublicToken((int) $order->id);
        }

        // Stripe replaces the literal `{CHECKOUT_SESSION_ID}` in success_url; do not URL-encode that placeholder.
        $successUrl = add_query_arg(
            [
                'sikshya_stripe_return' => '1',
                'order_id' => (string) $order->id,
                'public_token' => $publicToken,
            ],
            $checkoutBase
        );
        $successUrl .= (strpos($successUrl, '?') !== false ? '&' : '?') . 'checkout_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = add_query_arg(
            [
                'sikshya_stripe_cancel' => '1',
                'order_id' => (string) $order->id,
                'public_token' => $publicToken,
            ],
            $checkoutBase
        );

        $user = wp_get_current_user();
        $email = ($user && $user->exists()) ? (string) $user->user_email : '';

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
        $sub_plan_id = $is_sub_checkout ? (int) $metaDecoded['subscription_plan_id'] : 0;
        $interval_unit = $is_sub_checkout && isset($metaDecoded['subscription_interval_unit'])
            ? sanitize_key((string) $metaDecoded['subscription_interval_unit'])
            : '';
        if ($is_sub_checkout && $interval_unit === '') {
            // Default: Pro plans use day/week/month/year; if missing, assume monthly.
            $interval_unit = 'month';
        }
        $allowed_intervals = ['day', 'week', 'month', 'year'];
        if ($is_sub_checkout && !in_array($interval_unit, $allowed_intervals, true)) {
            $interval_unit = 'month';
        }

        $body = [
            'mode' => $is_sub_checkout ? 'subscription' : 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order->id,
            'metadata[order_id]' => (string) $order->id,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => (string) $minor,
            'line_items[0][price_data][product_data][name]' => sprintf(
                /* translators: %d: order id */
                __('Sikshya order %d', 'sikshya'),
                (int) $order->id
            ),
            'line_items[0][quantity]' => '1',
        ];
        if ($is_sub_checkout) {
            $body['metadata[checkout_mode]'] = 'subscription';
            $body['metadata[subscription_plan_id]'] = (string) $sub_plan_id;
            $body['line_items[0][price_data][recurring][interval]'] = $interval_unit;
        }
        if ($email !== '' && is_email($email)) {
            $body['customer_email'] = $email;
        }

        $response = wp_remote_post(
            'https://api.stripe.com/v1/checkout/sessions',
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
        if ($code >= 400 || !is_array($json) || empty($json['id']) || empty($json['url'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? (string) $json['error']['message'] : __('Stripe Checkout session could not be created.', 'sikshya');
            throw new \RuntimeException($msg);
        }

        $sessionId = (string) $json['id'];
        $this->orders->updateOrder((int) $order->id, ['gateway_intent_id' => $sessionId]);

        return [
            'approval_url' => (string) $json['url'],
            'stripe_checkout_session_id' => $sessionId,
        ];
    }

    /**
     * Retrieve a Stripe subscription object.
     *
     * @return array<string, mixed>|null
     */
    public function retrieveStripeSubscription(string $subscription_id): ?array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '' || $subscription_id === '') {
            return null;
        }

        $res = wp_remote_get(
            'https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscription_id),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                ],
            ]
        );
        if (is_wp_error($res)) {
            return null;
        }
        $json = json_decode((string) wp_remote_retrieve_body($res), true);
        return is_array($json) ? $json : null;
    }

    /**
     * PayPal REST host: uses global {@see enable_test_mode} only.
     */
    private function paypalRestBase(): string
    {
        $test = $this->settings()->getSetting('enable_test_mode', true);
        $is_test = $test === true || $test === 1 || $test === '1' || $test === 'true' || $test === 'yes' || $test === 'on';

        return $is_test ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    /**
     * Public accessor for PayPal REST API base (sandbox vs live).
     */
    public function paypalRestBasePublic(): string
    {
        return $this->paypalRestBase();
    }

    /**
     * Create a PayPal REST access token using configured credentials.
     */
    public function paypalAccessToken(): string
    {
        $client_id = (string) $this->settings()->getSetting('paypal_client_id', '');
        $secret = (string) $this->settings()->getSetting('paypal_secret', '');
        $base = $this->paypalRestBase();
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

        return $access;
    }

    private function createPayPalOrder(object $order): array
    {
        $client_id = (string) $this->settings()->getSetting('paypal_client_id', '');
        $secret = (string) $this->settings()->getSetting('paypal_secret', '');
        $base = $this->paypalRestBase();

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

        // Guests return from PayPal without a WP session. Include public_token so
        // the checkout return page can confirm payment and redirect to receipt.
        $publicToken = isset($order->public_token) && is_string($order->public_token) ? (string) $order->public_token : '';
        if ($publicToken === '') {
            $publicToken = (new \Sikshya\Database\Repositories\OrderRepository())->ensurePublicToken((int) $order->id);
        }

        $payload = [
            'intent' => 'CAPTURE',
            'application_context' => [
                // Redirect back to Sikshya checkout to capture and enroll.
                'return_url' => add_query_arg(
                    [
                        'sikshya_paypal_return' => '1',
                        'order_id' => (string) $order->id,
                        'public_token' => $publicToken,
                    ],
                    PublicPageUrls::url('checkout')
                ),
                'cancel_url' => add_query_arg(
                    [
                        'sikshya_paypal_cancel' => '1',
                        'order_id' => (string) $order->id,
                        'public_token' => $publicToken,
                    ],
                    PublicPageUrls::url('checkout')
                ),
            ],
            'purchase_units' => [
                [
                    'reference_id' => (string) $order->id,
                    // Bind PayPal order to Sikshya order for later integrity checks (confirm + webhook).
                    'custom_id' => (string) $order->id,
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
     * PayPal Standard (email-only) redirect, mirroring Sikshya "Simple mode".
     *
     * This flow completes via PayPal IPN callback to `/sikshya/v1/webhooks/paypal-ipn`.
     *
     * @return array{approval_url: string}
     */
    private function createPayPalStandardRedirect(object $order): array
    {
        $email = trim((string) $this->settings()->getSetting('paypal_email', ''));
        if ($email === '' || !is_email($email)) {
            throw new \RuntimeException(__('PayPal email is required for Simple mode.', 'sikshya'));
        }

        $test = $this->settings()->getSetting('enable_test_mode', true);
        $is_test = $test === true || $test === 1 || $test === '1' || $test === 'true' || $test === 'yes' || $test === 'on';
        $paypalUrl = $is_test ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

        $amount = number_format((float) $order->total, 2, '.', '');
        $currency = strtoupper((string) $order->currency);
        if (strlen($currency) !== 3) {
            $currency = 'USD';
        }

        // After payment, PayPal returns here (we do NOT auto-confirm in-browser; IPN is source of truth).
        $returnUrl = \Sikshya\Frontend\Site\PublicPageUrls::orderView((string) ($order->public_token ?? ''));
        $cancelUrl = add_query_arg(
            [
                'sikshya_paypal_cancel' => '1',
                'order_id' => (string) $order->id,
            ],
            \Sikshya\Frontend\Site\PublicPageUrls::url('checkout')
        );

        $params = [
            'cmd' => '_xclick',
            'business' => $email,
            'item_name' => sprintf(
                /* translators: %d: order id */
                __('Sikshya order %d', 'sikshya'),
                (int) $order->id
            ),
            'item_number' => (string) $order->id,
            'amount' => $amount,
            'currency_code' => $currency,
            'return' => $returnUrl,
            'cancel_return' => $cancelUrl,
            'notify_url' => rest_url('sikshya/v1/webhooks/paypal-ipn'),
            'custom' => wp_json_encode(['order_id' => (int) $order->id]),
            'no_shipping' => '1',
            'no_note' => '1',
            'rm' => '2',
        ];

        $this->orders->updateOrder((int) $order->id, [
            'gateway_intent_id' => 'paypal-std-' . (int) $order->id,
        ]);

        return [
            'approval_url' => $paypalUrl . '?' . http_build_query($params),
        ];
    }

    /**
     * Create or reuse a Stripe PaymentIntent for inline checkout (Payment Element).
     *
     * @return array{payment_intent_id: string, client_secret: string}
     */
    public function createOrReuseStripePaymentIntent(object $order, string $receipt_email = ''): array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '') {
            throw new \RuntimeException(__('Stripe is not configured.', 'sikshya'));
        }

        $currency = strtoupper((string) $order->currency);
        $amount = (float) $order->total;
        $minor = self::toMinorUnits($amount, $currency);

        $existing = isset($order->gateway_intent_id) ? (string) $order->gateway_intent_id : '';
        if (is_string($existing) && strncmp($existing, 'pi_', 3) === 0) {
            $pi = $this->retrieveStripePaymentIntent($existing);
            $status = is_array($pi) ? (string) ($pi['status'] ?? '') : '';
            $piAmt = is_array($pi) ? (int) ($pi['amount'] ?? 0) : 0;
            $piCur = is_array($pi) && isset($pi['currency']) ? strtoupper((string) $pi['currency']) : '';
            $clientSecret = is_array($pi) ? (string) ($pi['client_secret'] ?? '') : '';
            if ($clientSecret !== '' && $status !== 'succeeded' && $piAmt === $minor && ($piCur === '' || $piCur === $currency)) {
                return [
                    'payment_intent_id' => $existing,
                    'client_secret' => $clientSecret,
                ];
            }
        }

        $body = [
            'amount' => (string) $minor,
            'currency' => strtolower($currency),
            'metadata[order_id]' => (string) (int) $order->id,
            'description' => sprintf(
                /* translators: %d: order id */
                __('Sikshya order %d', 'sikshya'),
                (int) $order->id
            ),
            // Let Stripe decide eligible methods for the Payment Element.
            'automatic_payment_methods[enabled]' => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ];
        if ($receipt_email !== '' && is_email($receipt_email)) {
            $body['receipt_email'] = $receipt_email;
        }

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
        if ($code >= 400 || !is_array($json) || empty($json['id']) || empty($json['client_secret'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? (string) $json['error']['message'] : __('Stripe PaymentIntent could not be created.', 'sikshya');
            throw new \RuntimeException($msg);
        }

        $pid = (string) $json['id'];
        $client_secret = (string) $json['client_secret'];

        $this->orders->updateOrder((int) $order->id, [
            'gateway' => 'stripe',
            'gateway_intent_id' => $pid,
        ]);

        return [
            'payment_intent_id' => $pid,
            'client_secret' => $client_secret,
        ];
    }

    /**
     * Load a Stripe Checkout Session (after customer returns from hosted checkout).
     */
    public function retrieveStripeCheckoutSession(string $session_id): ?array
    {
        $secret = (string) $this->settings()->getSetting('stripe_secret_key', '');
        if ($secret === '' || $session_id === '') {
            return null;
        }

        $response = wp_remote_get(
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($session_id),
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
     * Confirm Stripe PaymentIntent server-side (legacy in-flight payments).
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
        $base = $this->paypalRestBase();

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

    /**
     * @return array{approval_url: string, mollie_payment_id: string}
     */
    private function createMolliePayment(object $order): array
    {
        $key = (string) $this->settings()->getSetting('mollie_api_key', '');
        $amount = number_format((float) $order->total, 2, '.', '');
        $currency = strtoupper((string) $order->currency);
        if (strlen($currency) !== 3) {
            $currency = 'EUR';
        }

        $returnUrl = add_query_arg(
            [
                'sikshya_mollie_return' => '1',
                'order_id' => (string) $order->id,
                'public_token' => isset($order->public_token) ? (string) $order->public_token : '',
            ],
            PublicPageUrls::url('checkout')
        );

        $body = [
            'amount' => [
                'currency' => $currency,
                'value' => $amount,
            ],
            'description' => sprintf(
                /* translators: %d: order id */
                __('Sikshya order %d', 'sikshya'),
                (int) $order->id
            ),
            'redirectUrl' => $returnUrl,
            'metadata' => [
                'order_id' => (string) $order->id,
            ],
        ];

        $response = wp_remote_post(
            'https://api.mollie.com/v2/payments',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($json) || empty($json['id'])) {
            $msg = is_array($json) && isset($json['detail']) ? (string) $json['detail'] : __('Mollie payment creation failed.', 'sikshya');
            throw new \RuntimeException($msg);
        }

        $payId = (string) $json['id'];
        $href = '';
        if (!empty($json['_links']['checkout']['href'])) {
            $href = (string) $json['_links']['checkout']['href'];
        }

        if ($href === '') {
            throw new \RuntimeException(__('Mollie did not return a checkout URL.', 'sikshya'));
        }

        $this->orders->updateOrder((int) $order->id, ['gateway_intent_id' => $payId]);

        return [
            'approval_url' => $href,
            'mollie_payment_id' => $payId,
        ];
    }

    /**
     * @return array{approval_url: string, paystack_reference: string}
     */
    private function createPaystackTransaction(object $order): array
    {
        $secret = (string) $this->settings()->getSetting('paystack_secret_key', '');
        $user = wp_get_current_user();
        $email = ($user && $user->exists()) ? (string) $user->user_email : '';
        if ($email === '') {
            // Guest checkout: Paystack still needs a customer email. Use the guest email
            // captured on the order during /checkout/session.
            $meta = [];
            if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
                $decoded = json_decode($order->meta, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $guest = isset($meta['guest']) && is_array($meta['guest']) ? $meta['guest'] : [];
            $email = isset($guest['email']) ? sanitize_email((string) $guest['email']) : '';
        }
        if ($email === '' || !is_email($email)) {
            throw new \RuntimeException(__('A valid account email is required for Paystack.', 'sikshya'));
        }

        $currency = strtoupper((string) $order->currency);
        if (strlen($currency) !== 3) {
            $currency = 'NGN';
        }

        $reference = 'skord_' . (int) $order->id . '_' . strtolower(wp_generate_password(8, false, false));
        $amountMinor = self::toMinorUnits((float) $order->total, $currency);
        if ($amountMinor < 1) {
            throw new \RuntimeException(__('Order total is too small for Paystack.', 'sikshya'));
        }

        $callback = add_query_arg(
            [
                'sikshya_paystack_return' => '1',
                'order_id' => (string) $order->id,
                'public_token' => isset($order->public_token) ? (string) $order->public_token : '',
            ],
            PublicPageUrls::url('checkout')
        );

        $response = wp_remote_post(
            'https://api.paystack.co/transaction/initialize',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'email' => $email,
                    'amount' => $amountMinor,
                    'currency' => $currency,
                    'reference' => $reference,
                    'callback_url' => $callback,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = is_array($json) && isset($json['data']) && is_array($json['data']) ? $json['data'] : null;
        if (!$data || empty($data['authorization_url'])) {
            $msg = is_array($json) && isset($json['message']) ? (string) $json['message'] : __('Paystack initialization failed.', 'sikshya');
            throw new \RuntimeException($msg);
        }

        $this->orders->updateOrder((int) $order->id, [
            'gateway_intent_id' => $reference,
        ]);

        return [
            'approval_url' => (string) $data['authorization_url'],
            'paystack_reference' => $reference,
        ];
    }

    /**
     * @return array{approval_url: string, razorpay_payment_link_id: string}
     */
    private function createRazorpayPaymentLink(object $order): array
    {
        $keyId = (string) $this->settings()->getSetting('razorpay_key_id', '');
        $keySecret = (string) $this->settings()->getSetting('razorpay_key_secret', '');
        $currency = strtoupper((string) $order->currency);
        if (strlen($currency) !== 3) {
            $currency = 'INR';
        }

        $amountMinor = self::toMinorUnits((float) $order->total, $currency);
        if ($amountMinor < 1) {
            throw new \RuntimeException(__('Order total is too small for Razorpay.', 'sikshya'));
        }

        $callback = add_query_arg(
            [
                'sikshya_razorpay_return' => '1',
                'order_id' => (string) $order->id,
                // Guest checkout confirm requires the public token to authenticate the order.
                'public_token' => isset($order->public_token) ? (string) $order->public_token : '',
            ],
            PublicPageUrls::url('checkout')
        );

        $body = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'description' => sprintf(
                /* translators: %d: order id */
                __('Sikshya order %d', 'sikshya'),
                (int) $order->id
            ),
            // Bind link to order: returned back in GET (and visible in dashboard).
            'reference_id' => (string) $order->id,
            'notes' => [
                'order_id' => (string) $order->id,
            ],
            'callback_url' => $callback,
            'callback_method' => 'get',
        ];

        $response = wp_remote_post(
            'https://api.razorpay.com/v1/payment_links',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($keyId . ':' . $keySecret),
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($json) || empty($json['id']) || empty($json['short_url'])) {
            $msg = is_array($json) && isset($json['error']['description'])
                ? (string) $json['error']['description']
                : __('Razorpay payment link creation failed.', 'sikshya');
            throw new \RuntimeException($msg);
        }

        $linkId = (string) $json['id'];
        $this->orders->updateOrder((int) $order->id, ['gateway_intent_id' => $linkId]);

        return [
            'approval_url' => (string) $json['short_url'],
            'razorpay_payment_link_id' => $linkId,
        ];
    }

    /**
     * Fetch Mollie payment JSON for status checks.
     */
    public function getMolliePayment(string $payment_id): ?array
    {
        $key = (string) $this->settings()->getSetting('mollie_api_key', '');
        if ($key === '' || $payment_id === '') {
            return null;
        }

        $response = wp_remote_get(
            'https://api.mollie.com/v2/payments/' . rawurlencode($payment_id),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
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
     * Verify Paystack transaction by reference.
     */
    public function verifyPaystackReference(string $reference): ?array
    {
        $secret = (string) $this->settings()->getSetting('paystack_secret_key', '');
        if ($secret === '' || $reference === '') {
            return null;
        }

        $url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference);
        $response = wp_remote_get(
            $url,
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
        if (!is_array($json) || empty($json['status']) || !$json['status']) {
            return null;
        }

        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Fetch Razorpay payment link JSON.
     */
    public function getRazorpayPaymentLink(string $link_id): ?array
    {
        $keyId = (string) $this->settings()->getSetting('razorpay_key_id', '');
        $keySecret = (string) $this->settings()->getSetting('razorpay_key_secret', '');
        if ($keyId === '' || $link_id === '') {
            return null;
        }

        $response = wp_remote_get(
            'https://api.razorpay.com/v1/payment_links/' . rawurlencode($link_id),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($keyId . ':' . $keySecret),
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
     * @param array<int, int> $course_ids
     */
    private function assertCheckoutOrderEligibility(int $user_id, array $course_ids): void
    {
        $course_ids = array_values(array_unique(array_filter(array_map('intval', $course_ids))));
        if ($course_ids === []) {
            return;
        }

        /*
         * Allow add-ons to block checkout before an order row is created (e.g. prerequisites).
         */
        $reject = apply_filters('sikshya_checkout_validate_order_eligibility', null, $user_id, $course_ids);
        if ($reject instanceof \WP_Error) {
            throw new \InvalidArgumentException($reject->get_error_message());
        }
    }
}
