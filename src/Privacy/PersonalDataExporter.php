<?php

namespace Sikshya\Privacy;

/**
 * GDPR personal-data exporter for Sikshya.
 *
 * Registers callbacks via the `wp_privacy_personal_data_exporters` filter so a
 * site admin running Tools → Export Personal Data receives a complete archive
 * of every Sikshya row touching a learner's email.
 *
 * Groups (one entry per repository concern):
 *   1. sikshya-profile          — sikshya_* user meta (billing, social, learn notes)
 *   2. sikshya-enrollments      — sikshya_enrollments rows
 *   3. sikshya-orders           — sikshya_orders + sikshya_order_items
 *   4. sikshya-payments         — sikshya_payments
 *   5. sikshya-coupon-redemptions — sikshya_coupon_redemptions
 *   6. sikshya-progress         — sikshya_progress
 *   7. sikshya-quiz-attempts    — sikshya_quiz_attempts (+ items as JSON blob)
 *   8. sikshya-assignments      — sikshya_assignment_submissions
 *   9. sikshya-certificates     — sikshya_certificates
 *   10. sikshya-reviews         — sikshya_reviews (+ replies)
 *
 * Pagination: WP passes `$page` (1-based) — for typical learners the dataset
 * is well under WP's recommended ~500 items, so each exporter returns
 * `done = true` after the first page. If a customer reports needing pagination
 * on a power-user account, add a `LIMIT/OFFSET` per exporter.
 *
 * @package Sikshya\Privacy
 */
final class PersonalDataExporter
{
    public static function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'registerExporters']);
    }

    /**
     * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters
     * @return array<string, array{exporter_friendly_name: string, callback: callable}>
     */
    public static function registerExporters(array $exporters): array
    {
        $exporters['sikshya-profile'] = [
            'exporter_friendly_name' => __('Sikshya — Profile & preferences', 'sikshya'),
            'callback' => [self::class, 'exportProfile'],
        ];
        $exporters['sikshya-enrollments'] = [
            'exporter_friendly_name' => __('Sikshya — Course enrolments', 'sikshya'),
            'callback' => [self::class, 'exportEnrollments'],
        ];
        $exporters['sikshya-orders'] = [
            'exporter_friendly_name' => __('Sikshya — Orders', 'sikshya'),
            'callback' => [self::class, 'exportOrders'],
        ];
        $exporters['sikshya-payments'] = [
            'exporter_friendly_name' => __('Sikshya — Payments', 'sikshya'),
            'callback' => [self::class, 'exportPayments'],
        ];
        $exporters['sikshya-coupon-redemptions'] = [
            'exporter_friendly_name' => __('Sikshya — Coupon redemptions', 'sikshya'),
            'callback' => [self::class, 'exportCouponRedemptions'],
        ];
        $exporters['sikshya-progress'] = [
            'exporter_friendly_name' => __('Sikshya — Lesson & quiz progress', 'sikshya'),
            'callback' => [self::class, 'exportProgress'],
        ];
        $exporters['sikshya-quiz-attempts'] = [
            'exporter_friendly_name' => __('Sikshya — Quiz attempts', 'sikshya'),
            'callback' => [self::class, 'exportQuizAttempts'],
        ];
        $exporters['sikshya-assignments'] = [
            'exporter_friendly_name' => __('Sikshya — Assignment submissions', 'sikshya'),
            'callback' => [self::class, 'exportAssignments'],
        ];
        $exporters['sikshya-certificates'] = [
            'exporter_friendly_name' => __('Sikshya — Certificates', 'sikshya'),
            'callback' => [self::class, 'exportCertificates'],
        ];
        $exporters['sikshya-reviews'] = [
            'exporter_friendly_name' => __('Sikshya — Course reviews', 'sikshya'),
            'callback' => [self::class, 'exportReviews'],
        ];
        return $exporters;
    }

    /**
     * Group constants for export rows — kept in one place so the eraser can
     * reference the same labels in its retain messages.
     */
    private const GROUP_LABELS = [
        'sikshya-profile' => 'Sikshya — Profile & preferences',
        'sikshya-enrollments' => 'Sikshya — Course enrolments',
        'sikshya-orders' => 'Sikshya — Orders',
        'sikshya-payments' => 'Sikshya — Payments',
        'sikshya-coupon-redemptions' => 'Sikshya — Coupon redemptions',
        'sikshya-progress' => 'Sikshya — Lesson & quiz progress',
        'sikshya-quiz-attempts' => 'Sikshya — Quiz attempts',
        'sikshya-assignments' => 'Sikshya — Assignment submissions',
        'sikshya-certificates' => 'Sikshya — Certificates',
        'sikshya-reviews' => 'Sikshya — Course reviews',
    ];

    /**
     * Resolve email → user_id. Centralised so every exporter handles a missing
     * account uniformly (return empty + done).
     */
    private static function resolveUserId(string $email): int
    {
        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    private static function emptyResponse(): array
    {
        return ['data' => [], 'done' => true];
    }

    /**
     * Build a single WP export row. `data` is an array of label/value pairs
     * the exporter rendering layer presents as a definition list.
     *
     * @param array<int, array{name: string, value: scalar|string}> $rows
     */
    private static function buildItem(string $group, string $item_id, array $rows): array
    {
        return [
            'group_id' => $group,
            'group_label' => __(self::GROUP_LABELS[$group] ?? $group, 'sikshya'),
            'item_id' => $item_id,
            'data' => $rows,
        ];
    }

    // ───────────────────────────── Profile / user meta ───────────────────

    public static function exportProfile(string $email, int $page = 1): array
    {
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }

        // Allowlist of keys to surface — matches the surface of `UserService::formatSafeMeta`
        // plus billing/instructor application keys that the user explicitly owns.
        $keys = [
            'sikshya_user_phone' => __('Phone', 'sikshya'),
            'sikshya_user_location' => __('Location', 'sikshya'),
            'sikshya_user_facebook' => __('Facebook URL', 'sikshya'),
            'sikshya_user_twitter' => __('Twitter URL', 'sikshya'),
            'sikshya_user_linkedin' => __('LinkedIn URL', 'sikshya'),
            'sikshya_user_instagram' => __('Instagram URL', 'sikshya'),
            'sikshya_avatar_attachment_id' => __('Avatar attachment ID', 'sikshya'),
            '_sikshya_billing_phone' => __('Billing phone', 'sikshya'),
            '_sikshya_billing_address_1' => __('Billing address line 1', 'sikshya'),
            '_sikshya_billing_address_2' => __('Billing address line 2', 'sikshya'),
            '_sikshya_billing_city' => __('Billing city', 'sikshya'),
            '_sikshya_billing_state' => __('Billing state / region', 'sikshya'),
            '_sikshya_billing_postcode' => __('Billing postcode', 'sikshya'),
            '_sikshya_billing_country' => __('Billing country', 'sikshya'),
            '_sikshya_instructor_application' => __('Instructor application', 'sikshya'),
            '_sikshya_instructor_status' => __('Instructor application status', 'sikshya'),
            '_sikshya_instructor_applied_at' => __('Instructor applied at', 'sikshya'),
            '_sikshya_learn_notes' => __('Personal lesson notes', 'sikshya'),
        ];

        $rows = [];
        foreach ($keys as $meta_key => $label) {
            $value = get_user_meta($uid, $meta_key, true);
            if ($value === '' || $value === null || $value === []) {
                continue;
            }
            $rows[] = ['name' => $label, 'value' => is_scalar($value) ? (string) $value : wp_json_encode($value)];
        }
        if ($rows === []) {
            return self::emptyResponse();
        }
        return [
            'data' => [self::buildItem('sikshya-profile', 'user-' . $uid . '-profile', $rows)],
            'done' => true,
        ];
    }

    // ───────────────────────────── Enrolments ────────────────────────────

    public static function exportEnrollments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_enrollments';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-enrollments', 'enrollment-' . (int) $r->id, [
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($r->status ?? '')],
                ['name' => __('Progress (%)', 'sikshya'), 'value' => (string) ($r->progress ?? '')],
                ['name' => __('Enrolled at', 'sikshya'), 'value' => (string) ($r->enrolled_date ?? '')],
                ['name' => __('Completed at', 'sikshya'), 'value' => (string) ($r->completed_date ?? '')],
                ['name' => __('Payment method', 'sikshya'), 'value' => (string) ($r->payment_method ?? '')],
                ['name' => __('Amount', 'sikshya'), 'value' => (string) ($r->amount ?? '')],
                ['name' => __('Transaction ID', 'sikshya'), 'value' => (string) ($r->transaction_id ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Orders + items ────────────────────────

    public static function exportOrders(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $orders_table = $wpdb->prefix . 'sikshya_orders';
        $items_table = $wpdb->prefix . 'sikshya_order_items';
        $orders = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$orders_table} WHERE user_id = %d", $uid));
        if (!$orders) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($orders as $o) {
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE order_id = %d", (int) $o->id));
            $rows = [
                ['name' => __('Order ID', 'sikshya'), 'value' => (string) $o->id],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($o->status ?? '')],
                ['name' => __('Currency', 'sikshya'), 'value' => (string) ($o->currency ?? '')],
                ['name' => __('Subtotal', 'sikshya'), 'value' => (string) ($o->subtotal ?? '')],
                ['name' => __('Discount', 'sikshya'), 'value' => (string) ($o->discount_total ?? '')],
                ['name' => __('Total', 'sikshya'), 'value' => (string) ($o->total ?? '')],
                ['name' => __('Gateway', 'sikshya'), 'value' => (string) ($o->gateway ?? '')],
                ['name' => __('Created at', 'sikshya'), 'value' => (string) ($o->created_at ?? '')],
            ];
            if (!empty($o->meta)) {
                $rows[] = ['name' => __('Meta (billing snapshot)', 'sikshya'), 'value' => (string) $o->meta];
            }
            if ($items) {
                $rows[] = ['name' => __('Line items', 'sikshya'), 'value' => wp_json_encode($items)];
            }
            $data[] = self::buildItem('sikshya-orders', 'order-' . (int) $o->id, $rows);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Payments ──────────────────────────────

    public static function exportPayments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_payments';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-payments', 'payment-' . (int) $r->id, [
                ['name' => __('Payment ID', 'sikshya'), 'value' => (string) $r->id],
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Amount', 'sikshya'), 'value' => (string) ($r->amount ?? '')],
                ['name' => __('Currency', 'sikshya'), 'value' => (string) ($r->currency ?? '')],
                ['name' => __('Method', 'sikshya'), 'value' => (string) ($r->payment_method ?? '')],
                ['name' => __('Transaction ID', 'sikshya'), 'value' => (string) ($r->transaction_id ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($r->status ?? '')],
                ['name' => __('Paid at', 'sikshya'), 'value' => (string) ($r->payment_date ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Coupon redemptions ────────────────────

    public static function exportCouponRedemptions(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_coupon_redemptions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-coupon-redemptions', 'redemption-' . (int) $r->id, [
                ['name' => __('Coupon ID', 'sikshya'), 'value' => (string) ($r->coupon_id ?? '')],
                ['name' => __('Order ID', 'sikshya'), 'value' => (string) ($r->order_id ?? '')],
                ['name' => __('Redeemed at', 'sikshya'), 'value' => (string) ($r->redeemed_at ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Progress ──────────────────────────────

    public static function exportProgress(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_progress';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-progress', 'progress-' . (int) $r->id, [
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Lesson / Quiz ID', 'sikshya'), 'value' => (string) ($r->lesson_id ?? $r->quiz_id ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($r->status ?? '')],
                ['name' => __('Percent', 'sikshya'), 'value' => (string) ($r->percentage ?? '')],
                ['name' => __('Time spent (s)', 'sikshya'), 'value' => (string) ($r->time_spent ?? '')],
                ['name' => __('Completed at', 'sikshya'), 'value' => (string) ($r->completed_date ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Quiz attempts ─────────────────────────

    public static function exportQuizAttempts(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $attempts_table = $wpdb->prefix . 'sikshya_quiz_attempts';
        $items_table = $wpdb->prefix . 'sikshya_quiz_attempt_items';
        $attempts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$attempts_table} WHERE user_id = %d", $uid));
        if (!$attempts) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($attempts as $a) {
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE attempt_id = %d", (int) $a->id));
            $rows = [
                ['name' => __('Attempt ID', 'sikshya'), 'value' => (string) $a->id],
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($a->course_id ?? '')],
                ['name' => __('Quiz ID', 'sikshya'), 'value' => (string) ($a->quiz_id ?? '')],
                ['name' => __('Attempt #', 'sikshya'), 'value' => (string) ($a->attempt_number ?? '')],
                ['name' => __('Score', 'sikshya'), 'value' => (string) ($a->score ?? '')],
                ['name' => __('Correct answers', 'sikshya'), 'value' => (string) ($a->correct_answers ?? '')],
                ['name' => __('Time taken (s)', 'sikshya'), 'value' => (string) ($a->time_taken ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($a->status ?? '')],
                ['name' => __('Started at', 'sikshya'), 'value' => (string) ($a->started_at ?? '')],
                ['name' => __('Completed at', 'sikshya'), 'value' => (string) ($a->completed_at ?? '')],
            ];
            if (!empty($a->answers_data)) {
                $rows[] = ['name' => __('Answers (raw)', 'sikshya'), 'value' => (string) $a->answers_data];
            }
            if ($items) {
                $rows[] = ['name' => __('Per-question answers', 'sikshya'), 'value' => wp_json_encode($items)];
            }
            $data[] = self::buildItem('sikshya-quiz-attempts', 'attempt-' . (int) $a->id, $rows);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Assignment submissions ────────────────

    public static function exportAssignments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_assignment_submissions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-assignments', 'submission-' . (int) $r->id, [
                ['name' => __('Submission ID', 'sikshya'), 'value' => (string) $r->id],
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Assignment ID', 'sikshya'), 'value' => (string) ($r->assignment_id ?? '')],
                ['name' => __('Content', 'sikshya'), 'value' => (string) ($r->content ?? '')],
                ['name' => __('Attachments (IDs)', 'sikshya'), 'value' => (string) ($r->attachment_ids ?? '')],
                ['name' => __('Grade', 'sikshya'), 'value' => (string) ($r->grade ?? '')],
                ['name' => __('Feedback', 'sikshya'), 'value' => (string) ($r->feedback ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($r->status ?? '')],
                ['name' => __('Submitted at', 'sikshya'), 'value' => (string) ($r->submitted_at ?? '')],
                ['name' => __('Graded at', 'sikshya'), 'value' => (string) ($r->graded_at ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Certificates ──────────────────────────

    public static function exportCertificates(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_certificates';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $uid));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = self::buildItem('sikshya-certificates', 'certificate-' . (int) $r->id, [
                ['name' => __('Certificate ID', 'sikshya'), 'value' => (string) $r->id],
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Certificate number', 'sikshya'), 'value' => (string) ($r->certificate_number ?? '')],
                ['name' => __('Issued at', 'sikshya'), 'value' => (string) ($r->issued_date ?? '')],
                ['name' => __('Expires at', 'sikshya'), 'value' => (string) ($r->expiry_date ?? '')],
                ['name' => __('Download URL', 'sikshya'), 'value' => (string) ($r->download_url ?? '')],
                ['name' => __('Verification code', 'sikshya'), 'value' => (string) ($r->verification_code ?? '')],
                ['name' => __('Status', 'sikshya'), 'value' => (string) ($r->status ?? '')],
            ]);
        }
        return ['data' => $data, 'done' => true];
    }

    // ───────────────────────────── Reviews ───────────────────────────────

    public static function exportReviews(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_reviews';
        // Surface BOTH reviews authored by this user AND replies authored by
        // them — instructors who replied to a learner's review have personal
        // content in `reply_text`.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d OR reply_user_id = %d",
            $uid,
            $uid
        ));
        if (!$rows) {
            return self::emptyResponse();
        }
        $data = [];
        foreach ($rows as $r) {
            $is_author = (int) ($r->user_id ?? 0) === $uid;
            $is_replier = (int) ($r->reply_user_id ?? 0) === $uid;
            $row = [
                ['name' => __('Review ID', 'sikshya'), 'value' => (string) $r->id],
                ['name' => __('Course ID', 'sikshya'), 'value' => (string) ($r->course_id ?? '')],
                ['name' => __('Role on this review', 'sikshya'), 'value' => $is_author && $is_replier ? 'author + replier' : ($is_author ? 'author' : 'replier')],
            ];
            if ($is_author) {
                $row[] = ['name' => __('Rating', 'sikshya'), 'value' => (string) ($r->rating ?? '')];
                $row[] = ['name' => __('Review text', 'sikshya'), 'value' => (string) ($r->review_text ?? '')];
            }
            if ($is_replier) {
                $row[] = ['name' => __('Reply text', 'sikshya'), 'value' => (string) ($r->reply_text ?? '')];
                $row[] = ['name' => __('Replied at', 'sikshya'), 'value' => (string) ($r->reply_created_at ?? '')];
            }
            $data[] = self::buildItem('sikshya-reviews', 'review-' . (int) $r->id, $row);
        }
        return ['data' => $data, 'done' => true];
    }
}
