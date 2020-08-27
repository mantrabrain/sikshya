<div class="sikshya-course-meta-curriculum-tab-wrap">
	<div class="sikshya-course-meta-curriculum-tab">

		<?php
		do_action('sikshya_course_curriculum_tab_before');
		?>
		<input type="hidden" value="<?php echo esc_attr($active_tab); ?>" name="sikshya_course_active_tab"
			   class="sikshya_course_active_tab"/>


	</div>
	<button id="sik-add-new-section"
			data-action="sikshya_load_section_settings"
			data-nonce="<?php echo wp_create_nonce('wp_sikshya_load_section_settings_nonce') ?>"
			type="button" class="button button-primary sikshya-button btn-success"><span
			class="dashicons dashicons-menu"></span>
		<?php echo esc_html__('Add Section', 'sikshya'); ?>
	</button>
</div>
