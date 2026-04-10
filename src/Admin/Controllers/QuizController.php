<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;

/**
 * Quiz admin — React shell only.
 *
 * @package Sikshya\Admin\Controllers
 */
class QuizController
{
    public function __construct(private Plugin $plugin)
    {
    }

    public function renderQuizzesPage(): void
    {
        ReactAdminView::render('quizzes', []);
    }
}
