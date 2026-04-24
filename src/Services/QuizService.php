<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\QuizAttemptRepository;

/**
 * Learner quiz runtime + stats (table-backed).
 *
 * This is used by legacy frontend controllers and the learner dashboard stats.
 *
 * @package Sikshya\Services
 */
final class QuizService
{
    public function getUserQuizzesCount(int $user_id): int
    {
        return (new QuizAttemptRepository())->countDistinctQuizzesForUser($user_id);
    }

    public function getPassedQuizzesCount(int $user_id): int
    {
        // "Passed" means completed attempt at/above passing score for that quiz.
        // We approximate by using the attempt score and quiz passing score meta.
        $rows = (new QuizAttemptRepository())->bestScoresByQuizForUser($user_id);

        $passed = 0;
        foreach ($rows as $r) {
            $qid = (int) $r->quiz_id;
            if ($qid <= 0) {
                continue;
            }
            $passing = (float) get_post_meta($qid, '_sikshya_quiz_passing_score', true);
            if ($passing <= 0) {
                $passing = 70.0;
            }
            if ((float) $r->best_score >= $passing) {
                $passed++;
            }
        }

        return $passed;
    }

    public function getAverageQuizScore(int $user_id): float
    {
        $avg = (new QuizAttemptRepository())->averageScoreForUser($user_id);
        return $avg !== 0.0 ? round($avg, 2) : 0.0;
    }

    /**
     * Legacy frontend: return attempt rows for quiz.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserAttempts(int $quiz_id, int $user_id): array
    {
        $rows = (new QuizAttemptRepository())->listAttemptsForUserQuiz($user_id, $quiz_id, 50);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'attempt_number' => (int) $r->attempt_number,
                'score' => (float) $r->score,
                'status' => (string) $r->status,
                'started_at' => (string) $r->started_at,
                'completed_at' => (string) ($r->completed_at ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Legacy frontend: create an in-progress attempt shell.
     */
    public function startAttempt(int $quiz_id, int $user_id): int
    {
        $quiz_id = absint($quiz_id);
        $user_id = absint($user_id);
        if ($quiz_id <= 0 || $user_id <= 0) {
            return 0;
        }
        if (get_post_type($quiz_id) !== PostTypes::QUIZ) {
            return 0;
        }

        $course_id = (int) get_post_meta($quiz_id, '_sikshya_quiz_course', true);
        $attempt_number = $this->countCompletedAttempts($quiz_id, $user_id) + 1;
        return (new QuizAttemptRepository())->createAttempt([
            'user_id' => $user_id,
            'quiz_id' => $quiz_id,
            'course_id' => $course_id,
            'attempt_number' => $attempt_number,
            'score' => 0.00,
            'total_questions' => 0,
            'correct_answers' => 0,
            'time_taken' => 0,
            'status' => 'in_progress',
            'started_at' => current_time('mysql'),
            'completed_at' => null,
            'answers_data' => null,
        ]);
    }

    /**
     * Legacy frontend: save answers draft to the attempt row.
     *
     * @param array<string, mixed> $answers
     * @return array{success: bool, message?: string}
     */
    public function saveProgress(int $attempt_id, int $user_id, array $answers): array
    {
        $attempt_id = absint($attempt_id);
        $user_id = absint($user_id);
        if ($attempt_id <= 0 || $user_id <= 0) {
            return ['success' => false, 'message' => __('Invalid request.', 'sikshya')];
        }

        $ok = (new QuizAttemptRepository())->updateAnswersData($attempt_id, $user_id, (string) wp_json_encode($answers));

        return $ok ? ['success' => true] : ['success' => false, 'message' => __('Could not save quiz progress.', 'sikshya')];
    }

    /**
     * Legacy frontend: score and complete an in-progress attempt.
     *
     * @param array<string, mixed> $answers
     * @return array{success: bool, result?: array<string, mixed>, message?: string}
     */
    public function submitAttempt(int $attempt_id, int $user_id, array $answers): array
    {
        $repo = new QuizAttemptRepository();
        $row = $repo->getAttemptForUser($attempt_id, $user_id);
        if (!$row) {
            return ['success' => false, 'message' => __('Attempt not found.', 'sikshya')];
        }

        $quiz_id = (int) $row->quiz_id;
        $course_id = (int) $row->course_id;

        $question_ids = get_post_meta($quiz_id, '_sikshya_quiz_questions', true);
        if (!is_array($question_ids)) {
            $question_ids = [];
        }
        $question_ids = array_map('intval', $question_ids);

        $total_points = 0.0;
        $earned_points = 0.0;
        $correct_count = 0;

        foreach ($question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $type = (string) get_post_meta($qid, '_sikshya_question_type', true);
            $points = (float) get_post_meta($qid, '_sikshya_question_points', true);
            if ($points < 0) {
                $points = 0;
            }
            $correct = (string) get_post_meta($qid, '_sikshya_question_correct_answer', true);
            $raw_answer = $answers[(string) $qid] ?? $answers[$qid] ?? '';

            $total_points += $points;
            $eval = $this->evaluateAnswer($type, $correct, $raw_answer);
            if ($type !== 'essay' && $eval['correct']) {
                $earned_points += $points;
                $correct_count++;
            }
        }

        $score_percent = $total_points > 0 ? round($earned_points / $total_points * 100, 2) : 0.0;

        $repo->updateAttempt((int) $row->id, $user_id, [
            'score' => $score_percent,
            'total_questions' => count($question_ids),
            'correct_answers' => $correct_count,
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'answers_data' => wp_json_encode($answers),
        ]);

        // Mark quiz complete if passed.
        $passing = (float) get_post_meta($quiz_id, '_sikshya_quiz_passing_score', true);
        if ($passing <= 0) {
            $passing = 70.0;
        }
        $passed = $score_percent >= $passing;
        if ($passed) {
            $progress = new \Sikshya\Database\Repositories\ProgressRepository();
            $progress->markQuizComplete($user_id, $course_id, $quiz_id, $score_percent);
        }

        return [
            'success' => true,
            'result' => [
                'attempt_id' => (int) $row->id,
                'quiz_id' => $quiz_id,
                'course_id' => $course_id,
                'score_percent' => $score_percent,
                'passed' => $passed,
                'passing_score' => $passing,
            ],
        ];
    }

    /**
     * Legacy frontend: return question payload for the quiz UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQuizQuestions(int $quiz_id, int $attempt_id = 0): array
    {
        unset($attempt_id);

        $quiz_id = absint($quiz_id);
        if ($quiz_id <= 0) {
            return [];
        }

        $question_ids = get_post_meta($quiz_id, '_sikshya_quiz_questions', true);
        if (!is_array($question_ids)) {
            $question_ids = [];
        }
        $question_ids = array_map('intval', $question_ids);

        $questions = [];
        foreach ($question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $questions[] = [
                'id' => $qid,
                'type' => (string) get_post_meta($qid, '_sikshya_question_type', true),
                'text' => (string) get_post_meta($qid, '_sikshya_question_text', true),
                'options' => get_post_meta($qid, '_sikshya_question_options', true),
                'points' => (float) get_post_meta($qid, '_sikshya_question_points', true),
            ];
        }

        return $questions;
    }

    /**
     * Legacy frontend: best-effort detail payload for a completed attempt.
     *
     * @return array<string, mixed>
     */
    public function getResultDetails(int $result_id, int $user_id): array
    {
        $row = (new QuizAttemptRepository())->getAttemptForUser($result_id, $user_id);
        if (!$row) {
            return [];
        }

        $answers = [];
        if (!empty($row->answers_data)) {
            $decoded = json_decode((string) $row->answers_data, true);
            if (is_array($decoded)) {
                $answers = $decoded;
            }
        }

        return [
            'attempt_id' => (int) $row->id,
            'quiz_id' => (int) $row->quiz_id,
            'course_id' => (int) $row->course_id,
            'score' => (float) $row->score,
            'total_questions' => (int) $row->total_questions,
            'correct_answers' => (int) $row->correct_answers,
            'answers' => $answers,
            'status' => (string) $row->status,
            'completed_at' => (string) ($row->completed_at ?? ''),
        ];
    }

    private function countCompletedAttempts(int $quiz_id, int $user_id): int
    {
        return (new QuizAttemptRepository())->countAttemptsForUserQuiz($user_id, $quiz_id);
    }

    /**
     * @return array{correct: bool}
     */
    private function evaluateAnswer(string $type, string $correct, mixed $answer): array
    {
        $c = trim($correct);

        if ($type === 'essay') {
            return ['correct' => false];
        }

        if ($type === 'multiple_choice' || $type === 'true_false') {
            $a = $this->normalizeScalarAnswer($answer);
            return ['correct' => strcasecmp($a, $c) === 0];
        }

        if ($type === 'short_answer' || $type === 'fill_blank') {
            $u = is_string($answer) ? trim($answer) : '';
            if ($u === '') {
                return ['correct' => false];
            }
            $opts = array_map('trim', explode('|', $c));
            $ul = strtolower($u);
            foreach ($opts as $o) {
                if ($o !== '' && strtolower($o) === $ul) {
                    return ['correct' => true];
                }
            }
            return ['correct' => false];
        }

        if ($type === 'multiple_response') {
            $exp = json_decode($c, true);
            if (!is_array($exp)) {
                return ['correct' => false];
            }
            $user = is_array($answer) ? $answer : (is_string($answer) ? json_decode($answer, true) : []);
            if (!is_array($user)) {
                return ['correct' => false];
            }
            $exp = array_values(array_unique(array_map('strval', $exp)));
            $user = array_values(array_unique(array_map('strval', $user)));
            sort($exp);
            sort($user);
            return ['correct' => $exp === $user];
        }

        return ['correct' => false];
    }

    private function normalizeScalarAnswer(mixed $answer): string
    {
        if (is_bool($answer)) {
            return $answer ? 'true' : 'false';
        }
        if (is_numeric($answer)) {
            return (string) $answer;
        }
        if (is_string($answer)) {
            return trim($answer);
        }
        return '';
    }
}

