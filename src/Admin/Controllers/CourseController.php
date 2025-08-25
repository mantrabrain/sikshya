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
use Sikshya\Models\Course;

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
        // Create and prepare the list table
        $list_table = new \Sikshya\Admin\ListTable\CoursesListTable($this->plugin);
        $list_table->prepare_items();
        
        // Render the page with proper Sikshya design
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <i class="fas fa-graduation-cap"></i>
                        <?php _e('Courses', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Course', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <?php _e('Manage Courses', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('Create, edit, and manage your courses', 'sikshya'); ?></p>
                        </div>
                        <div class="sikshya-content-card-header-right">
                            <?php $this->display_status_filter_tabs(); ?>
                        </div>
                    </div>
                    <div class="sikshya-content-card-body">
                        <form method="post">
                            <?php
                            $list_table->display();
                            ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
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
            
            // Get course ID from URL parameter (support both 'course_id' and 'id')
            $course_id = (int) ($_GET['course_id'] ?? $_GET['id'] ?? 0);
            
            // Load course data directly if course ID is present
            $course_data = null;
            if ($course_id > 0) {
                error_log('Sikshya: Loading course data for ID: ' . $course_id);
                $course_data = $this->loadCourseData($course_id);
                error_log('Sikshya: Course data loaded: ' . print_r($course_data, true));
            }
            
            // Enqueue course builder assets
            $this->enqueueCourseBuilderAssets();
            
            // Render the dynamic course builder template
            $this->render('course-builder-dynamic', [
                'plugin' => $this->plugin,
                'active_tab' => $active_tab,
                'course_id' => $course_id,
                'course_data' => $course_data,
            ]);
            
            error_log('Sikshya: Finished rendering course builder page');
        } catch (\Exception $e) {
            error_log('Sikshya CourseController Error: ' . $e->getMessage());
            error_log('Sikshya CourseController Stack: ' . $e->getTraceAsString());
            echo '<!-- Sikshya Course Builder Error: ' . esc_html($e->getMessage()) . ' -->';
        }
    }

    /**
     * Load course data for editing
     * 
     * @param int $course_id
     * @return array|null
     */
    private function loadCourseData(int $course_id): ?array
    {
        try {
            error_log('Sikshya: Attempting to load course with ID: ' . $course_id);
            
            // Use the new Course model
            $course = Course::find($course_id);
            
            if (!$course || !$course->exists()) {
                error_log('Sikshya: Course not found for ID: ' . $course_id);
                return null;
            }
            
            error_log('Sikshya: Course found - Title: ' . $course->getTitle());
            
            // Return course data as array
            return $course->toArray();
            
        } catch (\Exception $e) {
            error_log('Sikshya: Error loading course data: ' . $e->getMessage());
            return null;
        }
    }
    


    /**
     * Display status filter tabs
     * 
     * @return void
     */
    private function display_status_filter_tabs(): void
    {
        $current_status = $_GET['post_status'] ?? 'all';
        $base_url = remove_query_arg(['post_status', 'paged']);
        
        $status_counts = $this->get_status_counts();
        
        echo '<ul class="subsubsub">';
        
        // All tab
        $all_count = array_sum($status_counts);
        $all_class = ($current_status === 'all') ? 'current' : '';
        $all_url = $base_url;
        echo '<li class="all">';
        echo '<a href="' . esc_url($all_url) . '" class="' . esc_attr($all_class) . '"' . ($all_class ? ' aria-current="page"' : '') . '>';
        echo esc_html__('All', 'sikshya') . ' <span class="count">(' . esc_html($all_count) . ')</span>';
        echo '</a> |</li>';
        
        // Published tab
        if (isset($status_counts['publish'])) {
            $publish_class = ($current_status === 'publish') ? 'current' : '';
            $publish_url = add_query_arg('post_status', 'publish', $base_url);
            echo '<li class="publish">';
            echo '<a href="' . esc_url($publish_url) . '" class="' . esc_attr($publish_class) . '">';
            echo esc_html__('Published', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['publish']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Draft tab
        if (isset($status_counts['draft'])) {
            $draft_class = ($current_status === 'draft') ? 'current' : '';
            $draft_url = add_query_arg('post_status', 'draft', $base_url);
            echo '<li class="draft">';
            echo '<a href="' . esc_url($draft_url) . '" class="' . esc_attr($draft_class) . '">';
            echo esc_html__('Draft', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['draft']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Pending tab
        if (isset($status_counts['pending'])) {
            $pending_class = ($current_status === 'pending') ? 'current' : '';
            $pending_url = add_query_arg('post_status', 'pending', $base_url);
            echo '<li class="pending">';
            echo '<a href="' . esc_url($pending_url) . '" class="' . esc_attr($pending_class) . '">';
            echo esc_html__('Pending', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['pending']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Private tab
        if (isset($status_counts['private'])) {
            $private_class = ($current_status === 'private') ? 'current' : '';
            $private_url = add_query_arg('post_status', 'private', $base_url);
            echo '<li class="private">';
            echo '<a href="' . esc_url($private_url) . '" class="' . esc_attr($private_class) . '">';
            echo esc_html__('Private', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['private']) . ')</span>';
            echo '</a></li>';
        }
        
        echo '</ul>';
    }

    /**
     * Get status counts for filter tabs
     * 
     * @return array
     */
    private function get_status_counts(): array
    {
        // For demo purposes, return dummy counts based on the dummy data
        return [
            'publish' => 6,  // 6 published courses
            'draft' => 2,    // 2 draft courses
            'pending' => 2,  // 2 pending courses
            'private' => 0   // 0 private courses
        ];
        
        // TODO: Implement actual status counting logic
        // $counts = [];
        // $statuses = ['publish', 'draft', 'pending', 'private'];
        // foreach ($statuses as $status) {
        //     $args = [
        //         'post_type' => 'sik_course',
        //         'post_status' => $status,
        //         'posts_per_page' => -1,
        //         'fields' => 'ids'
        //     ];
        //     $query = new \WP_Query($args);
        //     $counts[$status] = $query->found_posts;
        // }
        // return $counts;
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
