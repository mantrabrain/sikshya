<?php

namespace Sikshya\Models;

use WP_Post;

/**
 * Quiz Model
 *
 * Handles all quiz-related data operations
 *
 * @package Sikshya\Models
 */
class Quiz
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // No dependencies needed for this model
    }

    /**
     * Get all quizzes
     *
     * @param array $args Query arguments
     * @return array Array of quizzes
     */
    public function getAll(array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_quiz',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $query = new \WP_Query($args);

        return $query->posts;
    }

    /**
     * Get quiz by ID
     *
     * @param int $quiz_id Quiz ID
     * @return WP_Post|null Quiz post or null
     */
    public function getById(int $quiz_id): ?WP_Post
    {
        $quiz = get_post($quiz_id);

        if (!$quiz || $quiz->post_type !== 'sikshya_quiz') {
            return null;
        }

        return $quiz;
    }

    /**
     * Create a new quiz
     *
     * @param array $data Quiz data
     * @return int|WP_Error Quiz ID or error
     */
    public function create(array $data)
    {
        $defaults = [
            'post_title' => '',
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'meta_input' => []
        ];

        $data = wp_parse_args($data, $defaults);

        // Set post type
        $data['post_type'] = 'sikshya_quiz';

        // Create the quiz
        $quiz_id = wp_insert_post($data);

        if (is_wp_error($quiz_id)) {
            return $quiz_id;
        }

        // Set default meta values
        $this->setDefaultMeta($quiz_id);

        return $quiz_id;
    }

    /**
     * Update quiz
     *
     * @param int $quiz_id Quiz ID
     * @param array $data Quiz data
     * @return int|WP_Error Quiz ID or error
     */
    public function update(int $quiz_id, array $data)
    {
        $data['ID'] = $quiz_id;
        $data['post_type'] = 'sikshya_quiz';

        return wp_update_post($data);
    }

    /**
     * Delete quiz
     *
     * @param int $quiz_id Quiz ID
     * @return bool|WP_Error Success or error
     */
    public function delete(int $quiz_id)
    {
        return wp_delete_post($quiz_id, true);
    }

    /**
     * Get quiz meta
     *
     * @param int $quiz_id Quiz ID
     * @param string $key Meta key
     * @param bool $single Whether to return a single value
     * @return mixed Meta value
     */
    public function getMeta(int $quiz_id, string $key, bool $single = true)
    {
        return get_post_meta($quiz_id, $key, $single);
    }

    /**
     * Set quiz meta
     *
     * @param int $quiz_id Quiz ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return int|bool Meta ID or false
     */
    public function setMeta(int $quiz_id, string $key, $value)
    {
        return update_post_meta($quiz_id, $key, $value);
    }

    /**
     * Get quiz course
     *
     * @param int $quiz_id Quiz ID
     * @return int Course ID
     */
    public function getCourse(int $quiz_id): int
    {
        $course = $this->getMeta($quiz_id, '_sikshya_quiz_course', true);
        return (int) ($course ?: 0);
    }

    /**
     * Set quiz course
     *
     * @param int $quiz_id Quiz ID
     * @param int $course_id Course ID
     * @return int|bool Meta ID or false
     */
    public function setCourse(int $quiz_id, int $course_id)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_course', $course_id);
    }

    /**
     * Get quiz duration
     *
     * @param int $quiz_id Quiz ID
     * @return int Quiz duration in minutes
     */
    public function getDuration(int $quiz_id): int
    {
        $duration = $this->getMeta($quiz_id, '_sikshya_quiz_duration', true);
        return (int) ($duration ?: 0);
    }

    /**
     * Set quiz duration
     *
     * @param int $quiz_id Quiz ID
     * @param int $duration Quiz duration in minutes
     * @return int|bool Meta ID or false
     */
    public function setDuration(int $quiz_id, int $duration)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_duration', $duration);
    }

    /**
     * Get quiz attempts limit
     *
     * @param int $quiz_id Quiz ID
     * @return int Attempts limit
     */
    public function getAttemptsLimit(int $quiz_id): int
    {
        $attempts = $this->getMeta($quiz_id, '_sikshya_quiz_attempts_limit', true);
        return (int) ($attempts ?: 1);
    }

    /**
     * Set quiz attempts limit
     *
     * @param int $quiz_id Quiz ID
     * @param int $attempts Attempts limit
     * @return int|bool Meta ID or false
     */
    public function setAttemptsLimit(int $quiz_id, int $attempts)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_attempts_limit', $attempts);
    }

    /**
     * Get quiz passing score
     *
     * @param int $quiz_id Quiz ID
     * @return int Passing score percentage
     */
    public function getPassingScore(int $quiz_id): int
    {
        $score = $this->getMeta($quiz_id, '_sikshya_quiz_passing_score', true);
        return (int) ($score ?: 70);
    }

    /**
     * Set quiz passing score
     *
     * @param int $quiz_id Quiz ID
     * @param int $score Passing score percentage
     * @return int|bool Meta ID or false
     */
    public function setPassingScore(int $quiz_id, int $score)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_passing_score', $score);
    }

    /**
     * Get quiz questions
     *
     * @param int $quiz_id Quiz ID
     * @return array Quiz questions
     */
    public function getQuestions(int $quiz_id): array
    {
        $questions = $this->getMeta($quiz_id, '_sikshya_quiz_questions', true);

        if (!is_array($questions)) {
            return [];
        }

        // Process questions to handle different types
        foreach ($questions as &$question) {
            $question = $this->processQuestionData($question);
        }

        return $questions;
    }

    /**
     * Process question data based on type
     *
     * @param array $question
     * @return array
     */
    private function processQuestionData(array $question): array
    {
        $questionTypeService = new \Sikshya\Services\QuestionTypeService();
        $question_type = $question['question_type'] ?? 'multiple_choice';

        // Decode options if they exist
        if (!empty($question['options'])) {
            $question['options'] = json_decode($question['options'], true) ?: [];
        } else {
            $question['options'] = [];
        }

        // Decode correct answer if it exists
        if (!empty($question['correct_answer'])) {
            $question['correct_answer'] = json_decode($question['correct_answer'], true) ?: $question['correct_answer'];
        }

        // Add question type metadata
        $question['type_info'] = $questionTypeService->getQuestionType($question_type);

        return $question;
    }

    /**
     * Set quiz questions
     *
     * @param int $quiz_id Quiz ID
     * @param array $questions Quiz questions
     * @return int|bool Meta ID or false
     */
    public function setQuestions(int $quiz_id, array $questions)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_questions', $questions);
    }

    /**
     * Get quiz settings
     *
     * @param int $quiz_id Quiz ID
     * @return array Quiz settings
     */
    public function getSettings(int $quiz_id): array
    {
        $settings = $this->getMeta($quiz_id, '_sikshya_quiz_settings', true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Set quiz settings
     *
     * @param int $quiz_id Quiz ID
     * @param array $settings Quiz settings
     * @return int|bool Meta ID or false
     */
    public function setSettings(int $quiz_id, array $settings)
    {
        return $this->setMeta($quiz_id, '_sikshya_quiz_settings', $settings);
    }

    /**
     * Get quizzes by course
     *
     * @param int $course_id Course ID
     * @return array Quizzes
     */
    public function getByCourse(int $course_id): array
    {
        $args = [
            'post_type' => 'sikshya_quiz',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_sikshya_quiz_course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Get user quiz attempts
     *
     * @param int $quiz_id Quiz ID
     * @param int $user_id User ID
     * @return array Quiz attempts
     */
    public function getUserAttempts(int $quiz_id, int $user_id): array
    {
        $attempts = get_user_meta($user_id, '_sikshya_quiz_attempts_' . $quiz_id, true);
        return is_array($attempts) ? $attempts : [];
    }

    /**
     * Save user quiz attempt
     *
     * @param int $quiz_id Quiz ID
     * @param int $user_id User ID
     * @param array $attempt_data Attempt data
     * @return bool Success
     */
    public function saveUserAttempt(int $quiz_id, int $user_id, array $attempt_data): bool
    {
        $attempts = $this->getUserAttempts($quiz_id, $user_id);

        $attempt_data['id'] = count($attempts) + 1;
        $attempt_data['timestamp'] = current_time('mysql');
        $attempt_data['score'] = $this->calculateScore($quiz_id, $attempt_data['answers']);
        $attempt_data['passed'] = $attempt_data['score'] >= $this->getPassingScore($quiz_id);

        $attempts[] = $attempt_data;

        return update_user_meta($user_id, '_sikshya_quiz_attempts_' . $quiz_id, $attempts);
    }

    /**
     * Calculate quiz score
     *
     * @param int $quiz_id Quiz ID
     * @param array $answers User answers
     * @return int Score percentage
     */
    public function calculateScore(int $quiz_id, array $answers): int
    {
        $questions = $this->getQuestions($quiz_id);
        $correct_answers = 0;
        $total_points = 0;
        $earned_points = 0;

        if (empty($questions)) {
            return 0;
        }

        $questionTypeService = new \Sikshya\Services\QuestionTypeService();

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $question_type = $question['question_type'] ?? 'multiple_choice';
            $points = (int) ($question['points'] ?? 1);
            $total_points += $points;

            if (isset($answers[$question_id])) {
                $user_answer = $answers[$question_id];
                $correct_answer = $question['correct_answer'] ?? '';
                $options = $question['options'] ?? [];

                if ($this->isAnswerCorrect($question_type, $user_answer, $correct_answer, $options)) {
                    $earned_points += $points;
                }
            }
        }

        return $total_points > 0 ? round(($earned_points / $total_points) * 100) : 0;
    }

    /**
     * Check if user answer is correct based on question type
     *
     * @param string $question_type
     * @param mixed $user_answer
     * @param mixed $correct_answer
     * @param array $options
     * @return bool
     */
    private function isAnswerCorrect(string $question_type, $user_answer, $correct_answer, array $options = []): bool
    {
        $questionTypeService = new \Sikshya\Services\QuestionTypeService();

        switch ($question_type) {
            case 'multiple_choice':
            case 'true_false':
                return $user_answer === $correct_answer;

            case 'fill_blank':
                // Case-insensitive comparison for fill in the blank
                return strtolower(trim($user_answer)) === strtolower(trim($correct_answer));

            case 'matching':
                // For matching questions, check if all pairs match
                if (is_array($user_answer) && is_array($correct_answer)) {
                    return $user_answer === $correct_answer;
                }
                return false;

            case 'essay':
                // Essay questions are not auto-gradable
                return false;

            default:
                return $user_answer === $correct_answer;
        }
    }

    /**
     * Check if user can take quiz
     *
     * @param int $quiz_id Quiz ID
     * @param int $user_id User ID
     * @return bool True if can take quiz
     */
    public function canTakeQuiz(int $quiz_id, int $user_id): bool
    {
        $attempts = $this->getUserAttempts($quiz_id, $user_id);
        $attempts_limit = $this->getAttemptsLimit($quiz_id);

        return count($attempts) < $attempts_limit;
    }

    /**
     * Get user's best quiz score
     *
     * @param int $quiz_id Quiz ID
     * @param int $user_id User ID
     * @return int Best score percentage
     */
    public function getUserBestScore(int $quiz_id, int $user_id): int
    {
        $attempts = $this->getUserAttempts($quiz_id, $user_id);

        if (empty($attempts)) {
            return 0;
        }

        $scores = array_column($attempts, 'score');
        return max($scores);
    }

    /**
     * Check if user passed quiz
     *
     * @param int $quiz_id Quiz ID
     * @param int $user_id User ID
     * @return bool True if passed
     */
    public function hasUserPassed(int $quiz_id, int $user_id): bool
    {
        $best_score = $this->getUserBestScore($quiz_id, $user_id);
        $passing_score = $this->getPassingScore($quiz_id);

        return $best_score >= $passing_score;
    }

    /**
     * Set default meta values for new quiz
     *
     * @param int $quiz_id Quiz ID
     */
    private function setDefaultMeta(int $quiz_id): void
    {
        $defaults = [
            '_sikshya_quiz_course' => 0,
            '_sikshya_quiz_duration' => 0,
            '_sikshya_quiz_attempts_limit' => 1,
            '_sikshya_quiz_passing_score' => 70,
            '_sikshya_quiz_questions' => [],
            '_sikshya_quiz_settings' => [
                'randomize_questions' => false,
                'show_results' => true,
                'allow_review' => true,
            ],
        ];

        foreach ($defaults as $key => $value) {
            $this->setMeta($quiz_id, $key, $value);
        }
    }
}
