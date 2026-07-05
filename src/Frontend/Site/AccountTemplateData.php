<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\AchievementsRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\Settings;
use Sikshya\Services\CourseService;
use Sikshya\Services\LearnerCurriculumHelper;
use Sikshya\Core\Plugin;
use Sikshya\Services\PublicCurriculumService;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Learner dashboard data for the account page.
 *
 * @package Sikshya\Frontend\Site
 */
final class AccountTemplateData
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $uid = get_current_user_id();
        $enrollments = [];
        $enrollments_ongoing = [];
        $enrollments_completed = [];
        $orders = [];
        $legacy_payments = [];
        $quiz_attempts = [];

        $display_name = '';
        $email = '';
        $avatar_url = '';

        if ($uid > 0) {
            $user = wp_get_current_user();
            if ($user && $user->exists()) {
                $display_name = (string) $user->display_name;
                if ($display_name === '') {
                    $display_name = (string) $user->user_login;
                }
                $email = (string) $user->user_email;
                $avatar_url = (string) get_avatar_url($uid, ['size' => 96]);
            }

            $courseService = Plugin::getInstance()->getService('course');
            if ($courseService instanceof CourseService) {
                $enrollments = $courseService->getUserEnrollments($uid, ['limit' => 50]);
            }

            foreach ($enrollments as $enr_row) {
                $st = is_object($enr_row) ? (string) ($enr_row->status ?? '') : (string) ($enr_row['status'] ?? '');
                if ($st === 'completed') {
                    $enrollments_completed[] = $enr_row;
                } else {
                    $enrollments_ongoing[] = $enr_row;
                }
            }

            $orderRepo = new OrderRepository();
            if ($orderRepo->tableExists()) {
                $orders = $orderRepo->findRecentForUser($uid, 50);
            }

            $paymentRepo = new PaymentRepository();
            if ($paymentRepo->tableExists()) {
                foreach ($paymentRepo->findFiltered($uid, null) as $pr) {
                    $legacy_payments[] = [
                        'amount' => isset($pr->amount) ? (float) $pr->amount : 0.0,
                        'currency' => isset($pr->currency) ? (string) $pr->currency : 'USD',
                        'status' => isset($pr->status) ? (string) $pr->status : '',
                        'payment_method' => isset($pr->payment_method) ? (string) $pr->payment_method : '',
                        'transaction_id' => isset($pr->transaction_id) ? (string) $pr->transaction_id : '',
                        'payment_date' => isset($pr->payment_date) ? (string) $pr->payment_date : '',
                        'course_id' => isset($pr->course_id) ? (int) $pr->course_id : 0,
                    ];
                }
            }

            // Quiz attempts overview (per enrolled course).
            $quiz_attempt_repo = new QuizAttemptRepository();
            $seen_quiz_ids = [];
            $global_attempts_limit = (int) Settings::get('quiz_attempts_limit', 1);
            if ($global_attempts_limit < 0) {
                $global_attempts_limit = 0;
            }

            foreach ($enrollments as $enr) {
                $course_id = is_object($enr) ? (int) ($enr->course_id ?? 0) : (int) ($enr['course_id'] ?? 0);
                if ($course_id <= 0) {
                    continue;
                }

                $raw = PublicCurriculumService::getCourseCurriculum($course_id);
                foreach ((array) $raw as $row) {
                    foreach ((array) ($row['contents'] ?? []) as $p) {
                        if (!$p instanceof \WP_Post) {
                            continue;
                        }
                        if ($p->post_type !== PostTypes::QUIZ) {
                            continue;
                        }

                        $qid = (int) $p->ID;
                        if ($qid <= 0 || isset($seen_quiz_ids[$qid])) {
                            continue;
                        }
                        $seen_quiz_ids[$qid] = true;

                        $per_quiz = (int) get_post_meta($qid, '_sikshya_quiz_attempts_allowed', true);
                        $limit = $per_quiz > 0 ? $per_quiz : $global_attempts_limit;
                        if ($limit < 0) {
                            $limit = 0;
                        }

                        $used = $quiz_attempt_repo->countAttemptsForUserQuiz($uid, $qid);
                        $remaining = $limit > 0 ? max(0, $limit - (int) $used) : null;
                        $locked = $limit > 0 && (int) $used >= $limit;

                        $quiz_attempts[] = [
                            'quiz_id' => $qid,
                            'course_id' => $course_id,
                            'quiz_title' => get_the_title($qid),
                            'course_title' => get_the_title($course_id),
                            'attempts_used' => (int) $used,
                            'attempts_limit' => (int) $limit,
                            'attempts_remaining' => $remaining,
                            'is_locked' => $locked,
                            'url' => PublicPageUrls::learnContentForPost($p),
                        ];

                        // Keep the account page fast; cap the dataset.
                        if (count($quiz_attempts) >= 120) {
                            break 3;
                        }
                    }
                }
            }
        }

        $resume_card = self::buildResumeCard($uid, $enrollments_ongoing);

        $achievements = [];
        $achievements_count = 0;
        if ($uid > 0) {
            $achievementsRepo = new AchievementsRepository();
            if ($achievementsRepo->tableExists()) {
                $rows = $achievementsRepo->findByUser($uid, 12);
                foreach ($rows as $row) {
                    $achievements[] = [
                        'type' => isset($row->achievement_type) ? (string) $row->achievement_type : '',
                        'name' => isset($row->achievement_name) ? (string) $row->achievement_name : '',
                        'description' => isset($row->description) ? (string) $row->description : '',
                        'badge_url' => isset($row->badge_url) ? (string) $row->badge_url : '',
                        'earned_date' => isset($row->earned_date) ? (string) $row->earned_date : '',
                    ];
                }
                $achievements_count = $achievementsRepo->countByUser($uid);
            }
        }

        $account_view = PublicPageUrls::currentAccountView();

        return apply_filters(
            'sikshya_account_template_data',
            [
                'user_id' => $uid,
                'resume_card' => $resume_card,
                'achievements' => $achievements,
                'achievements_count' => $achievements_count,
                'display_name' => $display_name,
                'email' => $email,
                'avatar_url' => $avatar_url,
                'account_view' => $account_view,
                'enrollments' => $enrollments,
                'enrollments_ongoing' => $enrollments_ongoing,
                'enrollments_completed' => $enrollments_completed,
                'orders' => $orders,
                'legacy_payments' => $legacy_payments,
                'quiz_attempts' => $quiz_attempts,
                'enrollment_count' => count($enrollments),
                'ongoing_count' => count($enrollments_ongoing),
                'completed_count' => count($enrollments_completed),
                'orders_count' => count($orders),
                'legacy_payments_count' => count($legacy_payments),
                'quiz_attempts_count' => count($quiz_attempts),
                'urls' => [
                    'home' => home_url('/'),
                    'account' => PublicPageUrls::url('account'),
                    'account_dashboard' => PublicPageUrls::accountViewUrl('dashboard'),
                    'account_learning' => PublicPageUrls::accountViewUrl('learning'),
                    'account_payments' => PublicPageUrls::accountViewUrl('payments'),
                    'account_quiz_attempts' => PublicPageUrls::accountViewUrl('quiz-attempts'),
                    'account_profile' => PublicPageUrls::accountViewUrl('profile'),
                    'learn' => PublicPageUrls::url('learn'),
                    'cart' => PublicPageUrls::url('cart'),
                    'checkout' => PublicPageUrls::url('checkout'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }

    /**
     * Build the "Continue where you left off" hero card payload.
     *
     * Picks the most-recently-touched ongoing enrollment and resolves the next
     * uncompleted curriculum lesson — falling back to the last touched lesson
     * if everything is already complete. Returns null when there's nothing to
     * resume (no ongoing enrollments, or no curriculum lessons resolved).
     *
     * @param array<int, mixed> $enrollments_ongoing
     * @return array<string, mixed>|null
     */
    private static function buildResumeCard(int $user_id, array $enrollments_ongoing): ?array
    {
        if ($user_id <= 0 || $enrollments_ongoing === []) {
            return null;
        }

        $ongoing_course_ids = [];
        foreach ($enrollments_ongoing as $enr) {
            $cid = is_object($enr) ? (int) ($enr->course_id ?? 0) : (int) ($enr['course_id'] ?? 0);
            if ($cid > 0) {
                $ongoing_course_ids[$cid] = true;
            }
        }
        if ($ongoing_course_ids === []) {
            return null;
        }

        $progressRepo = new ProgressRepository();
        if (!$progressRepo->tableExists()) {
            return null;
        }

        $touched = $progressRepo->findLastTouchedLessonRow($user_id);
        $resume_course_id = 0;
        $last_lesson_id = 0;
        if ($touched && isset($touched->course_id)) {
            $tcid = (int) $touched->course_id;
            if (isset($ongoing_course_ids[$tcid])) {
                $resume_course_id = $tcid;
                $last_lesson_id = isset($touched->lesson_id) ? (int) $touched->lesson_id : 0;
            }
        }
        if ($resume_course_id <= 0) {
            // No recent activity in an ongoing course — fall back to the first
            // ongoing enrollment so the card still surfaces.
            $resume_course_id = (int) array_key_first($ongoing_course_ids);
        }
        if ($resume_course_id <= 0) {
            return null;
        }

        $lesson_ids = LearnerCurriculumHelper::lessonIdsForCourse($resume_course_id);
        if ($lesson_ids === []) {
            return null;
        }
        $completed = $progressRepo->completedLessonIdsForCourse($user_id, $resume_course_id);
        $completed_set = array_fill_keys(array_map('intval', $completed), true);

        $next_lesson_id = 0;
        foreach ($lesson_ids as $lid) {
            if (!isset($completed_set[(int) $lid])) {
                $next_lesson_id = (int) $lid;
                break;
            }
        }
        if ($next_lesson_id <= 0) {
            $next_lesson_id = $last_lesson_id > 0 ? $last_lesson_id : (int) $lesson_ids[0];
        }

        $total = count($lesson_ids);
        $completed_count = $progressRepo->countCompletedLessons($user_id, $resume_course_id);
        $percent = $total > 0 ? (int) round(100 * $completed_count / $total) : 0;

        $lesson_post = get_post($next_lesson_id);
        $resume_url = '';
        $lesson_title = '';
        if ($lesson_post instanceof \WP_Post) {
            $resume_url = PublicPageUrls::learnContentForPost($lesson_post);
            $lesson_title = (string) get_the_title($lesson_post);
        }
        if ($resume_url === '') {
            $resume_url = PublicPageUrls::learnForCourse($resume_course_id);
        }

        return [
            'course_id' => $resume_course_id,
            'course_title' => (string) get_the_title($resume_course_id),
            'course_permalink' => get_permalink($resume_course_id) ?: '',
            'lesson_id' => $next_lesson_id,
            'lesson_title' => $lesson_title,
            'resume_url' => $resume_url,
            'lesson_total' => $total,
            'lessons_completed' => $completed_count,
            'progress_percent' => $percent,
            'is_resume' => $last_lesson_id > 0,
        ];
    }
}
