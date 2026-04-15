<?php
/**
 * Course archive — catalog grid (data from main query).
 *
 * @package Sikshya
 */

get_header();
?>

<div class="sikshya-public sikshya-archive-courses">
    <div class="sikshya-container">
        <header class="sikshya-archive-courses__header">
            <h1 class="sikshya-archive-courses__title"><?php post_type_archive_title(); ?></h1>
            <?php the_archive_description('<div class="sikshya-archive-courses__desc">', '</div>'); ?>
        </header>

        <div class="sikshya-course-grid">
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
                ]
            );
            if (!empty($links)) {
                // paginate_links returns HTML list; safe for output.
                echo wp_kses_post($links);
            }
            ?>
        </div>
    </div>
</div>

<?php
get_footer();
