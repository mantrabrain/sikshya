<?php

/**
 * Admin REST routes — thin controllers over domain services.
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Sikshya\Admin\CourseBuilder\BundleBuilderFieldFilter;
use Sikshya\Admin\CourseBuilder\CourseBuilderManager;
use Sikshya\Admin\Controllers\ReportController;
use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Commerce\PaymentGatewayRegistry;
use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\Services\CategoryService;
use Sikshya\Services\CourseBuilderService;
use Sikshya\Services\CourseCurriculumActions;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Services\CourseService;
use Sikshya\Services\CurriculumService;
use Sikshya\Services\SampleDataImporter;
use Sikshya\Addons\Addons;
use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Licensing\Pro;
use Sikshya\Services\EmailNotificationService;
use Sikshya\Services\Settings;
use Sikshya\Services\PermalinkService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class AdminRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/certificates/(?P<id>\d+)/preview', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'previewCertificate'],
                'permission_callback' => [$this, 'permissionAdminOrCanEditCertificate'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'previewCertificate'],
                'permission_callback' => [$this, 'permissionAdminOrCanEditCertificate'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/course-builder/save', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCourseBuilder'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/course-builder/bootstrap', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourseBuilderBootstrap'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'course_id' => [
                        'default' => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/course-builder/set-type', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setCourseType'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'course_type' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => static function ($v): bool {
                            return in_array((string) $v, ['free', 'paid', 'subscription', 'bundle'], true);
                        },
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/course-chapters', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourseChapters'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/course-curriculum-tree', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourseCurriculumTree'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/content', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createContent'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/content/link', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'linkContent'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/content-item', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveContentItem'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/chapter-order', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveChapterOrder'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/lesson-order', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveLessonOrder'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/outline-structure', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveOutlineStructure'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/bulk-delete', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulkDeleteCurriculumItems'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/chapters', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createChapter'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/curriculum/chapters/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getChapter'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateChapter'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/taxonomies/course-category', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCategory'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/taxonomies/course-category/(?P<id>\d+)', [
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

        register_rest_route($namespace, '/settings/schema', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettingsSchema'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/settings/values', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettingsValues'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'tab' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/settings/save', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveSettings'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/settings/reset', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'resetSettings'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/settings/email/test-delivery', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'postSettingsEmailTestDelivery'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/tools', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'toolsAction'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);

        register_rest_route($namespace, '/admin/post-status-counts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminPostStatusCounts'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'post_type' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/overview', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminOverview'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/admin/licensing', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLicensingPayload'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/admin/shell-meta', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getShellMeta'],
                /** Same gate as the unified React admin screen (`edit_posts`). */
                'permission_callback' => [$this, 'permissionReactApp'],
                'args' => [
                    'view' => [
                        'type' => 'string',
                        'default' => 'dashboard',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/reports-snapshot', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminReportsSnapshot'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/admin/enrollments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminEnrollments'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'status' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'course_id' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    'search' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/quiz-attempts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminQuizAttempts'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 30,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'quiz_id' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    'course_id' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    'user_id' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    'status' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'search' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/payments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminPayments'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 30,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/issued-certificates', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getIssuedCertificates'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 50,
                        'minimum' => 1,
                        'maximum' => 200,
                    ],
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/issued-certificates/revoke', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'revokeIssuedCertificate'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/admin/orders', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminOrders'],
                'permission_callback' => [$this, 'permissionAdmin'],
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 30,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/orders/(?P<id>\d+)/mark-paid', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'markAdminOrderPaid'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);

        register_rest_route($namespace, '/admin/coupons', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminCoupons'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createAdminCoupon'],
                'permission_callback' => [$this, 'permissionAdmin'],
            ],
        ]);
    }

    /**
     * Full-page certificate preview payload (HTML only; no theme styling).
     *
     * @return WP_REST_Response|WP_Error
     */
    public function previewCertificate(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        if ($id <= 0) {
            return new WP_Error('invalid_id', __('Invalid certificate id.', 'sikshya'), ['status' => 400]);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== PostTypes::CERTIFICATE) {
            return new WP_Error('not_found', __('Certificate not found.', 'sikshya'), ['status' => 404]);
        }

        if (!current_user_can('edit_post', $id)) {
            return new WP_Error('rest_forbidden', __('You cannot preview this certificate.', 'sikshya'), ['status' => 403]);
        }

        $title = wp_strip_all_tags(get_the_title($id));
        $html = (string) $post->post_content;
        $html = is_string($html) ? str_replace("\0", '', (string) wp_check_invalid_utf8($html, true)) : '';

        if ($request->get_method() === 'POST') {
            $params = $request->get_json_params();
            if (is_array($params)) {
                if (isset($params['title']) && is_string($params['title']) && $params['title'] !== '') {
                    $title = sanitize_text_field($params['title']);
                }
                if (isset($params['html']) && is_string($params['html']) && $params['html'] !== '') {
                    $inline = (string) wp_unslash($params['html']);
                    if (strlen($inline) > 400000) {
                        return new WP_Error(
                            'payload_too_large',
                            __('Preview HTML is too large.', 'sikshya'),
                            ['status' => 413]
                        );
                    }
                    $inline = str_replace("\0", '', (string) wp_check_invalid_utf8($inline, true));
                    $html = $inline;
                }
            }
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'id' => (int) $id,
                'title' => $title,
                'html' => $html,
            ],
            200
        );
    }

    public function canManageCourseBuilder(): bool
    {
        return current_user_can('manage_sikshya')
            || current_user_can('edit_sikshya_courses');
    }

    /**
     * Logged-in admin (cookie + X-WP-Nonce) or valid JWT Bearer.
     *
     * @return bool|WP_Error
     */
    /**
     * Anyone who can open the Sikshya React app (`admin.php?page=sikshya`).
     *
     * @return bool
     */
    public function permissionReactApp()
    {
        return current_user_can('edit_posts');
    }

    public function permissionAdmin(WP_REST_Request $request)
    {
        if ($this->canManageCourseBuilder()) {
            return true;
        }

        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error('rest_forbidden', __('Authentication required', 'sikshya'), ['status' => 401]);
        }

        $svc = $this->plugin->getService('jwtAuth');
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error('rest_forbidden', __('JWT unavailable', 'sikshya'), ['status' => 500]);
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user($uid);

        return $this->canManageCourseBuilder()
            ? true
            : new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    /**
     * Certificate preview: allow course builder admins, or any user who can edit that certificate
     * (wp-admin with cookie+nonce, without manage_sikshya / course caps).
     *
     * @return bool|WP_Error
     */
    public function permissionAdminOrCanEditCertificate(WP_REST_Request $request)
    {
        if ($this->canManageCourseBuilder()) {
            return true;
        }

        $id = (int) $request->get_param('id');
        if ($id > 0) {
            $post = get_post($id);
            if (
                $post
                && $post->post_type === PostTypes::CERTIFICATE
                && current_user_can('edit_post', $id)
            ) {
                return true;
            }
        }

        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error('rest_forbidden', __('Authentication required', 'sikshya'), ['status' => 401]);
        }

        $svc = $this->plugin->getService('jwtAuth');
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error('rest_forbidden', __('JWT unavailable', 'sikshya'), ['status' => 500]);
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user($uid);

        if ($this->canManageCourseBuilder()) {
            return true;
        }

        if ($id > 0) {
            $post = get_post($id);
            if (
                $post
                && $post->post_type === PostTypes::CERTIFICATE
                && current_user_can('edit_post', $id)
            ) {
                return true;
            }
        }

        return new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    /**
     * Maintainer tools (export/import, cache, diagnostics) — administrators only.
     *
     * @return bool|WP_Error
     */
    public function permissionTools()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    /**
     * Single aggregate counts for post-type list tabs (replaces N parallel wp/v2 requests).
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getAdminPostStatusCounts(WP_REST_Request $request)
    {
        $post_type = (string) $request->get_param('post_type');
        if ($post_type === '' || !post_type_exists($post_type)) {
            return new WP_Error(
                'invalid_post_type',
                __('Invalid post type.', 'sikshya'),
                ['status' => 400]
            );
        }

        $pto = get_post_type_object($post_type);
        if (!$pto instanceof \WP_Post_Type) {
            return new WP_Error(
                'invalid_post_type',
                __('Invalid post type.', 'sikshya'),
                ['status' => 400]
            );
        }

        if (!current_user_can($pto->cap->edit_posts)) {
            return new WP_Error(
                'rest_cannot_view',
                __('Sorry, you are not allowed to view posts of this post type.', 'sikshya'),
                ['status' => 403]
            );
        }

        $c = wp_count_posts($post_type);
        $publish = (int) ($c->publish ?? 0);
        $draft = (int) ($c->draft ?? 0);
        $pending = (int) ($c->pending ?? 0);
        $future = (int) ($c->future ?? 0);
        $private = (int) ($c->private ?? 0);
        $trash = (int) ($c->trash ?? 0);

        // "All" tab: every status (incl. trash + custom statuses) to match expanded REST `status=any`.
        $any = 0;
        foreach ((array) $c as $n) {
            $any += (int) $n;
        }

        return new WP_REST_Response(
            [
                'any' => $any,
                'publish' => $publish,
                'draft' => $draft,
                'pending' => $pending,
                'future' => $future,
                'private' => $private,
                'trash' => $trash,
            ],
            200
        );
    }

    private function jsonBody(WP_REST_Request $request): array
    {
        $p = $request->get_json_params();
        if (is_array($p)) {
            return $p;
        }
        $b = $request->get_body_params();
        return is_array($b) ? $b : [];
    }

    private function curriculumActions(): ?CourseCurriculumActions
    {
        $ui = $this->plugin->getService('courseBuilderUi');
        return $ui instanceof CourseCurriculumActions ? $ui : null;
    }

    public function createChapter(WP_REST_Request $request): WP_REST_Response
    {
        $ui = $this->curriculumActions();
        if (!$ui) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $r = $ui->restCreateChapter($this->jsonBody($request));
        if (empty($r['success'])) {
            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], 400);
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => $r['message'] ?? '',
                'data' => $r['data'] ?? [],
            ],
            200
        );
    }

    public function getChapter(WP_REST_Request $request): WP_REST_Response
    {
        $ui = $this->curriculumActions();
        if (!$ui) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $id = (int) $request->get_param('id');
        $r = $ui->restChapterData($id);
        if (empty($r['success'])) {
            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], 400);
        }

        return new WP_REST_Response(['success' => true, 'data' => $r['data'] ?? []], 200);
    }

    public function updateChapter(WP_REST_Request $request): WP_REST_Response
    {
        $ui = $this->curriculumActions();
        if (!$ui) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
        }

        $p = $this->jsonBody($request);
        $p['chapter_id'] = (int) $request->get_param('id');
        $r = $ui->restUpdateChapter($p);
        if (empty($r['success'])) {
            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], 400);
        }

        return new WP_REST_Response(['success' => true, 'message' => $r['message'] ?? ''], 200);
    }

    public function getCourseBuilderBootstrap(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->canManageCourseBuilder()) {
            return new WP_REST_Response(['success' => false, 'message' => __('Forbidden', 'sikshya')], 403);
        }

        $course_id = (int) $request->get_param('course_id');
        $manager = new CourseBuilderManager($this->plugin);

        $defaults = [
            'course_id' => $course_id,
            'title' => '',
            'description' => '',
            'slug' => '',
            'difficulty' => 'beginner',
            'language' => 'en',
            'duration' => 1,
        ];

        $values = $course_id > 0
            ? array_merge($defaults, $manager->getFlatFieldValuesForCourse($course_id))
            : $defaults;

        $users = [];
        foreach (get_users(['number' => 250, 'orderby' => 'display_name', 'order' => 'ASC']) as $u) {
            $users[] = [
                'id' => (int) $u->ID,
                'name' => $u->display_name,
            ];
        }

        $preview_url = '';
        if ($course_id > 0) {
            $link = get_permalink($course_id);
            $preview_url = is_string($link) ? $link : '';
        }

        $is_bundle = $course_id > 0
            && sanitize_key((string) get_post_meta($course_id, '_sikshya_course_type', true)) === 'bundle';

        $tabs = $manager->getTabsForBootstrap();
        $tab_fields = $manager->getTabFieldsForJs();

        // Bundles are commercial packages + landing page — hide curriculum/settings/Pro tabs; trim fields in Course + Pricing.
        if ($is_bundle) {
            $keep = ['course', 'pricing'];
            $tabs = array_values(
                array_filter(
                    $tabs,
                    static function ($t) use ($keep): bool {
                        return in_array((string) ($t['id'] ?? ''), $keep, true);
                    }
                )
            );
            foreach (array_keys($tab_fields) as $tid) {
                if (!in_array($tid, $keep, true)) {
                    unset($tab_fields[$tid]);
                }
            }
            $tab_fields = BundleBuilderFieldFilter::filterTabFields($tab_fields);
            $tabs = BundleBuilderFieldFilter::adjustTabSummariesForBundle($tabs);
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'data' => [
                    'course_id' => $course_id,
                    'tabs' => $tabs,
                    'tabFields' => $tab_fields,
                    'values' => $values,
                    'users' => $users,
                    'preview_url' => $preview_url,
                    'is_bundle' => $is_bundle,
                ],
            ],
            200
        );
    }

    /**
     * Chapters with nested curriculum content IDs for the React course builder outline.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function getCourseCurriculumTree(WP_REST_Request $request): WP_REST_Response
    {
        $course_id = (int) $request->get_param('course_id');
        if ($course_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid course.', 'sikshya')], 400);
        }

        $posts = get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_parent' => $course_id,
                'post_status' => 'any',
                'numberposts' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => '_sikshya_order',
                'order' => 'ASC',
            ]
        );

        $tree = [];
        foreach ($posts as $p) {
            $chapter_id = (int) $p->ID;
            $chapter_contents = get_post_meta($chapter_id, '_sikshya_contents', true);
            if (!is_array($chapter_contents)) {
                $chapter_contents = [];
            }
            $items = [];
            foreach ($chapter_contents as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                $cp = get_post($pid);
                if (!$cp) {
                    continue;
                }
                $pt = (string) get_post_type($cp);
                $type = 'lesson';
                if (PostTypes::QUIZ === $pt) {
                    $type = 'quiz';
                } elseif (PostTypes::ASSIGNMENT === $pt) {
                    $type = 'assignment';
                } elseif (PostTypes::QUESTION === $pt) {
                    $type = 'question';
                }
                $meta = [];
                if ($type === 'lesson') {
                    $meta['lesson_type'] = (string) get_post_meta($pid, '_sikshya_lesson_type', true);
                    $meta['duration'] = (string) get_post_meta($pid, '_sikshya_lesson_duration', true);
                } elseif ($type === 'quiz') {
                    $meta['time_limit'] = (int) get_post_meta($pid, '_sikshya_quiz_time_limit', true);
                } elseif ($type === 'assignment') {
                    $meta['points'] = (int) get_post_meta($pid, '_sikshya_assignment_points', true);
                }

                $items[] = [
                    'id' => $pid,
                    'title' => $cp->post_title,
                    'type' => $type,
                    'status' => $cp->post_status,
                    'meta' => $meta,
                ];
            }
            $tree[] = [
                'id' => $chapter_id,
                'title' => $p->post_title,
                'contents' => $items,
            ];
        }

        return new WP_REST_Response(['success' => true, 'data' => ['chapters' => $tree]], 200);
    }

    public function getCourseChapters(WP_REST_Request $request): WP_REST_Response
    {
        $course_id = (int) $request->get_param('course_id');
        if ($course_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid course.', 'sikshya')], 400);
        }

        $posts = get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_parent' => $course_id,
                'post_status' => 'any',
                'numberposts' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => '_sikshya_order',
                'order' => 'ASC',
            ]
        );

        $rows = [];
        foreach ($posts as $p) {
            $rows[] = [
                'id' => (int) $p->ID,
                'title' => $p->post_title,
            ];
        }

        return new WP_REST_Response(['success' => true, 'data' => $rows], 200);
    }

    public function saveCourseBuilder(WP_REST_Request $request)
    {
        $params = $this->jsonBody($request);
        $course_status = isset($params['course_status']) ? (string) $params['course_status'] : 'draft';
        unset($params['course_status']);

        $service = $this->plugin->getService('courseBuilder');
        if (!$service instanceof CourseBuilderService) {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Course builder service is not available.', 'sikshya'), 'code' => 'service_missing'],
                500
            );
        }

        $result = $service->save($params, $course_status);

        if (empty($result['success'])) {
            $status = !empty($result['errors']) ? 422 : 400;
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => $result['message'] ?? __('Request failed', 'sikshya'),
                    'errors' => $result['errors'] ?? null,
                    'field_errors' => $result['field_errors'] ?? null,
                    'code' => $result['code'] ?? 'error',
                ],
                $status
            );
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? [],
            ],
            200
        );
    }

    /**
     * Directly set the course type meta (e.g. marking a new draft as a bundle).
     * Bypasses the full builder save/validate cycle — used immediately after post creation.
     */
    public function setCourseType(WP_REST_Request $request): WP_REST_Response
    {
        $course_id = (int) $request->get_param('course_id');
        $type = sanitize_key((string) $request->get_param('course_type'));

        if ($course_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid course ID.', 'sikshya')], 400);
        }

        $post = get_post($course_id);
        if (!$post || $post->post_type !== \Sikshya\Constants\PostTypes::COURSE) {
            return new WP_REST_Response(['success' => false, 'message' => __('Course not found.', 'sikshya')], 404);
        }

        update_post_meta($course_id, '_sikshya_course_type', $type);

        if ($type === 'bundle') {
            if (get_post_meta($course_id, '_sikshya_bundle_visible_in_listing', true) === '') {
                update_post_meta($course_id, '_sikshya_bundle_visible_in_listing', '1');
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'course_id' => $course_id,
                'course_type' => $type,
                'is_bundle' => $type === 'bundle',
            ],
        ], 200);
    }

    public function createContent(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $out = $svc->createContent($this->jsonBody($request));

        return new WP_REST_Response(
            [
                'success' => !empty($out['success']),
                'message' => $out['message'] ?? '',
                'data' => $out['data'] ?? [],
            ],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function linkContent(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $out = $svc->linkContentToChapter((int) ($p['content_id'] ?? 0), (int) ($p['chapter_id'] ?? 0));

        return new WP_REST_Response(
            [
                'success' => !empty($out['success']),
                'message' => $out['message'] ?? '',
                'data' => $out['data'] ?? [],
            ],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function saveContentItem(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $item_id = (int) ($p['item_id'] ?? 0);
        $data = isset($p['data']) && is_array($p['data']) ? $p['data'] : [];
        $out = $svc->saveContentItem($item_id, $data);

        return new WP_REST_Response(
            [
                'success' => !empty($out['success']),
                'message' => $out['message'] ?? '',
            ],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function saveChapterOrder(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $out = $svc->saveChapterOrder((int) ($p['course_id'] ?? 0), isset($p['chapter_order']) && is_array($p['chapter_order']) ? $p['chapter_order'] : []);

        return new WP_REST_Response(
            ['success' => !empty($out['success']), 'message' => $out['message'] ?? ''],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function saveLessonOrder(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $out = $svc->saveLessonOrder((int) ($p['chapter_id'] ?? 0), isset($p['lesson_order']) && is_array($p['lesson_order']) ? $p['lesson_order'] : []);

        return new WP_REST_Response(
            ['success' => !empty($out['success']), 'message' => $out['message'] ?? ''],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function saveOutlineStructure(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('curriculum');
        if (!$svc instanceof CurriculumService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $course_id = (int) ($p['course_id'] ?? 0);
        $chapter_order = isset($p['chapter_order']) && is_array($p['chapter_order']) ? $p['chapter_order'] : [];
        $blocks = isset($p['chapters']) && is_array($p['chapters']) ? $p['chapters'] : [];

        $out = $svc->saveOutlineStructure($course_id, $chapter_order, $blocks);

        return new WP_REST_Response(
            ['success' => !empty($out['success']), 'message' => $out['message'] ?? ''],
            !empty($out['success']) ? 200 : 400
        );
    }

    public function bulkDeleteCurriculumItems(WP_REST_Request $request): WP_REST_Response
    {
        $p = $this->jsonBody($request);
        $chapters = isset($p['chapters']) && is_array($p['chapters']) ? array_map('absint', $p['chapters']) : [];
        $lessons = isset($p['lessons']) && is_array($p['lessons']) ? array_map('absint', $p['lessons']) : [];

        $deleted = 0;
        $errors = [];

        foreach ($chapters as $id) {
            if ($id <= 0) {
                continue;
            }
            if (!wp_delete_post($id, true)) {
                $errors[] = "Failed to delete chapter ID: {$id}";
            } else {
                $deleted++;
            }
        }

        foreach ($lessons as $id) {
            if ($id <= 0) {
                continue;
            }
            if (!wp_delete_post($id, true)) {
                $errors[] = "Failed to delete lesson ID: {$id}";
            } else {
                $deleted++;
            }
        }

        if (!empty($errors)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Some items could not be deleted', 'errors' => $errors, 'deleted_count' => $deleted], 400);
        }

        return new WP_REST_Response(['success' => true, 'message' => "Successfully deleted {$deleted} items", 'deleted_count' => $deleted], 200);
    }

    public function getSettingsSchema(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('settings');
        if (!$svc instanceof SettingsManager) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $tabs = $svc->getAllSettings();
        // Annotate locked sections/fields (addons disabled, feature gate off) so the
        // React client can visually gate them without duplicating licensing state.
        $tabs = $svc->decorateSchemaGating($tabs);
        return new WP_REST_Response(
            [
                'success' => true,
                'data' => [
                    'tabs' => $tabs,
                    'meta' => [
                        'payment_gateways' => PaymentGatewayRegistry::clientPayload(),
                    ],
                ],
            ],
            200
        );
    }

    public function getSettingsValues(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('settings');
        if (!$svc instanceof SettingsManager) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $tab = sanitize_key((string) $request->get_param('tab'));
        $allowed = ['general','courses','lessons','quizzes','students','instructors','enrollment','progress','certificates','assignments','payment','email','notifications','integrations','permalinks','security','advanced'];
        if (!in_array($tab, $allowed, true)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid tab.', 'sikshya')], 400);
        }

        return new WP_REST_Response(['success' => true, 'data' => ['values' => $svc->exportTabSettings($tab)]], 200);
    }

    public function saveSettings(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('settings');
        if (!$svc instanceof SettingsManager) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $tab = sanitize_key((string) ($p['tab'] ?? 'general'));
        $values = isset($p['values']) && is_array($p['values']) ? $p['values'] : [];

        $allowed = ['general','courses','lessons','quizzes','students','instructors','enrollment','progress','certificates','assignments','payment','email','notifications','integrations','permalinks','security','advanced'];
        if (!in_array($tab, $allowed, true)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid tab.', 'sikshya')], 400);
        }

        // Ensure unchecked checkboxes are saved as '0'.
        $tab_settings = $svc->getTabSettings($tab);
        $checkbox_fields = [];
        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field) {
                    if (isset($field['key'], $field['type']) && $field['type'] === 'checkbox') {
                        $checkbox_fields[] = (string) $field['key'];
                    }
                }
            }
        }
        foreach ($checkbox_fields as $key) {
            if (!array_key_exists($key, $values)) {
                $values[$key] = '0';
            }
        }

        $ok = $svc->saveTabSettings($tab, $values);
        if (!$ok) {
            return new WP_REST_Response(['success' => false, 'message' => __('Failed to save settings.', 'sikshya')], 400);
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __('Settings saved.', 'sikshya'),
                'data' => ['values' => $svc->exportTabSettings($tab)],
            ],
            200
        );
    }

    public function resetSettings(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('settings');
        if (!$svc instanceof SettingsManager) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $p = $this->jsonBody($request);
        $tab = isset($p['tab']) ? sanitize_key((string) $p['tab']) : '';
        $allowed = ['general','courses','lessons','quizzes','students','instructors','enrollment','progress','certificates','assignments','payment','email','notifications','integrations','permalinks','security','advanced'];

        if ($tab !== '') {
            if (!in_array($tab, $allowed, true)) {
                return new WP_REST_Response(['success' => false, 'message' => __('Invalid tab.', 'sikshya')], 400);
            }
            $ok = $svc->resetTabSettings($tab);
            return new WP_REST_Response(
                ['success' => (bool) $ok, 'message' => $ok ? __('Settings reset.', 'sikshya') : __('Reset failed.', 'sikshya')],
                $ok ? 200 : 400
            );
        }

        $ok = $svc->resetAllSettings();
        return new WP_REST_Response(
            ['success' => (bool) $ok, 'message' => $ok ? __('Settings reset to default.', 'sikshya') : __('Reset failed.', 'sikshya')],
            $ok ? 200 : 400
        );
    }

    /**
     * Send a test HTML message through wp_mail (respects From/reply, optional SMTP, branded wrappers when licensed + add-on on).
     */
    public function postSettingsEmailTestDelivery(WP_REST_Request $request)
    {
        if (!Pro::feature('email_advanced_customization')) {
            return Pro::restFeatureRequired('email_advanced_customization');
        }
        if (!Addons::isEnabled('email_advanced_customization')) {
            return Pro::restAddonDisabled('email_advanced_customization');
        }

        $p = $this->jsonBody($request);
        $raw = isset($p['to']) ? (string) $p['to'] : '';
        $to = sanitize_email($raw);

        if ($to === '') {
            $user = wp_get_current_user();
            $to = $user && !empty($user->user_email) ? (string) $user->user_email : '';
        }

        if ($to === '' || !is_email($to)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __('Enter a valid recipient email address, or leave the field empty to use your account email.', 'sikshya'),
                ],
                400
            );
        }

        $mailer = $this->plugin->getService('mailer');
        if (!$mailer instanceof EmailNotificationService) {
            return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable.', 'sikshya')], 500);
        }

        $ok = $mailer->sendTestDeliveryEmail($to);
        if (!$ok) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __('Could not send the test email. Check From / SMTP settings and your host mail logs.', 'sikshya'),
                ],
                500
            );
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: recipient email address */
                    __('Test email sent to %s.', 'sikshya'),
                    $to
                ),
            ],
            200
        );
    }

    public function toolsAction(WP_REST_Request $request): WP_REST_Response
    {
        $p = $this->jsonBody($request);
        $action = sanitize_key((string) ($p['action_type'] ?? ''));

        switch ($action) {
            case 'clear_cache':
                wp_cache_flush();
                delete_transient('sikshya_course_stats');
                delete_transient('sikshya_user_stats');
                delete_transient('sikshya_revenue_stats');
                return new WP_REST_Response(['success' => true, 'message' => __('Cache cleared successfully', 'sikshya')], 200);
            case 'reset_settings':
                delete_option('sikshya_settings');
                return new WP_REST_Response(['success' => true, 'message' => __('Settings reset to default', 'sikshya')], 200);
            case 'system_info':
                global $wpdb;
                $theme = wp_get_theme();
                $plugins = (array) Settings::getRaw('active_plugins', []);

                return new WP_REST_Response(
                    [
                        'success' => true,
                        'data' => [
                            'site_url' => home_url('/'),
                            'is_multisite' => is_multisite(),
                            'wordpress_version' => get_bloginfo('version'),
                            'php_version' => PHP_VERSION,
                            'mysql_version' => $wpdb->db_version(),
                            'sikshya_version' => defined('SIKSHYA_VERSION') ? SIKSHYA_VERSION : '',
                            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
                            'memory_limit' => ini_get('memory_limit'),
                            'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '',
                            'max_execution_time' => ini_get('max_execution_time'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'post_max_size' => ini_get('post_max_size'),
                            'theme_name' => $theme->get('Name'),
                            'theme_stylesheet' => get_stylesheet(),
                            'active_plugins_count' => count($plugins),
                        ],
                    ],
                    200
                );
            case 'export_settings':
                $svc = $this->plugin->getService('settings');
                if (!$svc instanceof SettingsManager) {
                    return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
                }

                return new WP_REST_Response(
                    [
                        'success' => true,
                        'data' => $svc->exportAllSettings(),
                        'message' => __('Settings exported successfully', 'sikshya'),
                    ],
                    200
                );
            case 'import_settings':
                $svc = $this->plugin->getService('settings');
                if (!$svc instanceof SettingsManager) {
                    return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
                }

                $settings_payload = $p['settings'] ?? null;
                if (!is_array($settings_payload)) {
                    return new WP_REST_Response(
                        ['success' => false, 'message' => __('Invalid settings payload.', 'sikshya')],
                        400
                    );
                }

                $overwrite = !empty($p['overwrite']);
                $ok = $svc->importSettings($settings_payload, $overwrite);

                return new WP_REST_Response(
                    [
                        'success' => (bool) $ok,
                        'message' => $ok
                            ? __('Settings imported successfully', 'sikshya')
                            : __('Some settings could not be imported.', 'sikshya'),
                    ],
                    $ok ? 200 : 400
                );
            case 'import_sample_data':
                if (!defined('SIKSHYA_PLUGIN_FILE')) {
                    return new WP_REST_Response(
                        ['success' => false, 'message' => __('Plugin bootstrap unavailable.', 'sikshya')],
                        500
                    );
                }

                $pack_key = sanitize_key((string) ($p['pack'] ?? 'default'));
                $filename = $pack_key === 'default' ? 'sample-lms.json' : $pack_key . '.json';
                $path = dirname(SIKSHYA_PLUGIN_FILE) . '/sample-data/' . $filename;
                $data = SampleDataImporter::loadJsonFile($path);
                if ($data === null) {
                    return new WP_REST_Response(
                        ['success' => false, 'message' => __('Sample data file not found or invalid.', 'sikshya')],
                        400
                    );
                }

                $curriculum = $this->plugin->getService('curriculum');
                $actions = $this->plugin->getService('courseBuilderUi');
                if (!$curriculum instanceof CurriculumService || !$actions instanceof CourseCurriculumActions) {
                    return new WP_REST_Response(
                        ['success' => false, 'message' => __('Service unavailable.', 'sikshya')],
                        500
                    );
                }

                $importer = new SampleDataImporter($curriculum, $actions);
                $out = $importer->importPack($data);

                return new WP_REST_Response(
                    [
                        'success' => (bool) $out['success'],
                        'message' => (string) $out['message'],
                        'data' => ['counts' => $out['counts'] ?? []],
                    ],
                    $out['success'] ? 200 : 400
                );
            case 'export_data':
                $export_type = sanitize_key((string) ($p['export_type'] ?? 'courses'));
                $map = [
                    'courses' => PostTypes::COURSE,
                    'lessons' => PostTypes::LESSON,
                    'quizzes' => PostTypes::QUIZ,
                    'assignments' => PostTypes::ASSIGNMENT,
                    'questions' => PostTypes::QUESTION,
                    'chapters' => PostTypes::CHAPTER,
                    'certificates' => PostTypes::CERTIFICATE,
                ];

                if ($export_type === 'settings') {
                    $svc = $this->plugin->getService('settings');
                    if (!$svc instanceof SettingsManager) {
                        return new WP_REST_Response(['success' => false, 'message' => __('Service unavailable', 'sikshya')], 500);
                    }

                    return new WP_REST_Response(
                        [
                            'success' => true,
                            'data' => $svc->exportAllSettings(),
                            'message' => __('Data exported successfully', 'sikshya'),
                        ],
                        200
                    );
                }

                if (!isset($map[$export_type])) {
                    return new WP_REST_Response(['success' => false, 'message' => __('Invalid export type', 'sikshya')], 400);
                }

                $data = $this->exportPostsForTools($map[$export_type]);

                return new WP_REST_Response(
                    [
                        'success' => true,
                        'data' => $data,
                        'export_type' => $export_type,
                        'message' => __('Data exported successfully', 'sikshya'),
                    ],
                    200
                );
            case 'import_data':
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => __(
                            'Content import is not available yet. Use Import settings with a settings export file.',
                            'sikshya'
                        ),
                    ],
                    400
                );
            default:
                return new WP_REST_Response(['success' => false, 'message' => __('Invalid action', 'sikshya')], 400);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportPostsForTools(string $post_type): array
    {
        $posts = get_posts(
            [
                'post_type' => $post_type,
                'numberposts' => -1,
                'post_status' => 'any',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]
        );

        $out = [];

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $meta = [];
            foreach (get_post_meta($post->ID) as $key => $values) {
                if (!is_string($key) || !isset($values[0])) {
                    continue;
                }
                if (in_array($key, ['_edit_lock', '_edit_last'], true)) {
                    continue;
                }
                if (!preg_match('/(_sikshya_|_sik_)/', $key)) {
                    continue;
                }
                $meta[$key] = maybe_unserialize($values[0]);
            }

            $out[] = [
                'post_type' => $post->post_type,
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'date_gmt' => $post->post_date_gmt,
                'modified_gmt' => $post->post_modified_gmt,
                'featured_media' => (int) get_post_thumbnail_id($post->ID),
                'meta' => $meta,
            ];
        }

        return $out;
    }

    public function getCourseCategory(WP_REST_Request $request): WP_REST_Response
    {
        $svc = $this->plugin->getService('categoryService');
        if (!$svc instanceof CategoryService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
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
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
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
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        $id = (int) $request->get_param('id');
        $r = $svc->delete($id);
        if (empty($r['ok'])) {
            return new WP_REST_Response(['success' => false, 'message' => $r['message'] ?? ''], 400);
        }

        return new WP_REST_Response(['success' => true, 'message' => $r['message'] ?? ''], 200);
    }

    /**
     * Live dashboard payload (same shape as {@see ReactAdminConfig::dashboardInitialData()}).
     */
    public function getAdminOverview(): WP_REST_Response
    {
        ReportController::clearReportsSnapshotCache();

        return new WP_REST_Response(ReactAdminConfig::dashboardInitialData(), 200);
    }

    /**
     * Free vs Pro feature catalog + enabled flags (admin UI ships in core; Pro unlocks behaviour).
     */
    public function getLicensingPayload(): WP_REST_Response
    {
        return new WP_REST_Response(Pro::getClientPayload(), 200);
    }

    /**
     * Alerts, licensing, and Pro version flags for the React shell (refresh after licence changes).
     */
    public function getShellMeta(WP_REST_Request $request): WP_REST_Response
    {
        $view = $request->get_param('view');
        $page_key = is_string($view) && $view !== '' ? sanitize_key($view) : 'dashboard';

        return new WP_REST_Response(ReactAdminConfig::shellBootstrap($page_key), 200);
    }

    /**
     * Reports chart + stats for refresh without full page reload.
     */
    public function getAdminReportsSnapshot(): WP_REST_Response
    {
        ReportController::clearReportsSnapshotCache();

        return new WP_REST_Response(ReactAdminConfig::reportsInitialData(), 200);
    }

    /**
     * Paginated enrollments with learner and course labels for the React admin.
     */
    public function getAdminEnrollments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sikshya_enrollments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return new WP_REST_Response(
                [
                    'success' => true,
                    'enrollments' => [],
                    'total' => 0,
                    'pages' => 0,
                    'page' => 1,
                    'per_page' => 20,
                    'table_missing' => true,
                ],
                200
            );
        }

        $per_page = max(1, min(100, absint($request->get_param('per_page') ?: 20)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;
        $status = $request->get_param('status');
        $course_id = $request->get_param('course_id');
        $search = sanitize_text_field((string) $request->get_param('search'));

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $course_type = PostTypes::COURSE;

        $where = ['1=1'];
        $prepare = [];

        if ($status !== null && $status !== '') {
            $where[] = 'e.status = %s';
            $prepare[] = sanitize_key((string) $status);
        }
        if ($course_id) {
            $where[] = 'e.course_id = %d';
            $prepare[] = (int) $course_id;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR p.post_title LIKE %s)';
            array_push($prepare, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(DISTINCT e.id) FROM {$table} e
            LEFT JOIN {$users_table} u ON e.user_id = u.ID
            LEFT JOIN {$posts_table} p ON e.course_id = p.ID AND p.post_type = %s
            WHERE {$where_sql}";
        $count_prepare = array_merge([$course_type], $prepare);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from validated fragments above.
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_prepare));

        $list_prepare = array_merge([$course_type], $prepare, [$per_page, $offset]);
        $list_sql = "SELECT e.id, e.user_id, e.course_id, e.status, e.enrolled_date, e.payment_method, e.amount,
            u.display_name AS learner_name, u.user_email AS learner_email, p.post_title AS course_title
            FROM {$table} e
            LEFT JOIN {$users_table} u ON e.user_id = u.ID
            LEFT JOIN {$posts_table} p ON e.course_id = p.ID AND p.post_type = %s
            WHERE {$where_sql}
            ORDER BY e.enrolled_date DESC
            LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from validated fragments above.
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_prepare));

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'status' => (string) $row->status,
                'enrolled_date' => (string) $row->enrolled_date,
                'payment_method' => (string) $row->payment_method,
                'amount' => isset($row->amount) ? (float) $row->amount : 0.0,
                'learner_name' => (string) ($row->learner_name ?? ''),
                'learner_email' => (string) ($row->learner_email ?? ''),
                'course_title' => (string) ($row->course_title ?? ''),
            ];
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'enrollments' => $items,
                'total' => $total,
                'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
                'page' => $page,
                'per_page' => $per_page,
            ],
            200
        );
    }

    /**
     * Paginated quiz attempts with learner + quiz/course labels for the React admin.
     */
    public function getAdminQuizAttempts(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sikshya_quiz_attempts';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return new WP_REST_Response(
                [
                    'success' => true,
                    'attempts' => [],
                    'total' => 0,
                    'pages' => 0,
                    'page' => 1,
                    'per_page' => 30,
                    'table_missing' => true,
                ],
                200
            );
        }

        $per_page = max(1, min(100, absint($request->get_param('per_page') ?: 30)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;

        $quiz_id = (int) ($request->get_param('quiz_id') ?: 0);
        $course_id = (int) ($request->get_param('course_id') ?: 0);
        $user_id = (int) ($request->get_param('user_id') ?: 0);
        $status = sanitize_key((string) ($request->get_param('status') ?: ''));
        $search = sanitize_text_field((string) $request->get_param('search'));

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;

        $where = ['1=1'];
        $prepare = [];

        if ($quiz_id > 0) {
            $where[] = 'a.quiz_id = %d';
            $prepare[] = $quiz_id;
        }
        if ($course_id > 0) {
            $where[] = 'a.course_id = %d';
            $prepare[] = $course_id;
        }
        if ($user_id > 0) {
            $where[] = 'a.user_id = %d';
            $prepare[] = $user_id;
        }
        if ($status !== '') {
            $where[] = 'a.status = %s';
            $prepare[] = $status;
        }
        if ($search !== '') {
            // Match by learner name/email or quiz/course title.
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR q.post_title LIKE %s OR c.post_title LIKE %s)';
            array_push($prepare, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);
        $table_sql = esc_sql($table);

        // Total count.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped; values bound.
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_sql}` a LEFT JOIN {$users_table} u ON u.ID = a.user_id LEFT JOIN {$posts_table} q ON q.ID = a.quiz_id LEFT JOIN {$posts_table} c ON c.ID = a.course_id WHERE {$where_sql}", ...$prepare));
        $pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

        // Rows.
        $prepare_rows = array_merge($prepare, [$per_page, $offset]);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped; values bound.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.user_id, a.quiz_id, a.course_id, a.attempt_number, a.score, a.status, a.started_at, a.completed_at,
                        u.display_name AS user_name, u.user_email AS user_email,
                        q.post_title AS quiz_title,
                        c.post_title AS course_title
                 FROM `{$table_sql}` a
                 LEFT JOIN {$users_table} u ON u.ID = a.user_id
                 LEFT JOIN {$posts_table} q ON q.ID = a.quiz_id
                 LEFT JOIN {$posts_table} c ON c.ID = a.course_id
                 WHERE {$where_sql}
                 ORDER BY a.completed_at DESC, a.id DESC
                 LIMIT %d OFFSET %d",
                ...$prepare_rows
            ),
            ARRAY_A
        );

        $global_attempts_limit = (int) Settings::get('quiz_attempts_limit', 1);
        if ($global_attempts_limit < 0) {
            $global_attempts_limit = 0;
        }
        $repo = new QuizAttemptRepository();

        $out = [];
        foreach ($rows as $r) {
            $qid = isset($r['quiz_id']) ? (int) $r['quiz_id'] : 0;
            $uid = isset($r['user_id']) ? (int) $r['user_id'] : 0;
            $per_quiz = $qid > 0 ? (int) get_post_meta($qid, '_sikshya_quiz_attempts_allowed', true) : 0;
            $limit = $per_quiz > 0 ? $per_quiz : $global_attempts_limit;
            if ($limit < 0) {
                $limit = 0;
            }
            $used = ($uid > 0 && $qid > 0) ? $repo->countAttemptsForUserQuiz($uid, $qid) : 0;
            $remaining = $limit > 0 ? max(0, $limit - $used) : null;

            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'user_id' => $uid,
                'user_name' => (string) ($r['user_name'] ?? ''),
                'user_email' => (string) ($r['user_email'] ?? ''),
                'quiz_id' => $qid,
                'quiz_title' => (string) ($r['quiz_title'] ?? ''),
                'course_id' => (int) ($r['course_id'] ?? 0),
                'course_title' => (string) ($r['course_title'] ?? ''),
                'attempt_number' => (int) ($r['attempt_number'] ?? 0),
                'score' => (float) ($r['score'] ?? 0),
                'status' => (string) ($r['status'] ?? ''),
                'started_at' => (string) ($r['started_at'] ?? ''),
                'completed_at' => (string) ($r['completed_at'] ?? ''),
                'attempts_used' => (int) $used,
                'attempts_limit' => (int) $limit,
                'attempts_remaining' => $remaining,
                'is_locked' => $limit > 0 ? $used >= $limit : false,
            ];
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'attempts' => $out,
                'total' => $total,
                'pages' => $pages,
                'page' => $page,
                'per_page' => $per_page,
                'table_missing' => false,
            ],
            200
        );
    }

    /**
     * Paginated payments with payer and course labels for the React admin.
     */
    public function getAdminPayments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sikshya_payments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return new WP_REST_Response(
                [
                    'success' => true,
                    'payments' => [],
                    'total' => 0,
                    'pages' => 0,
                    'page' => 1,
                    'per_page' => 30,
                    'table_missing' => true,
                ],
                200
            );
        }

        $per_page = max(1, min(100, absint($request->get_param('per_page') ?: 30)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $course_type = PostTypes::COURSE;

        $sql = $wpdb->prepare(
            "SELECT py.id, py.user_id, py.course_id, py.amount, py.currency, py.payment_method, py.transaction_id, py.status, py.payment_date,
                u.display_name AS payer_name, u.user_email AS payer_email, p.post_title AS course_title
            FROM {$table} py
            LEFT JOIN {$wpdb->users} u ON py.user_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON py.course_id = p.ID AND p.post_type = %s
            ORDER BY py.payment_date DESC
            LIMIT %d OFFSET %d",
            $course_type,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($sql);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'amount' => isset($row->amount) ? (float) $row->amount : 0.0,
                'currency' => (string) ($row->currency ?? ''),
                'payment_method' => (string) ($row->payment_method ?? ''),
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'status' => (string) ($row->status ?? ''),
                'payment_date' => (string) ($row->payment_date ?? ''),
                'payer_name' => (string) ($row->payer_name ?? ''),
                'payer_email' => (string) ($row->payer_email ?? ''),
                'course_title' => (string) ($row->course_title ?? ''),
            ];
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'payments' => $items,
                'total' => $total,
                'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
                'page' => $page,
                'per_page' => $per_page,
            ],
            200
        );
    }

    /**
     * Issued learner certificates (table-backed).
     */
    public function getIssuedCertificates(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new \Sikshya\Database\Repositories\CertificateRepository();
        $per_page = max(1, min(200, absint($request->get_param('per_page') ?: 50)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;

        $rows = $repo->findAllPaged($per_page, $offset);
        $out = [];

        $permalinks = PermalinkService::get();
        $base = isset($permalinks['rewrite_base_certificate']) ? PermalinkService::sanitizeSlug((string) $permalinks['rewrite_base_certificate']) : 'certificates';

        foreach ($rows as $row) {
            $code = (string) ($row->verification_code ?? '');
            $template_post_id = isset($row->template_post_id) ? (int) $row->template_post_id : 0;
            $verify_url = '';
            if ($code !== '') {
                $clean = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $code) ?? '');
                if ($clean !== '') {
                    $verify_url = PermalinkService::isPlainPermalinks()
                        ? home_url('/' . $base . '/?hash=' . rawurlencode($clean))
                        : user_trailingslashit(home_url('/' . $base . '/' . $clean));
                }
            }

            $out[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'certificate_number' => (string) $row->certificate_number,
                'issued_date' => (string) $row->issued_date,
                'status' => (string) $row->status,
                'verification_code' => $code,
                'template_post_id' => $template_post_id > 0 ? $template_post_id : null,
                'verify_url' => $verify_url,
                'document_url' => $verify_url,
            ];
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'certificates' => $out,
                'page' => $page,
                'per_page' => $per_page,
            ],
            200
        );
    }

    /**
     * Revoke a certificate by ID.
     */
    public function revokeIssuedCertificate(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_id', 'message' => __('Invalid certificate.', 'sikshya')],
                400
            );
        }

        $repo = new \Sikshya\Database\Repositories\CertificateRepository();
        $ok = $repo->updateStatus($id, 'revoked');

        return new WP_REST_Response(
            [
                'ok' => $ok,
                'message' => $ok ? __('Certificate revoked.', 'sikshya') : __('Could not revoke.', 'sikshya'),
            ],
            $ok ? 200 : 500
        );
    }

    /**
     * Checkout orders (normalized commerce ledger).
     */
    public function getAdminOrders(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new \Sikshya\Database\Repositories\OrderRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                [
                    'success' => true,
                    'orders' => [],
                    'total' => 0,
                    'table_missing' => true,
                ],
                200
            );
        }

        $per_page = max(1, min(100, absint($request->get_param('per_page') ?: 30)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;

        $result = $repo->findAllPaged($per_page, $offset);
        $items = [];

        foreach ($result['rows'] as $row) {
            $line_courses = [];
            foreach ($repo->getItems((int) $row->id) as $it) {
                $cid = (int) $it->course_id;
                $title = $cid > 0 ? get_the_title($cid) : '';
                $line_courses[] = [
                    'course_id' => $cid,
                    'course_title' => $title ?: '',
                    'line_total' => isset($it->line_total) ? (float) $it->line_total : 0.0,
                ];
            }

            $items[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'status' => (string) $row->status,
                'currency' => (string) $row->currency,
                'subtotal' => isset($row->subtotal) ? (float) $row->subtotal : 0.0,
                'discount_total' => isset($row->discount_total) ? (float) $row->discount_total : 0.0,
                'total' => isset($row->total) ? (float) $row->total : 0.0,
                'gateway' => (string) $row->gateway,
                'gateway_intent_id' => (string) ($row->gateway_intent_id ?? ''),
                'public_token' => (string) ($row->public_token ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
                'payer_name' => (string) ($row->payer_name ?? ''),
                'payer_email' => (string) ($row->payer_email ?? ''),
                'lines' => $line_courses,
            ];
        }

        $total = (int) $result['total'];
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

        return new WP_REST_Response(
            [
                'success' => true,
                'orders' => $items,
                'total' => $total,
                'pages' => $pages,
                'page' => $page,
                'per_page' => $per_page,
            ],
            200
        );
    }

    /**
     * Fulfill a pending / on-hold order (offline payments after manual verification).
     */
    public function markAdminOrderPaid(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request['id'];
        $repo = new OrderRepository();
        $order = $repo->findById($id);
        if (!$order) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Order not found.', 'sikshya')],
                404
            );
        }

        if ((string) $order->status === 'paid') {
            return new WP_REST_Response(
                ['ok' => true, 'message' => __('Order is already marked paid.', 'sikshya')],
                200
            );
        }

        if (!in_array((string) $order->status, ['pending', 'on-hold'], true)) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('This order cannot be marked paid from its current status.', 'sikshya')],
                400
            );
        }

        $gw = (string) $order->gateway;
        if ($gw !== 'offline' && $gw !== '') {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Only offline (or unset gateway) orders can be marked paid here. Use the payment provider for Stripe or PayPal.', 'sikshya')],
                400
            );
        }

        $course = $this->plugin->getService('course');
        if (!$course instanceof CourseService) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Course service unavailable.', 'sikshya')],
                500
            );
        }

        $fulfill = new OrderFulfillmentService($repo, new PaymentRepository(), $course);
        $fulfill->fulfillPaidOrder($id);

        return new WP_REST_Response(
            ['ok' => true, 'message' => __('Order marked paid and enrollments created.', 'sikshya')],
            200
        );
    }

    /**
     * Coupon codes (basic CRUD list + create).
     */
    public function getAdminCoupons(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new \Sikshya\Database\Repositories\CouponRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                [
                    'ok' => true,
                    'coupons' => [],
                    'table_missing' => true,
                ],
                200
            );
        }

        $rows = $repo->findAll(200, 0);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'discount_type' => (string) $row->discount_type,
                'discount_value' => (float) $row->discount_value,
                'max_uses' => (int) $row->max_uses,
                'used_count' => (int) $row->used_count,
                'expires_at' => $row->expires_at ?? null,
                'status' => (string) $row->status,
            ];
        }

        return new WP_REST_Response(['ok' => true, 'coupons' => $out], 200);
    }

    /**
     * Create a coupon (admin UI).
     */
    public function createAdminCoupon(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new \Sikshya\Database\Repositories\CouponRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Coupons table not installed.', 'sikshya')],
                500
            );
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $code = isset($params['code']) ? (string) $params['code'] : '';
        if (trim($code) === '') {
            return new WP_REST_Response(['ok' => false, 'message' => __('Code is required.', 'sikshya')], 400);
        }

        $id = $repo->createAdminCoupon(
            [
                'code' => $code,
                'discount_type' => $params['discount_type'] ?? 'percent',
                'discount_value' => $params['discount_value'] ?? 0,
                'max_uses' => $params['max_uses'] ?? 0,
                'expires_at' => $params['expires_at'] ?? null,
                'status' => $params['status'] ?? 'active',
            ]
        );

        if ($id <= 0) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Could not create coupon.', 'sikshya')], 500);
        }

        return new WP_REST_Response(['ok' => true, 'id' => $id], 201);
    }
}
