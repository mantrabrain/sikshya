<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;

/**
 * @package Sikshya\Frontend\Public
 */
final class CartTemplateData
{
    /**
     * @return array{lines: array<int, array{course_id: int, title: string, permalink: string, pricing: array}>, subtotal_hint: float, currency: string}
     */
    public static function build(): array
    {
        $ids = CartStorage::getCourseIds();
        $lines = [];
        $subtotal = 0.0;
        $currency = 'USD';

        foreach ($ids as $cid) {
            $p = get_post($cid);
            if (!$p || $p->post_status !== 'publish') {
                continue;
            }
            $pricing = function_exists('sikshya_get_course_pricing') ? sikshya_get_course_pricing($cid) : [];
            $eff = isset($pricing['effective']) && $pricing['effective'] !== null ? (float) $pricing['effective'] : 0.0;
            $subtotal += $eff;
            if (!empty($pricing['currency'])) {
                $currency = (string) $pricing['currency'];
            }
            $lines[] = [
                'course_id' => $cid,
                'title' => get_the_title($p),
                'permalink' => get_permalink($cid) ?: '',
                'pricing' => $pricing,
            ];
        }

        return apply_filters(
            'sikshya_cart_template_data',
            [
                'lines' => $lines,
                'subtotal_hint' => round($subtotal, 2),
                'currency' => $currency,
                'urls' => [
                    'checkout' => PublicPageUrls::url('checkout'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }
}
