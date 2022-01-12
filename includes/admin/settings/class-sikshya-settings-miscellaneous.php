<?php
/**
 * Sikshya Miscellaneous Settings
 *
 * @package Sikshya/Admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (class_exists('Sikshya_Settings_Miscellaneous', false)) {
	return new Sikshya_Settings_Miscellaneous();
}

/**
 * Sikshya_Settings_Checkout.
 */
class Sikshya_Settings_Miscellaneous extends Sikshya_Admin_Settings_Base
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id = 'miscellaneous';
		$this->label = __('Miscellaneous', 'sikshya');

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections()
	{
		$sections = array(
			'' => __('Miscellaneous Settings', 'sikshya'),
		);

		return apply_filters('sikshya_get_sections_' . $this->id, $sections);
	}

	/**
	 * Output the settings.
	 */
	public function output()
	{
		global $current_section;

		$settings = $this->get_settings($current_section);

		Sikshya_Admin_Settings::output_fields($settings);
	}

	/**
	 * Save settings.
	 */
	public function save()
	{
		global $current_section;

		$settings = $this->get_settings($current_section);
		Sikshya_Admin_Settings::save_fields($settings);

		if ($current_section) {
			do_action('sikshya_update_options_' . $this->id . '_' . $current_section);
		}
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings($current_section = '')
	{
		$terms_setup_link = admin_url('admin.php?page=sikshya-settings&tab=general&section=pages');
		$privacy_setup_link = admin_url('options-privacy.php');

		return apply_filters('sikshya_get_settings_' . $this->id, array(
			array(
				'title' => __('Miscellaneous Settings', 'sikshya'),
				'type' => 'title',
				'id' => 'sikshya_miscellaneous_options',
			),
			array(
				'title' => __('Log Options', 'sikshya'),
				'desc' => __('This option allows you to setup log option for sikshya plugin. Log option might be on file or on db.', 'sikshya'),
				'desc_tip' => true,
				'id' => 'sikshya_log_options',
				'type' => 'select',
				'default' => 'db',
				'options' => array(
					'file' => __('File', 'sikshya'),
					'db' => __('Database', 'sikshya'),
				)
			),
			array(
				'type' => 'sectionend',
				'id' => 'sikshya_miscellaneous_options',
			),

		), $current_section);
	}
}

return new Sikshya_Settings_Checkout();
