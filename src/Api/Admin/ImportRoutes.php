<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Import\ImportRunner;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin routes for importing from another LMS.
 *
 * `/admin/import/sources` (GET), `/admin/import/run` (POST) and
 * `/admin/import/reset` (POST). Administrator-only: an import writes courses,
 * enrolments and completions, which is not a thing an instructor does to a
 * whole site.
 *
 * The run route does **one batch** and returns where it got to. The screen
 * calls it again until `finished` — which is what lets a four-hundred-course
 * site migrate over a few minutes of polling instead of one request that no
 * host will hold open.
 *
 * @package Sikshya\Api\Admin
 */
final class ImportRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/import/sources', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'sources'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);

        register_rest_route($namespace, '/admin/import/run', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'run'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);

        register_rest_route($namespace, '/admin/import/reset', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'reset'],
                'permission_callback' => [$this, 'permissionTools'],
            ],
        ]);
    }

    public function sources(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return new WP_REST_Response([
            'ok' => true,
            'sources' => ImportRunner::available(),
            'state' => ImportRunner::state(),
        ], 200);
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        $source = (string) $request->get_param('source');
        $dryRun = (bool) $request->get_param('dry_run');
        $batch = (int) ($request->get_param('batch_size') ?? 0);

        $result = ImportRunner::runBatch($source, $dryRun, $batch);

        // 200 even on a refusal: the body says what happened, and the screen
        // shows it. A 400 here would be read as "the request was malformed"
        // when the real answer is "there is nothing from that LMS here".
        return new WP_REST_Response($result, 200);
    }

    public function reset(WP_REST_Request $request): WP_REST_Response
    {
        $source = (string) $request->get_param('source');
        $dryRun = (bool) $request->get_param('dry_run');

        ImportRunner::resetProgress($source, $dryRun);

        return new WP_REST_Response([
            'ok' => true,
            // Said plainly, because "reset" could reasonably be read as
            // "undo the import".
            'message' => __(
                'Progress cleared. The next run looks at every course again; nothing already imported is duplicated or removed.',
                'sikshya'
            ),
            'state' => ImportRunner::state(),
        ], 200);
    }
}
