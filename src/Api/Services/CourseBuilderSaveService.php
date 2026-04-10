<?php

/**
 * Course builder save logic shared by admin-ajax and REST API.
 *
 * @package Sikshya\Api\Services
 */

namespace Sikshya\Api\Services;

use Sikshya\Core\Plugin;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Back-compat facade: delegates to {@see \Sikshya\Services\CourseBuilderService} (domain layer).
 */
final class CourseBuilderSaveService
{
    /**
     * @param Plugin $plugin        Plugin instance.
     * @param array  $data          Field key => value (includes course_id, title, etc.).
     * @param string $course_status draft|publish|published (published normalized to publish).
     * @return array{success:bool,message?:string,data?:array,errors?:array,code?:string}
     */
    public static function save(Plugin $plugin, array $data, string $course_status): array
    {
        $service = $plugin->getService('courseBuilder');
        if (!$service instanceof \Sikshya\Services\CourseBuilderService) {
            return [
                'success' => false,
                'message' => __('Course builder service is not available.', 'sikshya'),
                'code' => 'service_missing',
            ];
        }

        return $service->save($data, $course_status);
    }
}
