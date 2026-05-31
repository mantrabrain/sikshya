<?php

namespace Sikshya\Privacy;

/**
 * GDPR personal-data eraser for Sikshya.
 *
 * Registers callbacks via the `wp_privacy_personal_data_erasers` filter so a
 * site admin running Tools → Erase Personal Data wipes a learner's Sikshya
 * footprint without leaving orphans or breaking aggregate analytics.
 *
 * Strategy per resource:
 *
 *   - Learner-private content (delete entirely): progress rows, quiz attempts
 *     + per-question items, assignment submissions, profile / billing /
 *     instructor-application user meta, personal lesson notes.
 *
 *   - Financial / audit records (anonymise + retain): orders + order items,
 *     payments, coupon redemptions, certificates. Most jurisdictions require
 *     financial records be kept for years (tax, refund disputes, accounting
 *     audits). We reset `user_id` to 0, scrub identifying meta (billing
 *     snapshot, gateway response), but leave amount/currency/transaction-id
 *     so reconciliation still works. WP surfaces these as "items retained"
 *     with a message explaining why.
 *
 *   - Reviews (anonymise + retain): rating is aggregate data; review text is
 *     personal. Scrub the text + reset user_id, keep the rating + course_id.
 *
 *   - Enrolments: delete. They have no audit value once the orders that
 *     created them are anonymised.
 *
 * @package Sikshya\Privacy
 */
final class PersonalDataEraser
{
    public static function register(): void
    {
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'registerErasers']);
    }

    /**
     * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers
     * @return array<string, array{eraser_friendly_name: string, callback: callable}>
     */
    public static function registerErasers(array $erasers): array
    {
        $erasers['sikshya-profile'] = [
            'eraser_friendly_name' => __('Sikshya — Profile & preferences', 'sikshya'),
            'callback' => [self::class, 'eraseProfile'],
        ];
        $erasers['sikshya-enrollments'] = [
            'eraser_friendly_name' => __('Sikshya — Course enrolments', 'sikshya'),
            'callback' => [self::class, 'eraseEnrollments'],
        ];
        $erasers['sikshya-orders'] = [
            'eraser_friendly_name' => __('Sikshya — Orders', 'sikshya'),
            'callback' => [self::class, 'eraseOrders'],
        ];
        $erasers['sikshya-payments'] = [
            'eraser_friendly_name' => __('Sikshya — Payments', 'sikshya'),
            'callback' => [self::class, 'erasePayments'],
        ];
        $erasers['sikshya-coupon-redemptions'] = [
            'eraser_friendly_name' => __('Sikshya — Coupon redemptions', 'sikshya'),
            'callback' => [self::class, 'eraseCouponRedemptions'],
        ];
        $erasers['sikshya-progress'] = [
            'eraser_friendly_name' => __('Sikshya — Lesson & quiz progress', 'sikshya'),
            'callback' => [self::class, 'eraseProgress'],
        ];
        $erasers['sikshya-quiz-attempts'] = [
            'eraser_friendly_name' => __('Sikshya — Quiz attempts', 'sikshya'),
            'callback' => [self::class, 'eraseQuizAttempts'],
        ];
        $erasers['sikshya-assignments'] = [
            'eraser_friendly_name' => __('Sikshya — Assignment submissions', 'sikshya'),
            'callback' => [self::class, 'eraseAssignments'],
        ];
        $erasers['sikshya-certificates'] = [
            'eraser_friendly_name' => __('Sikshya — Certificates', 'sikshya'),
            'callback' => [self::class, 'eraseCertificates'],
        ];
        $erasers['sikshya-reviews'] = [
            'eraser_friendly_name' => __('Sikshya — Course reviews', 'sikshya'),
            'callback' => [self::class, 'eraseReviews'],
        ];
        return $erasers;
    }

    private static function resolveUserId(string $email): int
    {
        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    private static function emptyResponse(): array
    {
        return [
            'items_removed' => 0,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Profile meta (DELETE) ────────────────

    public static function eraseProfile(string $email, int $page = 1): array
    {
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }

        // Same allowlist as the exporter — these are the keys the learner owns.
        $delete_keys = [
            'sikshya_user_phone',
            'sikshya_user_location',
            'sikshya_user_facebook',
            'sikshya_user_twitter',
            'sikshya_user_linkedin',
            'sikshya_user_instagram',
            'sikshya_avatar_attachment_id',
            'sikshya_cart_course_ids',
            'sikshya_cart_bundle_id',
            '_sikshya_billing_phone',
            '_sikshya_billing_address_1',
            '_sikshya_billing_address_2',
            '_sikshya_billing_city',
            '_sikshya_billing_state',
            '_sikshya_billing_postcode',
            '_sikshya_billing_country',
            '_sikshya_instructor_application',
            '_sikshya_instructor_status',
            '_sikshya_instructor_applied_at',
            '_sikshya_learn_notes',
            '_sikshya_completed_lessons',
            '_sikshya_enrolled_courses',
        ];

        $removed = 0;
        foreach ($delete_keys as $key) {
            // Returns false if no row existed — we don't count those.
            if (delete_user_meta($uid, $key)) {
                ++$removed;
            }
        }

        return [
            'items_removed' => $removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Enrolments (DELETE) ───────────────────

    public static function eraseEnrollments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_enrollments';
        $removed = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d", $uid));
        return [
            'items_removed' => $removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Orders (ANONYMISE + RETAIN) ──────────

    public static function eraseOrders(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_orders';
        // Scrub user_id + billing snapshot. Keep amount/currency/gateway so
        // financial reconciliation, tax reporting, and refund disputes still
        // work even after the learner is erased.
        $retained = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET user_id = 0, meta = NULL WHERE user_id = %d",
            $uid
        ));
        $messages = [];
        if ($retained > 0) {
            $messages[] = __(
                'Sikshya orders were retained but stripped of personal data (user link removed, billing snapshot cleared). Amounts and dates are kept for financial record-keeping required by tax / audit rules.',
                'sikshya'
            );
        }
        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    // ───────────────────────────── Payments (ANONYMISE + RETAIN) ────────

    public static function erasePayments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_payments';
        // Scrub user link + raw gateway_response (often contains the payer's
        // email or address again). Keep amount / currency / transaction_id
        // so refunds can still be reconciled with the gateway.
        $retained = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET user_id = 0, gateway_response = NULL WHERE user_id = %d",
            $uid
        ));
        $messages = [];
        if ($retained > 0) {
            $messages[] = __(
                'Sikshya payment rows were retained but stripped of personal data (user link removed, gateway response cleared). Transaction IDs are kept so refunds and disputes can still be reconciled with the payment gateway.',
                'sikshya'
            );
        }
        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    // ───────────────────────────── Coupon redemptions (ANONYMISE) ───────

    public static function eraseCouponRedemptions(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_coupon_redemptions';
        // Coupon redemption counts feed the cap enforcement we patched
        // earlier (`incrementUsedCount` race fix). Resetting user_id to 0
        // keeps the count integrity without naming the user.
        $retained = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET user_id = 0 WHERE user_id = %d",
            $uid
        ));
        $messages = [];
        if ($retained > 0) {
            $messages[] = __(
                'Sikshya coupon redemption rows were retained but stripped of user link. Counts are kept so coupon usage limits remain enforceable.',
                'sikshya'
            );
        }
        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    // ───────────────────────────── Progress (DELETE) ────────────────────

    public static function eraseProgress(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_progress';
        $removed = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d", $uid));
        return [
            'items_removed' => $removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Quiz attempts (DELETE) ───────────────

    public static function eraseQuizAttempts(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $attempts_table = $wpdb->prefix . 'sikshya_quiz_attempts';
        $items_table = $wpdb->prefix . 'sikshya_quiz_attempt_items';

        // Get attempt IDs first so we can delete the per-question item rows
        // that reference them (foreign-key style; the table has no real FK
        // constraint but the rows would be orphaned without this).
        $attempt_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$attempts_table} WHERE user_id = %d",
            $uid
        ));
        $items_removed = 0;
        if (is_array($attempt_ids) && $attempt_ids !== []) {
            $ids = array_map('intval', $attempt_ids);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $items_removed = (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM {$items_table} WHERE attempt_id IN ({$placeholders})",
                $ids
            ));
        }
        $attempts_removed = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$attempts_table} WHERE user_id = %d",
            $uid
        ));
        return [
            'items_removed' => $attempts_removed + $items_removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Assignment submissions (DELETE + files) ─

    public static function eraseAssignments(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_assignment_submissions';

        // Best-effort: also delete the uploaded attachments. Submissions store
        // attachment IDs in a comma-separated string. We force-delete each
        // attachment (`force_delete = true`) so the underlying file is gone.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT attachment_ids FROM {$table} WHERE user_id = %d",
            $uid
        ));
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ids_raw = (string) ($r->attachment_ids ?? '');
                if ($ids_raw === '') {
                    continue;
                }
                $ids = array_filter(array_map('intval', explode(',', $ids_raw)), static fn (int $i): bool => $i > 0);
                foreach ($ids as $aid) {
                    wp_delete_attachment($aid, true);
                }
            }
        }
        $removed = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d", $uid));
        return [
            'items_removed' => $removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    // ───────────────────────────── Certificates (ANONYMISE + RETAIN) ────

    public static function eraseCertificates(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_certificates';
        // Certificates may be referenced by external verifiers (employers,
        // accreditation bodies) via the verification_code. Hard-deleting
        // would break those legitimate verification flows. Anonymise the
        // user link + the JSON payload that may contain the learner's name.
        $retained = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET user_id = 0, certificate_data = NULL, status = 'revoked' WHERE user_id = %d",
            $uid
        ));
        $messages = [];
        if ($retained > 0) {
            $messages[] = __(
                'Sikshya certificates were retained but marked revoked and stripped of personal data. The verification code is preserved so any third party that previously verified this certificate sees a "revoked" status rather than a 404.',
                'sikshya'
            );
        }
        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    // ───────────────────────────── Reviews (ANONYMISE + RETAIN) ─────────

    public static function eraseReviews(string $email, int $page = 1): array
    {
        global $wpdb;
        $uid = self::resolveUserId($email);
        if ($uid <= 0 || $page > 1) {
            return self::emptyResponse();
        }
        $table = $wpdb->prefix . 'sikshya_reviews';

        // Erase as REVIEW AUTHOR: keep the row (rating contributes to course
        // average), null the user_id + review_text.
        $retained_author = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET user_id = 0, review_text = NULL WHERE user_id = %d",
            $uid
        ));
        // Erase as REPLY AUTHOR (instructor who replied): keep the row, null
        // the reply user link + reply text.
        $retained_reply = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET reply_user_id = 0, reply_text = NULL, reply_created_at = NULL WHERE reply_user_id = %d",
            $uid
        ));
        $retained = $retained_author + $retained_reply;
        $messages = [];
        if ($retained > 0) {
            $messages[] = __(
                'Sikshya reviews were retained but stripped of personal text and user link. Star ratings are kept because they contribute to the public course rating average.',
                'sikshya'
            );
        }
        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }
}
