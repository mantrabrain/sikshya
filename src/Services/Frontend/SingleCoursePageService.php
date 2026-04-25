<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Public\SingleCourseTemplateData;
use Sikshya\Presentation\Models\SingleCoursePageModel;

/**
 * Single-course landing page builder (service layer wrapper).
 *
 * This keeps template consumption model-driven while the data-shaping logic
 * remains backward-compatible via the legacy `sikshya_single_course_template_data` filter.
 *
 * @package Sikshya\Services\Frontend
 */
final class SingleCoursePageService
{
    public static function forPost(\WP_Post $post): SingleCoursePageModel
    {
        // SingleCourseTemplateData currently contains the data-shaping logic and filter.
        // The service provides the stable entry point for templates.
        $vm = SingleCourseTemplateData::legacyArrayForPost($post);

        return SingleCoursePageModel::fromViewData($vm);
    }
}

