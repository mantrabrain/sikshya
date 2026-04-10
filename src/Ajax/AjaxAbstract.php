<?php

/**
 * Abstract AJAX Class
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class AjaxAbstract
{
    /**
     * Plugin instance
     *
     * @var \Sikshya\Core\Plugin
     */
    protected $plugin;

    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     * @param bool                 $register_hooks When false, hooks are not registered (REST-only mode; renderer still usable).
     */
    public function __construct($plugin, bool $register_hooks = true)
    {
        $this->plugin = $plugin;
        if ($register_hooks && $this->legacyAjaxEnabled()) {
            $this->initHooks();
        }
    }

    /**
     * Whether legacy admin-ajax handlers should register.
     */
    protected function legacyAjaxEnabled(): bool
    {
        return !defined('SIKSHYA_LEGACY_AJAX') || SIKSHYA_LEGACY_AJAX;
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    abstract protected function initHooks(): void;

    /**
     * Verify nonce
     *
     * @param string $nonce_name
     * @param string $nonce_field
     * @return bool
     */
    protected function verifyNonce(string $nonce_name, string $nonce_field = 'nonce'): bool
    {
        if (!isset($_POST[$nonce_field])) {
            return false;
        }

        return wp_verify_nonce($_POST[$nonce_field], $nonce_name);
    }

    /**
     * Check user capabilities
     *
     * @param string $capability
     * @return bool
     */
    protected function checkCapability(string $capability = 'edit_posts'): bool
    {
        return current_user_can($capability);
    }

    /**
     * Send success response
     *
     * @param mixed $data
     * @param string $message
     * @return void
     */
    protected function sendSuccess($data = null, string $message = ''): void
    {
        $response = [
            'success' => true,
            'data' => $data ?? []
        ];

        if (!empty($message)) {
            $response['data']['message'] = $message;
        }

        wp_send_json($response);
    }

    /**
     * Send error response
     *
     * @param string $message
     * @param mixed $data
     * @return void
     */
    protected function sendError(string $message, $data = null): void
    {
        $response = [
            'success' => false,
            'data' => ['message' => $message]
        ];

        if ($data !== null) {
            $response['data'] = array_merge($response['data'], is_array($data) ? $data : ['data' => $data]);
        }

        wp_send_json($response);
    }

    /**
     * Get POST data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getPostData(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getGetData(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Log error
     *
     * @param string $message
     * @param \Exception $exception
     * @return void
     */
    protected function logError(string $message, ?\Exception $exception = null): void
    {
        $log_message = 'Sikshya AJAX Error: ' . $message;

        if ($exception) {
            $log_message .= ' - Exception: ' . $exception->getMessage();
            $log_message .= ' - Stack: ' . $exception->getTraceAsString();
        }

        error_log($log_message);
    }
}
