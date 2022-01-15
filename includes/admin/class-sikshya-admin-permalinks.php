<?php
/**
 * Adds settings to the permalinks admin settings page
 *
 * @class       Sikshya_Admin_Permalinks
 * @category    Admin
 * @package     sikshya/inc/admin
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Sikshya_Admin_Permalinks', false)) :

	/**
	 * Sikshya_Admin_Permalinks Class.
	 */
	class Sikshya_Admin_Permalinks
	{

		/**
		 * Permalink settings.
		 *
		 * @var array
		 */
		private $permalinks = array();

		/**
		 * Hook in tabs.
		 */
		public function __construct()
		{

			add_action('current_screen', array($this, 'conditional_includes'));
		}

		public function init()
		{

			$this->settings_init();
			$this->settings_save();
		}

		public function conditional_includes()
		{
			if (!$screen = get_current_screen()) {
				return;
			}

			switch ($screen->id) {
				case 'options-permalink' :
					$this->init();
					break;
			}
		}

		/**
		 * Init our settings.
		 */
		public function settings_init()
		{

			// Add our settings
			add_settings_field(
				'sikshya_course_base',            // id
				__('Course Base', 'sikshya'),   // setting title
				array($this, 'sikshya_course_base_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);

			add_settings_field(
				'sikshya_course_category_base',            // id
				__('Course Category Base', 'sikshya'),   // setting title
				array($this, 'sikshya_course_category_base_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);

			add_settings_field(
				'sikshya_course_tag_base',            // id
				__('Course Tag Base', 'sikshya'),   // setting title
				array($this, 'sikshya_course_tag_base_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);

			add_settings_field(
				'sikshya_lesson_base',            // id
				__('Lesson Base', 'sikshya'),   // setting title
				array($this, 'sikshya_lesson_base_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			add_settings_field(
				'sikshya_quiz_base',            // id
				__('Quiz Base', 'sikshya'),   // setting title
				array($this, 'sikshya_quiz_base_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			$this->permalinks = sikshya_get_permalink_structure();
		}

		/**
		 * Show a slug input box.
		 */
		public function sikshya_course_base_input()
		{

			?>
			<input name="sikshya_course_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_course_base']); ?>"
				   placeholder="<?php echo esc_attr_x('courses', 'slug', 'sikshya') ?>"/>
			<?php
		}

		/**
		 * Show a slug input box.
		 */
		public function sikshya_course_category_base_input()
		{

			?>
			<input name="sikshya_course_category_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_course_category_base']); ?>"
				   placeholder="<?php echo esc_attr_x('course-category', 'slug', 'sikshya') ?>"/>
			<?php
		}

		/**
		 * Show a slug input box.
		 */
		public function sikshya_course_tag_base_input()
		{

			?>
			<input name="sikshya_course_tag_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_course_tag_base']); ?>"
				   placeholder="<?php echo esc_attr_x('course-tag', 'slug', 'sikshya') ?>"/>
			<?php
		}

		/**
		 * Show a slug input box.
		 */
		public function sikshya_lesson_base_input()
		{

			?>
			<input name="sikshya_lesson_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_lesson_base']); ?>"
				   placeholder="<?php echo esc_attr_x('lessons', 'slug', 'sikshya') ?>"/>
			<?php
		}

		/**
		 * Show a slug input box.
		 */
		public function sikshya_quiz_base_input()
		{

			?>
			<input name="sikshya_quiz_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_quiz_base']); ?>"
				   placeholder="<?php echo esc_attr_x('quizzes', 'slug', 'sikshya') ?>"/>
			<?php
		}

		/**
		 * Save the settings.
		 */
		public function settings_save()
		{
			if (!is_admin()) {
				return;
			}
			// We need to save the options ourselves; settings api does not trigger save for the permalinks page.
			if (isset($_POST['permalink_structure'])) {

				$permalinks = (array)get_option('sikshya_permalinks', array());
				$permalinks['sikshya_course_base'] = trim(sanitize_text_field($_POST['sikshya_course_base']));
				$permalinks['sikshya_course_category_base'] = trim(sanitize_text_field($_POST['sikshya_course_category_base']));
				$permalinks['sikshya_course_tag_base'] = trim(sanitize_text_field($_POST['sikshya_course_tag_base']));
				$permalinks['sikshya_lesson_base'] = trim(sanitize_text_field($_POST['sikshya_lesson_base']));
				$permalinks['sikshya_quiz_base'] = trim(sanitize_text_field($_POST['sikshya_quiz_base']));

				update_option('sikshya_permalinks', $permalinks);
			}
		}
	}

endif;

return new Sikshya_Admin_Permalinks();
