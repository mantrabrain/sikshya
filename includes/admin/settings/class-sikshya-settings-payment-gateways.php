<?php
/**
 * Sikshya Payment Gateways Settings
 *
 * @package Sikshya/Admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (class_exists('Sikshya_Settings_Payment_Gateways', false)) {
	return new Sikshya_Settings_Payment_Gateways();
}

/**
 * Sikshya_Settings_Payment_Gateways.
 */
class Sikshya_Settings_Payment_Gateways extends Sikshya_Admin_Settings_Base
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id = 'payment-gateways';
		$this->label = __('Payment Gateways', 'sikshya');

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
		$settings = array();
		$status_page_url = admin_url('admin.php?page=sikshya-status&tab=logs');


		if ('' === $current_section) {
			$settings = apply_filters(
				'sikshya_settings_payment_gateways_general',
				array(
					array(
						'title' => __('Payment Gateways General Settings', 'sikshya'),
						'type' => 'title',
						'desc' => '',
						'id' => 'sikshya_payment_gateways_general_options',
					),
					array(
						'title' => __('Test Mode', 'sikshya'),
						'desc' => __(' While in test mode no live transactions are processed. To fully use test mode, you must have a sandbox (test) account for the payment gateway you are testing.', 'sikshya'),
						'id' => 'sikshya_payment_gateway_test_mode',
						'type' => 'checkbox',
					),
					array(
						'title' => __('Log Gateway information', 'sikshya'),
						'desc' => sprintf(__('When you enable this option all payment gateway response and other payment information will be stored into <a href="%s" target="_blank">Sikshya logging system.</a> <strong style="color:red;">Please enable this option only for debugging purpose.</strong>', 'sikshya'), $status_page_url),
						'id' => 'sikshya_payment_gateway_enable_logging',
						'type' => 'checkbox',
						'default' => 'no',
					),
					array(
						'title' => __('Payment Gateways', 'sikshya'),
						'id' => 'sikshya_payment_gateways',
						'type' => 'multicheckbox',
						'options' => sikshya_get_payment_gateways()

					),

					array(
						'type' => 'sectionend',
						'id' => 'sikshya_payment_gateways_general_options',
					),

				)

			);

		}

		return apply_filters('sikshya_get_settings_' . $this->id, $settings, $current_section);
	}
}

return new Sikshya_Settings_Payment_Gateways();
