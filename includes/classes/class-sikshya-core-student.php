<?php

class Sikshya_Core_Student
{
	public function save()
	{

	}


	public function get_enrolled_count($course_id = 0)
	{

		$course_id = (absint($course_id)) > 0 ? $course_id : sikshya()->course->get_id();


		global $wpdb;

		$sql = "SELECT COUNT(*) AS total FROM " . SIKSHYA_DB_PREFIX . "user_items WHERE item_type=%s AND item_id = %d AND reference_type=%s AND parent_id=0;";
		$query = $wpdb->prepare($sql,
			SIKSHYA_COURSES_CUSTOM_POST_TYPE,
			$course_id,
			SIKSHYA_ORDERS_CUSTOM_POST_TYPE


		);
		$results = $wpdb->get_results($query);

		return is_array($results) ? absint($results[0]->total) : 0;

	}

	public function get_enrolled_count_from_courses($all_course_ids = array())
	{
		$item_ids_query = '';

		$item_ids_query_value = array(SIKSHYA_COURSES_CUSTOM_POST_TYPE);

		foreach ($all_course_ids as $all_course_index => $course_id) {
			$item_ids_query .= '%d';
			if (count($all_course_ids) != ($all_course_index + 1)) {
				$item_ids_query .= ', ';
			}
			$item_ids_query_value[] = $course_id;
		}
		$item_ids_query_value[] = SIKSHYA_ORDERS_CUSTOM_POST_TYPE;

		global $wpdb;


		$sql = "SELECT COUNT(*) AS total FROM " . SIKSHYA_DB_PREFIX . "user_items WHERE item_type=%s AND item_id in (" . $item_ids_query . ") AND reference_type=%s AND parent_id=0;";

		$query = $wpdb->prepare($sql,
			$item_ids_query_value


		);
		$results = $wpdb->get_results($query);


		return is_array($results) ? absint($results[0]->total) : 0;
	}

	public function update_billing_info($billing_data, $user_id = null)
	{
		$user_id = is_null($user_id) ? get_current_user_id() : $user_id;

		$billing_fields = sikshya()->checkout->billing_fields();

		foreach ($billing_fields as $field) {

			$id = isset($field['id']) ? $field['id'] : '';

			if (isset($billing_data[$id])) {

				update_user_meta($user_id, $id, sanitize_text_field($billing_data[$id]));
			}
		}

		return $user_id;
	}

}
