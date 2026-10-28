<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

use Sikshya\Constants\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The field on a WooCommerce product that says which courses it sells.
 *
 * Deliberately on the product's own edit screen rather than on the course:
 * somebody setting up a shop is already there, pricing it, and the alternative
 * — a product picker on every course — asks them to describe the same
 * relationship from the far end.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class ProductAdmin
{
    public const NONCE = 'sikshya_woo_courses_nonce';

    public static function register(): void
    {
        add_action('woocommerce_product_data_panels', [self::class, 'renderPanel']);
        add_filter('woocommerce_product_data_tabs', [self::class, 'addTab'], 10, 1);
        add_action('woocommerce_process_product_meta', [self::class, 'save'], 10, 1);
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public static function addTab(array $tabs): array
    {
        $tabs['sikshya_courses'] = [
            'label' => __('Courses', 'sikshya'),
            'target' => 'sikshya_courses_product_data',
            'class' => [],
            'priority' => 65,
        ];

        return $tabs;
    }

    public static function renderPanel(): void
    {
        global $post;

        $productId = $post instanceof \WP_Post ? (int) $post->ID : 0;
        $selected = $productId > 0 ? ProductCourseLink::coursesFor($productId) : [];

        $courses = get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => 300,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        echo '<div id="sikshya_courses_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        wp_nonce_field('sikshya_woo_courses', self::NONCE);

        echo '<p class="form-field"><label for="sikshya-woo-courses">'
            . esc_html__('Courses this product gives access to', 'sikshya')
            . '</label>';

        echo '<select id="sikshya-woo-courses" name="sikshya_course_ids[]" multiple="multiple" style="width:100%;max-width:28rem;height:12rem;">';
        foreach ($courses as $course) {
            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                (int) $course->ID,
                in_array((int) $course->ID, $selected, true) ? 'selected="selected"' : '',
                esc_html(get_the_title($course) !== '' ? get_the_title($course) : sprintf(
                    /* translators: %d: course id */
                    __('(no title) — #%d', 'sikshya'),
                    (int) $course->ID
                ))
            );
        }
        echo '</select>';

        echo '<span class="description" style="display:block;margin-top:.5rem;">'
            . esc_html__(
                'Buying this product enrolls the customer in every course selected here. A refund or cancellation closes only the access this order gave them.',
                'sikshya'
            )
            . '</span>';

        echo '</p></div></div>';
    }

    /** @param int|string $productId */
    public static function save($productId): void
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately below.
        $nonce = isset($_POST[self::NONCE]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'sikshya_woo_courses')) {
            // Without the nonce this is not our form being saved — a product
            // can be written by plenty of other code paths, and clearing the
            // link because one of them did would silently unsell the course.
            return;
        }

        if (!current_user_can('edit_post', $productId)) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
        $raw = isset($_POST['sikshya_course_ids']) ? wp_unslash($_POST['sikshya_course_ids']) : [];
        ProductCourseLink::link($productId, is_array($raw) ? $raw : []);
    }
}
