<?php
function sikshya_update_0011_section_meta()
{
	global $wpdb;

	$all_section_ids = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type='" . SIKSHYA_SECTIONS_CUSTOM_POST_TYPE . "'");
	if ($all_section_ids) {

		foreach ($all_section_ids as $id) {

			add_post_meta($id->ID, 'section_order', 0, true);
		}
	}
}

