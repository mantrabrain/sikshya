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