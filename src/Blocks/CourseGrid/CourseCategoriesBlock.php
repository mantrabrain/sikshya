<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

use Sikshya\Constants\Taxonomies;

/**
 * Server renderer for sikshya/course-categories.
 *
 * A catalog landing page needs three things: a search field, a way into the
 * categories, and a grid. The grid block was the missing piece; these two make
 * it usable without a page builder, which is what a block-theme site has
 * instead of the plugin's own archive template.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseCategoriesBlock
{
    public const NAME = 'sikshya/course-categories';

    public const MAX_TERMS = 60;

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes = []): string
    {
        $columns = self::clamp($attributes['columns'] ?? 4, 1, 6, 4);
        $limit = self::clamp($attributes['limit'] ?? 8, 1, self::MAX_TERMS, 8);
        $show_count = !isset($attributes['showCount']) || (bool) $attributes['showCount'];
        $show_image = !isset($attributes['showImage']) || (bool) $attributes['showImage'];
        $hide_empty = !isset($attributes['hideEmpty']) || (bool) $attributes['hideEmpty'];
        $parent_only = !empty($attributes['topLevelOnly']);
        $style = isset($attributes['displayStyle']) ? (string) $attributes['displayStyle'] : 'tiles';
        if (!in_array($style, ['tiles', 'pills', 'list'], true)) {
            $style = 'tiles';
        }

        $args = [
            'taxonomy' => Taxonomies::COURSE_CATEGORY,
            'hide_empty' => $hide_empty,
            'number' => $limit,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        if ($parent_only) {
            $args['parent'] = 0;
        }

        /**
         * get_terms() arguments for the course-categories block.
         *
         * @param array<string, mixed> $args
         * @param array<string, mixed> $attributes
         */
        $args = apply_filters('sikshya_course_categories_block_args', $args, $attributes);

        $terms = get_terms(is_array($args) ? $args : []);
        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return '';
        }

        $wrapper = get_block_wrapper_attributes([
            'class' => 'sikshya-cg-cats sikshya-cg-cats--' . sanitize_html_class($style) . ' sik-f-scope',
            'style' => '--sikshya-grid-columns:' . $columns . ';',
        ]);

        $out = '<nav ' . $wrapper . ' aria-label="' . esc_attr__('Course categories', 'sikshya') . '">';
        $out .= '<ul class="sikshya-cg-cats__list" role="list">';

        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $link = get_term_link($term);
            if (!is_string($link)) {
                continue;
            }

            $image = '';
            if ($show_image && function_exists('sikshya_course_category_featured_image_url')) {
                $image = (string) sikshya_course_category_featured_image_url($term, 'medium');
            }

            $out .= '<li class="sikshya-cg-cats__item">';
            $out .= '<a class="sikshya-cg-cats__link" href="' . esc_url($link) . '">';

            if ($show_image) {
                if ($image !== '') {
                    $out .= '<span class="sikshya-cg-cats__media">'
                        . '<img src="' . esc_url($image) . '" alt="" loading="lazy" decoding="async" />'
                        . '</span>';
                } else {
                    $out .= '<span class="sikshya-cg-cats__media sikshya-cg-cats__media--placeholder" aria-hidden="true"></span>';
                }
            }

            $out .= '<span class="sikshya-cg-cats__name">' . esc_html($term->name) . '</span>';

            if ($show_count) {
                $count = (int) $term->count;
                $out .= '<span class="sikshya-cg-cats__count">'
                    . esc_html(sprintf(
                        /* translators: %s: number of courses in the category */
                        _n('%s course', '%s courses', $count, 'sikshya'),
                        number_format_i18n($count)
                    ))
                    . '</span>';
            }

            $out .= '</a></li>';
        }

        $out .= '</ul></nav>';

        return $out;
    }

    /**
     * @param mixed $value
     */
    private static function clamp($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
