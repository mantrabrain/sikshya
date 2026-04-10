<?php

/**
 * Course builder domain service: validation + orchestration. Persistence uses repositories.
 *
 * Layering:
 * - Repositories: CourseRepository, PostMetaRepository (meta via tabs).
 * - This service: validateAllTabs + create/update course post + saveAllTabs.
 * - Controllers: CourseAjax, REST AdminRestRoutes (HTTP only).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Admin\CourseBuilder\CourseBuilderManager;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\CourseRepository;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class CourseBuilderService
{
    public function __construct(
        private Plugin $plugin,
        private CourseRepository $courses
    ) {
    }

    /**
     * Save course from flat builder field array.
     *
     * @param array  $data          Form fields including course_id, title, description, etc.
     * @param string $course_status draft|publish|published (published normalized).
     * @return array{success:bool,message?:string,data?:array,errors?:array,code?:string}
     */
    public function save(array $data, string $course_status): array
    {
        $course_status = sanitize_text_field($course_status);
        if ($course_status === 'published') {
            $course_status = 'publish';
        }
        if (!in_array($course_status, ['draft', 'publish', 'pending', 'private'], true)) {
            $course_status = 'draft';
        }

        try {
            $manager = new CourseBuilderManager($this->plugin);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Failed to initialize course builder.', 'sikshya'),
                'code' => 'init_failed',
            ];
        }

        $errors = $manager->validateAllTabs($data);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => __('Validation failed', 'sikshya'),
                'errors' => $errors,
                'field_errors' => self::flattenFieldErrors($errors),
                'code' => 'validation_failed',
            ];
        }

        $course_id = isset($data['course_id']) ? (int) $data['course_id'] : 0;

        if ($course_id === 0) {
            $title = $data['title'] ?? __('New Course', 'sikshya');
            $description = $data['description'] ?? '';
            $inserted = $this->courses->insertFromBuilder((string) $title, (string) $description, $course_status);

            if (is_wp_error($inserted)) {
                return [
                    'success' => false,
                    'message' => __('Failed to create course', 'sikshya'),
                    'code' => 'create_failed',
                ];
            }

            $course_id = (int) $inserted;
        } else {
            if (!$this->courses->isCourse($course_id)) {
                return [
                    'success' => false,
                    'message' => __('Invalid course.', 'sikshya'),
                    'code' => 'invalid_course',
                ];
            }

            if (!$this->courses->updatePostStatus($course_id, $course_status)) {
                return [
                    'success' => false,
                    'message' => __('Failed to update course status.', 'sikshya'),
                    'code' => 'status_update_failed',
                ];
            }
        }

        $save_errors = $manager->saveAllTabs($data, $course_id);
        if (!empty($save_errors)) {
            return [
                'success' => false,
                'message' => __('Failed to save some data', 'sikshya'),
                'errors' => $save_errors,
                'field_errors' => self::flattenFieldErrors($save_errors),
                'code' => 'save_partial_failed',
            ];
        }

        $message = ($course_status === 'publish')
            ? __('Course published successfully!', 'sikshya')
            : __('Course draft saved successfully!', 'sikshya');

        $js_status = ($course_status === 'publish') ? 'published' : $course_status;

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'course_id' => $course_id,
                'status' => $js_status,
            ],
        ];
    }

    /**
     * Flatten tab-keyed field errors for clients that expect a single map (field_id => message).
     *
     * @param array<string, mixed> $tabErrors
     * @return array<string, string>
     */
    private static function flattenFieldErrors(array $tabErrors): array
    {
        $flat = [];
        foreach ($tabErrors as $errs) {
            if (!is_array($errs)) {
                continue;
            }
            foreach ($errs as $fieldId => $message) {
                if (is_string($fieldId) && is_string($message)) {
                    $flat[$fieldId] = $message;
                }
            }
        }

        return $flat;
    }
}
