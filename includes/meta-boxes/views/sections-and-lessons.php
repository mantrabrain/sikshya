<input type="hidden" class="js-coursessikshya_section_lessons"
       data-tooltip="<?php _e('Here you can fill in the actual content of your courses. If you want to know how it works, visit https://mantrabrain.com/hilfe', 'sikshya'); ?>">
<script type="text/html" id="sikshya-accordion__section-template">
    <?php echo sikshya()->section->render_tmpl('{%section_id%}', __('New section', 'sikshya'), '', '', '{%lessons%}'); ?>
</script>
<script type="text/html" id="sikshya-accordion__lesson-template">
    <?php echo sikshya()->lesson->render_tmpl('{%lesson_id%}', '[{%section_id%}][{%lesson_id%}]', __('New lesson', 'sikshya'), '{%editor%}', false, '{%quizes%}'); ?>
</script>
<script type="text/html" id="sikshya-accordion__quiz-template">
    <?php echo sikshya()->quiz->render_tmpl('{%quiz_id%}', 'lessons_quiz[{%section_id%}][{%lesson_id%}][{%quiz_id%}]', array('title' => __('New quiz', 'sikshya')), '{%editor%}'); ?>
</script>
<script type="text/html" id="sikshya-accordion__quiz-question-template">
    <?php echo sikshya()->question->render_tmpl('{%question_id%}', 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}]', array('title' => __('New question', 'sikshya'), 'type' => 'text')); ?>
</script>
<script type="text/html" id="sikshya-accordion__quiz-question-answer-template">
    <?php echo sikshya()->question->render_answer_tmpl('{%question_id%}_{%answer_id%}', '{%answer_id%}', 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers][{%answer_id%}]', array(), 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers_correct][]', array(), 'text'); ?>
</script>
<script type="text/html" id="sikshya-editor">
    <?php echo sikshya_render_editor('', '{%name%}', '{%id%}'); ?>
</script>

<div class="js-sikshya__sections ms-metabox-sections-lessons sikshya-metabox-sections-lessons">
    <?php

    if (!empty($template_vars)) {

        sikshya_load_metabox_html($template_vars);
    }
    ?>
</div>
<div class="sikshya-actions">
    <a class="js-sikshya__sections-add button button-primary button-large" href="javascript:void(0);">
        <span class="dashicons dashicons-menu"></span>
        <?php _e('Add section', 'sikshya'); ?>
    </a>
</div>