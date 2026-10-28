<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

use Sikshya\Constants\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Which courses a WooCommerce product grants.
 *
 * Stored on the **product**, as a list, so that one product can sell a bundle
 * of courses — which is how shops actually sell them — and so that removing a
 * course from a product is one edit in one place.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class ProductCourseLink
{
    public const META = '_sikshya_course_ids';

    /** @return array<int, int> */
    public static function coursesFor(int $productId): array
    {
        $raw = get_post_meta($productId, self::META, true);
        $ids = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? explode(',', $raw) : []);

        $out = [];
        foreach ($ids as $id) {
            $courseId = (int) $id;
            if ($courseId > 0 && get_post_type($courseId) === PostTypes::COURSE) {
                $out[$courseId] = $courseId;
            }
        }

        return array_values($out);
    }

    /**
     * @param array<int, int|string> $courseIds
     */
    public static function link(int $productId, array $courseIds): void
    {
        $clean = [];
        foreach ($courseIds as $id) {
            $courseId = (int) $id;
            if ($courseId > 0 && get_post_type($courseId) === PostTypes::COURSE) {
                $clean[$courseId] = $courseId;
            }
        }

        if ($clean === []) {
            delete_post_meta($productId, self::META);

            return;
        }

        update_post_meta($productId, self::META, array_values($clean));
    }

    /**
     * The product that sells a course, if any.
     *
     * A meta query rather than a reverse pointer on the course: the link is
     * owned by the product, and a second copy of it on the course is a second
     * thing to keep in step.
     */
    public static function productFor(int $courseId): int
    {
        global $wpdb;

        if ($courseId <= 0) {
            return 0;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
               AND p.post_type = %s
               AND p.post_status = %s
               AND pm.meta_value LIKE %s
             ORDER BY pm.post_id ASC",
            self::META,
            'product',
            'publish',
            // Serialised array of ints; the LIKE narrows the rows, and the
            // exact membership check below is what decides.
            '%' . $wpdb->esc_like((string) $courseId) . '%'
        ), ARRAY_A);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $productId = (int) $row['post_id'];
            if (in_array($courseId, self::coursesFor($productId), true)) {
                return $productId;
            }
        }

        return 0;
    }
}
