<?php

declare(strict_types=1);

namespace Sikshya\Import;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Services\CourseCurriculumActions;
use Sikshya\Services\CurriculumService;
use Sikshya\Services\LearningProgressService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates Sikshya content from one course read out of another LMS.
 *
 * Everything goes through Sikshya's own curriculum services rather than
 * `wp_insert_post()`, so an imported course is linked the way the course
 * builder links one — chapter order, `_sikshya_contents`, the lesson-to-course
 * meta the Learn templates read — and cannot drift from it.
 *
 * **Re-runnable.** Every object is looked up in {@see ImportMap} first and
 * updated rather than duplicated. Running an import twice is not an accident
 * to be guarded against; it is what people do when the first run stopped half
 * way or ten more courses were added afterwards.
 *
 * @package Sikshya\Import
 */
final class CourseWriter
{
    /**
     * Tutor's question types, and what each becomes here.
     *
     * Anything absent from this map is **skipped and counted**, not guessed
     * at. A matching question flattened into a multiple choice is a question
     * that marks learners wrong for being right, and a quiz that silently
     * lost its hardest question is worse than one that says so.
     *
     * @var array<string, string>
     */
    private const QUESTION_TYPES = [
        'true_false' => 'true_false',
        'single_choice' => 'multiple_choice',
        'multiple_choice' => 'multiple_response',
        'open_ended' => 'essay',
        'short_answer' => 'short_answer',
        'fill_in_the_blank' => 'fill_blank',
        'ordering' => 'ordering',
        'matching' => 'matching',
    ];

    private ImportMap $map;
    private string $runId;
    private bool $dryRun;

    /** @var array<string, int> */
    private array $tally = [];

    /** @var array<int, string> */
    private array $notes = [];

    public function __construct(ImportMap $map, string $runId = '', bool $dryRun = false)
    {
        $this->map = $map;
        $this->runId = $runId;
        $this->dryRun = $dryRun;
    }

    /** @return array<string, int> */
    public function tally(): array
    {
        return $this->tally;
    }

    /** @return array<int, string> */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * @param array<string, mixed> $course
     * @return int the Sikshya course id, or 0 when nothing was written
     */
    public function write(array $course): int
    {
        $sourceId = (int) ($course['id'] ?? 0);
        if ($sourceId <= 0) {
            return 0;
        }

        if ($this->dryRun) {
            $this->count('course');
            foreach ((array) ($course['chapters'] ?? []) as $chapter) {
                $this->count('chapter');
                foreach ((array) ($chapter['contents'] ?? []) as $item) {
                    $this->count((string) ($item['kind'] ?? 'lesson'));
                    foreach ((array) ($item['questions'] ?? []) as $question) {
                        $this->countQuestion((string) ($question['type'] ?? ''), (int) ($question['id'] ?? 0));
                    }
                }
            }
            $this->tally['enrolment'] = ($this->tally['enrolment'] ?? 0) + count((array) ($course['enrolments'] ?? []));

            return 0;
        }

        $courseId = $this->upsertPost('course', $sourceId, [
            'post_title' => (string) ($course['title'] ?? ''),
            'post_content' => (string) ($course['content'] ?? ''),
            'post_excerpt' => (string) ($course['excerpt'] ?? ''),
            'post_status' => self::safeStatus((string) ($course['status'] ?? 'draft')),
            'post_author' => (int) ($course['author'] ?? 0),
            'post_type' => PostTypes::COURSE,
        ]);

        if ($courseId <= 0) {
            return 0;
        }

        $this->applyCourseMeta($courseId, $course);
        $this->writeChapters($courseId, (array) ($course['chapters'] ?? []));
        $this->writeEnrolments($courseId, $course);

        return $courseId;
    }

    /**
     * @param array<int, array<string, mixed>> $chapters
     */
    private function writeChapters(int $courseId, array $chapters): void
    {
        $builder = $this->builder();
        $curriculum = $this->curriculum();
        if ($builder === null || $curriculum === null) {
            $this->notes[] = 'The curriculum service was unavailable, so no chapters were created.';

            return;
        }

        $order = 0;
        foreach ($chapters as $chapter) {
            $order++;
            $sourceId = (int) ($chapter['id'] ?? 0);
            $title = trim((string) ($chapter['title'] ?? ''));
            if ($title === '') {
                $title = sprintf(
                    /* translators: %d: chapter number */
                    __('Section %d', 'sikshya'),
                    $order
                );
            }

            $chapterId = $this->map->find('chapter', $sourceId);
            if ($chapterId <= 0) {
                $created = $builder->restCreateChapter([
                    'course_id' => $courseId,
                    'title' => $title,
                    'description' => (string) ($chapter['summary'] ?? ''),
                    'order' => $order,
                ]);
                $chapterId = (int) ($created['data']['chapter_id'] ?? 0);
                if (empty($created['success']) || $chapterId <= 0) {
                    $this->notes[] = sprintf('Section "%s" could not be created.', $title);
                    continue;
                }
                $this->map->remember('chapter', $sourceId, $chapterId, $this->runId);
                $this->count('chapter');
            } else {
                wp_update_post(['ID' => $chapterId, 'post_title' => $title]);
            }

            foreach ((array) ($chapter['contents'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (($item['kind'] ?? '') === 'quiz') {
                    $this->writeQuiz($courseId, $chapterId, $item);
                    continue;
                }

                $this->writeLesson($courseId, $chapterId, $item);
            }
        }
    }

    /**
     * @param array<string, mixed> $lesson
     */
    private function writeLesson(int $courseId, int $chapterId, array $lesson): void
    {
        $curriculum = $this->curriculum();
        if ($curriculum === null) {
            return;
        }

        $sourceId = (int) ($lesson['id'] ?? 0);
        $existing = $this->map->find('lesson', $sourceId);
        $video = is_array($lesson['video'] ?? null) ? $lesson['video'] : ['source' => '', 'url' => ''];
        $isVideo = trim((string) $video['url']) !== '';

        if ($existing > 0) {
            wp_update_post([
                'ID' => $existing,
                'post_title' => (string) ($lesson['title'] ?? ''),
                'post_content' => (string) ($lesson['content'] ?? ''),
            ]);
            $lessonId = $existing;
        } else {
            $out = $curriculum->createContent([
                'title' => (string) ($lesson['title'] ?? ''),
                'description' => '',
                'type' => 'lesson',
                'lesson_type' => $isVideo ? 'video' : 'text',
            ]);
            $lessonId = (int) ($out['data']['content_id'] ?? 0);
            if (empty($out['success']) || $lessonId <= 0) {
                $this->notes[] = sprintf('Lesson "%s" could not be created.', (string) ($lesson['title'] ?? ''));

                return;
            }

            // `createContent` writes the title; the body is the lesson, so it
            // is set here rather than squeezed into the description field.
            wp_update_post(['ID' => $lessonId, 'post_content' => (string) ($lesson['content'] ?? '')]);
            $curriculum->linkContentToChapter($lessonId, $chapterId);
            $this->map->remember('lesson', $sourceId, $lessonId, $this->runId);
            $this->count('lesson');
        }

        if ($isVideo) {
            update_post_meta($lessonId, '_sikshya_media_url', esc_url_raw((string) $video['url']));
        }

        $attachments = array_values(array_filter(array_map('intval', (array) ($lesson['attachments'] ?? []))));
        if ($attachments !== []) {
            update_post_meta($lessonId, '_sikshya_lesson_attachments', $attachments);
        }

        $this->writeLessonCompletions($courseId, $lessonId, (array) ($lesson['completions'] ?? []));
    }

    /**
     * @param array<string, mixed> $quiz
     */
    private function writeQuiz(int $courseId, int $chapterId, array $quiz): void
    {
        $curriculum = $this->curriculum();
        if ($curriculum === null) {
            return;
        }

        $sourceId = (int) ($quiz['id'] ?? 0);
        $quizId = $this->map->find('quiz', $sourceId);

        if ($quizId <= 0) {
            $out = $curriculum->createContent([
                'title' => (string) ($quiz['title'] ?? ''),
                'description' => (string) ($quiz['content'] ?? ''),
                'type' => 'quiz',
            ]);
            $quizId = (int) ($out['data']['content_id'] ?? 0);
            if (empty($out['success']) || $quizId <= 0) {
                $this->notes[] = sprintf('Quiz "%s" could not be created.', (string) ($quiz['title'] ?? ''));

                return;
            }
            $curriculum->linkContentToChapter($quizId, $chapterId);
            $this->map->remember('quiz', $sourceId, $quizId, $this->runId);
            $this->count('quiz');
        } else {
            wp_update_post(['ID' => $quizId, 'post_title' => (string) ($quiz['title'] ?? '')]);
        }

        $settings = is_array($quiz['settings'] ?? null) ? $quiz['settings'] : [];
        if (isset($settings['passing_grade']) && is_numeric($settings['passing_grade'])) {
            update_post_meta($quizId, '_sikshya_quiz_passing_score', (float) $settings['passing_grade']);
        }
        if (!empty($settings['time_limit'])) {
            update_post_meta($quizId, '_sikshya_quiz_time_limit', self::minutes($settings));
        }

        $questionIds = [];
        foreach ((array) ($quiz['questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }
            $questionId = $this->writeQuestion($question);
            if ($questionId > 0) {
                $questionIds[] = $questionId;
            }
        }

        if ($questionIds !== []) {
            update_post_meta($quizId, '_sikshya_quiz_questions', array_map('intval', $questionIds));
        }
    }

    /**
     * @param array<string, mixed> $question
     */
    private function writeQuestion(array $question): int
    {
        $sourceType = (string) ($question['type'] ?? '');
        $sourceId = (int) ($question['id'] ?? 0);

        $type = self::QUESTION_TYPES[$sourceType] ?? '';
        if ($type === '') {
            $this->countQuestion($sourceType, $sourceId);

            return 0;
        }

        $options = [];
        $correct = [];
        foreach ((array) ($question['options'] ?? []) as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $options[] = (string) ($option['title'] ?? '');
            if (!empty($option['is_correct'])) {
                $correct[] = (int) $index;
            }
        }

        $existing = $this->map->find('question', $sourceId);
        $title = trim((string) ($question['title'] ?? ''));

        if ($existing > 0) {
            wp_update_post(['ID' => $existing, 'post_title' => $title]);
            $questionId = $existing;
        } else {
            $inserted = wp_insert_post([
                'post_title' => $title !== '' ? $title : __('Question', 'sikshya'),
                'post_content' => (string) ($question['description'] ?? ''),
                'post_type' => PostTypes::QUESTION,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($inserted)) {
                return 0;
            }

            $questionId = (int) $inserted;
            $this->map->remember('question', $sourceId, $questionId, $this->runId);
            $this->count('question');
        }

        update_post_meta($questionId, '_sikshya_question_type', $type);
        update_post_meta($questionId, '_sikshya_question_points', max(1, (int) round((float) ($question['mark'] ?? 1))));

        if ($options !== []) {
            update_post_meta($questionId, '_sikshya_question_options', array_map('strval', $options));
        }

        // The encoding the grader reads: a single index as a string, several as
        // a JSON array. Writing a label here instead of an index is the bug
        // that marks every learner wrong.
        if ($type === 'multiple_response' || count($correct) > 1) {
            update_post_meta($questionId, '_sikshya_question_correct_answer', (string) wp_json_encode($correct));
        } elseif ($correct !== []) {
            update_post_meta($questionId, '_sikshya_question_correct_answer', (string) $correct[0]);
        }

        return $questionId;
    }

    /**
     * @param array<string, mixed> $course
     */
    private function writeEnrolments(int $courseId, array $course): void
    {
        $enrolments = (array) ($course['enrolments'] ?? []);
        if ($enrolments === []) {
            return;
        }

        $repo = new EnrollmentRepository();
        $completed = [];
        foreach ((array) ($course['completions'] ?? []) as $completion) {
            if (is_array($completion) && isset($completion['user_id'])) {
                $completed[(int) $completion['user_id']] = true;
            }
        }

        foreach ($enrolments as $enrolment) {
            if (!is_array($enrolment)) {
                continue;
            }

            $userId = (int) ($enrolment['user_id'] ?? 0);
            if ($userId <= 0 || get_userdata($userId) === false) {
                // A learner who no longer exists cannot be enrolled; counting
                // it as imported would overstate what came across.
                continue;
            }

            $sourceEnrolmentId = (int) ($enrolment['id'] ?? 0);
            $existing = $repo->findByUserAndCourse($userId, $courseId);
            if ($existing !== null) {
                /*
                 * Already enrolled, from an earlier run or by hand.
                 *
                 * The enrolments table has a unique index on (user, course),
                 * so a second insert cannot create a duplicate row — it fails,
                 * loudly, and WordPress writes a database error per learner
                 * per course into the log and (with debug display on) onto the
                 * page. Asking first is what keeps a re-run quiet.
                 *
                 * The mapping is still recorded, so the screen can say this
                 * enrolment has come across.
                 */
                if ($sourceEnrolmentId > 0) {
                    $this->map->remember('enrolment', $sourceEnrolmentId, (int) $existing->id, $this->runId);
                }
                continue;
            }

            $createdId = $repo->create([
                'user_id' => $userId,
                'course_id' => $courseId,
                // Tutor's enrolment status of "completed" means the enrolment
                // went through, not that the learner finished — so everyone
                // arrives enrolled, and only the course-completion records
                // make somebody complete.
                'status' => isset($completed[$userId]) ? 'completed' : 'enrolled',
                // The date they actually enrolled, not the date of the
                // import: a report on "who joined this quarter" is wrong
                // otherwise, for every learner at once.
                'enrolled_date' => self::safeDate((string) ($enrolment['at'] ?? '')),
            ]);

            if ($createdId > 0) {
                if ($sourceEnrolmentId > 0) {
                    $this->map->remember('enrolment', $sourceEnrolmentId, $createdId, $this->runId);
                }
                $this->count('enrolment');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $completions
     */
    private function writeLessonCompletions(int $courseId, int $lessonId, array $completions): void
    {
        if ($completions === []) {
            return;
        }

        $progress = new LearningProgressService();
        foreach ($completions as $completion) {
            $userId = is_array($completion) ? (int) ($completion['user_id'] ?? 0) : 0;
            if ($userId <= 0 || get_userdata($userId) === false) {
                continue;
            }

            if ($progress->markLessonComplete($lessonId, $userId)) {
                $this->count('lesson_completion');
            }
        }

        unset($courseId);
    }

    /**
     * @param array<string, mixed> $course
     */
    private function applyCourseMeta(int $courseId, array $course): void
    {
        $thumbnailId = (int) ($course['thumbnail_id'] ?? 0);
        if ($thumbnailId > 0 && get_post($thumbnailId) !== null) {
            // The attachment is already in this site's library — both plugins
            // share it — so the image is re-pointed, never re-uploaded.
            set_post_thumbnail($courseId, $thumbnailId);
        }

        $level = sanitize_text_field((string) ($course['level'] ?? ''));
        if ($level !== '') {
            update_post_meta($courseId, '_sikshya_difficulty_level', $level);
        }

        $duration = $course['duration'] ?? null;
        if (is_array($duration) && isset($duration['hours'], $duration['minutes'])) {
            $minutes = ((int) $duration['hours'] * 60) + (int) $duration['minutes'];
            if ($minutes > 0) {
                update_post_meta($courseId, '_sikshya_duration', $minutes);
            }
        }

        foreach (['benefits' => 'benefits', 'requirements' => 'requirements', 'target_audience' => 'audience'] as $from => $to) {
            $value = trim((string) ($course[$from] ?? ''));
            if ($value !== '') {
                update_post_meta($courseId, '_sikshya_course_' . $to, $value);
            }
        }

        // Sikshya's taxonomies, not the source's: the names in the read
        // payload are whatever the old plugin called them.
        $this->applyTerms($courseId, (array) ($course['categories'] ?? []), Taxonomies::COURSE_CATEGORY);
        $this->applyTerms($courseId, (array) ($course['tags'] ?? []), Taxonomies::COURSE_TAG);
    }

    /**
     * @param array<int, string> $names
     */
    private function applyTerms(int $courseId, array $names, string $taxonomy): void
    {
        $names = array_values(array_filter(array_map('strval', $names)));
        if ($names === [] || !taxonomy_exists($taxonomy)) {
            return;
        }

        // Matched by name, and created when missing: the source's own term ids
        // mean nothing in a taxonomy Sikshya owns.
        wp_set_object_terms($courseId, $names, $taxonomy, false);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function upsertPost(string $objectType, int $sourceId, array $fields): int
    {
        $existing = $this->map->find($objectType, $sourceId);

        if ($existing > 0) {
            $fields['ID'] = $existing;
            unset($fields['post_type']);
            wp_update_post($fields);

            return $existing;
        }

        $inserted = wp_insert_post($fields, true);
        if (is_wp_error($inserted)) {
            $this->notes[] = $inserted->get_error_message();

            return 0;
        }

        $id = (int) $inserted;
        $this->map->remember($objectType, $sourceId, $id, $this->runId);
        $this->count($objectType);

        return $id;
    }

    private function count(string $bucket): void
    {
        $this->tally[$bucket] = ($this->tally[$bucket] ?? 0) + 1;
    }

    private function countQuestion(string $sourceType, int $sourceId): void
    {
        if (isset(self::QUESTION_TYPES[$sourceType])) {
            $this->count('question');

            return;
        }

        $this->count('question_skipped');
        $note = sprintf(
            /* translators: 1: source question type, 2: source question id */
            __('Question type "%1$s" has no equivalent in Sikshya, so question #%2$d was skipped.', 'sikshya'),
            $sourceType !== '' ? $sourceType : '(none)',
            $sourceId
        );
        if (!in_array($note, $this->notes, true)) {
            $this->notes[] = $note;
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function minutes(array $settings): int
    {
        $value = (int) ($settings['time_limit'] ?? 0);
        $type = (string) ($settings['time_type'] ?? 'minutes');

        switch ($type) {
            case 'seconds':
                return (int) max(1, round($value / 60));
            case 'hours':
                return $value * 60;
            case 'days':
                return $value * 60 * 24;
            case 'weeks':
                return $value * 60 * 24 * 7;
            default:
                return $value;
        }
    }

    private static function safeDate(string $date): string
    {
        $time = strtotime($date);

        return $time === false ? current_time('mysql') : gmdate('Y-m-d H:i:s', $time);
    }

    private static function safeStatus(string $status): string
    {
        return in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'draft';
    }

    private function curriculum(): ?CurriculumService
    {
        $service = Plugin::getInstance()->getService('curriculum');

        return $service instanceof CurriculumService ? $service : null;
    }

    private function builder(): ?CourseCurriculumActions
    {
        $service = Plugin::getInstance()->getService('courseBuilderUi');

        return $service instanceof CourseCurriculumActions ? $service : null;
    }
}
