<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\CertificateRepository;
use Sikshya\Certificates\CertificateRenderer;

/**
 * Creates rows in sikshya_certificates when a learner completes a course.
 *
 * @package Sikshya\Services
 */
final class CertificateIssuanceService
{
    private CertificateRepository $certificates;

    public function __construct(?CertificateRepository $certificates = null)
    {
        $this->certificates = $certificates ?? new CertificateRepository();
    }

    /**
     * Published certificate template posts for Course Builder selects (id => title).
     *
     * @return array<string, string>
     */
    public static function getPublishedCertificateTemplateChoices(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::CERTIFICATE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'no_found_rows' => true,
            ]
        );

        $out = [];
        foreach ($posts as $p) {
            if (!$p instanceof \WP_Post) {
                continue;
            }
            $id = (int) $p->ID;
            if ($id <= 0) {
                continue;
            }
            $title = trim((string) $p->post_title);
            if ($title === '') {
                $title = sprintf(
                    /* translators: %d: certificate template post ID */
                    __('Template #%d', 'sikshya'),
                    $id
                );
            }
            $out[(string) $id] = $title;
        }

        return $out;
    }

    /**
     * Map legacy `_sikshya_certificate_template` slugs (and custom) to a concrete template post id for the builder UI.
     */
    public static function normalizeBuilderCertificateTemplateValue(int $course_id, string $stored): string
    {
        $course_id = absint($course_id);
        $stored = trim($stored);
        if ($course_id <= 0) {
            return '';
        }

        if ($stored !== '' && ctype_digit($stored)) {
            $pid = absint($stored);
            if (
                $pid > 0
                && get_post_type($pid) === PostTypes::CERTIFICATE
                && get_post_status($pid) === 'publish'
            ) {
                return (string) $pid;
            }
        }

        $slug = sanitize_key($stored);
        if ($slug === 'custom') {
            $cid = absint(get_post_meta($course_id, '_sikshya_certificate', true));
            if (
                $cid > 0
                && get_post_type($cid) === PostTypes::CERTIFICATE
                && get_post_status($cid) === 'publish'
            ) {
                return (string) $cid;
            }

            return '';
        }

        if ($slug === '' || $slug === 'default' || in_array($slug, ['classic', 'modern'], true)) {
            $key = ($slug === '' || $slug === 'default' || $slug === 'classic') ? 'classic' : 'modern';
            $pid = self::findPublishedTemplateIdByDefaultKey($key);

            return $pid > 0 ? (string) $pid : '';
        }

        return '';
    }

    /**
     * @return int Template post id or 0
     */
    private static function findPublishedTemplateIdByDefaultKey(string $key): int
    {
        static $cache = [];
        if (isset($cache[$key])) {
            return (int) $cache[$key];
        }

        $q = new \WP_Query(
            [
                'post_type' => PostTypes::CERTIFICATE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sikshya_certificate_default_key',
                        'value' => $key,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $id = isset($q->posts[0]) ? (int) $q->posts[0] : 0;
        $cache[$key] = $id > 0 ? $id : 0;

        return (int) $cache[$key];
    }

    /**
     * Resolve the certificate template post id for a course from course meta.
     *
     * - Numeric `_sikshya_certificate_template`: that published `sikshya_certificate` post (Course Builder).
     * - `custom`: `_sikshya_certificate` template post id (legacy).
     * - `default` / `classic` / `modern` / empty: seeded defaults via `_sikshya_certificate_default_key`.
     */
    private function resolveTemplatePostIdForCourse(int $course_id): int
    {
        $course_id = absint($course_id);
        if ($course_id <= 0) {
            return 0;
        }

        $raw = get_post_meta($course_id, '_sikshya_certificate_template', true);
        $raw_str = is_scalar($raw) ? trim((string) $raw) : '';

        if ($raw_str !== '' && ctype_digit($raw_str)) {
            $pid = absint($raw_str);
            if ($pid > 0 && get_post_type($pid) === PostTypes::CERTIFICATE && get_post_status($pid) === 'publish') {
                return $pid;
            }
        }

        $choice = sanitize_key($raw_str);
        if ($choice === '') {
            $choice = 'default';
        }

        if ($choice === 'custom') {
            $custom_id = absint(get_post_meta($course_id, '_sikshya_certificate', true));
            return $custom_id > 0 ? $custom_id : 0;
        }

        $key = $choice === 'default' ? 'classic' : $choice;
        if (!in_array($key, ['classic', 'modern'], true)) {
            $key = 'classic';
        }

        return self::findPublishedTemplateIdByDefaultKey($key);
    }

    public function certificatesEnabled(): bool
    {
        $prefixed = get_option(Settings::PREFIX . 'enable_certificates', null);
        if ($prefixed !== null && $prefixed !== '') {
            return Settings::isTruthy($prefixed);
        }

        $settings = Settings::getRaw('sikshya_course_settings', []);
        if (is_array($settings) && array_key_exists('enable_certificates', $settings)) {
            return !empty($settings['enable_certificates']);
        }

        return true;
    }

    /**
     * Whether automatic issuance on course completion is enabled (Global Settings → Certificates).
     */
    private function autoIssueOnCompletionEnabled(): bool
    {
        $prefixed = get_option(Settings::PREFIX . 'auto_generate_certificates', null);
        if ($prefixed !== null && $prefixed !== '') {
            return Settings::isTruthy($prefixed);
        }

        return true;
    }

    /**
     * MySQL datetime for certificate expiry, or null when credentials do not expire.
     */
    private function resolveExpiryDate(): ?string
    {
        $days = (int) Settings::get('certificate_expiry_days', 0);
        if ($days <= 0) {
            return null;
        }

        $issued_ts = strtotime(current_time('mysql'));
        if ($issued_ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $issued_ts + $days * DAY_IN_SECONDS);
    }

    /**
     * Issue certificate if settings allow and none active exists.
     */
    public function issueIfEnabled(int $user_id, int $course_id): ?int
    {
        if (!$this->certificatesEnabled()) {
            return null;
        }

        if (!$this->autoIssueOnCompletionEnabled()) {
            return null;
        }

        $existing = $this->certificates->findByUserAndCourse($user_id, $course_id);
        if ($existing && $existing->status === 'active') {
            return (int) $existing->id;
        }

        $template_post_id = $this->resolveTemplatePostIdForCourse($course_id);

        $number = sprintf('SK-%d-%d-%s', $user_id, $course_id, gmdate('Ymd'));
        // 64-char URL-safe verification hash (hex): good for QR + verifiable links.
        // 32 random bytes -> 64 hex chars.
        $verification = '';
        try {
            $verification = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            // Fallback: still URL-safe, but keep length stable.
            $verification = bin2hex(openssl_random_pseudo_bytes(32) ?: random_bytes(32));
        }

        $id = $this->certificates->create(
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'certificate_number' => $number,
                'issued_date' => current_time('mysql'),
                'expiry_date' => $this->resolveExpiryDate(),
                'status' => 'active',
                'download_url' => '',
                'certificate_data' => [
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'issued_at' => current_time('mysql'),
                ],
                'template_post_id' => $template_post_id > 0 ? $template_post_id : null,
                'verification_code' => $verification,
            ]
        );

        if ($id > 0) {
            // Always prefer the current permalink system for verification/document URLs.
            // This prevents UI/config toggles from storing "legacy" query-style links when pretty permalinks are enabled.
            $this->certificates->update($id, ['download_url' => CertificateRenderer::publicUrlForHash($verification)]);

            /**
             * After a certificate row is stored (Pro may attach download URLs, QR assets, etc.).
             *
             * @param int $id Certificate row id.
             * @param int $user_id Learner.
             * @param int $course_id Course.
             * @param string $certificate_number Human-readable serial.
             * @param string $verification_code Secret verification token.
             * @param int $template_post_id Certificate template post (0 if none).
             */
            do_action('sikshya_certificate_row_created', $id, $user_id, $course_id, $number, $verification, $template_post_id > 0 ? $template_post_id : 0);
        }

        return $id > 0 ? $id : null;
    }
}
