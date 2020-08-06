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

	public function add($data)
	{
		global $wpdb;

		$prepare_args = array(
			$data['user_id'],
			$data['username'],
			$data['first_name'],
			$data['last_name'],
			$data['email'],
			$data['country'],
			$data['postcode'],
			$data['city'],
			$data['state'],
			$data['phone'],
			$data['street_address_1'],
			$data['street_address_2'],
			$data['date_last_active'],
			$data['date_registered']
		);

		$sql = "(
		user_id, username, first_name, last_name, email, country, postcode, city, state, phone, street_address_1, street_address_2,  date_last_active, date_registered)
VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";

		if (absint($data['user_id']) < 1) {
			$sql = "(
		first_name, last_name, email, country, postcode, city, state, phone, street_address_1, street_address_2,  date_last_active, date_registered)
VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";
			$prepare_args = array(
				$data['first_name'],
				$data['last_name'],
				$data['email'],
				$data['country'],
				$data['postcode'],
				$data['city'],
				$data['state'],
				$data['phone'],
				$data['street_address_1'],
				$data['street_address_2'],
				$data['date_last_active'],
				$data['date_registered']
			);
		}
		$insert_sql_query = "INSERT INTO " . SIKSHYA_DB_PREFIX . "students {$sql}";

		$query = $wpdb->prepare($insert_sql_query, $prepare_args);

		$wpdb->query($query);

		return $wpdb->insert_id;
	}


}
