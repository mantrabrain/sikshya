<div class="sikshya-course-meta-curriculum-tab">

    <?php
    do_action('sikshya_course_curriculum_tab_before');
    ?>
    <button id="sik-add-new-section"
            data-action="sikshya_load_section_settings"
            data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_section_settings_nonce') ?>"
            type="button" class="button button-primary sikshya-button btn-success">Add Section
    </button>

</div>