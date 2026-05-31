<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Database\Repositories\CertificateRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * Request-scoped cache for (user × course) state used by archive cards.
 *
 * The course-archive template renders dozens of cards per page, and each card
 * asks three questions about the current learner: "are they enrolled?", "did
 * they complete this?", "is there a certificate they can download?". Each
 * question hits a custom Sikshya table — none of them are batched by
 * WordPress' built-in caches the way `WP_Query::update_meta_cache()` batches
 * post meta. The pre-existing template helpers had per-function `static`
 * caches that warmed lazily, which meant the *first* render of each card
 * still fired its own query — 24 cards × 3 queries = 72 queries on a typical
 * logged-in catalogue page.
 *
 * This class flips the pattern: the archive controller calls `warm()` with
 * all course IDs about to be rendered, we run TWO batched queries (one for
 * enrolments, one for certificates), and every subsequent card lookup is a
 * memory hit.
 *
 * The helpers in `template-functions.php` consult this cache first and fall
 * back to a per-row query if the cache hasn't been warmed — so behaviour is
 * unchanged for callers that don't warm (e.g., a single-course page or an
 * admin tool that asks once and is done).
 *
 * @package Sikshya\Frontend\Site
 */
final class UserCourseStateCache
{
    /** @var array<string, ?object> Enrollment row per `"<uid>:<cid>"` (null = not enrolled). */
    private static array $enrolment = [];

    /** @var array<string, ?object> Certificate row per `"<uid>:<cid>"` (null = none issued). */
    private static array $certificate = [];

    /**
     * Batch-fetch enrolment + certificate rows for `$user_id × $course_ids`.
     * Safe to call multiple times — already-warmed entries are not refetched.
     *
     * @param int[] $course_ids
     */
    public static function warm(int $user_id, array $course_ids): void
    {
        if ($user_id <= 0 || $course_ids === []) {
            return;
        }
        // Normalise + dedupe + drop any IDs we already have cached for this
        // user — re-running the warmer between paginated chunks shouldn't
        // re-query rows we already loaded.
        $ids = array_values(array_unique(array_filter(array_map('intval', $course_ids), static fn (int $i): bool => $i > 0)));
        $missing_enrolment = [];
        $missing_certificate = [];
        foreach ($ids as $cid) {
            $key = $user_id . ':' . $cid;
            if (!array_key_exists($key, self::$enrolment)) {
                $missing_enrolment[] = $cid;
            }
            if (!array_key_exists($key, self::$certificate)) {
                $missing_certificate[] = $cid;
            }
        }

        if ($missing_enrolment !== []) {
            self::loadEnrolments($user_id, $missing_enrolment);
        }
        if ($missing_certificate !== []) {
            self::loadCertificates($user_id, $missing_certificate);
        }
    }

    /**
     * @return ?object Row or null if no enrolment exists. Callers can read
     *                 `->status`, `->progress`, `->completed_date`, etc.
     */
    public static function getEnrollment(int $user_id, int $course_id): ?object
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return null;
        }
        $key = $user_id . ':' . $course_id;
        if (array_key_exists($key, self::$enrolment)) {
            return self::$enrolment[$key];
        }
        // Cache miss: do a single-row fetch and store. Same shape callers
        // pre-warmer used to hit, so we maintain backward compatibility.
        $repo = new EnrollmentRepository();
        $row = $repo->findByUserAndCourse($user_id, $course_id);
        self::$enrolment[$key] = $row ?: null;
        return self::$enrolment[$key];
    }

    /** Convenience: enrolled at any non-empty status. */
    public static function isEnrolled(int $user_id, int $course_id): bool
    {
        $row = self::getEnrollment($user_id, $course_id);
        return $row !== null;
    }

    /** Convenience: enrolled AND status is completed. */
    public static function isCompleted(int $user_id, int $course_id): bool
    {
        $row = self::getEnrollment($user_id, $course_id);
        return $row !== null && isset($row->status) && (string) $row->status === 'completed';
    }

    /**
     * Certificate download URL for `(user, course)`, or empty string if none.
     * Returns empty string also when the "students can download" setting is
     * disabled site-wide — matches the legacy helper's behaviour.
     */
    public static function certificateDownloadUrl(int $user_id, int $course_id): string
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return '';
        }
        if (class_exists(\Sikshya\Services\Settings::class)
            && !\Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('students_can_download_certificates', '1'))
        ) {
            return '';
        }
        $key = $user_id . ':' . $course_id;
        if (!array_key_exists($key, self::$certificate)) {
            $repo = new CertificateRepository();
            $row = $repo->findByUserAndCourse($user_id, $course_id);
            self::$certificate[$key] = $row ?: null;
        }
        $row = self::$certificate[$key];
        return $row !== null && isset($row->download_url) ? (string) $row->download_url : '';
    }

    /** Test-only: reset between requests (and useful for PHPUnit). */
    public static function flush(): void
    {
        self::$enrolment = [];
        self::$certificate = [];
    }

    /**
     * One batched SELECT for all `$course_ids`. Stores nulls for course IDs
     * with no row so subsequent lookups don't re-query.
     *
     * @param int[] $course_ids
     */
    private static function loadEnrolments(int $user_id, array $course_ids): void
    {
        global $wpdb;
        $repo = new EnrollmentRepository();
        // We need the underlying table name. EnrollmentRepository doesn't
        // expose it publicly today; do a raw query via the documented table
        // alias instead.
        $table = $wpdb->prefix . 'sikshya_enrollments';
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $sql = "SELECT * FROM {$table} WHERE user_id = %d AND course_id IN ({$placeholders})";
        $params = array_merge([$user_id], $course_ids);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Seed all requested IDs with `null` first so missing rows count as
        // "not enrolled" without firing a fallback query later.
        foreach ($course_ids as $cid) {
            self::$enrolment[$user_id . ':' . (int) $cid] = null;
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $cid = (int) ($row->course_id ?? 0);
                if ($cid > 0) {
                    self::$enrolment[$user_id . ':' . $cid] = $row;
                }
            }
        }
        unset($repo);
    }

    /**
     * One batched SELECT for all `$course_ids`. Same null-seeding pattern.
     *
     * @param int[] $course_ids
     */
    private static function loadCertificates(int $user_id, array $course_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_certificates';
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        // Only "active" certs are surfaced as downloadable — matches
        // `CertificateRepository::findByUserAndCourse` semantics.
        $sql = "SELECT * FROM {$table}
                WHERE user_id = %d AND course_id IN ({$placeholders}) AND status = 'active'";
        $params = array_merge([$user_id], $course_ids);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        foreach ($course_ids as $cid) {
            self::$certificate[$user_id . ':' . (int) $cid] = null;
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $cid = (int) ($row->course_id ?? 0);
                if ($cid > 0) {
                    self::$certificate[$user_id . ':' . $cid] = $row;
                }
            }
        }
    }
}
