<?php


if (!defined('ABSPATH')) {
	exit;
}


class Sikshya_REST_API_Setting_Themes_V1
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
	protected $rest_base = 'settings/themes';


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
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'get_settings'),
				'permission_callback' => array($this, 'get_settings_permission_check'),
			),
		));
		register_rest_route($this->namespace, '/' . $this->rest_base . '/action', array(
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

			$theme = sanitize_text_field($request->get_param('theme'));

			if ($theme !== 'pragyan') {
				throw  new Exception("Invalid Theme Slug", 500);
			}

			$sikshya_theme = wp_get_theme($theme);

			$installed = false;

			$activated = false;

			if ($sikshya_theme->exists()) {

				$installed = true;
			}

			$style_parent_theme = wp_get_theme(get_template());

			if ($theme === $style_parent_theme->get_stylesheet() && $installed) {

				$activated = true;
			}

			$response = array(

				'installed' => $installed,
				'activated' => $activated

			);
			return new \WP_REST_Response($response, 200);
		} catch (\Exception $e) {

			$response_code = $e->getCode() > 0 ? $e->getCode() : 500;

			return new \WP_REST_Response($e->getMessage(), $response_code);
		}
	}

	public function update_settings(\WP_REST_Request $request)
	{
		$user_action = sanitize_text_field($request->get_param('action'));

		$theme = sanitize_text_field($request->get_param('theme'));

		try {
			if ($theme !== 'pragyan') {

				throw  new Exception("Invalid Theme Slug", 500);
			}


			$sikshya_theme = wp_get_theme($theme);

			if ($sikshya_theme->exists()) {

				$user_action = 'activate';

			} else {
				$user_action = 'install';
			}
			if ($user_action === "install") {

				$theme_zip = "https://downloads.wordpress.org/theme/{$theme}.latest-stable.zip";

				include_once ABSPATH . 'wp-admin/includes/file.php';
				include_once ABSPATH . 'wp-admin/includes/misc.php';
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				wp_cache_flush();

				ob_start();

				$upgrader = new Theme_Upgrader();

				$installed = $upgrader->install($theme_zip);

				ob_get_clean();

				if (is_wp_error($installed)) {

					throw  new Exception($installed->get_error_message(), $installed->get_error_code());
				}
			}

			if ($user_action === "activate") {
				switch_theme($theme);
			}

			$this->get_settings($request);

		} catch (\Exception $e) {

			$this->get_settings($request);
		}
	}

	public function get_settings_permission_check()
	{

		if (current_user_can('manage_options') && is_user_logged_in() && current_user_can('install_themes')) {
			return true;
		}
		return false;
	}

	public function get_settings_update_permission_check()
	{

		if (current_user_can('manage_options') && is_user_logged_in() && current_user_can('install_themes')) {
			return true;
		}
		return false;
	}

}
