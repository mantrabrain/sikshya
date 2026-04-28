<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;

/**
 * Lesson admin — React shell only.
 *
 * @package Sikshya\Admin\Controllers
 */
class LessonController
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function renderLessonsPage(): void
    {
        ReactAdminView::render('lessons', []);
    }

    public function renderAddLessonPage(): void
    {
        ReactAdminView::render('add-lesson', []);
    }

    /**
     * @param string $type Unused; reserved for future React routing.
     */
    public function renderAddLessonForm(string $type): void
    {
        ReactAdminView::render('add-lesson', []);
    }
}
