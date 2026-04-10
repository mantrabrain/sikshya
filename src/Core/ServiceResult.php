<?php

/**
 * Standard service-layer response shape (no HTTP).
 *
 * @package Sikshya\Core
 */

namespace Sikshya\Core;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @phpstan-type ServiceResultArray array{ok:bool,message?:string,data?:mixed,errors?:mixed,code?:string}
 */
final class ServiceResult
{
    /**
     * @param mixed $data
     * @return ServiceResultArray
     */
    public static function success($data = null, string $message = ''): array
    {
        $out = ['ok' => true];
        if ($message !== '') {
            $out['message'] = $message;
        }
        if ($data !== null) {
            $out['data'] = $data;
        }

        return $out;
    }

    /**
     * @param mixed $errors
     * @return ServiceResultArray
     */
    public static function failure(string $message, $errors = null, string $code = ''): array
    {
        $out = [
            'ok' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $out['errors'] = $errors;
        }
        if ($code !== '') {
            $out['code'] = $code;
        }

        return $out;
    }
}
