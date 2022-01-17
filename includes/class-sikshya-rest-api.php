<?php

class Sikshya_Rest_API
{
 

	public function __construct()
	{

	}

	public function register_routes()
	{
		register_rest_route(YATRA_REST_WEBHOOKS_NAMESPACE, self::REST_ROUTE, array(
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => array($this, 'handle_request'),
			'permission_callback' => array($this, 'validate_request')
		));
	}
}

add_action('rest_api_init', function () {
	$handler = new Sikshya_Rest_API();
	$handler->register_routes();
});
