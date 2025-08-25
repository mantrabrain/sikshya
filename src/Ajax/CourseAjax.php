<?php
/**
 * Course AJAX Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

use Sikshya\Admin\CourseBuilder\CourseBuilderManager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CourseAjax extends AjaxAbstract
{
    /**
     * Initialize hooks
     * 
     * @return void
     */
    protected function initHooks(): void
    {
        
        
        // Course builder AJAX handlers
        add_action('wp_ajax_sikshya_save_course_builder', [$this, 'handleSaveCourseBuilder']);
        error_log('Sikshya: sikshya_save_course_builder action registered');
        
        // Test if action is actually registered
        if (has_action('wp_ajax_sikshya_save_course_builder')) {
            error_log('Sikshya: sikshya_save_course_builder action is properly registered');
        } else {
            error_log('Sikshya: ERROR - sikshya_save_course_builder action is NOT registered');
        }
        add_action('wp_ajax_sikshya_load_course_data', [$this, 'handleLoadCourseData']);
        add_action('wp_ajax_sikshya_save_chapter_order', [$this, 'handleSaveChapterOrder']);
        add_action('wp_ajax_sikshya_save_lesson_order', [$this, 'handleSaveLessonOrder']);
        add_action('wp_ajax_sikshya_save_content_type', [$this, 'handleSaveContentType']);
        add_action('wp_ajax_sikshya_load_content_type_form', [$this, 'handleLoadContentTypeForm']);
        add_action('wp_ajax_sikshya_validate_course_field', [$this, 'handleValidateCourseField']);
        
        // Course management AJAX handlers
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
        
        // Test AJAX action
        add_action('wp_ajax_sikshya_test_ajax', [$this, 'handleTestAjax']);
        add_action('wp_ajax_sikshya_simple_test', [$this, 'handleSimpleTest']);
    }
    
    /**
     * Test AJAX handler
     */
    public function handleTestAjax(): void
    {
        error_log('Sikshya: Test AJAX handler called');
        wp_send_json_success(['message' => 'Test AJAX working!']);
    }
    
    /**
     * Simple test AJAX handler
     */
    public function handleSimpleTest(): void
    {
        error_log('Sikshya: Simple test AJAX handler called');
        wp_die('Simple test working!');
    }

    /**
     * Handle save course builder AJAX request
     */
    public function handleSaveCourseBuilder(): void
    {
        error_log('Sikshya: handleSaveCourseBuilder method called');
        error_log('Sikshya: POST data: ' . print_r($_POST, true));
        
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $data = $this->getPostData('data', []);
            $course_id = intval($data['course_id'] ?? 0);
            $course_status = sanitize_text_field($this->getPostData('course_status', 'draft'));
            
            // Initialize course builder manager
            try {
                $course_builder_manager = new CourseBuilderManager($this->plugin);
            } catch (\Exception $e) {
                $this->logError('Failed to instantiate CourseBuilderManager', $e);
                $this->sendError('Failed to initialize course builder');
                return;
            }
            
            // Validate data
            $errors = $course_builder_manager->validateAllTabs($data);
            if (!empty($errors)) {
                $this->sendError('Validation failed', $errors);
                return;
            }
            
            // Create course if it doesn't exist
            if ($course_id === 0) {
                $post_data = [
                    'post_title' => $data['title'] ?? 'New Course',
                    'post_content' => $data['description'] ?? '',
                    'post_type' => 'sikshya_course',
                    'post_status' => $course_status,
                ];
                
                $course_id = wp_insert_post($post_data);
                
                if (is_wp_error($course_id)) {
                    $this->sendError('Failed to create course');
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
                $this->sendError('Failed to save some data', $save_errors);
                return;
            }
            
            $message = $course_status === 'published' ? 'Course published successfully!' : 'Course draft saved successfully!';
            
            $this->sendSuccess([
                'course_id' => $course_id,
                'status' => $course_status
            ], $message);
            
        } catch (\Exception $e) {
            $this->logError('Save course builder error', $e);
            $this->sendError('Failed to save course: ' . $e->getMessage());
        }
    }

    /**
     * Handle load course data AJAX request
     */
    public function handleLoadCourseData(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $course_id = intval($this->getPostData('course_id', 0));
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }
            
            // Initialize course builder manager
            $course_builder_manager = new CourseBuilderManager($this->plugin);
            
            // Load all tab data
            $data = $course_builder_manager->loadAllTabs($course_id);
            
            $this->sendSuccess($data);
            
        } catch (\Exception $e) {
            $this->logError('Load course data error', $e);
            $this->sendError('Failed to load course data: ' . $e->getMessage());
        }
    }

    /**
     * Handle course list AJAX request
     */
    public function handleCourseList(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }

            $page = intval($this->getPostData('page', 1));
            $per_page = intval($this->getPostData('per_page', 20));
            $search = sanitize_text_field($this->getPostData('search', ''));
            $filters = $this->getPostData('filters', []);

            $args = [
                'posts_per_page' => $per_page,
                'paged' => $page,
                'search' => $search,
            ];

            // Apply filters
            if (!empty($filters['status'])) {
                $args['post_status'] = $filters['status'];
            }

            $courses = get_posts(array_merge($args, [
                'post_type' => 'sikshya_course',
                'post_status' => 'any'
            ]));
            
            $total = wp_count_posts('sikshya_course');

            $this->sendSuccess([
                'items' => $courses,
                'total' => $total->publish + $total->draft + $total->private,
                'pages' => ceil(($total->publish + $total->draft + $total->private) / $per_page),
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Course list error', $e);
            $this->sendError('Failed to load course list: ' . $e->getMessage());
        }
    }

    /**
     * Handle course save AJAX request
     */
    public function handleCourseSave(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }

            $course_id = intval($this->getPostData('course_id', 0));
            $data = $this->getPostData('course_data', []);

            if ($course_id > 0) {
                $result = wp_update_post(array_merge($data, ['ID' => $course_id]));
                $message = 'Course updated successfully';
            } else {
                $course_id = wp_insert_post(array_merge($data, ['post_type' => 'sikshya_course']));
                $result = $course_id > 0;
                $message = 'Course created successfully';
            }

            if ($result && !is_wp_error($result)) {
                $this->sendSuccess(['course_id' => $course_id], $message);
            } else {
                $this->sendError('Failed to save course');
            }
            
        } catch (\Exception $e) {
            $this->logError('Course save error', $e);
            $this->sendError('Failed to save course: ' . $e->getMessage());
        }
    }

    /**
     * Handle course delete AJAX request
     */
    public function handleCourseDelete(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }

            $course_id = intval($this->getPostData('course_id', 0));
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }

            $result = wp_delete_post($course_id, true);

            if ($result) {
                $this->sendSuccess(null, 'Course deleted successfully');
            } else {
                $this->sendError('Failed to delete course');
            }
            
        } catch (\Exception $e) {
            $this->logError('Course delete error', $e);
            $this->sendError('Failed to delete course: ' . $e->getMessage());
        }
    }

    /**
     * Handle course builder save (legacy)
     */
    public function handleCourseBuilderSave(): void
    {
        $this->handleSaveCourseBuilder();
    }

    /**
     * Handle course builder publish
     */
    public function handleCourseBuilderPublish(): void
    {
        // Set status to published and call save
        $_POST['course_status'] = 'published';
        $this->handleSaveCourseBuilder();
    }

    /**
     * Handle course builder preview
     */
    public function handleCourseBuilderPreview(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $data = $this->getPostData('data', []);
            $course_id = intval($data['course_id'] ?? 0);
            
            // Generate preview URL
            if ($course_id > 0) {
                $preview_url = get_preview_post_link($course_id);
            } else {
                // For new courses, create a temporary preview
                $preview_url = add_query_arg([
                    'preview' => 'true',
                    'course_data' => base64_encode(json_encode($data))
                ], home_url());
            }
            
            $this->sendSuccess(['preview_url' => $preview_url]);
            
        } catch (\Exception $e) {
            $this->logError('Course preview error', $e);
            $this->sendError('Failed to generate preview: ' . $e->getMessage());
        }
    }

    /**
     * Handle load chapter template
     */
    public function handleLoadChapterTemplate(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            // Return chapter template HTML
            $template = $this->getChapterTemplate();
            $this->sendSuccess(['template' => $template]);
            
        } catch (\Exception $e) {
            $this->logError('Load chapter template error', $e);
            $this->sendError('Failed to load chapter template: ' . $e->getMessage());
        }
    }

    /**
     * Handle load content template
     */
    public function handleLoadContentTemplate(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $content_type = sanitize_text_field($this->getPostData('content_type', 'lesson'));
            
            // Return content template HTML
            $template = $this->getContentTemplate($content_type);
            $this->sendSuccess(['template' => $template]);
            
        } catch (\Exception $e) {
            $this->logError('Load content template error', $e);
            $this->sendError('Failed to load content template: ' . $e->getMessage());
        }
    }

    /**
     * Handle load modal template
     */
    public function handleLoadModalTemplate(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $modal_type = sanitize_text_field($this->getPostData('modal_type', ''));
            
            // Return modal template HTML
            $template = $this->getModalTemplate($modal_type);
            $this->sendSuccess(['template' => $template]);
            
        } catch (\Exception $e) {
            $this->logError('Load modal template error', $e);
            $this->sendError('Failed to load modal template: ' . $e->getMessage());
        }
    }

    /**
     * Handle load form template
     */
    public function handleLoadFormTemplate(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $form_type = sanitize_text_field($this->getPostData('form_type', ''));
            
            // Return form template HTML
            $template = $this->getFormTemplate($form_type);
            $this->sendSuccess(['template' => $template]);
            
        } catch (\Exception $e) {
            $this->logError('Load form template error', $e);
            $this->sendError('Failed to load form template: ' . $e->getMessage());
        }
    }

    /**
     * Handle create chapter
     */
    public function handleCreateChapter(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $data = $this->getPostData('data', []);
            $course_id = intval($data['course_id'] ?? 0);
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }
            
            // Create chapter post
            $chapter_id = wp_insert_post([
                'post_title' => sanitize_text_field($data['title'] ?? 'New Chapter'),
                'post_content' => wp_kses_post($data['description'] ?? ''),
                'post_type' => 'sikshya_chapter',
                'post_status' => 'publish',
                'post_parent' => $course_id,
            ]);
            
            if (is_wp_error($chapter_id)) {
                $this->sendError('Failed to create chapter');
                return;
            }
            
            $this->sendSuccess(['chapter_id' => $chapter_id], 'Chapter created successfully');
            
        } catch (\Exception $e) {
            $this->logError('Create chapter error', $e);
            $this->sendError('Failed to create chapter: ' . $e->getMessage());
        }
    }

    /**
     * Handle create content
     */
    public function handleCreateContent(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $data = $this->getPostData('data', []);
            $chapter_id = intval($data['chapter_id'] ?? 0);
            $content_type = sanitize_text_field($data['content_type'] ?? 'lesson');
            
            if ($chapter_id === 0) {
                $this->sendError('Invalid chapter ID');
                return;
            }
            
            // Determine post type based on content type
            $post_type = 'sikshya_' . $content_type;
            
            // Create content post
            $content_id = wp_insert_post([
                'post_title' => sanitize_text_field($data['title'] ?? 'New ' . ucfirst($content_type)),
                'post_content' => wp_kses_post($data['description'] ?? ''),
                'post_type' => $post_type,
                'post_status' => 'publish',
                'post_parent' => $chapter_id,
            ]);
            
            if (is_wp_error($content_id)) {
                $this->sendError('Failed to create ' . $content_type);
                return;
            }
            
            $this->sendSuccess(['content_id' => $content_id], ucfirst($content_type) . ' created successfully');
            
        } catch (\Exception $e) {
            $this->logError('Create content error', $e);
            $this->sendError('Failed to create content: ' . $e->getMessage());
        }
    }

    /**
     * Handle save chapter order
     */
    public function handleSaveChapterOrder(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $course_id = intval($this->getPostData('course_id', 0));
            $chapter_order = $this->getPostData('chapter_order', []);
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }
            
            // Save chapter order
            update_post_meta($course_id, '_sikshya_chapter_order', $chapter_order);
            
            $this->sendSuccess(null, 'Chapter order saved successfully');
            
        } catch (\Exception $e) {
            $this->logError('Save chapter order error', $e);
            $this->sendError('Failed to save chapter order: ' . $e->getMessage());
        }
    }

    /**
     * Handle save lesson order
     */
    public function handleSaveLessonOrder(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $chapter_id = intval($this->getPostData('chapter_id', 0));
            $lesson_order = $this->getPostData('lesson_order', []);
            
            if ($chapter_id === 0) {
                $this->sendError('Invalid chapter ID');
                return;
            }
            
            // Save lesson order
            update_post_meta($chapter_id, '_sikshya_lesson_order', $lesson_order);
            
            $this->sendSuccess(null, 'Lesson order saved successfully');
            
        } catch (\Exception $e) {
            $this->logError('Save lesson order error', $e);
            $this->sendError('Failed to save lesson order: ' . $e->getMessage());
        }
    }

    /**
     * Handle save content type
     */
    public function handleSaveContentType(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $item_id = intval($this->getPostData('item_id', 0));
            $data = $this->getPostData('data', []);
            
            if ($item_id === 0) {
                $this->sendError('Invalid item ID');
                return;
            }
            
            // Update content item
            $result = wp_update_post([
                'ID' => $item_id,
                'post_title' => sanitize_text_field($data['title'] ?? ''),
                'post_content' => wp_kses_post($data['description'] ?? ''),
            ]);
            
            if (is_wp_error($result)) {
                $this->sendError('Failed to save content');
                return;
            }
            
            $this->sendSuccess(null, 'Content saved successfully');
            
        } catch (\Exception $e) {
            $this->logError('Save content type error', $e);
            $this->sendError('Failed to save content: ' . $e->getMessage());
        }
    }

    /**
     * Handle load content type form
     */
    public function handleLoadContentTypeForm(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $content_type = sanitize_text_field($this->getPostData('content_type', 'lesson'));
            $item_id = intval($this->getPostData('item_id', 0));
            
            // Load content data if editing
            $data = [];
            if ($item_id > 0) {
                $post = get_post($item_id);
                if ($post) {
                    $data = [
                        'title' => $post->post_title,
                        'description' => $post->post_content,
                    ];
                }
            }
            
            // Return form HTML
            $form = $this->getContentTypeForm($content_type, $data);
            $this->sendSuccess(['form' => $form]);
            
        } catch (\Exception $e) {
            $this->logError('Load content type form error', $e);
            $this->sendError('Failed to load form: ' . $e->getMessage());
        }
    }

    /**
     * Handle validate course field
     */
    public function handleValidateCourseField(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $field_name = sanitize_text_field($this->getPostData('field_name', ''));
            $field_value = $this->getPostData('field_value', '');
            $field_type = sanitize_text_field($this->getPostData('field_type', 'text'));
            
            $errors = [];
            
            // Validate based on field type
            switch ($field_type) {
                case 'email':
                    if (!empty($field_value) && !is_email($field_value)) {
                        $errors[] = 'Please enter a valid email address.';
                    }
                    break;
                    
                case 'url':
                    if (!empty($field_value) && !filter_var($field_value, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Please enter a valid URL.';
                    }
                    break;
                    
                case 'number':
                    if (!empty($field_value) && !is_numeric($field_value)) {
                        $errors[] = 'Please enter a valid number.';
                    }
                    break;
                    
                case 'required':
                    if (empty($field_value)) {
                        $errors[] = 'This field is required.';
                    }
                    break;
            }
            
            $this->sendSuccess(['errors' => $errors]);
            
        } catch (\Exception $e) {
            $this->logError('Validate course field error', $e);
            $this->sendError('Failed to validate field: ' . $e->getMessage());
        }
    }

    /**
     * Get chapter template HTML
     * 
     * @return string
     */
    private function getChapterTemplate(): string
    {
        ob_start();
        ?>
        <div class="sikshya-chapter-card" data-chapter-id="0">
            <div class="sikshya-chapter-header">
                <h4 class="sikshya-chapter-title">New Chapter</h4>
                <div class="sikshya-chapter-actions">
                    <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary sikshya-edit-chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-danger sikshya-delete-chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="sikshya-chapter-content">
                <p class="sikshya-chapter-description">Chapter description will appear here...</p>
                <div class="sikshya-chapter-lessons">
                    <p class="sikshya-no-lessons">No lessons added yet</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content template HTML
     * 
     * @param string $content_type
     * @return string
     */
    private function getContentTemplate(string $content_type): string
    {
        $title = ucfirst($content_type);
        $icon = $this->getContentTypeIcon($content_type);
        
        ob_start();
        ?>
        <div class="sikshya-content-item" data-content-type="<?php echo esc_attr($content_type); ?>">
            <div class="sikshya-content-icon">
                <?php echo $icon; ?>
            </div>
            <div class="sikshya-content-info">
                <h5 class="sikshya-content-title"><?php echo esc_html($title); ?></h5>
                <p class="sikshya-content-desc">Add a new <?php echo esc_html($content_type); ?> to this chapter</p>
            </div>
            <div class="sikshya-content-actions">
                <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-primary sikshya-add-content" data-type="<?php echo esc_attr($content_type); ?>">
                    Add <?php echo esc_html($title); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get modal template HTML
     * 
     * @param string $modal_type
     * @return string
     */
    private function getModalTemplate(string $modal_type): string
    {
        ob_start();
        ?>
        <div class="sikshya-modal sikshya-modal-<?php echo esc_attr($modal_type); ?>">
            <div class="sikshya-modal-header">
                <h3 class="sikshya-modal-title"><?php echo esc_html(ucfirst($modal_type)); ?></h3>
                <button type="button" class="sikshya-modal-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="sikshya-modal-body">
                <!-- Modal content will be loaded here -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get form template HTML
     * 
     * @param string $form_type
     * @return string
     */
    private function getFormTemplate(string $form_type): string
    {
        ob_start();
        ?>
        <form class="sikshya-form sikshya-form-<?php echo esc_attr($form_type); ?>">
            <div class="sikshya-form-row">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="sikshya-form-row">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="sikshya-form-actions">
                <button type="submit" class="sikshya-btn sikshya-btn-primary">Save</button>
                <button type="button" class="sikshya-btn sikshya-btn-secondary sikshya-cancel">Cancel</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content type form HTML
     * 
     * @param string $content_type
     * @param array $data
     * @return string
     */
    private function getContentTypeForm(string $content_type, array $data = []): string
    {
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        
        ob_start();
        ?>
        <form class="sikshya-content-form" data-content-type="<?php echo esc_attr($content_type); ?>">
            <div class="sikshya-form-row">
                <label for="content_title"><?php echo esc_html(ucfirst($content_type)); ?> Title *</label>
                <input type="text" id="content_title" name="title" value="<?php echo esc_attr($title); ?>" required>
            </div>
            <div class="sikshya-form-row">
                <label for="content_description">Description</label>
                <textarea id="content_description" name="description" rows="3"><?php echo esc_textarea($description); ?></textarea>
            </div>
            <div class="sikshya-form-actions">
                <button type="submit" class="sikshya-btn sikshya-btn-primary">Save <?php echo esc_html(ucfirst($content_type)); ?></button>
                <button type="button" class="sikshya-btn sikshya-btn-secondary sikshya-cancel">Cancel</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Get content type icon
     * 
     * @param string $content_type
     * @return string
     */
    private function getContentTypeIcon(string $content_type): string
    {
        $icons = [
            'lesson' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
            'quiz' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'assignment' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        ];
        
        return $icons[$content_type] ?? $icons['lesson'];
    }
}
