<?php

declare(strict_types=1);

namespace Sikshya\Services;

/**
 * Warms the curriculum-count transients for a whole page of courses at once.
 *
 * `sikshya_get_course_curriculum_counts()` caches its answer in a transient
 * per course, keyed by the course's modified time. That makes the second view
 * of a course cheap, but a listing still pays one option read per card —
 * transients are not autoloaded, so on a site with no persistent object cache
 * a twelve-card grid spent twelve queries just asking "do I have the counts?".
 * Measured on the course-grid block: 3 cards cost 17 queries, 12 cost 45.
 *
 * This is the same shape as the per-item cron and per-row meta defects
 * elsewhere in the plugin: work that scales with the customer's data instead
 * of with the page. The fix is the same shape too — ask once for everything
 * the page needs.
 *
 * Nothing here computes counts. It only puts the already-stored values into
 * the options cache, so the per-card call finds them in memory. A course with
 * no stored counts yet is simply built on first use, as before.
 *
 * @package Sikshya\Services
 */
final class CourseCurriculumCountPrimer
{
    /**
     * Courses primed in this request, so two blocks on one page do not repeat it.
     *
     * @var array<int, true>
     */
    private static array $primed = [];

    /**
     * @param int[] $course_ids
     */
    public static function prime(array $course_ids): void
    {
        $ids = [];
        foreach ($course_ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !isset(self::$primed[$id])) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        $option_names = [];
        foreach ($ids as $id) {
            self::$primed[$id] = true;

            $name = self::transientOptionName($id);
            if ($name !== '') {
                $option_names[] = $name;
                // The timeout sibling is read on every get_transient() call,
                // so priming only the value would halve the saving.
                $option_names[] = '_transient_timeout_' . substr($name, strlen('_transient_'));
            }
        }

        if ($option_names === []) {
            return;
        }

        /*
         * WordPress 6.4 added a primer for exactly this; the plugin supports
         * 6.0, so fall back to one explicit query. Both end with the values in
         * the `options` cache group, which is where get_transient() looks.
         */
        if (function_exists('wp_prime_option_caches')) {
            wp_prime_option_caches($option_names);

            return;
        }

        self::primeViaQuery($option_names);
    }

    public static function flush(): void
    {
        self::$primed = [];
    }

    /**
     * The option name holding a course's cached counts.
     *
     * Mirrors the key built in `sikshya_get_course_curriculum_counts()`. If
     * that key ever changes, this returns a name that does not exist and the
     * primer becomes a no-op — a miss, never a wrong answer.
     */
    private static function transientOptionName(int $course_id): string
    {
        $modified = get_post_modified_time('U', true, $course_id);
        if ($modified === false) {
            return '';
        }

        return '_transient_sikshya_course_counts_' . $course_id . '_' . (string) $modified;
    }

    /**
     * @param string[] $option_names
     */
    private static function primeViaQuery(array $option_names): void
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return;
        }

        $option_names = array_values(array_unique($option_names));

        $placeholders = implode(',', array_fill(0, count($option_names), '%s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
                ...$option_names
            )
        );

        $found = [];
        foreach ((array) $rows as $row) {
            if (!is_object($row) || !isset($row->option_name)) {
                continue;
            }
            $found[(string) $row->option_name] = $row->option_value;
            wp_cache_set((string) $row->option_name, $row->option_value, 'options');
        }

        /*
         * A name with no row has to be cached as missing too, or get_option()
         * issues its own query for it — which is the cost this class exists to
         * remove. `false` in the `notoptions` map is how core records that.
         */
        $notoptions = wp_cache_get('notoptions', 'options');
        $notoptions = is_array($notoptions) ? $notoptions : [];

        foreach ($option_names as $name) {
            if (!array_key_exists($name, $found)) {
                $notoptions[$name] = true;
            }
        }

        wp_cache_set('notoptions', $notoptions, 'options');
    }
}
