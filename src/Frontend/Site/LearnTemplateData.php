<?php

/**
 * @deprecated 2.x Use {@see \Sikshya\Services\Frontend\LearnPageService::fromRequest()} and {@see \Sikshya\Presentation\Models\LearnPageModel}.
 *
 * @package Sikshya\Frontend\Site
 */

namespace Sikshya\Frontend\Site;

use Sikshya\Presentation\Models\LearnPageModel;
use Sikshya\Services\Frontend\LearnPageService;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LearnTemplateData
{
    public static function fromRequest(): LearnPageModel
    {
        return LearnPageService::fromRequest();
    }
}
