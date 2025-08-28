<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\Views\FormBuilder;
use Sikshya\Services\LessonService;
use Sikshya\Constants\AdminPages;

/**
 * Lesson Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class LessonController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Lesson service
     *
     * @var LessonService
     */
    private LessonService $lessonService;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->lessonService = new LessonService();
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('wp_ajax_sikshya_lesson_list', [$this, 'handleLessonList']);
        add_action('wp_ajax_sikshya_lesson_save', [$this, 'handleLessonSave']);
        add_action('wp_ajax_sikshya_lesson_delete', [$this, 'handleLessonDelete']);
        add_action('wp_ajax_sikshya_get_lesson_form', [$this, 'handleGetLessonForm']);
        add_action('wp_ajax_sikshya_load_lesson_modal_template', [$this, 'handleLoadLessonModalTemplate']);
    }

    /**
     * Render lessons list page
     */
    public function renderLessonsPage(): void
    {
        // Create and prepare the list table
        $list_table = new \Sikshya\Admin\ListTable\LessonsListTable($this->plugin);
        $list_table->prepare_items();
        
        // Render the page with proper Sikshya design
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <i class="fas fa-book"></i>
                        <?php _e('Lessons', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-add-lesson-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Lesson', 'sikshya'); ?>
                    </button>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <?php _e('Manage Lessons', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('Create, edit, and manage your lessons', 'sikshya'); ?></p>
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

        <!-- Content Type Selection Modal -->
        <div id="content-type-modal" class="sikshya-modal" style="display: none;">
            <div class="sikshya-modal-overlay"></div>
            <div class="sikshya-modal-content">
                <div class="sikshya-modal-header">
                    <h3 id="modal-title"><?php _e('Add New Content', 'sikshya'); ?></h3>
                    <button type="button" class="sikshya-modal-close" onclick="closeLessonModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="sikshya-modal-body" id="modal-body">
                    <!-- Form content will be loaded here -->
                </div>
            </div>
        </div>
        <?php
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
        // For demo purposes, return dummy counts
        return [
            'publish' => 8,  // 8 published lessons
            'draft' => 3,    // 3 draft lessons
            'pending' => 1,  // 1 pending lesson
            'private' => 0   // 0 private lessons
        ];
        
        // TODO: Implement actual status counting logic for lessons
    }

    /**
     * Render add lesson page
     */
    public function renderAddLessonPage(): void
    {
        // Check if type parameter is provided
        $type = $_GET['type'] ?? '';
        
        if (empty($type)) {
            // Show content type selection popup
            $this->renderContentTypeSelectionPopup();
        } else {
            // Show specific form based on type
            $this->renderAddLessonForm($type);
        }
    }

    /**
     * Render content type selection page
     */
    private function renderContentTypeSelectionPopup(): void
    {
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Lesson', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=' . AdminPages::LESSONS); ?>" class="sikshya-btn sikshya-btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <?php _e('Back to Lessons', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <?php _e('Choose Content Type', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('Choose the type of content you want to add to your course.', 'sikshya'); ?></p>
                        </div>
                    </div>
                    <div class="sikshya-content-card-body">
                        <div class="sikshya-content-type-grid">
                            <!-- Text Lesson -->
                            <div class="sikshya-content-type-card sikshya-content-type-text" data-content-type="text">
                                <div class="sikshya-content-type-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-content-type-content">
                                    <h4><?php _e('Text Lesson', 'sikshya'); ?></h4>
                                    <p><?php _e('Rich text content with images and formatting.', 'sikshya'); ?></p>
                                </div>
                            </div>

                            <!-- Video Lesson -->
                            <div class="sikshya-content-type-card sikshya-content-type-video" data-content-type="video">
                                <div class="sikshya-content-type-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-content-type-content">
                                    <h4><?php _e('Video Lesson', 'sikshya'); ?></h4>
                                    <p><?php _e('Upload video files with descriptions.', 'sikshya'); ?></p>
                                </div>
                            </div>

                            <!-- Audio Lesson -->
                            <div class="sikshya-content-type-card sikshya-content-type-audio" data-content-type="audio">
                                <div class="sikshya-content-type-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-content-type-content">
                                    <h4><?php _e('Audio Lesson', 'sikshya'); ?></h4>
                                    <p><?php _e('Audio files with transcripts.', 'sikshya'); ?></p>
                                </div>
                            </div>

                            <!-- Quiz -->
                            <div class="sikshya-content-type-card sikshya-content-type-quiz" data-content-type="quiz">
                                <div class="sikshya-content-type-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-content-type-content">
                                    <h4><?php _e('Quiz', 'sikshya'); ?></h4>
                                    <p><?php _e('Interactive assessments and tests.', 'sikshya'); ?></p>
                                </div>
                            </div>

                            <!-- Assignment -->
                            <div class="sikshya-content-type-card sikshya-content-type-assignment" data-content-type="assignment">
                                <div class="sikshya-content-type-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                </div>
                                <div class="sikshya-content-type-content">
                                    <h4><?php _e('Assignment', 'sikshya'); ?></h4>
                                    <p><?php _e('Student submissions and projects.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render add lesson form based on content type
     */
    public function renderAddLessonForm(string $type): void
    {
        // Map content types to template files
        $template_map = [
            'text' => 'text-lesson.php',
            'video' => 'video-lesson.php',
            'audio' => 'audio-lesson.php',
            'quiz' => 'quiz.php',
            'assignment' => 'assignment.php'
        ];
        
        $template_file = $template_map[$type] ?? 'text-lesson.php';
        $template_path = SIKSHYA_PLUGIN_DIR . 'templates/admin/views/courses/forms/' . $template_file;
        
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <?php echo $this->getContentTypeIcon($type); ?>
                        <?php echo esc_html(ucfirst($type)); ?> <?php _e('Lesson', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=' . AdminPages::ADD_LESSON); ?>" class="sikshya-btn sikshya-btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <?php _e('Back to Content Types', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <?php echo $this->getContentTypeIcon($type); ?>
                                <?php echo esc_html(ucfirst($type)); ?> <?php _e('Lesson Form', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('Create a new', 'sikshya'); ?> <?php echo esc_html($type); ?> <?php _e('lesson for your course', 'sikshya'); ?></p>
                        </div>
                    </div>
                    <div class="sikshya-content-card-body">
                        <form class="sikshya-lesson-form" data-content-type="<?php echo esc_attr($type); ?>">
                            <?php wp_nonce_field('sikshya_lesson_nonce', 'sikshya_lesson_nonce'); ?>
                            <input type="hidden" name="action" value="sikshya_save_lesson">
                            <input type="hidden" name="content_type" value="<?php echo esc_attr($type); ?>">
                            
                            <?php include $template_path; ?>
                            

                            
                            <!-- Form Actions -->
                            <div class="sikshya-form-actions">
                                <button type="submit" class="sikshya-btn sikshya-btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <?php _e('Save Lesson', 'sikshya'); ?>
                                </button>
                                <a href="<?php echo admin_url('admin.php?page=' . AdminPages::LESSONS); ?>" class="sikshya-btn sikshya-btn-secondary">
                                    <?php _e('Cancel', 'sikshya'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle lesson list AJAX request
     */
    public function handleLessonList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'paged' => $_POST['page'] ?? 1,
        ];

        $lessons = $this->lessonService->getAllLessons($args);
        $total = wp_count_posts('sikshya_lesson');

        wp_send_json_success([
            'data' => $lessons,
            'total' => $total->publish + $total->draft + $total->private,
        ]);
    }

    /**
     * Handle lesson save AJAX request
     */
    public function handleLessonSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $data = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'course_id' => intval($_POST['course_id'] ?? 0),
            'type' => sanitize_text_field($_POST['type'] ?? 'text'),
            'media_url' => esc_url_raw($_POST['media_url'] ?? ''),
            'duration' => intval($_POST['duration'] ?? 0),
            'order' => intval($_POST['order'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
        ];

        try {
            if (!empty($_POST['id'])) {
                $lesson_id = $this->lessonService->updateLesson(intval($_POST['id']), $data);
                $message = __('Lesson updated successfully!', 'sikshya');
            } else {
                $lesson_id = $this->lessonService->createLesson($data);
                $message = __('Lesson created successfully!', 'sikshya');
            }

            wp_send_json_success([
                'id' => $lesson_id,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle lesson delete AJAX request
     */
    public function handleLessonDelete(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $lesson_id = intval($_POST['id'] ?? 0);

        try {
            $this->lessonService->deleteLesson($lesson_id);
            wp_send_json_success(__('Lesson deleted successfully!', 'sikshya'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle AJAX request to get lesson form content
     */
    public function handleGetLessonForm(): void
    {
        check_ajax_referer('sikshya_lesson_form_nonce', 'nonce');

        $content_type = sanitize_text_field($_POST['content_type'] ?? 'text');
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
            ob_start();
            include $template_path;
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(__('Form template not found.', 'sikshya'));
        }
    }

    /**
     * Handle load lesson modal template AJAX request
     */
    public function handleLoadLessonModalTemplate(): void
    {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_lesson')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $modal_type = sanitize_text_field($_POST['modal_type'] ?? '');
            
            // Load specific modal template based on type
            if ($modal_type === 'content-type') {
                $template = $this->loadLessonContentTypeModalTemplate();
            } else {
                $template = $this->getLessonModalTemplate($modal_type);
            }
            
            wp_send_json_success(['html' => $template]);
            
        } catch (\Exception $e) {
            wp_send_json_error('Failed to load modal template: ' . $e->getMessage());
        }
    }

    /**
     * Load lesson content type modal template
     */
    private function loadLessonContentTypeModalTemplate(): string
    {
        ob_start();
        
        // Include the lesson content type modal template
        include SIKSHYA_PLUGIN_DIR . 'templates/admin/views/courses/modal-content-type.php';
        
        return ob_get_clean();
    }

    /**
     * Get lesson modal template HTML
     */
    private function getLessonModalTemplate(string $modal_type): string
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
     * Get content type icon
     */
    private function getContentTypeIcon(string $type): string
    {
        $icons = [
            'text' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'video' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
            'audio' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>',
            'quiz' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'assignment' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
        ];
        
        return $icons[$type] ?? $icons['text'];
    }

    /**
     * Get courses list for dropdown
     */
    private function getCoursesList(): array
    {
        $courses = get_posts([
            'post_type' => 'sikshya_course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $list = ['' => __('Select Course', 'sikshya')];
        foreach ($courses as $course) {
            $list[$course->ID] = $course->post_title;
        }

        return $list;
    }
} 