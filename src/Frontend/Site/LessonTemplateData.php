<?php

/**
 * @deprecated Use {@see \Sikshya\Services\Frontend\LessonPageService::forPost()} and {@see \Sikshya\Presentation\Models\SingleLessonPageModel}.
 *
 * @package Sikshya\Frontend\Site
 */

namespace Sikshya\Frontend\Site;

use Sikshya\Presentation\Models\SingleLessonPageModel;
use Sikshya\Services\Frontend\LessonPageService;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LessonTemplateData
{
    public static function forPost(\WP_Post $post): SingleLessonPageModel
    {
        return LessonPageService::forPost($post);
    }
}
