<?php

namespace Sikshya\Shortcodes;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

/**
 * Shortcode: [sikshya_courses]
 *
 * Lists published courses using the same reusable card partial as the catalog/archive.
 *
 * Supported attributes:
 * - per_page: int (default 9, max 50)
 * - columns: int (currently only 3 is a fixed layout; other values use auto grid)
 * - view: "grid" | "list" (default "grid")
 * - category: slug (course category)
 * - tag: slug (course tag)
 * - search: string (search term)
 * - orderby: "date" | "title" | "price" (default "date")
 * - order: "asc" | "desc" (default "desc")
 * - pagination: "1" | "0" (default "1")
 *
 * Pagination uses the `sikshya_courses_page` query arg to avoid interfering with the main query.
 *
 * @package Sikshya\Shortcodes
 */
final class CoursesShortcode
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        add_shortcode('sikshya_courses', [self::class, 'render']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render($atts = []): string
    {
        if (!function_exists('sikshya_render_course_card')) {
            return '';
        }

        $a = shortcode_atts(
            [
                'per_page' => 9,
                'columns' => 0,
                'view' => 'grid',
                'category' => '',
                'tag' => '',
                'search' => '',
                'orderby' => 'date',
                'order' => 'desc',
                'pagination' => 1,
            ],
            is_array($atts) ? $atts : [],
            'sikshya_courses'
        );

        $per_page = (int) $a['per_page'];
        if ($per_page < 1) {
            $per_page = 9;
        }
        if ($per_page > 50) {
            $per_page = 50;
        }

        $columns = (int) $a['columns'];
        if ($columns < 0 || $columns > 6) {
            $columns = 0;
        }

        $view = sanitize_key((string) $a['view']);
        if (!in_array($view, ['grid', 'list'], true)) {
            $view = 'grid';
        }

        $category = sanitize_title((string) $a['category']);
        $tag = sanitize_title((string) $a['tag']);
        $search = sanitize_text_field((string) $a['search']);

        $orderby = sanitize_key((string) $a['orderby']);
        if (!in_array($orderby, ['date', 'title', 'price'], true)) {
            $orderby = 'date';
        }

        $order = strtoupper(sanitize_key((string) $a['order'])) === 'ASC' ? 'ASC' : 'DESC';
        $pagination = (string) $a['pagination'] === '0' ? 0 : 1;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging arg.
        $page_raw = isset($_GET['sikshya_courses_page']) ? wp_unslash($_GET['sikshya_courses_page']) : 1;
        $paged = max(1, (int) $page_raw);

        $query_args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $pagination ? $paged : 1,
            's' => $search !== '' ? $search : '',
            'ignore_sticky_posts' => true,
        ];

        if ($orderby === 'title') {
            $query_args['orderby'] = 'title';
            $query_args['order'] = $order;
        } elseif ($orderby === 'price') {
            // Match archive sorting behaviour (legacy meta key used across installs).
            $query_args['meta_key'] = '_sikshya_price';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = $order;
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order'] = $order;
        }

        $tax_query = [];
        if ($category !== '') {
            $tax_query[] = [
                'taxonomy' => Taxonomies::COURSE_CATEGORY,
                'field' => 'slug',
                'terms' => [$category],
            ];
        }
        if ($tag !== '') {
            $tax_query[] = [
                'taxonomy' => Taxonomies::COURSE_TAG,
                'field' => 'slug',
                'terms' => [$tag],
            ];
        }
        if ($tax_query !== []) {
            $query_args['tax_query'] = $tax_query;
        }

        $q = new \WP_Query($query_args);

        $grid_classes = 'sikshya-course-grid';
        if ($view === 'list') {
            $grid_classes .= ' sikshya-course-grid--list';
        } elseif ($columns === 3) {
            $grid_classes .= ' sikshya-course-grid--cols-3';
        }

        $label_courses = function_exists('sikshya_label_plural')
            ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend')
            : __('Courses', 'sikshya');

        ob_start();

        echo '<div class="sikshya-public sikshya-shortcode-courses">';

        if ($q->have_posts()) {
            echo '<div class="' . esc_attr($grid_classes) . '">';
            while ($q->have_posts()) {
                $q->the_post();
                $p = get_post();
                if ($p instanceof \WP_Post) {
                    sikshya_render_course_card($p, 'default');
                }
            }
            echo '</div>';

            if ($pagination && (int) $q->max_num_pages > 1) {
                $current_url = remove_query_arg('sikshya_courses_page');
                $links = paginate_links(
                    [
                        'total' => (int) $q->max_num_pages,
                        'current' => $paged,
                        'mid_size' => 2,
                        'end_size' => 1,
                        'prev_text' => __('Previous', 'sikshya'),
                        'next_text' => __('Next', 'sikshya'),
                        'type' => 'list',
                        'base' => esc_url_raw(add_query_arg('sikshya_courses_page', '%#%', $current_url)),
                        'format' => '',
                    ]
                );
                if (!empty($links)) {
                    echo '<nav class="sikshya-pagination" aria-label="' . esc_attr(sprintf(__('%s pagination', 'sikshya'), $label_courses)) . '">';
                    echo wp_kses_post($links);
                    echo '</nav>';
                }
            }
        } else {
            echo '<div class="sikshya-archive-courses__empty">';
            echo '<p class="sikshya-archive-courses__empty-text">' . esc_html(sprintf(__('No %s found.', 'sikshya'), strtolower((string) $label_courses))) . '</p>';
            echo '</div>';
        }

        echo '</div>';

        wp_reset_postdata();

        return (string) ob_get_clean();
    }
}

