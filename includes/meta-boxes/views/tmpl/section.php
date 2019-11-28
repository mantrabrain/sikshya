<div class="group js-sikshya__sections-item" data-section-id="<?php echo $id; ?>">
    <?php
    if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
        ?>
        <h3 class="js-group__label js-sikshya__sections-item_title ms-head"
            data-default="<?php echo esc_html($title); ?>">
            <span class="dashicons dashicons-menu"></span>
            <span class="js-group__label_text"><?php echo esc_html($title); ?></span>
            <span class="sikshya-count"><?php echo sikshya()->section->get_child_count_text($id); ?></span>
            <a class="js-sikshya__remove-section" href="#"><span
                        class="dashicons dashicons-trash"></span></a>
        </h3>
    <?php } ?>

    <div class="js-sikshya__sections-item_wrapper">
        <?php
        if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
            ?>
            <div class="ms-field ms-field-large">
                <label class="ms-label"
                       for="sikshya_section_<?php echo $id; ?>[section_title]"><?php _e('Section title', 'sikshya'); ?>
                    :</label>
                <div class="ms-value">
                    <input size="25" type="text" id="sikshya_section_<?php echo $id; ?>[section_title]"
                           name="sikshya_section[<?php echo $id; ?>][section_title]"
                           class="js-sikshya__sections-item-title"
                           value="<?php echo esc_html($title); ?>"/>
                </div>
            </div>
        <?php } ?>

        <?php if (sikshya_is_screen(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {
            ?>
            <div class="ms-label ms-label-wide"><?php _e('Lessons', 'sikshya'); ?>:</div>

            <div class="js-sikshya__sections-item_wrapper-lessons ms-lessons-list">
                <?php echo $lessonsHtml; ?>
            </div>
            <div class="sikshya-actions">
                <a class="js-sikshya__sections-lessons-add button button-primary button-large"
                   href="javascript:void(0);">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php _e('Add lesson', 'sikshya'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>