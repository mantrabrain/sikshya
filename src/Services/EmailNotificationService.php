<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CertificateRepository;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Site\PublicPageUrls;

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
     * Payment receipt after order is marked paid ({@see 'sikshya_order_fulfilled'}). Uses Email templates → “Payment receipt”.
     *
     * @param object $order Order row (must include user_id, totals, currency, gateway).
     */
    public function sendPaymentReceiptForOrder(int $order_id, $order): bool
    {
        $ctx = $this->buildMergeContextForPaidOrder($order_id, $order);
        if ($ctx === null) {
            return false;
        }

        return $this->sendSystemTemplate('learner_payment_receipt', $ctx);
    }

    /**
     * Merge context for {@see learner_payment_receipt} (and previews).
     *
     * @param object $order Order row from {@see OrderRepository::findById()}.
     *
     * @return array<string, string>|null
     */
    public function buildMergeContextForPaidOrder(int $order_id, $order): ?array
    {
        if (!is_object($order)) {
            return null;
        }
        $user_id = isset($order->user_id) ? (int) $order->user_id : 0;
        if ($user_id <= 0) {
            return null;
        }

        $base = $this->buildUserContext($user_id);
        $currency = strtoupper((string) ($order->currency ?? 'USD'));
        if (strlen($currency) !== 3) {
            $currency = 'USD';
        }
        $subtotal = isset($order->subtotal) ? (float) $order->subtotal : 0.0;
        $discount = isset($order->discount_total) ? (float) $order->discount_total : 0.0;
        $total = isset($order->total) ? (float) $order->total : 0.0;
        $gateway = sanitize_key((string) ($order->gateway ?? ''));

        $repo = new OrderRepository();
        $public_token = isset($order->public_token) && is_string($order->public_token) && $order->public_token !== ''
            ? (string) $order->public_token
            : $repo->ensurePublicToken($order_id);
        $receipt_url = $public_token !== '' ? PublicPageUrls::orderView($public_token) : home_url('/');

        $lines_html = '';
        foreach ($repo->getItems($order_id) as $item) {
            if (!is_object($item)) {
                continue;
            }
            $cid = isset($item->course_id) ? (int) $item->course_id : 0;
            $title = $cid > 0 ? get_the_title($cid) : '';
            if (!is_string($title) || $title === '') {
                $title = '#' . (string) $cid;
            }
            $line_total = isset($item->line_total) ? (float) $item->line_total : 0.0;
            $line_fmt = function_exists('sikshya_format_price_plain')
                ? sikshya_format_price_plain($line_total, $currency)
                : number_format_i18n($line_total, 2) . ' ' . $currency;
            $lines_html .= '<li style="margin:0 0 8px;">' . esc_html($title) . ' — <strong>' . esc_html($line_fmt) . '</strong></li>';
        }
        if ($lines_html !== '') {
            $lines_html = '<ul style="margin:0;padding-left:20px;">' . $lines_html . '</ul>';
        } else {
            $lines_html = '<p style="margin:0;color:#64748b;">' . esc_html__('(No line items)', 'sikshya') . '</p>';
        }

        $fmt = static function (float $a, string $cur): string {
            return function_exists('sikshya_format_price_plain')
                ? sikshya_format_price_plain($a, $cur)
                : number_format_i18n($a, 2) . ' ' . $cur;
        };

        $base['{{order_id}}'] = (string) $order_id;
        $base['{{order_currency}}'] = $currency;
        $base['{{order_subtotal}}'] = $fmt($subtotal, $currency);
        $base['{{order_discount}}'] = $discount > 0.00001 ? $fmt($discount, $currency) : '—';
        $base['{{order_total}}'] = $fmt($total, $currency);
        $base['{{payment_method}}'] = $this->paymentGatewayLabel($gateway);
        $base['{{order_lines_html}}'] = $lines_html;
        $base['{{order_receipt_url}}'] = esc_url($receipt_url);

        return $base;
    }

    private function paymentGatewayLabel(string $gateway): string
    {
        $map = [
            'offline' => __('Offline / manual', 'sikshya'),
            'bank_transfer' => __('Bank transfer', 'sikshya'),
            'stripe' => __('Stripe', 'sikshya'),
            'paypal' => __('PayPal', 'sikshya'),
            'mollie' => __('Mollie', 'sikshya'),
            'paystack' => __('Paystack', 'sikshya'),
            'razorpay' => __('Razorpay', 'sikshya'),
            'square' => __('Square', 'sikshya'),
            'authorize_net' => __('Authorize.Net', 'sikshya'),
        ];

        return $map[$gateway] ?? ($gateway !== '' ? $gateway : __('Unknown', 'sikshya'));
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
     * Instructor notice after a learner posts a Community Q&A question (Growth add-on hook).
     */
    public function sendInstructorQaQuestionPostedEmail(int $learner_user_id, int $course_id, int $content_id, int $comment_id): bool
    {
        $def = EmailTemplateCatalog::get('instructor_qa_question');
        if ($def === null) {
            return false;
        }
        if (EmailTemplateGate::metadataFromCatalogDef($def)['locked']) {
            return false;
        }

        $ctx = $this->buildMergeContextForQaQuestion($learner_user_id, $course_id, $content_id, $comment_id);
        if ($ctx === null) {
            return false;
        }

        $ie = isset($ctx['{{instructor_email}}']) ? trim((string) $ctx['{{instructor_email}}']) : '';
        if ($ie === '' || !is_email($ie)) {
            return false;
        }

        return $this->sendSystemTemplate('instructor_qa_question', $ctx);
    }

    /**
     * Merge tags for instructor Q&A question emails.
     *
     * @return array<string, string>|null
     */
    public function buildMergeContextForQaQuestion(int $learner_user_id, int $course_id, int $content_id, int $comment_id): ?array
    {
        if ($course_id <= 0 || $content_id <= 0 || $comment_id <= 0) {
            return null;
        }

        $comment = get_comment($comment_id);
        if (!$comment instanceof \WP_Comment) {
            return null;
        }

        $uid = $learner_user_id > 0 ? $learner_user_id : (int) $comment->user_id;
        if ($uid > 0) {
            $ctx = $this->buildMergeContextForCourse($uid, $course_id);
        } else {
            $base = $this->buildUserContext(0);
            $name = sanitize_text_field((string) $comment->comment_author);
            $em = sanitize_email((string) $comment->comment_author_email);
            $base['{{student_name}}'] = $name;
            $base['{{student_email}}'] = is_email($em) ? $em : '';
            $base['{{learner_name}}'] = $name;
            $base['{{learner_email}}'] = $base['{{student_email}}'];
            $ctx = $this->applyCourseTitleUrlAndInstructor($base, $course_id);
        }

        $ct = get_the_title($content_id);
        $ctx['{{content_title}}'] = is_string($ct) ? $ct : '';

        $cp = get_permalink($content_id);
        $cp = is_string($cp) ? $cp : '';

        $ctx['{{content_url}}'] = $cp !== '' ? esc_url($cp) : esc_url(home_url('/'));

        if ($cp !== '') {
            $review_url = $cp . '#comment-' . $comment_id;
        } else {
            $link_fallback = get_comment_link($comment_id);
            $review_url = is_string($link_fallback) && $link_fallback !== '' ? $link_fallback : ($ctx['{{content_url}}']);
        }

        $ctx['{{qa_review_url}}'] = esc_url(is_string($review_url) ? $review_url : home_url('/'));

        $snippet = wp_strip_all_tags(wp_unslash((string) $comment->comment_content));
        $snippet = wp_trim_words($snippet, 80, __('…', 'sikshya'));

        /**
         * Filters the plain preview text before escaping for {@see '{{qa_question_preview}}'} merge output.
         */
        $snippet = (string) apply_filters('sikshya_email_qa_question_preview_text', $snippet, $course_id, $content_id, $comment_id);

        $ctx['{{qa_question_preview}}'] = nl2br(esc_html($snippet), false);

        /**
         * @var array<string, string> $ctx
         */
        return $ctx;
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
        $admin = $this->resolveAdminContactEmail();
        if ($admin === '') {
            $admin = 'admin@example.com';
        }

        $sample_cur = 'USD';

        return [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{learner_name}}' => __('Alex Student', 'sikshya'),
            '{{learner_email}}' => 'student@example.com',
            '{{student_name}}' => __('Alex Student', 'sikshya'),
            '{{student_email}}' => 'student@example.com',
            '{{instructor_name}}' => __('Dr. Sample Instructor', 'sikshya'),
            '{{instructor_email}}' => 'instructor@example.com',
            '{{admin_email}}' => $admin,
            '{{course_title}}' => __('🎓 Introduction to Sample Course', 'sikshya'),
            '{{course_url}}' => home_url('/courses/sample-course/'),
            '{{certificate_url}}' => home_url('/certificates/preview/'),
            '{{certificate_number}}' => 'SK-CERT-PREVIEW-001',
            '{{lesson_title}}' => __('Sample lesson: Getting started', 'sikshya'),
            '{{lesson_url}}' => home_url('/learn/sample-lesson/'),
            '{{order_id}}' => '10042',
            '{{order_currency}}' => $sample_cur,
            '{{order_subtotal}}' => function_exists('sikshya_format_price_plain')
                ? sikshya_format_price_plain(120.0, $sample_cur)
                : '120.00 ' . $sample_cur,
            '{{order_discount}}' => function_exists('sikshya_format_price_plain')
                ? sikshya_format_price_plain(20.0, $sample_cur)
                : '20.00 ' . $sample_cur,
            '{{order_total}}' => function_exists('sikshya_format_price_plain')
                ? sikshya_format_price_plain(100.0, $sample_cur)
                : '100.00 ' . $sample_cur,
            '{{payment_method}}' => __('Stripe', 'sikshya'),
            '{{order_lines_html}}' => '<ul style="margin:0;padding-left:20px;">'
                . '<li style="margin:0 0 8px;">' . esc_html__('Sample course A', 'sikshya') . ' — <strong>60.00 USD</strong></li>'
                . '<li style="margin:0 0 8px;">' . esc_html__('Sample course B', 'sikshya') . ' — <strong>40.00 USD</strong></li>'
                . '</ul>',
            '{{order_receipt_url}}' => esc_url(home_url('/?sikshya_order_preview=1')),
            '{{qa_question_preview}}' => nl2br(esc_html__('Hi instructor — quick question before I start Module 3. Can you confirm if the quiz covers chapter 5 only?', 'sikshya'), false),
            '{{qa_review_url}}' => esc_url(home_url('/learn/sample-lesson/')),
            '{{content_title}}' => __('Sample quiz · Quick quiz 2', 'sikshya'),
            '{{content_url}}' => esc_url(home_url('/learn/sample-lesson/')),
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

        $admin = $this->resolveAdminContactEmail();

        return [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{learner_name}}' => $name,
            '{{learner_email}}' => $email,
            '{{student_name}}' => $name,
            '{{student_email}}' => $email,
            '{{admin_email}}' => $admin,
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

        return $this->applyCourseTitleUrlAndInstructor($base, $course_id);
    }

    /**
     * @param array<string, string> $base Merge row with learner tags already set.
     * @return array<string, string>
     */
    private function applyCourseTitleUrlAndInstructor(array $base, int $course_id): array
    {
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

        return $this->send($to, $subject, $html, true);
    }

    /**
     * @param bool $ignore_notification_toggle When true, sends even if “Email notices” is off (e.g. delivery test).
     */
    private function send(string $to, string $subject, string $html, bool $ignore_notification_toggle = false): bool
    {
        if (!$ignore_notification_toggle && !Settings::isTruthy(Settings::get('enable_email_notifications', '1'))) {
            return false;
        }

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

    /**
     * Admin-facing address for merge tags: LMS notification setting → Global Settings contact → WordPress admin email.
     */
    private function resolveAdminContactEmail(): string
    {
        $from_settings = trim((string) Settings::get('admin_notification_email', ''));
        if ($from_settings !== '' && is_email($from_settings)) {
            return $from_settings;
        }

        $main = trim((string) Settings::get('admin_email', ''));
        if ($main !== '' && is_email($main)) {
            return $main;
        }

        $wp = trim((string) get_option('admin_email', ''));

        return is_email($wp) ? $wp : '';
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

    /**
     * Register WordPress actions that dispatch domain events to email templates / {@see wp_mail()} flows.
     *
     * Kept alongside the sender so transactional behavior stays discoverable outside {@see \Sikshya\Core\Plugin}.
     */
    public static function registerHookListeners(self $mailer): void
    {
        CustomEmailTemplateHookDispatcher::register($mailer);

        add_action(
            'sikshya_course_qa_question_posted',
            static function ($learner_user_id, $course_id, $content_id, $comment_id) use ($mailer): void {
                $lid = absint($learner_user_id);
                $cid = absint($course_id);
                $xp = absint($content_id);
                $com = absint($comment_id);
                if ($cid <= 0 || $xp <= 0 || $com <= 0) {
                    return;
                }
                $mailer->sendInstructorQaQuestionPostedEmail($lid, $cid, $xp, $com);
            },
            10,
            4
        );

        add_action(
            'sikshya_order_fulfilled',
            static function ($order_id, $order) use ($mailer): void {
                $oid = (int) $order_id;
                if ($oid > 0) {
                    $mailer->sendPaymentReceiptForOrder($oid, $order);
                }
            },
            12,
            2
        );
    }
}
