<div class="sikshya-topbar-item sikshya-topbar-mark-to-done">
    <div class="sikshya-single-lesson-segment sikshya-lesson-compelte-form-wrap">

        <form method="post">
            <input type="hidden" value="sikshya_complete_lesson" name="sikshya_action"/>
            <input type="hidden" value="sikshya_complete_lesson_notice" name="sikshya_notice"/>
            <input type="hidden"
                   value="<?php echo wp_create_nonce('wp_sikshya_complete_lesson_nonce') ?>"
                   name="sikshya_nonce"/>
            <input type="hidden" value="<?php echo absint($lesson_id) ?>" name="lesson_id">
            <input type="hidden" value="<?php echo absint($course_id) ?>" name="course_id">

            <button type="submit" class="course-complete-button sikshya-button"
                    name="complete_lesson_btn"
                    value="complete_lesson"><?php echo __('Complete Lesson', 'sikshya') ?>
            </button>
        </form>
    </div>
</div>