<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Frontend\Site\CheckoutTemplateData;
use Sikshya\Presentation\Models\CheckoutPageModel;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

final class CheckoutPageService
{
    public static function build(): CheckoutPageModel
    {
        return CheckoutPageModel::fromViewData(CheckoutTemplateData::build());
    }
}

