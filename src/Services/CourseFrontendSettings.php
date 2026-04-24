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
        $n = (int) Settings::get('courses_per_page', 12);

        return max(1, min(50, $n));
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
        $layout = sanitize_key((string) Settings::get('course_archive_layout', 'grid'));
        $allowed = ['grid', 'list', 'masonry'];

        return in_array($layout, $allowed, true) ? $layout : 'grid';
    }

    public static function singleLayout(): string
    {
        $layout = sanitize_key((string) Settings::get('course_single_layout', 'default'));
        $allowed = ['default', 'sidebar', 'fullwidth'];

        return in_array($layout, $allowed, true) ? $layout : 'default';
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

    public static function areTagsEnabled(): bool
    {
        return Settings::isTruthy(Settings::get('enable_course_tags', true));
    }
}
