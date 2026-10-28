<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Import\ImportMap;
use Sikshya\Import\ImportMapTable;
use Sikshya\Import\ImportRunner;
use Sikshya\Import\Sources\LearnDashSource;
use WP_UnitTestCase;

/**
 * Moving a LearnDash site into Sikshya.
 *
 * LearnDash is three levels deep where Sikshya is two, and its quiz answers
 * are serialised PHP objects. Both are where an importer gets this wrong, so
 * both are what these tests are about:
 *
 *  - a course that uses topics becomes a chapter per lesson; a flat course
 *    stays flat, because twenty chapters of one lesson each is faithful and
 *    unreadable;
 *  - answers are read without instantiating anything, which has to keep
 *    working on a site where LearnDash is deactivated and its classes do not
 *    exist — which is the usual case.
 *
 * @covers \Sikshya\Import\Sources\LearnDashSource
 *
 * @group import
 */
final class LearnDashImportIntegrationTest extends WP_UnitTestCase
{
    private int $course = 0;
    private int $lessonWithTopics = 0;
    private int $topic = 0;
    private int $quiz = 0;
    private int $proQuizId = 77;
    private int $learner = 0;
    private int $finisher = 0;

    public function setUp(): void
    {
        parent::setUp();

        ImportMapTable::ensure();
        $this->registerPostTypes();
        $this->createTables();
        ImportRunner::resetProgress(LearnDashSource::ID);

        $this->learner = self::factory()->user->create(['role' => 'subscriber']);
        $this->finisher = self::factory()->user->create(['role' => 'subscriber']);
    }

    public function tearDown(): void
    {
        global $wpdb;

        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'learndash_pro_quiz_question');
        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'learndash_user_activity');
        ImportRunner::resetProgress(LearnDashSource::ID);

        parent::tearDown();
    }

    private function registerPostTypes(): void
    {
        foreach ([
            LearnDashSource::CPT_COURSE,
            LearnDashSource::CPT_LESSON,
            LearnDashSource::CPT_TOPIC,
            LearnDashSource::CPT_QUIZ,
        ] as $type) {
            register_post_type($type, ['public' => false]);
        }
    }

    private function createTables(): void
    {
        global $wpdb;

        $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'learndash_pro_quiz_question (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            sort int(11) NOT NULL DEFAULT 0,
            title text NULL,
            question longtext NULL,
            points int(11) NOT NULL DEFAULT 1,
            answer_type varchar(50) NULL,
            answer_data longtext NULL,
            PRIMARY KEY (id)
        )');

        $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'learndash_user_activity (
            activity_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL DEFAULT 0,
            course_id bigint(20) NOT NULL DEFAULT 0,
            activity_type varchar(50) NULL,
            activity_status tinyint(1) NOT NULL DEFAULT 0,
            activity_started bigint(20) NOT NULL DEFAULT 0,
            activity_completed bigint(20) NOT NULL DEFAULT 0,
            PRIMARY KEY (activity_id)
        )');
    }

    /**
     * ProQuiz stores answers as serialised `WpProQuiz_Model_AnswerTypes`
     * objects. This builds that exact payload *without* defining the class, so
     * the fixture is what a site with LearnDash deactivated actually holds.
     *
     * @param array<int, array{text: string, correct: bool}> $answers
     */
    private function serialisedAnswers(array $answers): string
    {
        $class = 'WpProQuiz_Model_AnswerTypes';
        $parts = '';

        foreach ($answers as $index => $answer) {
            $props = [
                '_answer' => (string) $answer['text'],
                '_correct' => (bool) $answer['correct'],
                '_points' => 1,
            ];

            $serialisedProps = '';
            foreach ($props as $name => $value) {
                // Private property names are mangled with the class name.
                $mangled = "\0" . $class . "\0" . $name;
                $serialisedProps .= 's:' . strlen($mangled) . ':"' . $mangled . '";';
                if (is_bool($value)) {
                    $serialisedProps .= 'b:' . ($value ? '1' : '0') . ';';
                } elseif (is_int($value)) {
                    $serialisedProps .= 'i:' . $value . ';';
                } else {
                    $serialisedProps .= 's:' . strlen((string) $value) . ':"' . $value . '";';
                }
            }

            $object = 'O:' . strlen($class) . ':"' . $class . '":' . count($props) . ':{' . $serialisedProps . '}';
            $parts .= 'i:' . $index . ';' . $object;
        }

        return 'a:' . count($answers) . ':{' . $parts . '}';
    }

    private function seedCourseWithTopics(): void
    {
        global $wpdb;

        $this->course = self::factory()->post->create([
            'post_type' => LearnDashSource::CPT_COURSE,
            'post_status' => 'publish',
            'post_title' => 'Safeguarding (LearnDash)',
            'post_content' => '<p>Course body.</p>',
        ]);

        $this->lessonWithTopics = self::factory()->post->create([
            'post_type' => LearnDashSource::CPT_LESSON,
            'post_status' => 'publish',
            'post_title' => 'Module one',
            'post_content' => '<p>Module intro.</p>',
            'menu_order' => 0,
        ]);
        update_post_meta($this->lessonWithTopics, 'course_id', $this->course);

        $this->topic = self::factory()->post->create([
            'post_type' => LearnDashSource::CPT_TOPIC,
            'post_status' => 'publish',
            'post_title' => 'Spotting the signs',
            'post_content' => '<p>Topic body.</p>',
            'menu_order' => 0,
        ]);
        update_post_meta($this->topic, 'course_id', $this->course);
        update_post_meta($this->topic, 'lesson_id', $this->lessonWithTopics);
        update_post_meta($this->topic, '_sfwd-topic', ['sfwd-topic_lesson_video_url' => 'https://example.test/topic.mp4']);

        $this->quiz = self::factory()->post->create([
            'post_type' => LearnDashSource::CPT_QUIZ,
            'post_status' => 'publish',
            'post_title' => 'Module one quiz',
            'menu_order' => 1,
        ]);
        update_post_meta($this->quiz, 'course_id', $this->course);
        update_post_meta($this->quiz, 'lesson_id', $this->lessonWithTopics);
        update_post_meta($this->quiz, 'quiz_pro_id', $this->proQuizId);
        update_post_meta($this->quiz, '_sfwd-quiz', [
            'sfwd-quiz_passingpercentage' => 80,
            'sfwd-quiz_timeLimit' => 600,
        ]);

        $questions = $wpdb->prefix . 'learndash_pro_quiz_question';

        $wpdb->insert($questions, [
            'quiz_id' => $this->proQuizId,
            'sort' => 1,
            'title' => 'Who do you tell?',
            'question' => '<p>Pick one.</p>',
            'points' => 3,
            'answer_type' => 'single',
            'answer_data' => $this->serialisedAnswers([
                ['text' => 'Nobody', 'correct' => false],
                ['text' => 'The safeguarding lead', 'correct' => true],
            ]),
        ]);

        $wpdb->insert($questions, [
            'quiz_id' => $this->proQuizId,
            'sort' => 2,
            'title' => 'Name the policy',
            'points' => 1,
            'answer_type' => 'free_answer',
            'answer_data' => $this->serialisedAnswers([
                ['text' => 'Safeguarding policy', 'correct' => true],
                ['text' => 'The safeguarding policy', 'correct' => true],
            ]),
        ]);

        $wpdb->insert($questions, [
            'quiz_id' => $this->proQuizId,
            'sort' => 3,
            'title' => 'Sort these steps',
            'points' => 1,
            'answer_type' => 'sort_answer',
            'answer_data' => $this->serialisedAnswers([
                ['text' => 'First', 'correct' => true],
                ['text' => 'Second', 'correct' => true],
            ]),
        ]);

        $activity = $wpdb->prefix . 'learndash_user_activity';
        $wpdb->insert($activity, [
            'user_id' => $this->learner,
            'post_id' => $this->course,
            'course_id' => $this->course,
            'activity_type' => 'course',
            'activity_status' => 0,
            'activity_started' => strtotime('2026-04-01 09:00:00'),
        ]);
        $wpdb->insert($activity, [
            'user_id' => $this->finisher,
            'post_id' => $this->course,
            'course_id' => $this->course,
            'activity_type' => 'course',
            'activity_status' => 1,
            'activity_started' => strtotime('2026-04-02 09:00:00'),
            'activity_completed' => strtotime('2026-05-02 09:00:00'),
        ]);
        $wpdb->insert($activity, [
            'user_id' => $this->finisher,
            'post_id' => $this->topic,
            'course_id' => $this->course,
            'activity_type' => 'topic',
            'activity_status' => 1,
            'activity_started' => strtotime('2026-04-02 10:00:00'),
            'activity_completed' => strtotime('2026-04-02 11:00:00'),
        ]);
    }

    private function seedFlatCourse(): int
    {
        $course = self::factory()->post->create([
            'post_type' => LearnDashSource::CPT_COURSE,
            'post_status' => 'publish',
            'post_title' => 'Fire Marshal (LearnDash)',
        ]);

        foreach (['Alarms', 'Evacuation', 'Debrief'] as $i => $title) {
            $lesson = self::factory()->post->create([
                'post_type' => LearnDashSource::CPT_LESSON,
                'post_status' => 'publish',
                'post_title' => $title,
                'post_content' => '<p>' . $title . '</p>',
                'menu_order' => $i,
            ]);
            update_post_meta($lesson, 'course_id', $course);
        }

        return $course;
    }

    // ----- detection ---------------------------------------------------------

    public function testItDetectsLearnDashDataAndCountsIt(): void
    {
        $this->seedCourseWithTopics();
        $source = new LearnDashSource();

        self::assertTrue($source->detect());

        $counts = $source->counts();
        self::assertSame(1, $counts['course']);
        self::assertSame(2, $counts['lesson'], 'Lessons and topics both become lessons here.');
        self::assertSame(1, $counts['quiz']);
        self::assertSame(3, $counts['question']);
        self::assertSame(2, $counts['enrolment']);
    }

    public function testItIsOfferedAlongsideTutor(): void
    {
        $ids = array_column(ImportRunner::available(), 'id');

        self::assertContains(LearnDashSource::ID, $ids);
        self::assertContains('tutor', $ids, 'Adding a source must not remove one.');
    }

    // ----- structure ---------------------------------------------------------

    public function testACourseWithTopicsBecomesAChapterPerLesson(): void
    {
        $this->seedCourseWithTopics();

        $result = ImportRunner::runBatch(LearnDashSource::ID);
        self::assertTrue($result['ok'], (string) ($result['message'] ?? ''));

        $map = new ImportMap(LearnDashSource::ID);
        $courseId = $map->find('course', $this->course);
        self::assertGreaterThan(0, $courseId);

        $chapterId = $map->find('chapter', $this->lessonWithTopics);
        self::assertGreaterThan(0, $chapterId, 'A LearnDash lesson with topics is a chapter.');
        self::assertSame('Module one', get_post($chapterId)->post_title);

        // The lesson's own page is not lost just because the lesson became a
        // chapter.
        $ownPage = $map->find('lesson', $this->lessonWithTopics);
        self::assertGreaterThan(0, $ownPage);
        self::assertStringContainsString('Module intro.', (string) get_post($ownPage)->post_content);

        $topicLesson = $map->find('lesson', $this->topic);
        self::assertGreaterThan(0, $topicLesson);
        self::assertSame('Spotting the signs', get_post($topicLesson)->post_title);
        self::assertSame(
            'video',
            (string) get_post_meta($topicLesson, '_sikshya_lesson_type', true),
            "LearnDash's own video URL field makes this a video lesson."
        );
    }

    public function testAFlatCourseStaysFlat(): void
    {
        $flat = $this->seedFlatCourse();

        ImportRunner::runBatch(LearnDashSource::ID);

        $map = new ImportMap(LearnDashSource::ID);
        $courseId = $map->find('course', $flat);
        self::assertGreaterThan(0, $courseId);

        $chapters = get_posts([
            'post_type' => PostTypes::CHAPTER,
            'post_parent' => $courseId,
            'numberposts' => 20,
            'post_status' => 'any',
        ]);

        self::assertCount(
            1,
            $chapters,
            'Twenty chapters of one lesson each is faithful to LearnDash and useless to read.'
        );

        $lessons = 0;
        foreach (['Alarms', 'Evacuation', 'Debrief'] as $title) {
            $found = get_posts([
                'post_type' => PostTypes::LESSON,
                'title' => $title,
                'numberposts' => 1,
                'post_status' => 'any',
            ]);
            $lessons += count($found);
        }
        self::assertSame(3, $lessons);
    }

    // ----- quizzes -----------------------------------------------------------

    public function testQuizSettingsAndQuestionsArriveFromTheProQuizTables(): void
    {
        $this->seedCourseWithTopics();

        $result = ImportRunner::runBatch(LearnDashSource::ID);

        $map = new ImportMap(LearnDashSource::ID);
        $quizId = $map->find('quiz', $this->quiz);
        self::assertGreaterThan(0, $quizId);
        self::assertSame('80', (string) get_post_meta($quizId, '_sikshya_quiz_passing_score', true));
        self::assertSame(
            '10',
            (string) get_post_meta($quizId, '_sikshya_quiz_time_limit', true),
            'LearnDash states its limit in seconds; six hundred of them is ten minutes.'
        );

        $questionIds = (array) get_post_meta($quizId, '_sikshya_quiz_questions', true);
        self::assertCount(2, $questionIds, 'The sortable question has no honest equivalent and is skipped.');

        $single = get_posts([
            'post_type' => PostTypes::QUESTION,
            'title' => 'Who do you tell?',
            'numberposts' => 1,
            'post_status' => 'any',
        ]);
        self::assertCount(1, $single);
        $singleId = (int) $single[0]->ID;

        self::assertSame('multiple_choice', get_post_meta($singleId, '_sikshya_question_type', true));
        self::assertSame(
            ['Nobody', 'The safeguarding lead'],
            get_post_meta($singleId, '_sikshya_question_options', true),
            'Read out of serialised objects without ever instantiating one.'
        );
        self::assertSame('1', (string) get_post_meta($singleId, '_sikshya_question_correct_answer', true));
        self::assertSame('3', (string) get_post_meta($singleId, '_sikshya_question_points', true));

        $free = get_posts([
            'post_type' => PostTypes::QUESTION,
            'title' => 'Name the policy',
            'numberposts' => 1,
            'post_status' => 'any',
        ]);
        self::assertCount(1, $free);
        self::assertSame(
            'Safeguarding policy|The safeguarding policy',
            (string) get_post_meta((int) $free[0]->ID, '_sikshya_question_correct_answer', true)
        );

        self::assertStringContainsString('sort_answer', implode(' ', $result['notes']));
    }

    public function testAnswersAreReadWithoutInstantiatingTheirClass(): void
    {
        // The fixture never defines `WpProQuiz_Model_AnswerTypes`, which is
        // the point: on a site with LearnDash deactivated the class is gone,
        // and letting stored data name a class to construct is how
        // deserialisation becomes code execution.
        self::assertFalse(
            class_exists('WpProQuiz_Model_AnswerTypes'),
            'If this class exists the test is no longer proving anything.'
        );

        $this->seedCourseWithTopics();
        ImportRunner::runBatch(LearnDashSource::ID);

        $single = get_posts([
            'post_type' => PostTypes::QUESTION,
            'title' => 'Who do you tell?',
            'numberposts' => 1,
            'post_status' => 'any',
        ]);

        self::assertCount(1, $single);
        self::assertSame(
            ['Nobody', 'The safeguarding lead'],
            get_post_meta((int) $single[0]->ID, '_sikshya_question_options', true)
        );
    }

    // ----- people ------------------------------------------------------------

    public function testEnrolmentsAndCompletionsComeFromTheActivityTable(): void
    {
        $this->seedCourseWithTopics();

        ImportRunner::runBatch(LearnDashSource::ID);

        $map = new ImportMap(LearnDashSource::ID);
        $courseId = $map->find('course', $this->course);
        $repo = new EnrollmentRepository();

        $started = $repo->findByUserAndCourse($this->learner, $courseId);
        $finished = $repo->findByUserAndCourse($this->finisher, $courseId);

        self::assertNotNull($started);
        self::assertNotNull($finished);
        self::assertSame('enrolled', (string) $started->status);
        self::assertSame('completed', (string) $finished->status);
        self::assertStringStartsWith('2026-04-01', (string) $started->enrolled_date);

        global $wpdb;
        $topicLesson = $map->find('lesson', $this->topic);
        $done = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sikshya_progress WHERE user_id = %d AND lesson_id = %d AND status = %s',
            $this->finisher,
            $topicLesson,
            'completed'
        ));

        self::assertSame(1, $done, 'A topic they had finished should not have to be done again.');
    }

    public function testAccessGrantedWithoutAnyActivityIsStillAnEnrolment(): void
    {
        $this->seedCourseWithTopics();

        $invited = self::factory()->user->create(['role' => 'subscriber']);
        update_user_meta($invited, 'course_' . $this->course . '_access_from', strtotime('2026-06-01 09:00:00'));

        ImportRunner::runBatch(LearnDashSource::ID);

        $courseId = (new ImportMap(LearnDashSource::ID))->find('course', $this->course);
        $row = (new EnrollmentRepository())->findByUserAndCourse($invited, $courseId);

        self::assertNotNull(
            $row,
            'Somebody given access who never opened the course is still enrolled, and would otherwise be dropped.'
        );
        self::assertStringStartsWith('2026-06-01', (string) $row->enrolled_date);
    }

    // ----- the properties that matter ---------------------------------------

    public function testRunningItTwiceDoesNotDuplicateAnything(): void
    {
        $this->seedCourseWithTopics();

        ImportRunner::runBatch(LearnDashSource::ID);
        $after = self::countSikshyaPosts();

        ImportRunner::resetProgress(LearnDashSource::ID);
        ob_start();
        ImportRunner::runBatch(LearnDashSource::ID);
        $printed = (string) ob_get_clean();

        self::assertSame($after, self::countSikshyaPosts());
        self::assertStringNotContainsString('database error', strtolower($printed));
    }

    public function testTheSourceIsLeftExactlyAsItWas(): void
    {
        $this->seedCourseWithTopics();
        $before = $this->fingerprint();

        ImportRunner::runBatch(LearnDashSource::ID);

        self::assertSame($before, $this->fingerprint());
    }

    public function testADryRunCreatesNothing(): void
    {
        $this->seedCourseWithTopics();
        $before = self::countSikshyaPosts();

        $result = ImportRunner::runBatch(LearnDashSource::ID, true);

        self::assertTrue($result['dry_run']);
        self::assertSame(1, $result['tally']['course']);
        self::assertSame($before, self::countSikshyaPosts());
    }

    private static function countSikshyaPosts(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s, %s, %s)",
            PostTypes::COURSE,
            PostTypes::CHAPTER,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::QUESTION
        ));
    }

    private function fingerprint(): string
    {
        global $wpdb;

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, post_status, menu_order
             FROM {$wpdb->posts}
             WHERE post_type IN (%s, %s, %s, %s)
             ORDER BY ID ASC",
            LearnDashSource::CPT_COURSE,
            LearnDashSource::CPT_LESSON,
            LearnDashSource::CPT_TOPIC,
            LearnDashSource::CPT_QUIZ
        ), ARRAY_A);

        $questions = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->prefix . 'learndash_pro_quiz_question ORDER BY id ASC',
            ARRAY_A
        );

        $activity = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->prefix . 'learndash_user_activity ORDER BY activity_id ASC',
            ARRAY_A
        );

        return (string) wp_json_encode([$posts, $questions, $activity]);
    }
}
