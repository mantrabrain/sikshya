<?php

namespace Sikshya\Services;

/**
 * Built-in transactional email definitions (merge with per-site overrides in EmailTemplateStore).
 *
 * @package Sikshya\Services
 */
final class EmailTemplateCatalog
{
    /**
     * Shared layout: banner, body area, footer strip (looks good in preview + clients).
     *
     * @param string $inner_html Already-escaped where needed; may contain merge tags.
     */
    private static function layout(string $emoji, string $heading, string $inner_html): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;margin:0 auto;">'
            . '<tr><td style="background:linear-gradient(135deg,#0d9488 0%,#0f766e 100%);padding:28px 24px;text-align:center;">'
            . '<div style="font-size:32px;line-height:1;">' . $emoji . '</div>'
            . '<h1 style="margin:12px 0 0;font-family:Georgia,serif;color:#ffffff;font-size:24px;font-weight:600;">' . $heading . '</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 24px;background:#ffffff;font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif;font-size:15px;line-height:1.65;color:#334155;">'
            . $inner_html
            . '</td></tr>'
            . '<tr><td style="padding:16px 24px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;text-align:center;">'
            . esc_html__('Sent by', 'sikshya') . ' <strong>{{site_name}}</strong>'
            . '</td></tr>'
            . '</table>';
    }

    /**
     * Simple key/value table for order-style facts.
     *
     * @param list<array{label: string, value: string}> $rows
     */
    private static function factTable(array $rows): string
    {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">';
        foreach ($rows as $r) {
            $html .= '<tr>'
                . '<td style="padding:12px 16px;background:#f8fafc;font-size:11px;font-weight:700;letter-spacing:0.05em;color:#64748b;text-transform:uppercase;width:38%;">' . $r['label'] . '</td>'
                . '<td style="padding:12px 16px;background:#ffffff;font-size:14px;font-weight:600;color:#0f172a;">' . $r['value'] . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $tags_common = [
            '{{site_name}}',
            '{{site_url}}',
            '{{student_name}}',
            '{{student_email}}',
            '{{learner_name}}',
            '{{learner_email}}',
            '{{instructor_name}}',
            '{{instructor_email}}',
            '{{admin_email}}',
            '{{course_title}}',
            '{{course_url}}',
        ];

        $tags_cert = array_merge($tags_common, ['{{certificate_url}}', '{{certificate_number}}']);

        $defs = [
            'learner_enrollment' => [
                'name' => __('Enrollment confirmation', 'sikshya'),
                'description' => __('Sent to the student when they enroll in a course.', 'sikshya'),
                'event' => 'sikshya_user_enrolled',
                'category' => 'enrollment',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('🎓 [{{site_name}}] You’re in! Welcome to {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '🎓',
                    esc_html__('You’re enrolled', 'sikshya'),
                    '<p style="margin:0 0 16px;font-size:16px;">' . sprintf(
                        /* translators: %s student first name or display name */
                        esc_html__('Hello %s,', 'sikshya'),
                        '{{student_name}}'
                    ) . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('Great news — your seat is confirmed. You now have full access to the course materials below.', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                        ['label' => esc_html__('Your account', 'sikshya'), 'value' => '{{student_email}}'],
                    ])
                    . '<p style="margin:20px 0 0;">' . esc_html__('When you’re ready, jump in and start with the first lesson.', 'sikshya') . '</p>'
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{course_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Start learning →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'admin_new_enrollment' => [
                'name' => __('New enrollment (admin notice)', 'sikshya'),
                'description' => __('Sent to the admin address when a student enrolls in a course.', 'sikshya'),
                'event' => 'sikshya_user_enrolled',
                'category' => 'enrollment',
                'recipient' => 'admin',
                'recipient_to' => '{{admin_email}}',
                'template_type' => 'system',
                'default_subject' => __('📬 [{{site_name}}] New enrollment · {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '📬',
                    esc_html__('New enrollment', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Someone just joined a course on your site. Here’s a quick summary:', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Student', 'sikshya'), 'value' => '{{student_name}} — {{student_email}}'],
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                    ])
                    . '<p style="margin:20px 0 0;">'
                    . '<a href="{{course_url}}" style="color:#0d9488;font-weight:600;">' . esc_html__('Open course →', 'sikshya') . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'instructor_new_enrollment' => [
                'name' => __('New enrollment (instructor notice)', 'sikshya'),
                'description' => __('Sent to the course lead instructor when a student enrolls.', 'sikshya'),
                'event' => 'sikshya_user_enrolled',
                'category' => 'enrollment',
                'recipient' => 'instructor',
                'recipient_to' => '{{instructor_email}}',
                'template_type' => 'system',
                'default_subject' => __('✨ [{{site_name}}] New student in {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '✨',
                    esc_html__('New student in your course', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{instructor_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('A new learner just enrolled. You may want to welcome them or check your roster.', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Student', 'sikshya'), 'value' => '{{student_name}}'],
                        ['label' => esc_html__('Email', 'sikshya'), 'value' => '{{student_email}}'],
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                    ])
                    . '<p style="margin:20px 0 0;">'
                    . '<a href="{{course_url}}" style="color:#0d9488;font-weight:600;">' . esc_html__('View course →', 'sikshya') . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'instructor_qa_question' => [
                'name' => __('New Q&A question (instructor)', 'sikshya'),
                'description' => __(
                    'Sent when a learner posts a new question in Course Q&A (Community discussions). Customize subject and HTML here.',
                    'sikshya'
                ),
                'required_addon' => 'community_discussions',
                'required_feature' => 'community_discussions',
                'event' => 'sikshya_course_qa_question_posted',
                'category' => 'courses',
                'recipient' => 'instructor',
                'recipient_to' => '{{instructor_email}}',
                'template_type' => 'system',
                'default_subject' => __('💬 [{{site_name}}] New question · {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '💬',
                    esc_html__('New learner question', 'sikshya'),
                    '<p style="margin:0 0 16px;color:#475569;font-size:15px;">' . esc_html__('Hi {{instructor_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__(
                        'A learner just posted something in Q&A for one of your courses. Here is what they wrote:',
                        'sikshya'
                    ) . '</p>'
                    . '<div style="margin:18px 0;padding:14px 16px;background:#fefce8;border:1px solid #fde047;border-radius:12px;color:#78350f;font-size:14px;line-height:1.55;white-space:pre-wrap;">'
                    . '{{qa_question_preview}}</div>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                        ['label' => esc_html__('Lesson / quiz', 'sikshya'), 'value' => '{{content_title}}'],
                        ['label' => esc_html__('From', 'sikshya'), 'value' => '{{student_name}} · {{student_email}}'],
                    ])
                    . '<p style="margin:24px 0 12px;text-align:center;">'
                    . '<a href="{{qa_review_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Review & reply in context →', 'sikshya')
                    . '</a></p>'
                    . '<p style="margin:0;font-size:13px;color:#64748b;">' . esc_html__(
                        'If the button doesn’t work, paste this URL into your browser:',
                        'sikshya'
                    ) . ' <span style="word-break:break-all;">{{qa_review_url}}</span></p>'
                ),
                'merge_tags' => array_merge(
                    $tags_common,
                    ['{{qa_question_preview}}', '{{qa_review_url}}', '{{content_title}}', '{{content_url}}']
                ),
            ],
            'learner_course_completed' => [
                'name' => __('Course completed', 'sikshya'),
                'description' => __('Sent when a student completes a course.', 'sikshya'),
                'event' => 'sikshya_course_completed',
                'category' => 'completion',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('🏆 [{{site_name}}] You completed {{course_title}}!', 'sikshya'),
                'default_body_html' => self::layout(
                    '🏆',
                    esc_html__('Course completed', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hello {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('Outstanding work — you’ve finished every requirement for this course. Celebrate this milestone!', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                    ])
                    . '<p style="margin:20px 0 0;text-align:center;">'
                    . '<a href="{{course_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Review course →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'learner_certificate_issued' => [
                'name' => __('Certificate issued', 'sikshya'),
                'description' => __('Sent when a certificate is generated for the student.', 'sikshya'),
                'event' => 'sikshya_certificate_issued',
                'category' => 'certificate',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('📜 [{{site_name}}] Your certificate for {{course_title}} is ready', 'sikshya'),
                'default_body_html' => self::layout(
                    '📜',
                    esc_html__('Your certificate is ready', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('Your credential has been issued. Save or share it using the link below.', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                        ['label' => esc_html__('Certificate #', 'sikshya'), 'value' => '{{certificate_number}}'],
                    ])
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{certificate_url}}" style="display:inline-block;padding:14px 28px;background:#7c3aed;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('View certificate →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => $tags_cert,
            ],
            'learner_welcome' => [
                'name' => __('Welcome (new account)', 'sikshya'),
                'description' => __('Sent when a new user account is created (if welcome emails are enabled).', 'sikshya'),
                'event' => 'user_register',
                'category' => 'account',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('👋 [{{site_name}}] Welcome, {{student_name}}!', 'sikshya'),
                'default_body_html' => self::layout(
                    '👋',
                    esc_html__('Welcome aboard', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('Thanks for creating an account. We’re glad you’re here — explore courses, track progress, and learn at your own pace.', 'sikshya') . '</p>'
                    . '<p style="margin:0;text-align:center;">'
                    . '<a href="{{site_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Go to site →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => [
                    '{{site_name}}',
                    '{{site_url}}',
                    '{{student_name}}',
                    '{{student_email}}',
                    '{{learner_name}}',
                    '{{learner_email}}',
                    '{{admin_email}}',
                ],
            ],
            'learner_progress_reminder' => [
                'name' => __('Progress reminder', 'sikshya'),
                'description' => __('Generic nudge to continue a course (automation hooks, not drip unlock).', 'sikshya'),
                'event' => 'sikshya.scheduled_reminder',
                'category' => 'automation',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('⏰ [{{site_name}}] Keep going on {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '⏰',
                    esc_html__('Friendly reminder', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('You’re making progress — open your course and pick up where you left off.', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                    ])
                    . '<p style="margin:20px 0 0;text-align:center;">'
                    . '<a href="{{course_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Resume learning →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'learner_payment_receipt' => [
                'name' => __('Payment receipt', 'sikshya'),
                'description' => __(
                    'Sent to the buyer when an order is paid (checkout, manual mark paid, etc.). Turn off here to stop receipt emails without affecting enrollment.',
                    'sikshya'
                ),
                'event' => 'sikshya_order_fulfilled',
                'category' => 'commerce',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('🧾 [{{site_name}}] Payment received — order #{{order_id}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '🧾',
                    esc_html__('Thank you for your payment', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__(
                        'We received your payment. Below is a summary of your order. You can open the full receipt anytime from your account.',
                        'sikshya'
                    ) . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Order', 'sikshya'), 'value' => '#' . '{{order_id}}'],
                        ['label' => esc_html__('Payment method', 'sikshya'), 'value' => '{{payment_method}}'],
                        ['label' => esc_html__('Subtotal', 'sikshya'), 'value' => '{{order_subtotal}}'],
                        ['label' => esc_html__('Discount', 'sikshya'), 'value' => '{{order_discount}}'],
                        ['label' => esc_html__('Total paid', 'sikshya'), 'value' => '{{order_total}}'],
                    ])
                    . '<p style="margin:18px 0 8px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">'
                    . esc_html__('Items', 'sikshya')
                    . '</p>'
                    . '{{order_lines_html}}'
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{order_receipt_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('View order receipt →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => array_merge(
                    $tags_common,
                    [
                        '{{order_id}}',
                        '{{order_total}}',
                        '{{order_subtotal}}',
                        '{{order_discount}}',
                        '{{order_currency}}',
                        '{{payment_method}}',
                        '{{order_lines_html}}',
                        '{{order_receipt_url}}',
                    ]
                ),
            ],
            'drip_lesson_unlocked' => [
                'name' => __('Drip: lesson unlocked', 'sikshya'),
                'description' => __(
                    'Sent when Content drip opens a lesson for a learner. Enable/disable sending here; Content drip is controlled on the course.',
                    'sikshya'
                ),
                'required_addon' => 'drip_notifications',
                'required_feature' => 'drip_notifications',
                'event' => 'sikshya_drip_lesson_unlocked',
                'category' => 'automation',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('🔓 [{{site_name}}] New lesson available in {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '🔓',
                    esc_html__('Lesson unlocked', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__('A new lesson is now available in your course.', 'sikshya') . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                        ['label' => esc_html__('Lesson', 'sikshya'), 'value' => '{{lesson_title}}'],
                    ])
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{lesson_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Open lesson →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => array_merge($tags_common, ['{{lesson_title}}', '{{lesson_url}}']),
            ],
            'drip_course_unlocked' => [
                'name' => __('Drip: course schedule unlocked', 'sikshya'),
                'description' => __(
                    'Sent when a course-wide drip schedule opens the curriculum (no specific lesson). Enable/disable sending here; drip rules stay on the course.',
                    'sikshya'
                ),
                'required_addon' => 'drip_notifications',
                'required_feature' => 'drip_notifications',
                'event' => 'sikshya_drip_course_unlocked',
                'category' => 'automation',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('📅 [{{site_name}}] Your course schedule is now open: {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '📅',
                    esc_html__('Course access opened', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 20px;">' . esc_html__(
                        'Your scheduled access for this course is now active — you can open lessons, quizzes, and assignments according to your schedule.',
                        'sikshya'
                    ) . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                    ])
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{course_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Go to course →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => $tags_common,
            ],
            'drip_lessons_unlocked_digest' => [
                'name' => __('Drip: lessons unlocked (digest)', 'sikshya'),
                'description' => __(
                    'Sent when multiple lessons unlock in one drip cron pass (digest mode). Useful for reducing email noise when several lessons unlock at once.',
                    'sikshya'
                ),
                'required_addon' => 'drip_notifications',
                'required_feature' => 'drip_notifications',
                'event' => 'sikshya_drip_lessons_unlocked',
                'category' => 'automation',
                'recipient' => 'student',
                'recipient_to' => '{{student_email}}',
                'template_type' => 'system',
                'default_subject' => __('🔓 [{{site_name}}] {{lessons_count}} new lessons unlocked in {{course_title}}', 'sikshya'),
                'default_body_html' => self::layout(
                    '🔓',
                    esc_html__('Lessons unlocked', 'sikshya'),
                    '<p style="margin:0 0 16px;">' . esc_html__('Hi {{student_name}},', 'sikshya') . '</p>'
                    . '<p style="margin:0 0 16px;">' . esc_html__(
                        'New lessons are now available in your course. Here’s what just opened:',
                        'sikshya'
                    ) . '</p>'
                    . self::factTable([
                        ['label' => esc_html__('Course', 'sikshya'), 'value' => '{{course_title}}'],
                        ['label' => esc_html__('Unlocked', 'sikshya'), 'value' => '{{lessons_count}} ' . esc_html__('lessons', 'sikshya')],
                    ])
                    . '{{lessons_list_html}}'
                    . '<p style="margin:24px 0 0;text-align:center;">'
                    . '<a href="{{course_url}}" style="display:inline-block;padding:14px 28px;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
                    . esc_html__('Continue learning →', 'sikshya')
                    . '</a></p>'
                ),
                'merge_tags' => array_merge($tags_common, ['{{lessons_count}}', '{{lessons_list_html}}', '{{first_lesson_url}}']),
            ],
        ];

        /**
         * Catalog rows may include `required_addon` and `required_feature` for add-on gating in admin and REST.
         *
         */
        return apply_filters('sikshya_email_template_catalog_definitions', $defs);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $id): ?array
    {
        $all = self::definitions();

        return $all[ $id ] ?? null;
    }
}
