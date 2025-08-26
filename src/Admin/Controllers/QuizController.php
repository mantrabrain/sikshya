<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\Views\FormBuilder;
use Sikshya\Admin\ListTable\QuizzesListTable;

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
        // Create and prepare the list table
        $list_table = new QuizzesListTable($this->plugin);
        $list_table->prepare_items();
        
        // Render the page with proper Sikshya design
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <i class="fas fa-question-circle"></i>
                        <?php _e('Quizzes', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Quiz', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?php _e('Manage Quizzes', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('Create, edit, and manage your quizzes', 'sikshya'); ?></p>
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
            echo esc_html__('Pending Review', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['pending']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Private tab
        if (isset($status_counts['private'])) {
            $private_class = ($current_status === 'private') ? 'current' : '';
            $private_url = add_query_arg('post_status', 'private', $base_url);
            echo '<li class="private">';
            echo '<a href="' . esc_url($private_url) . '" class="' . esc_attr($private_class) . '">';
            echo esc_html__('Private', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['private']) . ')</span>';
            echo '</a> |</li>';
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
            'all' => 8,
            'publish' => 5,
            'draft' => 2,
            'pending' => 1,
            'private' => 0,
        ];
        
        // TODO: Implement actual status counting
        /*
        global $wpdb;
        
        $counts = [
            'all' => 0,
            'publish' => 0,
            'draft' => 0,
            'pending' => 0,
            'private' => 0,
        ];
        
        $results = $wpdb->get_results("
            SELECT post_status, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'sik_quiz'
            GROUP BY post_status
        ");
        
        foreach ($results as $result) {
            $counts[$result->post_status] = $result->count;
            $counts['all'] += $result->count;
        }
        
        return $counts;
        */
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