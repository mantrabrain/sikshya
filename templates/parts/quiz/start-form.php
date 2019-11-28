<div class="sikshya-quiz-buttons">

    <form name="sikshya-start-quiz" class="sikshya-start-quiz" method="post">
        <button type="submit" class="button"><?php echo __('Start', 'sikshya') ?></button>
        <input type="hidden" name="sikshya_quiz_id" value="<?php echo absint($quiz_id) ?>"/>
        <input type="hidden" name="sikshya_course_id" value="<?php echo absint($course_id) ?>"/>
        <input type="hidden" name="sikshya_lesson_id" value="<?php echo absint($lesson_id) ?>"/>
        <input type="hidden" value="sikshya_start_quiz" name="sikshya_action"/>
        <input type="hidden" value="sikshya_notice" name="sikshya_start_quiz"/>
        <input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_start_quiz_nonce') ?>"
               name="sikshya_nonce"/>

    </form>


</div>