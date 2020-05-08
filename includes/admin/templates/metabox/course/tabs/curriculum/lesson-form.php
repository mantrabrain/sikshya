<form method="post" class="lesson-form" action="<?php echo esc_url(admin_url('admin-ajax.php')) ?>"
      data-section-id="<?php echo absint($section_id); ?>"
>
    <label for="lesson_title">
        <span>Lesson Title</span>
        <input type="text" name="lesson_title"/>
        <input type="hidden" name="sikshya_nonce"
               value="<?php echo wp_create_nonce('wp_sikshya_add_lesson_nonce'); ?>"/>
        <input type="hidden" name="action"
               value="sikshya_add_lesson"/>
        <input type="hidden" name="section_id"
               value="<?php echo absint($section_id); ?>"/>
    </label>
    <button type="submit" class="sikshya-button btn-success">Submit</button>
</form>
