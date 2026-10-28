<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sending a buyer to the WooCommerce product instead of Sikshya's checkout.
 *
 * Without this, a course sold through WooCommerce shows two ways to pay on the
 * same page — Sikshya's cart and, somewhere else, the product. Whichever the
 * buyer picks, half the shop's reporting is wrong, and the two carts disagree
 * about what was bought.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class StorefrontLinks
{
    public static function register(): void
    {
        add_filter('sikshya_course_purchase_handled', [self::class, 'claimPurchase'], 10, 2);
        add_action('sikshya_course_purchase_actions', [self::class, 'renderActions'], 10, 1);
    }

    /**
     * @param mixed $handled
     * @param mixed $courseId
     * @return bool
     */
    public static function claimPurchase($handled, $courseId): bool
    {
        if ($handled) {
            return true;
        }

        return WooCommerceBridge::sellsCourse((int) $courseId);
    }

    /** @param mixed $courseId */
    public static function renderActions($courseId): void
    {
        $productId = ProductCourseLink::productFor((int) $courseId);
        if ($productId <= 0) {
            return;
        }

        $addToCart = function_exists('wc_get_cart_url')
            ? add_query_arg('add-to-cart', $productId, wc_get_cart_url())
            : (string) get_permalink($productId);

        $productUrl = (string) get_permalink($productId);
        $price = function_exists('wc_get_product') ? wc_get_product($productId) : null;
        $priceHtml = is_object($price) && method_exists($price, 'get_price_html') ? (string) $price->get_price_html() : '';

        ?>
        <div class="sikshya-course-lp__woo" data-sikshya-woo-purchase>
            <?php if ($priceHtml !== '') : ?>
                <p class="sikshya-course-lp__woo-price"><?php echo wp_kses_post($priceHtml); ?></p>
            <?php endif; ?>
            <a class="sikshya-btn sikshya-btn--sm sikshya-btn--primary sikshya-course-lp__btn-full" href="<?php echo esc_url($addToCart); ?>">
                <?php esc_html_e('Buy now', 'sikshya'); ?>
            </a>
            <a class="sikshya-course-lp__sub-link" href="<?php echo esc_url($productUrl); ?>">
                <?php esc_html_e('See what is included', 'sikshya'); ?>
            </a>
        </div>
        <?php
    }
}
