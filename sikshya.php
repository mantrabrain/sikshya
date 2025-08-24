<?php
/**
 * Plugin Name: Sikshya LMS
 * Plugin URI: https://sikshya.com
 * Description: A comprehensive WordPress Learning Management System plugin with modern SaaS design and enterprise-level architecture.
 * Version: 1.0.0
 * Author: Sikshya Team
 * Author URI: https://sikshya.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sikshya
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: true
 *
 * @package Sikshya
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIKSHYA_VERSION', '1.0.0');
define('SIKSHYA_PLUGIN_FILE', __FILE__);
define('SIKSHYA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIKSHYA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIKSHYA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SIKSHYA_MINIMUM_WP_VERSION', '6.0');
define('SIKSHYA_MINIMUM_PHP_VERSION', '8.1');

// Load Composer autoloader
if (file_exists(SIKSHYA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SIKSHYA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback for development without Composer
    require_once SIKSHYA_PLUGIN_DIR . 'src/Core/Autoloader.php';
    \Sikshya\Core\Autoloader::register();
}

// Initialize the plugin
function sikshya() {
    try {
        // Check requirements
        if (!\Sikshya\Core\Requirements::check()) {
            return;
        }
        
        // Initialize the main plugin
        \Sikshya\Core\Plugin::getInstance();
    } catch (\Exception $e) {
        error_log('Sikshya Plugin Initialization Error: ' . $e->getMessage());
        error_log('Sikshya Plugin Initialization Stack: ' . $e->getTraceAsString());
        
        // Add admin notice
        add_action('admin_notices', function() use ($e) {
            // Error logged instead of displayed
            error_log('Sikshya LMS Initialization Error: ' . $e->getMessage());
        });
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    \Sikshya\Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    \Sikshya\Core\Deactivator::deactivate();
});

// Uninstall hook is handled by uninstall.php file

// Initialize the plugin
sikshya(); 