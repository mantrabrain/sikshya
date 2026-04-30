<?php

/**
 * WP-CLI bridge for the legacy migration runner.
 *
 * Usage:
 *
 *   wp sikshya migrate-legacy            # run one batch (will resume on its own)
 *   wp sikshya migrate-legacy --dry-run  # log changes without writing
 *   wp sikshya migrate-legacy --reset    # clear state
 *   wp sikshya migrate-legacy --status   # show current status snapshot
 *   wp sikshya migrate-legacy --all      # loop until done (no time budget)
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyMigrationCli
{
    public static function register(): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }
        \WP_CLI::add_command('sikshya migrate-legacy', [self::class, 'command']);
    }

    /**
     * @param string[] $args
     * @param array<string, mixed> $assoc_args
     */
    public static function command(array $args, array $assoc_args): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }

        if (!empty($assoc_args['reset'])) {
            LegacyMigrator::reset();
            \WP_CLI::success('Migration state reset.');
            return;
        }

        if (!empty($assoc_args['status'])) {
            self::printStatus();
            return;
        }

        $dry_run = !empty($assoc_args['dry-run']);
        $loop = !empty($assoc_args['all']);

        if ($loop) {
            $iterations = 0;
            $max_iterations = (int) ($assoc_args['max-iterations'] ?? 200);
            while ($iterations < $max_iterations) {
                $state = LegacyMigrator::run($dry_run);
                if ($state->isFinished()) {
                    break;
                }
                $iterations++;
                \WP_CLI::log(sprintf('Batch %d completed; resuming.', $iterations));
                if (!LegacyMigrator::isPending() && !$state->isFinished()) {
                    \WP_CLI::warning('Migration paused but pending flag is gone. Aborting loop.');
                    break;
                }
            }
        } else {
            LegacyMigrator::run($dry_run);
        }

        self::printStatus();

        $state = LegacyMigrator::status();
        if ($state->isFinished()) {
            \WP_CLI::success('Migration finished.');
        } elseif ($state->lastError() !== '') {
            \WP_CLI::error('Migration failed: ' . $state->lastError(), false);
        } else {
            \WP_CLI::log('Migration paused; run again or pass --all to loop.');
        }
    }

    private static function printStatus(): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }
        $state = LegacyMigrator::status();
        $rows = [];
        foreach (LegacyMigrator::steps() as $step) {
            $steps = $state->steps();
            $counts = isset($steps[$step->id()]['counts']) && is_array($steps[$step->id()]['counts'])
                ? $steps[$step->id()]['counts']
                : [];
            $rows[] = [
                'step' => $step->id(),
                'status' => $state->getStepStatus($step->id()),
                'cursor' => $state->getStepCursor($step->id()),
                'counts' => wp_json_encode($counts),
            ];
        }
        \WP_CLI\Utils\format_items('table', $rows, ['step', 'status', 'cursor', 'counts']);

        \WP_CLI::log(sprintf('Started at:  %s', $state->startedAt() ?: '—'));
        \WP_CLI::log(sprintf('Finished at: %s', $state->finishedAt() ?: '—'));
        \WP_CLI::log(sprintf('Last error:  %s', $state->lastError() ?: '—'));
    }
}
