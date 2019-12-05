<script type="text/html" id="sikshya-quiz-question-answer-template">
    <?php
    sikshya_load_admin_template('metabox.answer.template-dynamic',
        sikshya()->question->get_answer_args(
            array(
                'answer_value' => __('Answer', 'sikshya'),
                'answer_image' => '',
            )
        ));
    ?>
</script>