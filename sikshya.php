<?php
/**
 *  Plugin Name:     Sikshya
 *  Version:         0.0.8
 *  Plugin URI:      https://wordpress.org/plugins/sikshya
 *  Description:     Sikshya is free Learning management system (LMS) for WordPress. It helps to create course, lessons, quizzes, questions and answers for your online course system.
 *  Author:          mantrabrain
 *  Author URI:      https://mantrabrain.com
 *  Text Domain:     sikshya
 *  Domain Path:     /languages/
 **/


define('SIKSHYA_FILE', __FILE__);

// Define SIKSHYA_VERSION.
if (!defined('SIKSHYA_VERSION')) {
	define('SIKSHYA_VERSION', '0.0.8');
}

// Include the main Mantrabrain Starter Sites class.
if (!class_exists('Sikshya')) {
	include_once dirname(__FILE__) . '/includes/class-sikshya.php';
}

/**
 * Main instance of Mantrabrain Sikshya
 *
 * Returns the main instance to prevent the need to use globals.
 *
 * @return Sikshya
 * @since 1.0.0
 */
function sikshya()
{
	return Sikshya::instance();
}


$GLOBALS['sikshya'] = sikshya();
