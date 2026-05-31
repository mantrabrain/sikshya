<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Tables\OrdersTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global admin search: a single endpoint that fans out across users, courses,
 * and orders so the React admin can render a small command-palette-style
 * dropdown in the TopBar.
 *
 * Endpoint: `GET /sikshya/v1/admin/search?q=<term>&limit=<n>`
 *
 * Responds with a unified envelope:
 *   { ok: true, results: { users: [...], courses: [...], orders: [...] } }
 *
 * Each row carries `{ id, title, subtitle, url }` so the React client doesn't
 * need to know each domain's schema. Empty queries return empty result lists
 * (no 400 — easier to wire in the UI).
 *
 * @package Sikshya\Api\Admin
 */
final class SearchRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/search', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'search'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'q' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'limit' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    public function search(WP_REST_Request $request): WP_REST_Response
    {
        $q = trim((string) $request->get_param('q'));
        $limit = (int) $request->get_param('limit');
        if ($limit < 1 || $limit > 20) {
            $limit = 5;
        }

        if ($q === '' || strlen($q) < 2) {
            return new WP_REST_Response(
                [
                    'ok' => true,
                    'query' => $q,
                    'results' => ['users' => [], 'courses' => [], 'orders' => []],
                ],
                200
            );
        }

        $results = [
            'users' => $this->searchUsers($q, $limit),
            'courses' => $this->searchCourses($q, $limit),
            'orders' => $this->searchOrders($q, $limit),
        ];

        /**
         * Allow Pro / addons to contribute extra result buckets (subscriptions,
         * coupons, payouts, …). Each entry should be a list of rows with the
         * canonical `{id, title, subtitle, url}` shape so the React palette
         * renders them uniformly.
         *
         * @param array<string, array<int, array{id:int,title:string,subtitle:string,url:string}>> $results
         * @param string                                                                            $q
         * @param int                                                                               $limit
         */
        $results = (array) apply_filters('sikshya_admin_global_search_results', $results, $q, $limit);

        return new WP_REST_Response(
            [
                'ok' => true,
                'query' => $q,
                'results' => $results,
            ],
            200
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchUsers(string $q, int $limit): array
    {
        $userQuery = new WP_User_Query([
            'search' => '*' . $q . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => $limit,
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        ]);
        $users = $userQuery->get_results();
        $out = [];
        foreach ((array) $users as $u) {
            $uid = (int) $u->ID;
            // Route to the Sikshya React people hub focused on this user
            // rather than wp-admin's core user-edit screen. The global
            // search should always land inside the Sikshya app where the
            // learner / instructor view (enrollments, progress, payments)
            // lives, not in the bare WordPress profile editor.
            $out[] = [
                'id' => $uid,
                'title' => (string) ($u->display_name ?: $u->user_login),
                'subtitle' => (string) $u->user_email,
                'url' => admin_url('admin.php?page=sikshya&view=people&user_id=' . $uid),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchCourses(string $q, int $limit): array
    {
        $posts = get_posts([
            'post_type' => PostTypes::COURSE,
            's' => $q,
            'posts_per_page' => $limit,
            'post_status' => ['publish', 'draft', 'private'],
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);
        $out = [];
        foreach ((array) $posts as $p) {
            if (!$p instanceof \WP_Post) {
                continue;
            }
            // Open in the Sikshya React course builder (add-course view
            // accepts `course_id` for editing an existing course) instead of
            // wp-admin's bare post editor. The Sikshya builder is the
            // canonical edit surface — curriculum, pricing, settings, etc.
            // live there.
            $out[] = [
                'id' => (int) $p->ID,
                'title' => (string) get_the_title($p),
                'subtitle' => (string) get_post_status_object($p->post_status)->label ?? '',
                'url' => admin_url('admin.php?page=sikshya&view=add-course&course_id=' . (int) $p->ID),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchOrders(string $q, int $limit): array
    {
        $repo = new OrderRepository();
        if (!$repo->tableExists()) {
            return [];
        }

        // Two recognised forms: a numeric ID (lookup directly) or a public token
        // (substring match against `public_token`).
        global $wpdb;
        $table = OrdersTable::getTableName();
        if ($table === '') {
            return [];
        }

        $like = '%' . $wpdb->esc_like($q) . '%';
        $isNumeric = ctype_digit($q);
        if ($isNumeric) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, public_token, total, currency, status, created_at FROM {$table}
                     WHERE id = %d OR public_token LIKE %s
                     ORDER BY created_at DESC LIMIT %d",
                    (int) $q,
                    $like,
                    $limit
                )
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, public_token, total, currency, status, created_at FROM {$table}
                     WHERE public_token LIKE %s
                     ORDER BY created_at DESC LIMIT %d",
                    $like,
                    $limit
                )
            );
        }

        $out = [];
        foreach ((array) $rows as $row) {
            $oid = (int) ($row->id ?? 0);
            if ($oid <= 0) {
                continue;
            }
            $token = (string) ($row->public_token ?? '');
            $subtitle = trim(sprintf(
                '%s · %s %s',
                (string) ($row->status ?? ''),
                (string) ($row->currency ?? ''),
                (string) ($row->total ?? '')
            ));
            $out[] = [
                'id' => $oid,
                'title' => '#' . $oid . ($token !== '' ? ' · ' . substr($token, 0, 8) : ''),
                'subtitle' => $subtitle,
                'url' => admin_url('admin.php?page=sikshya&view=order-detail&order_id=' . $oid),
            ];
        }
        return $out;
    }
}
