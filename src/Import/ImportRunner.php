<?php

declare(strict_types=1);

namespace Sikshya\Import;

use Sikshya\Import\Sources\TutorSource;
use Sikshya\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Runs an import a few courses at a time.
 *
 * Never "import everything now". A course with twenty sections, two hundred
 * lessons and a thousand enrolment rows is minutes of work on shared hosting,
 * and a site with four hundred such courses cannot do it inside one request
 * at any host. So a run carries a count and a wall-clock budget, remembers
 * where it got to, and is called again — from the admin screen, which polls,
 * or from WP-CLI.
 *
 * The cursor is the source course id, not an offset. An offset shifts when the
 * source changes underneath a long-running import and silently skips courses;
 * `WHERE ID > cursor` cannot.
 *
 * @package Sikshya\Import
 */
final class ImportRunner
{
    public const OPT_STATE = 'sikshya_import_state';

    /** Courses per batch. A course is the unit, because its children must land with it. */
    public const DEFAULT_BATCH = 3;

    /** Seconds a batch may spend, deliberately under a conservative host's limit. */
    public const DEFAULT_TIME_BUDGET = 20.0;

    /** @return array<int, ImportSourceInterface> */
    public static function sources(): array
    {
        /**
         * The LMSs Sikshya can import from.
         *
         * @param array<int, ImportSourceInterface> $sources
         */
        return (array) apply_filters('sikshya_import_sources', [new TutorSource()]);
    }

    public static function source(string $id): ?ImportSourceInterface
    {
        foreach (self::sources() as $source) {
            if ($source->id() === sanitize_key($id)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * What is available to import, per source.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function available(): array
    {
        // The screen that calls this only reads, but it reads from a table
        // that may never have been created — on a site that has never
        // imported anything, which is every site the first time.
        ImportMapTable::ensure();

        $out = [];
        foreach (self::sources() as $source) {
            $detected = $source->detect();
            $out[] = [
                'id' => $source->id(),
                'label' => $source->label(),
                'detected' => $detected,
                'counts' => $detected ? $source->counts() : [],
                'imported' => (new ImportMap($source->id()))->counts(),
            ];
        }

        return $out;
    }

    /**
     * Run one batch.
     *
     * @return array{
     *   ok: bool,
     *   source: string,
     *   dry_run: bool,
     *   finished: bool,
     *   cursor: int,
     *   courses_this_batch: int,
     *   tally: array<string, int>,
     *   totals: array<string, int>,
     *   notes: array<int, string>,
     *   message?: string
     * }
     */
    public static function runBatch(string $sourceId, bool $dryRun = false, int $batchSize = 0): array
    {
        $source = self::source($sourceId);
        if ($source === null) {
            return self::failure($sourceId, $dryRun, __('Unknown import source.', 'sikshya'));
        }

        if (!$source->detect()) {
            return self::failure($sourceId, $dryRun, __('No data from that LMS was found on this site.', 'sikshya'));
        }

        ImportMapTable::ensure();

        $state = self::state();
        $key = $source->id() . ($dryRun ? ':dry' : '');
        $cursor = (int) ($state[$key]['cursor'] ?? 0);
        $totals = is_array($state[$key]['totals'] ?? null) ? $state[$key]['totals'] : [];
        $runId = (string) ($state[$key]['run_id'] ?? '');
        if ($runId === '') {
            $runId = function_exists('wp_generate_uuid4') ? (string) wp_generate_uuid4() : (string) time();
        }

        $batchSize = $batchSize > 0 ? min(50, $batchSize) : (int) apply_filters('sikshya_import_batch_size', self::DEFAULT_BATCH);
        $budget = (float) apply_filters('sikshya_import_time_budget', self::DEFAULT_TIME_BUDGET);

        $writer = new CourseWriter(new ImportMap($source->id()), $runId, $dryRun);
        $startedAt = microtime(true);
        $done = 0;

        foreach ($source->courseIds($cursor, $batchSize) as $courseId) {
            /*
             * Stop when the batch has spent its time, but never before the
             * first course.
             *
             * The `$done > 0` guard is a safety net rather than the main
             * protection: the clock starts immediately above this loop, so the
             * first check is always ~0 seconds in and only a pathologically
             * small budget could trip it. What actually keeps a slow host
             * making progress is that the batch size is small and the cursor
             * is persisted, so each request does a little and the next one
             * continues.
             */
            if ($done > 0 && $budget > 0 && (microtime(true) - $startedAt) >= $budget) {
                break;
            }

            $course = $source->readCourse($courseId);
            if ($course !== null) {
                $writer->write($course);
            }

            // The cursor is only persisted once, after the loop, so a batch
            // killed part-way is redone from where the *previous* batch
            // finished. That is the safe direction: redoing a course is an
            // update, because of the map, while skipping one loses it
            // silently — the kind of failure nobody notices until a learner
            // does.
            $cursor = $courseId;
            $done++;
        }

        $tally = $writer->tally();
        foreach ($tally as $bucket => $n) {
            $totals[$bucket] = (int) ($totals[$bucket] ?? 0) + (int) $n;
        }

        $finished = $source->courseIds($cursor, 1) === [];

        $state[$key] = [
            'cursor' => $cursor,
            'totals' => $totals,
            'run_id' => $runId,
            'finished' => $finished,
            'updated_at' => current_time('mysql', true),
        ];
        self::saveState($state);

        return [
            'ok' => true,
            'source' => $source->id(),
            'dry_run' => $dryRun,
            'finished' => $finished,
            'cursor' => $cursor,
            'courses_this_batch' => $done,
            'tally' => $tally,
            'totals' => $totals,
            'notes' => $writer->notes(),
        ];
    }

    /**
     * Forget where a run got to, so the next one starts from the beginning.
     *
     * Deliberately does **not** delete the mapping table: starting over must
     * mean "look at every course again", not "make a second copy of every
     * course". The map is what keeps a restart idempotent.
     */
    public static function resetProgress(string $sourceId, bool $dryRun = false): void
    {
        $state = self::state();
        unset($state[sanitize_key($sourceId) . ($dryRun ? ':dry' : '')]);
        self::saveState($state);
    }

    /** @return array<string, mixed> */
    public static function state(): array
    {
        $state = Settings::getRaw(self::OPT_STATE, []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string, mixed> $state */
    private static function saveState(array $state): void
    {
        Settings::setRaw(self::OPT_STATE, $state);
    }

    /**
     * @return array{
     *   ok: bool, source: string, dry_run: bool, finished: bool, cursor: int,
     *   courses_this_batch: int, tally: array<string, int>, totals: array<string, int>,
     *   notes: array<int, string>, message: string
     * }
     */
    private static function failure(string $sourceId, bool $dryRun, string $message): array
    {
        return [
            'ok' => false,
            'source' => sanitize_key($sourceId),
            'dry_run' => $dryRun,
            'finished' => false,
            'cursor' => 0,
            'courses_this_batch' => 0,
            'tally' => [],
            'totals' => [],
            'notes' => [],
            'message' => $message,
        ];
    }
}
