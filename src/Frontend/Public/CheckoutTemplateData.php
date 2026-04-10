<?php

namespace Sikshya\Frontend\Public;

/**
 * @package Sikshya\Frontend\Public
 */
final class CheckoutTemplateData
{
    /**
     * Which gateways have minimum credentials (matches {@see CheckoutService::startGatewaySession}).
     *
     * @return array{stripe: bool, paypal: bool}
     */
    public static function gatewaysConfigured(): array
    {
        $stripe = (string) get_option('_sikshya_stripe_secret_key', '') !== '';
        $paypal = (string) get_option('_sikshya_paypal_client_id', '') !== ''
            && (string) get_option('_sikshya_paypal_secret', '') !== '';

        return [
            'stripe' => $stripe,
            'paypal' => $paypal,
        ];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, course_ids: array<int, int>, empty: bool, rest_nonce: string, rest_url: string, gateways: array{stripe: bool, paypal: bool}}
     */
    public static function build(): array
    {
        $cart = CartTemplateData::build();
        $course_ids = array_map(
            static fn(array $row): int => (int) $row['course_id'],
            $cart['lines']
        );

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
                    'cart' => PublicPageUrls::url('cart'),
                    'account' => PublicPageUrls::url('account'),
                ],
            ]
        );
    }
}
