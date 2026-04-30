<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\Settings;
use Sikshya\Services\CourseService;
use Sikshya\Core\Plugin;
use Sikshya\Services\PublicCurriculumService;

/**
 * Learner dashboard data for the account page.
 *
 * @package Sikshya\Frontend\Public
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

        $account_view = PublicPageUrls::currentAccountView();

        return apply_filters(
            'sikshya_account_template_data',
            [
                'user_id' => $uid,
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
}
