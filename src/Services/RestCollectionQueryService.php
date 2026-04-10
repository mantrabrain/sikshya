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
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function includeTrashWhenStatusAny(array $args, WP_REST_Request $request): array
    {
        if ($request->get_param('status') !== 'any') {
            return $args;
        }

        $ps = $args['post_status'] ?? null;
        if (!is_array($ps)) {
            return $args;
        }
        if (!in_array('trash', $ps, true)) {
            $args['post_status'][] = 'trash';
        }

        return $args;
    }
}
