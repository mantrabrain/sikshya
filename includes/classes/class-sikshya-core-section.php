<?php

class Sikshya_Core_Section
{
	public function add_section($section_title)
	{
		if ('' == $section_title) {

			return null;
		}
		$args = array(
			'post_title' => $section_title,
			'post_content' => '',
			'post_status' => 'publish',
			'post_type' => SIKSHYA_SECTIONS_CUSTOM_POST_TYPE,
		);
		$section_id = wp_insert_post($args);

		return array('section_id' => $section_id, 'section_title' => $section_title);

	}

	public function save($section_ids = array(), $course_id = 0, $sikshya_section_order = array())
	{

		$updated_section_ids = array();

		foreach ($section_ids as $section_id) {

			$section_id = absint($section_id);

			if (SIKSHYA_SECTIONS_CUSTOM_POST_TYPE === get_post_type($section_id) && $course_id > 0) {

				$section_order = isset($sikshya_section_order[$section_id]) ? absint($sikshya_section_order[$section_id]) : 0;

				update_post_meta($section_id, 'course_id', $course_id);
				update_post_meta($section_id, 'section_order', $section_order);

				$updated_section_ids[] = $section_id;
			}


		}

		return $updated_section_ids;

	}

	public function update_meta($sections, $section_id)
	{
		foreach ($sections as $section_id_ => $section_content) {

		}

		return $section_id;
	}


	function render_tmpl($id, $title, $description = '', $image = '', $lessonsHtml = '')
	{
		ob_start();

		include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/section.php';

		return ob_get_clean();
	}


	function get_all_by_course($course_id)
	{
		if ($course_id instanceof \WP_Post) {
			$course_id = $course_id->ID;
		}

		$args = array(
			'numberposts' => -1,
			'no_found_rows' => true,
			'order' => 'ASC',
			'orderby' => 'meta_value title',       // Or post by custom field
			'meta_key' => 'section_order', // By which custom field*/
			'post_type' => SIKSHYA_SECTIONS_CUSTOM_POST_TYPE,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array( //check to see if date has been filled out
						'key' => 'section_order',
						'value' => array(''),
						'compare' => 'NOT IN'

					),
					array( //if no date has been added show these posts too
						'key' => 'section_order',
						'compare' => 'NOT EXISTS',
						'value' => ''
					)

				),
				array(
					'key' => 'course_id',
					'value' => (int)$course_id
				),
			),
		);


		$data = get_posts($args);

		return $data;
	}

	public function get_child_count_text($section_id)
	{
		if (sikshya_is_new_post($section_id)) {
			return '';
		}
		$section_id = absint($section_id);
		if ($section_id < 1) {
			return '';
		}
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) as total, p.post_type
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm
ON p.ID=pm.post_id
WHERE pm.meta_key = 'section_id'
AND pm.meta_value = %d  and p.post_status='publish'
GROUP BY p.post_type having p.post_type in (%s,%s) ORDER BY FIELD (p.post_type, %s, %s)",
			$section_id,
			SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
			SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
			SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
			SIKSHYA_QUIZZES_CUSTOM_POST_TYPE
		);

		$results = $wpdb->get_results($sql);

		$count_string = '';

		foreach ($results as $result) {

			$total = isset($result->total) ? $result->total : 0;

			$post_type = isset($result->post_type) ? $result->post_type : '';

			switch ($post_type) {

				case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
					$count_string .= $total . ' Lesson';
					break;
				case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
					$count_string .= ', ' . $total . ' Quiz';
					break;
				case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
					$count_string .= ', ' . $total . ' Question';
					break;
			}

		}


		echo !empty($count_string) ? '( ' . $count_string . ' )' : '';
	}

	public function remove_from_course($section_id = 0, $course_id = 0)
	{
		if ($section_id < 1) {
			return false;
		}
		sikshya_remove_post_meta($section_id, 'course_id');

		$lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section_id);

		foreach ($lesson_and_quizes as $lesson_and_quize) {

			$id = $lesson_and_quize->ID;

			sikshya_remove_post_meta($id, 'section_id', $section_id);
		}


	}

	public function get_all_by_lesson_id($lesson_id = 0)
	{
		if ($lesson_id < 1) {
			return array();
		}

		$section_id = get_post_meta($lesson_id, 'section_id', true);

		$course_id = get_post_meta($section_id, 'course_id', true);

		$data = sikshya()->course->get_all_sections($course_id);

		return $data;
	}

	public function get_lesson_and_quiz($section_id)
	{
		if ($section_id instanceof \WP_Post) {
			$section_id = $section_id->ID;
		}

		$args = array(
			'numberposts' => -1,
			'no_found_rows' => true,
			'orderby' => 'meta_value',       // Or post by custom field
			'meta_key' => 'sikshya_order_number', // By which custom field
			'order' => 'ASC',
			'post_type' => array(SIKSHYA_LESSONS_CUSTOM_POST_TYPE, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE),
			'meta_query' => array(
				array(
					'key' => 'section_id',
					'value' => absint($section_id)

				)
			)
		);

		$data = get_posts($args);


		return $data;
	}

	public function get_all_child_count($section_id)
	{
		global $wpdb;

		$query_args[] = $section_id;
		$query_args[] = SIKSHYA_LESSONS_CUSTOM_POST_TYPE;
		$query_args[] = SIKSHYA_QUIZZES_CUSTOM_POST_TYPE;
		$query_args[] = SIKSHYA_LESSONS_CUSTOM_POST_TYPE;
		$query_args[] = SIKSHYA_QUIZZES_CUSTOM_POST_TYPE;

		$sql_query = "SELECT COUNT(*) as total, p.post_type
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm
ON p.ID=pm.post_id
WHERE pm.meta_key = 'section_id'
AND pm.meta_value = %d and p.post_status='publish'
GROUP BY p.post_type having p.post_type in (%s,%s) ORDER BY FIELD (p.post_type, %s, %s)";
		$sql = $wpdb->prepare(
			$sql_query,
			$query_args
		);

		$results = $wpdb->get_results($sql);

		$all_total[SIKSHYA_LESSONS_CUSTOM_POST_TYPE] = 0;
		$all_total[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE] = 0;
		foreach ($results as $result) {

			$total = isset($result->total) ? $result->total : 0;

			$post_type = isset($result->post_type) ? $result->post_type : '';

			switch ($post_type) {

				case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
					$all_total[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE] = $total;
					break;
				case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
					$all_total[SIKSHYA_LESSONS_CUSTOM_POST_TYPE] = $total;
					break;
			}

		}
		return $all_total;

	}

	public function get_course_id($section_id)
	{
		return get_post_meta($section_id, 'course_id', true);
	}


	public function get_id()
	{
		$id = get_the_ID();

		$post = get_post($id);

		$post_type = isset($post->post_type) ? $post->post_type : '';

		$section_id = 0;

		switch ($post_type) {

			case SIKSHYA_SECTIONS_CUSTOM_POST_TYPE:
				$section_id = $id;
				break;
			case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
			case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
				$section_id = get_post_meta($id, 'section_id', true);
				break;
			case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
				$quiz_id = get_post_meta($id, 'quiz_id', true);
				$section_id = get_post_meta($quiz_id, 'section_id', true);
				break;

		}
		return $section_id;

	}


}
