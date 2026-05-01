<?php

namespace Sikshya\Services;

/**
 * Reads course catalog / archive settings stored via {@see Settings}.
 *
 * @package Sikshya\Services
 */
final class CourseFrontendSettings
{
    public static function coursesPerPageDefault(): int
    {
        // Catalog display controls are no longer exposed in Global Settings; keep a stable default.
        return 12;
    }

    public static function isCourseSearchEnabled(): bool
    {
        return Settings::isTruthy(Settings::get('enable_course_search', true));
    }

    public static function areCourseFiltersEnabled(): bool
    {
        return Settings::isTruthy(Settings::get('enable_course_filters', true));
    }

    public static function archiveLayout(): string
    {
        // Layout controls are no longer exposed in Global Settings; keep a stable default.
        return 'grid';
    }

    public static function singleLayout(): string
    {
        // Layout controls are no longer exposed in Global Settings; keep a stable default.
        return 'default';
    }

    /**
     * @return array{enrollment: string, free: string}
     */
    public static function enrollmentButtonLabels(): array
    {
        $enrollment = (string) Settings::get('enrollment_button_text', '');
        $free = (string) Settings::get('free_course_text', '');
        if (trim($enrollment) === '') {
            $enrollment = __('Enroll Now', 'sikshya');
        }
        if (trim($free) === '') {
            $free = __('Start Learning', 'sikshya');
        }

        return [
            'enrollment' => $enrollment,
            'free' => $free,
        ];
    }

    public static function areCategoriesEnabled(): bool
    {
        return Settings::isTruthy(Settings::get('enable_course_categories', true));
    }

    /**
     * How category links are rendered in the course archive filter sidebar.
     *
     * @return string list|grid|dropdown
     */
    public static function categoryDisplay(): string
    {
        $v = sanitize_key((string) Settings::get('category_display', 'dropdown'));
        $allowed = ['list', 'grid', 'dropdown'];

        return in_array($v, $allowed, true) ? $v : 'dropdown';
    }
}
