<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Import\ImportMap;
use Sikshya\Import\ImportMapTable;
use Sikshya\Import\ImportRunner;
use Sikshya\Import\Sources\TutorSource;
use WP_UnitTestCase;

/**
 * Moving a Tutor LMS site into Sikshya.
 *
 * Two properties matter more than any single field mapping.
 *
 * **It can be run twice.** People stop an import half way, add a course to the
 * old plugin, and press the button again. A second run that duplicates the
 * catalogue is worse than no importer at all, so most of these tests run the
 * import more than once.
 *
 * **It reads and never writes to the source.** The old plugin's data has to be
 * exactly as it was afterwards, because that is what makes switching back
 * possible — and switching back is why an administrator is willing to try.
 *
 * @covers \Sikshya\Import\Sources\TutorSource
 * @covers \Sikshya\Import\CourseWriter
 * @covers \Sikshya\Import\ImportRunner
 * @covers \Sikshya\Import\ImportMap
 *
 * @group import
 */
final class TutorImportIntegrationTest extends WP_UnitTestCase
{
    private int $tutorCourse = 0;
    private int $tutorTopic = 0;
    private int $tutorLesson = 0;
    private int $tutorQuiz = 0;
    private int $learner = 0;
    private int $finisher = 0;

    public function setUp(): void
    {
        parent::setUp();

        ImportMapTable::ensure();
        $this->registerTutorPostTypes();
        $this->createTutorTables();
        ImportRunner::resetProgress(TutorSource::ID);
        ImportRunner::resetProgress(TutorSource::ID, true);

        $this->learner = self::factory()->user->create(['role' => 'subscriber']);
        $this->finisher = self::factory()->user->create(['role' => 'subscriber']);

        $this->seedTutorCourse();
    }

    public function tearDown(): void
    {
        global $wpdb;

        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tutor_quiz_question_answers');
        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tutor_quiz_questions');
        ImportRunner::resetProgress(TutorSource::ID);
        ImportRunner::resetProgress(TutorSource::ID, true);

        parent::tearDown();
    }

    /**
     * Tutor's post types do not exist on a site where Tutor is not installed,
     * and the usual migration happens exactly there — so the importer must
     * work against rows whose post type is not registered. Registering them
     * here only makes `get_posts()` usable inside the test.
     */
    private function registerTutorPostTypes(): void
    {
        foreach ([TutorSource::CPT_COURSE, TutorSource::CPT_TOPIC, TutorSource::CPT_LESSON, TutorSource::CPT_QUIZ, TutorSource::CPT_ENROLMENT] as $type) {
            register_post_type($type, ['public' => false, 'hierarchical' => true]);
        }
        register_taxonomy('course-category', [TutorSource::CPT_COURSE], ['public' => false]);
        register_taxonomy('course-tag', [TutorSource::CPT_COURSE], ['public' => false]);
    }

    private function createTutorTables(): void
    {
        global $wpdb;

        $questions = $wpdb->prefix . 'tutor_quiz_questions';
        $answers = $wpdb->prefix . 'tutor_quiz_question_answers';

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$questions} (
            question_id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            question_title text NULL,
            question_description longtext NULL,
            question_type varchar(50) NULL,
            question_mark decimal(9,2) NULL,
            question_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (question_id)
        )");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$answers} (
            answer_id bigint(20) NOT NULL AUTO_INCREMENT,
            belongs_question_id bigint(20) NOT NULL,
            answer_title text NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            answer_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (answer_id)
        )");
    }

    private function seedTutorCourse(): void
    {
        global $wpdb;

        $this->tutorCourse = self::factory()->post->create([
            'post_type' => TutorSource::CPT_COURSE,
            'post_status' => 'publish',
            'post_title' => 'Fire Safety (Tutor)',
            'post_content' => '<p>The old course body.</p>',
            'post_excerpt' => 'Short blurb',
        ]);
        update_post_meta($this->tutorCourse, '_tutor_course_level', 'intermediate');
        update_post_meta($this->tutorCourse, '_tutor_course_duration', ['hours' => 1, 'minutes' => 30]);
        update_post_meta($this->tutorCourse, '_tutor_course_benefits', "Put out a fire\nStay alive");
        wp_set_object_terms($this->tutorCourse, ['Compliance'], 'course-category', false);
        wp_set_object_terms($this->tutorCourse, ['annual'], 'course-tag', false);

        $this->tutorTopic = self::factory()->post->create([
            'post_type' => TutorSource::CPT_TOPIC,
            'post_parent' => $this->tutorCourse,
            'post_status' => 'publish',
            'post_title' => 'Basics',
            'menu_order' => 0,
        ]);

        $this->tutorLesson = self::factory()->post->create([
            'post_type' => TutorSource::CPT_LESSON,
            'post_parent' => $this->tutorTopic,
            'post_status' => 'publish',
            'post_title' => 'Extinguisher types',
            'post_content' => '<p>Water, foam, CO2.</p>',
            'menu_order' => 0,
        ]);
        update_post_meta($this->tutorLesson, '_video', [
            'source' => 'youtube',
            'source_youtube' => 'https://youtu.be/abc123',
        ]);
        update_user_meta($this->finisher, '_tutor_completed_lesson_id_' . $this->tutorLesson, '2026-01-01 10:00:00');

        $this->tutorQuiz = self::factory()->post->create([
            'post_type' => TutorSource::CPT_QUIZ,
            'post_parent' => $this->tutorTopic,
            'post_status' => 'publish',
            'post_title' => 'Fire safety check',
            'menu_order' => 1,
        ]);
        update_post_meta($this->tutorQuiz, 'tutor_quiz_option', [
            'passing_grade' => 70,
            'time_limit' => ['time_value' => 2, 'time_type' => 'hours'],
            'attempts_allowed' => 3,
        ]);

        $questions = $wpdb->prefix . 'tutor_quiz_questions';
        $answers = $wpdb->prefix . 'tutor_quiz_question_answers';

        // A single choice, which Sikshya calls multiple_choice.
        $wpdb->insert($questions, [
            'quiz_id' => $this->tutorQuiz,
            'question_title' => 'Which extinguisher for an electrical fire?',
            'question_description' => 'Pick one.',
            'question_type' => 'single_choice',
            'question_mark' => 2,
            'question_order' => 1,
        ]);
        $single = (int) $wpdb->insert_id;
        foreach ([['Water', 0], ['CO2', 1], ['Foam', 0]] as $i => [$title, $correct]) {
            $wpdb->insert($answers, [
                'belongs_question_id' => $single,
                'answer_title' => $title,
                'is_correct' => $correct,
                'answer_order' => $i,
            ]);
        }

        // A type with no honest equivalent here.
        $wpdb->insert($questions, [
            'quiz_id' => $this->tutorQuiz,
            'question_title' => 'Match the extinguisher to the fire',
            'question_type' => 'image_matching',
            'question_mark' => 1,
            'question_order' => 2,
        ]);

        // Enrolments: Tutor's `completed` status means the enrolment went
        // through, not that the learner finished.
        foreach ([$this->learner, $this->finisher] as $userId) {
            self::factory()->post->create([
                'post_type' => TutorSource::CPT_ENROLMENT,
                'post_parent' => $this->tutorCourse,
                'post_author' => $userId,
                'post_status' => 'completed',
                'post_date' => '2026-02-03 09:00:00',
            ]);
        }

        // Only one of them actually finished the course.
        wp_insert_comment([
            'comment_post_ID' => $this->tutorCourse,
            'comment_type' => 'course_completed',
            'comment_approved' => 'approved',
            'user_id' => $this->finisher,
            'comment_date' => '2026-03-04 08:00:00',
        ]);
    }

    // ----- detection ---------------------------------------------------------

    public function testItDetectsTutorDataAndCountsIt(): void
    {
        $source = new TutorSource();

        self::assertTrue($source->detect(), 'Detection looks for data, because the old plugin is usually already off.');

        $counts = $source->counts();
        self::assertSame(1, $counts['course']);
        self::assertSame(1, $counts['chapter']);
        self::assertSame(1, $counts['lesson']);
        self::assertSame(1, $counts['quiz']);
        self::assertSame(2, $counts['question']);
        self::assertSame(2, $counts['enrolment']);
    }

    public function testAvailableSourcesReportWhatIsThereAndWhatHasArrived(): void
    {
        $available = ImportRunner::available();
        $tutor = null;
        foreach ($available as $row) {
            if ($row['id'] === TutorSource::ID) {
                $tutor = $row;
            }
        }

        self::assertIsArray($tutor);
        self::assertTrue($tutor['detected']);
        self::assertSame(1, $tutor['counts']['course']);
        self::assertSame([], $tutor['imported'], 'Nothing has been imported yet.');
    }

    // ----- the dry run -------------------------------------------------------

    public function testADryRunCountsEverythingAndCreatesNothing(): void
    {
        $before = self::countSikshyaPosts();

        $result = ImportRunner::runBatch(TutorSource::ID, true);

        self::assertTrue($result['ok']);
        self::assertTrue($result['dry_run']);
        self::assertSame(1, $result['tally']['course']);
        self::assertSame(1, $result['tally']['chapter']);
        self::assertSame(1, $result['tally']['lesson']);
        self::assertSame(1, $result['tally']['quiz']);
        self::assertSame(1, $result['tally']['question']);
        self::assertSame(1, $result['tally']['question_skipped']);
        self::assertSame(2, $result['tally']['enrolment']);

        self::assertSame(
            $before,
            self::countSikshyaPosts(),
            'A migration nobody can preview is a migration nobody runs on a live site.'
        );
    }

    // ----- the import --------------------------------------------------------

    public function testItRecreatesTheCourseWithItsSectionsLessonsAndQuiz(): void
    {
        $result = ImportRunner::runBatch(TutorSource::ID);

        self::assertTrue($result['ok'], (string) ($result['message'] ?? ''));
        self::assertTrue($result['finished']);

        $map = new ImportMap(TutorSource::ID);
        $courseId = $map->find('course', $this->tutorCourse);
        self::assertGreaterThan(0, $courseId);

        $course = get_post($courseId);
        self::assertSame(PostTypes::COURSE, $course->post_type);
        self::assertSame('Fire Safety (Tutor)', $course->post_title);
        self::assertSame('publish', $course->post_status);
        self::assertStringContainsString('The old course body.', (string) $course->post_content);
        self::assertSame('intermediate', get_post_meta($courseId, '_sikshya_difficulty_level', true));
        self::assertSame('90', (string) get_post_meta($courseId, '_sikshya_duration', true));
        self::assertStringContainsString('Put out a fire', (string) get_post_meta($courseId, '_sikshya_course_benefits', true));

        self::assertContains(
            'Compliance',
            (array) wp_get_post_terms($courseId, \Sikshya\Constants\Taxonomies::COURSE_CATEGORY, ['fields' => 'names'])
        );

        $chapterId = $map->find('chapter', $this->tutorTopic);
        self::assertGreaterThan(0, $chapterId);
        self::assertSame($courseId, (int) get_post($chapterId)->post_parent);

        $lessonId = $map->find('lesson', $this->tutorLesson);
        self::assertGreaterThan(0, $lessonId);
        self::assertSame('Extinguisher types', get_post($lessonId)->post_title);
        self::assertSame(
            'video',
            (string) get_post_meta($lessonId, '_sikshya_lesson_type', true),
            'A Tutor lesson with a video is a video lesson here, not a text one with a URL nobody renders.'
        );
        self::assertSame('https://youtu.be/abc123', get_post_meta($lessonId, '_sikshya_media_url', true));

        $quizId = $map->find('quiz', $this->tutorQuiz);
        self::assertGreaterThan(0, $quizId);
        self::assertSame('70', (string) get_post_meta($quizId, '_sikshya_quiz_passing_score', true));
        self::assertSame(
            '120',
            (string) get_post_meta($quizId, '_sikshya_quiz_time_limit', true),
            'Tutor states a limit with a unit; two hours is 120 minutes, not 2.'
        );
    }

    public function testCategoriesArriveEvenWhenTheOldPluginIsDeactivated(): void
    {
        // The usual migration: the other plugin is already switched off, so
        // its taxonomies are not registered and `wp_get_post_terms()` on them
        // returns an error rather than terms.
        unregister_taxonomy('course-category');
        unregister_taxonomy('course-tag');
        self::assertFalse(taxonomy_exists('course-category'));

        ImportRunner::runBatch(TutorSource::ID);

        $courseId = (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse);
        self::assertContains(
            'Compliance',
            (array) wp_get_post_terms($courseId, \Sikshya\Constants\Taxonomies::COURSE_CATEGORY, ['fields' => 'names']),
            'Reading the source through WordPress APIs that need it registered is how every course arrives uncategorised.'
        );
    }

    public function testQuestionsArriveWithTheAnswerKeyTheGraderReads(): void
    {
        ImportRunner::runBatch(TutorSource::ID);

        $map = new ImportMap(TutorSource::ID);
        $quizId = $map->find('quiz', $this->tutorQuiz);
        $questionIds = get_post_meta($quizId, '_sikshya_quiz_questions', true);

        self::assertIsArray($questionIds);
        self::assertCount(1, $questionIds, 'The unmappable question is skipped, not flattened into something else.');

        $questionId = (int) $questionIds[0];
        self::assertSame('multiple_choice', get_post_meta($questionId, '_sikshya_question_type', true));
        self::assertSame(
            ['Water', 'CO2', 'Foam'],
            get_post_meta($questionId, '_sikshya_question_options', true)
        );
        self::assertSame(
            '1',
            (string) get_post_meta($questionId, '_sikshya_question_correct_answer', true),
            'The grader reads an option index. A label here marks every learner wrong.'
        );
        self::assertSame('2', (string) get_post_meta($questionId, '_sikshya_question_points', true));
    }

    public function testAnUnmappableQuestionTypeIsReportedRatherThanGuessedAt(): void
    {
        $result = ImportRunner::runBatch(TutorSource::ID);

        self::assertSame(1, $result['tally']['question_skipped'] ?? 0);
        self::assertNotSame([], $result['notes']);
        self::assertStringContainsString('image_matching', implode(' ', $result['notes']));
    }

    public function testEnrolmentsArriveAndOnlyTheLearnerWhoFinishedIsComplete(): void
    {
        ImportRunner::runBatch(TutorSource::ID);

        $courseId = (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse);
        $repo = new EnrollmentRepository();

        $enrolled = $repo->findByUserAndCourse($this->learner, $courseId);
        $finished = $repo->findByUserAndCourse($this->finisher, $courseId);

        self::assertNotNull($enrolled);
        self::assertNotNull($finished);
        self::assertSame(
            'enrolled',
            (string) $enrolled->status,
            "Tutor's enrolment status of 'completed' means the enrolment went through. Reading it as course completion would mark every student of every course as having finished."
        );
        self::assertSame('completed', (string) $finished->status);
        self::assertStringStartsWith(
            '2026-02-03',
            (string) $enrolled->enrolled_date,
            'The date they joined, not the date of the import — otherwise every "who joined this quarter" report is wrong at once.'
        );
    }

    public function testLessonCompletionsCarryOver(): void
    {
        ImportRunner::runBatch(TutorSource::ID);

        $map = new ImportMap(TutorSource::ID);
        $lessonId = $map->find('lesson', $this->tutorLesson);

        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_progress';
        $done = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND lesson_id = %d AND status = %s",
            $this->finisher,
            $lessonId,
            'completed'
        ));
        $notDone = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND lesson_id = %d AND status = %s",
            $this->learner,
            $lessonId,
            'completed'
        ));

        self::assertSame(1, $done, 'A learner who had finished a lesson should not have to do it again.');
        self::assertSame(0, $notDone);
    }

    // ----- the properties that matter ---------------------------------------

    public function testRunningTheImportTwiceDoesNotDuplicateAnything(): void
    {
        ImportRunner::runBatch(TutorSource::ID);
        $after = self::countSikshyaPosts();
        $courseId = (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse);

        ImportRunner::resetProgress(TutorSource::ID);

        // Captured, because this is how the failure shows up: WordPress prints
        // a database error for every refused insert, and with debug display on
        // that lands on the page.
        ob_start();
        $second = ImportRunner::runBatch(TutorSource::ID);
        $printed = (string) ob_get_clean();

        self::assertStringNotContainsString(
            'database error',
            strtolower($printed),
            'A re-run must not attempt enrolments the unique index then refuses.'
        );
        self::assertTrue($second['ok']);
        self::assertSame(
            $after,
            self::countSikshyaPosts(),
            'People do run importers twice. A second catalogue is worse than no importer.'
        );
        self::assertSame(
            $courseId,
            (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse),
            'The same source course has to keep pointing at the same Sikshya course.'
        );
        self::assertSame(
            1,
            (int) count((array) get_post_meta(
                (new ImportMap(TutorSource::ID))->find('quiz', $this->tutorQuiz),
                '_sikshya_quiz_questions',
                true
            )),
            'Nor a second copy of the questions.'
        );

        global $wpdb;
        $enrolments = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sikshya_enrollments WHERE course_id = %d',
            $courseId
        ));
        self::assertSame(2, $enrolments, 'Nor a second enrolment row for the same learner.');

        self::assertSame(
            2,
            (int) ((new ImportMap(TutorSource::ID))->counts()['enrolment'] ?? 0),
            'Enrolments are recorded as imported too, so the screen can say how many have come across.'
        );

        self::assertSame(
            '',
            (string) $wpdb->last_error,
            'A re-run must not insert enrolments that the unique index then refuses: that is a database error per learner per course, in the log and on the page.'
        );
    }

    public function testALearnerWhoNoLongerExistsIsNotEnrolled(): void
    {
        global $wpdb;

        // An enrolment left behind by a user who has since been deleted —
        // common on a site old enough to be migrating.
        self::factory()->post->create([
            'post_type' => TutorSource::CPT_ENROLMENT,
            'post_parent' => $this->tutorCourse,
            'post_author' => 999999,
            'post_status' => 'completed',
        ]);

        $result = ImportRunner::runBatch(TutorSource::ID);

        $courseId = (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse);
        $rows = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sikshya_enrollments WHERE course_id = %d',
            $courseId
        ));

        self::assertSame(2, $rows, 'A learner who no longer exists cannot be enrolled.');
        self::assertSame(
            2,
            (int) ($result['totals']['enrolment'] ?? 0),
            'And counting them would overstate what came across.'
        );
        self::assertSame('', (string) $wpdb->last_error);
    }

    public function testASecondRunPicksUpATitleChangedInTheOldPlugin(): void
    {
        ImportRunner::runBatch(TutorSource::ID);
        wp_update_post(['ID' => $this->tutorCourse, 'post_title' => 'Fire Safety (renamed)']);

        ImportRunner::resetProgress(TutorSource::ID);
        ImportRunner::runBatch(TutorSource::ID);

        $courseId = (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse);
        self::assertSame('Fire Safety (renamed)', get_post($courseId)->post_title);
    }

    public function testTheSourceIsLeftExactlyAsItWas(): void
    {
        $before = $this->tutorFingerprint();

        ImportRunner::runBatch(TutorSource::ID);

        self::assertSame(
            $before,
            $this->tutorFingerprint(),
            'Switching back has to stay possible; that is why an administrator is willing to try this at all.'
        );
    }

    public function testADeletedSikshyaCourseIsRecreatedRatherThanOrphaned(): void
    {
        ImportRunner::runBatch(TutorSource::ID);
        $map = new ImportMap(TutorSource::ID);
        $first = $map->find('course', $this->tutorCourse);
        wp_delete_post($first, true);

        self::assertSame(0, $map->find('course', $this->tutorCourse), 'A mapping to a deleted post is not a mapping.');

        ImportRunner::resetProgress(TutorSource::ID);
        ImportRunner::runBatch(TutorSource::ID);

        $second = $map->find('course', $this->tutorCourse);
        self::assertGreaterThan(0, $second);
        self::assertNotSame($first, $second);
    }

    public function testTheBatchStopsAtItsSizeAndTheCursorCarriesOn(): void
    {
        // A second course, so one batch cannot cover both.
        $other = self::factory()->post->create([
            'post_type' => TutorSource::CPT_COURSE,
            'post_status' => 'publish',
            'post_title' => 'First aid (Tutor)',
        ]);

        add_filter('sikshya_import_batch_size', [$this, 'oneCoursePerBatch'], 99);

        $first = ImportRunner::runBatch(TutorSource::ID);
        self::assertSame(1, $first['courses_this_batch']);
        self::assertFalse($first['finished'], 'A site with four hundred courses cannot import inside one request.');

        $second = ImportRunner::runBatch(TutorSource::ID);
        self::assertSame(1, $second['courses_this_batch']);
        self::assertTrue($second['finished']);

        remove_filter('sikshya_import_batch_size', [$this, 'oneCoursePerBatch'], 99);

        $map = new ImportMap(TutorSource::ID);
        self::assertGreaterThan(0, $map->find('course', $this->tutorCourse));
        self::assertGreaterThan(0, $map->find('course', $other));
    }

    public function testEveryBatchImportsAtLeastOneCourseEvenWithNoTimeLeft(): void
    {
        add_filter('sikshya_import_time_budget', [$this, 'noTimeAtAll'], 99);

        $result = ImportRunner::runBatch(TutorSource::ID);

        remove_filter('sikshya_import_time_budget', [$this, 'noTimeAtAll'], 99);

        self::assertSame(
            1,
            $result['courses_this_batch'],
            'A budget already spent must still let a batch make progress, or a slow host never finishes.'
        );
        self::assertGreaterThan(0, (new ImportMap(TutorSource::ID))->find('course', $this->tutorCourse));
    }

    public function testASecondCourseIsStillReachedAfterTheBudgetStopsTheFirstBatch(): void
    {
        $other = self::factory()->post->create([
            'post_type' => TutorSource::CPT_COURSE,
            'post_status' => 'publish',
            'post_title' => 'First aid (Tutor)',
        ]);

        add_filter('sikshya_import_time_budget', [$this, 'noTimeAtAll'], 99);

        $first = ImportRunner::runBatch(TutorSource::ID);
        self::assertSame(1, $first['courses_this_batch']);
        self::assertFalse($first['finished']);

        $second = ImportRunner::runBatch(TutorSource::ID);

        remove_filter('sikshya_import_time_budget', [$this, 'noTimeAtAll'], 99);

        self::assertSame(1, $second['courses_this_batch'], 'The cursor has to carry on from the first batch, not restart.');
        self::assertTrue($second['finished']);
        self::assertGreaterThan(0, (new ImportMap(TutorSource::ID))->find('course', $other));
    }

    /** @param float $seconds */
    public function noTimeAtAll($seconds = 20.0): float
    {
        unset($seconds);

        return 0.0001;
    }

    public function testAnUnknownSourceIsRefusedWithAMessage(): void
    {
        $result = ImportRunner::runBatch('moodle');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Unknown import source', (string) $result['message']);
    }

    // ----- REST --------------------------------------------------------------

    public function testTheRestSurfaceReportsSourcesRunsABatchAndResetsProgress(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $routes = new \Sikshya\Api\Admin\ImportRoutes(\Sikshya\Core\Plugin::getInstance());

        $sources = $routes->sources(new \WP_REST_Request('GET', '/x'))->get_data();
        self::assertTrue($sources['ok']);
        $ids = array_column($sources['sources'], 'id');
        self::assertContains(TutorSource::ID, $ids);

        $run = new \WP_REST_Request('POST', '/x');
        $run->set_param('source', TutorSource::ID);
        $run->set_param('dry_run', true);
        $dry = $routes->run($run)->get_data();

        self::assertTrue($dry['ok']);
        self::assertTrue($dry['dry_run']);
        self::assertSame(1, $dry['tally']['course']);

        $reset = new \WP_REST_Request('POST', '/x');
        $reset->set_param('source', TutorSource::ID);
        $reset->set_param('dry_run', true);
        $cleared = $routes->reset($reset)->get_data();

        self::assertTrue($cleared['ok']);
        self::assertStringContainsString('nothing already imported is duplicated', (string) $cleared['message']);
    }

    public function testAnInstructorCannotRunAnImport(): void
    {
        $instructor = self::factory()->user->create(['role' => 'sikshya_instructor']);
        wp_set_current_user($instructor);

        $routes = new \Sikshya\Api\Admin\ImportRoutes(\Sikshya\Core\Plugin::getInstance());

        self::assertNotTrue(
            $routes->permissionTools(new \WP_REST_Request('POST', '/x')),
            'An import writes courses, enrolments and completions across the whole site.'
        );
    }

    /** @param int $size */
    public function oneCoursePerBatch($size = 3): int
    {
        unset($size);

        return 1;
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

    private function tutorFingerprint(): string
    {
        global $wpdb;

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, post_status, post_parent, menu_order
             FROM {$wpdb->posts}
             WHERE post_type IN (%s, %s, %s, %s, %s)
             ORDER BY ID ASC",
            TutorSource::CPT_COURSE,
            TutorSource::CPT_TOPIC,
            TutorSource::CPT_LESSON,
            TutorSource::CPT_QUIZ,
            TutorSource::CPT_ENROLMENT
        ), ARRAY_A);

        $questions = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->prefix . 'tutor_quiz_questions ORDER BY question_id ASC',
            ARRAY_A
        );

        return (string) wp_json_encode([$posts, $questions]);
    }
}
