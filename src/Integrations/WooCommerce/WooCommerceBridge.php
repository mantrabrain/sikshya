<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Selling Sikshya courses through WooCommerce.
 *
 * For shops that already run WooCommerce, this is not a feature so much as the
 * removal of a reason not to use Sikshya at all: their gateways, taxes,
 * invoices, accounting exports and customer accounts already work, and nobody
 * wants a second checkout beside them. It is free for the same reason the
 * importers are.
 *
 * Which statuses grant access is the one decision worth stating. `completed`
 * is the obvious one, but a shop selling only courses often leaves digital
 * orders in `processing` for ever — so `processing` grants too, and
 * `payment_complete` covers gateways that go straight there. All three are
 * idempotent, so an order that passes through two of them enrols once.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class WooCommerceBridge
{
    public static function register(): void
    {
        // Registered unconditionally and inert without WooCommerce, so the
        // hooks exist for anything that wants to filter them and nothing runs
        // on a site that does not sell this way.
        add_action('woocommerce_order_status_completed', [self::class, 'onPaid'], 10, 1);
        add_action('woocommerce_order_status_processing', [self::class, 'onPaid'], 10, 1);
        add_action('woocommerce_payment_complete', [self::class, 'onPaid'], 10, 1);

        add_action('woocommerce_order_status_refunded', [self::class, 'onRefunded'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [self::class, 'onRefunded'], 10, 1);
        add_action('woocommerce_order_status_failed', [self::class, 'onRefunded'], 10, 1);
        add_action('woocommerce_order_refunded', [self::class, 'onPartiallyRefunded'], 10, 2);

        // Nobody should be able to buy a course they are already in.
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'blockDuplicatePurchase'], 10, 3);

        if (is_admin()) {
            ProductAdmin::register();
        }

        StorefrontLinks::register();
    }

    /** @param int|string $orderId */
    public static function onPaid($orderId): void
    {
        $snapshot = WooOrderReader::read((int) $orderId);
        if ($snapshot === null || $snapshot->courseIds() === []) {
            return;
        }

        (new CourseAccessService())->grant($snapshot);
    }

    /** @param int|string $orderId */
    public static function onRefunded($orderId): void
    {
        $snapshot = WooOrderReader::read((int) $orderId);
        if ($snapshot === null || $snapshot->courseIds() === []) {
            return;
        }

        (new CourseAccessService())->revoke($snapshot);
    }

    /**
     * A refund against part of an order.
     *
     * @param int|string $orderId
     * @param int|string $refundId
     */
    public static function onPartiallyRefunded($orderId, $refundId): void
    {
        unset($refundId);

        $snapshot = WooOrderReader::read((int) $orderId);
        if ($snapshot === null) {
            return;
        }

        // Only the lines that came back in full: a partial refund of a
        // five-seat line is not a reason to shut the buyer out.
        (new CourseAccessService())->revoke($snapshot, true);
    }

    /**
     * Refuse to add a course the current learner already has.
     *
     * @param mixed $passed
     * @param mixed $productId
     * @param mixed $quantity
     * @return mixed
     */
    public static function blockDuplicatePurchase($passed, $productId, $quantity)
    {
        unset($quantity);

        if (!$passed || !is_user_logged_in()) {
            return $passed;
        }

        $courses = ProductCourseLink::coursesFor((int) $productId);
        if ($courses === []) {
            return $passed;
        }

        $userId = get_current_user_id();
        $enrolments = new EnrollmentRepository();

        foreach ($courses as $courseId) {
            $row = $enrolments->findByUserAndCourse($userId, $courseId);
            if ($row === null || (string) $row->status === 'cancelled') {
                // Not enrolled, or access was revoked and they are buying
                // again — which has to stay possible.
                continue;
            }

            if (function_exists('wc_add_notice')) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: course title */
                        __('You are already enrolled in “%s”, so it has not been added to your cart.', 'sikshya'),
                        get_the_title($courseId)
                    ),
                    'error'
                );
            }

            return false;
        }

        return $passed;
    }

    /**
     * Whether a course is sold through WooCommerce.
     *
     * Used by the storefront to send the buyer to the product instead of
     * Sikshya's own checkout, so the two never both offer to take the money.
     */
    public static function sellsCourse(int $courseId): bool
    {
        if (get_post_type($courseId) !== PostTypes::COURSE || !WooOrderReader::available()) {
            return false;
        }

        return ProductCourseLink::productFor($courseId) > 0;
    }
}
