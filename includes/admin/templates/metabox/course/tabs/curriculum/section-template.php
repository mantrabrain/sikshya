<div class="course-section-template" id="course-section-template-<?php echo absint($section_id) ?>"  data-section-id="<?php echo absint($section_id) ?>">
    <div class="heading">
        <h4><?php echo esc_attr($section_title); ?></h4>
        <button
                data-action="sikshya_load_lesson_form"
                data-section-id="<?php echo absint($section_id) ?>"
                data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_lesson_form_nonce') ?>"
                type="button" class="sik-add-new-lesson button button-primary sikshya-button btn-success">
            <span
                    class="dashicons dashicons-media-text"></span>
            Add Lesson
        </button>

        <button
                data-action="sikshya_load_quiz_form"
                data-section-id="<?php echo absint($section_id) ?>"
                data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_quiz_form_nonce') ?>"
                type="button" class="sik-add-new-quiz button button-primary sikshya-button btn-success">
            <span
                    class="dashicons dashicons-clock"></span>Add Quiz
        </button>
        <input type="text" value="<?php echo $section_id; ?>" name="sikshya_course_content1[section_ids][]"/>
    </div>
    <div class="course-section-template-inner">

        <?php
        do_action('sikshya_course_curriculum_tab_lesson_quiz_template', $section_id);

        ?>
    </div>
</div>