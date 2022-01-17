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

	}

	public function get_settings(\WP_REST_Request $request)
	{

		try {
			return new \WP_REST_Response('Success', 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	public function get_settings_permission_check()
	{

		if (current_user_can('manage_options')) {
			return true;
		}
		return false;
	}

}
