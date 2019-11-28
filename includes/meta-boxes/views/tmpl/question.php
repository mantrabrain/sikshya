<div class="group js-sikshya__quiz-question-item" data-question-id="<?php echo esc_attr($id); ?>">
    <h3 class="js-group__label ms-head" data-default="<?php echo esc_html($title); ?>">
        <span class="dashicons dashicons-warning"></span>

        <span class="js-group__label_text"><?php echo esc_html($title); ?></span>
        <?php
        if (isset($count_text)) {
            ?>
            <span class="sikshya-count"> <?php echo esc_html($count_text); ?></span>
        <?php } ?>
        <a class="js-sikshya__remove-quiz-question" href="#">
            <span class="dashicons dashicons-trash"></span></a>
    </h3>
    <div class="sikshya__quiz-question-form js-sikshya__quiz-question-form">
        <div class="ms-field ms-field-large">
            <label class="ms-label"
                   for="quiz_question<?php echo $id; ?>_text"><?php _e('Question text', 'sikshya'); ?>:</label>
            <div class="ms-value">
                <input type="text" id="quiz_question<?php echo $id; ?>_text" name="<?php echo $name; ?>[title]"
                       size="25" class="js-sikshya__quiz-question-title" value="<?php echo esc_attr($title); ?>"/>
            </div>
        </div>
        <div class="ms-field ms-field-large">
            <label class="ms-label"
                   for="quiz_question<?php echo $id; ?>_type"><?php _e('Type of answer', 'sikshya'); ?>:</label>
            <div class="ms-value">
                <select id="quiz_question<?php echo $id; ?>_type" name="<?php echo $name; ?>[type]"
                        class="js-sikshya__quiz-question-type">
                    <?php foreach ($allowedTypes as $typeValue => $typeTitle) { ?>
                        <option value="<?php echo esc_attr($typeValue); ?>"<?php if ($type == $typeValue) { ?> selected="selected"<?php } ?>><?php echo esc_html($typeTitle); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="js-sikshya__wrapper-quiz-question-answers ms-quiz-question-answers-list">
            <?php echo $answersHtml; ?>
        </div>
        <div class="sikshya-actions js-sikshya__quiz-question-answers-actions">
            <a class="js-sikshya__quiz-question-answers-add button button-primary button-large"
               href="javascript:void(0);">
                <?php _e('Add answer', 'sikshya'); ?>
            </a>
        </div>
    </div>
</div>