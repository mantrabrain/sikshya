<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Security Service
 *
 * @package Sikshya\Services
 */
class SecurityService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Verify nonce
     *
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Sanitize input
     *
     * @param mixed $input
     * @param string $type
     * @return mixed
     */
    public function sanitize($input, string $type = 'text')
    {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'url':
                return esc_url_raw($input);
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'html':
                return wp_kses_post($input);
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Escape output
     *
     * @param string $output
     * @param string $type
     * @return string
     */
    public function escape(string $output, string $type = 'text'): string
    {
        switch ($type) {
            case 'html':
                return esc_html($output);
            case 'attr':
                return esc_attr($output);
            case 'url':
                return esc_url($output);
            case 'js':
                return esc_js($output);
            default:
                return esc_html($output);
        }
    }
} 