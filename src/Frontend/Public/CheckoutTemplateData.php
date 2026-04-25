<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Licensing\Pro;
use Sikshya\Services\Settings;

/**
 * @package Sikshya\Frontend\Public
 */
final class CheckoutTemplateData
{
    /**
     * Which gateways are offered on checkout (offline is on by default; Stripe/PayPal need credentials).
     *
     * @return array{offline: bool, stripe: bool, paypal: bool}
     */
    public static function gatewaysConfigured(): array
    {
        $isWired = static function (string $id, bool $default): bool {
            /**
             * Allow Pro/addons to declare that a gateway's checkout + confirm flow is fully wired.
             * Core wires Stripe/PayPal/Mollie/Paystack/Razorpay and manual flows; Square/Authorize.Net
             * are settings-only until an extension implements their session+confirm handlers.
             */
            return (bool) apply_filters('sikshya_payment_gateway_wired', $default, $id);
        };

        $enable_offline = Settings::get('enable_offline_payment', '1');
        $enable_paypal = Settings::get('enable_paypal_payment', '1');
        $enable_stripe = Settings::get('enable_stripe_payment', '0');

        $stripe = Pro::isActive()
            && $isWired('stripe', true)
            && self::isTruthyGatewayOption($enable_stripe)
            && (string) Settings::get('stripe_secret_key', '') !== '';

        $paypal = $isWired('paypal', true)
            && self::isTruthyGatewayOption($enable_paypal)
            && (string) Settings::get('paypal_client_id', '') !== ''
            && (string) Settings::get('paypal_secret', '') !== '';

        // Pro gateways (configured + enabled) — actual session handling lives in Pro.
        $razorpay = Pro::isActive()
            && $isWired('razorpay', true)
            && self::isTruthyGatewayOption(Settings::get('enable_razorpay_payment', '0'))
            && (string) Settings::get('razorpay_key_id', '') !== ''
            && (string) Settings::get('razorpay_key_secret', '') !== '';

        $mollie = Pro::isActive()
            && $isWired('mollie', true)
            && self::isTruthyGatewayOption(Settings::get('enable_mollie_payment', '0'))
            && (string) Settings::get('mollie_api_key', '') !== '';

        $paystack = Pro::isActive()
            && $isWired('paystack', true)
            && self::isTruthyGatewayOption(Settings::get('enable_paystack_payment', '0'))
            && (string) Settings::get('paystack_public_key', '') !== ''
            && (string) Settings::get('paystack_secret_key', '') !== '';

        $square = Pro::isActive()
            && $isWired('square', false)
            && self::isTruthyGatewayOption(Settings::get('enable_square_payment', '0'))
            && (string) Settings::get('square_access_token', '') !== ''
            && (string) Settings::get('square_location_id', '') !== '';

        $authorize_net = Pro::isActive()
            && $isWired('authorize_net', false)
            && self::isTruthyGatewayOption(Settings::get('enable_authorize_net_payment', '0'))
            && (string) Settings::get('authorize_net_login_id', '') !== ''
            && (string) Settings::get('authorize_net_transaction_key', '') !== '';

        $bank_transfer = Pro::isActive()
            && $isWired('bank_transfer', true)
            && self::isTruthyGatewayOption(Settings::get('enable_bank_transfer_payment', '0'))
            && (string) Settings::get('bank_transfer_instructions', '') !== '';

        return [
            'offline' => $isWired('offline', true) && self::isTruthyGatewayOption($enable_offline),
            'stripe' => $stripe,
            'paypal' => $paypal,
            'razorpay' => $razorpay,
            'mollie' => $mollie,
            'paystack' => $paystack,
            'square' => $square,
            'authorize_net' => $authorize_net,
            'bank_transfer' => $bank_transfer,
        ];
    }

    /**
     * @param mixed $v
     */
    private static function isTruthyGatewayOption($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /**
     * Enabled gateways in display order (payment_gateways_order then remainder).
     *
     * @return list<string>
     */
    public static function checkoutGatewayIdsOrdered(): array
    {
        $configured = self::gatewaysConfigured();
        $orderRaw = trim((string) Settings::get('payment_gateways_order', ''));
        $parts = $orderRaw === '' ? [] : array_map('trim', explode(',', $orderRaw));
        $order = [];
        foreach ($parts as $p) {
            $k = sanitize_key($p);
            if ($k !== '') {
                $order[] = $k;
            }
        }

        $out = [];
        foreach ($order as $id) {
            if (!empty($configured[$id])) {
                $out[] = $id;
            }
        }
        foreach ($configured as $id => $on) {
            if ($on && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Button labels for checkout (translate in template).
     *
     * @return array<string, string>
     */
    public static function gatewayCheckoutLabels(): array
    {
        return [
            'offline' => __('Offline payment', 'sikshya'),
            'paypal' => __('Pay with PayPal', 'sikshya'),
            'stripe' => __('Pay with card (Stripe)', 'sikshya'),
            'razorpay' => __('Pay with Razorpay', 'sikshya'),
            'mollie' => __('Pay with Mollie', 'sikshya'),
            'paystack' => __('Pay with Paystack', 'sikshya'),
            'square' => __('Pay with Square', 'sikshya'),
            'authorize_net' => __('Pay with Authorize.Net', 'sikshya'),
            'bank_transfer' => __('Bank transfer', 'sikshya'),
        ];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, course_ids: array<int, int>, empty: bool, subtotal_hint: float, currency: string, rest_nonce: string, rest_url: string, gateways: array{offline: bool, stripe: bool, paypal: bool}, urls: array{home: string, cart: string, checkout: string, account: string}, viewer: array{display_name: string, email: string}}
     */
    public static function build(): array
    {
        $cart = CartTemplateData::build();
        $course_ids = array_map(
            static fn(array $row): int => (int) $row['course_id'],
            $cart['lines']
        );

        $user = wp_get_current_user();
        $display = ($user && $user->exists())
            ? (string) $user->display_name
            : '';
        if ($display === '' && $user && $user->exists()) {
            $display = (string) $user->user_login;
        }

        return apply_filters(
            'sikshya_checkout_template_data',
            [
                'lines' => $cart['lines'],
                'course_ids' => $course_ids,
                'subtotal_hint' => $cart['subtotal_hint'],
                'currency' => $cart['currency'],
                'bundle_id' => isset($cart['bundle_id']) ? (int) $cart['bundle_id'] : CartStorage::getBundleId(),
                'empty' => $cart['lines'] === [],
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'rest_url' => esc_url_raw(rest_url('sikshya/v1/')),
                'gateways' => self::gatewaysConfigured(),
                'checkout_gateway_ids' => self::checkoutGatewayIdsOrdered(),
                'urls' => [
                    'home' => home_url('/'),
                    'cart' => PublicPageUrls::url('cart'),
                    'checkout' => PublicPageUrls::url('checkout'),
                    'account' => PublicPageUrls::url('account'),
                ],
                'viewer' => [
                    'display_name' => $display,
                    'email' => ($user && $user->exists()) ? (string) $user->user_email : '',
                ],
            ]
        );
    }
}
