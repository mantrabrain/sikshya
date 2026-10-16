<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\SampleDataImportService;
use WP_UnitTestCase;

/**
 * Importing the sample pack twice.
 *
 * The importer had no record of what it had already done. Pressing the Tools
 * button twice, or running the setup wizard after a first attempt, gave the
 * site a second copy of every sample course. Worse, the pack writes a few
 * hundred posts and copies an image per course, so a shared host's 30-second
 * limit could cut a run off part-way — and the retry duplicated the courses it
 * had already made instead of finishing the ones it had not.
 *
 * These tests drive the service directly with small in-memory packs, because
 * what is under test is the bookkeeping, not the shipped pack's contents.
 */
final class SampleImportIdempotenceTest extends WP_UnitTestCase
{
    private function service(): SampleDataImportService
    {
        $plugin = \Sikshya\Core\Plugin::getInstance();
        $svc = $plugin->getService('sampleDataImport');
        self::assertInstanceOf(SampleDataImportService::class, $svc);

        return $svc;
    }

    /**
     * @return array<string, mixed>
     */
    private function pack(string $slug = 'pack-course-a', string $title = 'Pack course A'): array
    {
        return [
            'version' => 1,
            'courses' => [
                [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => '<p>Sample.</p>',
                    'status' => 'publish',
                    'chapters' => [
                        [
                            'title' => 'Chapter one',
                            'contents' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function coursesTitled(string $title): int
    {
        return count(get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            's' => $title,
            'suppress_filters' => true,
        ]));
    }

    /**
     * @return int[]
     */
    private function importedIds(string $source): array
    {
        return array_map('intval', get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => SampleDataImportService::META_SOURCE,
            'meta_value' => $source,
            'suppress_filters' => true,
        ]));
    }

    public function testAFirstImportCreatesTheCourse(): void
    {
        $result = $this->service()->importPack($this->pack());

        self::assertTrue((bool) $result['success'], (string) ($result['message'] ?? ''));
        self::assertSame(1, (int) $result['counts']['courses']);
        self::assertCount(1, $this->importedIds('pack-course-a'));
    }

    public function testImportedCoursesCarryTheirSourceMarker(): void
    {
        $this->service()->importPack($this->pack());

        $ids = $this->importedIds('pack-course-a');
        self::assertCount(1, $ids);

        // The marker is what lets a later run tell its own work apart from a
        // course somebody wrote by hand.
        self::assertSame(
            'done',
            (string) get_post_meta($ids[0], SampleDataImportService::META_SOURCE_STATE, true),
            'A finished import must be marked finished, or the next run treats it as interrupted.'
        );
    }

    public function testASecondImportCreatesNoDuplicate(): void
    {
        $this->service()->importPack($this->pack());
        $second = $this->service()->importPack($this->pack());

        self::assertCount(
            1,
            $this->importedIds('pack-course-a'),
            'Importing the same pack twice must not give the site two copies of the course.'
        );
        self::assertSame(0, (int) $second['counts']['courses'], 'The second run creates nothing.');
        self::assertSame(1, (int) $second['counts']['skipped'], 'And says what it skipped.');
    }

    public function testASecondImportStillReportsSuccess(): void
    {
        $this->service()->importPack($this->pack());
        $second = $this->service()->importPack($this->pack());

        // "Nothing to do" is not a failure. Reporting one would send an admin
        // looking for a problem that is not there.
        self::assertTrue(
            (bool) $second['success'],
            'A run that finds everything already imported must not report failure.'
        );
    }

    public function testAnInterruptedImportIsRebuiltRatherThanDuplicated(): void
    {
        $this->service()->importPack($this->pack());

        $ids = $this->importedIds('pack-course-a');
        self::assertCount(1, $ids);
        $first_id = $ids[0];

        // Simulate the host killing the first run before it finished: the
        // course exists and carries the marker, but was never marked done.
        update_post_meta($first_id, SampleDataImportService::META_SOURCE_STATE, 'importing');

        $second = $this->service()->importPack($this->pack());

        $after = $this->importedIds('pack-course-a');
        self::assertCount(
            1,
            $after,
            'A retry after an interrupted run must leave one course, not a half-course beside a new one.'
        );
        self::assertNotSame(
            [$first_id],
            $after,
            'The unfinished course must be rebuilt, not adopted — its curriculum may be incomplete.'
        );
        self::assertSame(1, (int) $second['counts']['courses']);
        self::assertSame(
            'done',
            (string) get_post_meta($after[0], SampleDataImportService::META_SOURCE_STATE, true)
        );
    }

    public function testACourseWrittenByHandIsNeverTouched(): void
    {
        $mine = (int) self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'My own course',
            'post_name' => 'pack-course-a',
        ]);

        // Same slug as the pack course, but no importer marker: the importer
        // must not claim it, and above all must not delete it.
        $this->service()->importPack($this->pack());

        self::assertInstanceOf(
            \WP_Post::class,
            get_post($mine),
            'A course the importer did not create must survive an import untouched.'
        );
        self::assertSame('My own course', (string) get_post($mine)->post_title);
    }

    public function testTwoDifferentPackCoursesBothImport(): void
    {
        $this->service()->importPack($this->pack('pack-course-a', 'Pack course A'));
        $this->service()->importPack($this->pack('pack-course-b', 'Pack course B'));

        self::assertCount(1, $this->importedIds('pack-course-a'));
        self::assertCount(
            1,
            $this->importedIds('pack-course-b'),
            'Skipping must be per course, not "this pack has been imported before".'
        );
    }

    public function testACourseWithNoChaptersIsStillMarkedFinished(): void
    {
        $pack = $this->pack('pack-course-c', 'Pack course C');
        unset($pack['courses'][0]['chapters']);

        $this->service()->importPack($pack);
        $ids = $this->importedIds('pack-course-c');
        self::assertCount(1, $ids);

        // Left unmarked, every later run would delete and rebuild it forever.
        self::assertSame(
            'done',
            (string) get_post_meta($ids[0], SampleDataImportService::META_SOURCE_STATE, true),
            'A course with no curriculum is complete, and must be marked so.'
        );

        $this->service()->importPack($pack);
        self::assertCount(1, $this->importedIds('pack-course-c'));
    }
}
