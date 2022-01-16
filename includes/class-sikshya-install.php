<?php
/**
 * Installation related functions and actions.
 *
 * @package Sikshya/Classes
 */

defined('ABSPATH') || exit;

/**
 * Sikshya_Install Class.
 */
class Sikshya_Install
{

	private static $update_callbacks = array(
		'0.0.11' => array(
			'sikshya_update_0011_section_meta',
		),
		'0.0.15' => array(
			'sikshya_update_0015_logs_update',
		)
	);

	/**
	 * Hook in tabs.
	 */
	public static function init()
	{
		add_action('init', array(__CLASS__, 'check_version'), 5);


	}

	/**
	 * Check Sikshya version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version()
	{
		if (!defined('IFRAME_REQUEST') && version_compare(get_option('sikshya_version'), sikshya()->version, '<')) {
			self::install();
			do_action('sikshya_updated');
		}
	}


	/**
	 * Install Sikshya.
	 */
	public static function install()
	{


		// Check if we are not already running this routine.
		if ('yes' === get_transient('sikshya_installing')) {
			return;
		}
		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient('sikshya_installing', 'yes', MINUTE_IN_SECONDS * 10);
		$sikshya_version = get_option('sikshya_version');

		if (empty($sikshya_version)) {
			self::create_tables();
			self::create_options();
			if (empty($sikshya_version) && apply_filters('sikshya_enable_setup_wizard', true)) {
				set_transient('_sikshya_activation_redirect', 1, 30);
			}
		}
		self::create_roles();
		self::setup_environment();
		self::versionwise_update();

		self::update_sikshya_version();
		delete_transient('sikshya_installing');

		do_action('sikshya_flush_rewrite_rules');
		do_action('sikshya_installed');
	}

	private static function setup_environment()
	{
		$options = array(
			'sikshya_queue_flush_rewrite_rules' => 'yes'
		);

		foreach ($options as $option_key => $option_value) {

			update_option($option_key, $option_value);
		}

	}

	private static function versionwise_update()
	{
		$sikshya_version = get_option('sikshya_version', null);

		if ($sikshya_version == '' || $sikshya_version == null || empty($sikshya_version)) {
			return;
		}
		if (version_compare($sikshya_version, sikshya()->version, '<')) {

			foreach (self::$update_callbacks as $version => $callbacks) {

				if (version_compare($sikshya_version, $version, '<')) {

					self::exe_update_callback($callbacks);
				}
			}
		}


	}

	private static function exe_update_callback($callbacks)
	{
		include_once SIKSHYA_PATH . '/includes/sikshya-update-functions.php';

		foreach ($callbacks as $callback) {

			call_user_func($callback);

		}
	}

	/**
	 * Update Sikshya version to current.
	 */
	private static function update_sikshya_version()
	{
		delete_option('sikshya_version');
		add_option('sikshya_version', sikshya()->version);
	}


	/**
	 * Default options.
	 *
	 * Sets up the default options used on the settings page.
	 */
	private static function create_options()
	{


		// Include settings so that we can run through defaults.
		include_once dirname(__FILE__) . '/admin/class-sikshya-admin-settings.php';

		$settings = Sikshya_Admin_Settings::get_settings_pages();

		foreach ($settings as $section) {
			if (!method_exists($section, 'get_settings')) {
				continue;
			}
			$subsections = array_unique(array_merge(array(''), array_keys($section->get_sections())));

			foreach ($subsections as $subsection) {
				foreach ($section->get_settings($subsection) as $value) {
					if (isset($value['default']) && isset($value['id'])) {
						$autoload = isset($value['autoload']) ? (bool)$value['autoload'] : true;
						add_option($value['id'], $value['default'], '', ($autoload ? 'yes' : 'no'));
					}
				}
			}
		}


		$pages = array(

			array(
				'post_content' => '[sikshya_registration]',
				'post_title' => 'Sikshya Registration',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			), array(
				'post_content' => '[sikshya_account]',
				'post_title' => 'Sikshya Account',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			), array(
				'post_content' => '[sikshya_login]',
				'post_title' => 'Sikshya Login',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			), array(
				'post_content' => '[sikshya_cart]',
				'post_title' => 'Sikshya Cart',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			),
			array(
				'post_content' => '[sikshya_checkout]',
				'post_title' => 'Sikshya Checkout',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			),
			array(
				'post_content' => '<p>Your order has been successfully placed. You can check your order from myaccount page</p>',
				'post_title' => 'Sikshya Thank You',
				'post_status' => 'publish',
				'post_type' => 'page',
				'comment_status' => 'closed'

			)
		);

		foreach ($pages as $page) {

			$page_id = wp_insert_post($page);

			if ($page['post_title'] == 'Sikshya Registration') {
				update_option('sikshya_registration_page', $page_id);
			}
			if ($page['post_title'] == 'Sikshya Account') {
				update_option('sikshya_account_page', $page_id);
			}
			if ($page['post_title'] == 'Sikshya Login') {
				update_option('sikshya_login_page', $page_id);
			}
			if ($page['post_title'] == 'Sikshya Cart') {
				update_option('sikshya_cart_page', $page_id);
			}
			if ($page['post_title'] == 'Sikshya Checkout') {
				update_option('sikshya_checkout_page', $page_id);
			}
			if ($page['post_title'] == 'Sikshya Thank You') {
				update_option('sikshya_thankyou_page', $page_id);
			}

		}
	}


	/**
	 * Add the default terms for WC taxonomies - product types and order statuses. Modify this at your own risk.
	 */

	/**
	 * Set up the database tables which the plugin needs to function.
	 */
	private static function create_tables()
	{
		global $wpdb;

		$wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';


		$all_schemes = self::get_schema();

		foreach ($all_schemes as $scheme) {
			dbDelta($scheme);
		}


	}

	private static function get_schema()
	{
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'sikshya_';

		$collate = '';

		if ($wpdb->has_cap('collation')) {
			$collate = $wpdb->get_charset_collate();
		}

		// Order Items Table
		$tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}order_items (
		  order_item_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  item_name LONGTEXT  NOT NULL,
		  order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
		  order_datetime DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY  (order_item_id)
		) $collate;
		";
		// Order Item Data Table
		$tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}order_itemmeta (
		  meta_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  order_item_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
		  meta_key VARCHAR(255)  NOT NULL DEFAULT '',
		  meta_value LONGTEXT  NOT NULL,
		  PRIMARY KEY  (meta_id),
		  KEY sikshya_order_item_id (order_item_id)
		) $collate;
		";
		//  User Items Table
		$tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}user_items (
		  user_item_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  user_id BIGINT(20) NOT NULL DEFAULT '-1',
		  item_id BIGINT(20) NOT NULL DEFAULT '-1',
		  start_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  start_time_gmt DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  end_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  end_time_gmt DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  item_type VARCHAR(45) NOT NULL DEFAULT '',
		  status VARCHAR(45) NOT NULL DEFAULT '',
		  reference_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
		  reference_type VARCHAR(45) DEFAULT '0',
		  parent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
		  PRIMARY KEY  (user_item_id)
		  ) $collate;
		";
		// User Item Meta Table
		$tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}user_itemmeta (
		  meta_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  user_item_id BIGINT(20) UNSIGNED NOT NULL,
		  meta_key VARCHAR(255)  NOT NULL DEFAULT '',
		  meta_value LONGTEXT  NOT NULL,
		  PRIMARY KEY  (meta_id),
		  KEY sikshya_user_item_id(user_item_id),
		  KEY meta_key(meta_key(191))
		  ) $collate;
		  ";

		$tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}logs (
          log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          timestamp datetime NOT NULL,
          level smallint(4) NOT NULL,
          source varchar(200) NOT NULL,
          message longtext NOT NULL,
          context longtext NULL,
          PRIMARY KEY (log_id),
          KEY level (level)
        ) $collate;
		  ";

		return $tables;

		return $tables;
	}


	public static function get_tables()
	{
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'sikshya_';
		$tables = array(
			"{$table_prefix}students",
			"{$table_prefix}user_itemmeta",
			"{$table_prefix}user_items",
			"{$table_prefix}order_itemmeta",
			"{$table_prefix}order_items"
		);

		/**
		 * Filter the list of known Sikshya tables.
		 *
		 * If Sikshya plugins need to add new tables, they can inject them here.
		 *
		 * @param array $tables An array of Sikshya-specific database table names.
		 */
		$tables = apply_filters('sikshya_install_get_tables', $tables);

		return $tables;
	}

	/**
	 * Create roles and capabilities.
	 */
	public static function create_roles()
	{
		global $wp_roles;

		if (!class_exists('WP_Roles')) {
			return;
		}

		if (!isset($wp_roles)) {
			$wp_roles = new WP_Roles(); // @codingStandardsIgnoreLine
		}

		$capabilities = self::get_core_capabilities();

		foreach ($capabilities as $cap_group) {
			foreach ($cap_group as $cap) {

			}
		}
	}


	/**
	 * Get capabilities for Sikshya - these are assigned to admin/shop manager during installation or reset.
	 *
	 * @return array
	 */
	private static function get_core_capabilities()
	{
		$capabilities = array();

		return $capabilities;

		$capabilities['core'] = array(
			'manage_sikshya',
			'view_sikshya_reports',
		);

		$capability_types = array();//array('product', 'shop_order', 'shop_coupon');

		foreach ($capability_types as $capability_type) {

			$capabilities[$capability_type] = array(
				// Post type.
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",

				// Terms.
				"manage_{$capability_type}_terms",
				"edit_{$capability_type}_terms",
				"delete_{$capability_type}_terms",
				"assign_{$capability_type}_terms",
			);
		}

		return $capabilities;
	}

	/**
	 * Remove Sikshya roles.
	 */
	public static function remove_roles()
	{
		global $wp_roles;

		if (!class_exists('WP_Roles')) {
			return;
		}

		if (!isset($wp_roles)) {
			$wp_roles = new WP_Roles(); // @codingStandardsIgnoreLine
		}

		$capabilities = self::get_core_capabilities();

		foreach ($capabilities as $cap_group) {
			foreach ($cap_group as $cap) {
				$wp_roles->remove_cap('sikshya_instructor', $cap);
				$wp_roles->remove_cap('sikshya_student', $cap);
			}
		}

		remove_role('sikshya_instructor');
		remove_role('sikshya_student');
	}

	public static function verify_base_tables($execute = false)
	{
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ($execute) {
			self::create_tables();
		}
	}

	public static function drop_tables()
	{
		global $wpdb;

		$tables = self::get_tables();

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

}

Sikshya_Install::init();
