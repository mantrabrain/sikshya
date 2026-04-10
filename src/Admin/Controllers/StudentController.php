<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;

/**
 * Students admin — React shell only.
 *
 * @package Sikshya\Admin\Controllers
 */
class StudentController
{
    public function __construct(private Plugin $plugin)
    {
    }

    public function renderStudentsPage(): void
    {
        ReactAdminView::render('students', []);
    }
}
