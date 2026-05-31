<?php
// wp-phpunit configuration for the Sikshya Free integration suite.
//
// Points at a dedicated `sikshya_wp_tests` database on the Sikshya
// Local-by-Flywheel MySQL socket. The DB is truncated/rebuilt by wp-phpunit
// on each test run, so it must NEVER be the production `local` database the
// site itself uses.
//
// If the socket path changes (Local recreates the run/ dir), update DB_HOST
// below. Find the active socket for the Sikshya site by listing
// Local's run/*/mysql/mysqld.sock files and querying each for the WP siteurl.

// Path to a working WordPress install — we reuse the user's Local-by-Flywheel one.
define('ABSPATH', '/Users/umesh/Local Sites/sikshya/app/public/');

define('WP_DEBUG', true);

// Use the local MySQL socket explicitly to avoid TCP/host ambiguity.
define('DB_NAME', 'sikshya_wp_tests');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'localhost:/Users/umesh/Library/Application Support/Local/run/SrffS9sQY/mysql/mysqld.sock');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'sikshya.test');
define('WP_TESTS_EMAIL', 'admin@sikshya.test');
define('WP_TESTS_TITLE', 'Sikshya Test');

define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

// Authentication / nonce salts — generated values, NOT secrets (test-only DB).
define('AUTH_KEY', 'sikshya-tests-AUTH_KEY-fixed-string-do-not-rotate');
define('SECURE_AUTH_KEY', 'sikshya-tests-SECURE_AUTH_KEY-fixed-string');
define('LOGGED_IN_KEY', 'sikshya-tests-LOGGED_IN_KEY-fixed-string');
define('NONCE_KEY', 'sikshya-tests-NONCE_KEY-fixed-string');
define('AUTH_SALT', 'sikshya-tests-AUTH_SALT-fixed-string');
define('SECURE_AUTH_SALT', 'sikshya-tests-SECURE_AUTH_SALT-fixed-string');
define('LOGGED_IN_SALT', 'sikshya-tests-LOGGED_IN_SALT-fixed-string');
define('NONCE_SALT', 'sikshya-tests-NONCE_SALT-fixed-string');
