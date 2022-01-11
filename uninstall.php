<?php
/**
 * Sikshya Uninstall
 *
 * Uninstalls the plugin and associated data.
 *
 * @version 1.0.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb, $wp_version;

/*
 * Only remove ALL demo importer data if SIKSHYA_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if (defined('SIKSHYA_REMOVE_ALL_DATA') && true === SIKSHYA_REMOVE_ALL_DATA) {

	include_once dirname(__FILE__) . '/includes/class-sikshya-install.php';

	wp_trash_post(get_option('sikshya_account_page'));
	wp_trash_post(get_option('sikshya_registration_page'));
	wp_trash_post(get_option('sikshya_login_page'));
	wp_trash_post(get_option('sikshya_cart_page'));
	wp_trash_post(get_option('sikshya_checkout_page'));
	$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'sikshya\_%';");
	$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'sik_orders', 'sik_courses', 'sik_sections', 'sik_lessons', 'sik_quizzes', 'sik_questions');");
	$wpdb->query("DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.ID IS NULL;");

	Sikshya_Install::drop_tables();

}
