<?php

/**
 * Tab Interface for Course Builder
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

interface TabInterface
{
    /**
     * Get the unique identifier for this tab
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the display title for this tab
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get the description for this tab
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the SVG icon for this tab
     *
     * @return string
     */
    public function getIcon(): string;

    /**
     * Get the tab order (lower numbers appear first)
     *
     * @return int
     */
    public function getOrder(): int;

    /**
     * Get the fields configuration for this tab
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * Validate the form data for this tab
     *
     * @param array $data
     * @return array Array of errors, empty if valid
     */
    public function validate(array $data): array;

    /**
     * Save the form data for this tab
     *
     * @param array $data
     * @param int $course_id
     * @return bool
     */
    public function save(array $data, int $course_id): bool;

    /**
     * Load existing data for this tab
     *
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array;

    /**
     * Render the HTML for this tab
     *
     * @param array $data
     * @param string $active_tab
     * @return string
     */
    public function render(array $data = [], string $active_tab = ''): string;
}
