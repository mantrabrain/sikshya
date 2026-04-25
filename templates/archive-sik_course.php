<?php
/**
 * Course archive — catalog with filters, sort, grid/list.
 *
 * @package Sikshya
 */

get_header();

$f = sikshya_course_archive_get_filter_request();
$archive_layout = \Sikshya\Services\CourseFrontendSettings::archiveLayout();
$grid_classes = 'sikshya-course-grid sikshya-course-grid--' . sanitize_html_class($archive_layout);
$show_sidebar = \Sikshya\Services\CourseFrontendSettings::areCourseFiltersEnabled();
// View is toggled client-side (localStorage) to avoid changing the URL.

$ctx = \Sikshya\Frontend\Public\ArchiveContextTemplateData::fromWpQuery();
$found = (int) $ctx['found'];
$max_pages = (int) $ctx['max_pages'];
$paged = (int) $ctx['paged'];
?>

<div class="sikshya-public sikshya-archive-courses">
    <div class="sikshya-container">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>
        <header class="sikshya-archive-courses__header">
            <h1 class="sikshya-archive-courses__title"><?php post_type_archive_title(); ?></h1>
            <?php the_archive_description('<div class="sikshya-archive-courses__desc">', '</div>'); ?>
        </header>

        <div class="sikshya-archive-courses__layout<?php echo $show_sidebar ? '' : ' sikshya-archive-courses__layout--no-sidebar'; ?>">
            <?php if ($show_sidebar) : ?>
                <?php require __DIR__ . '/partials/course-archive-sidebar.php'; ?>
            <?php endif; ?>

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

                    <?php if ($max_pages > 1) : ?>
                        <nav class="sikshya-pagination" aria-label="<?php esc_attr_e('Courses pagination', 'sikshya'); ?>">
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
                                    'add_args' => sikshya_course_archive_get_preserved_query_args(),
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
                        <p class="sikshya-archive-courses__empty-text"><?php esc_html_e('No courses match your filters. Try adjusting filters or search.', 'sikshya'); ?></p>
                        <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_post_type_archive_link(\Sikshya\Constants\PostTypes::COURSE)); ?>">
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
