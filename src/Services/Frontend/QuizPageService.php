<?php

/**
 * @package Sikshya\Services\Frontend
 */

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Site\QuizTemplateData;
use Sikshya\Presentation\Models\SingleQuizPageModel;

if (!defined('ABSPATH')) {
    exit;
}

final class QuizPageService
{
    public static function forPost(\WP_Post $post): SingleQuizPageModel
    {
        return SingleQuizPageModel::fromViewData(QuizTemplateData::legacyArrayForPost($post));
    }
}
