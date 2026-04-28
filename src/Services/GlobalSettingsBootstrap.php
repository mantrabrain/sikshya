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

    /**
     * @param int $length
     * @param int $user_id
     * @param bool $remember
     * @return int
     */
    public static function filterAuthCookieExpiration($length, $user_id, $remember)
    {
        unset($remember, $user_id);
        $mins = (int) Settings::get('session_timeout', 120);
        if ($mins < 15) {
            $mins = 15;
        }
        if ($mins > 1440) {
            $mins = 1440;
        }
        $custom = $mins * 60;

        return (int) min((int) $length, $custom);
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
                $course_id = (int) get_post_meta($post_id, '_sikshya_lesson_course', true);
                if ($course_id <= 0) {
                    $course_id = (int) get_post_meta($post_id, '_sikshya_quiz_course', true);
                }
                if ($course_id <= 0) {
                    $course_id = (int) get_post_meta($post_id, '_sikshya_assignment_course', true);
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
