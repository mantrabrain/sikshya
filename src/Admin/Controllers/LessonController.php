<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;
use Sikshya\Services\LessonService;
use Sikshya\Constants\PostTypes;

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
     * Capability gate for lesson admin AJAX.
     */
    private function assertCanManageLessons(): void
    {
        if (!current_user_can('manage_sikshya') && !current_user_can('edit_sikshya_lessons') && !current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions.', 'sikshya'), 403);
        }
    }

    /**
     * Render lessons list page
     */
    public function renderLessonsPage(): void
    {
        ReactAdminView::render('lessons', []);
    }

    /**
     * Render add lesson page
     */
    public function renderAddLessonPage(): void
    {
        ReactAdminView::render('add-lesson', []);
    }

    /**
     * Legacy hook: route standalone lesson form URLs to the React shell.
     *
     * @param string $type Unused content-type slug (reserved for future React routing).
     */
    public function renderAddLessonForm(string $type): void
    {
        ReactAdminView::render('add-lesson', []);
    }

    /**
     * Handle lesson list AJAX request
     */
    public function handleLessonList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');
        $this->assertCanManageLessons();

        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'paged' => max(1, (int) ($_POST['page'] ?? 1)),
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
        $this->assertCanManageLessons();

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
        $this->assertCanManageLessons();

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
        $this->assertCanManageLessons();

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

            $this->assertCanManageLessons();
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
     * Get courses list for dropdown
     */
    private function getCoursesList(): array
    {
        $courses = get_posts([
            'post_type' => PostTypes::COURSE,
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
