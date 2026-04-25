<?php

/**
 * Plugin activation entry point.
 *
 * This class only exists to remain the address that
 * `register_activation_hook(__FILE__, [Activator::class, 'activate'])` points
 * to in `sikshya.php`. ALL installation work — database, roles, default
 * options, certificate templates, etc. — lives in {@see Installer} so there
 * is exactly one source of truth.
 *
 * @package Sikshya\Core
 */

namespace Sikshya\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    /**
     * Run the full install sequence (idempotent).
     */
    public static function activate(): void
    {
        Installer::install();
    }
}
