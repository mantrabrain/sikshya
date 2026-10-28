<?php

declare(strict_types=1);

namespace Sikshya\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI bridge for importing from another LMS.
 *
 * Usage:
 *
 *   wp sikshya import --list                      # what is on this site
 *   wp sikshya import --source=tutor --dry-run    # count, create nothing
 *   wp sikshya import --source=tutor              # run until finished
 *   wp sikshya import --source=tutor --batch=1    # one batch at a time
 *   wp sikshya import --source=tutor --reset      # forget progress, keep the map
 *
 * The command exists because the browser is the wrong place to migrate a large
 * library: a site with hundreds of courses is minutes of work, and an
 * administrator who closes the tab should not have to think about what that
 * did. Over CLI there is no request to time out and no tab to close.
 *
 * @package Sikshya\Import
 */
final class ImportCli
{
    public static function register(): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('sikshya import', [self::class, 'command']);
    }

    /**
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     */
    public static function command(array $args, array $assoc_args): void
    {
        unset($args);

        if (!class_exists('WP_CLI')) {
            return;
        }

        if (!empty($assoc_args['list'])) {
            foreach (ImportRunner::available() as $source) {
                \WP_CLI::log(sprintf(
                    '%s (%s): %s',
                    (string) $source['label'],
                    (string) $source['id'],
                    $source['detected'] ? self::describe((array) $source['counts']) : 'nothing found'
                ));
            }

            return;
        }

        $sourceId = isset($assoc_args['source']) ? (string) $assoc_args['source'] : '';
        if ($sourceId === '') {
            \WP_CLI::error('Pass --source=<id>, or --list to see what is available.');

            return;
        }

        $dryRun = !empty($assoc_args['dry-run']);

        if (!empty($assoc_args['reset'])) {
            ImportRunner::resetProgress($sourceId, $dryRun);
            \WP_CLI::success('Progress cleared. Nothing already imported was duplicated or removed.');

            return;
        }

        $batchSize = isset($assoc_args['batch']) ? (int) $assoc_args['batch'] : 0;
        $single = isset($assoc_args['batch']);

        $totals = [];
        $notes = [];

        // No ceiling on the number of batches here, unlike the admin screen:
        // there is no request to keep alive, and an operator who wants one
        // batch asks for one with --batch.
        while (true) {
            $result = ImportRunner::runBatch($sourceId, $dryRun, $batchSize);

            if (empty($result['ok'])) {
                \WP_CLI::error((string) ($result['message'] ?? 'The import could not run.'));

                return;
            }

            $totals = (array) $result['totals'];
            $notes = array_values(array_unique(array_merge($notes, (array) $result['notes'])));

            \WP_CLI::log(sprintf(
                '%d course(s) this batch%s',
                (int) $result['courses_this_batch'],
                $result['finished'] ? ' — finished' : ''
            ));

            if (!empty($result['finished']) || $single) {
                break;
            }
        }

        foreach ($notes as $note) {
            \WP_CLI::warning($note);
        }

        \WP_CLI::success(sprintf(
            '%s: %s',
            $dryRun ? 'Preview (nothing created)' : 'Imported',
            self::describe($totals)
        ));
    }

    /** @param array<string, int|string> $counts */
    private static function describe(array $counts): string
    {
        if ($counts === []) {
            return 'nothing';
        }

        $parts = [];
        foreach ($counts as $kind => $n) {
            $parts[] = $n . ' ' . $kind;
        }

        return implode(', ', $parts);
    }
}
