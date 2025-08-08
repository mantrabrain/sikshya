<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\Views\FormBuilder;

/**
 * Quiz Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class QuizController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('wp_ajax_sikshya_quiz_list', [$this, 'handleQuizList']);
        add_action('wp_ajax_sikshya_quiz_save', [$this, 'handleQuizSave']);
        add_action('wp_ajax_sikshya_quiz_delete', [$this, 'handleQuizDelete']);
    }

    /**
     * Render quizzes list page
     */
    public function renderQuizzesPage(): void
    {
        $dataTable = new DataTable($this->plugin, [
            'id' => 'sikshya-quizzes-table',
            'title' => __('Quizzes', 'sikshya'),
            'description' => __('Manage your quizzes', 'sikshya'),
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

        $dataTable->addColumn('questions', [
            'title' => __('Questions', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('time_limit', [
            'title' => __('Time Limit', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('passing_score', [
            'title' => __('Passing Score', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('attempts', [
            'title' => __('Attempts', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('status', [
            'title' => __('Status', 'sikshya'),
            'sortable' => true,
        ]);

        // Add actions
        $dataTable->addAction('edit', [
            'title' => __('Edit', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-edit-quiz&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('questions', [
            'title' => __('Questions', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-quiz-questions&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('delete', [
            'title' => __('Delete', 'sikshya'),
            'url' => '#',
            'class' => 'button button-small button-link-delete',
            'onclick' => 'sikshya.deleteQuiz({id})',
        ]);

        // Add bulk actions
        $dataTable->addBulkAction('delete', [
            'title' => __('Delete Selected', 'sikshya'),
            'action' => 'sikshya_bulk_delete_quizzes',
        ]);

        $dataTable->addBulkAction('publish', [
            'title' => __('Publish Selected', 'sikshya'),
            'action' => 'sikshya_bulk_publish_quizzes',
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
        ]);

        echo $dataTable->renderTable();
    }

    /**
     * Render add quiz page
     */
    public function renderAddQuizPage(): void
    {
        $formBuilder = new FormBuilder($this->plugin, [
            'id' => 'sikshya-add-quiz-form',
            'title' => __('Add New Quiz', 'sikshya'),
            'description' => __('Create a new quiz for your course', 'sikshya'),
            'action' => admin_url('admin-ajax.php'),
        ]);

        // Add form fields
        $formBuilder->addFields([
            'title' => [
                'type' => 'text',
                'label' => __('Quiz Title', 'sikshya'),
                'required' => true,
                'placeholder' => __('Enter quiz title', 'sikshya'),
                'help' => __('The title of your quiz', 'sikshya'),
            ],
            'course_id' => [
                'type' => 'select',
                'label' => __('Course', 'sikshya'),
                'required' => true,
                'options' => $this->getCoursesList(),
                'help' => __('Select the course this quiz belongs to', 'sikshya'),
            ],
            'description' => [
                'type' => 'textarea',
                'label' => __('Description', 'sikshya'),
                'placeholder' => __('Enter quiz description', 'sikshya'),
                'help' => __('Brief description of the quiz', 'sikshya'),
                'rows' => 3,
            ],
            'time_limit' => [
                'type' => 'number',
                'label' => __('Time Limit (minutes)', 'sikshya'),
                'min' => '0',
                'help' => __('Time limit in minutes (0 for no limit)', 'sikshya'),
            ],
            'passing_score' => [
                'type' => 'number',
                'label' => __('Passing Score (%)', 'sikshya'),
                'min' => '0',
                'max' => '100',
                'default' => '70',
                'help' => __('Minimum score required to pass', 'sikshya'),
            ],
            'max_attempts' => [
                'type' => 'number',
                'label' => __('Max Attempts', 'sikshya'),
                'min' => '0',
                'default' => '3',
                'help' => __('Maximum number of attempts allowed (0 for unlimited)', 'sikshya'),
            ],
            'randomize_questions' => [
                'type' => 'checkbox',
                'label' => __('Randomize Questions', 'sikshya'),
                'help' => __('Randomize question order for each attempt', 'sikshya'),
            ],
            'show_results' => [
                'type' => 'checkbox',
                'label' => __('Show Results', 'sikshya'),
                'help' => __('Show results immediately after completion', 'sikshya'),
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
                'help' => __('Quiz publication status', 'sikshya'),
            ],
        ]);

        echo $formBuilder->renderForm();
    }

    /**
     * Handle quiz list AJAX request
     */
    public function handleQuizList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $args = [
            'post_type' => 'sikshya_quiz',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'paged' => $_POST['page'] ?? 1,
        ];

        $quizzes = get_posts($args);
        $total = wp_count_posts('sikshya_quiz');

        wp_send_json_success([
            'data' => $quizzes,
            'total' => $total->publish + $total->draft + $total->private,
        ]);
    }

    /**
     * Handle quiz save AJAX request
     */
    public function handleQuizSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $data = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'course_id' => intval($_POST['course_id'] ?? 0),
            'time_limit' => intval($_POST['time_limit'] ?? 0),
            'passing_score' => intval($_POST['passing_score'] ?? 70),
            'max_attempts' => intval($_POST['max_attempts'] ?? 3),
            'randomize_questions' => isset($_POST['randomize_questions']),
            'show_results' => isset($_POST['show_results']),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
        ];

        try {
            if (!empty($_POST['id'])) {
                $quiz_id = $this->updateQuiz(intval($_POST['id']), $data);
                $message = __('Quiz updated successfully!', 'sikshya');
            } else {
                $quiz_id = $this->createQuiz($data);
                $message = __('Quiz created successfully!', 'sikshya');
            }

            wp_send_json_success([
                'id' => $quiz_id,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle quiz delete AJAX request
     */
    public function handleQuizDelete(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $quiz_id = intval($_POST['id'] ?? 0);

        try {
            $result = wp_delete_post($quiz_id, true);
            if (!$result) {
                throw new \Exception(__('Failed to delete quiz.', 'sikshya'));
            }

            wp_send_json_success(__('Quiz deleted successfully!', 'sikshya'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Create quiz
     */
    private function createQuiz(array $data): int
    {
        $post_data = [
            'post_type' => 'sikshya_quiz',
            'post_title' => $data['title'],
            'post_content' => $data['description'],
            'post_status' => $data['status'],
            'post_author' => get_current_user_id(),
        ];

        $quiz_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($quiz_id)) {
            throw new \Exception($quiz_id->get_error_message());
        }

        // Set meta fields
        update_post_meta($quiz_id, '_sikshya_course_id', $data['course_id']);
        update_post_meta($quiz_id, '_sikshya_time_limit', $data['time_limit']);
        update_post_meta($quiz_id, '_sikshya_passing_score', $data['passing_score']);
        update_post_meta($quiz_id, '_sikshya_max_attempts', $data['max_attempts']);
        update_post_meta($quiz_id, '_sikshya_randomize_questions', $data['randomize_questions']);
        update_post_meta($quiz_id, '_sikshya_show_results', $data['show_results']);

        return $quiz_id;
    }

    /**
     * Update quiz
     */
    private function updateQuiz(int $quiz_id, array $data): bool
    {
        $post_data = [
            'ID' => $quiz_id,
            'post_title' => $data['title'],
            'post_content' => $data['description'],
            'post_status' => $data['status'],
        ];

        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        // Update meta fields
        update_post_meta($quiz_id, '_sikshya_course_id', $data['course_id']);
        update_post_meta($quiz_id, '_sikshya_time_limit', $data['time_limit']);
        update_post_meta($quiz_id, '_sikshya_passing_score', $data['passing_score']);
        update_post_meta($quiz_id, '_sikshya_max_attempts', $data['max_attempts']);
        update_post_meta($quiz_id, '_sikshya_randomize_questions', $data['randomize_questions']);
        update_post_meta($quiz_id, '_sikshya_show_results', $data['show_results']);

        return true;
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