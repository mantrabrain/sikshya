<?php
/**
 * Course Controller
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\Controllers;

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
        error_log('Sikshya: CourseController constructor called');
        $this->initHooks();
        error_log('Sikshya: CourseController hooks initialized');
    }
    
    /**
     * Initialize hooks
     * 
     * @return void
     */
    private function initHooks(): void
    {
        error_log('Sikshya: CourseController initHooks called');
        // AJAX handlers are now managed by AjaxManager
        error_log('Sikshya: CourseController hooks initialized');
    }
    
    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-admin');
        wp_enqueue_script('sikshya-admin');
    }
    
    /**
     * Enqueue course builder assets
     */
    public function enqueueCourseBuilderAssets(): void
    {
        // Enqueue toast CSS
        wp_enqueue_style(
            'sikshya-toast',
            SIKSHYA_PLUGIN_URL . 'assets/admin/css/toast.css',
            [],
            SIKSHYA_VERSION
        );
        
        wp_enqueue_style(
            'sikshya-course-builder',
            SIKSHYA_PLUGIN_URL . 'assets/admin/css/course-builder.css',
            [],
            SIKSHYA_VERSION
        );
        
        // Enqueue toast system
        wp_enqueue_script(
            'sikshya-toast',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/toast.js',
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );
        
        wp_enqueue_script(
            'sikshya-course-builder',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/course-builder.js',
            ['jquery', 'sikshya-toast'],
            SIKSHYA_VERSION,
            true
        );

        // Enqueue dynamic course builder save script
        wp_enqueue_script(
            'sikshya-course-builder-save',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/course-builder-save.js',
            ['jquery', 'sikshya-toast'],
            SIKSHYA_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('sikshya-course-builder-save', 'sikshya_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sikshya_course_builder'),
            'debug' => true,
        ]);
    }
    
    /**
     * Render courses list page
     */
    public function renderCoursesPage(): void
    {
        $dataTable = new \Sikshya\Admin\DataTable($this->plugin, [
            'id' => 'sikshya-courses-table',
            'title' => __('Courses', 'sikshya'),
            'description' => __('Manage your courses', 'sikshya'),
        ]);

        // Add columns
        $dataTable->addColumn('id', [
            'title' => __('ID', 'sikshya'),
            'sortable' => true,
            'width' => '80px',
        ]);

        $dataTable->addColumn('title', [
            'title' => __('Title', 'sikshya'),
            'sortable' => true,
            'searchable' => true,
        ]);

        $dataTable->addColumn('instructor', [
            'title' => __('Instructor', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('status', [
            'title' => __('Status', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('enrollments', [
            'title' => __('Enrollments', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('price', [
            'title' => __('Price', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('created', [
            'title' => __('Created', 'sikshya'),
            'sortable' => true,
        ]);

        // Add actions
        $dataTable->addAction('edit', [
            'title' => __('Edit', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-edit-course&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('delete', [
            'title' => __('Delete', 'sikshya'),
            'url' => '#',
            'class' => 'button button-small button-link-delete',
            'onclick' => 'sikshya.deleteCourse({id})',
        ]);

        // Add bulk actions
        $dataTable->addBulkAction('delete', [
            'title' => __('Delete Selected', 'sikshya'),
            'action' => 'sikshya_bulk_delete_courses',
        ]);

        $dataTable->addBulkAction('publish', [
            'title' => __('Publish Selected', 'sikshya'),
            'action' => 'sikshya_bulk_publish_courses',
        ]);

        // Set filters
        $dataTable->setFilters([
            'status' => [
                'type' => 'select',
                'title' => __('Status', 'sikshya'),
                'options' => [
                    '' => __('All Statuses', 'sikshya'),
                    'draft' => __('Draft', 'sikshya'),
                    'publish' => __('Published', 'sikshya'),
                    'private' => __('Private', 'sikshya'),
                ],
            ],
            'instructor' => [
                'type' => 'select',
                'title' => __('Instructor', 'sikshya'),
                'options' => $this->getInstructorsList(),
            ],
        ]);

        // Render the table
        echo $dataTable->renderTable();
    }
    
    /**
     * Render add course page
     */
    public function renderAddCoursePage(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_die(__('Sorry, you are not allowed to access this page.', 'sikshya'));
            }

            error_log('Sikshya: Starting to render course builder page');
            
            // Get the active tab from URL parameter
            $active_tab = sanitize_text_field($_GET['tab'] ?? 'course');
            
            // Get course ID from URL parameter
            $course_id = (int) ($_GET['course_id'] ?? 0);
            
            // Enqueue course builder assets
            $this->enqueueCourseBuilderAssets();
            
            // Render the dynamic course builder template
            $this->render('course-builder-dynamic', [
                'plugin' => $this->plugin,
                'active_tab' => $active_tab,
                'course_id' => $course_id,
            ]);
            
            error_log('Sikshya: Finished rendering course builder page');
        } catch (\Exception $e) {
            error_log('Sikshya CourseController Error: ' . $e->getMessage());
            error_log('Sikshya CourseController Stack: ' . $e->getTraceAsString());
            echo '<!-- Sikshya Course Builder Error: ' . esc_html($e->getMessage()) . ' -->';
        }
    }
    
    /**
     * Get instructors list for filters
     * 
     * @return array
     */
    private function getInstructorsList(): array
    {
        $instructors = get_users([
            'role' => 'instructor',
            'orderby' => 'display_name',
        ]);
        
        $list = ['' => __('All Instructors', 'sikshya')];
        
        foreach ($instructors as $instructor) {
            $list[$instructor->ID] = $instructor->display_name;
        }
        
        return $list;
    }
}
