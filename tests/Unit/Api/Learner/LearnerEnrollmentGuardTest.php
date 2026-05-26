<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Api\Learner;

use PHPUnit\Framework\TestCase;
use Sikshya\Api\Learner\LearnerEnrollmentGuard;
use Sikshya\Services\CourseService;

/**
 * @covers \Sikshya\Api\Learner\LearnerEnrollmentGuard::denyUnlessEnrolled
 */
final class LearnerEnrollmentGuardTest extends TestCase
{
    public function testDeniesWhenCourseIdMissing(): void
    {
        $courses = $this->createMock(CourseService::class);
        $courses->expects(self::never())->method('isUserEnrolled');

        $denied = LearnerEnrollmentGuard::denyUnlessEnrolled(
            7,
            0,
            $courses,
            'assignment_no_course',
            'Assignment is not linked to a course.'
        );

        self::assertNotNull($denied);
        self::assertSame('assignment_no_course', $denied['code']);
        self::assertSame(400, $denied['status']);
        self::assertSame('Assignment is not linked to a course.', $denied['message']);
    }

    public function testDeniesWhenUserNotEnrolled(): void
    {
        $courses = $this->createMock(CourseService::class);
        $courses->expects(self::once())
            ->method('isUserEnrolled')
            ->with(12, 99)
            ->willReturn(false);

        $denied = LearnerEnrollmentGuard::denyUnlessEnrolled(
            12,
            99,
            $courses,
            'assignment_no_course',
            'Assignment is not linked to a course.'
        );

        self::assertNotNull($denied);
        self::assertSame('not_enrolled', $denied['code']);
        self::assertSame(403, $denied['status']);
    }

    public function testAllowsEnrolledUser(): void
    {
        $courses = $this->createMock(CourseService::class);
        $courses->expects(self::once())
            ->method('isUserEnrolled')
            ->with(3, 42)
            ->willReturn(true);

        self::assertNull(
            LearnerEnrollmentGuard::denyUnlessEnrolled(
                3,
                42,
                $courses,
                'assignment_no_course',
                'Assignment is not linked to a course.'
            )
        );
    }
}
