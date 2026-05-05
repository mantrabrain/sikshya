<?php
/**
 * Plugin Name: Sikshya LMS
 * Plugin URI: https://mantrabrain.com/plugins/sikshya/
 * Description: A comprehensive WordPress Learning Management System plugin with modern SaaS design and enterprise-level architecture.
 * Version: 1.0.1
 * Author: MantraBrain
 * Author URI: https://mantrabrain.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sikshya
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Network: true
 *
 * @package Sikshya
 * @version 1.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIKSHYA_VERSION', '1.0.1');
define('SIKSHYA_PLUGIN_FILE', __FILE__);
define('SIKSHYA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIKSHYA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIKSHYA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SIKSHYA_MINIMUM_WP_VERSION', '6.0');
define('SIKSHYA_MINIMUM_PHP_VERSION', '7.4');

/**
 * When false, legacy `admin-ajax.php` handlers are not registered (REST-only mode).
 * Set to true temporarily if a screen still depends on unmigrated AJAX.
 */
if (!defined('SIKSHYA_LEGACY_AJAX')) {
    define('SIKSHYA_LEGACY_AJAX', false);
}

// Load Composer autoloader
if (file_exists(SIKSHYA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SIKSHYA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback for development without Composer
    require_once SIKSHYA_PLUGIN_DIR . 'src/Core/Autoloader.php';
    \Sikshya\Core\Autoloader::register();
}

require_once SIKSHYA_PLUGIN_DIR . 'includes/template-functions.php';
require_once SIKSHYA_PLUGIN_DIR . 'includes/course-archive-filters.php';

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

    // Schedule the legacy `sikshya-old` -> rewrite migration if any legacy
    // data is detected on activation. The migrator class is gated on
    // `class_exists` so deleting `src/Migration/` after the installed base
    // has migrated turns this into a no-op (see src/Migration/README.md).
    if (class_exists('\\Sikshya\\Migration\\LegacyMigrator')) {
        \Sikshya\Migration\LegacyMigrator::scheduleIfPending();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    \Sikshya\Core\Deactivator::deactivate();
});

// Uninstall hook is handled by uninstall.php file

// Initialize the plugin
sikshya(); 