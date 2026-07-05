<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Site\CartTemplateData;
use Sikshya\Presentation\Models\CartPageModel;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

final class CartPageService
{
    public static function build(): CartPageModel
    {
        return CartPageModel::fromViewData(CartTemplateData::build());
    }
}

