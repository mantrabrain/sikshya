<?php

/**
 * Legacy admin-ajax / wp_ajax registration gate (REST-only when disabled).
 *
 * @package Sikshya\Core
 */

namespace Sikshya\Core;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class LegacyAjax
{
    /**
     * When false (default), do not register wp_ajax_* handlers — use REST API only.
     */
    public static function hooksEnabled(): bool
    {
        return defined('SIKSHYA_LEGACY_AJAX') && SIKSHYA_LEGACY_AJAX;
    }
}
