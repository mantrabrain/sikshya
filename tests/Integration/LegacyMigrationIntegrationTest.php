<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\OrderItemsTable;
use Sikshya\Database\Tables\OrdersTable;
use Sikshya\Database\Tables\ProgressTable;
use Sikshya\Migration\LegacyDataDetector;
use Sikshya\Services\Settings;
use Sikshya\Migration\LegacyMigrator;
use WP_UnitTestCase;

/**
 * The legacy upgrade path: 17 migration steps that every existing customer runs.
 *
 * These steps rename post types and taxonomies in place and project rows out of
 * the old `wp_sikshya_user_items` table into the rewrite's enrollment, progress
 * and order tables. They are destructive, one-shot and irreversible, and before
 * this file nothing exercised a single one of them — `src/Migration` is even
 * excluded from coverage. A mistake here mangles a paying customer's data on
 * upgrade, with no way back.
 *
 * Three properties matter more than any individual field mapping:
 *
 *  1. A dry run must change nothing. It is the only way an admin can inspect
 *     what will happen before committing.
 *  2. The real run must map every status and split lesson/quiz progress
 *     correctly.
 *  3. Re-running must not duplicate anything. Migrations resume after timeouts
 *     and admins re-trigger them, so this gets exercised in the wild.
 *
 * ISOLATION: the legacy tables must be real, not `TEMPORARY`. WP_UnitTestCase
 * rewrites `CREATE TABLE` via the `query` filter, and a temporary table is
 * invisible to the `SHOW TABLES` checks the steps use to detect legacy data.
 * Creating a real table forces an implicit commit, which defeats the
 * transaction wrapping each test, so this group is excluded from the default
 * run and tearDown undoes its own fixtures explicitly.
 *
 * @group migration
 * @covers \Sikshya\Migration\LegacyMigrator
 */
final class LegacyMigrationIntegrationTest extends WP_UnitTestCase
{
    private const LEGACY_ITEMS = 'sikshya_user_items';

    private int $student = 0;
    private int $instructor = 0;

    /** @var int[] */
    private array $createdPosts = [];

    private int $courseA = 0;
    private int $courseB = 0;
    private int $lesson = 0;
    private int $quiz = 0;
    private int $section = 0;
    private int $question = 0;
    private int $orderPost = 0;
    private int $legacyTermId = 0;

    public function setUp(): void
    {
        parent::setUp();

        // Real tables, not TEMPORARY — see the class docblock.
        remove_filter('query', [$this, '_create_temporary_tables']);
        remove_filter('query', [$this, '_drop_temporary_tables']);

        LegacyMigrator::reset();
        $this->seedLegacySite();
    }

    public function tearDown(): void
    {
        global $wpdb;

        foreach ($this->createdPosts as $id) {
            if (get_post($id)) {
                wp_delete_post($id, true);
            }
        }

        if ($this->legacyTermId > 0) {
            $wpdb->delete($wpdb->term_taxonomy, ['term_id' => $this->legacyTermId]);
            $wpdb->delete($wpdb->terms, ['term_id' => $this->legacyTermId]);
        }

        foreach ([EnrollmentsTable::getTableName(), ProgressTable::getTableName()] as $table) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d", $this->student));
        }
        $orders = OrdersTable::getTableName();
        $items = OrderItemsTable::getTableName();
        $wpdb->query($wpdb->prepare("DELETE FROM {$items} WHERE order_id IN (SELECT id FROM {$orders} WHERE user_id = %d)", $this->student));
        $wpdb->query($wpdb->prepare("DELETE FROM {$orders} WHERE user_id = %d", $this->student));

        $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . self::LEGACY_ITEMS . '`');

        LegacyMigrator::reset();
        parent::tearDown();
    }

    private function legacyPost(string $type, string $title, int $author = 0): int
    {
        global $wpdb;

        // The factory validates against registered post types, and the legacy
        // types no longer exist, so insert then set the type directly — exactly
        // the state a legacy install is in.
        $id = (int) self::factory()->post->create([
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_author' => $author ?: $this->instructor,
        ]);
        $wpdb->update($wpdb->posts, ['post_type' => $type], ['ID' => $id]);
        clean_post_cache($id);
        $this->createdPosts[] = $id;

        return $id;
    }

    private function seedLegacySite(): void
    {
        global $wpdb;

        $this->student = (int) self::factory()->user->create(['role' => 'subscriber']);
        $this->instructor = (int) self::factory()->user->create(['role' => 'author']);

        $this->courseA = $this->legacyPost('sik_courses', 'Legacy course A');
        $this->courseB = $this->legacyPost('sik_courses', 'Legacy course B');
        $this->lesson = $this->legacyPost('sik_lessons', 'Legacy lesson');
        $this->quiz = $this->legacyPost('sik_quizzes', 'Legacy quiz');
        $this->question = $this->legacyPost('sik_questions', 'Legacy question');
        $this->section = $this->legacyPost('sik_sections', 'Legacy section');
        $this->orderPost = $this->legacyPost('sik_orders', 'Legacy order', $this->student);

        // Legacy linkage meta the steps read.
        update_post_meta($this->section, 'course_id', $this->courseA);
        update_post_meta($this->section, 'section_order', 3);
        update_post_meta($this->lesson, '_sikshya_lesson_course', $this->courseA);
        update_post_meta($this->quiz, '_sikshya_quiz_course', $this->courseA);

        // Legacy meta keys the post-meta / alias / question steps rewrite.
        update_post_meta($this->courseA, 'sikshya_course_regular_price', '49');
        update_post_meta($this->courseA, 'sikshya_course_discounted_price', '29');
        update_post_meta($this->courseA, 'sikshya_course_duration', '10');
        update_post_meta($this->courseA, 'sikshya_course_level', 'beginner');

        update_post_meta($this->lesson, 'sikshya_is_preview_lesson', 'yes');
        update_post_meta($this->lesson, 'section_id', $this->section);
        update_post_meta($this->lesson, 'course_id', $this->courseA);
        update_post_meta($this->lesson, 'sikshya_order_number', 2);

        // Legacy question: answers are keyed by legacy answer id, and
        // `correct_answers` references those ids rather than the text.
        update_post_meta($this->question, 'quiz_id', $this->quiz);
        update_post_meta($this->question, 'type', 'single');
        update_post_meta($this->question, 'answers', [
            11 => ['value' => 'Paris'],
            12 => ['value' => 'Berlin'],
        ]);
        update_post_meta($this->question, 'correct_answers', [11]);

        // Legacy order payload.
        update_post_meta($this->orderPost, 'sikshya_order_meta', [
            'currency' => 'EUR',
            'student_id' => $this->student,
            'total_order_amount' => 59.5,
            'cart' => [
                $this->courseA => ['quantity' => 1, 'unit_price' => 39.5, 'total' => 39.5],
                $this->courseB => ['quantity' => 2, 'unit_price' => 10.0, 'total' => 20.0],
            ],
        ]);

        // Legacy store settings and billing details the remaining steps rewrite.
        update_option('sikshya_currency', 'EUR');
        update_option('sikshya_thousand_separator', '.');
        update_option('sikshya_payment_gateway_paypal_email', 'shop@example.test');

        update_user_meta($this->student, 'billing_first_name', 'Ada');
        update_user_meta($this->student, 'billing_country', 'NP');
        update_user_meta($this->student, 'billing_email', 'ada@example.test');

        // A legacy taxonomy row. Registering the old taxonomy is how a legacy
        // site looked; the step renames the term_taxonomy row with SQL.
        register_taxonomy('sik_course_category', 'sik_courses');
        $term = wp_insert_term('Legacy category', 'sik_course_category');
        if (!is_wp_error($term)) {
            $this->legacyTermId = (int) $term['term_id'];
        }

        $prefix = $wpdb->prefix . self::LEGACY_ITEMS;
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS `{$prefix}` (
                user_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                item_type VARCHAR(50) NOT NULL DEFAULT '',
                status VARCHAR(50) NOT NULL DEFAULT '',
                start_time DATETIME NULL,
                start_time_gmt DATETIME NULL,
                end_time DATETIME NULL,
                end_time_gmt DATETIME NULL,
                parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (user_item_id)
            ) DEFAULT CHARACTER SET utf8mb4"
        );

        $rows = [
            // Enrolments, covering every documented status plus an unknown one.
            [$this->courseA, 'sik_courses', 'completed'],
            [$this->courseB, 'sik_courses', 'cancelled'],
            // Progress.
            [$this->lesson, 'sik_lessons', 'completed'],
            [$this->quiz, 'sik_quizzes', 'started'],
        ];
        foreach ($rows as [$item_id, $type, $status]) {
            $wpdb->insert($prefix, [
                'user_id' => $this->student,
                'item_id' => $item_id,
                'item_type' => $type,
                'status' => $status,
                'start_time' => '2026-01-01 10:00:00',
                'start_time_gmt' => '2026-01-01 10:00:00',
                'end_time' => '2026-01-02 10:00:00',
                'end_time_gmt' => '2026-01-02 10:00:00',
                'parent_id' => 0,
            ]);
        }
    }

    private function countRows(string $table, string $where = '1=1'): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    }

    private function postType(int $id): string
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $id));
    }

    // ------------------------------------------------------------- dry run

    public function testDryRunChangesNothing(): void
    {
        global $wpdb;

        LegacyMigrator::dryRun();

        self::assertSame('sik_courses', $this->postType($this->courseA), 'A dry run must not rename post types.');
        self::assertSame('sik_lessons', $this->postType($this->lesson));
        self::assertSame('sik_sections', $this->postType($this->section));

        self::assertSame(
            0,
            $this->countRows(EnrollmentsTable::getTableName(), $wpdb->prepare('user_id = %d', $this->student)),
            'A dry run must not create enrolments.'
        );
        self::assertSame(
            0,
            $this->countRows(ProgressTable::getTableName(), $wpdb->prepare('user_id = %d', $this->student)),
            'A dry run must not create progress rows.'
        );
        self::assertSame(
            0,
            $this->countRows(OrdersTable::getTableName(), $wpdb->prepare('user_id = %d', $this->student)),
            'A dry run must not create orders.'
        );
        self::assertSame(
            '',
            (string) get_post_meta($this->orderPost, '_sikshya_migrated_order_id', true),
            'A dry run must not leave migration markers behind.'
        );
    }

    // ---------------------------------------------------------- real run

    public function testPostTypesAndTaxonomiesAreRenamed(): void
    {
        global $wpdb;

        LegacyMigrator::run();

        self::assertSame(PostTypes::COURSE, $this->postType($this->courseA));
        self::assertSame(PostTypes::COURSE, $this->postType($this->courseB));
        self::assertSame(PostTypes::LESSON, $this->postType($this->lesson));
        self::assertSame(PostTypes::QUIZ, $this->postType($this->quiz));
        self::assertSame(PostTypes::QUESTION, $this->postType($this->question));

        if ($this->legacyTermId > 0) {
            $taxonomy = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $this->legacyTermId)
            );
            self::assertSame(
                Taxonomies::COURSE_CATEGORY,
                $taxonomy,
                'Course categories must survive the upgrade, or every course loses its category.'
            );
        }
    }

    public function testSectionsBecomeChaptersParentedToTheirCourse(): void
    {
        LegacyMigrator::run();

        self::assertSame(PostTypes::CHAPTER, $this->postType($this->section));
        self::assertSame(
            $this->courseA,
            (int) get_post_field('post_parent', $this->section),
            'A converted chapter must be parented to the course its legacy `course_id` named.'
        );
        self::assertSame(
            $this->courseA,
            (int) get_post_meta($this->section, '_sikshya_chapter_course_id', true)
        );
        self::assertSame(3, (int) get_post_meta($this->section, '_sikshya_chapter_order', true));
        self::assertSame(
            'sik_sections',
            get_post_meta($this->section, '_sikshya_migrated_from_legacy', true),
            'Converted rows must be traceable back to their legacy origin.'
        );
    }

    public function testEnrolmentStatusesMapCorrectly(): void
    {
        global $wpdb;

        LegacyMigrator::run();

        $table = EnrollmentsTable::getTableName();
        $byCourse = [];
        foreach (
            $wpdb->get_results(
                $wpdb->prepare("SELECT course_id, status FROM {$table} WHERE user_id = %d", $this->student)
            ) as $row
        ) {
            $byCourse[(int) $row->course_id] = (string) $row->status;
        }

        self::assertSame('completed', $byCourse[$this->courseA] ?? null, 'completed must stay completed.');
        self::assertSame('cancelled', $byCourse[$this->courseB] ?? null, 'cancelled must stay cancelled.');
        self::assertCount(2, $byCourse, 'Only `sik_courses` rows are enrolments; lesson/quiz rows are progress.');
    }

    public function testLessonAndQuizProgressAreSplitIntoTheRightColumns(): void
    {
        global $wpdb;

        LegacyMigrator::run();

        $table = ProgressTable::getTableName();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT lesson_id, quiz_id, status, percentage, course_id FROM {$table} WHERE user_id = %d", $this->student)
        );
        self::assertCount(2, $rows, 'One lesson row and one quiz row.');

        $lessonRow = null;
        $quizRow = null;
        foreach ($rows as $row) {
            if ((int) $row->lesson_id === $this->lesson) {
                $lessonRow = $row;
            }
            if ((int) $row->quiz_id === $this->quiz) {
                $quizRow = $row;
            }
        }

        self::assertNotNull($lessonRow, 'Lesson progress must land in lesson_id, not quiz_id.');
        self::assertSame(0, (int) $lessonRow->quiz_id);
        self::assertSame('completed', (string) $lessonRow->status);
        self::assertSame(100.0, (float) $lessonRow->percentage, 'A completed item must read 100%.');
        self::assertSame(
            $this->courseA,
            (int) $lessonRow->course_id,
            'Progress must be attributed to the owning course, resolved from legacy meta.'
        );

        self::assertNotNull($quizRow, 'Quiz progress must land in quiz_id, not lesson_id.');
        self::assertSame(0, (int) $quizRow->lesson_id);
        self::assertSame('in_progress', (string) $quizRow->status, '`started` maps to in_progress.');
    }

    public function testLegacyOrdersBecomeOrdersWithLineItems(): void
    {
        global $wpdb;

        LegacyMigrator::run();

        $orders = OrdersTable::getTableName();
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$orders} WHERE user_id = %d", $this->student)
        );
        self::assertNotNull($order, 'The legacy order post must become an order row.');
        self::assertSame('EUR', (string) $order->currency, 'Currency must come from the legacy payload, not the default.');
        self::assertSame(59.5, (float) $order->total);
        self::assertSame('legacy_' . $this->orderPost, (string) $order->public_token);

        $items = OrderItemsTable::getTableName();
        $lines = $wpdb->get_results(
            $wpdb->prepare("SELECT course_id, quantity, unit_price, line_total FROM {$items} WHERE order_id = %d ORDER BY course_id ASC", (int) $order->id)
        );
        self::assertCount(2, $lines, 'Both cart lines must be carried over.');

        $byCourse = [];
        foreach ($lines as $line) {
            $byCourse[(int) $line->course_id] = $line;
        }
        self::assertSame(1, (int) $byCourse[$this->courseA]->quantity);
        self::assertSame(39.5, (float) $byCourse[$this->courseA]->unit_price);
        self::assertSame(2, (int) $byCourse[$this->courseB]->quantity);
        self::assertSame(20.0, (float) $byCourse[$this->courseB]->line_total);

        self::assertSame(
            (int) $order->id,
            (int) get_post_meta($this->orderPost, '_sikshya_migrated_order_id', true),
            'The marker meta is the first line of defence against a re-run duplicating the order.'
        );
    }

    // ------------------------------------------------------- idempotency

    public function testRunningTheMigrationTwiceDuplicatesNothing(): void
    {
        global $wpdb;

        LegacyMigrator::run();

        $student = $wpdb->prepare('user_id = %d', $this->student);
        $before = [
            'enrollments' => $this->countRows(EnrollmentsTable::getTableName(), $student),
            'progress' => $this->countRows(ProgressTable::getTableName(), $student),
            'orders' => $this->countRows(OrdersTable::getTableName(), $student),
        ];
        $orderId = (int) $wpdb->get_var("SELECT id FROM " . OrdersTable::getTableName() . " WHERE {$student}");
        $before['order_items'] = $this->countRows(OrderItemsTable::getTableName(), $wpdb->prepare('order_id = %d', $orderId));

        // A migration that times out mid-way is resumed, and admins re-trigger
        // it from the Tools screen, so a second pass is a real scenario.
        LegacyMigrator::reset();
        LegacyMigrator::run();

        self::assertSame(
            $before['enrollments'],
            $this->countRows(EnrollmentsTable::getTableName(), $student),
            'Re-running must not duplicate enrolments.'
        );
        self::assertSame(
            $before['progress'],
            $this->countRows(ProgressTable::getTableName(), $student),
            'Re-running must not duplicate progress rows.'
        );
        self::assertSame(
            $before['orders'],
            $this->countRows(OrdersTable::getTableName(), $student),
            'Re-running must not duplicate orders. The orders step uses a plain INSERT (not '
            . 'INSERT IGNORE), but two independent guards cover it: the '
            . '_sikshya_migrated_order_id marker short-circuits an already-migrated post, and '
            . 'the deterministic public_token `legacy_<postID>` collides with UNIQUE KEY '
            . 'public_token if it ever gets that far. Verified by mutation: removing the marker '
            . 'still produces no duplicate.'
        );
        self::assertSame(
            $before['order_items'],
            $this->countRows(OrderItemsTable::getTableName(), $wpdb->prepare('order_id = %d', $orderId)),
            'Re-running must not duplicate order line items.'
        );
    }

    public function testMigrationConvergesAndSchedulesItsOwnFollowUp(): void
    {
        // One `run()` executes a single batch per step, and a step only reports
        // complete on the pass where it finds nothing left to do — so the first
        // call is expected NOT to finish, even on a tiny data set. It must hand
        // off to cron rather than stopping.
        LegacyMigrator::run();

        self::assertFalse(
            LegacyMigrator::isComplete(),
            'A single batch is not expected to finish; this pins the batching contract.'
        );
        self::assertNotFalse(
            wp_next_scheduled(LegacyMigrator::CRON_HOOK),
            'An unfinished migration must schedule its next batch, or it stalls silently '
            . 'and the site is left half-migrated.'
        );

        // It must then converge. A step that never reports complete would
        // reschedule forever, pinning cron and nagging the admin indefinitely.
        $runs = 1;
        $limit = count(LegacyMigrator::steps()) + 2;
        while (!LegacyMigrator::isComplete() && $runs < $limit) {
            LegacyMigrator::run();
            $runs++;
        }

        self::assertTrue(
            LegacyMigrator::isComplete(),
            sprintf('The migration must converge; it was still unfinished after %d runs.', $runs)
        );
        self::assertLessThanOrEqual(
            3,
            $runs,
            'This data set should settle in two passes. Needing more means a step is not '
            . 'reporting completion when its work is done.'
        );
    }

    // -------------------------------------------------------- detection

    public function testLegacyDataIsDetectedBeforeMigrating(): void
    {
        $fingerprint = LegacyDataDetector::fingerprint();

        self::assertTrue($fingerprint['has_legacy_data'], 'A legacy site must be detected.');
        self::assertArrayHasKey('sik_courses', $fingerprint['post_types']);
        self::assertSame(4, $fingerprint['legacy_item_rows'], 'Two enrolment rows and two progress rows.');
    }

    public function testDetectionSurvivesAMigrationInterruptedAfterTheRename(): void
    {
        global $wpdb;

        // Reproduce the dangerous window: post types already renamed, but the
        // enrolment and progress rows not yet projected. That is the state a run
        // killed by a timeout leaves behind, and with a post-types-only signal it
        // reported "nothing to migrate" — silently abandoning every learner's
        // enrolment and progress in a table nothing reads.
        //
        // The order post is removed because `MigrateOrders` never renames
        // `sik_orders` (it marks them instead), so a site that had legacy orders
        // keeps that signal anyway. The gap only bites a site without them.
        wp_delete_post($this->orderPost, true);

        foreach (
            [
                'sik_courses' => PostTypes::COURSE,
                'sik_lessons' => PostTypes::LESSON,
                'sik_quizzes' => PostTypes::QUIZ,
                'sik_questions' => PostTypes::QUESTION,
                'sik_sections' => PostTypes::CHAPTER,
            ] as $legacy => $modern
        ) {
            $wpdb->update($wpdb->posts, ['post_type' => $modern], ['post_type' => $legacy]);
        }
        $wpdb->update(
            $wpdb->term_taxonomy,
            ['taxonomy' => Taxonomies::COURSE_CATEGORY],
            ['taxonomy' => 'sik_course_category']
        );
        clean_post_cache($this->courseA);

        $fingerprint = LegacyDataDetector::fingerprint();

        self::assertEmpty($fingerprint['post_types'], 'Precondition: no legacy post types remain.');
        self::assertGreaterThan(0, $fingerprint['legacy_item_rows'], 'Precondition: user_items still holds rows.');
        self::assertFalse(LegacyMigrator::isComplete(), 'Precondition: the migration never finished.');
        self::assertTrue(
            $fingerprint['has_legacy_data'],
            'Unmigrated user_items rows on an unfinished migration must still count as legacy data.'
        );

        // And the resumed run must actually recover them.
        $runs = 0;
        while (!LegacyMigrator::isComplete() && $runs < 5) {
            LegacyMigrator::run();
            $runs++;
        }
        self::assertSame(
            2,
            $this->countRows(EnrollmentsTable::getTableName(), $wpdb->prepare('user_id = %d', $this->student)),
            'The resumed run must recover the abandoned enrolments.'
        );
        self::assertSame(
            2,
            $this->countRows(ProgressTable::getTableName(), $wpdb->prepare('user_id = %d', $this->student)),
            'The resumed run must recover the abandoned progress rows.'
        );
    }

    public function testAFinishedMigrationIsNotReflaggedByLingeringLegacyRows(): void
    {
        global $wpdb;

        // Migration copies out of `user_items` and never empties it, so the rows
        // are still there afterwards. Treating their mere presence as pending
        // work would re-arm the pending flag on every activation for the life of
        // the site and bring back the bogus "migration completed successfully"
        // notice. This is the guard against that.
        $runs = 0;
        while (!LegacyMigrator::isComplete() && $runs < 5) {
            LegacyMigrator::run();
            $runs++;
        }
        self::assertTrue(LegacyMigrator::isComplete(), 'Precondition: the migration finished.');

        // `sik_orders` posts are never renamed, so drop the order post to isolate
        // the rows signal from the post-type signal.
        wp_delete_post($this->orderPost, true);
        clean_post_cache($this->courseA);

        $fingerprint = LegacyDataDetector::fingerprint();

        self::assertGreaterThan(
            0,
            $fingerprint['legacy_item_rows'],
            'Precondition: the legacy rows are still present after a successful migration.'
        );
        self::assertFalse(
            $fingerprint['has_legacy_data'],
            'A finished migration must not be re-flagged by rows it deliberately left in place.'
        );
    }

    public function testAnInstallWithNoLegacyTableIsNotFlaggedAsLegacy(): void
    {
        global $wpdb;

        // The guard that matters: two legacy table suffixes (`sikshya_order_items`,
        // `sikshya_logs`) are also created by the current plugin on activation, so
        // table presence alone must never flag an install. Drop the one
        // legacy-only table and nothing should look legacy any more.
        $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . self::LEGACY_ITEMS . '`');
        foreach (['sik_courses', 'sik_lessons', 'sik_quizzes', 'sik_questions', 'sik_sections', 'sik_orders'] as $legacy) {
            $wpdb->update($wpdb->posts, ['post_type' => 'post'], ['post_type' => $legacy]);
        }
        $wpdb->update($wpdb->term_taxonomy, ['taxonomy' => Taxonomies::COURSE_CATEGORY], ['taxonomy' => 'sik_course_category']);

        $fingerprint = LegacyDataDetector::fingerprint();

        self::assertSame(0, $fingerprint['legacy_item_rows']);
        self::assertFalse(
            $fingerprint['has_legacy_data'],
            'A fresh install must never be flagged, or it runs a no-op migration and shows '
            . '"Legacy data migration completed successfully" forever.'
        );
        self::assertNotEmpty(
            $fingerprint['tables'],
            'Modern tables still match the legacy suffix list — proving presence alone is not the signal.'
        );
    }

    // ------------------------------------------- per-step data correctness

    public function testCourseAndLessonMetaKeysAreRewritten(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        self::assertSame('49', get_post_meta($this->courseA, '_sikshya_price', true));
        self::assertSame('29', get_post_meta($this->courseA, '_sikshya_sale_price', true));
        self::assertSame('10', get_post_meta($this->courseA, '_sikshya_duration', true));
        self::assertSame('beginner', get_post_meta($this->courseA, '_sikshya_difficulty', true));

        self::assertSame(
            (string) $this->section,
            (string) get_post_meta($this->lesson, '_sikshya_chapter_id', true),
            'A lesson must keep its chapter link, or the curriculum comes out flat.'
        );
        self::assertSame(
            (string) $this->courseA,
            (string) get_post_meta($this->lesson, '_sikshya_lesson_course', true)
        );
        self::assertNotSame(
            '',
            (string) get_post_meta($this->lesson, '_sikshya_is_free', true),
            'The preview-lesson flag gates free access and must survive.'
        );
    }

    public function testPriceAliasesAreMirroredSoTheGateAndCheckoutAgree(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        // This pair is why a paid course could once be enrolled in for free: the
        // enrolment gate read one alias while checkout resolved the other, so a
        // course priced through the "wrong" key looked free.
        self::assertSame(
            get_post_meta($this->courseA, '_sikshya_price', true),
            get_post_meta($this->courseA, '_sikshya_course_price', true),
            'Both price aliases must agree after migration.'
        );
        self::assertSame(
            get_post_meta($this->courseA, '_sikshya_duration', true),
            get_post_meta($this->courseA, '_sikshya_course_duration', true)
        );
        self::assertSame(
            get_post_meta($this->courseA, '_sikshya_difficulty', true),
            get_post_meta($this->courseA, '_sikshya_course_level', true)
        );
    }

    public function testQuizQuestionListIsRebuiltOntoTheQuiz(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        // Legacy stored the link on the question (`quiz_id`); the rewrite stores
        // an ordered id list on the quiz. Get this wrong and every migrated quiz
        // renders with no questions.
        $questions = get_post_meta($this->quiz, '_sikshya_quiz_questions', true);
        self::assertIsArray($questions, 'The quiz must carry its question id list.');
        self::assertContains(
            $this->question,
            array_map('intval', $questions),
            'The migrated question must be attached to its quiz.'
        );
    }

    public function testQuestionsAreTransformedToTheCanonicalShape(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        self::assertSame(
            'multiple_choice',
            get_post_meta($this->question, '_sikshya_question_type', true),
            'Legacy `single` maps to multiple_choice.'
        );

        $options = get_post_meta($this->question, '_sikshya_question_options', true);
        self::assertSame(['Paris', 'Berlin'], $options, 'Options become plain ordered text.');

        self::assertSame(
            'Paris',
            get_post_meta($this->question, '_sikshya_question_correct_answer', true),
            'The correct answer is stored as text, resolved from the legacy answer id.'
        );
    }

    public function testCurriculumTreeIsRebuiltOnCourseAndChapter(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        // The player reads the curriculum from these two ordered id lists. If
        // they are empty the course appears to have no content at all, even
        // though every lesson survived the migration.
        $chapters = get_post_meta($this->courseA, '_sikshya_chapters', true);
        self::assertIsArray($chapters, 'The course must carry its ordered chapter list.');
        self::assertContains(
            $this->section,
            array_map('intval', $chapters),
            'The converted section must appear in the course curriculum.'
        );

        $contents = get_post_meta($this->section, '_sikshya_contents', true);
        self::assertIsArray($contents, 'The chapter must carry its ordered content list.');
        self::assertContains(
            $this->lesson,
            array_map('intval', $contents),
            'The lesson must appear under the chapter its legacy `section_id` named.'
        );
    }

    // ----------------------------------------------------- batch boundaries

    public function testNoRowsAreLostAcrossBatchBoundaries(): void
    {
        global $wpdb;

        // Steps page through the legacy table with a persisted cursor, 50 rows at
        // a time. An off-by-one there would silently drop a learner's enrolment at
        // every batch edge — invisible on a small data set, and the kind of thing
        // that only shows up on the customer's site.
        $extraCourses = [];
        $table = $wpdb->prefix . self::LEGACY_ITEMS;
        $students = [];

        for ($i = 0; $i < 120; $i++) {
            $course = $this->legacyPost('sik_courses', 'Bulk course ' . $i);
            $extraCourses[] = $course;
            $user = (int) self::factory()->user->create(['role' => 'subscriber']);
            $students[] = $user;
            $wpdb->insert($table, [
                'user_id' => $user,
                'item_id' => $course,
                'item_type' => 'sik_courses',
                'status' => 'enrolled',
                'start_time' => '2026-01-01 10:00:00',
                'start_time_gmt' => '2026-01-01 10:00:00',
                'end_time' => null,
                'end_time_gmt' => null,
                'parent_id' => 0,
            ]);
        }

        self::assertGreaterThan(
            LegacyMigrator::BATCH_SIZE,
            120,
            'Precondition: the fixture must span more than one batch.'
        );

        $runs = 0;
        while (!LegacyMigrator::isComplete() && $runs < 40) {
            LegacyMigrator::run();
            $runs++;
        }
        self::assertTrue(LegacyMigrator::isComplete(), 'The batched run must still converge.');

        /*
         * Asserted per learner rather than as a total.
         *
         * Counting all rows for these users assumed the destination table held
         * nothing else for them, which is not a property this test controls: the
         * migration fixtures create legacy tables, that DDL implicitly commits
         * the per-test transaction in MySQL, and so rows from earlier tests and
         * earlier runs survive. The total then read 122 against a seeded 120 and
         * the suite failed only on the first run after a full suite — the test's
         * own cleanup removed the strays, so it passed on a retry and looked
         * intermittent.
         *
         * What the test actually means is "no seeded enrolment was dropped at a
         * batch boundary", which is a statement about each learner, and is true
         * regardless of what else is in the table.
         */
        $enrollments = EnrollmentsTable::getTableName();
        $placeholders = implode(',', array_fill(0, count($students), '%d'));
        $per_user = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) AS rows_for_user FROM {$enrollments}
                 WHERE user_id IN ({$placeholders}) GROUP BY user_id",
                $students
            ),
            ARRAY_A
        );

        $counts = [];
        foreach ((array) $per_user as $row) {
            $counts[(int) $row['user_id']] = (int) $row['rows_for_user'];
        }

        $missing = [];
        foreach ($students as $student) {
            if (!isset($counts[$student]) || $counts[$student] < 1) {
                $missing[] = $student;
            }
        }

        self::assertSame(
            [],
            $missing,
            'Every legacy enrolment must survive, including the rows that sit on a batch '
            . 'boundary. Learners with no migrated enrolment: ' . implode(', ', $missing)
        );

        /*
         * And the exact count, scoped to the rows the migrator itself wrote.
         *
         * The per-learner check above is robust but too weak on its own: it only
         * notices a learner who ended with *nothing*, so losing one row out of a
         * batch while that learner still had another enrolment would slip past.
         * Verified by dropping every fiftieth row in the migrator — the
         * per-learner assertion did not notice.
         *
         * `payment_method = 'legacy'` is what {@see MigrateEnrollments} stamps on
         * everything it creates, so this counts precisely this fixture's migrated
         * rows and ignores anything else in a shared table.
         */
        $migrated = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$enrollments}
                 WHERE user_id IN ({$placeholders}) AND payment_method = 'legacy'",
                $students
            )
        );

        self::assertSame(
            120,
            $migrated,
            'Exactly the seeded legacy enrolments must have been migrated; a row dropped at a '
            . 'batch boundary shows up here even when the learner has other enrolments.'
        );

        // Cleanup: these users and rows are outside the fixture tearDown.
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$enrollments} WHERE user_id IN ({$placeholders})", $students)
        );
        foreach ($students as $user) {
            wp_delete_user($user);
        }
    }

    public function testStoreSettingsAreCarriedOverToTheNewKeys(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        // Losing these silently re-prices the whole catalogue in the wrong
        // currency and format.
        self::assertSame('EUR', Settings::get('currency'));
        self::assertSame('.', Settings::get('thousand_separator'));
        self::assertSame('shop@example.test', Settings::get('paypal_email'));
    }

    public function testBillingDetailsSurviveTheUpgrade(): void
    {
        LegacyMigrator::run();
        LegacyMigrator::run();

        self::assertSame('Ada', get_user_meta($this->student, '_sikshya_billing_first_name', true));
        self::assertSame('NP', get_user_meta($this->student, '_sikshya_billing_country', true));
        self::assertSame('ada@example.test', get_user_meta($this->student, '_sikshya_billing_email', true));
    }

    public function testRolesAreRestoredEvenWhenActivationNeverRan(): void
    {
        // A dropin / SFTP / WP-CLI upgrade never fires the activation hook, so
        // this step exists to put the roles back. Remove them to prove it does.
        foreach (['sikshya_instructor', 'sikshya_student', 'sikshya_assistant'] as $role) {
            remove_role($role);
        }
        self::assertNull(get_role('sikshya_instructor'), 'Precondition: roles removed.');

        LegacyMigrator::run();

        self::assertNotNull(
            get_role('sikshya_instructor'),
            'The migration must restore the roles an upgrade that skipped activation would be missing.'
        );
        self::assertNotNull(get_role('sikshya_student'));
    }
}
