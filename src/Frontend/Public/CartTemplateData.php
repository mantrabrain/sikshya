<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;

/**
 * @package Sikshya\Frontend\Public
 */
final class CartTemplateData
{
    /**
     * @return array{lines: array<int, array{course_id: int, title: string, permalink: string, thumbnail: string, instructor: string, pricing: array}>, subtotal_hint: float, currency: string, bundle_id: int, urls: array{home: string, cart: string, checkout: string, courses: string}}
     */
    public static function build(): array
    {
        $ids = CartStorage::getCourseIds();
        $bundle_id = CartStorage::getBundleId();
        $lines = [];
        $subtotal = 0.0;
        $currency = function_exists('sikshya_get_store_currency_code') ? sikshya_get_store_currency_code() : 'USD';

        foreach ($ids as $cid) {
            $p = get_post($cid);
            if (!$p || $p->post_status !== 'publish') {
                continue;
            }
            $pricing = function_exists('sikshya_get_course_pricing') ? sikshya_get_course_pricing($cid) : [];
            $eff = isset($pricing['effective']) && $pricing['effective'] !== null ? (float) $pricing['effective'] : 0.0;
            $subtotal += $eff;
            $thumb = get_the_post_thumbnail_url($cid, 'thumbnail');
            $thumb = is_string($thumb) ? $thumb : '';
            $instructor = get_the_author_meta('display_name', (int) $p->post_author);
            $instructor = is_string($instructor) ? $instructor : '';
            $lines[] = [
                'course_id' => $cid,
                'title' => get_the_title($p),
                'permalink' => get_permalink($cid) ?: '',
                'thumbnail' => $thumb,
                'instructor' => $instructor,
                'pricing' => $pricing,
            ];
        }

        return apply_filters(
            'sikshya_cart_template_data',
            [
                'lines' => $lines,
                'subtotal_hint' => round($subtotal, 2),
                'currency' => $currency,
                'bundle_id' => $bundle_id,
                'urls' => [
                    'home' => home_url('/'),
                    'cart' => PublicPageUrls::url('cart'),
                    'checkout' => PublicPageUrls::url('checkout'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }
}
