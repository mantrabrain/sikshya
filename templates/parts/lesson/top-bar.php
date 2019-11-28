<?php

$post_id = get_the_ID();

$section_id = get_post_meta($post_id, 'section_id', true);

$course_id = get_post_meta($section_id, 'course_id', true);

?>

    <div class="sikshya-topbar-item sikshya-hide-sidebar-bar">
        <a href="" class="sikshya-lesson-sidebar-hide-bar">
            <i class="dashicons dashicons-menu"></i> </a>
        <a href="<?php echo esc_url(get_post_permalink($course_id)); ?>"
           class="sikshya-topbar-home-btn">
            <i class="dashicons dashicons-arrow-left-alt"></i> <?php echo __('Go to course home', 'sikshya') ?>
        </a>
    </div>
    <div class="sikshya-topbar-item sikshya-topbar-content-title-wrap">
        <?php
        $icon = 'dashicons dashicons-media-text';
        if (sikshya_get_current_post_type() == SIKSHYA_QUIZZES_CUSTOM_POST_TYPE) {
            $icon = 'dashicons dashicons-clock';
        } else if (SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE) {
            $icon = 'dashicons dashicons-warning';
        }
        ?>
        <i class="<?php echo esc_attr($icon); ?>"></i><?php echo get_the_title() ?>
    </div>

<?php

