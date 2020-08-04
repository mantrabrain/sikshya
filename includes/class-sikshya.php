<?php
/**
 * Sikshya setup
 *
 * @package Sikshya
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Main Sikshya Class.
 *
 * @class Sikshya
 */
final class Sikshya
{

	/**
	 * Sikshya version.
	 *
	 * @var string
	 */
	public $version = SIKSHYA_VERSION;

	/**
	 * Sikshya_Helper_Main instance.
	 *
	 * @var Sikshya_Helper_Main
	 */
	public $helper = null;

	/**
	 * WP_Error instance.
	 *
	 * @var WP_Error
	 */
	public $errors = null;

	/**
	 * Sikshya_Messages instance.
	 *
	 * @var Sikshya_Messages
	 */
	public $messages = null;

	/**
	 * Notice Key
	 *
	 * @var String
	 */
	public $notice_key = 'sikshya_message';

	/**
	 * Sikshya_Core_Course instance.
	 *
	 * @var Sikshya_Core_Course
	 */
	public $course = null;

	/**
	 * Sikshya_Core_Lesson instance.
	 *
	 * @var Sikshya_Core_Lesson
	 */
	public $lesson = null;

	/**
	 * Sikshya_Core_Section instance.
	 *
	 * @var Sikshya_Core_Section
	 */
	public $section = null;

	/**
	 * Sikshya_Core_Quiz instance.
	 *
	 * @var Sikshya_Core_Quiz
	 */
	public $quiz = null;

	/**
	 * Sikshya_Core_Question instance.
	 *
	 * @var Sikshya_Core_Question
	 */
	public $question = null;


	/**
	 * Sikshya_Core_Order instance.
	 *
	 * @var Sikshya_Core_Order
	 */
	public $order = null;

	/**
	 * Sikshya_Role_Manager instance.
	 *
	 * @var Sikshya_Role_Manager
	 */
	public $role = null;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya
	 * @since 1.0.0
	 *
	 *
	 */
	/**
	 * Sikshya_Core_Student instance.
	 *
	 * @var Sikshya_Core_Student
	 */
	public $student = null;

	/**
	 * Sikshya_Core_Cart instance.
	 *
	 * @var Sikshya_Core_Cart
	 */
	public $cart = null;


	/**
	 * Sikshya_Core_Checkout instance.
	 *
	 * @var Sikshya_Core_Checkout
	 */
	public $checkout = null;

	/**
	 * Sikshya_Core_Session instance.
	 *
	 * @var Sikshya_Core_Session
	 */
	public $session = null;


	protected static $_instance = null;

	/**
	 * Main Sikshya Instance.
	 *
	 * Ensures only one instance of Sikshya is loaded or can be loaded.
	 *
	 * @return Sikshya - Main instance.
	 * @see sikshya()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone()
	{
		_doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'sikshya'), '1.0.0');
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup()
	{
		_doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'sikshya'), '1.0.0');
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key Key name.
	 * @return mixed
	 */
	public function __get($key)
	{
		/*if (in_array($key, array('payment_gateways', 'shipping', 'mailer', 'checkout'), true)) {
			return $this->$key();
		}*/
	}

	/**
	 * Sikshya Constructor.
	 */
	protected function __construct()
	{
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * When WP has loaded all plugins, trigger the `sikshya_loaded` hook.
	 *
	 * This ensures `sikshya_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 * the load order. See #21524 for details.
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded()
	{
		do_action('sikshya_loaded');
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks()
	{
		register_activation_hook(SIKSHYA_FILE, array('Sikshya_Install', 'install'));
		//register_shutdown_function(array($this, 'log_errors'));

		add_action('plugins_loaded', array($this, 'on_plugins_loaded'), -1);
		add_action('after_setup_theme', array($this, 'setup_environment'));
		add_action('after_setup_theme', array($this, 'include_template_functions'), 11);
		add_action('init', array($this, 'init'), 0);
		add_action('init', array('Sikshya_Shortcodes', 'init'));

		//add_action('init', array('WC_Emails', 'init_transactional_emails'));
//         add_action('activated_plugin', array($this, 'activated_plugin'));
//        add_action('deactivated_plugin', array($this, 'deactivated_plugin'));
	}

	/**
	 * Ensures fatal errors are logged so they can be picked up in the status report.
	 *
	 * @since 1.0.0
	 */
	public function log_errors()
	{

	}

	/**
	 * Define WC Constants.
	 */
	private function define_constants()
	{
		//include_once "class-sikshya-manager.php";

		global $wpdb;

		$this->define('SIKSHYA_DB_PREFIX', $wpdb->base_prefix . 'sikshya_');
		$this->define('SIKSHYA_ORDERS_CUSTOM_POST_TYPE', 'sik_orders');
		$this->define('SIKSHYA_COURSES_CUSTOM_POST_TYPE', 'sik_courses');
		$this->define('SIKSHYA_SECTIONS_CUSTOM_POST_TYPE', 'sik_sections');
		$this->define('SIKSHYA_LESSONS_CUSTOM_POST_TYPE', 'sik_lessons');
		$this->define('SIKSHYA_QUIZZES_CUSTOM_POST_TYPE', 'sik_quizzes');
		$this->define('SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE', 'sik_questions');
		$this->define('SIKSHYA_STUDENTS_CUSTOM_POST_TYPE', 'sik_students');
		$this->define('SIKSHYA_REST_API_URL', 'https://license.mantrabrain.com/sikshya/');
		$this->define('SIKSHYA_HOOK_PREFIX', 'sikshya');
		$this->define('SIKSHYA_PATH', dirname(SIKSHYA_FILE));
		$this->define('SIKSHYA_BASENAME', plugin_basename(SIKSHYA_FILE));
		$this->define('SIKSHYA_ASSETS_URL', plugins_url('/assets', SIKSHYA_FILE));
		$this->define('SIKSHYA_ADMIN_ASSETS_URL', plugins_url('/assets/admin', SIKSHYA_FILE));
		$this->define('SIKSHYA_TEMPLATES_URL', plugins_url('/templates', SIKSHYA_FILE));
	}


	/**
	 * Define constant if not already set.
	 *
	 * @param string $name Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define($name, $value)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}


	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request($type)
	{
		switch ($type) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined('DOING_AJAX');
			case 'cron':
				return defined('DOING_CRON');
			case 'frontend':
				return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
		}
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes()
	{
		/**
		 * Class autoloader.
		 */
		include_once SIKSHYA_PATH . '/includes/sikshya-functions.php';

		include_once SIKSHYA_PATH . '/includes/class-sikshya-autoloader.php';


		// Abstract
		include_once SIKSHYA_PATH . '/includes/abstracts/abstract-sikshya-payment-gateways.php';

		// LOAD START
		include_once SIKSHYA_PATH . '/includes/payment-gateways/class-sikshya-gateways-core.php';

		// LOAD END


		include_once SIKSHYA_PATH . '/includes/class-sikshya-messages.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-install.php';
		include_once SIKSHYA_PATH . '/includes/helpers/class-sikshya-helper-main.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-assets.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-custom-post-type.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-shortcodes.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-widgets.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-taxonomy.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-metabox.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-hooks.php';
		include_once SIKSHYA_PATH . '/includes/sikshya-template-functions.php';

		include_once SIKSHYA_PATH . '/includes/class-sikshya-ajax.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-permalink-manager.php';
		include_once SIKSHYA_PATH . '/includes/class-sikshya-role-manager.php';


		if ($this->is_request('admin')) {
			include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin.php';
		}

		if ($this->is_request('frontend')) {
			$this->frontend_includes();
		}


	}


	/**
	 * Include required frontend files.
	 */
	public function frontend_includes()
	{
		include_once SIKSHYA_PATH . '/includes/class-sikshya-frontend-form-handler.php';

	}

	/**
	 * Function used to Init Sikshya Template Functions - This makes them pluggable by plugins and themes.
	 */
	public function include_template_functions()
	{
		//include_once WC_ABSPATH . 'includes/wc-template-functions.php';
	}

	/**
	 * Init Sikshya when WordPress Initialises.
	 */
	public function init()
	{
		// Before init action.
		do_action('before_sikshya_init');

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Load class instances.


		$this->helper = new Sikshya_Helper_Main();
		$this->errors = new WP_Error();
		$this->messages = new Sikshya_Messages();
		$this->notice_key = isset($_REQUEST['sikshya_notice']) ? sanitize_text_field($_REQUEST['sikshya_notice']) : '';


		// Init Core Classes

		$this->session = new Sikshya_Core_Session();
		$this->course = new Sikshya_Core_Course();
		$this->lesson = new Sikshya_Core_Lesson();
		$this->section = new Sikshya_Core_Section();
		$this->quiz = new Sikshya_Core_Quiz();
		$this->question = new Sikshya_Core_Question();
		$this->order = new Sikshya_Core_Order();
		$this->role = new Sikshya_Role_Manager();
		$this->student = new Sikshya_Core_Student();
		$this->cart = new Sikshya_Core_Cart();
		$this->checkout = new Sikshya_Core_Checkout();
		// Init action.
		do_action('sikshya_init');
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/sikshya/sikshya-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/sikshya-LOCALE.mo
	 */
	public function load_plugin_textdomain()
	{
		if (function_exists('determine_locale')) {
			$locale = determine_locale();
		} else {
			// @todo Remove when start supporting WP 5.0 or later.
			$locale = is_admin() ? get_user_locale() : get_locale();
		}

		$locale = apply_filters('plugin_locale', $locale, 'sikshya');

		unload_textdomain('sikshya');
		load_textdomain('sikshya', WP_LANG_DIR . '/sikshya/sikshya-' . $locale . '.mo');
		load_plugin_textdomain('sikshya', false, plugin_basename(dirname(SIKSHYA_FILE)) . '/i18n/languages');
	}

	/**
	 * Ensure theme and server variable compatibility and setup image sizes.
	 */
	public function setup_environment()
	{
		/**
		 * SIKSHYA_TEMPLATE_PATH constant.
		 *
		 */
		$this->define('SIKSHYA_TEMPLATE_PATH', $this->template_path());

	}


	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url()
	{
		return untrailingslashit(plugins_url('/', SIKSHYA_FILE));
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path()
	{
		return untrailingslashit(plugin_dir_path(SIKSHYA_FILE));
	}

	/**
	 * Get the template path.
	 *
	 * @return string
	 */
	public function template_path()
	{
		return apply_filters('sikshya_template_path', 'sikshya/');
	}

	/**
	 * Get Ajax URL.
	 *
	 * @return string
	 */
	public function ajax_url()
	{
		return admin_url('admin-ajax.php', 'relative');
	}


}
