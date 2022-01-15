<?php
/**
 * Adds settings to the permalinks admin settings page
 *
 * @class       Sikshya_Admin_Permalinks
 * @category    Admin
 * @package     yatra/inc/admin
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
				__('Course Base', 'yatra'),   // setting title
				array($this, 'course_slug_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			add_settings_field(
				'sikshya_course_category_slug',            // id
				__('Course Category base', 'yatra'),   // setting title
				array($this, 'course_category_slug_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			add_settings_field(
				'sikshya_course_tag_slug',            // id
				__('Course Tag base', 'yatra'),   // setting title
				array($this, 'course_tag_slug_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			add_settings_field(
				'yatra_activity_slug',            // id
				__('Activity base', 'yatra'),   // setting title
				array($this, 'activity_slug_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);

			add_settings_field(
				'yatra_attribute_slug',            // id
				__('Attribute base', 'yatra'),   // setting title
				array($this, 'attribute_slug_input'),  // display callback
				'permalink',                        // settings page
				'optional'                          // settings section
			);
			$this->permalinks = sikshya_get_permalink_structure();
		}

		/**
		 * Show a slug input box.
		 */
		public function course_base_input()
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
		public function course_category_slug_input()
		{

			?>
			<input name="sikshya_course_category_slug" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_course_category_slug']); ?>"
				   placeholder="<?php echo esc_attr_x('course-category', 'slug', 'yatra') ?>"/>
			<?php
		}

		public function course_tag_slug_input()
		{
			?>
			<input name="sikshya_course_tag_slug" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['sikshya_course_tag_slug']); ?>"
				   placeholder="<?php echo esc_attr_x('course-tag', 'slug', 'yatra') ?>"/>
			<?php
		}

		/**
		 * Show a slug input box.
		 */
		public function activity_slug_input()
		{

			?>
			<input name="yatra_activity_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['yatra_activity_base']); ?>"
				   placeholder="<?php echo esc_attr_x('travel-locations', 'slug', 'yatra') ?>"/>
			<?php
		}


		/**
		 * Show a slug input box.
		 */
		public function attribute_slug_input()
		{

			?>
			<input name="yatra_attributes_base" type="text" class="regular-text code"
				   value="<?php echo esc_attr($this->permalinks['yatra_attributes_base']); ?>"
				   placeholder="<?php echo esc_attr_x('attributes', 'slug', 'yatra') ?>"/>
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
				$permalinks['yatra_destination_base'] = trim(sanitize_text_field($_POST['yatra_destination_base']));
				$permalinks['yatra_activity_base'] = trim(sanitize_text_field($_POST['yatra_activity_base']));
				$permalinks['yatra_attributes_base'] = trim(sanitize_text_field($_POST['yatra_attributes_base']));

				update_option('yatra_permalinks', $permalinks);
			}
		}
	}

endif;

return new Sikshya_Admin_Permalinks();
