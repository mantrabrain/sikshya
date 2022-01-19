<?php
/**
 * Initialize this version of the REST API.
 *
 * @package Sikshya\RestAPI
 */


defined('ABSPATH') || exit;

/**
 * Class responsible for loading the REST API and all REST API namespaces.
 */
class Sikshya_Rest_Server
{

	/**
	 * The single instance of the class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function __construct()
	{
	}

	/**
	 * Get class instance.
	 *
	 * @return object Instance.
	 */
	final public static function instance()
	{
		if (null === static::$instance) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone()
	{
	}

	/**
	 * Prevent unserializing.
	 */
	final public function __wakeup()
	{
		wc_doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'sikshya'), '0.0.15');
		die();
	}

	/**
	 * REST API namespaces and endpoints.
	 *
	 * @var array
	 */
	protected $controllers = array();

	/**
	 * Hook into WordPress ready to init the REST API as needed.
	 */
	public function init()
	{
		add_action('rest_api_init', array($this, 'register_rest_routes'), 10);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes()
	{
		foreach ($this->get_rest_namespaces() as $namespace => $controllers) {
			foreach ($controllers as $controller_name => $controller_class) {
				$this->controllers[$namespace][$controller_name] = new $controller_class();
				$this->controllers[$namespace][$controller_name]->register_routes();
			}
		}
	}

	/**
	 * Get API namespaces - new namespaces should be registered here.
	 *
	 * @return array List of Namespaces and Main controller classes.
	 */
	protected function get_rest_namespaces()
	{
		return apply_filters(
			'sikshya_rest_api_get_rest_namespaces',
			array(
				'sikshya/v1' => $this->get_v1_controllers(),
			)
		);
	}

	/**
	 * List of controllers in the sikshya/v1 namespace.
	 *
	 * @return array
	 */
	protected function get_v1_controllers()
	{
		return array(
			'settings/general' => 'Sikshya_REST_API_Setting_General_V1',
			'settings/pages' => 'Sikshya_REST_API_Setting_Pages_V1',
		);
	}
}
