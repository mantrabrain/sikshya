<?php

namespace Sikshya\Utils;

use Sikshya\Constants\PostTypes;

/**
 * Some posts can end up with an empty slug (post_name), commonly when the title
 * contains only characters that sanitize_title() strips. That results in 404s on
 * pretty permalinks until the post is edited and re-published (WordPress may then
 * generate a fallback slug).
 *
 * This utility ensures Sikshya content has a stable, non-empty slug.
 */
final class EnsurePostSlug
{
    /**
     * Prevent recursion when we call wp_update_post inside save_post.
     *
     * @var array<int, bool>
     */
    private static array $updating = [];

    public static function register(): void
    {
        add_action('save_post', [self::class, 'ensureOnSave'], 20, 3);

        // Opportunistic backfill for already-published content with empty slugs.
        add_action('init', [self::class, 'maybeBackfillEmptySlugs'], 20);
    }

    /**
     * Ensure a slug exists on save.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public static function ensureOnSave(int $post_id, \WP_Post $post, bool $update): void
    {
        unset($update);

        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return;
        }

        if (!self::isTargetPostType((string) $post->post_type)) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (isset(self::$updating[$post_id])) {
            return;
        }

        if (is_string($post->post_name) && trim($post->post_name) !== '') {
            return;
        }

        $fallback = sanitize_title((string) $post->post_title);
        if ($fallback === '') {
            $fallback = sanitize_key((string) $post->post_type) . '-' . (string) $post_id;
        }

        self::$updating[$post_id] = true;
        remove_action('save_post', [self::class, 'ensureOnSave'], 20);

        // Use post_name so WP does not re-sanitize the title into empty slug again.
        wp_update_post(
            [
                'ID' => $post_id,
                'post_name' => $fallback,
            ]
        );

        add_action('save_post', [self::class, 'ensureOnSave'], 20, 3);
        unset(self::$updating[$post_id]);
    }

    public static function maybeBackfillEmptySlugs(): void
    {
        if (is_admin() && wp_doing_ajax()) {
            return;
        }

        if (!empty(get_transient('sikshya_backfill_empty_slugs_done'))) {
            return;
        }

        // Run at most once per day; keep it light.
        set_transient('sikshya_backfill_empty_slugs_done', '1', DAY_IN_SECONDS);

        global $wpdb;
        if (!isset($wpdb) || !isset($wpdb->posts)) {
            return;
        }

        $types = array_map('esc_sql', self::targetPostTypes());
        if ($types === []) {
            return;
        }

        $in = "'" . implode("','", $types) . "'";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type IN ({$in})
               AND post_status IN ('publish','draft','pending','private')
               AND (post_name IS NULL OR post_name = '')
             ORDER BY ID DESC
             LIMIT 200"
        );
        if (!is_array($ids) || $ids === []) {
            return;
        }

        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }
            $post = get_post($id);
            if (!$post || !($post instanceof \WP_Post)) {
                continue;
            }
            self::ensureOnSave($id, $post, true);
        }
    }

    private static function isTargetPostType(string $post_type): bool
    {
        return in_array($post_type, self::targetPostTypes(), true);
    }

    /**
     * @return string[]
     */
    private static function targetPostTypes(): array
    {
        return [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::CHAPTER,
            PostTypes::QUESTION,
        ];
    }
}

