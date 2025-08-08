<?php
/**
 * Uninstall Sikshya LMS Plugin
 *
 * @package Sikshya
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load the uninstaller class
require_once plugin_dir_path(__FILE__) . 'src/Core/Uninstaller.php';

// Run the uninstall process
\Sikshya\Core\Uninstaller::uninstall(); 