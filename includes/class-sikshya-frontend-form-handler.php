<?php

class Sikshya_Frontend_Form_Handler
{
	public function __construct()
	{
		add_action('template_redirect', array($this, 'register_user'));
		add_action('template_redirect', array($this, 'login_user'));
		add_action('template_redirect', array($this, 'logout'));
		add_action('template_redirect', array($this, 'update_profile'));
		add_action('template_redirect', array($this, 'complete_lesson'));
		add_action('template_redirect', array($this, 'start_quiz'));
		add_action('template_redirect', array($this, 'next_quiz_question'));
		add_action('template_redirect', array($this, 'prev_quiz_question'));
		add_action('template_redirect', array($this, 'complete_quiz_question'));
		add_action('template_redirect', array($this, 'enroll_in_course'));
		add_action('template_redirect', array($this, 'place_order'));

	}

	public function place_order()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_place_order') {
			return;
		}
		sikshya()->helper->validate_nonce(true);

		$sikshya_billing_fields = sikshya()->checkout->validate_billing_data($_POST);

		if ($sikshya_billing_fields['status']) {

			$data = isset($sikshya_billing_fields['data']) ? $sikshya_billing_fields['data'] : array();
			
			sikshya()->student->add($data);

		}


	}

	public function logout()
	{
		$query_var = get_query_var('sikshya_account_page');

		if ($query_var == 'logout') {
			wp_redirect(wp_logout_url());
		}
	}

	public function enroll_in_course()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_enroll_in_course') {
			return;
		}
		sikshya()->helper->validate_nonce(true);

		$course_id = isset($_POST['sikshya_course_id']) ? absint($_POST['sikshya_course_id']) : 0;

		$user_id = get_current_user_id();


		if (!sikshya()->course->has_enrolled($course_id)) {

			sikshya()->cart->add_to_cart($course_id);

			$cart_page_permalink = sikshya()->cart->get_cart_page(true);

			if ($cart_page_permalink != '') {
				sikshya()->messages->add(sikshya()->notice_key, sprintf(__('Course successfully added to cart. <a href="%s">View Cart</a>', 'sikshya'), $cart_page_permalink), 'success');

			} else {
				sikshya()->messages->add(sikshya()->notice_key, __('Course successfully added to cart', 'sikshya'), 'success');
			}

			return;
		}

		if (sikshya()->course->has_enrolled($course_id) || $user_id < 1) {

			$next_item_id = get_user_meta($user_id, 'sikshya_next_item_id', true);
			if (absint($next_item_id) > 0) {

				$permalink = get_permalink($next_item_id);
				wp_safe_redirect($permalink);

			}

			return;
		}

		sikshya()->course->enroll($course_id, $user_id);


	}

	public function complete_quiz_question()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_complete_quiz_question') {
			return;
		}

		//Checking nonce
		sikshya()->helper->validate_nonce(true);

		$quiz_id = isset($_POST['sikshya_quiz_id']) ? absint($_POST['sikshya_quiz_id']) : 0;
		$course_id = isset($_POST['sikshya_course_id']) ? absint($_POST['sikshya_course_id']) : 0;
		$question_id = isset($_POST['sikshya_question_id']) ? absint($_POST['sikshya_question_id']) : 0;
		$user_id = get_current_user_id();
		$answer = isset($_POST['sikshya_selected_answer']) ? ($_POST['sikshya_selected_answer']) : '';


		if (!sikshya()->quiz->is_started($user_id, $quiz_id)) {
			return;
		}

		if ($quiz_id < 1 || $course_id < 1 || $user_id < 1 || $question_id < 1) {
			return;

		}
		if (!sikshya()->quiz->is_completed($user_id, $quiz_id, $course_id)) {

			$user_id = get_current_user_id();

			sikshya()->question->update_answer($user_id, $quiz_id, $course_id, $question_id, $answer, true);

			if (sikshya()->quiz->is_completed($user_id, $quiz_id, $course_id)) {
				$quiz_id = sikshya()->quiz->get_id();
				$quiz_permalink = get_permalink($quiz_id);
				$quiz_permalink = add_query_arg(array(
					'quiz_report' => 1,
				), $quiz_permalink);

				wp_safe_redirect($quiz_permalink);
				exit;
			}

			$next_question_id = sikshya()->question->get_next_question($question_id);

			if ($next_question_id) {
				$next_question_permalink = get_permalink($next_question_id);
				wp_safe_redirect($next_question_permalink);
				exit;
			}
		} else {
			$quiz_id = sikshya()->quiz->get_id();
			$quiz_permalink = get_permalink($quiz_id);
			$quiz_permalink = add_query_arg(array(
				'quiz_report' => 1,
			), $quiz_permalink);

			wp_safe_redirect($quiz_permalink);
		}
	}

	public function next_quiz_question()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_next_quiz_question') {
			return;
		}
		//Checking nonce
		sikshya()->helper->validate_nonce(true);


		$quiz_id = isset($_POST['sikshya_quiz_id']) ? absint($_POST['sikshya_quiz_id']) : 0;
		$course_id = isset($_POST['sikshya_course_id']) ? absint($_POST['sikshya_course_id']) : 0;
		$question_id = isset($_POST['sikshya_question_id']) ? absint($_POST['sikshya_question_id']) : 0;
		$user_id = get_current_user_id();
		$answer = isset($_POST['sikshya_selected_answer']) ? ($_POST['sikshya_selected_answer']) : '';

		if ($quiz_id < 1 || $course_id < 1 || $user_id < 1 || $question_id < 1) {
			return;

		}

		$user_id = get_current_user_id();

		if (!sikshya()->quiz->is_completed($user_id, $quiz_id, $course_id) && sikshya()->quiz->is_started($user_id, $quiz_id)) {

			sikshya()->question->update_answer($user_id, $quiz_id, $course_id, $question_id, $answer);
		}
		$next_question_id = sikshya()->question->get_next_question($question_id);


		if ($next_question_id) {
			$next_question_permalink = get_permalink($next_question_id);
			wp_safe_redirect($next_question_permalink);
			exit;
		}
		die('die');

	}

	public function prev_quiz_question()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_prev_quiz_question') {
			return;
		}
		//Checking nonce
		sikshya()->helper->validate_nonce(true);


		$quiz_id = isset($_POST['sikshya_quiz_id']) ? absint($_POST['sikshya_quiz_id']) : 0;

		$course_id = isset($_POST['sikshya_course_id']) ? absint($_POST['sikshya_course_id']) : 0;

		$question_id = isset($_POST['sikshya_question_id']) ? absint($_POST['sikshya_question_id']) : 0;

		$user_id = get_current_user_id();

		if ($quiz_id < 1 || $course_id < 1 || $user_id < 1 || $question_id < 1) {
			return;

		}
		$prev_question_id = sikshya()->question->get_prev_question($question_id);

		if ($prev_question_id) {

			$prev_question_permalink = get_permalink($prev_question_id);


			wp_safe_redirect($prev_question_permalink);
			exit;
		}
		die('die');

	}

	public function start_quiz()
	{

		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_start_quiz') {
			return;
		}

//Checking nonce
		sikshya()->helper->validate_nonce(true);

		$quiz_id = isset($_POST['sikshya_quiz_id']) ? absint($_POST['sikshya_quiz_id']) : 0;
		$course_id = isset($_POST['sikshya_course_id']) ? absint($_POST['sikshya_course_id']) : 0;

		$user_id = get_current_user_id();

		if ($quiz_id < 1 || $course_id < 1 || $user_id < 1) {
			return;

		}

		if (sikshya()->quiz->is_started($user_id, $quiz_id)) {

			$quiz_question_permalink = sikshya()->question->first_question_permalink($course_id, $quiz_id);

			wp_safe_redirect($quiz_question_permalink);

			exit;
		}

		$get_order_item_id = sikshya()->order->course_item_id($course_id, $user_id);

		if ($get_order_item_id > 0) {

			sikshya()->quiz->start_quiz($user_id, $quiz_id, $course_id, $get_order_item_id);
		}
		$quiz_question_permalink = sikshya()->question->first_question_permalink($course_id, $quiz_id);

		wp_safe_redirect($quiz_question_permalink);
		exit;

	}

	public function complete_lesson()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_complete_lesson') {
			return;
		}

		$lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
		$course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
		//Checking nonce
		sikshya()->helper->validate_nonce(true);

		$user_id = get_current_user_id();

		if ($lesson_id < 1 || $user_id < 1 || $course_id < 1) {
			return;

		}
		if (!sikshya_is_content_available_for_user(get_the_ID(), SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {
			return;
		}
		$get_order_item_id = sikshya()->order->course_item_id($course_id, $user_id);

		if ($get_order_item_id > 0) {

			sikshya()->lesson->make_complete($user_id, $lesson_id, $course_id, $get_order_item_id);
		}

	}

	public function register_user()
	{

		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_register_user') {
			return;
		}

		//Checking nonce
		sikshya()->helper->validate_nonce(true);

		$required_fields = apply_filters('sikshya_user_registration_required_fields', array(
			'first_name' => __('First name field is required', 'sikshya'),
			'last_name' => __('Last name field is required', 'sikshya'),
			'email' => __('E-Mail field is required', 'sikshya'),
			'user_login' => __('User Name field is required', 'sikshya'),
			'password' => __('Password field is required', 'sikshya'),
			'password_confirmation' => __('Password Confirmation field is required', 'sikshya'),
		));

		foreach ($required_fields as $required_key => $required_value) {
			if (empty($_POST[$required_key])) {
				sikshya()->errors->add(sikshya()->notice_key, $required_value);

			}
		}
		if (!filter_var(sikshya()->helper->input('email'), FILTER_VALIDATE_EMAIL)) {
			sikshya()->errors->add(sikshya()->notice_key, __('Valid E-Mail is required', 'sikshya'));

		}
		if (sikshya()->helper->input('password') !== sikshya()->helper->input('password_confirmation')) {
			sikshya()->errors->add(sikshya()->notice_key, __('Confirm password does not matched with Password field', 'sikshya'));

		}


		if (is_sikshya_error(sikshya()->errors)) {

			return;
		}


		$first_name = sanitize_text_field(sikshya()->helper->input('first_name'));
		$last_name = sanitize_text_field(sikshya()->helper->input('last_name'));
		$email = sanitize_text_field(sikshya()->helper->input('email'));
		$user_login = sanitize_text_field(sikshya()->helper->input('user_login'));
		$password = sanitize_text_field(sikshya()->helper->input('password'));

		$userdata = array(
			'user_login' => $user_login,
			'user_email' => $email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'user_pass' => $password,
		);

		$user_id = wp_insert_user($userdata);

		if (!is_wp_error($user_id)) {
			$user = get_user_by('id', $user_id);
			if ($user) {
				wp_set_current_user($user_id, $user->user_login);
				wp_set_auth_cookie($user_id);
			}

			//Redirect page
			$redirect_page = sikshya_get_account_page(true);
			if (empty($redirect_page)) {
				wp_safe_redirect(home_url());
			}

			wp_redirect($redirect_page);
			die();
		} else {
			sikshya()->errors->add(sikshya()->notice_key, __('Cannt register user right now', 'sikshya'));
			return;
		}

	}

	public function update_profile()
	{

		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_update_profile') {
			return;
		}
		sikshya()->helper->validate_nonce(true);

		$user_id = isset($_POST['current_user_id']) ? absint($_POST['current_user_id']) : 0;

		if ($user_id != get_current_user_id() || $user_id == 0) {
			return;
		}

		$first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';

		$last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

		$nicename = isset($_POST['nicename']) ? sanitize_text_field($_POST['nicename']) : '';

		$website = isset($_POST['website']) ? sanitize_text_field($_POST['website']) : '';

		$sikshya_change_password = isset($_POST['sikshya_change_password']) ? absint($_POST['sikshya_change_password']) : 0;

		$user = new stdClass();

		$user->ID = $user_id;

		$user->user_nicename = $nicename;

		$user->user_url = $website;

		wp_update_user($user);

		update_user_meta($user_id, 'first_name', $first_name);

		update_user_meta($user_id, 'last_name', $last_name);

		if (!boolval($sikshya_change_password)) {

			sikshya()->messages->add(sikshya()->notice_key, __('Profile successflly updated.', 'sikshya'), 'success');

		} else {
			$old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';

			$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

			$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

			$current_user = get_user_by('id', $user_id);

			if ($confirm_password != $new_password || empty($confirm_password)) {

				sikshya()->errors->add(sikshya()->notice_key, __('Confirm password doesnt match.', 'sikshya'));
			}

			if (!wp_check_password($old_password, $current_user->user_pass, $current_user->ID) || empty($new_password)) {

				sikshya()->errors->add(sikshya()->notice_key, __('Invalid old password or password doesnt match.', 'sikshya'));

				return;
			}

			if (sikshya()->errors->has_errors()) {

				return;
			}

			$user = new stdClass();

			$user->ID = $user_id;

			$user->user_pass = $new_password;

			wp_update_user($user);

			sikshya()->messages->add(sikshya()->notice_key, __('Profile successflly updated with password.', 'sikshya'), 'success');


		}

	}

	public function login_user()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_login_user') {
			return;
		}

		$redirect_url = isset($_POST['sikshya_redirect_to']) ? esc_url($_POST['sikshya_redirect_to']) : '';
		//Checking nonce
		sikshya()->helper->validate_nonce(true);

		$required_fields = array(
			'user_login' => __('Username or email field required.', 'sikshya'),
			'password' => __('Password required.', 'sikshya'),
		);

		foreach ($required_fields as $required_key => $required_value) {
			if (empty($_POST[$required_key])) {
				sikshya()->errors->add(sikshya()->notice_key, $required_value);

			}
		}
		if (!sikshya()->errors->has_errors()) {
			try {
				$creds = array(
					'user_login' => trim(wp_unslash($_POST['user_login'])), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'user_password' => $_POST['password'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'remember' => isset($_POST['rememberme']), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				);
				// On multisite, ensure user exists on current site, if not add them before allowing login.
				if (is_multisite()) {
					$user_data = get_user_by(is_email($creds['user_login']) ? 'email' : 'login', $creds['user_login']);

					if ($user_data && !is_user_member_of_blog($user_data->ID, get_current_blog_id())) {
						add_user_to_blog(get_current_blog_id(), $user_data->ID, 'subscriber');
					}
				}


				// Perform the login.
				$user = wp_signon($creds, is_ssl());

				if (is_wp_error($user)) {
					$message = $user->get_error_message();
					$message = str_replace(esc_html($creds['user_login']), esc_html($creds['user_login']), $message);
					throw new Exception($message);
				} else {

					$redirect = empty($redirect_url) ? sikshya_get_account_page(true) : $redirect_url;

					wp_redirect($redirect); // phpcs:ignore
					exit;
				}
			} catch (Exception $e) {

				sikshya()->errors->add(sikshya()->notice_key, $e->getMessage());

			}
		}
	}


}

new Sikshya_Frontend_Form_Handler();
