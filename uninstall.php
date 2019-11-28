<?php
/**
 * Sikshya Uninstall
 *
 * Uninstalls the plugin and associated data.
 *
 * @version 1.0.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;


/*
 * Only remove ALL demo importer data if SIKSHYA_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if (defined('SIKSHYA_REMOVE_ALL_DATA') && true === SIKSHYA_REMOVE_ALL_DATA) {

    // Delete options.
    // Write any query here to delete all data associated with Sikshya WordPress LMS plugin

}
