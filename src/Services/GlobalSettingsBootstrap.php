<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

/**
 * Wires Sikshya Global Settings (React / {@see \Sikshya\Admin\Settings\SettingsManager}) into runtime behavior.
 *
 * @package Sikshya\Services
 */
final class GlobalSettingsBootstrap
{
    public static function register(): void
    {
        add_filter('upload_size_limit', [self::class, 'filterUploadSizeLimit'], 20);
        add_filter('document_title_parts', [self::class, 'filterDocumentTitleParts'], 20);
        add_action('wp_head', [self::class, 'outputHeadMetaAndTags'], 2);
        add_filter('auth_cookie_expiration', [self::class, 'filterAuthCookieExpiration'], 20, 3);
        // Keeps the idle timeout honest: an active session is extended, so only
        // an idle one runs out. See extendSessionWhileActive().
        add_action('init', [self::class, 'extendSessionWhileActive'], 20);
        add_action('save_post', [self::class, 'maybeInvalidateCurriculumCache'], 20, 2);
    }

    /**
     * Cap WordPress upload size to the LMS “Largest upload size (MB)” setting (never raises host limit).
     *
     * @param int $bytes
     * @return int
     */
    public static function filterUploadSizeLimit($bytes)
    {
        $mb = (int) Settings::get('max_file_size', 10);
        if ($mb < 1) {
            $mb = 1;
        }
        if ($mb > 100) {
            $mb = 100;
        }
        $cap = $mb * 1024 * 1024;

        return (int) min((int) $bytes, $cap);
    }

    /**
     * @param array<string, string> $title
     * @return array<string, string>
     */
    public static function filterDocumentTitleParts(array $title): array
    {
        if (!self::isSikshyaPublicSurface()) {
            return $title;
        }
        $name = trim((string) Settings::get('site_title', ''));
        if ($name !== '') {
            $title['site'] = $name;
        }

        return $title;
    }

    public static function outputHeadMetaAndTags(): void
    {
        if (is_admin() || !self::isSikshyaPublicSurface()) {
            return;
        }
        $desc = trim((string) Settings::get('site_description', ''));
        if ($desc !== '') {
            echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($desc)) . "\" />\n";
        }

        $ga = trim((string) Settings::get('google_analytics_id', ''));
        if ($ga !== '' && preg_match('/^G-[A-Z0-9]+$/', $ga)) {
            echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga) . '"></script>' . "\n";
            echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . esc_js($ga) . '");</script>' . "\n";
        }

        $fb = trim((string) Settings::get('facebook_pixel_id', ''));
        if ($fb !== '' && preg_match('/^[0-9]{8,20}$/', $fb)) {
            echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc_js($fb) . "');fbq('track','PageView');</script>\n";
            echo '<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . esc_attr($fb) . '&amp;ev=PageView&amp;noscript=1" alt="" /></noscript>' . "\n";
        }
    }

    /** Lower and upper bounds for the idle timeout, in minutes. */
    private const SESSION_MIN_MINUTES = 15;
    private const SESSION_MAX_MINUTES = 1440;

    /**
     * The configured idle timeout, in seconds.
     */
    public static function sessionTimeoutSeconds(): int
    {
        $mins = (int) Settings::get('session_timeout', 120);
        $mins = max(self::SESSION_MIN_MINUTES, min(self::SESSION_MAX_MINUTES, $mins));

        return $mins * 60;
    }

    /**
     * Shorten the login cookie to the configured idle timeout.
     *
     * Only for an ordinary login. A "Remember Me" login is an explicit request
     * for a long-lived session, and WordPress promises fourteen days for it;
     * shortening that to the idle timeout — which this filter used to do, by
     * discarding `$remember` — broke that promise with nothing in the UI to
     * explain why the site owner kept being signed out.
     *
     * @param int $length
     * @param int $user_id
     * @param bool $remember
     * @return int
     */
    public static function filterAuthCookieExpiration($length, $user_id, $remember)
    {
        unset($user_id);

        if ($remember) {
            return (int) $length;
        }

        return (int) min((int) $length, self::sessionTimeoutSeconds());
    }

    /**
     * Extend the current session while the user is active.
     *
     * The setting is labelled "log out inactive users after", and until this
     * existed it did no such thing: it capped the absolute life of the login
     * cookie, so somebody halfway through marking a batch of assignments was
     * signed out mid-task at the two-hour mark whatever they were doing. An
     * idle timeout that logs out busy people is both the wrong behaviour and
     * the opposite of what its own label promises.
     *
     * So an authenticated, cookie-based request past the halfway point of its
     * window re-issues the cookie. Activity keeps a session alive; silence
     * still ends it on schedule. The old cookie's token stays valid until it
     * expires, so other tabs and in-flight requests are unaffected.
     */
    public static function extendSessionWhileActive(): void
    {
        // A cookie cannot be set once output has begun. Kept separate from the
        // decision below so the decision stays testable: under PHPUnit output
        // has always started, so a check in there would make every test of the
        // happy path pass for the wrong reason.
        if (headers_sent()) {
            return;
        }

        if (!self::shouldExtendSession()) {
            return;
        }

        wp_set_auth_cookie(get_current_user_id(), false);
    }

    /**
     * Whether the current request should have its session extended.
     */
    public static function shouldExtendSession(): bool
    {
        if (wp_doing_cron()) {
            return false;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        // Cookie sessions only. A request authenticated by an application
        // password or a bearer token has no cookie to extend, and re-issuing
        // one would hand it a browser session it never asked for.
        if (!isset($_COOKIE[LOGGED_IN_COOKIE])) {
            return false;
        }

        $expiration = self::cookieExpiration((string) $_COOKIE[LOGGED_IN_COOKIE]);
        if ($expiration <= 0) {
            return false;
        }

        $window = self::sessionTimeoutSeconds();
        $remaining = $expiration - time();

        // Nothing to do while there is plenty left. Anything longer than the
        // window is a remembered session, which is not ours to shorten.
        if ($remaining <= 0 || $remaining > (int) floor($window / 2)) {
            return false;
        }

        /**
         * Whether to extend this session because the user is active.
         *
         * @param bool $extend
         * @param int $user_id
         * @param int $remaining
         */
        return (bool) apply_filters('sikshya_extend_active_session', true, $user_id, $remaining);
    }

    /**
     * The expiry timestamp inside a WordPress auth cookie, or 0 if unreadable.
     */
    private static function cookieExpiration(string $cookie): int
    {
        $parts = explode('|', $cookie);

        // `username|expiration|token|hmac`. Anything else is not ours to read.
        if (count($parts) !== 4) {
            return 0;
        }

        return (int) $parts[1];
    }

    /**
     * @param int $post_id
     */
    public static function maybeInvalidateCurriculumCache($post_id, $post): void
    {
        if (!Settings::isTruthy(Settings::get('cache_enabled', '0'))) {
            return;
        }
        if (!$post instanceof \WP_Post) {
            return;
        }
        $types = [PostTypes::COURSE, PostTypes::CHAPTER, PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT];
        if (!in_array($post->post_type, $types, true)) {
            return;
        }
        $course_id = (int) $post->ID;
        if ($post->post_type !== PostTypes::COURSE) {
            if ($post->post_type === PostTypes::CHAPTER) {
                $course_id = (int) $post->post_parent;
            } else {
                if ($post->post_type === PostTypes::LESSON) {
                    $course_id = LessonCourseLink::resolvedCourseIdForLesson($post_id);
                } elseif ($post->post_type === PostTypes::QUIZ) {
                    $course_id = LessonCourseLink::resolvedCourseIdForQuiz($post_id);
                } elseif ($post->post_type === PostTypes::ASSIGNMENT) {
                    $course_id = LessonCourseLink::resolvedCourseIdForAssignment($post_id);
                }
            }
        }
        if ($course_id > 0) {
            delete_transient('sikshya_cache_curriculum_' . $course_id);
        }
    }

    public static function isSikshyaPublicSurface(): bool
    {
        if (is_admin()) {
            return false;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }
        if (get_query_var('sikshya_page')) {
            return true;
        }
        if (is_singular()) {
            $pt = get_post_type();
            if (is_string($pt) && strpos($pt, 'sikshya_') === 0) {
                return true;
            }
        }
        if (is_post_type_archive(PostTypes::COURSE)) {
            return true;
        }
        if (is_tax(Taxonomies::COURSE_CATEGORY) || is_tax(Taxonomies::COURSE_TAG)) {
            return true;
        }

        return false;
    }
}
