<?php

declare(strict_types=1);

namespace Sikshya\Api;

use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical REST response envelope helpers.
 *
 * Three response shapes coexist in the historical codebase:
 *   - `{success: bool, message?: string, data?: …}` (legacy learner + auth routes)
 *   - `{ok: bool, data?: …, message?: …}` (newer admin + Pro addon routes)
 *   - bare arrays (early courses/lessons endpoints)
 *
 * Existing endpoints must keep their shape — client contracts depend on it. New
 * endpoints and any future refactors should commit to the `{ok, data}` shape via
 * {@see self::ok()} and {@see self::error()}, matching what the React admin shell
 * already expects across most routes.
 *
 * The envelope:
 *
 *   Success: { ok: true,  data: <payload> }
 *   Error:   { ok: false, code: <slug>, message: <string>, data?: <extra> }
 *
 * @see RestPermissions for the matching permission-callback facade.
 * @package Sikshya\Api
 */
final class RestResponse
{
    /**
     * Success envelope. Optional `meta` is merged at the top level for pagination
     * cursors, request ids, etc. Avoids nesting them inside `data`.
     *
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    public static function ok($data = null, int $status = 200, array $meta = []): WP_REST_Response
    {
        $body = ['ok' => true];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if ($meta !== []) {
            // Reserved keys callers should never overwrite — drop them silently if present.
            unset($meta['ok'], $meta['data'], $meta['code']);
            $body += $meta;
        }

        return new WP_REST_Response($body, $status);
    }

    /**
     * Error envelope. `code` is a machine-readable slug (e.g. `invalid_params`,
     * `rest_forbidden`); `message` is human-readable. Optional `data` is for
     * structured field errors and stays under the `data` key so the success-shape
     * `data` accessor still works on the client.
     *
     * @param array<string, mixed>|null $data
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        ?array $data = null
    ): WP_REST_Response {
        $body = [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $body['data'] = $data;
        }

        return new WP_REST_Response($body, $status);
    }

    /**
     * Convenience: 400 Bad Request envelope.
     *
     * @param array<string, mixed>|null $data
     */
    public static function badRequest(string $message, string $code = 'invalid_params', ?array $data = null): WP_REST_Response
    {
        return self::error($code, $message, 400, $data);
    }

    /**
     * Convenience: 401 Unauthorized envelope. Use when the caller hasn't proven who they are.
     */
    public static function unauthorized(string $message, string $code = 'rest_unauthorized'): WP_REST_Response
    {
        return self::error($code, $message, 401);
    }

    /**
     * Convenience: 403 Forbidden envelope. Use when the caller is authenticated but lacks the
     * required capability.
     */
    public static function forbidden(string $message, string $code = 'rest_forbidden'): WP_REST_Response
    {
        return self::error($code, $message, 403);
    }

    /**
     * Convenience: 404 Not Found envelope. Use for missing or already-deleted resources.
     */
    public static function notFound(string $message, string $code = 'not_found'): WP_REST_Response
    {
        return self::error($code, $message, 404);
    }

    /**
     * Convenience: 409 Conflict envelope. Use when state collides (e.g. duplicate enrollment,
     * email already exists).
     */
    public static function conflict(string $message, string $code = 'conflict'): WP_REST_Response
    {
        return self::error($code, $message, 409);
    }

    /**
     * Convenience: 503 Service Unavailable envelope. Use when a dependency
     * (custom table not installed, third-party service down) is the blocker.
     */
    public static function serviceUnavailable(string $message, string $code = 'service_unavailable'): WP_REST_Response
    {
        return self::error($code, $message, 503);
    }
}
