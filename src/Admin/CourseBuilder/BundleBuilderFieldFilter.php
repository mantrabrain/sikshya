<?php

/**
 * Strips the React course builder down to the fields a commercial bundle post actually needs.
 *
 * @package Sikshya
 */

namespace Sikshya\Admin\CourseBuilder;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @phpstan-type TabFields array<string, array<string, mixed>>
 */
final class BundleBuilderFieldFilter
{
    /**
     * @param array<string, mixed> $tabFields getTabFieldsForJs() after bootstrapping
     * @return array<string, mixed>
     */
    public static function filterTabFields(array $tabFields): array
    {
        if (isset($tabFields['course'])) {
            $tabFields['course'] = self::filterCourseTab($tabFields['course']);
        }
        if (isset($tabFields['pricing'])) {
            $tabFields['pricing'] = self::filterPricingTab($tabFields['pricing']);
        }

        return $tabFields;
    }

    /**
     * @param array<int, array<string, string>> $tabs
     * @return array<int, array<string, string>>
     */
    public static function adjustTabSummariesForBundle(array $tabs): array
    {
        return array_map(
            static function (array $t): array {
                if (($t['id'] ?? '') === 'course') {
                    $t['title'] = __('Bundle page', 'sikshya');
                    $t['description'] = __(
                        'Name and describe this package, add a cover and trailer — what shoppers see before they buy.',
                        'sikshya'
                    );
                } elseif (($t['id'] ?? '') === 'pricing') {
                    $t['title'] = __('Price & included courses', 'sikshya');
                    $t['description'] = __(
                        'Set the bundle price and select published courses to include. Curriculum is edited on each course.',
                        'sikshya'
                    );
                }

                return $t;
            },
            $tabs
        );
    }

    /**
     * @param array<string, mixed> $course
     * @return array<string, mixed>
     */
    private static function filterCourseTab(array $course): array
    {
        $out = [];
        if (isset($course['basic_info'], $course['basic_info']['fields'], $course['basic_info']['section']) && is_array($course['basic_info']['fields'])) {
            $keep = ['title', 'slug', 'short_description', 'description', 'category', 'course_tags'];
            $fields = [];
            foreach ($keep as $k) {
                if (isset($course['basic_info']['fields'][$k])) {
                    $fields[$k] = $course['basic_info']['fields'][$k];
                }
            }
            $course['basic_info']['fields'] = $fields;
            $course['basic_info']['section']['title'] = __('Summary', 'sikshya');
            $course['basic_info']['section']['description'] = __(
                'Title, URL, catalog category, and the story you show on the bundle page.',
                'sikshya'
            );
            $out['basic_info'] = $course['basic_info'];
        }
        if (isset($course['media_visuals'])) {
            $out['media_visuals'] = $course['media_visuals'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $pricing
     * @return array<string, mixed>
     */
    private static function filterPricingTab(array $pricing): array
    {
        if (empty($pricing['pricing']['fields']) || !is_array($pricing['pricing']['fields'])) {
            return $pricing;
        }
        // Bundles always have course_type=bundle, so don't show the selector.
        $keep = ['price', 'sale_price', 'bundle_course_ids', 'bundle_visible_in_listing'];
        $fields = [];
        foreach ($keep as $k) {
            if (!isset($pricing['pricing']['fields'][$k])) {
                continue;
            }
            $f = $pricing['pricing']['fields'][$k];
            if (in_array($k, ['bundle_course_ids', 'bundle_visible_in_listing'], true) && is_array($f)) {
                unset($f['depends_on'], $f['depends_value'], $f['depends_all']);
            }
            $fields[$k] = $f;
        }
        $pricing['pricing']['fields'] = $fields;
        $pricing['pricing']['section']['title'] = __('Bundle offer', 'sikshya');
        $pricing['pricing']['section']['description'] = __(
            'Set the list price, optional sale, and which published courses are sold together. Global currency and tax are under Payment settings.',
            'sikshya'
        );

        return ['pricing' => $pricing['pricing']];
    }
}
