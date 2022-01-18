<?php


if (!defined('ABSPATH')) {
	exit;
}


class Sikshya_REST_API_Settings_V1
{

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'sikshya/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';


	/**
	 * Coupons actions.
	 */
	public function __construct()
	{
	}

	/**
	 * Register the routes for coupons.
	 */
	public function register_routes()
	{
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_settings'),
				'permission_callback' => array($this, 'get_settings_permission_check'),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/update', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'update_settings'),
				'permission_callback' => array($this, 'get_settings_update_permission_check'),
			),
		));

	}

	public function get_settings(\WP_REST_Request $request)
	{
		try {

			$response = array(
				'currency' => get_option('sikshya_currency', 'USD'),
				'currency_symbol_type' => get_option('sikshya_currency_symbol_type', 'symbol'),
				'currency_position' => get_option('sikshya_currency_position', 'left'),
				'thousand_separator' => get_option('sikshya_thousand_separator', ','),
				'number_of_decimals' => get_option('sikshya_price_number_decimals', 2),
				'decimal_separator' => get_option('sikshya_decimal_separator', '.'),
			);
			return new \WP_REST_Response($response, 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	public function update_settings(\WP_REST_Request $request)
	{
		$currency = sanitize_text_field($request->get_param('currency'));
		$currency_position = sanitize_text_field($request->get_param('currency_position'));
		$currency_symbol_type = sanitize_text_field($request->get_param('currency_symbol_type'));
		$decimal_separator = sanitize_text_field($request->get_param('decimal_separator'));
		$price_number_decimals = absint($request->get_param('number_of_decimals'));
		$thousand_separator = sanitize_text_field($request->get_param('thousand_separator'));
		try {

			update_option('sikshya_currency', $currency);
			update_option('sikshya_currency_symbol_type', $currency_symbol_type);
			update_option('sikshya_currency_position', $currency_position);
			update_option('sikshya_thousand_separator', $thousand_separator);
			update_option('sikshya_price_number_decimals', $price_number_decimals);
			update_option('sikshya_decimal_separator', $decimal_separator);

			return new \WP_REST_Response('Success', 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	public function get_settings_permission_check()
	{

		if (current_user_can('manage_options') && is_user_logged_in()) {
			return true;
		}
		return false;
	}

	public function get_settings_update_permission_check()
	{

		if (current_user_can('manage_options') && is_user_logged_in()) {
			return true;
		}
		return false;
	}

}
