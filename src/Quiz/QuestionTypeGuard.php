<?php

namespace Sikshya\Quiz;

use Sikshya\Addons\Addons;
use Sikshya\Constants\PostTypes;
use Sikshya\Licensing\Pro;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-side guard for Pro-gated quiz question types.
 *
 * UI gating is not security. This ensures a site without Pro / without the
 * Advanced Quiz add-on cannot create or update a question to an advanced type
 * via REST or direct editor calls.
 */
final class QuestionTypeGuard
{
    /**
     * @return list<string>
     */
    public static function advancedTypes(): array
    {
        return [
            'multiple_response',
            'fill_blank',
            'matching',
            'ordering',
            'essay',
        ];
    }

    public static function register(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        // Blocks both create + update on the WP REST "posts controller" endpoint.
        add_filter('rest_pre_insert_' . PostTypes::QUESTION, [self::class, 'restPreInsertQuestion'], 10, 2);
    }

    /**
     * @param \WP_Post $prepared_post
     * @return \WP_Post|\WP_Error
     */
    public static function restPreInsertQuestion($prepared_post, WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $meta = isset($params['meta']) && is_array($params['meta']) ? $params['meta'] : [];
        $raw_type = $meta['_sikshya_question_type'] ?? null;
        $type = sanitize_key(is_string($raw_type) ? $raw_type : (string) $raw_type);

        // If request isn't touching question type, do nothing.
        if ($type === '') {
            return $prepared_post;
        }

        if (!in_array($type, self::advancedTypes(), true)) {
            return $prepared_post;
        }

        // Requires Pro license feature + Addons toggle.
        if (!Pro::feature('quiz_advanced')) {
            return Pro::restFeatureRequired('quiz_advanced');
        }
        if (!Addons::isEnabled('quiz_advanced')) {
            return Pro::restAddonDisabled('quiz_advanced');
        }

        return $prepared_post;
    }
}

