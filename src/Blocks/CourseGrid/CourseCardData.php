<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

use Sikshya\Constants\Taxonomies;

/**
 * Everything one course-grid card needs, gathered once.
 *
 * The partial is markup only. Keeping the reads here means a theme that
 * overrides `partials/block-course-card.php` inherits the same data — and it
 * means the reads can be counted in a test, which is how the per-card cost of
 * this block stays pinned.
 *
 * Only the pieces the block was configured to show are fetched: a grid with
 * ratings and instructors turned off does no rating or user lookup at all.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseCardData
{
    /**
     * @param array<string, mixed> $settings Normalised block attributes.
     * @return array<string, mixed>
     */
    public static function forCourse(int $course_id, array $settings): array
    {
        $permalink = get_permalink($course_id);

        $data = [
            'id' => $course_id,
            'permalink' => is_string($permalink) ? $permalink : '',
            'title' => get_the_title($course_id),
            'thumbnail_id' => 0,
            'categories' => [],
            'excerpt' => '',
            'instructor' => null,
            'rating' => ['average' => 0.0, 'count' => 0],
            'pricing' => null,
            'difficulty' => '',
            'duration' => '',
            'counts' => ['lessons' => 0, 'quizzes' => 0, 'assignments' => 0, 'total' => 0],
            'is_enrolled' => false,
            'is_completed' => false,
            'progress' => null,
        ];

        if (!empty($settings['showThumbnail'])) {
            $data['thumbnail_id'] = (int) get_post_thumbnail_id($course_id);
        }

        if (!empty($settings['showCategory'])) {
            $terms = get_the_terms($course_id, Taxonomies::COURSE_CATEGORY);
            if (is_array($terms)) {
                $data['categories'] = array_slice($terms, 0, 2);
            }
        }

        if (!empty($settings['showExcerpt'])) {
            $data['excerpt'] = self::excerpt($course_id, (int) ($settings['excerptLength'] ?? 18));
        }

        if (!empty($settings['showInstructor'])) {
            $post = get_post($course_id);
            $author_id = $post instanceof \WP_Post ? (int) $post->post_author : 0;
            if ($author_id > 0) {
                $user = get_userdata($author_id);
                $data['instructor'] = $user instanceof \WP_User ? $user : null;
            }
        }

        if (!empty($settings['showRating'])) {
            /**
             * Same filter the archive card uses, so a rating source added for
             * one listing (Sikshya Pro's reviews) shows up in both.
             *
             * @param array{average: float, count: int}|mixed $vm
             * @param int                                     $course_id
             */
            $vm = apply_filters(
                'sikshya_course_card_rating_vm',
                ['average' => 0.0, 'count' => 0],
                $course_id
            );
            $data['rating'] = [
                'average' => max(0.0, min(5.0, is_array($vm) ? (float) ($vm['average'] ?? 0) : 0.0)),
                'count' => max(0, is_array($vm) ? (int) ($vm['count'] ?? 0) : 0),
            ];
        }

        if (!empty($settings['showPrice']) || !empty($settings['showButton'])) {
            // The CTA wording depends on price (Enrol / Buy), so the button
            // needs pricing even when the price itself is hidden.
            $data['pricing'] = function_exists('sikshya_get_course_pricing')
                ? sikshya_get_course_pricing($course_id)
                : ['price' => null, 'sale_price' => null, 'currency' => 'USD', 'effective' => null, 'on_sale' => false];
        }

        if (!empty($settings['showDifficulty'])) {
            $data['difficulty'] = (string) (function_exists('sikshya_first_nonempty_post_meta')
                ? sikshya_first_nonempty_post_meta(
                    $course_id,
                    ['_sikshya_difficulty', '_sikshya_course_difficulty', 'sikshya_course_level']
                )
                : get_post_meta($course_id, '_sikshya_difficulty', true));
        }

        if (!empty($settings['showMeta'])) {
            if (function_exists('sikshya_get_course_catalog_duration_display')) {
                $data['duration'] = (string) sikshya_get_course_catalog_duration_display($course_id);
            }
            if (function_exists('sikshya_get_course_curriculum_counts')) {
                $counts = sikshya_get_course_curriculum_counts($course_id);
                if (is_array($counts)) {
                    $data['counts'] = array_merge($data['counts'], $counts);
                }
            }
        }

        $data['is_enrolled'] = function_exists('sikshya_is_user_enrolled_in_course')
            && sikshya_is_user_enrolled_in_course($course_id);

        if ($data['is_enrolled']) {
            $data['is_completed'] = function_exists('sikshya_is_user_completed_course')
                && sikshya_is_user_completed_course($course_id);

            if (!empty($settings['showProgress'])) {
                $data['progress'] = self::progressPercent($course_id);
            }
        }

        $data['learn_url'] = function_exists('sikshya_course_learn_entry_url')
            ? (string) sikshya_course_learn_entry_url($course_id)
            : $data['permalink'];

        /**
         * Final card view model.
         *
         * @param array<string, mixed> $data
         * @param int                  $course_id
         * @param array<string, mixed> $settings
         */
        $data = apply_filters('sikshya_course_grid_card_data', $data, $course_id, $settings);

        return is_array($data) ? $data : [];
    }

    /**
     * Enrolment progress as a 0–100 integer, or null when unknown.
     */
    private static function progressPercent(int $course_id): ?int
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return null;
        }

        // Read from the warmed request cache; no extra query per card.
        $enrollment = \Sikshya\Frontend\Site\UserCourseStateCache::getEnrollment($user_id, $course_id);
        if (!is_object($enrollment) || !isset($enrollment->progress)) {
            return null;
        }

        $progress = (float) $enrollment->progress;

        return (int) round(max(0.0, min(100.0, $progress)));
    }

    private static function excerpt(int $course_id, int $words): string
    {
        $post = get_post($course_id);
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $raw = (string) $post->post_excerpt;
        if ($raw === '') {
            $raw = (string) $post->post_content;
            // Strip shortcodes before trimming: a course whose description
            // opens with a shortcode produced an excerpt of raw markup.
            $raw = strip_shortcodes($raw);
            $raw = excerpt_remove_blocks($raw);
        }

        $raw = wp_strip_all_tags($raw, true);
        if (trim($raw) === '') {
            return '';
        }

        return wp_trim_words($raw, max(4, $words), '…');
    }
}
