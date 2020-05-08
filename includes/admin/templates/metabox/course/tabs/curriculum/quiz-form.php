<form method="post" class="quiz-form" action="<?php echo esc_url(admin_url('admin-ajax.php')) ?>"
data-section-id="<?php echo absint($section_id); ?>">
    <label for="quiz_title">
        <span>Quiz Title</span>
        <input type="text" name="quiz_title"/>
        <input type="hidden" name="sikshya_nonce"
               value="<?php echo wp_create_nonce('wp_sikshya_add_quiz_nonce'); ?>"/>
        <input type="hidden" name="action"
               value="sikshya_add_quiz"/>
        <input type="hidden" name="section_id"
               value="<?php echo absint($section_id); ?>"/>
    </label>
    <button type="submit" class="sikshya-button btn-success">Submit</button>
</form>
