<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

/**
 * Server renderer for sikshya/course-search.
 *
 * A GET form pointed at the course archive. It submits `s` and `post_type`,
 * which is exactly what the archive's own filter layer reads
 * (includes/course-archive-filters.php), so the results page is the real
 * archive with its sorting and sidebar rather than a second search
 * implementation to keep in step.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseSearchBlock
{
    public const NAME = 'sikshya/course-search';

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes = []): string
    {
        $label_courses = function_exists('sikshya_label_plural')
            ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend')
            : __('Courses', 'sikshya');
        $lower = function_exists('mb_strtolower') ? mb_strtolower($label_courses) : strtolower($label_courses);

        $placeholder = isset($attributes['placeholder']) && is_string($attributes['placeholder'])
            ? sanitize_text_field($attributes['placeholder'])
            : '';
        if ($placeholder === '') {
            /* translators: %s: plural course label (e.g. courses) */
            $placeholder = sprintf(__('Search %s…', 'sikshya'), $lower);
        }

        $button_label = isset($attributes['buttonLabel']) && is_string($attributes['buttonLabel'])
            ? sanitize_text_field($attributes['buttonLabel'])
            : '';
        if ($button_label === '') {
            $button_label = __('Search', 'sikshya');
        }

        $show_categories = !empty($attributes['showCategoryFilter']);
        $show_button = !isset($attributes['showButton']) || (bool) $attributes['showButton'];

        $action = get_post_type_archive_link(PostTypes::COURSE);
        if (!is_string($action) || $action === '') {
            $action = home_url('/');
        }

        // One id per instance: two search blocks on a page must not share a
        // `for=` target, or the second label points at the first input.
        $uid = 'sikshya-course-search-' . wp_unique_id();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
        $current = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
        $current_cat = isset($_GET['sikshya_cat']) ? sanitize_title(wp_unslash((string) $_GET['sikshya_cat'])) : '';

        $wrapper = get_block_wrapper_attributes([
            'class' => 'sikshya-course-search sik-f-scope',
        ]);

        $out = '<div ' . $wrapper . '>';
        $out .= '<form role="search" method="get" class="sikshya-course-search__form" action="' . esc_url($action) . '">';

        $out .= '<label class="sikshya-screen-reader-text" for="' . esc_attr($uid) . '">'
            . esc_html(sprintf(
                /* translators: %s: plural course label (e.g. courses) */
                __('Search %s', 'sikshya'),
                $lower
            ))
            . '</label>';

        $out .= '<input type="search" id="' . esc_attr($uid) . '" class="sikshya-course-search__input"'
            . ' name="s" value="' . esc_attr($current) . '"'
            . ' placeholder="' . esc_attr($placeholder) . '" />';

        /*
         * post_type is required: without it WordPress runs a site-wide search
         * and lands on the theme's search template, not the course archive.
         */
        $out .= '<input type="hidden" name="post_type" value="' . esc_attr(PostTypes::COURSE) . '" />';

        if ($show_categories) {
            $terms = get_terms([
                'taxonomy' => Taxonomies::COURSE_CATEGORY,
                'hide_empty' => true,
                'number' => 100,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            if (!is_wp_error($terms) && is_array($terms) && $terms !== []) {
                $select_id = $uid . '-category';
                $out .= '<label class="sikshya-screen-reader-text" for="' . esc_attr($select_id) . '">'
                    . esc_html__('Filter by category', 'sikshya') . '</label>';
                $out .= '<select id="' . esc_attr($select_id) . '" name="sikshya_cat" class="sikshya-course-search__select">';
                $out .= '<option value="">' . esc_html__('All categories', 'sikshya') . '</option>';
                foreach ($terms as $term) {
                    if (!$term instanceof \WP_Term) {
                        continue;
                    }
                    $out .= '<option value="' . esc_attr($term->slug) . '"'
                        . selected($current_cat, $term->slug, false) . '>'
                        . esc_html($term->name) . '</option>';
                }
                $out .= '</select>';
            }
        }

        if ($show_button) {
            $out .= '<button type="submit" class="sikshya-course-search__submit wp-element-button">'
                . esc_html($button_label) . '</button>';
        }

        $out .= '</form></div>';

        return $out;
    }
}
