<?php
/**
 * Course category index — all categories at /{course-category-base}/.
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

sikshya_get_header();

$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');

$hide_empty = (bool) apply_filters('sikshya_course_category_index_hide_empty', true);

$terms = get_terms(
    [
        'taxonomy' => Taxonomies::COURSE_CATEGORY,
        'hide_empty' => $hide_empty,
        'orderby' => 'name',
        'order' => 'ASC',
    ]
);

$page_title = sprintf(
    /* translators: %s: plural course label (e.g. Courses) */
    __('%s categories', 'sikshya'),
    $label_courses
);
?>

<div class="sikshya-public sikshya-archive-courses sikshya-course-category-index">
    <div class="sikshya-container">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>
        <header class="sikshya-taxonomy-courses__header">
            <nav class="sikshya-taxonomy-courses__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a class="sikshya-taxonomy-courses__crumb" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-taxonomy-courses__crumb-sep" aria-hidden="true">/</span>
                <span class="sikshya-taxonomy-courses__crumb sikshya-taxonomy-courses__crumb--current"><?php echo esc_html($page_title); ?></span>
            </nav>

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
        </header>

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
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of categories */
                        _n('%d category', '%d categories', count($terms), 'sikshya'),
                        count($terms)
                    )
                );
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
        <?php endif; ?>
    </div>
</div>

<?php
sikshya_get_footer();
