<div id="admin-editor-sik_quiz_question" class="sik-admin-editor sik-box-data admin-editor-sik-quiz-question"
     data-quiz-id="<?php echo absint($quiz_id) ?>" data-question-id="<?php echo absint($question_id) ?>">
    <div class="sik-box-data-head sik-row">
        <h3 class="heading"><?php echo __('Quiz Questions', 'sikshya') ?></h3>
    </div>
    <div class="sik-box-data-content">
        <div class="sik-box-body-content">
            <div class="sikshya-quiz-question-item-container">
                <?php


                foreach ($questions as $question) {
                    $params = array(
                        'title' => $question->post_title,
                        'question_id' => $question->ID
                    );
                    sikshya_load_admin_template('metabox.question.template-dynamic', $params);
                }
                ?>
            </div>
        </div>
        <?php
        sikshya_load_admin_template('metabox.question.add');
        ?>
    </div>
</div>
