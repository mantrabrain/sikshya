<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

/**
 * View-model for the legacy `templates/courses-grid.php` browse layout.
 *
 * @package Sikshya\Frontend\Public
 */
final class CoursesGridTemplateData
{
    /**
     * @return array{courses_query: \WP_Query, filter_categories: array<int, \WP_Term>}
     */
    public static function forBrowseGrid(): array
    {
        $paged = get_query_var('paged') ? (int) get_query_var('paged') : 1;

        $courses_query = new \WP_Query([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'paged' => $paged,
        ]);

        $filter_categories = get_terms([
            'taxonomy' => Taxonomies::COURSE_CATEGORY,
            'hide_empty' => true,
        ]);

        if (is_wp_error($filter_categories) || !is_array($filter_categories)) {
            $filter_categories = [];
        }

        return [
            'courses_query' => $courses_query,
            'filter_categories' => $filter_categories,
        ];
    }
}
