<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Admin\SetupWizardController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Setup-wizard admin routes — step save + bundled sample-course import.
 *
 * Extracted from {@see \Sikshya\Api\AdminRestRoutes}. Owns `/sikshya/v1/admin/setup-wizard/step`
 * (POST) and `/sikshya/v1/admin/setup-wizard/sample-import` (POST). Permission is `manage_options`
 * via {@see AbstractAdminRestController::permissionTools()} (the setup wizard is administrator-only).
 *
 * Route paths and response shapes preserved 1:1 with the original implementation.
 *
 * @package Sikshya\Api\Admin
 */
final class SetupWizardRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/setup-wizard/step', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveSetupWizardStep'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);

        register_rest_route($namespace, '/admin/setup-wizard/sample-import', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'importSetupWizardSample'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);
    }

    /**
     * Auto-save a single setup wizard step (drives "Next" + shareable ?step= URLs).
     */
    public function saveSetupWizardStep(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }
        $step = isset($params['step']) ? absint($params['step']) : 0;
        if ($step < 1 || $step > 5) {
            return new WP_Error(
                'invalid_step',
                __('Choose a valid step (1–5).', 'sikshya'),
                ['status' => 400]
            );
        }
        $r = SetupWizardController::processStep($step, $params, $this->plugin);
        if (!$r['success']) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'errors' => $r['errors'],
                ],
                400
            );
        }
        $next_url = $step < 5
            ? SetupWizardController::adminUrl($step + 1)
            : SetupWizardController::doneUrl();
        $messages = [
            1 => __('You can continue to the next page.', 'sikshya'),
            2 => __('Your public page words are saved.', 'sikshya'),
            3 => __('Your currency settings are saved.', 'sikshya'),
            4 => __('Your lesson link style is saved.', 'sikshya'),
            5 => __('Sikshya is ready to use.', 'sikshya'),
        ];

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => $messages[ $step ] ?? '',
                'next_url' => $next_url,
            ],
            200
        );
    }

    /**
     * Setup wizard "Add sample course" button.
     *
     * Triggers a one-shot import of the bundled `default` sample pack and
     * stashes the result in a per-user transient so the celebration screen
     * can summarize what was created. Returns a normalized payload to the JS
     * caller so the inline UI can render success / failure immediately.
     */
    public function importSetupWizardSample(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        $payload = SetupWizardController::importBundledSampleCourse($this->plugin);

        return new WP_REST_Response(
            [
                'success' => (bool) $payload['success'],
                'message' => (string) $payload['message'],
                'data' => [
                    'counts' => isset($payload['counts']) && is_array($payload['counts']) ? $payload['counts'] : [],
                ],
            ],
            $payload['success'] ? 200 : 400
        );
    }
}
