<?php
/**
 * Course archive — catalog with filters, sort, grid/list.
 *
 * @package Sikshya
 */

sikshya_get_header();

$f = sikshya_course_archive_get_filter_request();
$archive_layout = \Sikshya\Services\CourseFrontendSettings::archiveLayout();
$grid_classes = 'sikshya-course-grid sikshya-course-grid--' . sanitize_html_class($archive_layout);
$show_sidebar = \Sikshya\Services\CourseFrontendSettings::areCourseFiltersEnabled();
// View is toggled client-side (localStorage) to avoid changing the URL.

$ctx = \Sikshya\Frontend\Public\ArchiveContextTemplateData::fromWpQuery();
$found = (int) $ctx['found'];
$max_pages = (int) $ctx['max_pages'];
$paged = (int) $ctx['paged'];

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
?>

<div
    class="sikshya-public sikshya-archive-courses"
    data-sikshya-archive-layout="<?php echo esc_attr($archive_layout); ?>"
>
    <div class="sikshya-container">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>
        <header class="sikshya-archive-courses__header">
            <h1 class="sikshya-archive-courses__title"><?php post_type_archive_title(); ?></h1>
            <?php the_archive_description('<div class="sikshya-archive-courses__desc">', '</div>'); ?>
            <p class="sikshya-archive-courses__tip">
                <?php
                esc_html_e(
                    'Open a course to see the full outline. Paid courses: add to cart, then checkout while signed in. Free courses usually enroll in one click after you log in.',
                    'sikshya'
                );
                ?>
            </p>
        </header>

        <div class="sikshya-archive-courses__layout<?php echo $show_sidebar ? '' : ' sikshya-archive-courses__layout--no-sidebar'; ?>">
            <?php if ($show_sidebar) : ?>
                <?php require __DIR__ . '/partials/course-archive-sidebar.php'; ?>
            <?php endif; ?>

            <div class="sikshya-archive-courses__main">
                <?php require __DIR__ . '/partials/course-archive-toolbar.php'; ?>

                <p class="sikshya-archive-courses__results" role="status">
                    <?php
                    echo esc_html(sprintf(
                        _n('%1$d %2$s found', '%1$d %3$s found', $found, 'sikshya'),
                        (int) $found,
                        strtolower($label_course),
                        strtolower($label_courses)
                    ));
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
                        <nav class="sikshya-pagination" aria-label="<?php echo esc_attr(sprintf(__('%s pagination', 'sikshya'), $label_courses)); ?>">
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
                        <p class="sikshya-archive-courses__empty-text">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: plural label (e.g. courses) */
                                __('No %s match your filters. Try adjusting filters or search.', 'sikshya'),
                                strtolower($label_courses)
                            ));
                            ?>
                        </p>
                        <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_post_type_archive_link(\Sikshya\Constants\PostTypes::COURSE)); ?>">
                            <?php echo esc_html(sprintf(__('View all %s', 'sikshya'), strtolower($label_courses))); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
sikshya_get_footer();
