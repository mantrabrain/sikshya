<div class="group js-sikshya__sections-item" data-section-id="<?php echo $id; ?>">

    <div class="js-sikshya__sections-item_wrapper">
        <div class="ms-field ms-field-large">
            <label class="ms-label"
                   for="sikshya_section_<?php echo $id; ?>[section_title]"><?php _e('Section title', 'sikshya'); ?>
                :</label>
            <div class="ms-value">
                <input size="25" type="text" id="sikshya_section_<?php echo $id; ?>[section_title]"
                       name="sikshya_section[<?php echo $id; ?>][section_title]" class="js-sikshya__sections-item-title"
                       value="<?php echo esc_html($title); ?>"/>
            </div>
        </div>

        <div class="ms-label ms-label-wide"><?php _e('Lessons', 'sikshya'); ?>:</div>


        <div class="js-sikshya__sections-item_wrapper-lessons ms-lessons-list">
            <?php echo $lessonsHtml; ?>
        </div>
        <div class="sikshya-actions">
            <a class="js-sikshya__sections-lessons-add button button-primary button-large"
               href="javascript:void(0);">
                <span class="ms-icon ms-icon-lesson"></span>
                <?php _e('Add lesson', 'sikshya'); ?>
            </a>
        </div>
    </div>
</div>