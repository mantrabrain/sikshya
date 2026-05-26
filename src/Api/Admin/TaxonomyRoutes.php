<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Services\CategoryService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course-category taxonomy admin routes — get, save, delete.
 *
 * Extracted from {@see \Sikshya\Api\AdminRestRoutes}. Owns
 * `/sikshya/v1/taxonomies/course-category` (POST upsert) and
 * `/sikshya/v1/taxonomies/course-category/(?P<id>\d+)` (GET, DELETE).
 *
 * All persistence is delegated to {@see CategoryService} (looked up via the plugin container);
 * the routes themselves are thin response shapers preserving the legacy
 * `{success: bool, message, data?, errors?}` envelope.
 *
 * @package Sikshya\Api\Admin
 */
final class TaxonomyRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/taxonomies/course-category', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCategory'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/taxonomies/course-category/(?P<id>\\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourseCategory'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteCategory'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);
    }

    public function getCourseCategory(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('categoryService');
        if (!$svc instanceof CategoryService) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $id = (int) $request->get_param('id');
        $r = $svc->get($id);
        if (empty($r['ok'])) {
            $code = ($r['code'] ?? '') === 'not_found' ? 404 : 403;

            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], $code);
        }

        return new WP_REST_Response(['success' => true, 'data' => $r['data'] ?? []], 200);
    }

    public function saveCategory(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('categoryService');
        if (!$svc instanceof CategoryService) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $r = $svc->save($this->jsonBody($request));
        if (empty($r['ok'])) {
            return new WP_REST_Response(
                ['success' => false, 'message' => $r['message'] ?? '', 'errors' => $r['errors'] ?? null],
                400
            );
        }

        return new WP_REST_Response(
            ['success' => true, 'message' => $r['message'] ?? '', 'data' => $r['data'] ?? []],
            200
        );
    }

    public function deleteCategory(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('categoryService');
        if (!$svc instanceof CategoryService) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $id = (int) $request->get_param('id');
        $r = $svc->delete($id);
        if (empty($r['ok'])) {
            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], 400);
        }

        return new WP_REST_Response(['success' => true, 'message' => $r['message'] ?? ''], 200);
    }
}
