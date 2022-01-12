<?php
/**
 * Sikshsya Autoloader.
 *
 * @package Sikshsya/Classes
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Autoloader class.
 */
class Sikshya_Autoloader
{

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path = '';

	/**
	 * The Constructor.
	 */
	public function __construct()
	{
		if (function_exists('__autoload')) {
			spl_autoload_register('__autoload');
		}

		spl_autoload_register(array($this, 'autoload'));

		$this->include_path = untrailingslashit(plugin_dir_path(SIKSHYA_FILE)) . '/includes/';
	}

	/**
	 * Take a class name and turn it into a file name.
	 *
	 * @param string $class Class name.
	 * @return string
	 */
	private function get_file_name_from_class($class)
	{
		return 'class-' . str_replace('_', '-', $class) . '.php';
	}

	/**
	 * Include a class file.
	 *
	 * @param string $path File path.
	 * @return bool Successful or not.
	 */
	private function load_file($path)
	{
		if ($path && is_readable($path)) {
			include_once $path;
			return true;
		}
		return false;
	}

	/**
	 * Auto-load WC classes on demand to reduce memory consumption.
	 *
	 * @param string $class Class name.
	 */
	public function autoload($class)
	{
		$class = strtolower($class);

		if (0 !== strpos($class, 'sikshya_')) {
			return;
		}

		$file = $this->get_file_name_from_class($class);

		$path = '';

		if (0 === strpos($class, 'sikshya_shortcode_')) {
			$path = $this->include_path . 'shortcodes/';
		} elseif (0 === strpos($class, 'sikshya_meta_box')) {
			$path = $this->include_path . 'admin/meta-boxes/';
		} elseif (0 === strpos($class, 'sikshya_custom_post_type')) {
			$path = $this->include_path . 'custom-post-type/';
		} elseif (0 === strpos($class, 'sikshya_taxonomy')) {
			$path = $this->include_path . 'taxonomy/';
		} elseif (0 === strpos($class, 'sikshya_metabox')) {
			$path = $this->include_path . 'meta-boxes/';
		} elseif (0 === strpos($class, 'sikshya_admin')) {
			$path = $this->include_path . 'admin/';
		} elseif (0 === strpos($class, 'sikshya_log_handler_')) {
			$path = $this->include_path . 'log-handlers/';
		} elseif (0 === strpos($class, 'sikshya_helpers_')) {
			$path = $this->include_path . 'helpers/';
		} elseif (0 === strpos($class, 'sikshya_controller_user_')) {
			$path = $this->include_path . 'controller/user/';
		} elseif (0 === strpos($class, 'sikshya_controller_')) {
			$path = $this->include_path . 'controller/';
		} elseif (0 === strpos($class, 'sikshya_services_')) {
			$path = $this->include_path . 'services/';
		} elseif (0 === strpos($class, 'sikshya_library_')) {
			$path = $this->include_path . 'libraries/';
		} elseif (0 === strpos($class, 'sikshya_core_')) {
			$path = $this->include_path . 'classes/';
		} elseif (0 === strpos($class, 'sikshya_model_')) {
			$path = $this->include_path . 'models/';
		} elseif (0 === strpos($class, 'sikshya_interface')) {
			$path = $this->include_path . 'interfaces/';
		}
		if (empty($path) || !$this->load_file($path . $file)) {
			$this->load_file($this->include_path . $file);
		}
	}
}

new Sikshya_Autoloader();
