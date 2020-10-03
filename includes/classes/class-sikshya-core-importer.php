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

				unset($sikshya_custom_post_arr['term_taxonomy']);
				unset($sikshya_custom_post_arr['meta']);
				unset($sikshya_custom_post_arr['image_attributes']);
				
				$sik_post_id = wp_insert_post($sikshya_custom_post_arr);


				$term_taxonomies = isset($sikshya_custom_posts['term_taxonomy']) ? $sikshya_custom_posts['term_taxonomy'] : array();


				$sik_post_taxonomy_datas = array();
				foreach ($term_taxonomies as $sik_term_taxonomy) {

					$sik_term_id = wp_insert_term(
						sanitize_text_field($sik_term_taxonomy['name']),   // the term
						sanitize_text_field($sik_term_taxonomy['taxonomy']),   // the term
						array(
							'description' => sanitize_text_field($sik_term_taxonomy['description']),
							'slug' => $sik_term_taxonomy['slug'],
							'parent' => $sik_term_taxonomy['parent'],
						)
					);
					if (is_wp_error($sik_term_id)) {
						$error_data = isset($sik_term_id->error_data) ? $sik_term_id->error_data : array();
						$sik_term_id = isset($error_data['term_exists']) ? absint($error_data['term_exists']) : 0;
					} else {
						$sik_term_id = isset($sik_term_id['term_id']) ? absint($sik_term_id['term_id']) : 0;

					}
					if (absint($sik_term_id) > 0) {
						$sik_post_taxonomy_datas[$sik_term_taxonomy['taxonomy']][] = $sik_term_id;
					}
				}


				foreach ($sik_post_taxonomy_datas as $sik_term_tax => $sik_term_ids) {
					$sik_uniq_term_ids = array_unique($sik_term_ids);
					wp_set_object_terms($sik_post_id, $sik_uniq_term_ids, $sik_term_tax);

				}


				$updated_post_ids_mapping[$sikshya_custom_posts['ID']] = $sik_post_id;


				$image_attributes = isset($sikshya_custom_posts['image_attributes']) ? $sikshya_custom_posts['image_attributes'] : array();

				$this->import_image($sik_post_id, $image_attributes);
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

	private function import_image($post_id, $image_attr)
	{
		$url = isset($image_attr[0]) ? $image_attr[0] : null;
		if (is_null($url)) {
			return;
		}
		$pathinfo = pathinfo($url);
		$filename = $pathinfo['filename'] . '.' . $pathinfo['extension'];

		$uploaddir = wp_upload_dir();
		$uploadfile = $uploaddir['path'] . '/' . $filename;

		$contents = file_get_contents($url);
		$savefile = fopen($uploadfile, 'w');
		fwrite($savefile, $contents);
		fclose($savefile);


		$wp_filetype = wp_check_filetype(basename($filename), null);

		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => $filename,
			'post_content' => '',
			'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment($attachment, $uploadfile);
		$imagenew = get_post($attach_id);
		$fullsizepath = get_attached_file($imagenew->ID);
		$attach_data = wp_generate_attachment_metadata($attach_id, $fullsizepath);
		wp_update_attachment_metadata($attach_id, $attach_data);
		update_post_meta($post_id, '_thumbnail_id', $attach_id);


	}
}
