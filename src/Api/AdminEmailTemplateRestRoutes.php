<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Services\EmailTemplateMerge;
use Sikshya\Services\EmailTemplateStore;
use Sikshya\Services\EmailNotificationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin email template CRUD (React settings → Templates tab).
 *
 * @package Sikshya\Api
 */
final class AdminEmailTemplateRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $ns = 'sikshya/v1';

        register_rest_route($ns, '/admin/email-templates', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_templates'],
                'permission_callback' => [$this, 'permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_template'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);

        register_rest_route($ns, '/admin/email-template-bulk', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulk_templates'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);

        register_rest_route($ns, '/admin/email-templates/(?P<id>[a-zA-Z0-9_\-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_template'],
                'permission_callback' => [$this, 'permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'patch_template'],
                'permission_callback' => [$this, 'permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_template'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);

        register_rest_route($ns, '/admin/email-templates/(?P<id>[a-zA-Z0-9_\-]+)/preview', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'preview_template'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);
    }

    /**
     * @return bool|WP_Error
     */
    public function permission()
    {
        if (current_user_can('manage_options') || current_user_can('manage_sikshya')) {
            return true;
        }

        return new WP_Error('rest_forbidden', __('You do not have permission to edit email templates.', 'sikshya'), ['status' => 403]);
    }

    public function list_templates(): WP_REST_Response
    {
        return new WP_REST_Response(['templates' => EmailTemplateStore::listMerged()], 200);
    }

    /**
     * Bulk enable, disable, or delete custom templates.
     *
     * @param array{action?: string, ids?: mixed} $params
     */
    public function bulk_templates(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $action = isset($params['action']) ? sanitize_key((string) $params['action']) : '';
        $raw_ids = isset($params['ids']) && is_array($params['ids']) ? $params['ids'] : [];
        $ids = [];
        foreach ($raw_ids as $rid) {
            $sid = sanitize_text_field((string) $rid);
            if ($sid !== '') {
                $ids[] = $sid;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($action === '' || $ids === []) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_request', 'message' => __('Action and template ids are required.', 'sikshya')],
                400
            );
        }

        if (!in_array($action, ['enable', 'disable', 'delete'], true)) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'invalid_action', 'message' => __('Invalid bulk action.', 'sikshya')],
                400
            );
        }

        $processed = 0;
        $skipped = [];

        foreach ($ids as $id) {
            if ($action === 'delete') {
                $ok = EmailTemplateStore::deleteCustom($id);
                if (is_wp_error($ok)) {
                    /** @var WP_Error $ok */
                    $skipped[] = ['id' => $id, 'message' => $ok->get_error_message()];
                    continue;
                }
                ++$processed;
                continue;
            }

            $enabled = $action === 'enable';
            $row = EmailTemplateStore::getRow($id);
            $is_custom = is_array($row) && (string) ($row['template_type'] ?? '') === 'custom';

            if ($is_custom) {
                $ok = EmailTemplateStore::updateCustom($id, ['enabled' => $enabled]);
            } else {
                $ok = EmailTemplateStore::updateSystem($id, ['enabled' => $enabled]);
            }

            if (is_wp_error($ok)) {
                /** @var WP_Error $ok */
                $skipped[] = ['id' => $id, 'message' => $ok->get_error_message()];
                continue;
            }
            ++$processed;
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'processed' => $processed,
                'skipped' => $skipped,
            ],
            200
        );
    }

    public function get_template(WP_REST_Request $request): WP_REST_Response
    {
        $id = (string) $request->get_param('id');
        $merged = EmailTemplateStore::getMerged($id);
        if ($merged === null) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'not_found', 'message' => __('Template not found.', 'sikshya')],
                404
            );
        }

        return new WP_REST_Response($merged, 200);
    }

    public function patch_template(WP_REST_Request $request)
    {
        $id = (string) $request->get_param('id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $row = EmailTemplateStore::getRow($id);
        $is_custom = is_array($row) && (string) ($row['template_type'] ?? '') === 'custom';

        if ($is_custom) {
            $ok = EmailTemplateStore::updateCustom($id, $body);
        } else {
            $ok = EmailTemplateStore::updateSystem($id, $body);
        }

        if (is_wp_error($ok)) {
            /** @var WP_Error $ok */
            return $ok;
        }

        $merged = EmailTemplateStore::getMerged($id);
        if ($merged === null) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'not_found', 'message' => __('Template not found.', 'sikshya')],
                404
            );
        }

        return new WP_REST_Response($merged, 200);
    }

    public function create_template(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $out = EmailTemplateStore::createCustom($body);
        if (is_wp_error($out)) {
            /** @var WP_Error $out */
            return $out;
        }

        $merged = EmailTemplateStore::getMerged($out['id']);
        if ($merged === null) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'server_error', 'message' => __('Could not load template.', 'sikshya')],
                500
            );
        }

        return new WP_REST_Response($merged, 201);
    }

    public function delete_template(WP_REST_Request $request)
    {
        $id = (string) $request->get_param('id');
        $ok = EmailTemplateStore::deleteCustom($id);
        if (is_wp_error($ok)) {
            /** @var WP_Error $ok */
            return $ok;
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function preview_template(WP_REST_Request $request)
    {
        $id = (string) $request->get_param('id');
        $merged = EmailTemplateStore::getMerged($id);
        if ($merged === null) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'not_found', 'message' => __('Template not found.', 'sikshya')],
                404
            );
        }

        if (!empty($merged['locked'])) {
            $reason = isset($merged['locked_reason']) && (string) $merged['locked_reason'] !== ''
                ? (string) $merged['locked_reason']
                : __('Enable the add-on and license to preview this template.', 'sikshya');

            return new WP_REST_Response(
                [
                    'ok' => false,
                    'code' => 'sikshya_addon_disabled',
                    'message' => $reason,
                ],
                403
            );
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $subject = isset($body['subject']) ? (string) $body['subject'] : (string) ($merged['subject'] ?? '');
        $html = isset($body['body_html']) ? (string) $body['body_html'] : (string) ($merged['body_html'] ?? '');

        $mailer = $this->plugin->getService('mailer');
        if (!$mailer instanceof EmailNotificationService) {
            return new WP_REST_Response(
                ['ok' => false, 'code' => 'mailer_missing', 'message' => __('Mailer unavailable.', 'sikshya')],
                500
            );
        }

        $sample = $mailer->buildSampleMergeContext();
        $subject_done = EmailTemplateMerge::apply($subject, $sample);
        $inner = EmailTemplateMerge::apply($html, $sample);
        $wrapped = $mailer->previewWrapHtml($inner);

        return new WP_REST_Response(
            [
                'subject' => $subject_done,
                'html' => $wrapped,
            ],
            200
        );
    }
}
