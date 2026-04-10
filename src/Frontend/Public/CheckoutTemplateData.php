<?php

namespace Sikshya\Frontend\Public;

/**
 * @package Sikshya\Frontend\Public
 */
final class CheckoutTemplateData
{
    /**
     * @return array{lines: array<int, array<string, mixed>>, course_ids: array<int, int>, empty: bool, rest_nonce: string, rest_url: string}
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
                'urls' => [
                    'cart' => PublicPageUrls::url('cart'),
                    'account' => PublicPageUrls::url('account'),
                ],
            ]
        );
    }
}
