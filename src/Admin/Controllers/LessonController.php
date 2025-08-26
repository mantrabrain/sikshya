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
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Lesson', 'sikshya'); ?>
                    </a>
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