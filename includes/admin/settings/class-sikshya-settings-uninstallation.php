<?php
/**
 *
 * @package Sikshya/Admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (class_exists('Sikshya_Settings_Uninstallation', false)) {
	return new Sikshya_Settings_Uninstallation();
}

/**
 * Sikshya_Settings_Uninstallation.
 */
class Sikshya_Settings_Uninstallation extends Sikshya_Admin_Settings_Base
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id = 'uninstallation';
		$this->label = __('Uninstallation', 'sikshya');

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
			'' => __('Data', 'sikshya'),
			'others' => __('Others', 'sikshya'),
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
		if ('others' === $current_section) {
			$settings = apply_filters(
				'sikshya_settings_uninstallation_others',
				array(
					array(
						'title' => __('Others', 'sikshya'),
						'type' => 'title',
						'desc' => '',
						'id' => 'uninstallation_other_settings',
					),
					array(
						'type' => 'sectionend',
						'id' => 'uninstallation_other_settings',
					),

				)

			);
		} else {
			$settings = apply_filters(
				'sikshya_settings_uninstallation_data',
				array(

					array(
						'title' => __('Data', 'sikshya'),
						'type' => 'title',
						'id' => 'data_options',
					),

					array(
						'title' => __('Remove All Data', 'sikshya'),
						'desc' => __('WARNING::Check this option only if you want to remove all data related to sikshya plugin from your website.', 'sikshya'),
						'id' => 'sikshya_remove_all_data_on_uninstallation',
						'default' => false,
						'type' => 'checkbox',
					),
					array(
						'type' => 'sectionend',
						'id' => 'pricing_options',
					),

				)

			);

		}

		return apply_filters('sikshya_get_settings_' . $this->id, $settings, $current_section);
	}
}

return new Sikshya_Settings_Uninstallation();
