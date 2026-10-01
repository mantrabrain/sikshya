<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Site\OrderTemplateData;
use Sikshya\Presentation\Models\OrderPageModel;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderPageService
{
    public static function fromRequest(): OrderPageModel
    {
        return OrderPageModel::fromViewData(OrderTemplateData::fromRequest());
    }
}

