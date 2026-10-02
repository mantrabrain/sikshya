<?php

namespace Sikshya\Core;

use Sikshya\Constants\Taxonomies;

use Sikshya\Constants\PostTypes;

use Sikshya\Database\Repositories\PluginLifecycleRepository;
use Sikshya\Migration\LegacyMigrator;
use Sikshya\Services\Settings;
use Sikshya\Services\StatsUsage;

/**
 * Plugin Uninstaller
 *
 * @package Sikshya\Core
 */
class Uninstaller
{
    private const OPT_ERASE_DATA = '_sikshya_erase_data_on_uninstall';
    private const OPT_ERASE_FILES = '_sikshya_erase_files_on_uninstall';

    /**
     * Uninstall the plugin
     */
    public static function uninstall(): void
    {
        // Check if user has permission
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $erase_data = Settings::isTruthy(get_option(self::OPT_ERASE_DATA, '0'));
        $erase_files = Settings::isTruthy(get_option(self::OPT_ERASE_FILES, '0'));

        // Remove custom post types and their data
        if ($erase_data) {
            self::removePostTypes();
        }

        // Remove custom database tables
        if ($erase_data) {
            self::removeTables();
        }

        // Remove plugin options
        if ($erase_data) {
            self::removeOptions();
        }

        // Remove per-user and leftover post meta
        if ($erase_data) {
            self::removeUserMeta();
            self::removePostMeta();
        }

        // Remove uploaded files
        if ($erase_data && $erase_files) {
            self::removeFiles();
        }

        // Clear any cached data
        self::clearCache();

        // Remove user roles and capabilities
        self::removeRoles();

        // Remove scheduled events
        self::removeScheduledEvents();
    }

    /**
     * Remove custom post types and their data
     */
    private static function removePostTypes(): void
    {
        /*
         * Must be the REGISTERED post types. These were previously spelled
         * `sikshya_course` / `sikshya_lesson` / ... which no post type has ever
         * used since `sik_*` was adopted in 1.0.0, so opting in to data erasure
         * deleted none of the user's course content. Question, chapter and
         * certificate were missing from the list entirely.
         */
        $post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];

        foreach ($post_types as $post_type) {
            // Get all posts of this type
            $posts = get_posts([
                'post_type' => $post_type,
                'numberposts' => -1,
                'post_status' => 'any',
            ]);

            // Delete each post
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }

        // Remove taxonomies
        // `sikshya_lesson_category` / `sikshya_quiz_category` were never
        // registered; the real ones are difficulty / lesson type / question type.
        $taxonomies = [
            Taxonomies::COURSE_CATEGORY,
            Taxonomies::COURSE_TAG,
            Taxonomies::DIFFICULTY,
            Taxonomies::LESSON_TYPE,
            Taxonomies::QUESTION_TYPE,
        ];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            foreach ($terms as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
    }

    /**
     * Remove custom database tables
     */
    private static function removeTables(): void
    {
        (new PluginLifecycleRepository())->dropAllFreeCustomTables();
    }

    /**
     * Remove plugin options.
     *
     * This was a hardcoded list of 19 names. Measured against a real install,
     * 13 of them have never existed while ~28 options the plugin does write
     * were absent from it — including `sikshya_jwt_secret` (the JWT signing
     * secret), `sikshya_license`, the cart/checkout/login page mappings and
     * every currency and price-format setting. Opting in to "erase all data"
     * left all of them behind.
     *
     * Matching by prefix instead means a new setting is covered the day it is
     * added, rather than the day somebody remembers to extend a list.
     *
     * Pro-owned options (`sikshya_pro_*`) are deliberately NOT touched: Sikshya
     * Pro ships its own `uninstall.php` and owns that namespace. Deleting
     * another plugin's options from here would be wrong even though Pro cannot
     * run without Free.
     */
    /**
     * Options inside the `sikshya_` namespace that another plugin owns.
     *
     * Sikshya Pro's addon schema markers were named before the `sikshya_pro_`
     * prefix became the convention, and renaming them now would re-run
     * migrations on every existing install. Filterable so a third-party addon
     * can protect its own options from this sweep.
     *
     * @return string[]
     */
    private static function foreignOwnedOptions(): array
    {
        $options = [
            'sikshya_webhooks_db_version',
            'sikshya_marketplace_db_version',
            'sikshya_enterprise_reports_db_version',
        ];

        /**
         * Filters the options this uninstaller must leave alone.
         *
         * @param string[] $options Option names to preserve.
         */
        $filtered = apply_filters('sikshya_uninstall_preserve_options', $options);

        return array_values(array_unique(array_map('strval', (array) $filtered)));
    }

    private static function removeOptions(): void
    {
        global $wpdb;

        // `$wpdb->esc_like()` escapes the literal underscores (and any `%`) so
        // `sikshya_` cannot also match `sikshyaX`. Relying on it instead of a
        // hand-written `ESCAPE` clause avoids a quoting trap: `ESCAPE '\\'`
        // inside a PHP double-quoted string reaches MySQL as `ESCAPE '\'`,
        // where the lone backslash escapes the closing quote and the statement
        // fails to parse.
        $free = $wpdb->esc_like('sikshya_') . '%';
        $free_underscore = $wpdb->esc_like('_sikshya_') . '%';
        $widget = $wpdb->esc_like('widget_sikshya_') . '%';
        $pro = $wpdb->esc_like('sikshya_pro_') . '%';
        $pro_underscore = $wpdb->esc_like('_sikshya_pro_') . '%';

        /*
         * A few Pro-owned options predate the `sikshya_pro_` prefix convention
         * and so are not covered by the NOT LIKE clauses. They are addon
         * schema-version markers: deleting them from here would make a still
         * installed Pro think its tables need rebuilding. Pro's own
         * `uninstall.php` removes them.
         */
        $foreign = self::foreignOwnedOptions();
        $placeholders = implode(', ', array_fill(0, count($foreign), '%s'));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                  WHERE (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s)
                    AND option_name NOT LIKE %s
                    AND option_name NOT LIKE %s
                    AND option_name NOT IN ({$placeholders})",
                array_merge([$free, $free_underscore, $widget, $pro, $pro_underscore], $foreign)
            )
        );

        // Also clears any object-cache entry for the two flags.
        delete_option(self::OPT_ERASE_DATA);
        delete_option(self::OPT_ERASE_FILES);

        if (is_multisite()) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->sitemeta}
                      WHERE (meta_key LIKE %s OR meta_key LIKE %s)
                        AND meta_key NOT LIKE %s
                        AND meta_key NOT LIKE %s
                        AND meta_key NOT IN ({$placeholders})",
                    array_merge([$free, $free_underscore, $pro, $pro_underscore], $foreign)
                )
            );

            delete_site_option(self::OPT_ERASE_DATA);
            delete_site_option(self::OPT_ERASE_FILES);
        }
    }

    /**
     * Remove per-user data the plugin stored in `wp_usermeta`.
     *
     * Nothing removed user meta at all. On the install this was measured
     * against that left 161 rows of `sikshya_cart_course_ids` plus
     * `_sikshya_learn_notes` — notes a learner typed themselves — behind after
     * an opt-in erasure.
     *
     * As with options, the `sikshya_pro_*` namespace belongs to Pro.
     */
    private static function removeUserMeta(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta}
                  WHERE (meta_key LIKE %s OR meta_key LIKE %s)
                    AND meta_key NOT LIKE %s
                    AND meta_key NOT LIKE %s",
                $wpdb->esc_like('sikshya_') . '%',
                $wpdb->esc_like('_sikshya_') . '%',
                $wpdb->esc_like('sikshya_pro_') . '%',
                $wpdb->esc_like('_sikshya_pro_') . '%'
            )
        );
    }

    /**
     * Remove plugin post meta still attached to posts that are not ours.
     *
     * Meta on Sikshya's own post types goes away with the posts in
     * {@see self::removePostTypes()}. This catches what is left on surviving
     * content — a page or a core post that Sikshya annotated.
     *
     * Pro's post-meta namespaces (`_sikshya_pro_*`, `_sikshya_drip_*`,
     * `_sikshya_access_*`) are left for Pro's own uninstaller.
     */
    private static function removePostMeta(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta}
                  WHERE meta_key LIKE %s
                    AND meta_key NOT LIKE %s
                    AND meta_key NOT LIKE %s
                    AND meta_key NOT LIKE %s",
                $wpdb->esc_like('_sikshya_') . '%',
                $wpdb->esc_like('_sikshya_pro_') . '%',
                $wpdb->esc_like('_sikshya_drip_') . '%',
                $wpdb->esc_like('_sikshya_access_') . '%'
            )
        );
    }

    /**
     * Remove uploaded files
     */
    private static function removeFiles(): void
    {
        $upload_dir = wp_upload_dir();
        $sikshya_dir = $upload_dir['basedir'] . '/sikshya';

        if (is_dir($sikshya_dir)) {
            self::deleteDirectory($sikshya_dir);
        }
    }

    /**
     * Clear cache
     */
    private static function clearCache(): void
    {
        // Clear WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sikshya');
        }

        (new PluginLifecycleRepository())->deleteSikshyaTransients();
    }

    /**
     * Remove user roles and capabilities
     */
    private static function removeRoles(): void
    {
        // Remove custom roles
        $roles = [
            'sikshya_instructor',
            'sikshya_student',
            'sikshya_assistant',
            'sikshya_auditor',
        ];

        foreach ($roles as $role) {
            remove_role($role);
        }

        // Remove capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'sikshya_access_admin_app',
                'edit_sikshya_courses',
                'edit_others_sikshya_courses',
                'publish_sikshya_courses',
                'read_private_sikshya_courses',
                'delete_sikshya_courses',
                'delete_private_sikshya_courses',
                'delete_published_sikshya_courses',
                'delete_others_sikshya_courses',
                'edit_private_sikshya_courses',
                'edit_published_sikshya_courses',
                'edit_sikshya_lessons',
                'edit_others_sikshya_lessons',
                'publish_sikshya_lessons',
                'read_private_sikshya_lessons',
                'delete_sikshya_lessons',
                'delete_private_sikshya_lessons',
                'delete_published_sikshya_lessons',
                'delete_others_sikshya_lessons',
                'edit_private_sikshya_lessons',
                'edit_published_sikshya_lessons',
                'edit_sikshya_quizzes',
                'edit_others_sikshya_quizzes',
                'publish_sikshya_quizzes',
                'read_private_sikshya_quizzes',
                'delete_sikshya_quizzes',
                'delete_private_sikshya_quizzes',
                'delete_published_sikshya_quizzes',
                'delete_others_sikshya_quizzes',
                'edit_private_sikshya_quizzes',
                'edit_published_sikshya_quizzes',
            ];

            foreach ($capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }

    /**
     * Unschedule the cron events this plugin registers.
     *
     * The previous list named four hooks — `sikshya_cleanup_expired_certificates`,
     * `sikshya_send_reminder_emails`, `sikshya_update_course_statistics` and
     * `sikshya_backup_progress_data` — that appear nowhere else in the codebase
     * and are never scheduled, so this method was a no-op while the three hooks
     * the plugin really does register stayed in `cron` after deletion. The names
     * are now taken from the classes that schedule them, so they cannot drift.
     *
     * Pro's hooks are Pro's to unschedule; its own `uninstall.php` does that.
     */
    private static function removeScheduledEvents(): void
    {
        $events = [
            StatsUsage::CRON_HOOK,
            StatsUsage::IMMEDIATE_HOOK,
            LegacyMigrator::CRON_HOOK,
        ];

        foreach ($events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Delete directory recursively
     */
    private static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
