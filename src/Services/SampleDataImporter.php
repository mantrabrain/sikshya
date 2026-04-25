<?php

/**
 * Backward-compatible facade: wires {@see SampleDataPackRepository} and {@see SampleDataImportService}.
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Database\Repositories\SampleDataPackRepository;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

class SampleDataImporter
{
    private SampleDataImportService $import;

    public function __construct(
        CurriculumService $curriculum,
        CourseCurriculumActions $actions,
        ?SampleDataPackRepository $packRepository = null
    ) {
        $this->import = new SampleDataImportService(
            $packRepository ?? new SampleDataPackRepository(),
            $curriculum,
            $actions
        );
    }

    /**
     * @param array<string, mixed> $pack
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importPack(array $pack): array
    {
        return $this->import->importPack($pack);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null Normalized service payload (version, categories, courses, label, description).
     */
    public static function loadJsonFile(string $absolutePath): ?array
    {
        $repo = new SampleDataPackRepository();
        $pack = $repo->findByAbsolutePath($absolutePath);

        return $pack?->toServiceArray();
    }
}
