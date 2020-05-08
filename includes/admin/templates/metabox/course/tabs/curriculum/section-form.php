<form method="post" class="section-form" action="<?php echo esc_url(admin_url('admin-ajax.php')) ?>">
    <label for="section_title">
        <span>Section Title</span>
        <input type="text" name="section_title"/>
        <input type="hidden" name="sikshya_nonce"
               value="<?php echo wp_create_nonce('wp_sikshya_add_section_nonce'); ?>"/>
        <input type="hidden" name="action"
               value="sikshya_add_section"/>
    </label>
    <button type="submit" class="sikshya-button btn-success">Submit</button>
</form>
