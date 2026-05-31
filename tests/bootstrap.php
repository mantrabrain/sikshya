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

/**
 * Test scaffolding: tests can prime these globals to simulate WP state without
 * loading a real WordPress install. Reset in each test's setUp() / tearDown().
 */
global $sik_test_post_statuses, $sik_test_post_types, $sik_test_user_caps, $sik_test_current_user_id;
$sik_test_post_statuses = [];
$sik_test_post_types = [];
$sik_test_user_caps = [];
$sik_test_current_user_id = 0;

if (!function_exists('get_post_status')) {
    function get_post_status($post_id = null)
    {
        global $sik_test_post_statuses;
        return $sik_test_post_statuses[(int) $post_id] ?? false;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id = null)
    {
        global $sik_test_post_types;
        return $sik_test_post_types[(int) $post_id] ?? false;
    }
}

/**
 * Minimal post-object stub for tests that prime $sik_test_posts[id] = ['post_type' => ..., 'post_status' => ...].
 * Returns null when no entry is set.
 */
if (!function_exists('get_post')) {
    function get_post($post_id = null)
    {
        global $sik_test_posts;
        $id = (int) $post_id;
        if (!isset($sik_test_posts[$id])) {
            return null;
        }
        $data = $sik_test_posts[$id];
        $obj = new \stdClass();
        $obj->ID = $id;
        $obj->post_type = (string) ($data['post_type'] ?? '');
        $obj->post_status = (string) ($data['post_status'] ?? '');
        foreach ($data as $k => $v) {
            $obj->{$k} = $v;
        }
        return $obj;
    }
}

global $sik_test_posts;
$sik_test_posts = [];

if (!function_exists('current_user_can')) {
    function current_user_can($cap, ...$args)
    {
        global $sik_test_user_caps;
        $key = $cap . ':' . implode(':', array_map('strval', $args));
        // Match exact (cap+args) first, then bare cap.
        if (array_key_exists($key, $sik_test_user_caps)) {
            return (bool) $sik_test_user_caps[$key];
        }
        return (bool) ($sik_test_user_caps[$cap] ?? false);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        global $sik_test_current_user_id;
        return (int) $sik_test_current_user_id;
    }
}

if (!function_exists('absint')) {
    function absint($n)
    {
        return abs((int) $n);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        $email = trim((string) $email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s)
    {
        return trim((string) $s);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($v)
    {
        return json_encode($v);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        public $code;
        /** @var string */
        public $message;
        /** @var array<string, mixed> */
        public $data;

        public function __construct($code = '', $message = '', $data = [])
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = is_array($data) ? $data : [];
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
