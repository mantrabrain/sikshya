<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Public\CheckoutTemplateData;
use Sikshya\Presentation\Models\CheckoutPageModel;

final class CheckoutPageService
{
    public static function build(): CheckoutPageModel
    {
        return CheckoutPageModel::fromViewData(CheckoutTemplateData::build());
    }
}

