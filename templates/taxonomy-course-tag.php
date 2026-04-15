<?php
/**
 * Course tag archive (same grid pattern as categories).
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;

get_header();
?>

<div class="sikshya-course-category-archive sikshya-public">
    <section class="sikshya-category-header">
        <div class="sikshya-container">
            <div class="sikshya-category-breadcrumb">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="sikshya-breadcrumb-link"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-breadcrumb-separator">/</span>
                <a href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>" class="sikshya-breadcrumb-link"><?php esc_html_e('Courses', 'sikshya'); ?></a>
                <span class="sikshya-breadcrumb-separator">/</span>
                <span class="sikshya-breadcrumb-current"><?php single_term_title(); ?></span>
            </div>
            <div class="sikshya-category-info">
                <h1 class="sikshya-category-title"><?php single_term_title(); ?></h1>
                <?php if (term_description()) : ?>
                    <div class="sikshya-category-description"><?php echo term_description(); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="sikshya-category-courses">
        <div class="sikshya-container">
            <?php if (have_posts()) : ?>
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
                            'prev_text' => __('Previous', 'sikshya'),
                            'next_text' => __('Next', 'sikshya'),
                            'type' => 'list',
                        ]
                    );
                    if (!empty($links)) {
                        echo wp_kses_post($links);
                    }
                    ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('No courses with this tag.', 'sikshya'); ?></p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
get_footer();
