<?php
/**
 * Course post type archive: filters, sort, and query integration (GET params).
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register hooks for course archive filtering.
 */
function sikshya_course_archive_filters_bootstrap(): void
{
    add_action('pre_get_posts', 'sikshya_course_archive_pre_get_posts', 10, 1);
    add_action('pre_get_posts', 'sikshya_course_taxonomy_archives_pre_get_posts', 10, 1);
}

sikshya_course_archive_filters_bootstrap();

/**
 * Match course catalog page size on category/tag archives.
 *
 * @param \WP_Query $query Query.
 */
function sikshya_course_taxonomy_archives_pre_get_posts(\WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (
        !$query->is_tax(
            [
                \Sikshya\Constants\Taxonomies::COURSE_CATEGORY,
                \Sikshya\Constants\Taxonomies::COURSE_TAG,
            ]
        )
    ) {
        return;
    }

    $query->set('posts_per_page', 12);
}

/**
 * Query args to keep on taxonomy pagination (e.g. search keyword).
 *
 * @return array<string, string>
 */
function sikshya_course_taxonomy_get_preserved_query_args(): array
{
    $s = get_search_query();
    if ($s === '') {
        return [];
    }

    return ['s' => $s];
}

/**
 * Adjust main query on course archive from GET parameters.
 *
 * @param \WP_Query $query Query.
 */
function sikshya_course_archive_pre_get_posts(\WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive(\Sikshya\Constants\PostTypes::COURSE)) {
        return;
    }

    $filters = sikshya_course_archive_get_filter_request();

    // Search keyword (WordPress `s`).
    if ($filters['s'] !== '') {
        $query->set('s', $filters['s']);
    }

    // Posts per page (bounded).
    $ppp = (int) $filters['per_page'];
    if ($ppp >= 6 && $ppp <= 48) {
        $query->set('posts_per_page', $ppp);
    }

    if ($filters['category_slug'] !== '') {
        $query->set(
            'tax_query',
            [
                [
                    'taxonomy' => \Sikshya\Constants\Taxonomies::COURSE_CATEGORY,
                    'field' => 'slug',
                    'terms' => $filters['category_slug'],
                ],
            ]
        );
    }

    $level_block = [];
    if ($filters['level'] !== '') {
        $level_block = [
            'relation' => 'OR',
            [
                'key' => '_sikshya_difficulty',
                'value' => $filters['level'],
                'compare' => '=',
            ],
            [
                'key' => '_sikshya_course_difficulty',
                'value' => $filters['level'],
                'compare' => '=',
            ],
        ];
    }

    $price_block = [];
    if ($filters['price'] === 'free') {
        $price_block = [
            'relation' => 'OR',
            [
                'key' => '_sikshya_price',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_sikshya_price',
                'value' => '',
                'compare' => '=',
            ],
            [
                'key' => '_sikshya_price',
                'value' => 0,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ],
        ];
    } elseif ($filters['price'] === 'paid') {
        $price_block = [
            'key' => '_sikshya_price',
            'value' => 0,
            'type' => 'NUMERIC',
            'compare' => '>',
        ];
    }

    $meta_parts = array_values(array_filter([$level_block, $price_block]));
    if ($meta_parts !== []) {
        if (count($meta_parts) === 1) {
            $query->set('meta_query', $meta_parts[0]);
        } else {
            $query->set(
                'meta_query',
                array_merge(
                    ['relation' => 'AND'],
                    $meta_parts
                )
            );
        }
    }

    switch ($filters['sort']) {
        case 'title_asc':
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
            break;
        case 'title_desc':
            $query->set('orderby', 'title');
            $query->set('order', 'DESC');
            break;
        case 'price_asc':
            $query->set('meta_key', '_sikshya_price');
            $query->set('orderby', 'meta_value_num');
            $query->set('order', 'ASC');
            break;
        case 'price_desc':
            $query->set('meta_key', '_sikshya_price');
            $query->set('orderby', 'meta_value_num');
            $query->set('order', 'DESC');
            break;
        case 'date_asc':
            $query->set('orderby', 'date');
            $query->set('order', 'ASC');
            break;
        default:
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            break;
    }
}

/**
 * Sanitized GET state for archive UI and pagination preservation.
 *
 * @return array{
 *   s: string,
 *   category_slug: string,
 *   level: string,
 *   price: string,
 *   sort: string,
 *   view: string,
 *   per_page: int
 * }
 */
function sikshya_course_archive_get_filter_request(): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter query args.
    $get = isset($_GET) ? wp_unslash($_GET) : [];

    $s = isset($get['s']) ? sanitize_text_field((string) $get['s']) : '';

    $cat = isset($get['sikshya_cat']) ? sanitize_title((string) $get['sikshya_cat']) : '';
    if ($cat === '') {
        $cat = isset($get['course_cat']) ? sanitize_title((string) $get['course_cat']) : '';
    }

    $level = isset($get['sikshya_level']) ? sanitize_key((string) $get['sikshya_level']) : '';
    if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        $level = '';
    }

    $price = isset($get['sikshya_price']) ? sanitize_key((string) $get['sikshya_price']) : '';
    if (!in_array($price, ['free', 'paid', 'all', ''], true)) {
        $price = '';
    }
    if ($price === 'all' || $price === '') {
        $price = '';
    }

    $sort = isset($get['sikshya_sort']) ? sanitize_key((string) $get['sikshya_sort']) : 'date_desc';
    $allowed_sort = ['date_desc', 'date_asc', 'title_asc', 'title_desc', 'price_asc', 'price_desc'];
    if (!in_array($sort, $allowed_sort, true)) {
        $sort = 'date_desc';
    }

    $view = isset($get['sikshya_view']) ? sanitize_key((string) $get['sikshya_view']) : 'grid';
    if (!in_array($view, ['grid', 'list'], true)) {
        $view = 'grid';
    }

    $per_page = isset($get['sikshya_per_page']) ? (int) $get['sikshya_per_page'] : 12;
    if ($per_page < 6 || $per_page > 48) {
        $per_page = 12;
    }

    return [
        's' => $s,
        'category_slug' => $cat,
        'level' => $level,
        'price' => $price,
        'sort' => $sort,
        'view' => $view,
        'per_page' => $per_page,
    ];
}

/**
 * Build query args to preserve in pagination links.
 *
 * @return array<string, string>
 */
function sikshya_course_archive_get_preserved_query_args(): array
{
    $f = sikshya_course_archive_get_filter_request();
    $out = [];

    if ($f['s'] !== '') {
        $out['s'] = $f['s'];
    }
    if ($f['category_slug'] !== '') {
        $out['sikshya_cat'] = $f['category_slug'];
    }
    if ($f['level'] !== '') {
        $out['sikshya_level'] = $f['level'];
    }
    if ($f['price'] !== '') {
        $out['sikshya_price'] = $f['price'];
    }
    if ($f['sort'] !== 'date_desc') {
        $out['sikshya_sort'] = $f['sort'];
    }
    if ($f['view'] !== 'grid') {
        $out['sikshya_view'] = $f['view'];
    }
    if ($f['per_page'] !== 12) {
        $out['sikshya_per_page'] = (string) $f['per_page'];
    }

    return $out;
}

/**
 * Archive URL with current filters, optionally overridden.
 *
 * @param array<string, mixed> $overrides Keys: s, category_slug, level, price, sort, view, per_page.
 * @return string
 */
function sikshya_course_archive_build_url(array $overrides = []): string
{
    $f = array_merge(sikshya_course_archive_get_filter_request(), $overrides);

    $args = [];
    if ($f['s'] !== '') {
        $args['s'] = $f['s'];
    }
    if ($f['category_slug'] !== '') {
        $args['sikshya_cat'] = $f['category_slug'];
    }
    if ($f['level'] !== '') {
        $args['sikshya_level'] = $f['level'];
    }
    if ($f['price'] !== '') {
        $args['sikshya_price'] = $f['price'];
    }
    if (($f['sort'] ?? 'date_desc') !== 'date_desc') {
        $args['sikshya_sort'] = $f['sort'];
    }
    if (($f['view'] ?? 'grid') !== 'grid') {
        $args['sikshya_view'] = $f['view'];
    }
    if ((int) ($f['per_page'] ?? 12) !== 12) {
        $args['sikshya_per_page'] = (string) (int) $f['per_page'];
    }

    $url = get_post_type_archive_link(\Sikshya\Constants\PostTypes::COURSE);
    if ($url === false || $url === '') {
        return home_url('/');
    }

    return add_query_arg($args, $url);
}
