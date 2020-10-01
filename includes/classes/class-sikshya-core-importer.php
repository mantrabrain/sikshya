<?php

class Sikshya_Core_Importer
{
	public function import($target_file)
	{
		if (!file_exists($target_file)) {
			return false;
		}
		try {

			$json_content = file_get_contents($target_file);

			$sikshya_content_array = json_decode($json_content, true);

			$sikshya_content_array = is_array($sikshya_content_array) ? $sikshya_content_array : array();

			$updated_post_ids_mapping = array();

			foreach ($sikshya_content_array as $sikshya_custom_posts) {


				$sikshya_custom_post_arr = $sikshya_custom_posts;

				unset($sikshya_custom_post_arr['ID']);
				unset($sikshya_custom_post_arr['post_author']);
				unset($sikshya_custom_post_arr['term_taxonomy']);
				unset($sikshya_custom_post_arr['meta']);
				unset($sikshya_custom_post_arr['image_attributes']);
				unset($sikshya_custom_post_arr['guid']);

				$sik_post_id = wp_insert_post($sikshya_custom_post_arr);

				$updated_post_ids_mapping[$sikshya_custom_posts['ID']] = $sik_post_id;


				//die('die after one post insert - ' . $id);

			}

			foreach ($sikshya_content_array as $sikshya_custom_posts1) {

				$sik_post_metas = isset($sikshya_custom_posts1['meta']) ? $sikshya_custom_posts1['meta'] : array();

				$sik_post_id_for_meta = isset($updated_post_ids_mapping[$sikshya_custom_posts1['ID']]) ? $updated_post_ids_mapping[$sikshya_custom_posts1['ID']] : 0;

				if (absint($sik_post_id_for_meta) > 0) {

					foreach ($sik_post_metas as $sik_post_meta) {

						$sik_meta_value = $sik_post_meta['meta_value'];

						switch ($sik_post_meta['meta_key']) {
							case "course_id":
							case "section_id":
							case "quiz_id":
								$sik_meta_value = absint($sik_meta_value);
								$sik_meta_value = isset($updated_post_ids_mapping[$sik_meta_value]) ? $updated_post_ids_mapping[$sik_meta_value] : '';
								break;
							default:
								$sik_meta_value = $sik_post_meta['meta_value'];
								break;
						}
						add_post_meta($sik_post_id_for_meta, sanitize_text_field($sik_post_meta['meta_key']), $sik_meta_value);

					}
				}
			}

		} catch (Exception $e) {
			return false;
		}

	}
}
