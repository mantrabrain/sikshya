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

if (!function_exists('sikshya_update_0015_logs_update')) {

	function sikshya_update_0015_logs_update()
	{
		Sikshya_Install::verify_base_tables(true);

		sikshya()->get_log_dir();
	}
}
