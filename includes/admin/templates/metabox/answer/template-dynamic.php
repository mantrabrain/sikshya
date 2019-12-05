<div class="ms-field ms-field-large sikshya-quiz-question-answer-item">
    <label class="ms-label" for="quiz_question_answer<?php echo $id; ?>_value"><?php _e('Answer', 'sikshya'); ?>
        :</label>
    <div class="ms-value">
        <div<?php if ($question_type !== 'single' && $question_type !== 'multi') { ?> style="display: none;" <?php } ?>
                class="js-sikshya__quiz-question-answer_type js-sikshya__quiz-question-answer_type_single js-sikshya__quiz-question-answer_type_multi">
            <input type="text" id="quiz_question_answer<?php echo $id; ?>_value" name="<?php echo $name; ?>[value]"
                   size="25" value="<?php echo esc_attr($answer_value); ?>"/>
            <a class="js-sikshya__remove-quiz-question-answer" href="#"><span
                        class="ui-icon ui-icon-closethick"></span></a>
        </div>
        <div<?php if ($question_type !== 'single_image' && $question_type !== 'multi_image') { ?> style="display: none;" <?php } ?>
                class="js-sikshya__quiz-question-answer_type js-sikshya__quiz-question-answer_type_single_image js-sikshya__quiz-question-answer_type_multi_image js-sikshya__image_upload_wrapper">
            <input type="text" class="js-sikshya__image_upload_value"
                   id="quiz_question_answer<?php echo $id; ?>_image" name="<?php echo $name; ?>[image]" size="25"
                   value="<?php echo esc_attr($answer_image); ?>"/>
            <input type="button" class="js-sikshya__image_upload_button button"
                   value="<?php _e('Upload image', 'sikshya'); ?>"/>
            <a class="js-sikshya__remove-quiz-question-answer" href="#"><span
                        class="ui-icon ui-icon-closethick"></span></a>
        </div>
        <div>
            <label><input
                        type="<?php if ($question_type === 'single' || $question_type === 'single_image') { ?>radio<?php } else { ?>checkbox<?php } ?>"
                        name="<?php echo $correct_answer_name; ?>" class="js-sikshya__quiz-question-answer_correct"
                        value="<?php echo esc_attr($answer_id); ?>"<?php if (in_array($answer_id, $correct_answers)) { ?> checked="checked"<?php } ?>> <?php _e('Correct answer', 'sikshya'); ?>
            </label>
        </div>
    </div>
</div>