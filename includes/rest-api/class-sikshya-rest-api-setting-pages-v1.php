<?php


if (!defined('ABSPATH')) {
	exit;
}


class Sikshya_REST_API_Setting_Pages_V1
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
	protected $rest_base = 'settings/pages';


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
				'account_page' => sikshya_get_account_page(),
				'registration_page' => sikshya_get_user_registration_page(),
				'login_page' => sikshya_get_login_page(),
				'cart_page' => sikshya_get_cart_page(),
				'checkout_page' => sikshya_get_checkout_page()
			);
			return new \WP_REST_Response($response, 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	public function update_settings(\WP_REST_Request $request)
	{
		$cart_page = absint($request->get_param('cart_page'));
		$checkout_page = absint($request->get_param('checkout_page'));
		$login_page = absint($request->get_param('login_page'));
		$registration_page = absint($request->get_param('registration_page'));
		$account_page = absint($request->get_param('account_page'));
		try {

			update_option('sikshya_account_page', $account_page);
			update_option('sikshya_registration_page', $registration_page);
			update_option('sikshya_login_page', $login_page);
			update_option('sikshya_cart_page', $cart_page);
			update_option('sikshya_checkout_page', $checkout_page);

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
