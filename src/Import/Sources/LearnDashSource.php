<?php

declare(strict_types=1);

namespace Sikshya\Import\Sources;

use Sikshya\Import\ImportSourceInterface;
use Sikshya\Import\QuestionTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads a LearnDash installation.
 *
 * LearnDash is three levels deep — course, lesson, topic — where Sikshya is
 * two. The mapping adapts to the course rather than forcing one shape on
 * every site:
 *
 *  - a course that uses **topics** becomes a chapter per lesson, with the
 *    lesson's own content as the first lesson inside it and its topics after;
 *  - a **flat** course, with no topics anywhere, keeps its lessons as lessons
 *    inside one chapter — because turning twenty lessons into twenty chapters
 *    of one lesson each is technically faithful and useless to read.
 *
 * Three things are read from the database rather than through LearnDash's own
 * APIs, because the usual migration happens with LearnDash **deactivated**:
 *
 *  - the course structure, from the `ld_course_steps` meta and the per-step
 *    `course_id` / `lesson_id` meta;
 *  - enrolments and completions, from the `learndash_user_activity` table;
 *  - quiz questions, from the ProQuiz tables — where the answers are a
 *    serialised array of *objects*. Those are read with
 *    `unserialize(..., ['allowed_classes' => false])`, which never
 *    instantiates anything, and the resulting incomplete objects are then read
 *    property by property. Letting stored data name a class to instantiate is
 *    how deserialisation turns into code execution.
 *
 * @package Sikshya\Import\Sources
 */
final class LearnDashSource implements ImportSourceInterface
{
    public const ID = 'learndash';

    public const CPT_COURSE = 'sfwd-courses';
    public const CPT_LESSON = 'sfwd-lessons';
    public const CPT_TOPIC = 'sfwd-topic';
    public const CPT_QUIZ = 'sfwd-quiz';

    /**
     * ProQuiz answer types, and what each becomes in Sikshya.
     *
     * `sort_answer` and `matrix_sort_answer` are absent for the same reason as
     * Tutor's ordering and matching: the stored options are already in the
     * right order. `assessment_answer` is a survey scale, which is not a
     * graded question at all.
     *
     * @var array<string, string>
     */
    private const QUESTION_TYPES = [
        'single' => QuestionTypes::SINGLE,
        'multiple' => QuestionTypes::MULTIPLE,
        'free_answer' => QuestionTypes::SHORT_ANSWER,
        'cloze_answer' => QuestionTypes::FILL_BLANK,
        'essay' => QuestionTypes::ESSAY,
    ];

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'LearnDash';
    }

    public function detect(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
            self::CPT_COURSE
        )) > 0;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        global $wpdb;

        $countPosts = static function (string $type): int {
            global $wpdb;

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')",
                $type
            ));
        };

        $questions = 0;
        $table = $wpdb->prefix . 'learndash_pro_quiz_question';
        if (self::tableExists($table)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix + literal.
            $questions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        $enrolments = 0;
        $activity = $wpdb->prefix . 'learndash_user_activity';
        if (self::tableExists($activity)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix + literal.
            $enrolments = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id, course_id) FROM {$activity} WHERE activity_type = %s",
                'course'
            ));
        }

        return [
            'course' => $countPosts(self::CPT_COURSE),
            'lesson' => $countPosts(self::CPT_LESSON) + $countPosts(self::CPT_TOPIC),
            'quiz' => $countPosts(self::CPT_QUIZ),
            'question' => $questions,
            'enrolment' => $enrolments,
        ];
    }

    /** @return array<int, int> */
    public function courseIds(int $afterId, int $limit): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
               AND post_status NOT IN ('trash','auto-draft')
               AND ID > %d
             ORDER BY ID ASC
             LIMIT %d",
            self::CPT_COURSE,
            $afterId,
            max(1, min(200, $limit))
        ));

        return array_map('intval', is_array($ids) ? $ids : []);
    }

    /** @return array<string, mixed>|null */
    public function readCourse(int $courseId): ?array
    {
        $course = get_post($courseId);
        if ($course === null || $course->post_type !== self::CPT_COURSE) {
            return null;
        }

        $settings = self::settings($courseId, 'sfwd-courses');

        return [
            'id' => (int) $course->ID,
            'title' => (string) $course->post_title,
            'content' => (string) $course->post_content,
            'excerpt' => (string) $course->post_excerpt,
            'status' => (string) $course->post_status,
            'author' => (int) $course->post_author,
            'thumbnail_id' => (int) get_post_thumbnail_id($courseId),
            'categories' => self::termNames($courseId, 'ld_course_category'),
            'tags' => self::termNames($courseId, 'ld_course_tag'),
            'level' => '',
            'duration' => null,
            'benefits' => '',
            'requirements' => (string) ($settings['sfwd-courses_course_materials'] ?? ''),
            'target_audience' => '',
            'chapters' => $this->readStructure($courseId),
            'enrolments' => $this->readEnrolments($courseId),
            'completions' => $this->readCompletions($courseId, 'course'),
        ];
    }

    /**
     * Turn a LearnDash course into chapters.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readStructure(int $courseId): array
    {
        $lessons = $this->stepIds($courseId, self::CPT_LESSON);
        if ($lessons === []) {
            // A course with no lessons may still have quizzes hung directly
            // off it, and losing those would lose the assessment.
            $courseQuizzes = $this->quizzesFor($courseId, 0);
            if ($courseQuizzes === []) {
                return [];
            }

            return [[
                'id' => $courseId,
                'title' => (string) get_the_title($courseId),
                'summary' => '',
                'contents' => $courseQuizzes,
            ]];
        }

        $usesTopics = false;
        foreach ($lessons as $lessonId) {
            if ($this->stepIds($courseId, self::CPT_TOPIC, $lessonId) !== []) {
                $usesTopics = true;
                break;
            }
        }

        return $usesTopics
            ? $this->chapterPerLesson($courseId, $lessons)
            : $this->flatCourse($courseId, $lessons);
    }

    /**
     * @param array<int, int> $lessons
     * @return array<int, array<string, mixed>>
     */
    private function chapterPerLesson(int $courseId, array $lessons): array
    {
        $chapters = [];

        foreach ($lessons as $lessonId) {
            $contents = [];

            $body = (string) get_post_field('post_content', $lessonId);
            if (trim(wp_strip_all_tags($body)) !== '') {
                // The lesson's own page becomes the first item in its chapter.
                // Dropping it because the lesson became a chapter would lose
                // whatever the author wrote on it.
                $contents[] = $this->lessonPayload($lessonId, $courseId);
            }

            foreach ($this->stepIds($courseId, self::CPT_TOPIC, $lessonId) as $topicId) {
                $contents[] = $this->lessonPayload($topicId, $courseId);
                foreach ($this->quizzesFor($courseId, $topicId) as $quiz) {
                    $contents[] = $quiz;
                }
            }

            foreach ($this->quizzesFor($courseId, $lessonId) as $quiz) {
                $contents[] = $quiz;
            }

            $chapters[] = [
                'id' => $lessonId,
                'title' => (string) get_the_title($lessonId),
                'summary' => '',
                'contents' => $contents,
            ];
        }

        $courseQuizzes = $this->quizzesFor($courseId, 0);
        if ($courseQuizzes !== []) {
            $chapters[] = [
                'id' => $courseId,
                'title' => __('Course quizzes', 'sikshya'),
                'summary' => '',
                'contents' => $courseQuizzes,
            ];
        }

        return $chapters;
    }

    /**
     * @param array<int, int> $lessons
     * @return array<int, array<string, mixed>>
     */
    private function flatCourse(int $courseId, array $lessons): array
    {
        $contents = [];

        foreach ($lessons as $lessonId) {
            $contents[] = $this->lessonPayload($lessonId, $courseId);
            foreach ($this->quizzesFor($courseId, $lessonId) as $quiz) {
                $contents[] = $quiz;
            }
        }

        foreach ($this->quizzesFor($courseId, 0) as $quiz) {
            $contents[] = $quiz;
        }

        return [[
            // The course id, so a re-import maps this chapter back to the same
            // one rather than making a second.
            'id' => $courseId,
            'title' => (string) get_the_title($courseId),
            'summary' => '',
            'contents' => $contents,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonPayload(int $postId, int $courseId): array
    {
        $settings = self::settings($postId, get_post_type($postId) === self::CPT_TOPIC ? 'sfwd-topic' : 'sfwd-lessons');
        $video = trim((string) ($settings['sfwd-lessons_lesson_video_url'] ?? $settings['sfwd-topic_lesson_video_url'] ?? ''));

        return [
            'kind' => 'lesson',
            'id' => $postId,
            'title' => (string) get_the_title($postId),
            'content' => (string) get_post_field('post_content', $postId),
            'video' => ['source' => $video !== '' ? 'external' : '', 'url' => $video],
            'attachments' => [],
            'completions' => $this->readCompletions($courseId, 'lesson', $postId),
        ];
    }

    /**
     * Quizzes attached to a course, a lesson or a topic.
     *
     * @return array<int, array<string, mixed>>
     */
    private function quizzesFor(int $courseId, int $stepId): array
    {
        $out = [];

        foreach ($this->stepIds($courseId, self::CPT_QUIZ, $stepId) as $quizId) {
            $settings = self::settings($quizId, 'sfwd-quiz');
            $passing = $settings['sfwd-quiz_passingpercentage'] ?? null;

            $out[] = [
                'kind' => 'quiz',
                'id' => $quizId,
                'title' => (string) get_the_title($quizId),
                'content' => (string) get_post_field('post_content', $quizId),
                'settings' => [
                    'passing_grade' => is_numeric($passing) ? (float) $passing : null,
                    'time_limit' => isset($settings['sfwd-quiz_timeLimit']) ? (int) $settings['sfwd-quiz_timeLimit'] : 0,
                    // LearnDash states its limit in seconds.
                    'time_type' => 'seconds',
                    'attempts_allowed' => isset($settings['sfwd-quiz_repeats']) ? (int) $settings['sfwd-quiz_repeats'] : 0,
                ],
                'questions' => $this->questionsFor($quizId),
            ];
        }

        return $out;
    }

    /**
     * Steps of one type belonging to a course, optionally under one parent step.
     *
     * Read from the per-step meta rather than the course's `ld_course_steps`
     * tree, because that tree is a cache LearnDash rebuilds — on a site where
     * it has been deactivated it can be stale or missing, while the per-step
     * meta is what the editor saved.
     *
     * @return array<int, int>
     */
    private function stepIds(int $courseId, string $postType, ?int $parentStepId = null): array
    {
        global $wpdb;

        $sql = "SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'course_id' AND c.meta_value = %d";

        $params = [$courseId];

        if ($parentStepId !== null) {
            if ($parentStepId > 0) {
                $sql .= " INNER JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = 'lesson_id' AND l.meta_value = %d";
                $params[] = $parentStepId;
            } else {
                // Hung off the course itself: either no `lesson_id` at all, or
                // one explicitly set to zero.
                $sql .= " LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = 'lesson_id'";
            }
        }

        $sql .= " WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft')";
        $params[] = $postType;

        if ($parentStepId === 0) {
            $sql .= " AND (l.meta_value IS NULL OR l.meta_value = '' OR l.meta_value = '0')";
        }

        $sql .= ' ORDER BY p.menu_order ASC, p.ID ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from literals above.
        $ids = $wpdb->get_col($wpdb->prepare($sql, ...$params));

        return array_map('intval', is_array($ids) ? $ids : []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionsFor(int $quizId): array
    {
        global $wpdb;

        $proQuizId = (int) get_post_meta($quizId, 'quiz_pro_id', true);
        if ($proQuizId <= 0) {
            $settings = self::settings($quizId, 'sfwd-quiz');
            $proQuizId = (int) ($settings['sfwd-quiz_quiz_pro'] ?? 0);
        }

        $table = $wpdb->prefix . 'learndash_pro_quiz_question';
        if ($proQuizId <= 0 || !self::tableExists($table)) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix + literal.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE quiz_id = %d ORDER BY sort ASC, id ASC",
            $proQuizId
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $sourceType = (string) ($row['answer_type'] ?? '');
            $type = self::QUESTION_TYPES[$sourceType] ?? '';
            $answers = self::answerTypes((string) ($row['answer_data'] ?? ''));

            $options = [];
            $accepted = [];
            foreach ($answers as $answer) {
                $text = trim((string) ($answer['answer'] ?? ''));
                if ($text === '') {
                    continue;
                }

                if (QuestionTypes::isTextAnswer($type)) {
                    $accepted[] = $text;
                    continue;
                }

                $options[] = ['title' => $text, 'is_correct' => !empty($answer['correct'])];
            }

            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['question'] ?? ''),
                'type' => $type,
                'source_type' => $sourceType,
                'points' => isset($row['points']) ? (int) $row['points'] : 1,
                'options' => $options,
                'answers' => $accepted,
            ];
        }

        return $out;
    }

    /**
     * Read ProQuiz's serialised answer objects without instantiating anything.
     *
     * `answer_data` holds an array of `WpProQuiz_Model_AnswerTypes`. On a site
     * where LearnDash is deactivated that class does not exist, and a plain
     * `unserialize()` would be both useless and the textbook deserialisation
     * hazard — stored data naming a class to construct. `allowed_classes =>
     * false` turns each entry into an `__PHP_Incomplete_Class` whose private
     * properties can still be read once the name mangling is stripped.
     *
     * @return array<int, array{answer: string, correct: bool, points: int}>
     */
    private static function answerTypes(string $serialised): array
    {
        if (trim($serialised) === '') {
            return [];
        }

        $decoded = @unserialize($serialised, ['allowed_classes' => false]);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $entry) {
            $fields = is_object($entry) ? (array) $entry : (is_array($entry) ? $entry : []);
            if ($fields === []) {
                continue;
            }

            $read = static function (string $property) use ($fields) {
                foreach ($fields as $key => $value) {
                    // Private properties arrive as "\0ClassName\0_answer".
                    $name = is_string($key) ? substr((string) strrchr("\0" . $key, "\0"), 1) : '';
                    if ($name === $property || $key === $property) {
                        return $value;
                    }
                }

                return null;
            };

            $out[] = [
                'answer' => (string) ($read('_answer') ?? $read('answer') ?? ''),
                'correct' => (bool) ($read('_correct') ?? $read('correct') ?? false),
                'points' => (int) ($read('_points') ?? $read('points') ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Who is enrolled, from the activity table and the per-course access meta.
     *
     * Both, because they answer slightly different questions: the activity
     * table has everyone who ever started the course, and the access meta has
     * everyone who was given access — including learners who never opened it.
     *
     * @return array<int, array{id: int, user_id: int, at: string}>
     */
    private function readEnrolments(int $courseId): array
    {
        global $wpdb;

        $found = [];

        $activity = $wpdb->prefix . 'learndash_user_activity';
        if (self::tableExists($activity)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix + literal.
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT activity_id, user_id, activity_started
                 FROM {$activity}
                 WHERE course_id = %d AND activity_type = %s",
                $courseId,
                'course'
            ), ARRAY_A);

            foreach (is_array($rows) ? $rows : [] as $row) {
                $userId = (int) $row['user_id'];
                $found[$userId] = [
                    'id' => (int) $row['activity_id'],
                    'user_id' => $userId,
                    'at' => self::fromTimestamp($row['activity_started']),
                ];
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            'course_' . $courseId . '_access_from'
        ), ARRAY_A);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $userId = (int) $row['user_id'];
            if (isset($found[$userId])) {
                continue;
            }

            $found[$userId] = [
                // No activity row, so there is no source id to map against;
                // the writer falls back to its own duplicate check.
                'id' => 0,
                'user_id' => $userId,
                'at' => self::fromTimestamp($row['meta_value']),
            ];
        }

        return array_values($found);
    }

    /**
     * Completions of a course, or of one lesson/topic inside it.
     *
     * @return array<int, array{user_id: int, at: string}>
     */
    private function readCompletions(int $courseId, string $type, int $postId = 0): array
    {
        global $wpdb;

        $activity = $wpdb->prefix . 'learndash_user_activity';
        if (!self::tableExists($activity)) {
            return [];
        }

        $sql = "SELECT user_id, activity_completed FROM {$activity}
                WHERE course_id = %d AND activity_status = 1 AND activity_type IN (%s, %s)";
        $params = [$courseId, $type, $type === 'lesson' ? 'topic' : $type];

        if ($postId > 0) {
            $sql .= ' AND post_id = %d';
            $params[] = $postId;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from literals above.
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'at' => self::fromTimestamp($row['activity_completed']),
            ];
        }

        return $out;
    }

    /**
     * LearnDash's settings arrays, read straight from meta.
     *
     * @return array<string, mixed>
     */
    private static function settings(int $postId, string $key): array
    {
        $raw = get_post_meta($postId, '_' . $key, true);

        return is_array($raw) ? $raw : [];
    }

    /** @param mixed $value */
    private static function fromTimestamp($value): string
    {
        $timestamp = is_numeric($value) ? (int) $value : 0;

        return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : '';
    }

    /** @return array<int, string> */
    private static function termNames(int $postId, string $taxonomy): array
    {
        global $wpdb;

        // Read from the term tables, not `wp_get_post_terms()`: on the usual
        // migration LearnDash is deactivated, so its taxonomies are not
        // registered and the WordPress API would answer with an error.
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT t.name
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE tr.object_id = %d AND tt.taxonomy = %s
             ORDER BY t.name ASC",
            $postId,
            $taxonomy
        ));

        return array_map('strval', is_array($names) ? $names : []);
    }

    private static function tableExists(string $table): bool
    {
        global $wpdb;

        $suppress = $wpdb->suppress_errors(true);
        $previous = $wpdb->last_error;
        $wpdb->last_error = '';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $wpdb->get_var("SELECT 1 FROM {$table} LIMIT 1");

        $missing = $wpdb->last_error !== '';
        $wpdb->last_error = $previous;
        $wpdb->suppress_errors($suppress);

        return !$missing;
    }
}
