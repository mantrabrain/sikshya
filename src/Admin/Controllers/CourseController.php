<?php

/**
 * Course Controller
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Admin\Views\BaseView;
use Sikshya\Core\Plugin;
use Sikshya\Services\CourseService;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CourseController extends BaseView
{
    /**
     * Course service
     *
     * @var CourseService
     */
    private $courseService;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);
        $this->courseService = new CourseService();
        $this->initHooks();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function initHooks(): void
    {
        // Admin UI uses REST + React.
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
    }

    /**
     * Render courses list page
     */
    public function renderCoursesPage(): void
    {
        ReactAdminView::render('courses', []);
    }

    /**
     * Render add course page
     */
    public function renderAddCoursePage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'sikshya'));
        }

        ReactAdminView::render('add-course', []);
    }

}
