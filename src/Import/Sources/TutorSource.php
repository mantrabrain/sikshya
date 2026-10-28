<?php

declare(strict_types=1);

namespace Sikshya\Import\Sources;

use Sikshya\Import\ImportSourceInterface;
use Sikshya\Import\QuestionTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads a Tutor LMS installation.
 *
 * Tutor's model maps onto Sikshya's almost one to one, which is why it is the
 * first source: `courses` → course, `topics` → chapter, `lesson` → lesson,
 * `tutor_quiz` → quiz. Only two things need care.
 *
 * **Quiz questions live in Tutor's own tables**, not in posts, so they are read
 * with SQL rather than `get_posts()`. **And an enrolment in Tutor is a post of
 * type `tutor_enrolled` whose status is `completed`** — "completed" there means
 * the *enrolment* went through, not that the learner finished the course. An
 * importer that read that as course completion would mark every student of
 * every course as having finished it.
 *
 * Nothing here writes. Tutor's data is left untouched, so a site that does not
 * like the result can switch back.
 *
 * @package Sikshya\Import\Sources
 */
final class TutorSource implements ImportSourceInterface
{
    public const ID = 'tutor';

    public const CPT_COURSE = 'courses';
    public const CPT_TOPIC = 'topics';
    public const CPT_LESSON = 'lesson';
    public const CPT_QUIZ = 'tutor_quiz';
    public const CPT_ENROLMENT = 'tutor_enrolled';

    /**
     * Tutor's question types, and what each becomes in Sikshya.
     *
     * Five of Tutor's eleven are absent on purpose. `image_matching`,
     * `image_answering` and `h5p` have no equivalent at all; `matching` and
     * `ordering` do, but a source stores their options already in the correct
     * order, so importing them hands the learner the answer. Each absent type
     * is named in the import report instead of being turned into the nearest
     * thing — a question converted into something it is not marks learners
     * wrong for being right.
     *
     * @var array<string, string>
     */
    private const QUESTION_TYPES = [
        'true_false' => QuestionTypes::TRUE_FALSE,
        'single_choice' => QuestionTypes::SINGLE,
        'multiple_choice' => QuestionTypes::MULTIPLE,
        'short_answer' => QuestionTypes::SHORT_ANSWER,
        'fill_in_the_blank' => QuestionTypes::FILL_BLANK,
        'open_ended' => QuestionTypes::ESSAY,
    ];

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'Tutor LMS';
    }

    public function detect(): bool
    {
        global $wpdb;

        // Data, not the plugin: the usual migration starts after the old
        // plugin has already been switched off.
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
            self::CPT_COURSE
        ));

        return $found > 0;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        global $wpdb;

        $countPosts = function (string $type): int {
            global $wpdb;

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')",
                $type
            ));
        };

        $questions = 0;
        $table = $wpdb->prefix . 'tutor_quiz_questions';
        if (self::tableExists($table)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a prefix + literal.
            $questions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return [
            'course' => $countPosts(self::CPT_COURSE),
            'chapter' => $countPosts(self::CPT_TOPIC),
            'lesson' => $countPosts(self::CPT_LESSON),
            'quiz' => $countPosts(self::CPT_QUIZ),
            'question' => $questions,
            'enrolment' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                self::CPT_ENROLMENT,
                'completed'
            )),
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

    /**
     * @return array<string, mixed>|null
     */
    public function readCourse(int $courseId): ?array
    {
        $course = get_post($courseId);
        if ($course === null || $course->post_type !== self::CPT_COURSE) {
            return null;
        }

        return [
            'id' => (int) $course->ID,
            'title' => (string) $course->post_title,
            'content' => (string) $course->post_content,
            'excerpt' => (string) $course->post_excerpt,
            'status' => (string) $course->post_status,
            'author' => (int) $course->post_author,
            'date' => (string) $course->post_date,
            'slug' => (string) $course->post_name,
            'thumbnail_id' => (int) get_post_thumbnail_id($courseId),
            'categories' => self::termNames($courseId, 'course-category'),
            'tags' => self::termNames($courseId, 'course-tag'),
            'level' => (string) get_post_meta($courseId, '_tutor_course_level', true),
            'duration' => get_post_meta($courseId, '_tutor_course_duration', true),
            'price_type' => (string) get_post_meta($courseId, '_tutor_course_price_type', true),
            'benefits' => (string) get_post_meta($courseId, '_tutor_course_benefits', true),
            'requirements' => (string) get_post_meta($courseId, '_tutor_course_requirements', true),
            'target_audience' => (string) get_post_meta($courseId, '_tutor_course_target_audience', true),
            'chapters' => $this->readTopics($courseId),
            'enrolments' => $this->readEnrolments($courseId),
            'completions' => $this->readCourseCompletions($courseId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readTopics(int $courseId): array
    {
        $topics = get_posts([
            'post_type' => self::CPT_TOPIC,
            'post_parent' => $courseId,
            'posts_per_page' => 200,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'suppress_filters' => true,
        ]);

        $out = [];
        foreach ($topics as $topic) {
            $out[] = [
                'id' => (int) $topic->ID,
                'title' => (string) $topic->post_title,
                'summary' => (string) $topic->post_content,
                'contents' => $this->readTopicContents((int) $topic->ID),
            ];
        }

        return $out;
    }

    /**
     * Lessons and quizzes inside a topic, in the author's order.
     *
     * Both post types are children of the topic and both use `menu_order`, so
     * they are read together and sorted once — a lesson between two quizzes
     * has to stay between them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readTopicContents(int $topicId): array
    {
        $children = get_posts([
            'post_type' => [self::CPT_LESSON, self::CPT_QUIZ],
            'post_parent' => $topicId,
            'posts_per_page' => 500,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'suppress_filters' => true,
        ]);

        $out = [];
        foreach ($children as $child) {
            if ($child->post_type === self::CPT_LESSON) {
                $out[] = [
                    'kind' => 'lesson',
                    'id' => (int) $child->ID,
                    'title' => (string) $child->post_title,
                    'content' => (string) $child->post_content,
                    'video' => self::lessonVideoUrl((int) $child->ID),
                    'attachments' => self::lessonAttachmentIds((int) $child->ID),
                    'completions' => self::lessonCompletions((int) $child->ID),
                ];
                continue;
            }

            $out[] = [
                'kind' => 'quiz',
                'id' => (int) $child->ID,
                'title' => (string) $child->post_title,
                'content' => (string) $child->post_content,
                'settings' => self::quizSettings((int) $child->ID),
                'questions' => self::quizQuestions((int) $child->ID),
            ];
        }

        return $out;
    }

    /**
     * Tutor stores a lesson video as a serialised array of source + url.
     *
     * @return array{source: string, url: string}
     */
    private static function lessonVideoUrl(int $lessonId): array
    {
        $video = get_post_meta($lessonId, '_video', true);
        if (!is_array($video)) {
            return ['source' => '', 'url' => ''];
        }

        $source = isset($video['source']) ? (string) $video['source'] : '';
        $key = 'source_' . $source;
        $url = isset($video[$key]) ? (string) $video[$key] : (string) ($video['source_external_url'] ?? '');

        return ['source' => $source, 'url' => $url];
    }

    /** @return array<int, int> */
    private static function lessonAttachmentIds(int $lessonId): array
    {
        $raw = get_post_meta($lessonId, '_tutor_attachments', true);

        return is_array($raw) ? array_values(array_filter(array_map('intval', $raw))) : [];
    }

    /**
     * Which learners completed a lesson, and when.
     *
     * Tutor writes one user-meta row per completed lesson, named with the
     * lesson id, so this is a meta_key lookup rather than a value lookup.
     *
     * @return array<int, array{user_id: int, at: string}>
     */
    private static function lessonCompletions(int $lessonId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            '_tutor_completed_lesson_id_' . $lessonId
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'at' => (string) $row['meta_value'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function quizSettings(int $quizId): array
    {
        $raw = get_post_meta($quizId, 'tutor_quiz_option', true);
        $raw = is_array($raw) ? $raw : [];

        return [
            'passing_grade' => isset($raw['passing_grade']) ? (float) $raw['passing_grade'] : null,
            'time_limit' => isset($raw['time_limit']['time_value']) ? (int) $raw['time_limit']['time_value'] : 0,
            'time_type' => isset($raw['time_limit']['time_type']) ? (string) $raw['time_limit']['time_type'] : '',
            'attempts_allowed' => isset($raw['attempts_allowed']) ? (int) $raw['attempts_allowed'] : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function quizQuestions(int $quizId): array
    {
        global $wpdb;

        $questions = $wpdb->prefix . 'tutor_quiz_questions';
        $answers = $wpdb->prefix . 'tutor_quiz_question_answers';

        if (!self::tableExists($questions)) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a prefix + literal.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$questions} WHERE quiz_id = %d ORDER BY question_order ASC, question_id ASC",
            $quizId
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $questionId = (int) $row['question_id'];
            $options = [];

            if (self::tableExists($answers)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a prefix + literal.
                $answerRows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$answers} WHERE belongs_question_id = %d ORDER BY answer_order ASC, answer_id ASC",
                    $questionId
                ), ARRAY_A);

                foreach (is_array($answerRows) ? $answerRows : [] as $answer) {
                    $options[] = [
                        'title' => (string) $answer['answer_title'],
                        'is_correct' => (string) $answer['is_correct'] === '1',
                    ];
                }
            }

            $sourceType = (string) $row['question_type'];
            $type = self::QUESTION_TYPES[$sourceType] ?? '';

            $out[] = [
                'id' => $questionId,
                'title' => (string) $row['question_title'],
                'description' => (string) ($row['question_description'] ?? ''),
                // Translated here, not in the writer: each source speaks its
                // own dialect, and the writer only speaks Sikshya's.
                'type' => $type,
                'source_type' => $sourceType,
                'points' => isset($row['question_mark']) ? (int) round((float) $row['question_mark']) : 1,
                'options' => QuestionTypes::isTextAnswer($type) ? [] : $options,
                // For a short answer or a fill-in-the-blank, Tutor keeps every
                // accepted wording as an answer row.
                'answers' => QuestionTypes::isTextAnswer($type)
                    ? array_map(static fn($o) => (string) $o['title'], $options)
                    : [],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{id: int, user_id: int, at: string}>
     */
    private function readEnrolments(int $courseId): array
    {
        global $wpdb;

        // `post_status = 'completed'` is Tutor's way of saying the enrolment
        // itself went through. It says nothing about the learner finishing.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_author, post_date FROM {$wpdb->posts}
             WHERE post_type = %s AND post_parent = %d AND post_status = %s",
            self::CPT_ENROLMENT,
            $courseId,
            'completed'
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                // The enrolment's own id travels with it so the import can be
                // recorded against it, which is what lets the screen say how
                // many have already come across.
                'id' => (int) $row['ID'],
                'user_id' => (int) $row['post_author'],
                'at' => (string) $row['post_date'],
            ];
        }

        return $out;
    }

    /**
     * Who actually finished the course.
     *
     * Tutor records this as a comment of type `course_completed`, which is a
     * different thing entirely from the enrolment status above.
     *
     * @return array<int, array{user_id: int, at: string}>
     */
    private function readCourseCompletions(int $courseId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, comment_date FROM {$wpdb->comments}
             WHERE comment_post_ID = %d AND comment_type = %s AND comment_approved = %s",
            $courseId,
            'course_completed',
            'approved'
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = ['user_id' => (int) $row['user_id'], 'at' => (string) $row['comment_date']];
        }

        return $out;
    }

    /**
     * Term names, read without needing the source's taxonomy to be registered.
     *
     * `wp_get_post_terms()` returns an error for a taxonomy WordPress does not
     * know about, and on the usual migration the old plugin is already
     * deactivated — so its taxonomies are not registered and every course
     * would arrive with no categories at all. This went unnoticed in tests,
     * which register them for convenience, and showed up the first time the
     * importer ran against a site where they were not.
     *
     * @return array<int, string>
     */
    private static function termNames(int $postId, string $taxonomy): array
    {
        global $wpdb;

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

    /**
     * Whether a source table can actually be read.
     *
     * Asked by reading from it rather than with `SHOW TABLES LIKE`, which
     * answers a subtly different question: it lists tables the *server* knows
     * about, and so reports "missing" for a temporary table that queries fine.
     * That is how this check first went wrong — against a test fixture, but
     * the same is true of any table the connection can read and the catalogue
     * does not list.
     */
    private static function tableExists(string $table): bool
    {
        global $wpdb;

        $suppress = $wpdb->suppress_errors(true);
        $hadError = $wpdb->last_error;
        $wpdb->last_error = '';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $wpdb->get_var("SELECT 1 FROM {$table} LIMIT 1");

        $missing = $wpdb->last_error !== '';
        $wpdb->last_error = $hadError;
        $wpdb->suppress_errors($suppress);

        return !$missing;
    }
}
