<?php
/**
 * Instructor author archive page.
 *
 * Lists published courses created by the current author.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

sikshya_get_header();

$instructor_slug = (string) get_query_var(\Sikshya\Services\PermalinkService::INSTRUCTOR_VAR);
$author = null;
if ($instructor_slug !== '') {
    $author = get_user_by('slug', sanitize_title($instructor_slug));
}

$author_id = $author instanceof \WP_User ? (int) $author->ID : 0;
$display_name = $author instanceof \WP_User ? (string) $author->display_name : __('Instructor', 'sikshya');
$bio = $author instanceof \WP_User ? (string) get_the_author_meta('description', $author_id) : '';

$paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
$per_page = (int) get_option('posts_per_page', 12);
if ($per_page < 1) {
    $per_page = 12;
}

$courses_query = new \WP_Query(
    [
        'post_type' => \Sikshya\Constants\PostTypes::COURSE,
        'post_status' => 'publish',
        'author' => $author_id,
        'paged' => $paged,
        'posts_per_page' => $per_page,
        'orderby' => 'date',
        'order' => 'DESC',
    ]
);

$found = (int) $courses_query->found_posts;

if ($author_id <= 0) {
    global $wp_query;
    if ($wp_query instanceof \WP_Query) {
        $wp_query->set_404();
    }
    status_header(404);
    nocache_headers();
}
?>

<div class="sikshya-public sikshya-archive-courses">
    <div class="sikshya-container">
        <header class="sikshya-archive-courses__header">
            <h1 class="sikshya-archive-courses__title">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: author display name */
                        __('Courses by %s', 'sikshya'),
                        $display_name
                    )
                );
                ?>
            </h1>
            <?php if ($bio !== '') : ?>
                <div class="sikshya-archive-courses__desc">
                    <p><?php echo esc_html($bio); ?></p>
                </div>
            <?php endif; ?>
        </header>

        <div class="sikshya-archive-courses__main">
            <p class="sikshya-archive-courses__results" role="status">
                <?php
                echo esc_html(
                    sprintf(
                        _n('%d course found', '%d courses found', $found, 'sikshya'),
                        $found
                    )
                );
                ?>
            </p>

            <?php if ($courses_query->have_posts()) : ?>
                <div class="sikshya-course-grid sikshya-course-grid--grid">
                    <?php
                    while ($courses_query->have_posts()) :
                        $courses_query->the_post();
                        $course = get_post();
                        if ($course instanceof \WP_Post) {
                            sikshya_render_course_card($course, 'default');
                        }
                    endwhile;
                    ?>
                </div>

                <?php if ((int) $courses_query->max_num_pages > 1) : ?>
                    <nav class="sikshya-pagination" aria-label="<?php esc_attr_e('Author courses pagination', 'sikshya'); ?>">
                        <?php
                        $links = paginate_links(
                            [
                                'total' => (int) $courses_query->max_num_pages,
                                'current' => $paged,
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
            <?php else : ?>
                <div class="sikshya-archive-courses__empty">
                    <p class="sikshya-archive-courses__empty-text">
                        <?php esc_html_e('This instructor has not published any courses yet.', 'sikshya'); ?>
                    </p>
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_post_type_archive_link(\Sikshya\Constants\PostTypes::COURSE)); ?>">
                        <?php esc_html_e('Browse all courses', 'sikshya'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
wp_reset_postdata();
sikshya_get_footer();
