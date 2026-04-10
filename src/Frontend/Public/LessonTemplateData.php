<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;

/**
 * @package Sikshya\Frontend\Public
 */
final class LessonTemplateData
{
    /**
     * @return array<string, mixed>
     */
    public static function forPost(\WP_Post $post): array
    {
        return apply_filters(
            'sikshya_lesson_template_data',
            [
                'post' => $post,
                'urls' => [
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ],
            $post
        );
    }
}
