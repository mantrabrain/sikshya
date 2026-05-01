<?php

/**
 * @package Sikshya\Services\Frontend
 */

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Site\CoursesGridTemplateData;
use Sikshya\Presentation\Models\CoursesGridPageModel;

if (!defined('ABSPATH')) {
    exit;
}

final class CoursesGridPageService
{
    public static function forBrowseGrid(): CoursesGridPageModel
    {
        return CoursesGridPageModel::fromViewData(CoursesGridTemplateData::legacyArrayForBrowseGrid());
    }
}
