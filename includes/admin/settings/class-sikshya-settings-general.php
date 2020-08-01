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

if (class_exists('Sikshya_Settings_General', false)) {
	return new Sikshya_Settings_General();
}

/**
 * Sikshya_Settings_General.
 */
class Sikshya_Settings_General extends Sikshya_Admin_Settings_Base
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id = 'general';
		$this->label = __('General', 'sikshya');

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
			'' => __('General', 'sikshya'),
			'pages' => __('Pages', 'sikshya'),
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
		if ('pages' === $current_section) {
			$settings = apply_filters(
				'sikshya_settings_general_pages',
				array(

					array(
						'title' => __('Pages', 'sikshya'),
						'type' => 'title',
						'id' => 'general_options',
					),

					array(
						'title' => __('Account page', 'sikshya'),
						'id' => 'sikshya_account_page',
						'default' => '',
						'type' => 'single_select_page',
					),
					array(
						'title' => __('Registration page', 'sikshya'),
						'id' => 'sikshya_registration_page',
						'default' => '',
						'type' => 'single_select_page',
					),
					array(
						'title' => __('Login page', 'sikshya'),
						'id' => 'sikshya_login_page',
						'default' => '',
						'type' => 'single_select_page',
					),
					array(
						'title' => __('Cart page', 'sikshya'),
						'id' => 'sikshya_cart_page',
						'default' => '',
						'type' => 'single_select_page',
					),
					array(
						'title' => __('Checkout page', 'sikshya'),
						'id' => 'sikshya_checkout_page',
						'default' => '',
						'type' => 'single_select_page',
					),
					array(
						'type' => 'sectionend',
						'id' => 'pricing_options',
					),

				)

			);

		} else {
			$settings = apply_filters(
				'sikshya_settings_general_general',
				array(
					array(
						'title' => __('General Settings', 'sikshya'),
						'type' => 'title',
						'desc' => '',
						'id' => 'sikshya_general_options',
					),
					array(
						'title' => __('Currency', 'sikshya'),
						'desc' => __('Currency for price of course.', 'sikshya'),
						'id' => 'sikshya_currency',
						'default' => 'USD',
						'type' => 'select',
						'options' => sikshya_get_currency_with_symbol()
					),
					array(
						'title' => __('Currency Position', 'sikshya'),
						'desc' => __('Currency position.', 'sikshya'),
						'id' => 'sikshya_currency_position',
						'default' => 'left',
						'type' => 'select',
						'options' => array(
							'left' => __('Left', 'sikshya'),
							'right' => __('Right', 'sikshya'),
						)
					),
					array(
						'type' => 'sectionend',
						'id' => 'sikshya_general_options',
					),

				)

			);
		}

		return apply_filters('sikshya_get_settings_' . $this->id, $settings, $current_section);
	}
}

return new Sikshya_Settings_General();
