<?php

class Sikshya_Admin_Importer
{

	public function __construct()
	{
		add_action('admin_menu', array($this, 'importer_menu'), 55);
		add_action('admin_enqueue_scripts', array($this, 'importer_scripts'));
	}

	public function importer_menu()
	{
		add_submenu_page(
			'sikshya',
			__('Course Importer', 'sikshya'),
			__('Course Importer', 'sikshya'),
			'administrator',
			'sikshya_course_importer', array($this, 'settings_page'));


	}


	/**
	 * Init the settings page.
	 */
	public function settings_page()
	{
		sikshya_load_admin_template('import-export.importer');
	}

	public function importer_scripts($hook)
	{
		if ('sikshya_page_sikshya_course_importer' != $hook) {
			return;
		}
		wp_enqueue_style('sikshya_importer_style', SIKSHYA_ASSETS_URL . '/admin/css/importer.css', array(), SIKSHYA_VERSION);
	}
}

new Sikshya_Admin_Importer();
