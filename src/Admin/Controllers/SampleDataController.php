<?php

/**
 * Application boundary for sample pack import. Delegates to {@see \Sikshya\Services\SampleDataImportService}.
 * Registered in {@see \Sikshya\Admin\Admin} for in-admin use. REST routes that run outside
 * `is_admin()` (typical for `/wp-json/`) should construct this with {@see \Sikshya\Core\Plugin}
 * rather than resolving the Admin service.
 *
 * @package Sikshya\Admin\Controllers
 */

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Services\SampleDataImportService;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SampleDataController
{
    public function __construct(
        private Plugin $plugin
    ) {
    }

    /**
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importByPackKey(string $packKey): array
    {
        $svc = $this->plugin->getService('sampleDataImport');
        if (!$svc instanceof SampleDataImportService) {
            return [
                'success' => false,
                'message' => __('Service unavailable.', 'sikshya'),
            ];
        }

        return $svc->importByPackKey($packKey);
    }
}
