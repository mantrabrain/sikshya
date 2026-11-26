<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

use Sikshya\Frontend\Site\UserCourseStateCache;
use Sikshya\Frontend\Theme\TemplateLoader;

/**
 * Server renderer for sikshya/course-grid.
 *
 * Distinct from the older sikshya/courses block, which is a thin wrapper around
 * the `[sikshya_courses]` shortcode and therefore inherits the archive's fixed
 * markup and brand colours. This block:
 *
 * - emits `get_block_wrapper_attributes()`, so align, colour, spacing, border
 *   and typography supports declared in block.json actually reach the DOM
 *   (the old block dropped them all);
 * - draws its colours from `--wp--preset--*` with the Sikshya tokens only as
 *   fallbacks, so it looks like part of the active theme instead of like a
 *   plugin;
 * - gives the CTA `wp-element-button`, which is the class block themes style,
 *   so the button matches every other button on the site;
 * - renders each card through a theme-overridable partial
 *   (`partials/block-course-card.php`).
 *
 * The old block is untouched and still registered: existing posts keep
 * rendering exactly as they did.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseGridBlock
{
    public const NAME = 'sikshya/course-grid';

    /**
     * Query arg used for this block's pagination.
     *
     * Not `paged`: a grid inside a page must not fight the main query, and two
     * grids on one page each get their own arg via the block's queryId.
     */
    public const PAGE_ARG = 'sikshya_courses';

    /**
     * @param array<string, mixed> $attributes
     * @param string               $content
     * @param mixed                $block
     */
    public static function render(array $attributes = [], string $content = '', $block = null): string
    {
        unset($content, $block);

        $a = CourseGridAttributes::normalize($attributes);
        $page = self::currentPage($a);
        $query = new \WP_Query(CourseGridQuery::args($a, $page));

        $posts = $query->posts;
        $course_ids = [];
        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $course_ids[] = (int) $post->ID;
            }
        }

        /*
         * One enrolment/progress read for the whole page instead of one per
         * card: the card asks `sikshya_is_user_enrolled_in_course()` for every
         * course, and that routes through this cache.
         */
        $user_id = get_current_user_id();
        if ($user_id > 0 && $course_ids !== []) {
            UserCourseStateCache::warm($user_id, $course_ids);
        }

        /*
         * Lesson/quiz counts are transient-cached per course, and transients
         * are not autoloaded: without this the page paid one option read per
         * card (12 cards measured 45 queries against 17 for 3).
         */
        if (!empty($a['showMeta']) && $course_ids !== []) {
            \Sikshya\Services\CourseCurriculumCountPrimer::prime($course_ids);
        }

        $classes = self::wrapperClasses($a);
        $styles = self::wrapperStyles($a);

        $wrapper = get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
            'style' => $styles,
        ]);

        ob_start();

        echo '<div ' . $wrapper . '>';

        if ($course_ids === []) {
            echo self::emptyState($a); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
        } else {
            printf(
                '<ul class="sikshya-cg__list" role="list" data-columns="%d">',
                (int) $a['columns']
            );

            foreach ($posts as $post) {
                if (!$post instanceof \WP_Post) {
                    continue;
                }

                echo '<li class="sikshya-cg__item">';
                TemplateLoader::part('partials/block-course-card.php', [
                    'course' => $post,
                    'settings' => $a,
                    'card_data' => CourseCardData::forCourse((int) $post->ID, $a),
                ]);
                echo '</li>';
            }

            echo '</ul>';

            if (!empty($a['pagination'])) {
                echo self::pagination($query, $a, $page); // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links output.
            }
        }

        echo '</div>';

        /*
         * wp_reset_postdata() is deliberately absent: nothing above calls
         * the_post(), so the global $post was never moved. Resetting here
         * would clobber an outer loop (a grid inside a query loop block).
         */

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $a
     * @return string[]
     */
    private static function wrapperClasses(array $a): array
    {
        $classes = [
            'sikshya-cg',
            'sikshya-cg--' . sanitize_html_class((string) $a['layout']),
            'sikshya-cg--style-' . sanitize_html_class((string) $a['cardStyle']),
            'sikshya-cg--ratio-' . sanitize_html_class((string) $a['aspectRatio']),
        ];

        // The list layout reserves a column for the image; with thumbnails off
        // that column would be an empty gutter, so the CSS needs to know.
        if (empty($a['showThumbnail'])) {
            $classes[] = 'sikshya-cg--no-media';
        }

        // `sik-f-scope` is what the design-system stylesheet keys its
        // primitives off; without it the cards lose their type scale.
        $classes[] = 'sik-f-scope';

        return $classes;
    }

    /**
     * Column counts travel as custom properties, not as a class per count:
     * one rule in CSS, and a theme can override a single property.
     *
     * @param array<string, mixed> $a
     */
    private static function wrapperStyles(array $a): string
    {
        return sprintf(
            '--sikshya-grid-columns:%d;--sikshya-grid-columns-tablet:%d;--sikshya-grid-columns-mobile:%d;',
            (int) $a['columns'],
            (int) $a['columnsTablet'],
            (int) $a['columnsMobile']
        );
    }

    /**
     * @param array<string, mixed> $a
     */
    private static function currentPage(array $a): int
    {
        if (empty($a['pagination'])) {
            return 1;
        }

        $arg = self::pageArg($a);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
        $raw = isset($_GET[$arg]) ? $_GET[$arg] : 1;

        $page = is_numeric($raw) ? (int) $raw : 1;

        return max(1, min(10000, $page));
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function pageArg(array $a): string
    {
        $id = (int) ($a['queryId'] ?? 0);

        return $id > 0 ? self::PAGE_ARG . '_' . $id : self::PAGE_ARG;
    }

    /**
     * @param array<string, mixed> $a
     */
    private static function pagination(\WP_Query $query, array $a, int $page): string
    {
        $total = (int) $query->max_num_pages;
        if ($total < 2) {
            return '';
        }

        $arg = self::pageArg($a);
        $links = paginate_links([
            'base' => add_query_arg($arg, '%#%'),
            'format' => '',
            'current' => $page,
            'total' => $total,
            'type' => 'list',
            'prev_text' => __('&laquo; Previous', 'sikshya'),
            'next_text' => __('Next &raquo;', 'sikshya'),
        ]);

        if (!is_string($links) || $links === '') {
            return '';
        }

        return '<nav class="sikshya-cg__pagination sikshya-pagination" aria-label="'
            . esc_attr__('Course pagination', 'sikshya') . '">' . $links . '</nav>';
    }

    /**
     * @param array<string, mixed> $a
     */
    private static function emptyState(array $a): string
    {
        $label_courses = function_exists('sikshya_label_plural')
            ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend')
            : __('Courses', 'sikshya');

        $message = (string) ($a['emptyMessage'] ?? '');
        if ($message === '') {
            $message = sprintf(
                /* translators: %s: plural course label (e.g. courses) */
                __('No %s found.', 'sikshya'),
                function_exists('mb_strtolower') ? mb_strtolower($label_courses) : strtolower($label_courses)
            );
        }

        return '<p class="sikshya-cg__empty">' . esc_html($message) . '</p>';
    }
}
