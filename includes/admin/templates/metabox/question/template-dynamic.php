<div class="sikshya-quiz-question-item">
    <div class="sikshya-question-item-wrap">
        <div class="question-header">
            <h2 class="header-title"><?php echo esc_html($title); ?></h2>
            <div class="right-button">
                <span class="sik-toggle dashicons dashicons-arrow-down-alt2"></span>
            </div>
        </div>
        <div class="question-content hide-if-js">
            <div class="question-answer-template">
                <div class="sik-input-group">
                    <label for="question-title"><?php echo __('Question title', 'sikshya') ?></label>
                    <input type="text" value="<?php echo esc_attr($title); ?>"/>
                </div>
                <?php

                sikshya()->question->load($question_id, false);
                //sikshya_load_admin_template('metabox.answer.add');

                ?>


            </div>
            <div class="question-setting-template">

            </div>
        </div>
    </div>

</div>