<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests under tests/Unit must avoid WordPress dependencies — test pure logic only and
 * mock WP functions where needed (Brain Monkey or hand-rolled stubs). Integration tests
 * under tests/Integration require a WP test installation; see tests/Integration/README.md
 * (to be added) for setup.
 *
 * Run: `composer test` (or `vendor/bin/phpunit`).
 *
 * @package Sikshya\Tests
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    // Guard for files that bail early when ABSPATH isn't set.
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('__')) {
    /**
     * @param string $text
     * @param string $domain
     * @return string
     */
    function __($text, $domain = 'default')
    {
        unset($domain);
        return $text;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
