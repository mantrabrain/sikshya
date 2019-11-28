<form name="sikshya-complete-question" class="sikshya-complete-question-form" method="post">

    <button type="submit"><?php echo __('Complete', 'sikshya') ?></button>
    <input type="hidden" name="sikshya_selected_answer"/>
    <input type="hidden" name="sikshya_question_id" value="<?php echo absint($question_id); ?>">
    <input type="hidden" name="sikshya_quiz_id" value="<?php echo absint($quiz_id); ?>">
    <input type="hidden" name="sikshya_course_id" value="<?php echo absint($course_id); ?>">
    <input type="hidden" value="sikshya_complete_quiz_question" name="sikshya_action"/>
    <input type="hidden" value="sikshya_notice" name="sikshya_complete_quiz_question"/>
    <input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_complete_quiz_question_nonce') ?>"
           name="sikshya_nonce"/>

</form>