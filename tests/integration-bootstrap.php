<?php
/**
 * Integration-suite bootstrap. Loads wp-phpunit, which spins up a real
 * WordPress kernel against the dedicated `sikshya_wp_tests` database and
 * makes WP_UnitTestCase (with its factories for posts, users, terms,
 * comments, etc.) available to tests.
 *
 * The Sikshya plugin is loaded via the `muplugins_loaded` action so it
 * registers its REST routes / post types in the test WP boot. See the
 * companion unit bootstrap at tests/bootstrap.php for the lightweight
 * mocked variant used by tests/Unit/.
 *
 * Run via: vendor/bin/phpunit --testsuite integration
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = __DIR__ . '/../vendor/wp-phpunit/wp-phpunit';

// Tell wp-phpunit where our config lives — it checks `defined('WP_TESTS_CONFIG_FILE_PATH')`
// before searching the default location.
define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');

// Load wp-phpunit's helper functions (including tests_add_filter) FIRST so the
// plugin can be queued for load via the muplugins_loaded filter.
require $_tests_dir . '/includes/functions.php';

// Queue Sikshya to load during WP's muplugins_loaded pass so its activation
// hooks (post type registration, capability install, REST init) run inside the
// test boot, not lazily during a test.
tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__) . '/sikshya.php';
});

// Boot WP + WP_UnitTestCase.
require $_tests_dir . '/includes/bootstrap.php';

// Run the activator once WP is fully booted. `wp_install` only fires on the
// first ever install — wp-phpunit reuses the schema across runs, so that
// hook is unreliable. Calling the Installer directly is idempotent (it uses
// dbDelta) and guarantees Sikshya's custom tables exist for the integration
// suite without polluting the production-site DB (we point at sikshya_wp_tests).
if (class_exists('\\Sikshya\\Core\\Activator')) {
    \Sikshya\Core\Activator::activate();
}
