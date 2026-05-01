<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Commerce\PaymentGatewayRegistry;
use Sikshya\Licensing\Pro;
use Sikshya\Services\Settings;

/**
 * @package Sikshya\Frontend\Site
 */
final class CheckoutTemplateData
{
    /**
     * Gateways toggled on in settings (does not imply configured).
     *
     * @return array<string, bool>
     */
    public static function gatewaysEnabled(): array
    {
        $out = [];
        foreach (PaymentGatewayRegistry::all() as $g) {
            $id = isset($g['id']) ? sanitize_key((string) $g['id']) : '';
            if ($id === '') {
                continue;
            }
            $enabled_key = isset($g['enabled_setting_key']) ? sanitize_key((string) $g['enabled_setting_key']) : '';
            if ($enabled_key === '') {
                $out[$id] = true;
                continue;
            }
            // Fresh installs: only offline defaults on; PayPal and others stay off until explicitly enabled.
            $default = $id === 'offline' ? '1' : '0';
            $out[$id] = self::isTruthyGatewayOption(Settings::get($enabled_key, $default));
        }
        return $out;
    }

    /**
     * Which gateways are offered on checkout (each needs enabled + configured; Pro gates premium methods).
     *
     * @return array<string, bool>
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
        $enable_paypal = Settings::get('enable_paypal_payment', '0');
        $enable_stripe = Settings::get('enable_stripe_payment', '0');

        $stripe = Pro::isActive()
            && $isWired('stripe', true)
            && self::isTruthyGatewayOption($enable_stripe)
            && (string) Settings::get('stripe_secret_key', '') !== '';

        $paypal_mode = sanitize_key((string) Settings::get('paypal_integration_mode', 'advanced'));
        if (!in_array($paypal_mode, ['simple', 'advanced'], true)) {
            $paypal_mode = 'advanced';
        }
        // Be forgiving: if PayPal email is configured, show PayPal on checkout (simple flow),
        // even if the integration mode is still set to "advanced".
        $paypal_email_ok = is_email((string) Settings::get('paypal_email', ''));
        $paypal_rest_ok = (string) Settings::get('paypal_client_id', '') !== '' && (string) Settings::get('paypal_secret', '') !== '';
        $paypal = $isWired('paypal', true)
            && self::isTruthyGatewayOption($enable_paypal)
            && ($paypal_email_ok || ($paypal_mode === 'advanced' && $paypal_rest_ok));

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
            && (string) Settings::get('square_application_id', '') !== ''
            && (string) Settings::get('square_access_token', '') !== ''
            && (string) Settings::get('square_location_id', '') !== '';

        $authorize_net = Pro::isActive()
            && $isWired('authorize_net', false)
            && self::isTruthyGatewayOption(Settings::get('enable_authorize_net_payment', '0'))
            && (string) Settings::get('authorize_net_login_id', '') !== ''
            && (string) Settings::get('authorize_net_public_client_key', '') !== ''
            && (string) Settings::get('authorize_net_transaction_key', '') !== '';

        $bank_transfer = Pro::isActive()
            && $isWired('bank_transfer', true)
            && self::isTruthyGatewayOption(Settings::get('enable_bank_transfer_payment', '0'))
            && (
                (string) Settings::get('bank_transfer_instructions', '') !== ''
                || (string) Settings::get('bank_transfer_account_number', '') !== ''
            );

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
        $enabled = self::gatewaysEnabled();
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
            if (!empty($enabled[$id])) {
                $out[] = $id;
            }
        }
        foreach ($enabled as $id => $on) {
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
     * @return array<string, mixed>
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

        $enabled = self::gatewaysEnabled();
        $configured = self::gatewaysConfigured();
        $gateway_statuses = [];
        $wired_defaults = [
            'offline' => true,
            'paypal' => true,
            'stripe' => true,
            'razorpay' => true,
            'mollie' => true,
            'paystack' => true,
            'bank_transfer' => true,
            'square' => false,
            'authorize_net' => false,
        ];
        $is_wired = static function (string $id) use ($wired_defaults): bool {
            $d = array_key_exists($id, $wired_defaults) ? (bool) $wired_defaults[$id] : true;
            return (bool) apply_filters('sikshya_payment_gateway_wired', $d, $id);
        };
        $pro_active = Pro::isActive();
        $tiers = [];
        foreach (PaymentGatewayRegistry::all() as $g) {
            $id = isset($g['id']) ? sanitize_key((string) $g['id']) : '';
            if ($id === '') {
                continue;
            }
            $tier = isset($g['tier']) && (string) $g['tier'] === 'pro' ? 'pro' : 'free';
            $tiers[$id] = $tier;
        }

        foreach ($enabled as $gid => $is_on) {
            $gid = sanitize_key((string) $gid);
            if ($gid === '') {
                continue;
            }
            $tier = $tiers[$gid] ?? 'free';
            $is_pro_gateway = $tier === 'pro';
            $gateway_statuses[$gid] = [
                'enabled' => (bool) $is_on,
                'configured' => !empty($configured[$gid]),
                'wired' => $is_wired($gid),
                'locked' => $is_pro_gateway && !$pro_active,
                'locked_reason' => $is_pro_gateway && !$pro_active ? __('Requires Sikshya Pro.', 'sikshya') : '',
            ];
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
                'gateway_statuses' => $gateway_statuses,
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
