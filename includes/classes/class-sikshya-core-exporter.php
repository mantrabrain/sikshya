<?php

class Sikshya_Core_Exporter
{
	public function export($sikshya_custom_post_types_for_export = array())
	{
		foreach ($sikshya_custom_post_types_for_export as $sik_cpt_for_export_index => $sik_cpt_for_export) {
			if (strpos($sik_cpt_for_export, 'sik_') === false) {
				unset($sikshya_custom_post_types_for_export[$sik_cpt_for_export_index]);
			}
		}
		global $wpdb;

		$sik_post_type_string = array_fill(0, count($sikshya_custom_post_types_for_export), '%s');

		$export_content = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type IN (" . implode(',', $sik_post_type_string) . ')', $sikshya_custom_post_types_for_export));

		foreach ($export_content as $result_index => $result) {

			$sik_post_id = $result->ID;

			$sik_post_type = $result->post_type ? $result->post_type : '';

			$sik_meta_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key NOT IN(%s, %s)", $sik_post_id, '_edit_lock', '_edit_last'));

			$sik_thub_id = get_post_meta($sik_post_id, '_thumbnail_id', true);

			$sik_image_attributes = wp_get_attachment_image_src($sik_thub_id, 'full');


			$sik_term_taxonomy = $wpdb->get_results($wpdb->prepare("SELECT t.*, tt.taxonomy, tt.description, tt.parent, tt.count FROM wp_terms t INNER JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id
INNER JOIN wp_term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN wp_posts p ON p.ID = tr.object_id WHERE p.post_type=%s AND p.ID=%d", $sik_post_type, $sik_post_id));

			$export_content[$result_index]->term_taxonomy = $sik_term_taxonomy;

			$export_content[$result_index]->meta = $sik_meta_result;

			$export_content[$result_index]->image_attributes = $sik_image_attributes;

		}

		$args = array(
			'content' => $export_content
		);

		sikshya_export($args);

	}
}
