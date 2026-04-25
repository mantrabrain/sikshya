<?php

/**
 * File-backed sample packs under `sample-data/`. No WordPress post queries.
 *
 * @package Sikshya\Database\Repositories
 */

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;
use Sikshya\Models\SampleData\SampleDataPack;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SampleDataPackRepository implements RepositoryInterface
{
    public function __construct(
        private ?string $pluginFilePath = null
    ) {
    }

    /**
     * Load a pack by admin key (e.g. "default" → sample-lms.json).
     */
    public function findByPackKey(string $packKey): ?SampleDataPack
    {
        $path = $this->absolutePathForKey($packKey);
        if ($path === null) {
            return null;
        }

        return $this->findByAbsolutePath($path);
    }

    /**
     * Read any readable JSON file that matches the sample pack schema (e.g. tests, migrations).
     */
    public function findByAbsolutePath(string $absolutePath): ?SampleDataPack
    {
        if ($absolutePath === '' || !is_readable($absolutePath)) {
            return null;
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || $data === []) {
            return null;
        }

        return SampleDataPack::tryFromArray($data);
    }

    public function absolutePathForKey(string $packKey): ?string
    {
        $key = sanitize_key($packKey);
        if ($key === '') {
            return null;
        }

        $filename = $key === 'default' ? 'sample-lms.json' : $key . '.json';
        $base = $this->pluginBaseDir();
        if ($base === null) {
            return null;
        }

        $path = $base . '/sample-data/' . $filename;
        if (!is_readable($path)) {
            return null;
        }

        return $path;
    }

    private function pluginBaseDir(): ?string
    {
        if (defined('SIKSHYA_PLUGIN_FILE')) {
            return dirname((string) constant('SIKSHYA_PLUGIN_FILE'));
        }
        if ($this->pluginFilePath !== null) {
            return dirname($this->pluginFilePath);
        }

        return null;
    }
}
