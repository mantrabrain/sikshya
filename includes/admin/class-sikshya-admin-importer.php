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

		echo '<div class="wrap">';
		sikshya_load_admin_template('import-export.importer');
		$custom_post_type_lists['sikshya_custom_post_type_lists'] = array(
			SIKSHYA_COURSES_CUSTOM_POST_TYPE => __('Courses', 'sikshya'),
			SIKSHYA_SECTIONS_CUSTOM_POST_TYPE => __('Sections', 'sikshya'),
			SIKSHYA_LESSONS_CUSTOM_POST_TYPE => __('Lessons', 'sikshya'),
			SIKSHYA_QUIZZES_CUSTOM_POST_TYPE => __('Quizzes', 'sikshya'),
			SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE => __('Questions', 'sikshya'),
		);
		sikshya_load_admin_template('import-export.export', $custom_post_type_lists);
		echo '</div>';
	}

	public function importer_scripts($hook)
	{
		if ('sikshya_page_sikshya_course_importer' != $hook) {
			return;
		}
		wp_enqueue_style('sikshya_importer_style', SIKSHYA_ASSETS_URL . '/admin/css/importer.css', array(), SIKSHYA_VERSION);
		wp_enqueue_script('sikshya_importer_script', SIKSHYA_ASSETS_URL . '/admin/js/importer.js', array(), SIKSHYA_VERSION);

		$data =
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
			);
		wp_localize_script('sikshya_importer_script', 'sikshyaImporterData', $data);

	}
}

new Sikshya_Admin_Importer();
