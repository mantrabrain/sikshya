<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;

/**
 * Instructors admin — React shell only.
 *
 * @package Sikshya\Admin\Controllers
 */
class InstructorController
{
    public function __construct(private Plugin $plugin)
    {
    }

    public function renderInstructorsPage(): void
    {
        ReactAdminView::render('instructors', []);
    }
}
