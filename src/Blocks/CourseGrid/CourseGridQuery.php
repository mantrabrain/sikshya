<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Tables\EnrollmentsTable;

/**
 * Turns normalised sikshya/course-grid attributes into WP_Query arguments.
 *
 * Kept apart from the renderer so the argument shape can be tested without
 * rendering HTML, and so a site can reshape the query with one filter
 * (`sikshya_course_grid_query_args`) without reimplementing the block.
 *
 * Every path here is bounded. `perPage` is capped at
 * {@see CourseGridAttributes::MAX_PER_PAGE}, `no_found_rows` is on unless the
 * block actually paginates, and the enrolled-courses filter reads at most
 * {@see self::MAX_ENROLLED_IDS} rows — a learner with 5,000 enrolments must
 * not turn one block into a 5,000-id `post__in`.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseGridQuery
{
    /**
     * Ceiling on ids pulled from the enrolments table for one block.
     */
    public const MAX_ENROLLED_IDS = 500;

    /**
     * @param array<string, mixed> $attributes Normalised attributes.
     * @return array<string, mixed>
     */
    public static function args(array $attributes, int $page = 1): array
    {
        $a = $attributes;
        $per_page = (int) ($a['perPage'] ?? 6);
        $page = max(1, $page);

        $args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'ignore_sticky_posts' => true,
            // A grid that does not paginate never needs the row count, and
            // SQL_CALC_FOUND_ROWS is the expensive half of the query.
            'no_found_rows' => empty($a['pagination']),
            'post__not_in' => [],
            // Term and meta caches are primed in one go per page instead of
            // once per card by get_the_terms()/get_post_meta() in the card.
            'update_post_term_cache' => true,
            'update_post_meta_cache' => true,
        ];

        self::applyOrder($args, $a);
        self::applyTaxonomies($args, $a);
        self::applyAuthors($args, $a);
        self::applyPrice($args, $a);
        self::applyIncludeExclude($args, $a);

        $limited = self::applyEnrollment($args, $a);
        if ($limited === false) {
            // The filter can only match nothing (e.g. "enrolled only" for a
            // visitor who is signed out). Returning args that match nothing is
            // safer than returning args that match everything.
            $args['post__in'] = [0];
        }

        if (isset($args['post__not_in']) && $args['post__not_in'] === []) {
            unset($args['post__not_in']);
        }

        /**
         * WP_Query arguments for a sikshya/course-grid block.
         *
         * @param array<string, mixed> $args       Query arguments.
         * @param array<string, mixed> $attributes Normalised block attributes.
         * @param int                  $page       Requested page.
         */
        $args = apply_filters('sikshya_course_grid_query_args', $args, $a, $page);

        return is_array($args) ? $args : [];
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     */
    private static function applyOrder(array &$args, array $a): void
    {
        $orderby = (string) ($a['orderBy'] ?? 'date');
        $args['order'] = (string) ($a['order'] ?? 'DESC');

        switch ($orderby) {
            case 'title':
                $args['orderby'] = 'title';
                break;

            case 'menu_order':
                $args['orderby'] = ['menu_order' => $args['order'], 'date' => 'DESC'];
                break;

            case 'rand':
                $args['orderby'] = 'rand';
                unset($args['order']);
                break;

            case 'price':
                /*
                 * meta_key + meta_value_num, not a meta_query clause: a course
                 * with no price row must still appear. An INNER JOIN would drop
                 * every free course from a price-sorted grid, which is how the
                 * archive's own price sort is written (includes/course-archive-filters.php).
                 */
                $args['meta_key'] = '_sikshya_price';
                $args['orderby'] = 'meta_value_num';
                break;

            case 'popular':
                /*
                 * Enrolment count is not stored on the post, so "popular" sorts
                 * by the cached count meta when an add-on maintains it and
                 * falls back to date otherwise. Counting enrolments per course
                 * in SQL here would be one subquery per row.
                 */
                $args['meta_key'] = '_sikshya_enrolled_count';
                $args['orderby'] = ['meta_value_num' => $args['order'], 'date' => 'DESC'];
                break;

            case 'date':
            default:
                $args['orderby'] = 'date';
                break;
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     */
    private static function applyTaxonomies(array &$args, array $a): void
    {
        $tax_query = [];

        $categories = (array) ($a['categories'] ?? []);
        if ($categories !== []) {
            $tax_query[] = [
                'taxonomy' => Taxonomies::COURSE_CATEGORY,
                'field' => 'term_id',
                'terms' => $categories,
                // Children included: picking a parent category in the editor
                // should show the courses filed under its subcategories too.
                'include_children' => true,
            ];
        }

        $tags = (array) ($a['tags'] ?? []);
        if ($tags !== []) {
            $tax_query[] = [
                'taxonomy' => Taxonomies::COURSE_TAG,
                'field' => 'term_id',
                'terms' => $tags,
            ];
        }

        if ($tax_query === []) {
            return;
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $args['tax_query'] = $tax_query;
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     */
    private static function applyAuthors(array &$args, array $a): void
    {
        $instructors = (array) ($a['instructors'] ?? []);
        if ($instructors !== []) {
            $args['author__in'] = $instructors;
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     */
    private static function applyPrice(array &$args, array $a): void
    {
        $filter = (string) ($a['priceFilter'] ?? 'any');
        if ($filter === 'any') {
            return;
        }

        /*
         * "Free" is three different states in the data: course_type === 'free',
         * no price meta at all, or a price meta of 0/''. Only the meta_query
         * can express that, and NOT EXISTS over a sparse key is cheap here
         * because the post type already narrows the join.
         */
        if ($filter === 'free') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_sikshya_course_type',
                    'value' => 'free',
                    'compare' => '=',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => '_sikshya_price',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => '_sikshya_course_price',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                [
                    'key' => '_sikshya_price',
                    'value' => 0,
                    'type' => 'NUMERIC',
                    'compare' => '<=',
                ],
            ];

            return;
        }

        $args['meta_query'] = [
            'relation' => 'AND',
            [
                'key' => '_sikshya_price',
                'value' => 0,
                'type' => 'NUMERIC',
                'compare' => '>',
            ],
            [
                /*
                 * NOT EXISTS is half the test, not a nicety: `!= 'free'` alone
                 * matches nothing unless the row exists, and most paid courses
                 * have no `_sikshya_course_type` row at all — so a paid-only
                 * grid came back empty on a normal site.
                 */
                'relation' => 'OR',
                [
                    'key' => '_sikshya_course_type',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_sikshya_course_type',
                    'value' => 'free',
                    'compare' => '!=',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     */
    private static function applyIncludeExclude(array &$args, array $a): void
    {
        $include = (array) ($a['includeIds'] ?? []);
        if ($include !== []) {
            $args['post__in'] = $include;
            // Honour the order the ids were listed in, which is the only
            // reason to hand-pick courses in the first place.
            if (($a['orderBy'] ?? 'date') === 'date') {
                $args['orderby'] = 'post__in';
                unset($args['order']);
            }
        }

        $exclude = (array) ($a['excludeIds'] ?? []);
        if ($exclude !== []) {
            $args['post__not_in'] = array_values(
                array_unique(array_merge((array) $args['post__not_in'], $exclude))
            );
        }
    }

    /**
     * Enrolled-only / exclude-enrolled.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $a
     * @return bool False when the filter can only ever match nothing.
     */
    private static function applyEnrollment(array &$args, array $a): bool
    {
        $enrolled_only = !empty($a['enrolledOnly']);
        $exclude_enrolled = !empty($a['excludeEnrolled']);

        if (!$enrolled_only && !$exclude_enrolled) {
            return true;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            // Signed out: nothing is enrolled. "Enrolled only" is empty;
            // "exclude enrolled" excludes nothing.
            return !$enrolled_only;
        }

        $ids = self::enrolledCourseIds($user_id);

        if ($enrolled_only) {
            if ($ids === []) {
                return false;
            }

            $existing = isset($args['post__in']) ? (array) $args['post__in'] : [];
            $args['post__in'] = $existing === [] ? $ids : array_values(array_intersect($existing, $ids));

            if ($args['post__in'] === []) {
                return false;
            }
        }

        if ($exclude_enrolled && $ids !== []) {
            $args['post__not_in'] = array_values(
                array_unique(array_merge((array) $args['post__not_in'], $ids))
            );
        }

        return true;
    }

    /**
     * Course ids the user holds an enrolment row for, newest first, capped.
     *
     * @return int[]
     */
    public static function enrolledCourseIds(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        static $memo = [];
        if (isset($memo[$user_id])) {
            return $memo[$user_id];
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return [];
        }

        $table = EnrollmentsTable::name();
        $limit = (int) apply_filters('sikshya_course_grid_max_enrolled_ids', self::MAX_ENROLLED_IDS);
        $limit = $limit > 0 ? min($limit, 5000) : self::MAX_ENROLLED_IDS;

        // Table name comes from a class constant, never from input; the one
        // caller-supplied value is placeheld.
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
                "SELECT DISTINCT course_id FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
                $user_id,
                $limit
            )
        );

        $ids = [];
        foreach ((array) $rows as $row) {
            $id = (int) $row;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $memo[$user_id] = array_values($ids);

        return $memo[$user_id];
    }
}
