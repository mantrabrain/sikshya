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

// Wipe data only when explicitly enabled in Sikshya settings.
// Stored as a normal WP option (`_sikshya_erase_data_on_uninstall`) so it can be read without bootstrapping the plugin.
$erase = get_option('_sikshya_erase_data_on_uninstall', '0');
if ($erase === true || $erase === 1 || $erase === '1' || $erase === 'true' || $erase === 'yes' || $erase === 'on') {
    \Sikshya\Core\Uninstaller::uninstall();
}