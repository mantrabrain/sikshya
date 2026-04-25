<?php

/**
 * Protect the bundled default certificate templates from deletion.
 *
 * The Free build seeds two ready-to-use templates so first-time admins can
 * issue certificates immediately. Those templates are the safety net for
 * existing courses, so we hard-block deletion at every level:
 *
 *   1. WordPress core delete/trash hooks (returns false, surfaces in REST as
 *      a 500 with `cant_delete_post`).
 *   2. `user_has_cap` strips `delete_post` for the protected ids so the WP
 *      REST DELETE endpoint can return a clean 403 BEFORE attempting deletion.
 *   3. Admin list `post_row_actions` removes the Trash/Delete row links so the
 *      affordance is never even shown.
 *   4. A REST-exposed boolean `meta._sikshya_certificate_default_locked` so
 *      the React admin can hide its trash button (UX, not security).
 *
 * Pro can opt out per-template via the
 * `sikshya_certificate_default_template_locked` filter (return false to allow
 * deletion).
 *
 * @package Sikshya\Certificates
 */

namespace Sikshya\Certificates;

use Sikshya\Constants\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateGuard
{
    private const META_DEFAULT = '_sikshya_certificate_default';

    /**
     * Wire all the guards. Idempotent; calling twice has no effect.
     */
    public static function register(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        // 1. Block trash + permanent delete at the model layer.
        add_filter('pre_trash_post', [self::class, 'maybeBlockTrash'], 10, 2);
        add_filter('pre_delete_post', [self::class, 'maybeBlockDelete'], 10, 3);

        // 2. Strip `delete_post` for protected ids so REST DELETE responds with a clean 403.
        add_filter('user_has_cap', [self::class, 'stripDeleteCapability'], 10, 4);

        // 3. Hide the Trash / Delete links from the classic admin list.
        add_filter('post_row_actions', [self::class, 'filterRowActions'], 10, 2);

        // 4. Expose `_sikshya_certificate_default_locked` to REST for the React UI.
        add_action('init', [self::class, 'registerProtectionMeta'], 30);
    }

    /**
     * True if `$post_id` is one of the seeded default templates AND the lock
     * has not been disabled via the public filter.
     */
    public static function isLocked(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        if (get_post_type($post_id) !== PostTypes::CERTIFICATE) {
            return false;
        }

        $is_default = (string) get_post_meta($post_id, self::META_DEFAULT, true) === '1';
        if (!$is_default) {
            return false;
        }

        // Filter `sikshya_certificate_default_template_locked` (bool $locked, int $post_id):
        //   Pro builds may return false to allow site owners to remove defaults they don't want.
        return (bool) apply_filters('sikshya_certificate_default_template_locked', true, $post_id);
    }

    /**
     * @param mixed                $check_value Standard `pre_trash_post` payload.
     * @param \WP_Post|int|null    $post        Post object (or id, depending on caller).
     * @return mixed                            Return `false` to block trash; passthrough otherwise.
     */
    public static function maybeBlockTrash($check_value, $post)
    {
        $id = self::resolvePostId($post);
        if ($id > 0 && self::isLocked($id)) {
            return false;
        }

        return $check_value;
    }

    /**
     * @param mixed             $check_value Standard `pre_delete_post` payload.
     * @param \WP_Post|int|null $post        Post object (or id).
     * @param bool              $force_delete True for permanent delete.
     * @return mixed                          Return `false` to block delete; passthrough otherwise.
     */
    public static function maybeBlockDelete($check_value, $post, $force_delete = false)
    {
        $id = self::resolvePostId($post);
        if ($id > 0 && self::isLocked($id)) {
            return false;
        }

        return $check_value;
    }

    /**
     * Strip `delete_post` (and its delete_published / delete_others variants)
     * for protected template ids so REST DELETE returns a clean 403 instead of
     * a 500 from the model-layer block.
     *
     * @param array<string,bool> $allcaps Existing user caps map.
     * @param array<int,string>  $caps    Required caps for the meta-cap.
     * @param array<int,mixed>   $args    Args passed to current_user_can: [meta_cap, user_id, post_id, ...].
     * @return array<string,bool>
     */
    public static function stripDeleteCapability(array $allcaps, array $caps, array $args): array
    {
        if (!isset($args[0]) || !is_string($args[0])) {
            return $allcaps;
        }

        $cap = $args[0];
        $delete_caps = ['delete_post', 'delete_others_posts', 'delete_published_posts'];
        if (!in_array($cap, $delete_caps, true)) {
            return $allcaps;
        }

        $post_id = isset($args[2]) ? (int) $args[2] : 0;
        if ($post_id <= 0 || !self::isLocked($post_id)) {
            return $allcaps;
        }

        // Deny each required primitive cap so WP rolls up to "no" cleanly.
        foreach ($caps as $required) {
            if (is_string($required) && $required !== '') {
                $allcaps[$required] = false;
            }
        }

        return $allcaps;
    }

    /**
     * Hide the Trash / Delete row actions for protected templates in the
     * classic list table (Edit Posts screen).
     *
     * @param array<string,string> $actions
     * @return array<string,string>
     */
    public static function filterRowActions(array $actions, $post): array
    {
        $id = self::resolvePostId($post);
        if ($id <= 0 || !self::isLocked($id)) {
            return $actions;
        }

        unset($actions['trash'], $actions['delete']);

        return $actions;
    }

    /**
     * Register a REST-exposed read-only boolean computed from the lock state,
     * so the React admin can hide its “Move to trash” affordance without
     * duplicating the lock logic on the client.
     */
    public static function registerProtectionMeta(): void
    {
        if (!function_exists('register_post_meta')) {
            return;
        }

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_default',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => static function ($v): string {
                    return ((string) $v) === '1' ? '1' : '0';
                },
                'default' => '0',
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_default_key',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => static function ($v): string {
                    return sanitize_key((string) $v);
                },
                'default' => '',
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_default_locked',
            [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'boolean',
                        'context' => ['view', 'edit'],
                        'readonly' => true,
                    ],
                ],
                'default' => false,
                // Computed at read time; stored value is irrelevant.
                'auth_callback' => static function (): bool {
                    return false;
                },
            ]
        );

        // Compute the boolean dynamically based on the live lock state instead
        // of trusting a stored value (so the Pro filter can flip it instantly).
        add_filter(
            'get_post_metadata',
            static function ($value, int $object_id, string $meta_key, bool $single) {
                if ($meta_key !== '_sikshya_certificate_default_locked') {
                    return $value;
                }
                $locked = self::isLocked($object_id);

                return $single ? $locked : [$locked];
            },
            10,
            4
        );
    }

    /**
     * Resolve a post id from a WP_Post or scalar. Returns 0 when unresolvable.
     *
     * @param mixed $post
     */
    private static function resolvePostId($post): int
    {
        if ($post instanceof \WP_Post) {
            return (int) $post->ID;
        }
        if (is_numeric($post)) {
            return (int) $post;
        }
        if (is_array($post) && isset($post['ID'])) {
            return (int) $post['ID'];
        }

        return 0;
    }
}
