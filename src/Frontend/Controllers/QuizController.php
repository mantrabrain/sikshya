<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Quiz Controller
 *
 * @package Sikshya\Frontend\Controllers
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
    }

    /**
     * Display single quiz
     */
    public function single(): void
    {
        $quiz_id = get_the_ID();
        $quiz = get_post($quiz_id);

        if (!$quiz || $quiz->post_type !== 'sikshya_quiz') {
            return;
        }

        // Get quiz data
        $quiz_data = $this->getQuizData($quiz_id);

        // Get course data
        $course_id = $quiz_data['course_id'];
        $course_data = $this->getCourseData($course_id);

        // Check if user is enrolled
        $user_id = get_current_user_id();
        $is_enrolled = $this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id);

        if (!$is_enrolled) {
            wp_redirect(get_permalink($course_id));
            exit;
        }

        // Get quiz attempts
        $attempts = $this->plugin->getService('quiz')->getUserAttempts($quiz_id, $user_id);

        // Check if user can take quiz
        $can_take = $this->canTakeQuiz($quiz_id, $user_id);

        // Load template
        include $this->plugin->getTemplatePath('frontend/single-quiz.php');
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'start_quiz':
                $this->startQuiz();
                break;
            case 'submit_quiz':
                $this->submitQuiz();
                break;
            case 'save_quiz_progress':
                $this->saveQuizProgress();
                break;
            case 'get_quiz_questions':
                $this->getQuizQuestions();
                break;
            case 'get_quiz_results':
                $this->getQuizResults();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Start quiz
     */
    private function startQuiz(): void
    {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$quiz_id) {
            wp_send_json_error(__('Quiz ID is required.', 'sikshya'));
        }

        // Check if user can take quiz
        if (!$this->canTakeQuiz($quiz_id, $user_id)) {
            wp_send_json_error(__('You cannot take this quiz at this time.', 'sikshya'));
        }

        // Start quiz attempt
        $attempt_id = $this->plugin->getService('quiz')->startAttempt($quiz_id, $user_id);

        if ($attempt_id) {
            wp_send_json_success([
                'attempt_id' => $attempt_id,
                'message' => __('Quiz started successfully.', 'sikshya'),
            ]);
        } else {
            wp_send_json_error(__('Failed to start quiz.', 'sikshya'));
        }
    }

    /**
     * Submit quiz
     */
    private function submitQuiz(): void
    {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $attempt_id = intval($_POST['attempt_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$quiz_id || !$attempt_id) {
            wp_send_json_error(__('Quiz ID and Attempt ID are required.', 'sikshya'));
        }

        // Submit quiz
        $result = $this->plugin->getService('quiz')->submitAttempt($attempt_id, $user_id, $answers);

        if ($result) {
            wp_send_json_success([
                'result_id' => $result['result_id'],
                'score' => $result['score'],
                'percentage' => $result['percentage'],
                'passed' => $result['passed'],
                'message' => __('Quiz submitted successfully.', 'sikshya'),
            ]);
        } else {
            wp_send_json_error(__('Failed to submit quiz.', 'sikshya'));
        }
    }

    /**
     * Save quiz progress
     */
    private function saveQuizProgress(): void
    {
        $attempt_id = intval($_POST['attempt_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$attempt_id) {
            wp_send_json_error(__('Attempt ID is required.', 'sikshya'));
        }

        // Save progress
        $result = $this->plugin->getService('quiz')->saveProgress($attempt_id, $user_id, $answers);

        if ($result) {
            wp_send_json_success(__('Progress saved successfully.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to save progress.', 'sikshya'));
        }
    }

    /**
     * Get quiz questions
     */
    private function getQuizQuestions(): void
    {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $attempt_id = intval($_POST['attempt_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$quiz_id || !$attempt_id) {
            wp_send_json_error(__('Quiz ID and Attempt ID are required.', 'sikshya'));
        }

        // Get questions
        $questions = $this->plugin->getService('quiz')->getQuizQuestions($quiz_id, $attempt_id);

        wp_send_json_success($questions);
    }

    /**
     * Get quiz results
     */
    private function getQuizResults(): void
    {
        $result_id = intval($_POST['result_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$result_id) {
            wp_send_json_error(__('Result ID is required.', 'sikshya'));
        }

        // Get results
        $results = $this->plugin->getService('quiz')->getResultDetails($result_id, $user_id);

        wp_send_json_success($results);
    }

    /**
     * Check if user can take quiz
     */
    private function canTakeQuiz(int $quiz_id, int $user_id): bool
    {
        $max_attempts = get_post_meta($quiz_id, 'sikshya_quiz_max_attempts', true);
        $current_attempts = $this->plugin->getService('quiz')->getUserAttempts($quiz_id, $user_id);

        if ($max_attempts && count($current_attempts) >= $max_attempts) {
            return false;
        }

        // Check if user has completed required lessons
        $required_lessons = get_post_meta($quiz_id, 'sikshya_quiz_required_lessons', true);
        if ($required_lessons) {
            foreach ($required_lessons as $lesson_id) {
                $progress = $this->plugin->getService('progress')->getLessonProgress($lesson_id, $user_id);
                if (!$progress['completed']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get quiz data
     */
    private function getQuizData(int $quiz_id): array
    {
        return [
            'id' => $quiz_id,
            'title' => get_the_title($quiz_id),
            'content' => get_post_field('post_content', $quiz_id),
            'excerpt' => get_post_field('post_excerpt', $quiz_id),
            'duration' => get_post_meta($quiz_id, 'sikshya_quiz_duration', true),
            'course_id' => get_post_meta($quiz_id, 'sikshya_quiz_course', true),
            'questions_count' => get_post_meta($quiz_id, 'sikshya_quiz_questions_count', true),
            'passing_score' => get_post_meta($quiz_id, 'sikshya_quiz_passing_score', true),
            'max_attempts' => get_post_meta($quiz_id, 'sikshya_quiz_max_attempts', true),
            'time_limit' => get_post_meta($quiz_id, 'sikshya_quiz_time_limit', true),
            'randomize_questions' => get_post_meta($quiz_id, 'sikshya_quiz_randomize_questions', true),
            'show_results' => get_post_meta($quiz_id, 'sikshya_quiz_show_results', true),
            'allow_review' => get_post_meta($quiz_id, 'sikshya_quiz_allow_review', true),
        ];
    }

    /**
     * Get course data
     */
    private function getCourseData(int $course_id): array
    {
        return [
            'id' => $course_id,
            'title' => get_the_title($course_id),
            'url' => get_permalink($course_id),
            'thumbnail' => get_the_post_thumbnail_url($course_id, 'medium'),
        ];
    }
}
