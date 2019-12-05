<script type="text/html" id="sikshya-quiz-question-template">
    <?php
    sikshya_load_admin_template('metabox.question.template-dynamic',
        array(
            'title' => __('Question title', 'sikshya'),
            'question_id' => '{%question_id%}'
        )
    );
    ?>
</script>