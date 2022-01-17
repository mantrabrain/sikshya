<?php

class Sikshya_Rest_API_Settings extends Sikshya_Rest_API_Abstract
{

	/**
	 * Endpoint route.
	 *
	 * @since 1.0.0
	 */
	const REST_ROUTE = 'authorizenet';

	/**
	 * Webhook payload
	 *
	 * @var object
	 * @since 1.0.0
	 */
	private $event;

	/**
	 * Registers REST API routes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes()
	{
		register_rest_route(YATRA_REST_WEBHOOKS_NAMESPACE, self::REST_ROUTE, array(
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => array($this, 'handle_request'),
			'permission_callback' => array($this, 'validate_request')
		));
	}

	/**
	 * Handles the current request.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function handle_request(\WP_REST_Request $request)
	{


		try {

			do_action('yatra_authorizenet_webhook_event', $request);


			return new \WP_REST_Response('Success', 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	/**
	 * Validates the webhook
	 *
	 * @return bool|\WP_Error
	 * @since 2.11
	 */
	public function validate_request()
	{


		try {
			//validate webhook


			return true;
		} catch (\Exception $e) {
			return new \WP_Error('validation_failure', $e->getMessage());
		}
	}
}

add_action('rest_api_init', function () {
	$handler = new Sikshya_Rest_API();
	$handler->register_routes();
});
