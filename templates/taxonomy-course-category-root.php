<?php
/**
 * Course category index — all categories at /{course-category-base}/.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
	exit;
}

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Frontend\Site\PublicPageUrls;

sikshya_get_header();

$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');

$hide_empty = (bool) apply_filters('sikshya_course_category_index_hide_empty', true);

/*
 * Pagination — pull the current page off `paged` (works for both pretty
 * permalinks like /course-category/page/2/ and the ?paged=2 fallback).
 * Per-page count is filterable so sites with hundreds of categories can
 * tune density without rewriting the template.
 */
$per_page = (int) apply_filters('sikshya_course_category_index_per_page', 12);
$per_page = $per_page > 0 ? $per_page : 12;

$current_page = max(1, (int) get_query_var('paged'));
if ($current_page <= 1) {
    $current_page = max(1, (int) get_query_var('page'));
}

$total_terms = (int) wp_count_terms(
    [
        'taxonomy' => Taxonomies::COURSE_CATEGORY,
        'hide_empty' => $hide_empty,
    ]
);
$max_pages = $per_page > 0 ? (int) ceil($total_terms / $per_page) : 1;
if ($max_pages < 1) {
    $max_pages = 1;
}
if ($current_page > $max_pages) {
    $current_page = $max_pages;
}
$offset = ($current_page - 1) * $per_page;

$terms = get_terms(
    [
        'taxonomy' => Taxonomies::COURSE_CATEGORY,
        'hide_empty' => $hide_empty,
        'orderby' => 'name',
        'order' => 'ASC',
        'number' => $per_page,
        'offset' => $offset,
    ]
);

$page_title = sprintf(
    /* translators: %s: plural course label (e.g. Courses) */
    __('%s categories', 'sikshya'),
    $label_courses
);

$breadcrumb_items = [
    [
        'label' => __('Home', 'sikshya'),
        'url' => home_url('/'),
    ],
    [
        'label' => $page_title,
    ],
];
?>

<div class="sikshya-public sikshya-archive-courses sikshya-course-category-index sik-f-scope">
    <header class="sikshya-course-lp__masthead">
        <div class="sikshya-container sikshya-container--course sikshya-course-lp__masthead-inner">
            <div class="sikshya-taxonomy-courses__header sikshya-archive-courses__header">
                <?php
                $items = $breadcrumb_items;
                require __DIR__ . '/partials/course-discovery-breadcrumb.php';
                ?>

                <h1 class="sikshya-archive-courses__title"><?php echo esc_html($page_title); ?></h1>

                <p class="sikshya-archive-courses__tip">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: plural label for courses (lowercase) */
                            __('Browse by topic, then open a category to see %s.', 'sikshya'),
                            strtolower($label_courses)
                        )
                    );
                    ?>
                </p>
            </div>
        </div>
    </header>

    <div class="sikshya-container">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>

        <?php if (is_wp_error($terms) || empty($terms)) : ?>
            <div class="sikshya-archive-courses__empty">
                <p class="sikshya-archive-courses__empty-text">
                    <?php esc_html_e('No categories are available yet.', 'sikshya'); ?>
                </p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>">
                    <?php echo esc_html(sprintf(__('Browse all %s', 'sikshya'), strtolower($label_courses))); ?>
                </a>
            </div>
        <?php else : ?>
            <p class="sikshya-archive-courses__results" role="status">
                <?php
                if ($max_pages > 1) {
                    echo esc_html(
                        sprintf(
                            /* translators: 1: total category count, 2: current page, 3: total pages */
                            __('%1$d categories · page %2$d of %3$d', 'sikshya'),
                            $total_terms,
                            $current_page,
                            $max_pages
                        )
                    );
                } else {
                    echo esc_html(
                        sprintf(
                            /* translators: %d: number of categories */
                            _n('%d category', '%d categories', $total_terms, 'sikshya'),
                            $total_terms
                        )
                    );
                }
                ?>
            </p>

            <ul class="sikshya-course-category-index__grid" role="list">
                <?php foreach ($terms as $term) : ?>
                    <?php
                    if (!$term instanceof \WP_Term) {
                        continue;
                    }
                    $link = get_term_link($term);
                    if (is_wp_error($link)) {
                        continue;
                    }
                    $thumb = function_exists('sikshya_course_category_featured_image_url')
                        ? sikshya_course_category_featured_image_url($term, 'medium')
                        : '';
                    ?>
                    <li class="sikshya-course-category-index__item">
                        <a class="sikshya-course-category-index__card" href="<?php echo esc_url($link); ?>">
                            <?php if ($thumb !== '') : ?>
                                <span class="sikshya-course-category-index__thumb">
                                    <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" decoding="async" />
                                </span>
                            <?php endif; ?>
                            <span class="sikshya-course-category-index__name"><?php echo esc_html($term->name); ?></span>
                            <?php if ($term->description !== '') : ?>
                                <span class="sikshya-course-category-index__desc"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($term->description), 24)); ?></span>
                            <?php endif; ?>
                            <span class="sikshya-course-category-index__count">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %d: course count in category */
                                        _n('%d course', '%d courses', (int) $term->count, 'sikshya'),
                                        (int) $term->count
                                    )
                                );
                                ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($max_pages > 1) : ?>
                <nav class="sikshya-pagination" aria-label="<?php echo esc_attr(sprintf(__('%s categories pagination', 'sikshya'), $label_courses)); ?>">
                    <?php
                    $category_root_url = PublicPageUrls::courseCategoryIndexUrl();
                    $links = paginate_links(
                        [
                            'base' => trailingslashit($category_root_url) . 'page/%#%/',
                            'format' => '?paged=%#%',
                            'total' => $max_pages,
                            'current' => $current_page,
                            'mid_size' => 2,
                            'end_size' => 1,
                            'prev_text' => __('Previous', 'sikshya'),
                            'next_text' => __('Next', 'sikshya'),
                            'type' => 'list',
                        ]
                    );
                    if (!empty($links)) {
                        echo wp_kses_post($links);
                    }
                    ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
sikshya_get_footer();
