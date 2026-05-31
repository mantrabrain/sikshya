<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Database\Repositories\AchievementsRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * Learner achievement / badge service.
 *
 * Backs the `achievement` DI key registered in {@see \Sikshya\Core\Plugin}. The
 * class name is preserved for backward compatibility with callers — historically
 * it was a stub returning empty results, now it persists earned achievements in
 * `sikshya_achievements` and listens to lifecycle events to award 4 starter
 * badges:
 *
 *   - `first_enrollment` — first time the learner enrolls in any course.
 *   - `first_lesson`     — first lesson the learner marks complete.
 *   - `first_course`     — first time a course is fully completed.
 *   - `five_courses`     — five completed courses.
 *
 * @package Sikshya\Services
 */
final class LearnerAchievementStub
{
    private AchievementsRepository $repo;
    private static bool $hooksRegistered = false;

    public function __construct(?AchievementsRepository $repo = null)
    {
        $this->repo = $repo ?? new AchievementsRepository();
        self::registerHooks($this->repo);
    }

    /**
     * @return array<int, mixed>
     */
    public function getUserAchievements(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }
        $rows = $this->repo->findByUser($user_id, 50);
        return array_map(
            static function ($row): array {
                return [
                    'id' => (int) ($row->id ?? 0),
                    'type' => (string) ($row->achievement_type ?? ''),
                    'name' => (string) ($row->achievement_name ?? ''),
                    'description' => isset($row->description) ? (string) $row->description : '',
                    'badge_url' => isset($row->badge_url) ? (string) $row->badge_url : '',
                    'earned_date' => isset($row->earned_date) ? (string) $row->earned_date : '',
                ];
            },
            $rows
        );
    }

    public function getUserAchievementsCount(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }
        return $this->repo->countByUser($user_id);
    }

    /**
     * Register the lifecycle hooks that award starter achievements. Idempotent.
     */
    public static function registerHooks(?AchievementsRepository $repo = null): void
    {
        if (self::$hooksRegistered) {
            return;
        }
        self::$hooksRegistered = true;
        $shared = $repo ?? new AchievementsRepository();

        // First enrollment — fires on both classic enrollments
        // (`sikshya_user_enrolled`) and on Pro subscription fulfillment, so
        // subscription customers also earn the badge.
        $awardFirstEnrollment = static function ($user_id) use ($shared) {
            $uid = (int) $user_id;
            if ($uid <= 0) {
                return;
            }
            $shared->awardOnce(
                $uid,
                'first_enrollment',
                __('First enrollment', 'sikshya'),
                __('Enrolled in your first course on this site.', 'sikshya')
            );
        };
        add_action('sikshya_user_enrolled', $awardFirstEnrollment, 20, 1);
        // Pro: Subscriptions addon fulfillment — receives ($sub_id, $user_id, $plan_id, $course_id, $order_id).
        add_action(
            'sikshya_subscriptions_after_checkout_fulfillment',
            static function ($sub_id, $user_id) use ($awardFirstEnrollment) {
                unset($sub_id);
                $awardFirstEnrollment($user_id);
            },
            20,
            2
        );

        // First lesson completed.
        add_action(
            'sikshya_lesson_completed',
            static function ($user_id, $course_id, $lesson_id) use ($shared) {
                $uid = (int) $user_id;
                if ($uid <= 0) {
                    return;
                }
                unset($course_id, $lesson_id);
                $shared->awardOnce(
                    $uid,
                    'first_lesson',
                    __('First lesson complete', 'sikshya'),
                    __('Completed your first lesson — keep going!', 'sikshya')
                );
            },
            20,
            3
        );

        // First completed course + five-course milestone.
        add_action(
            'sikshya_course_completed',
            static function ($user_id, $course_id) use ($shared) {
                $uid = (int) $user_id;
                if ($uid <= 0) {
                    return;
                }
                unset($course_id);
                $shared->awardOnce(
                    $uid,
                    'first_course',
                    __('First course complete', 'sikshya'),
                    __('You finished your first full course.', 'sikshya')
                );

                $completed = self::completedCourseCount($uid);
                if ($completed >= 5) {
                    $shared->awardOnce(
                        $uid,
                        'five_courses',
                        __('Five courses complete', 'sikshya'),
                        __('You completed five courses on this site. Top tier learning streak.', 'sikshya')
                    );
                }
            },
            20,
            2
        );
    }

    private static function completedCourseCount(int $user_id): int
    {
        $repo = new EnrollmentRepository();
        if (!$repo->tableExists()) {
            return 0;
        }
        return $repo->countForUserByStatus($user_id, 'completed');
    }
}
