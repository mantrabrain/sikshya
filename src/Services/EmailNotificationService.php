<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CertificateRepository;

/**
 * Basic email notifications (wp_mail) with editable templates.
 *
 * @package Sikshya\Services
 */
final class EmailNotificationService
{
    public function sendEnrollmentEmail(int $user_id, int $course_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildCourseContext($user_id, $course_id);

        return $this->sendSystemTemplate('learner_enrollment', $ctx);
    }

    /**
     * Admin copy for new enrollment (does not return learner send status).
     */
    public function sendAdminEnrollmentNotice(int $user_id, int $course_id): bool
    {
        $ctx = $this->buildCourseContext($user_id, $course_id);

        return $this->sendSystemTemplate('admin_new_enrollment', $ctx);
    }

    /**
     * Lead instructor on the course (first in _sikshya_instructors or course author).
     */
    public function sendInstructorEnrollmentNotice(int $user_id, int $course_id): bool
    {
        $ctx = $this->buildCourseContext($user_id, $course_id);

        return $this->sendSystemTemplate('instructor_new_enrollment', $ctx);
    }

    public function sendCourseCompletedEmail(int $user_id, int $course_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildCourseContext($user_id, $course_id);

        return $this->sendSystemTemplate('learner_course_completed', $ctx);
    }

    public function sendCertificateIssuedEmail(int $user_id, int $course_id, int $issued_id = 0): bool
    {
        if (!Settings::isTruthy(Settings::get('email_certificates', true))) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildCourseContext($user_id, $course_id);
        $ctx = array_merge($ctx, $this->buildCertificateContext($issued_id));

        return $this->sendSystemTemplate('learner_certificate_issued', $ctx);
    }

    public function sendWelcomeEmail(int $user_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildUserContext($user_id);

        return $this->sendSystemTemplate('learner_welcome', $ctx);
    }

    /**
     * Placeholder until automation sends reminders.
     */
    public function sendProgressReminderEmail(int $user_id, int $course_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildCourseContext($user_id, $course_id);

        return $this->sendSystemTemplate('learner_progress_reminder', $ctx);
    }

    /**
     * Drip unlock email (lesson rule). Respects the “Drip: lesson unlocked” template enabled state (Email templates).
     */
    public function sendDripLessonUnlockedEmail(int $user_id, int $course_id, int $lesson_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildMergeContextForDripLesson($user_id, $course_id, $lesson_id);

        return $this->sendSystemTemplate('drip_lesson_unlocked', $ctx);
    }

    /**
     * Drip unlock digest email (multiple lessons in one cron pass).
     *
     * @param int[] $lesson_ids
     */
    public function sendDripLessonsUnlockedDigestEmail(int $user_id, int $course_id, array $lesson_ids): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $lesson_ids = array_values(array_filter(array_map('intval', $lesson_ids), static fn($v) => $v > 0));
        if ($lesson_ids === []) {
            return false;
        }

        $ctx = $this->buildMergeContextForDripLessons($user_id, $course_id, $lesson_ids);

        return $this->sendSystemTemplate('drip_lessons_unlocked_digest', $ctx);
    }

    /**
     * Drip unlock email (course-wide rule). Respects the “Drip: course schedule unlocked” template enabled state (Email templates).
     */
    public function sendDripCourseUnlockedEmail(int $user_id, int $course_id): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $ctx = $this->buildMergeContextForCourse($user_id, $course_id);

        return $this->sendSystemTemplate('drip_course_unlocked', $ctx);
    }

    /**
     * Merge context for drip lesson unlock (course + lesson URLs).
     *
     * @return array<string, string>
     */
    public function buildMergeContextForDripLesson(int $user_id, int $course_id, int $lesson_id): array
    {
        $ctx = $this->buildMergeContextForCourse($user_id, $course_id);
        $lesson_title = get_the_title($lesson_id);
        $lesson_url = get_permalink($lesson_id);
        $ctx['{{lesson_title}}'] = is_string($lesson_title) ? $lesson_title : '';
        $ctx['{{lesson_url}}'] = is_string($lesson_url) ? $lesson_url : '';

        return $ctx;
    }

    /**
     * Merge context for drip lesson digest (course + lesson list).
     *
     * @param int[] $lesson_ids
     * @return array<string, string>
     */
    public function buildMergeContextForDripLessons(int $user_id, int $course_id, array $lesson_ids): array
    {
        $ctx = $this->buildMergeContextForCourse($user_id, $course_id);

        $lesson_ids = array_values(array_filter(array_map('intval', $lesson_ids), static fn($v) => $v > 0));
        $lesson_ids = array_slice(array_values(array_unique($lesson_ids)), 0, 25);

        $items = [];
        foreach ($lesson_ids as $lid) {
            $title = get_the_title($lid);
            $url = get_permalink($lid);
            $t = is_string($title) ? $title : '';
            $u = is_string($url) ? $url : '';
            if ($t === '') {
                continue;
            }
            $items[] = [
                'title' => $t,
                'url' => $u,
            ];
        }

        $list = '<ul style="margin:16px 0 0;padding:0 0 0 18px;">';
        foreach ($items as $it) {
            $title = esc_html($it['title']);
            $url = $it['url'] !== '' ? esc_url($it['url']) : '';
            $list .= '<li style="margin:0 0 8px;">';
            $list .= $url !== '' ? '<a href="' . $url . '" style="color:#0d9488;font-weight:600;text-decoration:none;">' . $title . '</a>' : $title;
            $list .= '</li>';
        }
        $list .= '</ul>';

        $ctx['{{lessons_count}}'] = (string) count($items);
        $ctx['{{lessons_list_html}}'] = $list;
        $ctx['{{first_lesson_url}}'] = isset($items[0]['url']) ? (string) $items[0]['url'] : '';

        return $ctx;
    }

    /**
     * @param array<string, string> $merge_ctx
     */
    private function sendSystemTemplate(string $template_id, array $merge_ctx): bool
    {
        if (!$this->isTemplateEnabled($template_id)) {
            return false;
        }
        $merged = EmailTemplateStore::getMerged($template_id);
        if ($merged === null) {
            return false;
        }
        $expr = (string) ($merged['recipient_to'] ?? '{{student_email}}');
        $to = EmailRecipientResolver::resolve($expr, $merge_ctx);
        if ($to === '') {
            return false;
        }

        return $this->sendTemplatedEmail($template_id, $to, $merge_ctx);
    }

    /**
     * Sample merge values for previews (REST).
     *
     * @return array<string, string>
     */
    public function buildSampleMergeContext(): array
    {
        $admin = trim((string) Settings::get('admin_notification_email', ''));
        if ($admin === '' || ! is_email($admin)) {
            $admin = (string) get_option('admin_email', 'admin@example.com');
        }

        return [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{learner_name}}' => __('Alex Student', 'sikshya'),
            '{{learner_email}}' => 'student@example.com',
            '{{student_name}}' => __('Alex Student', 'sikshya'),
            '{{student_email}}' => 'student@example.com',
            '{{instructor_name}}' => __('Dr. Sample Instructor', 'sikshya'),
            '{{instructor_email}}' => 'instructor@example.com',
            '{{admin_email}}' => is_email($admin) ? $admin : 'admin@example.com',
            '{{course_title}}' => __('🎓 Introduction to Sample Course', 'sikshya'),
            '{{course_url}}' => home_url('/courses/sample-course/'),
            '{{certificate_url}}' => home_url('/certificates/preview/'),
            '{{certificate_number}}' => 'SK-CERT-PREVIEW-001',
            '{{lesson_title}}' => __('Sample lesson: Getting started', 'sikshya'),
            '{{lesson_url}}' => home_url('/learn/sample-lesson/'),
        ];
    }

    /**
     * Send any enabled template (system or custom) to one address.
     *
     * @param array<string, string> $merge_context Keys like {{site_name}}.
     */
    public function sendTemplatedEmail(string $template_id, string $to, array $merge_context): bool
    {
        $to = trim($to);
        if ($to === '' || ! is_email($to)) {
            return false;
        }

        return $this->sendTemplateToAddress($template_id, $to, $merge_context);
    }

    /**
     * Merge tags for a single user (welcome, etc.).
     *
     * @return array<string, string>
     */
    public function buildMergeContextForUser(int $user_id): array
    {
        return $this->buildUserContext($user_id);
    }

    /**
     * Merge tags when course + learner are known.
     *
     * @return array<string, string>
     */
    public function buildMergeContextForCourse(int $user_id, int $course_id): array
    {
        return $this->buildCourseContext($user_id, $course_id);
    }

    /**
     * Course context plus certificate URL / number from an issued row id.
     *
     * @return array<string, string>
     */
    public function buildMergeContextForCourseAndCertificate(int $user_id, int $course_id, int $issued_id): array
    {
        $ctx = $this->buildCourseContext($user_id, $course_id);

        return array_merge($ctx, $this->buildCertificateContext($issued_id));
    }

    /**
     * Wrap inner HTML like production emails (for preview modal).
     */
    public function previewWrapHtml(string $inner): string
    {
        return $this->wrapHtml($inner);
    }

    /**
     * @param array<string, string> $merge_context
     */
    private function sendTemplateToAddress(string $template_id, string $to, array $merge_context): bool
    {
        $merged = EmailTemplateStore::getMerged($template_id);
        if ($merged === null || empty($merged['enabled'])) {
            return false;
        }

        $subject = (string) ($merged['subject'] ?? '');
        $body = (string) ($merged['body_html'] ?? '');

        $subject = EmailTemplateMerge::apply($subject, $merge_context);
        $inner = EmailTemplateMerge::apply($body, $merge_context);
        $html = $this->wrapHtml($inner);

        return $this->send($to, $subject, $html);
    }

    private function isTemplateEnabled(string $template_id): bool
    {
        $merged = EmailTemplateStore::getMerged($template_id);
        if ($merged === null) {
            return false;
        }

        return !empty($merged['enabled']);
    }

    /**
     * @return array<string, string>
     */
    private function buildUserContext(int $user_id): array
    {
        $user = get_user_by('id', $user_id);
        $name = $user ? $user->display_name : '';
        $email = $user && !empty($user->user_email) ? $user->user_email : '';

        $admin = trim((string) Settings::get('admin_notification_email', ''));
        if ($admin === '' || ! is_email($admin)) {
            $admin = (string) get_option('admin_email', '');
        }

        return [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{learner_name}}' => $name,
            '{{learner_email}}' => $email,
            '{{student_name}}' => $name,
            '{{student_email}}' => $email,
            '{{admin_email}}' => is_email($admin) ? $admin : '',
            '{{instructor_name}}' => '',
            '{{instructor_email}}' => '',
            '{{course_title}}' => '',
            '{{course_url}}' => '',
            '{{certificate_url}}' => '',
            '{{certificate_number}}' => '',
            '{{lesson_title}}' => '',
            '{{lesson_url}}' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCourseContext(int $user_id, int $course_id): array
    {
        $base = $this->buildUserContext($user_id);
        $course_title = get_the_title($course_id);
        $learn_url = get_permalink($course_id);
        if (!is_string($course_title)) {
            $course_title = '';
        }
        $base['{{course_title}}'] = $course_title;
        $base['{{course_url}}'] = is_string($learn_url) ? $learn_url : '';

        $inst = $this->getLeadInstructorDetails($course_id);
        $base['{{instructor_name}}'] = $inst['name'];
        $base['{{instructor_email}}'] = $inst['email'];

        return $base;
    }

    /**
     * @return array{name: string, email: string}
     */
    private function getLeadInstructorDetails(int $course_id): array
    {
        $raw = get_post_meta($course_id, '_sikshya_instructors', true);
        $ids = [];
        if (is_array($raw)) {
            $ids = array_map('intval', $raw);
        } elseif (is_numeric($raw)) {
            $ids = [(int) $raw];
        }
        $ids = array_values(array_filter($ids));
        $uid = $ids[0] ?? 0;
        if ($uid <= 0) {
            $author = get_post_field('post_author', $course_id);
            $uid = is_numeric($author) ? (int) $author : 0;
        }
        if ($uid <= 0) {
            return ['name' => '', 'email' => ''];
        }
        $u = get_userdata($uid);
        if (!$u) {
            return ['name' => '', 'email' => ''];
        }
        $em = $u->user_email;

        return [
            'name' => $u->display_name,
            'email' => is_email($em) ? $em : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCertificateContext(int $issued_id): array
    {
        $out = [
            '{{certificate_url}}' => '',
            '{{certificate_number}}' => '',
        ];
        if ($issued_id <= 0) {
            return $out;
        }

        $repo = new CertificateRepository();
        $row = $repo->findById($issued_id);
        if (!$row) {
            return $out;
        }

        $out['{{certificate_number}}'] = (string) ($row->certificate_number ?? '');
        $dl = (string) ($row->download_url ?? '');
        $out['{{certificate_url}}'] = $dl !== '' ? $dl : '';

        return $out;
    }

    /**
     * Send a one-off test message using the same From/reply headers, optional SMTP, and branded wrapper as production mail.
     */
    public function sendTestDeliveryEmail(string $to): bool
    {
        $to = trim($to);
        if ($to === '' || !is_email($to)) {
            return false;
        }

        $inner = '<p style="margin:0 0 12px;">' . esc_html__(
            'This is a test email from your LMS. If you received it, your From address, optional SMTP transport, and branded header/footer (when the professional email add-on is enabled) are working.',
            'sikshya'
        ) . '</p>';

        $html = $this->wrapHtml($inner);
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Email delivery test', 'sikshya'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        return $this->send($to, $subject, $html);
    }

    private function send(string $to, string $subject, string $html): bool
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $from_email = trim((string) Settings::get('from_email', ''));
        $from_name = trim(wp_specialchars_decode((string) Settings::get('from_name', ''), ENT_QUOTES));
        if ($from_email && is_email($from_email)) {
            if ($from_name !== '') {
                $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
            } else {
                $headers[] = 'From: ' . $from_email;
            }
        }

        $reply = trim((string) Settings::get('reply_to_email', ''));
        if ($reply && is_email($reply)) {
            $headers[] = 'Reply-To: ' . $reply;
        }

        return (bool) wp_mail($to, $subject, $html, $headers);
    }

    private function wrapHtml(string $inner): string
    {
        /**
         * Allow Pro (and other extensions) to prepend/append HTML around the inner body
         * before the Sikshya outer card wrapper is applied.
         *
         * @param string $inner Safe HTML fragment.
         */
        $inner = (string) apply_filters('sikshya_email_transactional_inner_html', $inner);

        $brand = esc_html(get_bloginfo('name'));
        $platform = function_exists('sikshya_brand_name') ? esc_html(sikshya_brand_name('email')) : esc_html__('Sikshya LMS', 'sikshya');
        $outer = '<div style="background:#f8fafc;padding:24px 0;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
              <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-weight:700;">' . $brand . '</div>
              <div style="padding:20px;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
                ' . $inner . '
              </div>
              <div style="padding:14px 20px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;">
                ' . sprintf(
                    /* translators: %s: platform/brand name */
                    esc_html__('Sent by %s', 'sikshya'),
                    $platform
                ) . '
              </div>
            </div>
          </div>';

        return $outer;
    }
}
