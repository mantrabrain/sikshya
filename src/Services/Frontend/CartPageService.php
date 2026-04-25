<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Public\CartTemplateData;
use Sikshya\Presentation\Models\CartPageModel;

final class CartPageService
{
    public static function build(): CartPageModel
    {
        return CartPageModel::fromViewData(CartTemplateData::build());
    }
}

