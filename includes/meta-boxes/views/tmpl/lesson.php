<div class="group js-sikshya__lesson-item" data-lesson-id="<?php echo $id; ?>">
    <?php if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
        ?>
        <h3 class="js-group__label ms-head" data-default="<?php echo esc_html($title); ?>">
            <span class="dashicons dashicons-media-text"></span>
            <span class="js-group__label_text"><?php echo esc_html($title); ?></span>
            <span class="sikshya-count"><?php echo sikshya()->lesson->get_child_count_text($id); ?></span>

            <a class="js-sikshya__remove-lesson" href="#"><span
                        class="dashicons dashicons-trash"></span></a>
            <a target="_blank" class="sikshya-edit-lesson"
               href="<?php echo esc_url(admin_url('post.php?post=' . $id . '&action=edit')) ?>"><span
                        class="dashicons dashicons-media-text" style="margin-right:10px;"></span></a>
        </h3>
    <?php } ?>
    <div class="sikshya__lesson-form js-sikshya__lesson-form">
        <?php if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
            ?>
            <div class="ms-field ms-field-large">
                <label class="ms-label"
                       for="sikshya_lesson_<?php echo $id; ?>[sikshya_lesson]"><?php _e('Lesson title', 'sikshya'); ?>
                    :</label>
                <div class="ms-value">
                    <input type="text" id="sikshya_lesson_<?php echo $id; ?>[sikshya_lesson]"
                           name="sikshya_lesson<?php echo $name; ?>[lessons_title]" size="25"
                           class="js-sikshya__lesson-title" value="<?php echo esc_html($title); ?>"/>
                </div>
            </div>
        <?php } ?>
        <?php if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
            ?>
            <div class="customEditor custom_upload_buttons">
                <div class="js-sikshya__lesson-editor"><?php echo $content; ?></div>
            </div>

            <div class="js-sikshya__wrapper-quizes ms-quizes-list">
                <?php echo $quizesHtml; ?>
            </div>
            <div class="sikshya-actions">
                <a class="js-sikshya__lesson-quizes-add button button-primary button-large"
                   href="javascript:void(0);">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Add quiz', 'sikshya'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>