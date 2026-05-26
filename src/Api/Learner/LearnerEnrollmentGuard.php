<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use Sikshya\Services\CourseService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared enrollment checks for learner REST routes (unit-testable without WordPress bootstrap).
 */
final class LearnerEnrollmentGuard
{
    /**
     * @return array{code: string, message: string, status: int}|null Null when the user may proceed.
     */
    public static function denyUnlessEnrolled(
        int $userId,
        int $courseId,
        CourseService $courseService,
        string $noCourseCode,
        string $noCourseMessage
    ): ?array {
        if ($courseId <= 0) {
            return [
                'code' => $noCourseCode,
                'message' => $noCourseMessage,
                'status' => 400,
            ];
        }

        if (!$courseService->isUserEnrolled($userId, $courseId)) {
            return [
                'code' => 'not_enrolled',
                'message' => __('You are not enrolled in this course.', 'sikshya'),
                'status' => 403,
            ];
        }

        return null;
    }
}
