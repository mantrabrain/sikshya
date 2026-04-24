<?php

/**
 * WordPress core REST: status=any excludes trashed posts. Align Sikshya admin lists with wp-admin "All" (include trash).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class RestCollectionQueryService
{
    public function init(): void
    {
        add_action('init', [$this, 'registerFilters'], 100);
    }

    public function registerFilters(): void
    {
        foreach (PostTypes::getAll() as $post_type) {
            if (!post_type_exists($post_type)) {
                continue;
            }
            add_filter("rest_{$post_type}_query", [$this, 'includeTrashWhenStatusAny'], 10, 2);
        }

        // Sikshya admin: filter courses by bundle vs regular in WP REST collections.
        if (post_type_exists(PostTypes::COURSE)) {
            add_filter('rest_' . PostTypes::COURSE . '_query', [$this, 'filterCoursesByType'], 11, 2);
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function includeTrashWhenStatusAny(array $args, WP_REST_Request $request): array
    {
        $raw = $request->get_param('status');
        $wants_any = $raw === 'any' || (is_array($raw) && in_array('any', $raw, true));

        if (!$wants_any) {
            return $args;
        }

        $ps = $args['post_status'] ?? null;

        /*
         * REST passes `post_status` as the string `any` (or a single-element array).
         * WP_Query then excludes statuses with `exclude_from_search` (including `trash`).
         * Expand explicitly so "All" lists match wp-admin and include trashed posts.
         */
        if ($ps === 'any' || (is_array($ps) && count($ps) === 1 && (string) ($ps[0] ?? '') === 'any')) {
            $statuses = array_keys(get_post_stati(['internal' => false]));
            if (!in_array('trash', $statuses, true)) {
                $statuses[] = 'trash';
            }
            $args['post_status'] = array_values(array_unique($statuses));

            return $args;
        }

        if (is_array($ps) && !in_array('trash', $ps, true)) {
            $args['post_status'][] = 'trash';
        }

        return $args;
    }

    /**
     * REST collection filter for the Courses listing:
     * - `sikshya_course_type=bundle` => only bundles
     * - `sikshya_course_type=subscription` => only subscription-only courses
     * - `sikshya_course_type=regular` => anything except bundles (including missing key)
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterCoursesByType(array $args, WP_REST_Request $request): array
    {
        $raw = sanitize_key((string) $request->get_param('sikshya_course_type'));
        if ($raw !== 'bundle' && $raw !== 'subscription' && $raw !== 'regular') {
            return $args;
        }

        $mq = $args['meta_query'] ?? [];
        if (!is_array($mq)) {
            $mq = [];
        }

        if ($raw === 'bundle') {
            $mq[] = [
                'key' => '_sikshya_course_type',
                'value' => 'bundle',
                'compare' => '=',
            ];
            $args['meta_query'] = $mq;
            return $args;
        }

        if ($raw === 'subscription') {
            $mq[] = [
                'key' => '_sikshya_course_type',
                'value' => 'subscription',
                'compare' => '=',
            ];
            $args['meta_query'] = $mq;
            return $args;
        }

        // regular: exclude bundles; treat missing meta as regular.
        $mq[] = [
            'relation' => 'OR',
            [
                'key' => '_sikshya_course_type',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_sikshya_course_type',
                'value' => 'bundle',
                'compare' => '!=',
            ],
        ];
        $args['meta_query'] = $mq;

        return $args;
    }
}
