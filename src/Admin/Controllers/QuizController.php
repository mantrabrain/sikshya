<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Quiz admin — React shell only.
 *
 * @package Sikshya\Admin\Controllers
 */
class QuizController
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function renderQuizzesPage(): void
    {
        ReactAdminView::render('quizzes', []);
    }
}
