<div id="admin-editor-sik_question" class="sik-admin-editor sik-box-data admin-editor-sik-question-answer"
     data-question-id="<?php echo esc_attr($question_id) ?>">
    <div class="sik-box-data-head sik-row">
        <h3 class="heading"><?php echo __('Question Answer', 'sikshya') ?></h3>
    </div>
    <div class="sik-box-data-content">
        <div class="sik-box-body-content">
            <div class="sikshya-quiz-question-answer-item-container">
                <div class="ms-field ms-field-large">
                    <label class="ms-label"
                           for="quiz_question<?php echo $name; ?>_type"><?php _e('Type of answer', 'sikshya'); ?>
                        :</label>
                    <div class="ms-value">
                        <select id="quiz_question<?php echo $typename; ?>_type" name="<?php echo $typename; ?>[type]"
                                class="js-sikshya_quiz-question-type">
                            <?php foreach (sikshya_question_answer_type() as $typeValue => $typeTitle) { ?>
                                <option value="<?php echo esc_attr($typeValue); ?>"<?php if ($type == $typeValue) { ?> selected="selected"<?php } ?>><?php echo esc_html($typeTitle); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="js-sikshya__wrapper-quiz-question-answers">

                    <?php

                    sikshya()->question->load_answer_dynamic($question_id);


                    ?>
                </div>
            </div>
            <?php
            sikshya_load_admin_template('metabox.answer.add');

            ?>
        </div>

    </div>
</div>
