<?php

namespace Sikshya\Core;

/**
 * PSR-4 Autoloader for Sikshya LMS
 *
 * @package Sikshya\Core
 */
class Autoloader
{
    /**
     * Register the autoloader
     */
    public static function register(): void
    {
        spl_autoload_register([new self(), 'autoload']);
    }

    /**
     * Autoload classes
     */
    public function autoload(string $class): void
    {
        // Only handle Sikshya classes
        if (strpos($class, 'Sikshya\\') !== 0) {
            return;
        }

        // Remove the namespace prefix
        $relative_class = substr($class, 8); // Remove 'Sikshya\'

        // Convert namespace separators to directory separators
        $file = SIKSHYA_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
