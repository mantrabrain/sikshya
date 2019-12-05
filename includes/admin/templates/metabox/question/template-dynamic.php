<div class="sikshya-quiz-question-item">
    <div class="sikshya-question-item-wrap">
        <div class="question-header">
            <h2 class="header-title"><input type="text" value="<?php echo esc_attr($title); ?>"
                                            name="<?php echo esc_attr($question_name) ?>"/></h2>
            <div class="right-button">
                <span class="sik-toggle dashicons dashicons-arrow-down-alt2"></span>
            </div>
        </div>
        <div class="question-content hide-if-js">
            <div class="question-answer-template">
                <?php

                sikshya()->question->load($question_id, false);


                ?>


            </div>
            <div class="question-setting-template">

            </div>
        </div>
    </div>

</div>