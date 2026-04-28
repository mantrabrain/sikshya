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
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function renderInstructorsPage(): void
    {
        ReactAdminView::render('instructors', []);
    }
}
