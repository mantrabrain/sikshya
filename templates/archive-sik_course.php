<?php
/**
 * Course archive — catalog with filters, sort, grid/list.
 *
 * @package Sikshya
 */

get_header();

$f = sikshya_course_archive_get_filter_request();
$grid_classes = 'sikshya-course-grid';
// View is toggled client-side (localStorage) to avoid changing the URL.

global $wp_query;
$found = isset($wp_query->found_posts) ? (int) $wp_query->found_posts : 0;
?>

<div class="sikshya-public sikshya-archive-courses">
    <div class="sikshya-container">
        <header class="sikshya-archive-courses__header">
            <h1 class="sikshya-archive-courses__title"><?php post_type_archive_title(); ?></h1>
            <?php the_archive_description('<div class="sikshya-archive-courses__desc">', '</div>'); ?>
        </header>

        <div class="sikshya-archive-courses__layout">
            <?php require __DIR__ . '/partials/course-archive-sidebar.php'; ?>

            <div class="sikshya-archive-courses__main">
                <?php require __DIR__ . '/partials/course-archive-toolbar.php'; ?>

                <p class="sikshya-archive-courses__results" role="status">
                    <?php
                    printf(
                        /* translators: %d: number of courses */
                        esc_html(_n('%d course found', '%d courses found', $found, 'sikshya')),
                        (int) $found
                    );
                    ?>
                </p>

                <?php if (have_posts()) : ?>
                    <div class="<?php echo esc_attr($grid_classes); ?>">
                        <?php
                        while (have_posts()) :
                            the_post();
                            sikshya_render_course_card(get_post(), 'default');
                        endwhile;
                        ?>
                    </div>

                    <div class="sikshya-pagination">
                        <?php
                        $links = paginate_links(
                            [
                                'total' => (int) $wp_query->max_num_pages,
                                'current' => max(1, (int) get_query_var('paged')),
                                'mid_size' => 2,
                                'prev_text' => __('Previous', 'sikshya'),
                                'next_text' => __('Next', 'sikshya'),
                                'type' => 'list',
                                'add_args' => sikshya_course_archive_get_preserved_query_args(),
                            ]
                        );
                        if (!empty($links)) {
                            echo wp_kses_post($links);
                        }
                        ?>
                    </div>
                <?php else : ?>
                    <div class="sikshya-archive-courses__empty">
                        <p class="sikshya-archive-courses__empty-text"><?php esc_html_e('No courses match your filters. Try adjusting filters or search.', 'sikshya'); ?></p>
                        <a class="sikshya-button sikshya-button--primary" href="<?php echo esc_url(get_post_type_archive_link(\Sikshya\Constants\PostTypes::COURSE)); ?>">
                            <?php esc_html_e('View all courses', 'sikshya'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
