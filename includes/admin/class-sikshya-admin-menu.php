<?php

class Sikshya_Admin_Menu
{
	const ADMIN_PAGE = 'sikshya';

	public function __construct()
	{
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('wp_loaded', array($this, 'save_settings'));


	}

	public function admin_menu()
	{


		add_submenu_page('edit.php?post_type=sik_courses', __('Students', 'sikshya'), __('Students', 'sikshya'), 'administrator', 'students', array($this, 'students_page_handler'), 9);


		$settings_page = add_submenu_page(
			'edit.php?post_type=sik_courses',
			__('Sikshya settings', 'sikshya'),
			__('Settings', 'sikshya'),
			'administrator',
			'sik-settings', array($this, 'settings_page'));

		add_action('load-' . $settings_page, array($this, 'settings_page_init'));
	}


	public function settings_page_init()
	{

		// Include settings pages.
		Sikshya_Admin_Settings::get_settings_pages();
		/*
		// Add any posted messages.
		if (!empty($_GET['sik_error'])) { // WPCS: input var okay, CSRF ok.
			Sikshya_Admin_Settings::add_error(wp_kses_post(wp_unslash($_GET['sik_error']))); // WPCS: input var okay, CSRF ok.
		}

		if (!empty($_GET['sik_message'])) { // WPCS: input var okay, CSRF ok.
			Sikshya_Admin_Settings::add_message(wp_kses_post(wp_unslash($_GET['sik_message']))); // WPCS: input var okay, CSRF ok.
		}*/

		do_action('sikshya_settings_page_init');
	}

	/**
	 * Init the settings page.
	 */
	public function settings_page()
	{
		Sikshya_Admin_Settings::output();
	}

	public function save_settings()
	{

		global $current_tab, $current_section;

		// We should only save on the settings page.
		if (!is_admin() || !isset($_GET['page']) || 'sik-settings' !== $_GET['page']) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
			return;
		}

		// Include settings pages.
		Sikshya_Admin_Settings::get_settings_pages();

		// Get current tab/section.
		$current_tab = empty($_GET['tab']) ? 'general' : sanitize_title(wp_unslash($_GET['tab'])); // WPCS: input var okay, CSRF ok.
		$current_section = empty($_REQUEST['section']) ? '' : sanitize_title(wp_unslash($_REQUEST['section'])); // WPCS: input var okay, CSRF ok.

		// Save settings if data has been posted.
		if ('' !== $current_section && apply_filters("sikshya_save_settings_{$current_tab}_{$current_section}", !empty($_POST['save']))) { // WPCS: input var okay, CSRF ok.
			Sikshya_Admin_Settings::save();
		} elseif ('' === $current_section && apply_filters("sikshya_save_settings_{$current_tab}", !empty($_POST['save']))) { // WPCS: input var okay, CSRF ok.
			Sikshya_Admin_Settings::save();
		}
	}

	public function students_page_handler()
	{
		include_once 'list-tables/class-sikshya-admin-list-table-students.php';

		include_once 'views/html-students-list.php';
	}
}

new Sikshya_Admin_Menu();
