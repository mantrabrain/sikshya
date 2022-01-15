<?php
/**
 * generate rules for show courses on login page
 *
 * @return array
 *
 * @since 1.0.0
 *
 */
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-pricing-helper.php';
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-currency-helper.php';
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-country-helper.php';
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-state-helper.php';
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-formatting-helper.php';
include_once SIKSHYA_PATH . '/includes/helpers/sikshya-html-helper.php';


function sikshya_export($args = array())
{
	$defaults = array(

		'content' => array(),

	);

	$args = wp_parse_args($args, $defaults);

	do_action('export_sikshya', $args);

	$sitename = strtolower(sanitize_key(get_bloginfo('name')));

	if (!empty($sitename)) {

		$sitename .= '.';
	}

	$content = $args['content'];

	$content = is_string($content) ? $content : json_encode($content);

	$date = gmdate('Y-m-d');

	$wp_filename = $sitename . 'sikshya.' . $date . '.json';

	$filename = apply_filters('export_wp_filename', $wp_filename, $sitename, $date);

	header('Content-Description: File Transfer');

	header('Content-Disposition: attachment; filename=' . $filename);

	header('Content-Type: text/json; charset=' . get_option('blog_charset'), true);

	echo $content;

	exit;

}


function sikshya_account_page_nav_items()
{
	$items_array = array(

		'dashboard' => array(
			'title' => __('Dashboard', 'sikshya'),
			//'cap' => ''
			'icon' => 'fa fa-home'
		),
		'profile' => array(
			'title' => __('Profile', 'sikshya'),
			//'cap' => ''
			'icon' => 'fas fa-user'
		),
		'enrolled-courses' => array(
			'title' => __('Enrolled Courses', 'sikshya'),
			//'cap' => ''
			'icon' => 'fa fa-book'
		),
		'update-profile' => array(
			'title' => __('Update Profile', 'sikshya'),
			//'cap' => ''
			'icon' => 'fa fa-pencil-alt'
		),
		'logout' => array(
			'title' => __('Logout', 'sikshya'),
			//'cap' => ''
			'icon' => 'fa fa-sign-out-alt'
		)

	);

	foreach ($items_array as $key => $nav_item) {
		if (is_array($nav_item)) {

			if (isset($nav_item['cap']) && !current_user_can($nav_item['cap'])) {
				unset($items_array[$key]);
			} else if (!is_user_logged_in()) {
				unset($items_array[$key]);
			}
		}
	}

	return apply_filters('sikshya_account_page_nav_items', $items_array);
}

function sikshya_get_meta_data($id, $title, $default = '', $format = '%s', $br = false, $page = false, $mail = false)
{
	$data = get_post_meta($id, $title, true);
	if (empty($data))
		$data = $default;

	$value = $br ? str_replace("\r", '</br>', $data) : $data;

	if ($value != null)
		if ($page)
			$value = sprintf($format, get_permalink($value), get_the_title($value));
		else
			$value = $mail ? sprintf($format, $value, $value) : sprintf($format, $value);

	return $value;
}


/**
 * return option value in format
 *
 * @param string $option
 * @param string $format
 * @param string $default
 *
 * @return string
 *
 * @since 1.0.0
 *
 */
function sikshya_get_formatted_option($option, $format = '%s', $default = '')
{
	$value = sikshya_get_option($option);
	if (empty($value))
		return $default;

	return sprintf($format, $value);
}


function sikshya_render_editor($content, $name, $id)
{
	ob_start();

	include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/editor.php';

	return ob_get_clean();

}

/**
 * @param \WP_Post|int $course_id
 * @return array
 */
function sikshya_get_course_info($course_id)
{
	if ($course_id instanceof \WP_Post) {
		$course_id = $course_id->ID;
	}
	$data = get_post_meta($course_id, 'sikshya_info', true);

	if (!is_array($data)) {
		$data = array();
	}
	return $data;
}

/**
 * @param array $args
 * @return array
 */
function sikshya_get_courses($args = array())
{
	$args = array(
			'numberposts' => -1,
			'no_found_rows' => true,
			'orderby' => 'menu_order',
			'order' => 'asc',
			'post_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE
		) + $args;

	$data = get_posts($args);

	return $data;
}

/**
 * @param int|WP_Post|null $course_id
 * @return WP_Post|null
 */
function sikshya_get_course($course_id)
{
	if (!$course_id) {
		return null;
	}
	$course = get_post($course_id);
	if (!$course || $course->post_type != SIKSHYA_COURSES_CUSTOM_POST_TYPE) {
		return null;
	}
	return $course;
}

/**
 * @param string $url
 * @return string
 */
function sikshya_get_image_url($url)
{
	if (!$url) {
		$url = SIKSHYA_ASSETS_URL . '/images/placeholder.jpg';
	}
	return $url;
}

/**
 * @param int|null $user_id
 * @return string
 */
function sikshya_get_avatar_url($user_id = null)
{
	if ($user_id === null) {
		$user_id = get_current_user_id();
	}
	if ($user_id) {
		$attachment_id = get_user_meta($user_id, 'sikshya_avatar_attachment_id', true);
		if ($attachment_id) {
			$url = wp_get_attachment_image_url($attachment_id, 'sikshya_avatar');
			if ($url) {
				return $url;
			}
		}
	}
	return get_avatar_url($user_id, array('size' => 300));
}


function sikshya_is_pro()
{
	return false;
}


function sikshya_is_new_post($post_id)
{
	if (substr($post_id, 0, 1) == '_') {

		return true;

	}
	return false;
}


function sikshya_load_metabox_html($template_vars)
{


	foreach ($template_vars->sections as $section) {

		$lessonsHtml = '';

		if (!empty($section->lessons)) {

			foreach ($section->lessons as $lesson) {

				$lesson_editor_name = 'sikshya_lesson[' . $section->ID . '][' . $lesson->ID . '][lessons_content]';

				$lesson_editor_id = 'lesson_editor_' . $lesson->ID;

				$editor = sikshya_render_editor($lesson->post_content, $lesson_editor_name, $lesson_editor_id);

				$quizesHtml = '';

				$quizzes = isset($lesson->quizzes) ? $lesson->quizzes : array();

				foreach ($quizzes as $quiz) {

					if ($quiz) {


						$questionsHtml = '';

						$questions = isset($quiz->questions) ? $quiz->questions : array();

						if ($questions) {

							foreach ($questions as $i => $question) {


								$question_type = get_post_meta($question->ID, 'type', true);


								$answersHtml = '';

								$question_answers = get_post_meta($question->ID, 'answers', true);

								$question_answers = !empty($question_answers) ? $question_answers : array();

								$answers_correct = get_post_meta($question->ID, 'correct_answers', true);

								$answers_correct = !empty($answers_correct) ? $answers_correct : array();

								if (!empty($question_answers)) {

									foreach ($question_answers as $answer_id => $answer) {

										//sikshya()->question->render_answer_tmpl('{%question_id%}_{%answer_id%}', '{%answer_id%}', 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers][{%answer_id%}]',
										// array(), 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers_correct][]', array(), 'text');
										$answersHtml .= sikshya()->question->render_answer_tmpl(

											$question->ID,

											$answer_id,

											'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . '][answers][' . $answer_id . ']',

											$answer,

											'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . '][answers_correct][]',

											$answers_correct,

											$question_type

										);

									}

								}

								$questionsHtml .= sikshya()->question->render_tmpl($question->ID, 'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . ']', $question, $answersHtml);

							}

						}

						$quizEditor = sikshya_render_editor($quiz->post_content, 'lessons_quiz[' . $section->ID . '][' . $lesson->ID . '][content]', 'lesson_quiz_editor_' . $lesson->ID);

					}
					$quizesHtml .= sikshya()->quiz->render_tmpl($quiz->ID, 'lessons_quiz[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . ']', $quiz, $quizEditor, $questionsHtml);

				}
				$lessonsHtml .= sikshya()->lesson->render_tmpl($lesson->ID, '[' . $section->ID . '][' . $lesson->ID . ']', $lesson->post_title, $editor, true, $quizesHtml);

			}
		}
		$image = get_post_meta($section->ID, 'image', true);

		echo sikshya()->section->render_tmpl($section->ID, $section->post_title, $section->post_content, $image, $lessonsHtml);
	}
}

if (!function_exists('is_sikshya_error')) {

	function is_sikshya_error($thing)
	{
		if ($thing instanceof WP_Error) {
			if ($thing->has_errors()) {
				return true;
			}
		}
		return false;
	}


}
if (!function_exists('sikshya_time')) {
	function sikshya_time()
	{
		//return current_time( 'timestamp' );
		return time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
	}
}
if (!function_exists('sikshya_get_account_page')) {

	function sikshya_get_account_page($get_permalink = false)
	{
		$page_id = absint(get_option('sikshya_account_page'));

		if ($page_id < 1) {

			global $wpdb;

			$page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_account]%" AND post_parent = 0');
		}

		$page_permalink = get_permalink($page_id);

		if ($get_permalink) {

			return $page_permalink;
		}

		return $page_id;


	}
}

if (!function_exists('sikshya_get_user_registration_page')) {

	function sikshya_get_user_registration_page($get_permalink = false)
	{
		$page_id = absint(get_option('sikshya_registration_page'));

		if ($page_id < 1) {

			global $wpdb;

			$page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_registration]%" AND post_parent = 0');
		}

		$page_permalink = get_permalink($page_id);

		if ($get_permalink) {

			return $page_permalink;
		}

		return $page_id;


	}
}
if (!function_exists('sikshya_is_screen')) {

	function sikshya_is_screen($check_screen = '')
	{
		$current_screen = get_current_screen();

		$screen_id = isset($current_screen->id) ? $current_screen->id : '';

		if ($check_screen != '' && $screen_id == $check_screen) {

			return true;
		}
		return false;


	}
}

if (!function_exists('sikshya_get_course_level')) {
	function sikshya_get_course_level($course_key = '')
	{
		$course_levels = array(
			'all' => __('All Levels', 'sikshya'),
			'beginner' => __('Beginner', 'sikshya'),
			'intermediate' => __('Intermediate', 'sikshya'),
			'expert' => __('Expert', 'sikshya'),

		);
		if (empty($course_key)) {
			return $course_levels;
		}
		$level_keys = array_keys($course_levels);
		if (in_array($course_key, $level_keys)) {
			return $course_levels[$course_key];
		}
		return $course_levels;
	}

}

if (!function_exists('sikshya_get_current_post_type')) {

	function sikshya_get_current_post_type()
	{

		$object = get_queried_object();

		$type = isset($object->post_type) ? $object->post_type : '';

		return $type;
	}
}

if (!function_exists('sikshya_is_content_available_for_user')) {

	function sikshya_is_content_available_for_user($content_id = 0, $content_type = '')
	{

		if ($content_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE) {
			$sikshya_is_preview_lesson = (boolean)get_post_meta($content_id, 'sikshya_is_preview_lesson', true);
			if ($sikshya_is_preview_lesson) {
				return true;
			}
		}
		$course_id = sikshya()->course->get_id();


		if (sikshya()->course->has_enrolled($course_id)) {
			return true;

		}
		return false;
	}
}

function sikshya_get_user_items($select = array(), $where = array(), $additonal_args = array())
{
	$default_additional_args = array(
		'order_by' => '',
		'order' => 'asc',
		'offset' => '0',
		'limit' => ''
	);
	$additional_parsed_args = wp_parse_args($additonal_args, $default_additional_args);

	global $wpdb;

	if (empty($select)) {

		$select_text = "SELECT * FROM " . SIKSHYA_DB_PREFIX . 'user_items';
	} else {
		$sanitized_select = array_map('sanitize_text_field', wp_unslash($select));

		$select_text = "SELECT " . join(', ', $sanitized_select) . " FROM " . SIKSHYA_DB_PREFIX . 'user_items';
	}
	$prepare_args = array();

	if (empty($where)) {

		$select_text = $select_text . " WHERE 1=%d";

		$prepare_args = array(
			'1'
		);

	} else {
		$where_query = ' WHERE ';

		foreach ($where as $wh => $wh_value) {

			$where_query .= sanitize_text_field($wh) . "=%s AND ";

			array_push($prepare_args, $wh_value);
		}

		$where_query = rtrim($where_query, "AND ");

		$select_text .= $where_query;


	}
	if ('' != ($additional_parsed_args['order_by'])) {

		$select_text .= ' ORDER BY ' . sanitize_text_field($additional_parsed_args['order_by']) . ' ';

		if (!in_array(strtolower($additional_parsed_args['order']), array('asc', 'desc'))) {
			$additional_parsed_args['order'] = 'asc';
		}
		$select_text .= sanitize_text_field($additional_parsed_args['order']);

	}

	if ('' != ($additional_parsed_args['limit'])) {

		$additional_parsed_args['offset'] = absint($additional_parsed_args['offset']);

		$select_text .= ' LIMIT ' . $additional_parsed_args['offset'] . ', ' . absint($additional_parsed_args['limit']);

	}
	$query = $wpdb->prepare($select_text, $prepare_args);

	return $wpdb->get_results($query);

}

function sikshya_update_user_items($update_values = array(), $where = array())
{
	global $wpdb;


	if (empty($update_values) || empty($where)) {
		return false;
	}

	$prepare_args = array();

	$update_query = "UPDATE " . SIKSHYA_DB_PREFIX . "user_items
SET ";

	foreach ($update_values as $up_key => $up_value) {

		$update_query .= sanitize_text_field($up_key) . "=%s, ";

		array_push($prepare_args, $up_value);
	}
	$update_query = rtrim($update_query, ", ");

	$where_query = ' WHERE ';

	foreach ($where as $wh => $wh_value) {

		$where_query .= sanitize_text_field($wh) . "=%s AND ";

		array_push($prepare_args, $wh_value);
	}

	$where_query = rtrim($where_query, "AND ");

	$update_query .= $where_query;

	$query = $wpdb->prepare($update_query, $prepare_args);

	$results = $wpdb->get_results($query);

	return $results;

}

function sikshya_get_user_item_meta($user_item_id, $meta_key, $meta_value = '')
{
	global $wpdb;

	$user_item_id = absint($user_item_id);

	$meta_key = sanitize_text_field($meta_key);

	$sql = "SELECT uim.meta_value FROM " . SIKSHYA_DB_PREFIX . "user_items ui
    INNER JOIN " . SIKSHYA_DB_PREFIX . "user_itemmeta uim ON ui.user_item_id=uim.user_item_id
    WHERE uim.meta_key=%s AND uim.user_item_id=%d";

	if (!empty($meta_value)) {

		$sql .= ' AND uim.meta_value=%s';

		$query = $wpdb->prepare($sql, $meta_key, $user_item_id, $meta_value);
	} else {
		$query = $wpdb->prepare($sql, $meta_key, $user_item_id);
	}
	$meta_data = $wpdb->get_results($query);

	if (isset($meta_data[0])) {

		return maybe_unserialize($meta_data[0]->meta_value);
	}
	return false;
}

function sikshya_update_user_item_meta($user_item_id, $meta_key, $meta_value, $prev_value = '')
{
	if ($user_item_id < 1) {
		return false;
	}
	$item = sikshya_get_user_items(array(
		'user_item_id'
	), array(
			'user_item_id' => absint($user_item_id)
		)
	);


	if (count($item) > 0) {

		$meta_key = wp_unslash($meta_key);

		$meta_value = wp_unslash($meta_value);

		$meta_value = maybe_serialize($meta_value);

		$item_meta = sikshya_get_user_item_meta($user_item_id, $meta_key);

		if (gettype($item_meta) != 'boolean' && $item_meta) {

			global $wpdb;

			$sql = "UPDATE " . SIKSHYA_DB_PREFIX . "user_itemmeta
SET meta_value= %s
WHERE meta_key = %s AND user_item_id=%d";


			if (!empty($prev_value)) {

				$sql .= ' AND meta_value=%s';

				$query = $wpdb->prepare($sql, $meta_value, $meta_key, $user_item_id, $prev_value);
			} else {
				$query = $wpdb->prepare($sql, $meta_value, $meta_key, $user_item_id);
			}
			return $wpdb->query($query);

		} else {

			global $wpdb;

			$insert_sql_query = "INSERT INTO " . SIKSHYA_DB_PREFIX . "user_itemmeta (user_item_id,meta_key, meta_value)
VALUES (%d, %s, %s);
";

			$query = $wpdb->prepare($insert_sql_query, $user_item_id, $meta_key, $meta_value);

			return $wpdb->query($query);
		}
	}

	return false;
}

function sikshya_get_order_items($select = array(), $where = array())
{
	global $wpdb;


	if (empty($select)) {

		$select_text = "SELECT * FROM " . SIKSHYA_DB_PREFIX . 'order_items';
	} else {
		$sanitized_select = array_map('sanitize_text_field', wp_unslash($select));

		$select_text = "SELECT " . join(', ', $sanitized_select) . " FROM " . SIKSHYA_DB_PREFIX . 'user_items';
	}
	if (empty($where)) {

		$query = $wpdb->prepare($select_text . " WHERE 1=%d", 1);

	} else {
		$where_query = ' WHERE ';
		$prepare_args = array();

		foreach ($where as $wh => $wh_value) {

			$where_query .= sanitize_text_field($wh) . "=%s AND ";

			array_push($prepare_args, $wh_value);
		}

		$where_query = rtrim($where_query, "AND ");

		$select_text .= $where_query;

		$query = $wpdb->prepare($select_text, $prepare_args);

	}
	$results = $wpdb->get_results($query);

	return $results;

}

function sikshya_update_order_items($update_values = array(), $where = array())
{
	global $wpdb;


	if (empty($update_values) || empty($where)) {
		return false;
	}

	$prepare_args = array();

	$update_query = "UPDATE " . SIKSHYA_DB_PREFIX . "order_items
SET ";

	foreach ($update_values as $up_key => $up_value) {

		$update_query .= sanitize_text_field($up_key) . "=%s, ";

		array_push($prepare_args, $up_value);
	}
	$update_query = rtrim($update_query, ", ");

	$where_query = ' WHERE ';

	foreach ($where as $wh => $wh_value) {

		$where_query .= sanitize_text_field($wh) . "=%s AND ";

		array_push($prepare_args, $wh_value);
	}

	$where_query = rtrim($where_query, "AND ");

	$update_query .= $where_query;

	$query = $wpdb->prepare($update_query, $prepare_args);

	$results = $wpdb->get_results($query);

	return $results;

}

function sikshya_get_order_item_meta($order_item_id, $meta_key, $meta_value = '')
{
	global $wpdb;

	$order_item_id = absint($order_item_id);

	$meta_key = sanitize_text_field($meta_key);

	$sql = "SELECT uim.meta_value FROM " . SIKSHYA_DB_PREFIX . "order_items ui
    INNER JOIN " . SIKSHYA_DB_PREFIX . "order_itemmeta uim ON ui.order_item_id=uim.order_item_id
    WHERE uim.meta_key=%s AND uim.order_item_id=%d";

	if (!empty($meta_value)) {

		$sql .= ' AND uim.meta_value=%s';

		$query = $wpdb->prepare($sql, $meta_key, $order_item_id, $meta_value);
	} else {
		$query = $wpdb->prepare($sql, $meta_key, $order_item_id);
	}
	$meta_data = $wpdb->get_results($query);

	if (isset($meta_data[0])) {

		return maybe_unserialize($meta_data[0]->meta_value);
	}
	return false;
}

function sikshya_update_order_item_meta($order_item_id, $meta_key, $meta_value, $prev_value = '')
{
	if ($order_item_id < 1) {
		return false;
	}


	$meta_key = wp_unslash($meta_key);

	$meta_value = wp_unslash($meta_value);

	$meta_value = maybe_serialize($meta_value);

	$item_meta = sikshya_get_order_item_meta($order_item_id, $meta_key, $meta_value);

	if (gettype($item_meta) != 'boolean' && $item_meta) {

		global $wpdb;

		$sql = "UPDATE " . SIKSHYA_DB_PREFIX . "order_itemmeta
SET meta_value= %s
WHERE meta_key = %s AND order_item_id=%d";


		if (!empty($prev_value)) {

			$sql .= ' AND meta_value=%s';

			$query = $wpdb->prepare($sql, $meta_value, $meta_key, $order_item_id, $prev_value);
		} else {
			$query = $wpdb->prepare($sql, $meta_value, $meta_key, $order_item_id);
		}

		return $wpdb->query($query);

	} else {

		global $wpdb;

		$insert_sql_query = "INSERT INTO " . SIKSHYA_DB_PREFIX . "order_itemmeta (order_item_id,meta_key, meta_value)
VALUES (%d, %s, %s);
";

		$query = $wpdb->prepare($insert_sql_query, $order_item_id, $meta_key, $meta_value);


		return $wpdb->query($query);
	}


	return false;
}


if (!function_exists('sikshya_get_login_page')) {

	function sikshya_get_login_page($get_permalink = false, $redirect_url = '')
	{
		$page_id = absint(get_option('sikshya_login_page'));

		if ($page_id < 1) {

			global $wpdb;

			$page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_login]%" AND post_parent = 0');
		}


		if ($get_permalink) {

			if ($page_id > 0) {

				$page_permalink = !empty($redirect_url) ? add_query_arg('redirect_to', urlencode($redirect_url), get_permalink($page_id)) : get_permalink($page_id);

			} else {

				$page_permalink = wp_login_url($redirect_url);
			}


			return $page_permalink;
		}

		return $page_id;


	}
}
if (!function_exists('sikshya_question_answer_type')) {

	function sikshya_question_answer_type()
	{
		return array(
			'single' => __('One answer', 'sikshya'),
			'single_image' => __('One answer with image', 'sikshya'),
			'multi' => __('Multiple answers', 'sikshya'),
			'multi_image' => __('Multiple answers with image', 'sikshya'),
		);
	}
}
if (!function_exists('sikshya_get_instructors_list')) {

	function sikshya_get_instructors_list()
	{
		$users = get_users(array(
			'role__in' => array('administrator', 'sikshya_instructor'),
		));
		$all_users = array();
		foreach ($users as $user) {

			$user_obj = new stdClass();
			$user_obj->ID = $user->ID;
			$user_obj->name = $user->user_nicename . ' (' . $user->user_login . ')';
			$all_users[$user->ID] = $user_obj;
		}
		return $all_users;
	}
}
if (!function_exists('sikshya_clean')) {

	function sikshya_clean($var)
	{
		if (is_array($var)) {
			return array_map('sikshya_clean', $var);
		} else {
			return is_scalar($var) ? sanitize_text_field($var) : $var;
		}
	}
}
if (!function_exists('sikshya_maybe_absint')) {
	function sikshya_maybe_absint($val)
	{
		if ('' == $val) {
			return '';

		}
		return absint($val);

	}
}


if (!function_exists('sikshya_remove_post_meta')) {

	function sikshya_remove_post_meta($post_id, $meta_key, $meta_value = '')
	{
		if ($meta_value === '') {

			delete_post_meta($post_id, $meta_key);

		} else {
			delete_post_meta($post_id, $meta_key, $meta_value);

		}
	}
}

if (!function_exists('sikshya_video_sources')) {
	function sikshya_video_sources()
	{
		return array(
			'youtube' => esc_html__('Youtube', 'sikshya')
		);
	}
}
if (!function_exists('sikshya_duration_times')) {
	function sikshya_duration_times()
	{
		return array(
			'minute' => esc_html__('Minute(s)', 'sikshya'),
			'hour' => esc_html__('Hour(s)', 'sikshya'),
			'day' => esc_html__('Day(s)', 'sikshya'),
			'week' => esc_html__('Week(s)', 'sikshya')
		);
	}
}
if (!function_exists('sikshya_course_levels')) {
	function sikshya_course_levels($level_key = '')
	{
		$data = array(
			'all' => esc_html__('All Levels', 'sikshya'),
			'beginner' => esc_html__('Beginner', 'sikshya'),
			'intermediate' => esc_html__('Intermediate', 'sikshya'),
			'expert' => esc_html__('Expert', 'sikshya')
		);
		if ('' !== $level_key) {
			return isset($data[$level_key]) ? $data[$level_key] : $data;
		}
		return $data;
	}
}

if (!function_exists('sikshya_lesson_quiz_id')) {
	function sikshya_lesson_quiz_id()
	{
		$id = get_the_ID();

		$post = get_post($id);

		$post_type = isset($post->post_type) ? $post->post_type : '';

		$lesson_quiz_id = 0;
		switch ($post_type) {
			case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
			case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
				$lesson_quiz_id = $id;
				break;
			case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
				$lesson_quiz_id = get_post_meta($id, 'quiz_id', true);
				break;

		}
		return $lesson_quiz_id;

	}
}
if (!function_exists('sikahy_is_active_lesson_quizes')) {
	function sikahy_is_active_lesson_quizes($lesson_quiz_id)
	{
		global $post;

		$post_id = isset($post->ID) ? $post->ID : 0;

		if ($lesson_quiz_id == $post_id) {
			return true;
		}
		$post_type = isset($post->post_type) ? $post->post_type : '';

		if ($post_type == SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE) {

			$parent_id = get_post_meta($post_id, 'quiz_id', true);

			if ($parent_id == $lesson_quiz_id) {

				return true;
			}
		}

		return false;

	}
}

if (!function_exists('sikshya_get_order_statuses')) {

	function sikshya_get_order_statuses($status_key = '')
	{
		$statuses = apply_filters(
			'sikshya_order_statuses', array(
				'sikshya-pending' => __('Pending', 'sikshya'),
				'sikshya-processing' => __('Processing', 'sikshya'),
				'sikshya-on-hold' => __('On Hold', 'sikshya'),
				'sikshya-completed' => __('Completed', 'sikshya'),
				'sikshya-cancelled' => __('Cancelled', 'sikshya')
			)
		);

		if (empty($status_key) || '' == $status_key) {

			return $statuses;
		}
		if (isset($statuses[$status_key])) {
			return $statuses[$status_key];
		}
		return $statuses;

	}
}

if (!function_exists('sikshya_update_order_status')) {

	function sikshya_update_order_status($sikshya_order_id = 0, $status = 'sikshya-pending')
	{
		$sikshya_order_statuses = sikshya_get_order_statuses();

		if ($sikshya_order_id < 1 || !isset($sikshya_order_statuses[$status])) {

			return false;
		}

		do_action('sikshya_before_order_status_change', array(
			'order_id' => $sikshya_order_id,
			'status' => $status
		));

		$order_array = array();
		$order_array['ID'] = $sikshya_order_id;
		$order_array['post_status'] = $status;

		// Update the post into the database
		wp_update_post($order_array);

		do_action('sikshya_after_order_status_change', array(
			'order_id' => $sikshya_order_id,
			'status' => $status
		));

		return true;
	}
}

if (!function_exists('sikshya_get_logger')) {

	function sikshya_get_logger()
	{
		static $logger = null;

		$class = apply_filters('sikshya_logging_class', 'Sikshya_Logger');

		if (null !== $logger && is_string($class) && is_a($logger, $class)) {
			return $logger;
		}

		$implements = class_implements($class);

		if (is_array($implements) && in_array('Sikshya_Interface_Logger', $implements, true)) {
			$logger = is_object($class) ? $class : new $class();
		} else {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
				/* translators: 1: class name 2: sikshya_logging_class 3: Sikshya_Interface_Logger */
					__('The class %1$s provided by %2$s filter must implement %3$s.', 'sikshya'),
					'<code>' . esc_html(is_object($class) ? get_class($class) : $class) . '</code>',
					'<code>sikshya_logging_class</code>',
					'<code>Sikshya_Interface_Logger</code>'
				),
				'3.0'
			);

			$logger = is_a($logger, 'Sikshya_Logger') ? $logger : new Sikshya_Logger();
		}

		return $logger;
	}
}

function sikshya_get_permalink_structure()
{

	$defaults = array(
		'sikshya_course_base' => 'courses',
		'sikshya_course_category_base' => 'course-category',
		'sikshya_course_tag_base' => 'course-tag',
		'sikshya_lesson_base' => 'lessons',
		'sikshya_quiz_base' => 'quizzes',
	);
	$permalinks = wp_parse_args(
		(array)get_option('sikshya_permalinks', array()),
		$defaults
	);

	// Ensure rewrite slugs are set.

	foreach ($defaults as $default_id => $default_permalink) {

		$permalinks[$default_id] = untrailingslashit(empty($permalinks[$default_id]) ? $default_permalink : $permalinks[$default_id]);

	}
	return $permalinks;
}
