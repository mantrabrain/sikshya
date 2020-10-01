<?php

class Sikshya_Admin_Form_Handler
{
	public function __construct()
	{
		add_action('admin_init', array($this, 'export'));


	}

	public function export()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_export') {
			return;
		}
		sikshya()->helper->validate_nonce(true);

		$sikshya_custom_post_types_for_export = isset($_POST['sikshya_custom_post_types_for_export']) ? $_POST['sikshya_custom_post_types_for_export'] : array();

		$export_content = array();

		global $wpdb;

		$sik_post_type_string = array_fill(0, count($sikshya_custom_post_types_for_export), '%s');


		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$all_post_types_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type IN (" . implode(',', $sik_post_type_string) . ')', $sikshya_custom_post_types_for_export));

		foreach ($all_post_types_result as $result_index => $result) {

			$sik_post_id = $result->ID;

			$sik_meta_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key NOT IN(%s, %s)", $sik_post_id, '_edit_lock', '_edit_last'));

			$all_post_types_result[$result_index]->meta = $sik_meta_result;
		}

		$args = array(
			'content' => $all_post_types_result
		);

		sikshya_export($args);
	}


}

new Sikshya_Admin_Form_Handler();
