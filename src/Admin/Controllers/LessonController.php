<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\Views\FormBuilder;
use Sikshya\Services\LessonService;

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
    }

    /**
     * Render lessons list page
     */
    public function renderLessonsPage(): void
    {
        $dataTable = new DataTable($this->plugin, [
            'id' => 'sikshya-lessons-table',
            'title' => __('Lessons', 'sikshya'),
            'description' => __('Manage your lessons', 'sikshya'),
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

        $dataTable->addColumn('course', [
            'title' => __('Course', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('type', [
            'title' => __('Type', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('duration', [
            'title' => __('Duration', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('order', [
            'title' => __('Order', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('status', [
            'title' => __('Status', 'sikshya'),
            'sortable' => true,
        ]);

        // Add actions
        $dataTable->addAction('edit', [
            'title' => __('Edit', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-edit-lesson&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('delete', [
            'title' => __('Delete', 'sikshya'),
            'url' => '#',
            'class' => 'button button-small button-link-delete',
            'onclick' => 'sikshya.deleteLesson({id})',
        ]);

        // Add bulk actions
        $dataTable->addBulkAction('delete', [
            'title' => __('Delete Selected', 'sikshya'),
            'action' => 'sikshya_bulk_delete_lessons',
        ]);

        $dataTable->addBulkAction('publish', [
            'title' => __('Publish Selected', 'sikshya'),
            'action' => 'sikshya_bulk_publish_lessons',
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
            'course' => [
                'type' => 'select',
                'title' => __('Course', 'sikshya'),
                'options' => $this->getCoursesList(),
            ],
            'type' => [
                'type' => 'select',
                'title' => __('Type', 'sikshya'),
                'options' => [
                    '' => __('All Types', 'sikshya'),
                    'text' => __('Text', 'sikshya'),
                    'video' => __('Video', 'sikshya'),
                    'audio' => __('Audio', 'sikshya'),
                    'file' => __('File', 'sikshya'),
                    'quiz' => __('Quiz', 'sikshya'),
                ],
            ],
        ]);

        echo $dataTable->renderTable();
    }

    /**
     * Render add lesson page
     */
    public function renderAddLessonPage(): void
    {
        $formBuilder = new FormBuilder($this->plugin, [
            'id' => 'sikshya-add-lesson-form',
            'title' => __('Add New Lesson', 'sikshya'),
            'description' => __('Create a new lesson for your course', 'sikshya'),
            'action' => admin_url('admin-ajax.php'),
        ]);

        // Add form fields
        $formBuilder->addFields([
            'title' => [
                'type' => 'text',
                'label' => __('Lesson Title', 'sikshya'),
                'required' => true,
                'placeholder' => __('Enter lesson title', 'sikshya'),
                'help' => __('The title of your lesson', 'sikshya'),
            ],
            'course_id' => [
                'type' => 'select',
                'label' => __('Course', 'sikshya'),
                'required' => true,
                'options' => $this->getCoursesList(),
                'help' => __('Select the course this lesson belongs to', 'sikshya'),
            ],
            'content' => [
                'type' => 'wysiwyg',
                'label' => __('Lesson Content', 'sikshya'),
                'required' => true,
                'help' => __('The main content of your lesson', 'sikshya'),
            ],
            'type' => [
                'type' => 'select',
                'label' => __('Lesson Type', 'sikshya'),
                'required' => true,
                'options' => [
                    'text' => __('Text', 'sikshya'),
                    'video' => __('Video', 'sikshya'),
                    'audio' => __('Audio', 'sikshya'),
                    'file' => __('File', 'sikshya'),
                    'quiz' => __('Quiz', 'sikshya'),
                ],
                'default' => 'text',
                'help' => __('The type of lesson content', 'sikshya'),
            ],
            'media_url' => [
                'type' => 'url',
                'label' => __('Media URL', 'sikshya'),
                'help' => __('URL for video, audio, or file (optional)', 'sikshya'),
            ],
            'duration' => [
                'type' => 'number',
                'label' => __('Duration (minutes)', 'sikshya'),
                'min' => '0',
                'help' => __('Estimated lesson duration in minutes', 'sikshya'),
            ],
            'order' => [
                'type' => 'number',
                'label' => __('Order', 'sikshya'),
                'min' => '0',
                'help' => __('Lesson order within the course', 'sikshya'),
            ],
            'status' => [
                'type' => 'select',
                'label' => __('Status', 'sikshya'),
                'options' => [
                    'draft' => __('Draft', 'sikshya'),
                    'publish' => __('Published', 'sikshya'),
                    'private' => __('Private', 'sikshya'),
                ],
                'default' => 'draft',
                'help' => __('Lesson publication status', 'sikshya'),
            ],
        ]);

        echo $formBuilder->renderForm();
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