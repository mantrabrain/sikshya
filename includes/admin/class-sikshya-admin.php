<?php

class Sikshya_Admin
{
	public function __construct()
	{

		$this->includes();
		$this->hooks();

	}

	public function includes()
	{
		include_once SIKSHYA_PATH . '/includes/admin/sikshya-admin-functions.php';
		include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-assets.php';
		include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-menu.php';
		include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-form-handler.php';

		include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-importer.php';
		include_once SIKSHYA_PATH . '/includes/about/class-sikshya-about.php';
		include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-permalinks.php';;
	}

	public function hooks()
	{

		add_action('init', array($this, 'setup_wizard'));
		add_action('admin_init', array($this, 'admin_redirects'));
		add_action('current_screen', array($this, 'setup_screen'));
		add_action('check_ajax_referer', array($this, 'setup_screen'));
		add_filter('display_post_states', array($this, 'add_display_post_states'), 10, 2);
	}

	public function admin_redirects()
	{


		if (!get_transient('_sikshya_activation_redirect')) {
			return;
		}

		delete_transient('_sikshya_activation_redirect');


		if ((!empty($_GET['page']) && in_array($_GET['page'], array('sikshya-setup'))) || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
			return;
		}


		// If it's the first time
		if (get_option('sikshya_setup_wizard_ran') != '1') {
			wp_safe_redirect(admin_url('index.php?page=sikshya-setup'));
			exit;

			// Otherwise, the welcome page
		} else {
			wp_safe_redirect(admin_url('edit.php?post_type=sik_courses'));
			exit;
		}
	}

	public function setup_wizard()
	{
		// Setup/welcome
		if (!empty($_GET['page'])) {

			if ('sikshya-setup' == $_GET['page']) {

				include_once SIKSHYA_PATH . '/includes/admin/setup/class-sikshya-setup-wizard.php';
			}
		}
	}

	public function setup_screen()
	{

		$screen_id = false;

		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			$screen_id = isset($screen, $screen->id) ? $screen->id : '';
		}

		if (!empty($_REQUEST['screen'])) { // WPCS: input var ok.
			$screen_id = sanitize_text_field($_REQUEST['screen']);
		}

		switch ($screen_id) {
			case 'edit-' . SIKSHYA_COURSES_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-courses.php';
				new Sikshya_Admin_List_Table_Courses();
				break;
			case 'edit-' . SIKSHYA_SECTIONS_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-sections.php';
				new Sikshya_Admin_List_Table_Sections();
				break;
			case 'edit-' . SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-lessons.php';
				new Sikshya_Admin_List_Table_Lessons();
				break;
			case 'edit-' . SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-quizzes.php';
				new Sikshya_Admin_List_Table_Quizzes();
				break;
			case 'edit-' . SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-questions.php';
				new Sikshya_Admin_List_Table_Questions();
				break;
			case 'edit-' . SIKSHYA_ORDERS_CUSTOM_POST_TYPE:
				include_once 'list-tables/class-sikshya-admin-list-table-orders.php';
				new Sikshya_Admin_List_Table_Orders();
				break;


		}

		// Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
		remove_action('current_screen', array($this, 'setup_screen'));
		remove_action('check_ajax_referer', array($this, 'setup_screen'));
	}


	public function add_display_post_states($post_states, $post)
	{
		$sikshya_cart_page = get_option('sikshya_cart_page');

		$sikshya_checkout_page = get_option('sikshya_checkout_page');

		$sikshya_thankyou_page = get_option('sikshya_thankyou_page');

		$sikshya_account_page = get_option('sikshya_account_page');

		$sikshya_registration_page = get_option('sikshya_registration_page');

		$sikshya_login_page = get_option('sikshya_login_page');

		if ($sikshya_cart_page == $post->ID) {
			$post_states['sikshya_page_for_cart'] = __('Sikshya Cart Page', 'sikshya');
		}

		if ($sikshya_checkout_page == $post->ID) {
			$post_states['sikshya_page_for_checkout'] = __('Sikshya Checkout Page', 'sikshya');
		}

		if ($sikshya_thankyou_page == $post->ID) {
			$post_states['sikshya_page_for_thankyou'] = __('Sikshya Thank You Page', 'sikshya');
		}

		if ($sikshya_account_page == $post->ID) {
			$post_states['sikshya_page_for_my_account'] = __('Sikshya My Account Page', 'sikshya');
		}

		if ($sikshya_registration_page == $post->ID) {
			$post_states['sikshya_page_for_my_account'] = __('Sikshya Registration Page', 'sikshya');
		}

		if ($sikshya_login_page == $post->ID) {
			$post_states['sikshya_page_for_my_account'] = __('Sikshya Login Page', 'sikshya');
		}

		return $post_states;
	}
}

new Sikshya_Admin();
