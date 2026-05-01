<?php

namespace Sikshya\Frontend\Site;

/**
 * Prompts learners to rate a course after completion.
 *
 * Free plugin provides the prompt + links; Pro course_reviews add-on can hook
 * into the deep link query args to prefill the rating UX.
 *
 * @package Sikshya\Frontend\Site
 */
final class CourseRatingPrompt
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('sikshya_course_completed', [self::class, 'markPending'], 10, 2);
        add_action('sikshya_learn_after_hero', [self::class, 'renderLearnPrompt'], 10, 2);
        add_action('sikshya_account_dashboard_after_hero', [self::class, 'renderAccountPrompt'], 20, 1);
    }

    public static function markPending(int $user_id, int $course_id): void
    {
        $user_id = (int) $user_id;
        $course_id = (int) $course_id;
        if ($user_id <= 0 || $course_id <= 0) {
            return;
        }

        if (!self::isRatingsEnabledForCourse($course_id)) {
            return;
        }

        $key = self::metaKey($course_id);
        update_user_meta($user_id, $key, '1');
    }

    private static function metaKey(int $course_id): string
    {
        return '_sikshya_pending_course_rating_' . (int) $course_id;
    }

    private static function isRatingsEnabledForCourse(int $course_id): bool
    {
        /**
         * Pro course_reviews add-on should return true here when ratings are enabled.
         *
         * @param bool $enabled
         * @param int  $course_id
         */
        return (bool) apply_filters('sikshya_course_ratings_enabled', false, (int) $course_id);
    }

    private static function dismissIfRequested(int $user_id, int $course_id): void
    {
        $want = isset($_GET['sikshya_dismiss_rate_course']) ? (int) $_GET['sikshya_dismiss_rate_course'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($want <= 0 || $want !== $course_id) {
            return;
        }
        delete_user_meta($user_id, self::metaKey($course_id));
    }

    private static function renderPromptBlock(int $course_id, string $context_url): void
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) {
            return;
        }
        $course_url = get_permalink($course_id);
        $course_url = is_string($course_url) ? $course_url : '';
        if ($course_url === '') {
            return;
        }

        $dismiss_url = add_query_arg('sikshya_dismiss_rate_course', (string) $course_id, $context_url);
        $dismiss_url = is_string($dismiss_url) ? $dismiss_url : $context_url;

        echo '<section class="sikshya-rateCourse" aria-label="' . esc_attr__('Rate this course', 'sikshya') . '">';
        echo '<div class="sikshya-rateCourse__left">';
        echo '<div class="sikshya-rateCourse__title">' . esc_html__('How was this course?', 'sikshya') . '</div>';
        echo '<div class="sikshya-rateCourse__subtitle">' . esc_html__('Tap a star to leave a rating.', 'sikshya') . '</div>';
        echo '</div>';

        echo '<div class="sikshya-rateCourse__stars" role="group" aria-label="' . esc_attr__('Choose a rating', 'sikshya') . '">';
        for ($i = 1; $i <= 5; $i++) {
            $u = add_query_arg(
                [
                    'sikshya_rate' => (string) $i,
                ],
                $course_url
            );
            $u = is_string($u) ? $u : $course_url;
            $u .= '#sikshya-reviews';
            echo '<a class="sikshya-rateCourse__star" href="' . esc_url($u) . '" aria-label="' . esc_attr(sprintf(__('%d star', 'sikshya'), $i)) . '">★</a>';
        }
        echo '</div>';

        echo '<a class="sikshya-rateCourse__dismiss" href="' . esc_url($dismiss_url) . '">' . esc_html__('Not now', 'sikshya') . '</a>';
        echo '</section>';
    }

    /**
     * @param array<string, mixed> $legacy
     * @param \Sikshya\Presentation\Models\LearnPageModel $page_model
     */
    public static function renderLearnPrompt($legacy, $page_model): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0 || !is_object($page_model) || !method_exists($page_model, 'getCourseId')) {
            return;
        }
        $course_id = (int) $page_model->getCourseId();
        if ($course_id <= 0 || !self::isRatingsEnabledForCourse($course_id)) {
            return;
        }

        self::dismissIfRequested($user_id, $course_id);

        $pending = (string) get_user_meta($user_id, self::metaKey($course_id), true);
        if ($pending !== '1') {
            return;
        }

        $context_url = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $context_url = $context_url !== '' ? home_url($context_url) : home_url('/');
        self::renderPromptBlock($course_id, $context_url);
    }

    /**
     * Shows a single “Rate your latest completed course” prompt.
     *
     * @param array<string, mixed> $acc
     */
    public static function renderAccountPrompt($acc): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        // Find one pending course id (fast scan: user meta is small).
        $all = get_user_meta($user_id);
        if (!is_array($all) || $all === []) {
            return;
        }

        $course_id = 0;
        foreach ($all as $k => $vals) {
            $k = (string) $k;
            if (strpos($k, '_sikshya_pending_course_rating_') !== 0) {
                continue;
            }
            $cid = (int) str_replace('_sikshya_pending_course_rating_', '', $k);
            if ($cid > 0 && self::isRatingsEnabledForCourse($cid)) {
                $course_id = $cid;
                break;
            }
        }
        if ($course_id <= 0) {
            return;
        }

        self::dismissIfRequested($user_id, $course_id);
        $pending = (string) get_user_meta($user_id, self::metaKey($course_id), true);
        if ($pending !== '1') {
            return;
        }

        $context_url = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $context_url = $context_url !== '' ? home_url($context_url) : home_url('/');
        self::renderPromptBlock($course_id, $context_url);
    }
}

