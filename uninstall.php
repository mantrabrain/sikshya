<?php
/**
 * Uninstall Sikshya LMS Plugin
 *
 * WordPress runs this file on its own. {@see uninstall_plugin()} includes
 * *only* `uninstall.php` — the plugin's main file is never loaded, so nothing
 * has registered Composer's autoloader at this point. Previously this file
 * required just `src/Core/Uninstaller.php`, but that class references
 * `Settings`, `PostTypes`, `Taxonomies`, `Tables` and a repository, none of
 * which were loadable: `Uninstaller::uninstall()` raised a fatal on its very
 * first statement and no cleanup ever happened, whatever the user had opted
 * into. Registering the autoloader here is what makes the routine reachable.
 *
 * {@see \Sikshya\Core\Uninstaller::uninstall()} decides what to do:
 * infrastructure left behind by the plugin itself (roles, capabilities,
 * scheduled events, caches) is always cleaned up, while deleting the user's
 * course content, tables and settings happens only when they have explicitly
 * opted in via Sikshya's "erase data on uninstall" setting. That gate lives
 * inside the class, so this file no longer duplicates it.
 *
 * @package Sikshya
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$sikshya_dir = plugin_dir_path(__FILE__);

$sikshya_autoload = $sikshya_dir . 'vendor/autoload.php';
if (is_readable($sikshya_autoload)) {
    require_once $sikshya_autoload;
}

require_once $sikshya_dir . 'src/Core/Uninstaller.php';

/*
 * Verify the dependency graph actually resolved before starting. A partial
 * run — posts deleted, tables and options left behind — is worse than none at
 * all, so bail silently rather than fatal halfway through.
 */
if (
    !class_exists('\\Sikshya\\Core\\Uninstaller')
    || !class_exists('\\Sikshya\\Services\\Settings')
    || !class_exists('\\Sikshya\\Constants\\PostTypes')
    || !class_exists('\\Sikshya\\Constants\\Taxonomies')
    || !class_exists('\\Sikshya\\Database\\Repositories\\PluginLifecycleRepository')
) {
    return;
}

\Sikshya\Core\Uninstaller::uninstall();
