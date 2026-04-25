<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;

/**
 * Tools area shell. Maintenance actions (e.g. import sample data) are handled by REST and
 * {@see \Sikshya\Admin\Controllers\SampleDataController} → {@see \Sikshya\Services\SampleDataImportService}.
 *
 * @package Sikshya\Admin\Controllers
 */
class ToolsController
{
    public function __construct(private Plugin $plugin)
    {
    }
}
