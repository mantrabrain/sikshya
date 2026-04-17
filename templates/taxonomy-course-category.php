<?php
/**
 * Course category archive — same course cards as /courses, simple 3-column grid.
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;

get_header();

global $wp_query;

$found = isset($wp_query->found_posts) ? (int) $wp_query->found_posts : 0;
$max_pages = isset($wp_query->max_num_pages) ? (int) $wp_query->max_num_pages : 0;
$paged = (int) get_query_var('paged');
if ($paged < 1) {
    $paged = (int) get_query_var('page');
}
if ($paged < 1) {
    $paged = 1;
}
?>

<div class="sikshya-public sikshya-archive-courses sikshya-taxonomy-courses sikshya-taxonomy-courses--category">
    <div class="sikshya-container">
        <header class="sikshya-taxonomy-courses__header">
            <nav class="sikshya-taxonomy-courses__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a class="sikshya-taxonomy-courses__crumb" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-taxonomy-courses__crumb-sep" aria-hidden="true">/</span>
                <a class="sikshya-taxonomy-courses__crumb" href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>"><?php esc_html_e('Course Categories', 'sikshya'); ?></a>
                <span class="sikshya-taxonomy-courses__crumb-sep" aria-hidden="true">/</span>
                <span class="sikshya-taxonomy-courses__crumb sikshya-taxonomy-courses__crumb--current"><?php single_term_title(); ?></span>
            </nav>

            <h1 class="sikshya-archive-courses__title"><?php single_term_title(); ?></h1>

            <?php if (term_description()) : ?>
                <div class="sikshya-archive-courses__desc sikshya-taxonomy-courses__description">
                    <?php echo term_description(); ?>
                </div>
            <?php endif; ?>

            <p class="sikshya-archive-courses__results" role="status">
                <?php
                printf(
                    /* translators: %d: number of courses */
                    esc_html(_n('%d course found', '%d courses found', $found, 'sikshya')),
                    (int) $found
                );
                ?>
            </p>
        </header>

        <?php if (have_posts()) : ?>
            <div class="sikshya-course-grid sikshya-course-grid--cols-3">
                <?php
                while (have_posts()) :
                    the_post();
                    sikshya_render_course_card(get_post(), 'default');
                endwhile;
                ?>
            </div>

            <?php if ($max_pages > 1) : ?>
                <nav class="sikshya-pagination" aria-label="<?php esc_attr_e('Category pagination', 'sikshya'); ?>">
                    <?php
                    $links = paginate_links(
                        [
                            'total' => $max_pages,
                            'current' => $paged,
                            'mid_size' => 2,
                            'end_size' => 1,
                            'prev_text' => __('Previous', 'sikshya'),
                            'next_text' => __('Next', 'sikshya'),
                            'type' => 'list',
                            'add_args' => sikshya_course_taxonomy_get_preserved_query_args(),
                        ]
                    );
                    if (!empty($links)) {
                        echo wp_kses_post($links);
                    }
                    ?>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="sikshya-archive-courses__empty">
                <p class="sikshya-archive-courses__empty-text">
                    <?php
                    printf(
                        /* translators: %s: category name */
                        esc_html__('No courses in "%s" yet.', 'sikshya'),
                        esc_html(single_term_title('', false))
                    );
                    ?>
                </p>
                <a class="sikshya-button sikshya-button--primary" href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>">
                    <?php esc_html_e('Browse all courses', 'sikshya'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
