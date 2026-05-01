<?php

/**
 * Preview vs enrolled access for curriculum outline rows (Learn hub + lesson/quiz shells).
 *
 * @package Sikshya\Services\Frontend
 */

namespace Sikshya\Services\Frontend;

use Sikshya\Constants\PostTypes;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Services\Settings;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves permalinks and lock state for a curriculum post in the Learn UI.
 */
final class LearnOutlineItemAccess
{
    /**
     * @return array{
     *   permalink: string,
     *   locked: bool,
     *   lock_reason: string,
     *   preview_allowed: bool
     * }
     */
    public static function forContentPost(
        \WP_Post $p,
        string $type_key,
        int $course_id,
        bool $enrolled,
        bool $is_preview_mode
    ): array {
        $is_free = Settings::isTruthy(get_post_meta((int) $p->ID, '_sikshya_is_free', true));
        $course_url = $course_id > 0 ? (string) (get_permalink($course_id) ?: '') : '';

        if ($enrolled) {
            return [
                'permalink' => self::learnPermalinkFor($p, $type_key),
                'locked' => false,
                'lock_reason' => '',
                'preview_allowed' => true,
            ];
        }

        if ($is_preview_mode) {
            $preview_allowed = $is_free;

            return [
                'permalink' => $preview_allowed ? self::learnPermalinkFor($p, $type_key) : $course_url,
                'locked' => !$preview_allowed,
                'lock_reason' => !$preview_allowed ? __('Enroll to unlock this content.', 'sikshya') : '',
                'preview_allowed' => $preview_allowed,
            ];
        }

        return [
            'permalink' => self::learnPermalinkFor($p, $type_key),
            'locked' => false,
            'lock_reason' => '',
            'preview_allowed' => false,
        ];
    }

    private static function learnPermalinkFor(\WP_Post $p, string $type_key): string
    {
        if (in_array($type_key, ['lesson', 'quiz', 'assignment'], true)) {
            return PublicPageUrls::learnContentForPost($p);
        }

        return get_permalink($p) ?: '';
    }

    public static function contentTypeKey(string $post_type): string
    {
        switch ($post_type) {
            case PostTypes::LESSON:
                return 'lesson';
            case PostTypes::QUIZ:
                return 'quiz';
            case PostTypes::ASSIGNMENT:
                return 'assignment';
            default:
                return 'content';
        }
    }
}
