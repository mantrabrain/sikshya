<hr/>
<h2>Sikshya Exporter</h2>
<form method="post" class="sikshya_export_form">

	<?php

	foreach ($sikshya_custom_post_type_lists as $custom_post_type => $custom_post_type_title) {
		echo '<p><label><input checked type="checkbox" name="sikshya_custom_post_types_for_export[]" value="' . esc_attr($custom_post_type) . '">' . esc_html($custom_post_type_title) . '</label></p>';
	}
	?>
	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
							 value="Download Export File"></p>

	<input type="hidden" value="sikshya_export" name="sikshya_action"/>
	<input type="hidden" value="sikshya_export" name="sikshya_export_notice"/>
	<input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_export_nonce') ?>"
		   name="sikshya_nonce"/>
</form>
