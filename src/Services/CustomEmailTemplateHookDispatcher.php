<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\OrderRepository;

/**
 * Registers WordPress hooks for custom email templates so stored {@see EmailTemplateStore} `event` keys actually send mail.
 *
 * System templates are still sent by {@see EmailNotificationService} from {@see EmailNotificationsAddon} at priority 10;
 * custom templates for the same hook run at priority 20 (additional emails).
 *
 * @package Sikshya\Services
 */
final class CustomEmailTemplateHookDispatcher
{
    private const PRIORITY = 20;

    /**
     * Map hook name → number of arguments passed by core Sikshya / WordPress.
     *
     * @var array<string, int>
     */
    private const HOOK_ARG_COUNTS = [
        'user_register' => 1,
        'sikshya_user_enrolled' => 3,
        'sikshya_user_unenrolled' => 3,
        'sikshya_course_completed' => 2,
        'sikshya_certificate_issued' => 3,
        'sikshya_order_fulfilled' => 2,
        'sikshya_assignment_submitted' => 4,
        'sikshya_certificate_row_created' => 6,
        'sikshya.scheduled_reminder' => 4,
        'sikshya_drip_lesson_unlocked' => 3,
        'sikshya_drip_course_unlocked' => 2,
        'sikshya_drip_lessons_unlocked' => 3,
        'sikshya_course_qa_question_posted' => 4,
    ];

    public static function register(EmailNotificationService $mailer): void
    {
        $by_event = self::collectEnabledCustomTemplatesByEvent();
        foreach ($by_event as $event => $template_ids) {
            $accepted = self::HOOK_ARG_COUNTS[ $event ] ?? 6;
            add_action(
                $event,
                static function (...$args) use ($mailer, $template_ids, $event) {
                    foreach ($template_ids as $template_id) {
                        self::dispatchOne($mailer, (string) $template_id, $event, $args);
                    }
                },
                self::PRIORITY,
                $accepted
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private static function collectEnabledCustomTemplatesByEvent(): array
    {
        $by_event = [];
        foreach (EmailTemplateStore::getStore() as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }
            if ((string) ($row['template_type'] ?? '') !== 'custom') {
                continue;
            }
            if (! Settings::isTruthy($row['enabled'] ?? true)) {
                continue;
            }
            $event = EmailTemplateStore::sanitizeEventKey((string) ($row['event'] ?? 'custom.manual'));
            if ($event === '' || $event === 'custom.manual') {
                continue;
            }
            if (! isset($by_event[ $event ])) {
                $by_event[ $event ] = [];
            }
            $by_event[ $event ][] = $id;
        }

        return $by_event;
    }

    /**
     * @param list<mixed> $args
     */
    private static function dispatchOne(EmailNotificationService $mailer, string $template_id, string $event, array $args): void
    {
        if (!EmailTemplateGate::isEventDispatchAllowed($event)) {
            return;
        }

        $merged = EmailTemplateStore::getMerged($template_id);
        if ($merged === null || empty($merged['enabled'])) {
            return;
        }
        if ((string) ($merged['template_type'] ?? '') !== 'custom') {
            return;
        }
        if ((string) ($merged['event'] ?? '') !== $event) {
            return;
        }

        $ctx = self::buildMergeContext($mailer, $event, $args);
        if ($ctx === null) {
            return;
        }

        $expr = (string) ($merged['recipient_to'] ?? '');
        if ($expr === '') {
            $legacy = (string) ($merged['recipient'] ?? 'learner');
            $expr = $legacy === 'admin' ? '{{admin_email}}' : '{{student_email}}';
        }
        $to = EmailRecipientResolver::resolve($expr, $ctx);
        if ($to === '') {
            return;
        }

        $mailer->sendTemplatedEmail($template_id, $to, $ctx);
    }

    /**
     * @param list<mixed> $args
     *
     * @return array<string, string>|null
     */
    private static function buildMergeContext(EmailNotificationService $mailer, string $event, array $args): ?array
    {
        switch ($event) {
            case 'user_register':
                $uid = isset($args[0]) ? absint($args[0]) : 0;

                return $uid > 0 ? $mailer->buildMergeContextForUser($uid) : null;

            case 'sikshya_user_enrolled':
            case 'sikshya_user_unenrolled':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;

                return ($uid > 0 && $cid > 0) ? $mailer->buildMergeContextForCourse($uid, $cid) : null;

            case 'sikshya_course_completed':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;

                return ($uid > 0 && $cid > 0) ? $mailer->buildMergeContextForCourse($uid, $cid) : null;

            case 'sikshya_course_qa_question_posted':
                $learner = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;
                $content_id = isset($args[2]) ? absint($args[2]) : 0;
                $com_id = isset($args[3]) ? absint($args[3]) : 0;

                return ($cid > 0 && $content_id > 0 && $com_id > 0)
                    ? $mailer->buildMergeContextForQaQuestion($learner, $cid, $content_id, $com_id)
                    : null;

            case 'sikshya_certificate_issued':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;
                $issued = isset($args[2]) ? absint($args[2]) : 0;

                return ($uid > 0 && $cid > 0 && $issued > 0)
                    ? $mailer->buildMergeContextForCourseAndCertificate($uid, $cid, $issued)
                    : null;

            case 'sikshya_order_fulfilled':
                $order_id = isset($args[0]) ? absint($args[0]) : 0;
                $order = $args[1] ?? null;

                return self::mergeContextForOrder($mailer, $order_id, $order);

            case 'sikshya_assignment_submitted':
                $cid = isset($args[2]) ? absint($args[2]) : 0;
                $uid = isset($args[3]) ? absint($args[3]) : 0;

                return ($uid > 0 && $cid > 0) ? $mailer->buildMergeContextForCourse($uid, $cid) : null;

            case 'sikshya_certificate_row_created':
                $issued_id = isset($args[0]) ? absint($args[0]) : 0;
                $uid = isset($args[1]) ? absint($args[1]) : 0;
                $cid = isset($args[2]) ? absint($args[2]) : 0;

                return ($issued_id > 0 && $uid > 0 && $cid > 0)
                    ? $mailer->buildMergeContextForCourseAndCertificate($uid, $cid, $issued_id)
                    : null;

            case 'sikshya.scheduled_reminder':
                if (count($args) >= 2) {
                    $uid = absint($args[0]);
                    $cid = absint($args[1]);
                    if ($uid > 0 && $cid > 0) {
                        return $mailer->buildMergeContextForCourse($uid, $cid);
                    }
                }

                return null;

            case 'sikshya_drip_lesson_unlocked':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;
                $lid = isset($args[2]) ? absint($args[2]) : 0;

                return ($uid > 0 && $cid > 0 && $lid > 0)
                    ? $mailer->buildMergeContextForDripLesson($uid, $cid, $lid)
                    : null;

            case 'sikshya_drip_course_unlocked':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;

                return ($uid > 0 && $cid > 0) ? $mailer->buildMergeContextForCourse($uid, $cid) : null;

            case 'sikshya_drip_lessons_unlocked':
                $uid = isset($args[0]) ? absint($args[0]) : 0;
                $cid = isset($args[1]) ? absint($args[1]) : 0;
                $lids = $args[2] ?? [];
                $lids = is_array($lids) ? array_values(array_filter(array_map('absint', $lids))) : [];
                if ($uid > 0 && $cid > 0 && $lids !== [] && method_exists($mailer, 'buildMergeContextForDripLessons')) {
                    /** @var array<string,string> $ctx */
                    $ctx = $mailer->buildMergeContextForDripLessons($uid, $cid, $lids);
                    return $ctx;
                }

                return null;

            default:
                return null;
        }
    }

    /**
     * @param mixed $order
     */
    private static function mergeContextForOrder(EmailNotificationService $mailer, int $order_id, $order): ?array
    {
        if (! is_object($order)) {
            return null;
        }
        $user_id = isset($order->user_id) ? absint($order->user_id) : 0;
        if ($user_id <= 0) {
            return null;
        }

        $repo = new OrderRepository();
        $items = $repo->getItems($order_id);
        $course_id = 0;
        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }
            $cid = isset($item->course_id) ? (int) $item->course_id : 0;
            if ($cid > 0) {
                $course_id = $cid;
                break;
            }
        }

        if ($course_id > 0) {
            return $mailer->buildMergeContextForCourse($user_id, $course_id);
        }

        return $mailer->buildMergeContextForUser($user_id);
    }
}
