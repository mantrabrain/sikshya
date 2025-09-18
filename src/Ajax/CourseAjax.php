<?php
/**
 * Course AJAX Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

use Sikshya\Admin\CourseBuilder\CourseBuilderManager;
use Sikshya\Constants\PostTypes;

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
        add_action('wp_ajax_sikshya_link_content_to_chapter', [$this, 'handleLinkContentToChapter']);
        add_action('wp_ajax_sikshya_load_curriculum', [$this, 'handleLoadCurriculum']);
        add_action('wp_ajax_sikshya_load_chapter_data', [$this, 'handleLoadChapterData']);
        add_action('wp_ajax_sikshya_update_chapter', [$this, 'handleUpdateChapter']);
        add_action('wp_ajax_sikshya_bulk_delete_items', [$this, 'handleBulkDeleteItems']);
        

    }
    


    /**
     * Handle save course builder AJAX request
     */
    public function handleSaveCourseBuilder(): void
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

            // Get all form data directly from $_POST (excluding action, nonce, and course_status)
            $data = [];
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'nonce', 'course_status'])) {
                    $data[$key] = $value;
                }
            }
            
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
                    'post_type' => PostTypes::COURSE,
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
                'post_type' => PostTypes::COURSE,
                'post_status' => 'any'
            ]));
            
            $total = wp_count_posts(PostTypes::COURSE);

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
                $course_id = wp_insert_post(array_merge($data, ['post_type' => PostTypes::COURSE]));
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
            if (!$this->verifyNonce('sikshya_course_builder_nonce', 'sikshya_course_builder_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $modal_type = sanitize_text_field($this->getPostData('modal_type', ''));
            $chapter_order = intval($this->getPostData('chapter_order', 1));
            
            // Load specific modal template based on type
            if ($modal_type === 'chapter') {
                $template = $this->loadChapterModalTemplate($chapter_order);
            } elseif ($modal_type === 'edit-chapter') {
                $template = $this->loadEditChapterModalTemplate();
            } elseif ($modal_type === 'content-type') {
                $template = $this->loadContentTypeModalTemplate();
            } else {
                $template = $this->getModalTemplate($modal_type);
            }
            
            $this->sendSuccess(['html' => $template]);
            
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
            
            $content_type = sanitize_text_field($this->getPostData('content_type', ''));
            
            // Return form template HTML
            $template = $this->getContentTypeForm($content_type);
            $this->sendSuccess(['html' => $template]);
            
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

            $title = sanitize_text_field($this->getPostData('title', ''));
            $description = wp_kses_post($this->getPostData('description', ''));
            $duration = sanitize_text_field($this->getPostData('duration', ''));
            $order = intval($this->getPostData('order', 1));
            
            if (empty($title)) {
                $this->sendError('Chapter title is required');
                return;
            }
            
            // Get course ID from URL or session
            $course_id = $this->getCourseIdFromContext();
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }
            
            // Create chapter post
            $chapter_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => PostTypes::CHAPTER,
                'post_status' => 'publish',
                'post_parent' => $course_id,
            ]);
            
            if (is_wp_error($chapter_id)) {
                $this->sendError('Failed to create chapter');
                return;
            }
            
            // Save additional meta
            if (!empty($duration)) {
                update_post_meta($chapter_id, '_sikshya_duration', $duration);
            }
            update_post_meta($chapter_id, '_sikshya_order', $order);
            
            // Generate chapter HTML
            $chapter_html = $this->generateChapterHTML($chapter_id, $title, $description, $duration, $order);
            
            $this->sendSuccess([
                'chapter_id' => $chapter_id,
                'html' => $chapter_html
            ], 'Chapter created successfully');
            
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

            $title = sanitize_text_field($this->getPostData('title', ''));
            $description = wp_kses_post($this->getPostData('description', ''));
            $duration = sanitize_text_field($this->getPostData('duration', ''));
            $content_type = sanitize_text_field($this->getPostData('type', 'lesson'));
            
            if (empty($title)) {
                $this->sendError('Content title is required');
                return;
            }
            
            // Determine post type based on content type
            $post_type = 'sik_' . $content_type;
            
            // Map content types to PostTypes constants
            switch ($content_type) {
                case 'lesson':
                    $post_type = PostTypes::LESSON;
                    break;
                case 'quiz':
                    $post_type = PostTypes::QUIZ;
                    break;
                case 'assignment':
                    $post_type = PostTypes::ASSIGNMENT;
                    break;
                default:
                    $post_type = PostTypes::LESSON; // Default to lesson
                    break;
            }
            
            // Create content post (no parent - content is independent)
            $content_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => $post_type,
                'post_status' => 'publish',
            ]);
            
            if (is_wp_error($content_id)) {
                $this->sendError('Failed to create ' . $content_type);
                return;
            }
            
            // Save additional meta
            if (!empty($duration)) {
                update_post_meta($content_id, '_sikshya_duration', $duration);
            }
            
            // Save content type specific meta
            if ($content_type === 'text') {
                $this->saveTextLessonMeta($content_id);
            }
            
            // Generate content HTML
            $content_html = $this->generateContentHTML($content_id, $title, $description, $duration, $content_type);
            
            $this->sendSuccess([
                'content_id' => $content_id,
                'html' => $content_html
            ], ucfirst($content_type) . ' created successfully');
            
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
     * Load chapter modal template
     * 
     * @param int $chapter_order
     * @return string
     */
    private function loadChapterModalTemplate(int $chapter_order): string
    {
        ob_start();
        
        // Set up template variables
        $args = [
            'chapter_order' => $chapter_order
        ];
        
        // Include the chapter modal template
        include SIKSHYA_PLUGIN_DIR . 'templates/admin/views/courses/modal-chapter.php';
        
        return ob_get_clean();
    }

    /**
     * Load edit chapter modal template
     * 
     * @return string
     */
    private function loadEditChapterModalTemplate(): string
    {
        ob_start();
        ?>
        <div class="sikshya-modal-overlay">
            <div class="sikshya-modal">
                <div class="sikshya-modal-header">
                    <div class="sikshya-modal-title">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Chapter
                    </div>
                    <div class="sikshya-modal-subtitle">Update your chapter information</div>
                    <button type="button" class="sikshya-modal-close" onclick="closeModal(this.closest('.sikshya-modal-overlay'))">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="sikshya-modal-body">
                    <form class="sikshya-form">
                        <input type="hidden" name="chapter_id" value="">
                        
                        <div class="sikshya-form-group">
                            <label for="title" class="sikshya-form-label">Chapter Title *</label>
                            <input type="text" id="title" name="title" class="sikshya-form-input" placeholder="Enter chapter title" required>
                        </div>
                        
                        <div class="sikshya-form-row">
                            <div class="sikshya-form-group">
                                <label for="duration" class="sikshya-form-label">Duration (hours)</label>
                                <input type="number" id="duration" name="duration" class="sikshya-form-input" min="0" step="0.5" placeholder="0">
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="order" class="sikshya-form-label">Order</label>
                                <input type="number" id="order" name="order" class="sikshya-form-input" min="1" placeholder="1">
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="sikshya-modal-footer">
                    <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="closeModal(this.closest('.sikshya-modal-overlay'))">
                        Cancel
                    </button>
                    <button type="button" class="sikshya-btn sikshya-btn-primary">
                        Update Chapter
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Load content type modal template
     * 
     * @return string
     */
    private function loadContentTypeModalTemplate(): string
    {
        ob_start();
        
        // Include the content type modal template
        include SIKSHYA_PLUGIN_DIR . 'templates/admin/views/courses/modal-content-type.php';
        
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
        // Map content types to template files
        $template_map = [
            'text' => 'text-lesson.php',
            'video' => 'video-lesson.php',
            'audio' => 'audio-lesson.php',
            'quiz' => 'quiz.php',
            'assignment' => 'assignment.php'
        ];
        
        $template_file = $template_map[$content_type] ?? 'text-lesson.php';
        $template_path = SIKSHYA_PLUGIN_DIR . 'templates/admin/views/courses/forms/' . $template_file;
        
        if (file_exists($template_path)) {
            // Extract data for template
            extract($data);
            
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback to basic form if template doesn't exist
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
     * Save text lesson specific meta data
     * 
     * @param int $content_id
     */
    private function saveTextLessonMeta(int $content_id): void
    {
        $meta_fields = [
            'content' => 'wp_kses_post',
            'objectives' => 'wp_kses_post',
            'takeaways' => 'wp_kses_post',
            'resources' => 'wp_kses_post',
            'difficulty' => 'sanitize_text_field',
            'completion' => 'sanitize_text_field',
            'comments' => 'sanitize_text_field',
            'progress' => 'sanitize_text_field',
            'print' => 'sanitize_text_field',
            'prerequisites' => 'wp_kses_post',
            'tags' => 'sanitize_text_field',
            'seo' => 'wp_kses_post',
            'format' => 'sanitize_text_field',
            'reading_level' => 'sanitize_text_field',
            'word_count' => 'intval',
            'language' => 'sanitize_text_field',
            'toc' => 'sanitize_text_field',
            'search' => 'sanitize_text_field',
            'related' => 'sanitize_text_field'
        ];
        
        foreach ($meta_fields as $field => $sanitize_function) {
            $value = $this->getPostData($field, '');
            if (!empty($value)) {
                $sanitized_value = $sanitize_function($value);
                update_post_meta($content_id, '_sikshya_' . $field, $sanitized_value);
            }
        }
    }

    /**
     * Get course ID from context (URL or session)
     * 
     * @return int
     */
    private function getCourseIdFromContext(): int
    {
        // Try to get from POST data first (for AJAX requests)
        $course_id = intval($this->getPostData('course_id', 0));
        
        error_log('Sikshya: getCourseIdFromContext - POST course_id: ' . $course_id);
        
        if ($course_id > 0) {
            error_log('Sikshya: getCourseIdFromContext - returning POST course_id: ' . $course_id);
            return $course_id;
        }
        
        // Try to get from URL parameter
        $course_id = intval($_GET['course_id'] ?? 0);
        
        error_log('Sikshya: getCourseIdFromContext - GET course_id: ' . $course_id);
        
        if ($course_id > 0) {
            error_log('Sikshya: getCourseIdFromContext - returning GET course_id: ' . $course_id);
            return $course_id;
        }
        
        // Try to get from session or other context
        // For now, return 0 if not found
        error_log('Sikshya: getCourseIdFromContext - no course_id found, returning 0');
        return 0;
    }

    /**
     * Generate chapter HTML for curriculum
     * 
     * @param int $chapter_id
     * @param string $title
     * @param string $description
     * @param string $duration
     * @param int $order
     * @return string
     */
    private function generateChapterHTML(int $chapter_id, string $title, string $description, string $duration, int $order): string
    {
        ob_start();
        ?>
        <div class="sikshya-chapter-card" id="chapter-<?php echo esc_attr($chapter_id); ?>" 
             data-chapter-id="chapter-<?php echo esc_attr($chapter_id); ?>"
             data-description="<?php echo esc_attr($description); ?>"
             data-duration="<?php echo esc_attr($duration); ?>"
             data-order="<?php echo esc_attr($order); ?>">
            
            <div class="sikshya-chapter-header" onclick="toggleChapter('chapter-<?php echo esc_attr($chapter_id); ?>')">
                <div class="sikshya-chapter-controls">
                    <div class="sikshya-chapter-checkbox">
                        <input type="checkbox" id="chapter-<?php echo esc_attr($chapter_id); ?>" class="sikshya-checkbox">
                        <label for="chapter-<?php echo esc_attr($chapter_id); ?>"></label>
                    </div>
                    <div class="sikshya-chapter-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="sikshya-chapter-number">
                        <?php echo esc_html($order); ?>
                    </div>
                </div>
                
                <div class="sikshya-chapter-info">
                    <div class="sikshya-chapter-main">
                        <h4 class="sikshya-chapter-title"><?php echo esc_html($title); ?></h4>
                        <?php if (!empty($description)): ?>
                            <p class="sikshya-chapter-description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        <div class="sikshya-chapter-content-summary">
                            <div class="sikshya-chapter-meta">
                                <span class="sikshya-chapter-lessons">
                                    <span class="lesson-count">0</span> lessons
                                </span>
                                <span class="sikshya-chapter-quizzes">
                                    <span class="quiz-count">0</span> quizzes
                                </span>
                                <span class="sikshya-chapter-assignments">
                                    <span class="assignment-count">0</span> assignments
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="sikshya-chapter-actions">
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); editChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Edit Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); deleteChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Delete Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon sikshya-chapter-toggle" onclick="event.stopPropagation(); toggleChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Toggle Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="sikshya-chapter-content" id="content-chapter-<?php echo esc_attr($chapter_id); ?>">
                <div class="sikshya-chapter-content-inner">
                    <div class="sikshya-lesson-list">
                        <div class="sikshya-chapter-empty">
                            <div class="sikshya-chapter-empty-icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <h4>No Lessons Yet</h4>
                            <p>Add your first lesson to this chapter</p>
                        </div>
                    </div>
                    
                    <!-- Add More Content -->
                    <div class="sikshya-add-lesson">
                        <button class="sikshya-add-lesson-btn" onclick="addContent('chapter-<?php echo esc_attr($chapter_id); ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Content
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate content HTML for curriculum
     * 
     * @param int $content_id
     * @param string $title
     * @param string $description
     * @param string $duration
     * @param string $content_type
     * @return string
     */
    private function generateContentHTML(int $content_id, string $title, string $description, string $duration, string $content_type): string
    {
        ob_start();
        ?>
        <div class="sikshya-content-item" id="content-<?php echo esc_attr($content_id); ?>" data-content-id="<?php echo esc_attr($content_id); ?>" data-content-type="<?php echo esc_attr($content_type); ?>">
            <div class="sikshya-content-header">
                <div class="sikshya-content-controls">
                    <div class="sikshya-content-checkbox">
                        <input type="checkbox" id="content-<?php echo esc_attr($content_id); ?>" class="sikshya-checkbox">
                        <label for="content-<?php echo esc_attr($content_id); ?>"></label>
                    </div>
                    <div class="sikshya-content-icon">
                        <?php echo $this->getContentTypeIcon($content_type); ?>
                    </div>
                </div>
                
                <div class="sikshya-content-info">
                    <div class="sikshya-content-main">
                        <h5 class="sikshya-content-title"><?php echo esc_html($title); ?></h5>
                        <?php if (!empty($description)): ?>
                            <p class="sikshya-content-description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($duration)): ?>
                            <span class="sikshya-content-duration"><?php echo esc_html($duration); ?> min</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sikshya-content-actions">
                    <button class="sikshya-btn-icon" onclick="editContent(<?php echo esc_attr($content_id); ?>)" title="Edit Content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon" onclick="deleteContent(<?php echo esc_attr($content_id); ?>)" title="Delete Content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Link content to chapter
     * 
     * @param int $content_id
     * @param int $chapter_id
     * @return bool
     */
    private function linkContentToChapter(int $content_id, int $chapter_id): bool
    {
        // Store content_id in chapter meta
        $chapter_contents = get_post_meta($chapter_id, '_sikshya_contents', true);
        if (!is_array($chapter_contents)) {
            $chapter_contents = [];
        }
        
        $chapter_contents[] = $content_id;
        
        $result = update_post_meta($chapter_id, '_sikshya_contents', $chapter_contents);
        
        // Also store chapter_id in course meta (if we have course_id)
        $course_id = get_post_field('post_parent', $chapter_id);
        if ($course_id) {
            $course_chapters = get_post_meta($course_id, '_sikshya_chapters', true);
            if (!is_array($course_chapters)) {
                $course_chapters = [];
            }
            
            // Add chapter if not already in list
            if (!in_array($chapter_id, $course_chapters)) {
                $course_chapters[] = $chapter_id;
                update_post_meta($course_id, '_sikshya_chapters', $course_chapters);
            }
        }
        
        return $result;
    }

    /**
     * Handle link content to chapter
     */
    public function handleLinkContentToChapter(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder_nonce', 'sikshya_course_builder_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }

            $content_id = intval($this->getPostData('content_id', 0));
            $chapter_id = intval($this->getPostData('chapter_id', 0));
            
            if ($content_id === 0 || $chapter_id === 0) {
                $this->sendError('Invalid content or chapter ID');
                return;
            }
            
            // Link content to chapter
            $result = $this->linkContentToChapter($content_id, $chapter_id);
            
            if ($result) {
                $this->sendSuccess([], 'Content linked to chapter successfully');
            } else {
                $this->sendError('Failed to link content to chapter');
            }
            
        } catch (\Exception $e) {
            $this->logError('Link content to chapter error', $e);
            $this->sendError('Failed to link content to chapter: ' . $e->getMessage());
        }
    }

    /**
     * Generate complete curriculum HTML
     * 
     * @param int $course_id
     * @return string
     */
    private function generateCurriculumHTML(int $course_id): string
    {
        error_log('Sikshya: generateCurriculumHTML called for course_id: ' . $course_id);
        
        // Get all chapters for this course
        $chapters = get_posts([
            'post_type' => PostTypes::CHAPTER,
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_sikshya_order',
            'order' => 'ASC'
        ]);
        
        error_log('Sikshya: Found ' . count($chapters) . ' chapters for course_id: ' . $course_id);
        
        if (empty($chapters)) {
            error_log('Sikshya: No chapters found, returning empty state');
            // Return empty state
            ob_start();
            ?>
            <div class="sikshya-curriculum-empty-state" id="curriculum-empty-state">
                <div class="sikshya-empty-header">
                    <div class="sikshya-empty-content">
                        <div class="sikshya-empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <div class="sikshya-empty-text">
                            <h3>Create Your First Chapter</h3>
                            <p>Start building your course curriculum with organized chapters and lessons.</p>
                        </div>
                    </div>
                    <div class="sikshya-empty-actions">
                        <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            Add Chapter
                        </button>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        error_log('Sikshya: Generating curriculum HTML for ' . count($chapters) . ' chapters');
        
        ob_start();
        ?>
        <div class="sikshya-curriculum-items" id="curriculum-items">
            <?php
            foreach ($chapters as $chapter) {
                $chapter_id = $chapter->ID;
                $chapter_title = $chapter->post_title;
                $chapter_description = $chapter->post_content;
                $chapter_order = get_post_meta($chapter_id, '_sikshya_order', true) ?: 1;
                $chapter_duration = get_post_meta($chapter_id, '_sikshya_duration', true);
                
                error_log('Sikshya: Processing chapter: ' . $chapter_id . ' - ' . $chapter_title);
                
                // Get content counts
                $chapter_contents = get_post_meta($chapter_id, '_sikshya_contents', true);
                
                // Ensure chapter_contents is always an array
                if (!is_array($chapter_contents)) {
                    $chapter_contents = [];
                }
                
                $lesson_count = 0;
                $quiz_count = 0;
                $assignment_count = 0;
                
                foreach ($chapter_contents as $content_id) {
                    $content_post_type = get_post_type($content_id);
                    switch ($content_post_type) {
                        case PostTypes::LESSON:
                            $lesson_count++;
                            break;
                        case PostTypes::QUIZ:
                            $quiz_count++;
                            break;
                        case PostTypes::ASSIGNMENT:
                            $assignment_count++;
                            break;
                    }
                }
                
                error_log('Sikshya: Chapter ' . $chapter_id . ' has ' . $lesson_count . ' lessons, ' . $quiz_count . ' quizzes, ' . $assignment_count . ' assignments');
                
                // Generate chapter HTML with content counts
                echo $this->generateChapterHTMLWithContent($chapter_id, $chapter_title, $chapter_description, $chapter_duration, $chapter_order, $lesson_count, $quiz_count, $assignment_count, $chapter_contents);
            }
            ?>
        </div>
        <?php
        $html = ob_get_clean();
        error_log('Sikshya: Generated curriculum HTML length: ' . strlen($html));
        return $html;
    }

    /**
     * Generate chapter HTML with content counts
     * 
     * @param int $chapter_id
     * @param string $title
     * @param string $description
     * @param string $duration
     * @param int $order
     * @param int $lesson_count
     * @param int $quiz_count
     * @param int $assignment_count
     * @param array $chapter_contents
     * @return string
     */
    private function generateChapterHTMLWithContent(int $chapter_id, string $title, string $description, string $duration, int $order, int $lesson_count, int $quiz_count, int $assignment_count, array $chapter_contents): string
    {
        ob_start();
        ?>
        <div class="sikshya-chapter-card" id="chapter-<?php echo esc_attr($chapter_id); ?>" 
             data-chapter-id="chapter-<?php echo esc_attr($chapter_id); ?>"
             data-description="<?php echo esc_attr($description); ?>"
             data-duration="<?php echo esc_attr($duration); ?>"
             data-order="<?php echo esc_attr($order); ?>" draggable="true">
            
            <div class="sikshya-chapter-header" onclick="toggleChapter('chapter-<?php echo esc_attr($chapter_id); ?>')">
                <div class="sikshya-sortable-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="8" cy="6" r="1.5"></circle>
                        <circle cx="16" cy="6" r="1.5"></circle>
                        <circle cx="8" cy="12" r="1.5"></circle>
                        <circle cx="16" cy="12" r="1.5"></circle>
                        <circle cx="8" cy="18" r="1.5"></circle>
                        <circle cx="16" cy="18" r="1.5"></circle>
                    </svg>
                </div>
                <div class="sikshya-chapter-controls">
                    <div class="sikshya-chapter-checkbox">
                        <input type="checkbox" id="chapter-<?php echo esc_attr($chapter_id); ?>" class="sikshya-checkbox">
                        <label for="chapter-<?php echo esc_attr($chapter_id); ?>"></label>
                    </div>
                    <div class="sikshya-chapter-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="sikshya-chapter-number"><?php echo esc_html($order); ?></div>
                </div>
                
                <div class="sikshya-chapter-info">
                    <div class="sikshya-chapter-main">
                        <h4 class="sikshya-chapter-title"><?php echo esc_html($title); ?></h4>
                        <?php if (!empty($description)): ?>
                            <p class="sikshya-chapter-description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        <div class="sikshya-chapter-content-summary">
                            <div class="sikshya-chapter-meta">
                                <span class="sikshya-chapter-lessons"><span class="lesson-count"><?php echo esc_html($lesson_count); ?></span> lessons</span>
                                <span class="sikshya-chapter-quizzes"><span class="quiz-count"><?php echo esc_html($quiz_count); ?></span> quizzes</span>
                                <span class="sikshya-chapter-assignments"><span class="assignment-count"><?php echo esc_html($assignment_count); ?></span> assignments</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="sikshya-chapter-actions">
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); editChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Edit Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); deleteChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Delete Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon sikshya-chapter-toggle" onclick="event.stopPropagation(); toggleChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Toggle Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="sikshya-chapter-content" id="content-chapter-<?php echo esc_attr($chapter_id); ?>">
                <div class="sikshya-chapter-content-inner">
                    <div class="sikshya-lesson-list">
                        <?php if ($lesson_count + $quiz_count + $assignment_count === 0): ?>
                            <div class="sikshya-chapter-empty">
                                <div class="sikshya-chapter-empty-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                                <h4>No Lessons Yet</h4>
                                <p>Add your first lesson to this chapter</p>
                            </div>
                        <?php else: ?>
                            <?php
                            // Display content items
                            if (is_array($chapter_contents)) {
                                foreach ($chapter_contents as $content_id) {
                                    $content_post = get_post($content_id);
                                    if ($content_post) {
                                        $content_title = $content_post->post_title;
                                        $content_description = $content_post->post_content;
                                        $content_duration = get_post_meta($content_id, '_sikshya_duration', true);
                                        $content_type = str_replace('sik_', '', $content_post->post_type);
                                        
                                        echo $this->generateContentHTML($content_id, $content_title, $content_description, $content_duration, $content_type);
                                    }
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add More Content -->
                    <div class="sikshya-add-lesson">
                        <button class="sikshya-add-lesson-btn" onclick="addContent('chapter-<?php echo esc_attr($chapter_id); ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Content
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle load curriculum
     */
    public function handleLoadCurriculum(): void
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

            $course_id = $this->getCourseIdFromContext();
            
            error_log('Sikshya: Loading curriculum for course_id: ' . $course_id);
            
            if ($course_id === 0) {
                $this->sendError('Invalid course ID');
                return;
            }
            
            // Load complete curriculum structure
            $curriculum_html = $this->generateCurriculumHTML($course_id);
            
            error_log('Sikshya: Generated curriculum HTML length: ' . strlen($curriculum_html));
            
            $this->sendSuccess(['html' => $curriculum_html], 'Curriculum loaded successfully');
            
        } catch (\Exception $e) {
            $this->logError('Load curriculum error', $e);
            $this->sendError('Failed to load curriculum: ' . $e->getMessage());
        }
    }

    /**
     * Handle load chapter data
     */
    public function handleLoadChapterData(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_course_builder')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
                return;
            }
            
            if (!$this->checkCapability()) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $chapter_id = intval($this->getPostData('chapter_id', 0));
            
            error_log('Sikshya: handleLoadChapterData called with chapter_id: ' . $chapter_id);
            
            if ($chapter_id <= 0) {
                error_log('Sikshya: Invalid chapter ID: ' . $chapter_id);
                wp_send_json_error(['message' => 'Invalid chapter ID']);
                return;
            }
        
        // Get chapter data
        $chapter = get_post($chapter_id);
        error_log('Sikshya: Chapter post retrieved: ' . ($chapter ? 'YES' : 'NO'));
        
        if (!$chapter || $chapter->post_type !== PostTypes::CHAPTER) {
            error_log('Sikshya: Chapter not found or wrong post type. Post type: ' . ($chapter ? $chapter->post_type : 'NULL'));
            wp_send_json_error(['message' => 'Chapter not found']);
            return;
        }
        
        // Get chapter meta data
        $duration = get_post_meta($chapter_id, '_sikshya_duration', true);
        $order = get_post_meta($chapter_id, '_sikshya_order', true);
        
        error_log('Sikshya: Chapter meta data - duration: ' . $duration . ', order: ' . $order);
        
        $chapter_data = [
            'id' => $chapter_id,
            'title' => $chapter->post_title,
            'description' => $chapter->post_content,
            'duration' => $duration ?: '',
            'order' => $order ?: 1,
        ];
        
        error_log('Sikshya: Chapter data prepared: ' . print_r($chapter_data, true));
        
        $this->sendSuccess($chapter_data);
        
        } catch (\Exception $e) {
            $this->logError('Load chapter data error', $e);
            $this->sendError('Failed to load chapter data: ' . $e->getMessage());
        }
    }

    /**
     * Handle update chapter
     */
    public function handleUpdateChapter(): void
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
            $title = sanitize_text_field($this->getPostData('title', ''));
            $description = sanitize_textarea_field($this->getPostData('description', ''));
            $duration = sanitize_text_field($this->getPostData('duration', ''));
            $order = intval($this->getPostData('order', 1));
            
            if ($chapter_id <= 0) {
                $this->sendError('Invalid chapter ID');
                return;
            }
            
            if (empty($title)) {
                $this->sendError('Chapter title is required');
                return;
            }
            
            // Update chapter post
            $update_result = wp_update_post([
                'ID' => $chapter_id,
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => PostTypes::CHAPTER,
            ]);
            
            if (is_wp_error($update_result)) {
                $this->sendError('Failed to update chapter: ' . $update_result->get_error_message());
                return;
            }
            
            // Update meta fields
            update_post_meta($chapter_id, '_sikshya_duration', $duration);
            update_post_meta($chapter_id, '_sikshya_order', $order);
            
            $this->sendSuccess([
                'message' => 'Chapter updated successfully',
                'chapter_id' => $chapter_id
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Update chapter error', $e);
            $this->sendError('Failed to update chapter: ' . $e->getMessage());
        }
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
            'lesson' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253"/></svg>',
            'quiz' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'assignment' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        ];
        
        return $icons[$content_type] ?? $icons['lesson'];
    }

    /**
     * Handle bulk delete items AJAX request
     */
    public function handleBulkDeleteItems()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $chapters = $_POST['chapters'] ?? [];
        $lessons = $_POST['lessons'] ?? [];
        
        $deleted_count = 0;
        $errors = [];

        // Delete chapters
        foreach ($chapters as $chapter_id) {
            if (wp_delete_post($chapter_id, true)) {
                $deleted_count++;
            } else {
                $errors[] = "Failed to delete chapter ID: $chapter_id";
            }
        }

        // Delete lessons
        foreach ($lessons as $lesson_id) {
            if (wp_delete_post($lesson_id, true)) {
                $deleted_count++;
            } else {
                $errors[] = "Failed to delete lesson ID: $lesson_id";
            }
        }

        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Some items could not be deleted',
                'errors' => $errors,
                'deleted_count' => $deleted_count
            ]);
        } else {
            wp_send_json_success([
                'message' => "Successfully deleted $deleted_count items",
                'deleted_count' => $deleted_count
            ]);
        }
    }
}
