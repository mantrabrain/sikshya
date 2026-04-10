<?php
/**
 * Single lesson — {@see \Sikshya\Frontend\Public\LessonTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\LessonTemplateData;

get_header();

while (have_posts()) :
    the_post();
    $vm = LessonTemplateData::forPost(get_post());
    ?>

<div class="sikshya-public sikshya-single-lesson">
    <div class="sikshya-container sikshya-container--narrow">
        <nav class="sikshya-breadcrumb sikshya-breadcrumb--public">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
            <span class="sikshya-breadcrumb-sep">/</span>
            <a href="<?php echo esc_url($vm['urls']['courses']); ?>"><?php esc_html_e('Courses', 'sikshya'); ?></a>
        </nav>
        <h1><?php the_title(); ?></h1>
        <div class="sikshya-prose">
            <?php the_content(); ?>
        </div>
        <?php do_action('sikshya_single_lesson_after_content', $vm); ?>
    </div>
</div>

    <?php
endwhile;

get_footer();
