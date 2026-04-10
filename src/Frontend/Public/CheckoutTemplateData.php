<?php

namespace Sikshya\Frontend\Public;

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
        $stripe = (string) get_option('_sikshya_stripe_secret_key', '') !== '';
        $paypal = (string) get_option('_sikshya_paypal_client_id', '') !== ''
            && (string) get_option('_sikshya_paypal_secret', '') !== '';
        $offline_raw = get_option('_sikshya_enable_offline_payment', '1');

        return [
            'offline' => self::isTruthyGatewayOption($offline_raw),
            'stripe' => $stripe,
            'paypal' => $paypal,
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
                'empty' => $cart['lines'] === [],
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'rest_url' => esc_url_raw(rest_url('sikshya/v1/')),
                'gateways' => self::gatewaysConfigured(),
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
