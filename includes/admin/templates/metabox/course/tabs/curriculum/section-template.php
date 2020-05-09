<div class="course-section-template" id="course-section-template-<?php echo absint($section_id) ?>"
     data-section-id="<?php echo absint($section_id) ?>">
    <div class="heading">
        <h4><?php echo esc_attr($section_title); ?></h4>
        <div class="section-actions">
            <button
                    data-action="sikshya_load_lesson_form"
                    data-section-id="<?php echo absint($section_id) ?>"
                    data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_lesson_form_nonce') ?>"
                    type="button" class="sik-add-new-lesson button button-primary sikshya-button btn-success">
            <span
                    class="dashicons dashicons-media-text"></span> <?php echo esc_html__('Add Lesson', 'sikshya'); ?>
            </button>

            <button
                    data-action="sikshya_load_quiz_form"
                    data-section-id="<?php echo absint($section_id) ?>"
                    data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_quiz_form_nonce') ?>"
                    type="button" class="sik-add-new-quiz button button-primary sikshya-button btn-success">
            <span
                    class="dashicons dashicons-clock"></span> <?php echo esc_html__('Add Quiz', 'sikshya'); ?>
            </button>

        </div>
    </div>
    <div class="course-section-template-inner">

        <?php
        do_action('sikshya_course_curriculum_tab_lesson_quiz_template', $section_id);

        ?>
    </div>
    <a href="#" class="remove-section"><span class="dashicons dashicons-trash"></span></a>
    <div style="clear:both"></div>

</div>