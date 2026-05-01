<?php

/**
 * DTO for a decoded sample-data JSON pack (Tools → import). No I/O.
 *
 * @package Sikshya\Models\SampleData
 */

namespace Sikshya\Models\SampleData;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SampleDataPack
{
    public const SUPPORTED_VERSIONS = [1, 2];

    private int $version;

    private string $label;

    private string $description;

    /** @var list<array<string, mixed>> */
    private array $courseCategories;

    /** @var list<array<string, mixed>> */
    private array $courses;

    /**
     * @param list<array<string, mixed>> $courseCategories
     * @param list<array<string, mixed>>  $courses
     */
    private function __construct(
        int $version,
        string $label,
        string $description,
        array $courseCategories,
        array $courses
    ) {
        $this->version = $version;
        $this->label = $label;
        $this->description = $description;
        $this->courseCategories = $courseCategories;
        $this->courses = $courses;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCourseCategories(): array
    {
        return $this->courseCategories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCourses(): array
    {
        return $this->courses;
    }

    /**
     * Shape expected by {@see \Sikshya\Services\SampleDataImportService}.
     *
     * @return array<string, mixed>
     */
    public function toServiceArray(): array
    {
        return [
            'version' => $this->version,
            'label' => $this->label,
            'description' => $this->description,
            'course_categories' => $this->courseCategories,
            'courses' => $this->courses,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function tryFromArray(array $data): ?self
    {
        if (!in_array((int) ($data['version'] ?? 0), self::SUPPORTED_VERSIONS, true)) {
            return null;
        }

        $courses = $data['courses'] ?? null;
        if (!is_array($courses) || $courses === []) {
            return null;
        }

        $cats = $data['course_categories'] ?? [];
        if (!is_array($cats)) {
            $cats = [];
        }

        return new self(
            (int) $data['version'],
            (string) ($data['label'] ?? ''),
            (string) ($data['description'] ?? ''),
            $cats,
            $courses
        );
    }
}
