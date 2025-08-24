<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\Views\FormBuilder;
use Sikshya\Admin\Views\Dashboard;
use Sikshya\Admin\Views\BaseView;
use Sikshya\Services\CourseService;

/**
 * Custom Course Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class CourseController extends BaseView
{
    /**
     * Course service
     *
     * @var CourseService
     */
    private CourseService $courseService;

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
     */
    private function initHooks(): void
    {
        // AJAX handlers
        add_action('wp_ajax_sikshya_course_list', [$this, 'handleCourseList']);
        add_action('wp_ajax_sikshya_course_save', [$this, 'handleCourseSave']);
        add_action('wp_ajax_sikshya_course_delete', [$this, 'handleCourseDelete']);
        add_action('wp_ajax_sikshya_course_builder_save', [$this, 'handleCourseBuilderSave']);
        add_action('wp_ajax_sikshya_course_builder_publish', [$this, 'handleCourseBuilderPublish']);
        add_action('wp_ajax_sikshya_course_builder_preview', [$this, 'handleCourseBuilderPreview']);
        
        // Template loading AJAX handlers
        add_action('wp_ajax_sikshya_load_chapter_template', [$this, 'handleLoadChapterTemplate']);
        add_action('wp_ajax_sikshya_load_content_template', [$this, 'handleLoadContentTemplate']);
        add_action('wp_ajax_sikshya_load_modal_template', [$this, 'handleLoadModalTemplate']);
        add_action('wp_ajax_sikshya_load_form_template', [$this, 'handleLoadFormTemplate']);
        add_action('wp_ajax_sikshya_create_chapter', [$this, 'handleCreateChapter']);
        add_action('wp_ajax_sikshya_create_content', [$this, 'handleCreateContent']);
        
        // Dynamic course builder AJAX handlers
        add_action('wp_ajax_sikshya_save_course_builder', [$this, 'handleSaveCourseBuilder']);
        add_action('wp_ajax_sikshya_load_course_data', [$this, 'handleLoadCourseData']);
        add_action('wp_ajax_sikshya_save_chapter_order', [$this, 'handleSaveChapterOrder']);
        add_action('wp_ajax_sikshya_save_lesson_order', [$this, 'handleSaveLessonOrder']);
        add_action('wp_ajax_sikshya_save_content_type', [$this, 'handleSaveContentType']);
        add_action('wp_ajax_sikshya_load_content_type_form', [$this, 'handleLoadContentTypeForm']);
        add_action('wp_ajax_sikshya_validate_course_field', [$this, 'handleValidateCourseField']);
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
        $dataTable = new DataTable($this->plugin, [
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
     * Handle course list AJAX request
     */
    public function handleCourseList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $page = (int) ($_POST['page'] ?? 1);
        $per_page = (int) ($_POST['per_page'] ?? 20);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $filters = $_POST['filters'] ?? [];

        $args = [
            'posts_per_page' => $per_page,
            'paged' => $page,
            'search' => $search,
        ];

        // Apply filters
        if (!empty($filters['status'])) {
            $args['post_status'] = $filters['status'];
        }

        $courses = $this->courseService->getAllCourses($args);
        $total = wp_count_posts('sikshya_course');

        wp_send_json_success([
            'items' => $courses,
            'total' => $total->publish + $total->draft + $total->private,
            'pages' => ceil(($total->publish + $total->draft + $total->private) / $per_page),
        ]);
    }

    /**
     * Handle course save AJAX request
     */
    public function handleCourseSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $data = $_POST['course_data'] ?? [];

        try {
            if ($course_id > 0) {
                $result = $this->courseService->updateCourse($course_id, $data);
                $message = __('Course updated successfully', 'sikshya');
            } else {
                $course_id = $this->courseService->createCourse($data);
                $result = $course_id > 0;
                $message = __('Course created successfully', 'sikshya');
            }

            if ($result) {
                wp_send_json_success([
                    'message' => $message,
                    'course_id' => $course_id,
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save course', 'sikshya')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle course delete AJAX request
     */
    public function handleCourseDelete(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $course_id = (int) ($_POST['course_id'] ?? 0);

        try {
            $result = $this->courseService->deleteCourse($course_id);
            
            if ($result) {
                wp_send_json_success(['message' => __('Course deleted successfully', 'sikshya')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete course', 'sikshya')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle course builder save AJAX request
     */
    public function handleCourseBuilderSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'sikshya')]);
        }

        $course_data = $_POST['course_data'] ?? [];
        
        try {
            // Process course data
            $processed_data = $this->processCourseData($course_data);
            
            // Save as draft
            $course_id = $this->courseService->createCourse($processed_data);
            
            if ($course_id > 0) {
                wp_send_json_success([
                    'message' => __('Course draft saved successfully', 'sikshya'),
                    'course_id' => $course_id,
                    'redirect' => admin_url('admin.php?page=sikshya-courses')
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save course draft', 'sikshya')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle course builder publish AJAX request
     */
    public function handleCourseBuilderPublish(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'sikshya')]);
        }

        $course_data = $_POST['course_data'] ?? [];
        
        try {
            // Process course data
            $processed_data = $this->processCourseData($course_data);
            $processed_data['status'] = 'publish';
            
            // Create and publish course
            $course_id = $this->courseService->createCourse($processed_data);
            
            if ($course_id > 0) {
                wp_send_json_success([
                    'message' => __('Course published successfully', 'sikshya'),
                    'course_id' => $course_id,
                    'redirect' => admin_url('admin.php?page=sikshya-courses')
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to publish course', 'sikshya')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle course builder preview AJAX request
     */
    public function handleCourseBuilderPreview(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'sikshya')]);
        }

        $course_data = $_POST['course_data'] ?? [];
        
        try {
            // Process course data for preview
            $processed_data = $this->processCourseData($course_data);
            
            // Create temporary course for preview
            $temp_course_id = $this->courseService->createCourse($processed_data);
            
            if ($temp_course_id > 0) {
                $preview_url = get_permalink($temp_course_id);
                wp_send_json_success([
                    'message' => __('Preview generated successfully', 'sikshya'),
                    'preview_url' => $preview_url
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to generate preview', 'sikshya')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get instructors list
     */
    private function getInstructorsList(): array
    {
        $instructors = get_users([
            'role' => 'sikshya_instructor',
            'orderby' => 'display_name',
        ]);

        $list = ['' => __('All Instructors', 'sikshya')];
        foreach ($instructors as $instructor) {
            $list[$instructor->ID] = $instructor->display_name;
        }

        return $list;
    }

    /**
     * Get categories list
     */
    private function getCategoriesList(): array
    {
        $categories = get_terms([
            'taxonomy' => 'sikshya_course_category',
            'hide_empty' => false,
        ]);

        $list = [];
        foreach ($categories as $category) {
            $list[$category->term_id] = $category->name;
        }

        return $list;
    }

    /**
     * Process course data from form
     */
    private function processCourseData(array $data): array
    {
        $processed = [
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['description'] ?? ''),
            'post_excerpt' => sanitize_text_field($data['short_description'] ?? ''),
            'post_status' => 'draft',
            'post_type' => 'sikshya_course',
        ];

        // Meta fields
        $meta_fields = [
            'category' => sanitize_text_field($data['category'] ?? ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?? 'beginner'),
            'language' => sanitize_text_field($data['language'] ?? 'english'),
            'duration' => floatval($data['duration'] ?? 0),
            'max_students' => intval($data['max_students'] ?? 0),
            'course_type' => sanitize_text_field($data['course_type'] ?? 'paid'),
            'price' => floatval($data['price'] ?? 0),
            'sale_price' => floatval($data['sale_price'] ?? 0),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'seo_keywords' => sanitize_text_field($data['seo_keywords'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? $data['title'] ?? ''),
            'allow_enrollment' => !empty($data['allow_enrollment']),
            'require_approval' => !empty($data['require_approval']),
            'access_duration' => sanitize_text_field($data['access_duration'] ?? 'lifetime'),
            'include_certificate' => !empty($data['include_certificate']),
            'allow_download' => !empty($data['allow_download']),
            'enable_discussion' => !empty($data['enable_discussion']),
            'allow_reviews' => !empty($data['allow_reviews']),
        ];

        $processed['meta_input'] = $meta_fields;

        return $processed;
    }

    /**
     * Handle loading chapter template
     */
    public function handleLoadChapterTemplate(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $args = [
                'chapter_id' => sanitize_text_field($_POST['chapter_id'] ?? ''),
                'chapter_title' => sanitize_text_field($_POST['chapter_title'] ?? ''),
                'chapter_description' => sanitize_textarea_field($_POST['chapter_description'] ?? ''),
                'chapter_duration' => sanitize_text_field($_POST['chapter_duration'] ?? ''),
                'chapter_order' => intval($_POST['chapter_order'] ?? 1),
                'content_count' => intval($_POST['content_count'] ?? 0),
            ];

            ob_start();
            $this->render('courses/chapter', $args);
            $html = ob_get_clean();

            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            error_log('Sikshya Chapter Template Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load chapter template: ' . $e->getMessage());
        }
    }

    /**
     * Handle loading content template
     */
    public function handleLoadContentTemplate(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $args = [
                'content_id' => sanitize_text_field($_POST['content_id'] ?? ''),
                'content_type' => sanitize_text_field($_POST['content_type'] ?? 'text'),
                'content_title' => sanitize_text_field($_POST['content_title'] ?? ''),
                'content_duration' => sanitize_text_field($_POST['content_duration'] ?? ''),
                'content_description' => sanitize_textarea_field($_POST['content_description'] ?? ''),
            ];

            ob_start();
            $this->render('courses/content-item', $args);
            $html = ob_get_clean();

            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            error_log('Sikshya Content Template Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load content template: ' . $e->getMessage());
        }
    }

    /**
     * Handle loading modal template
     */
    public function handleLoadModalTemplate(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $modal_type = sanitize_text_field($_POST['modal_type'] ?? '');
            $args = [];

            switch ($modal_type) {
                case 'chapter':
                    $args['chapter_order'] = intval($_POST['chapter_order'] ?? 1);
                    $template = 'courses/modal-chapter';
                    break;
                case 'content-type':
                    $template = 'courses/modal-content-type';
                    break;
                default:
                    wp_send_json_error('Invalid modal type');
                    return;
            }

            ob_start();
            $this->render($template, $args);
            $html = ob_get_clean();

            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            error_log('Sikshya Modal Template Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load modal template: ' . $e->getMessage());
        }
    }

    /**
     * Handle loading form template
     */
    public function handleLoadFormTemplate(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $content_type = sanitize_text_field($_POST['content_type'] ?? '');
            $args = [];

            // Always use advanced forms, handle different naming patterns
            if ($content_type === 'quiz' || $content_type === 'assignment') {
                $template = 'courses/forms/' . $content_type;
            } else {
                $template = 'courses/forms/' . $content_type . '-lesson';
            }

            // Add debugging
            error_log('Sikshya: Loading form template: ' . $template . ' for content type: ' . $content_type);

            ob_start();
            $this->render($template, $args);
            $html = ob_get_clean();

            // Add debugging
            error_log('Sikshya: Form template HTML length: ' . strlen($html));

            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            error_log('Sikshya Form Template Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load form template: ' . $e->getMessage());
        }
    }

    /**
     * Handle creating chapter
     */
    public function handleCreateChapter(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $chapter_data = [
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'duration' => sanitize_text_field($_POST['duration'] ?? ''),
                'order' => intval($_POST['order'] ?? 1),
            ];

            // Validate required fields
            if (empty($chapter_data['title'])) {
                wp_send_json_error('Chapter title is required');
                return;
            }

            // Generate unique chapter ID
            $chapter_id = 'chapter-' . uniqid();

            // Load chapter template with data
            $args = [
                'chapter_id' => $chapter_id,
                'chapter_title' => $chapter_data['title'],
                'chapter_description' => $chapter_data['description'],
                'chapter_duration' => $chapter_data['duration'],
                'chapter_order' => $chapter_data['order'],
                'content_count' => 0,
            ];

            $html = $this->renderToString('courses/chapter', $args);

            wp_send_json_success([
                'html' => $html,
                'chapter_id' => $chapter_id,
                'message' => 'Chapter created successfully'
            ]);
            
        } catch (\Exception $e) {
            error_log('Sikshya Create Chapter Error: ' . $e->getMessage());
            wp_send_json_error('Failed to create chapter: ' . $e->getMessage());
        }
    }

    /**
     * Handle test AJAX request
     */
    public function handleTestAjax(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            wp_send_json_success(['message' => 'AJAX is working!']);
        } catch (\Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle creating content
     */
    public function handleCreateContent(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('sikshya_course_builder', 'nonce');

            $content_data = [
                'type' => sanitize_text_field($_POST['type'] ?? 'text'),
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'duration' => sanitize_text_field($_POST['duration'] ?? ''),
            ];

            // Debug logging
            error_log('Sikshya Create Content - POST data: ' . print_r($_POST, true));
            error_log('Sikshya Create Content - Content type: ' . $content_data['type']);

            // Validate required fields
            if (empty($content_data['title'])) {
                wp_send_json_error('Content title is required');
                return;
            }

            // Generate unique content ID
            $content_id = 'content-' . uniqid();

            // Load content template with data
            $args = [
                'content_id' => $content_id,
                'content_type' => $content_data['type'],
                'content_title' => $content_data['title'],
                'content_description' => $content_data['description'],
                'content_duration' => $content_data['duration'],
            ];

            // Debug logging
            error_log('Sikshya Create Content - Template args: ' . print_r($args, true));

            ob_start();
            $this->render('courses/content-item', $args);
            $html = ob_get_clean();

            wp_send_json_success([
                'html' => $html,
                'content_id' => $content_id,
                'message' => 'Content created successfully'
            ]);
        } catch (\Exception $e) {
            error_log('Sikshya Create Content Error: ' . $e->getMessage());
            wp_send_json_error('Failed to create content: ' . $e->getMessage());
        }
    }

    /**
     * Handle save course builder AJAX request
     */
    public function handleSaveCourseBuilder(): void
    {
        error_log('Sikshya: handleSaveCourseBuilder called');
        error_log('Sikshya: POST data received: ' . print_r($_POST, true));
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $data = $_POST['data'] ?? [];
            $course_id = (int) ($data['course_id'] ?? 0);
            $course_status = sanitize_text_field($_POST['course_status'] ?? 'draft');
            
            // Initialize course builder manager
            try {
                $course_builder_manager = new \Sikshya\Admin\CourseBuilder\CourseBuilderManager($this->plugin);
            } catch (\Exception $e) {
                error_log('Sikshya: Failed to instantiate CourseBuilderManager: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Failed to initialize course builder']);
                return;
            }
            
            // Validate data
            $errors = $course_builder_manager->validateAllTabs($data);
            if (!empty($errors)) {
                wp_send_json_error(['message' => 'Validation failed', 'errors' => $errors]);
                return;
            }
            
            // Create course if it doesn't exist
            if ($course_id === 0) {
                $course_id = wp_insert_post([
                    'post_title' => $data['title'] ?? 'New Course',
                    'post_content' => $data['description'] ?? '',
                    'post_type' => 'sikshya_course',
                    'post_status' => $course_status,
                ]);
                
                if (is_wp_error($course_id)) {
                    wp_send_json_error(['message' => 'Failed to create course']);
                    return;
                }
            } else {
                // Update existing course status
                wp_update_post([
                    'ID' => $course_id,
                    'post_status' => $course_status,
                ]);
            }
            
            // Save all tab data
            $save_errors = $course_builder_manager->saveAllTabs($data, $course_id);
            if (!empty($save_errors)) {
                wp_send_json_error(['message' => 'Failed to save some data', 'errors' => $save_errors]);
                return;
            }
            
            $message = $course_status === 'published' ? 'Course published successfully!' : 'Course draft saved successfully!';
            
            wp_send_json_success([
                'message' => $message,
                'course_id' => $course_id,
                'status' => $course_status
            ]);
            
        } catch (\Exception $e) {
            error_log('Sikshya Save Course Builder Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to save course: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle load course data AJAX request
     */
    public function handleLoadCourseData(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $course_id = (int) ($_POST['course_id'] ?? 0);
            
            if ($course_id === 0) {
                wp_send_json_error(['message' => 'Invalid course ID']);
                return;
            }
            
            // Initialize course builder manager
            $course_builder_manager = new \Sikshya\Admin\CourseBuilder\CourseBuilderManager($this->plugin);
            
            // Load all tab data
            $data = $course_builder_manager->loadAllTabs($course_id);
            
            wp_send_json_success(['data' => $data]);
            
        } catch (\Exception $e) {
            error_log('Sikshya Load Course Data Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to load course data: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle save chapter order AJAX request
     */
    public function handleSaveChapterOrder(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $course_id = (int) ($_POST['course_id'] ?? 0);
            $chapter_order = $_POST['chapter_order'] ?? [];
            
            if ($course_id === 0) {
                wp_send_json_error(['message' => 'Invalid course ID']);
                return;
            }
            
            // Save chapter order to course meta
            update_post_meta($course_id, '_sikshya_chapter_order', $chapter_order);
            
            wp_send_json_success(['message' => 'Chapter order saved successfully']);
            
        } catch (\Exception $e) {
            error_log('Sikshya Save Chapter Order Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to save chapter order: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle save lesson order AJAX request
     */
    public function handleSaveLessonOrder(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $chapter_id = (int) ($_POST['chapter_id'] ?? 0);
            $lesson_order = $_POST['lesson_order'] ?? [];
            
            if ($chapter_id === 0) {
                wp_send_json_error(['message' => 'Invalid chapter ID']);
                return;
            }
            
            // Save lesson order to chapter meta
            update_post_meta($chapter_id, '_sikshya_lesson_order', $lesson_order);
            
            wp_send_json_success(['message' => 'Lesson order saved successfully']);
            
        } catch (\Exception $e) {
            error_log('Sikshya Save Lesson Order Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to save lesson order: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle save content type AJAX request
     */
    public function handleSaveContentType(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $item_id = (int) ($_POST['item_id'] ?? 0);
            $data = $_POST['data'] ?? [];
            
            if ($item_id === 0) {
                wp_send_json_error(['message' => 'Invalid item ID']);
                return;
            }
            
            // Save content type data
            foreach ($data as $key => $value) {
                update_post_meta($item_id, '_sikshya_' . $key, $value);
            }
            
            wp_send_json_success(['message' => 'Content saved successfully']);
            
        } catch (\Exception $e) {
            error_log('Sikshya Save Content Type Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to save content: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle load content type form AJAX request
     */
    public function handleLoadContentTypeForm(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $content_type = sanitize_text_field($_POST['content_type'] ?? '');
            
            if (empty($content_type)) {
                wp_send_json_error(['message' => 'Content type is required']);
                return;
            }
            
            // Load content type form template
            $args = [
                'content_type' => $content_type,
                'item_id' => $_POST['item_id'] ?? '',
            ];
            
            ob_start();
            $this->render('courses/content-type-form', $args);
            $html = ob_get_clean();
            
            wp_send_json_success(['html' => $html]);
            
        } catch (\Exception $e) {
            error_log('Sikshya Load Content Type Form Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to load form: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle validate course field AJAX request
     */
    public function handleValidateCourseField(): void
    {
        try {
            check_ajax_referer('sikshya_course_builder', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $field_name = sanitize_text_field($_POST['field_name'] ?? '');
            $field_value = $_POST['field_value'] ?? '';
            $field_type = sanitize_text_field($_POST['field_type'] ?? 'text');
            
            if (empty($field_name)) {
                wp_send_json_error(['message' => 'Field name is required']);
                return;
            }
            
            // Basic validation based on field type
            $error = '';
            
            switch ($field_type) {
                case 'email':
                    if (!empty($field_value) && !is_email($field_value)) {
                        $error = 'Please enter a valid email address.';
                    }
                    break;
                    
                case 'url':
                    if (!empty($field_value) && !filter_var($field_value, FILTER_VALIDATE_URL)) {
                        $error = 'Please enter a valid URL.';
                    }
                    break;
                    
                case 'number':
                    if (!empty($field_value) && !is_numeric($field_value)) {
                        $error = 'Please enter a valid number.';
                    }
                    break;
            }
            
            if (!empty($error)) {
                wp_send_json_error(['message' => $error]);
            } else {
                wp_send_json_success(['message' => 'Field is valid']);
            }
            
        } catch (\Exception $e) {
            error_log('Sikshya Validate Course Field Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Validation failed: ' . $e->getMessage()]);
        }
    }
} 