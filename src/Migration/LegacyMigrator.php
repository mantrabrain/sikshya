<?php

/**
 * Orchestrator for the legacy `sikshya-old` -> rewrite migration.
 *
 * Public surface:
 *
 *   - `LegacyMigrator::scheduleIfPending()`   — called from the activation hook;
 *                                               sets the pending flag if legacy
 *                                               data is present.
 *   - `LegacyMigrator::register()`            — wires `plugins_loaded` and the
 *                                               admin notice / Tools page / CLI.
 *   - `LegacyMigrator::run([dry_run])`        — execute one batched chunk.
 *   - `LegacyMigrator::dryRun()`              — dry-run convenience wrapper.
 *   - `LegacyMigrator::status()`              — read-only state snapshot.
 *   - `LegacyMigrator::reset()`               — clear state for support cases.
 *   - `LegacyMigrator::isPending()` / `isComplete()`.
 *
 * The whole `Migration/` directory is gated by `class_exists` from the
 * plugin bootstrap so deleting it makes the migrator a clean no-op.
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

use Sikshya\Migration\Steps\FlushRewriteRules;
use Sikshya\Migration\Steps\MigrateEnrollments;
use Sikshya\Migration\Steps\MigrateOrders;
use Sikshya\Migration\Steps\MigratePostMeta;
use Sikshya\Migration\Steps\MigratePostTypes;
use Sikshya\Migration\Steps\MigrateProgress;
use Sikshya\Migration\Steps\MigrateRolesAndCapabilities;
use Sikshya\Migration\Steps\MigrateSectionsToChapters;
use Sikshya\Migration\Steps\MigrateSettings;
use Sikshya\Migration\Steps\MigrateTaxonomies;
use Sikshya\Migration\Steps\MigrateUserMeta;
use Sikshya\Migration\Steps\MirrorCourseAliases;
use Sikshya\Migration\Steps\RebuildChapterContents;
use Sikshya\Migration\Steps\RebuildQuizQuestions;
use Sikshya\Migration\Steps\StepInterface;
use Sikshya\Migration\Steps\TransformQuestions;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyMigrator
{
    public const CRON_HOOK = 'sikshya_run_legacy_migration_batch';
    public const NONCE_ACTION = 'sikshya_legacy_migration';

    /**
     * Default per-step batch size. Tuned so a single run completes well
     * under the 10-second request budget on shared hosting.
     */
    public const BATCH_SIZE = 50;

    /**
     * Transient that caches the result of {@see LegacyDataDetector::hasLegacyData()}
     * during defensive detection so we don't run a fingerprint scan on every
     * single admin page load forever.
     */
    private const DETECTION_TRANSIENT = 'sikshya_legacy_migration_detected_v2';

    /** TTL in seconds for the detection transient (1 hour). */
    private const DETECTION_TRANSIENT_TTL = 3600;

    private static bool $registered = false;

    /**
     * Register all hooks.  Called from the plugin bootstrap (`Plugin::init`)
     * exactly once per request.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('plugins_loaded', [self::class, 'maybeRunOnLoad'], 20);
        add_action(self::CRON_HOOK, [self::class, 'runScheduledBatch']);

        if (is_admin()) {
            LegacyMigrationAdminNotice::register();
        }

        if (defined('WP_CLI') && WP_CLI && class_exists(LegacyMigrationCli::class)) {
            LegacyMigrationCli::register();
        }
    }

    /**
     * Activation-hook entry point. If the site has legacy data, set the
     * pending flag so the next page load picks the migration up.
     */
    public static function scheduleIfPending(): void
    {
        if (!LegacyDataDetector::hasLegacyData()) {
            return;
        }
        update_option(MigrationState::PENDING_FLAG, 'yes', false);

        $state = MigrationState::load();
        if (!$state->isFinished()) {
            $state->markStarted(false);
        }

        if (function_exists('wp_schedule_single_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }
    }

    /**
     * `plugins_loaded` listener — runs one batched chunk if the migration
     * is pending and we're not inside a problematic context (REST, AJAX,
     * cron, CLI).
     *
     * Defensive detection: when no pending flag is set but the migration
     * isn't finished, we cheaply test whether legacy data is present (cached
     * with a short transient so this is not a per-request hit). This catches
     * sites that updated the plugin via dropin / WP-CLI / SFTP without
     * firing the activation hook.
     */
    public static function maybeRunOnLoad(): void
    {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            // Cron + CLI handle their own runs; AJAX/REST shouldn't block.
            return;
        }

        if (!self::isPending()) {
            // Already finished? Nothing to do — and don't waste cycles
            // detecting on every page load forever.
            if (self::isComplete()) {
                return;
            }
            // Defensive detection (cheap, transient-cached).
            if (!self::detectAndSchedule()) {
                return;
            }
        }

        try {
            self::run(false);
        } catch (\Throwable $e) {
            (new LegacyMigrationLogger())->error('Migration aborted: ' . $e->getMessage());
        }
    }

    /**
     * Cheap defensive detection used by {@see maybeRunOnLoad}. Runs at most
     * once per `DETECTION_TRANSIENT_TTL` seconds per request, since the
     * legacy fingerprint can't change in the middle of a session.
     */
    private static function detectAndSchedule(): bool
    {
        $cached = get_transient(self::DETECTION_TRANSIENT);
        if ($cached === 'no') {
            return false;
        }
        if ($cached !== 'yes') {
            $has_legacy = LegacyDataDetector::hasLegacyData();
            set_transient(self::DETECTION_TRANSIENT, $has_legacy ? 'yes' : 'no', self::DETECTION_TRANSIENT_TTL);
            if (!$has_legacy) {
                return false;
            }
        }

        update_option(MigrationState::PENDING_FLAG, 'yes', false);
        $state = MigrationState::load();
        if (!$state->isFinished()) {
            $state->markStarted(false);
        }
        return true;
    }

    /**
     * Cron callback. Runs one batch and re-schedules itself if more work
     * remains.
     */
    public static function runScheduledBatch(): void
    {
        if (!self::isPending()) {
            return;
        }
        try {
            self::run(false);
        } catch (\Throwable $e) {
            (new LegacyMigrationLogger())->error('Cron batch aborted: ' . $e->getMessage());
        }
    }

    /**
     * Run a single batched chunk. Returns the migration state after the
     * run.
     */
    public static function run(bool $dryRun = false): MigrationState
    {
        $state = MigrationState::load();
        $logger = new LegacyMigrationLogger();

        $state->markStarted($dryRun);
        $logger->info('Migration run started.', [
            'dry_run' => $dryRun,
            'steps_total' => count(self::steps()),
        ]);

        $start_time = microtime(true);
        $time_budget_seconds = (float) apply_filters('sikshya_legacy_migration_time_budget', 8.0);
        $batch_size = (int) apply_filters('sikshya_legacy_migration_batch_size', self::BATCH_SIZE);
        $any_remaining = false;

        foreach (self::steps() as $step) {
            if ($step->isComplete($state)) {
                continue;
            }
            try {
                $logger->info(sprintf('Running step: %s — %s', $step->id(), $step->description()));
                $processed = $step->executeBatch($state, $logger, $batch_size, $dryRun);
                $logger->info(sprintf('Step %s processed %d items.', $step->id(), $processed));
            } catch (\Throwable $e) {
                $logger->error(sprintf(
                    'Step %s failed: %s',
                    $step->id(),
                    $e->getMessage()
                ), ['trace' => $e->getTraceAsString()]);
                $state->markFailed($step->id(), $e->getMessage());
                return $state;
            }

            if (!$step->isComplete($state)) {
                $any_remaining = true;
                if ((microtime(true) - $start_time) > $time_budget_seconds) {
                    break;
                }
            }
        }

        // Were any steps still incomplete at the end of the loop? (We exit
        // early via `break` once the time budget is exhausted.)
        if (!$any_remaining) {
            foreach (self::steps() as $step) {
                if (!$step->isComplete($state)) {
                    $any_remaining = true;
                    break;
                }
            }
        }

        if ($any_remaining) {
            self::scheduleNextBatch();
            $logger->info('Migration paused; next batch scheduled.');
        } else {
            $state->markFinished();
            delete_option(MigrationState::PENDING_FLAG);
            $logger->info('Migration finished cleanly.', $state->toArray());
            do_action('sikshya_legacy_migration_completed', $state->toArray());
        }

        return $state;
    }

    public static function dryRun(): MigrationState
    {
        return self::run(true);
    }

    public static function status(): MigrationState
    {
        return MigrationState::load();
    }

    public static function reset(): void
    {
        $state = MigrationState::load();
        $state->reset();
        delete_option(MigrationState::PENDING_FLAG);
        delete_transient(self::DETECTION_TRANSIENT);
    }

    public static function isPending(): bool
    {
        $flag = get_option(MigrationState::PENDING_FLAG, '');
        return $flag === 'yes' || $flag === '1' || $flag === 1 || $flag === true;
    }

    public static function isComplete(): bool
    {
        return MigrationState::load()->isFinished();
    }

    /**
     * Ordered list of steps.  Order matters: post types must be renamed
     * before post-meta keys (so the meta step can scope by the new
     * post_type), enrollments rely on courses already existing under the
     * new slugs, etc.
     *
     * @return StepInterface[]
     */
    public static function steps(): array
    {
        return [
            new MigrateRolesAndCapabilities(),
            new MigratePostTypes(),
            new MigrateSectionsToChapters(),
            new MigrateTaxonomies(),
            new MigratePostMeta(),
            // RebuildQuizQuestions reads the legacy `quiz_id` post-meta on
            // each question, so it must run BEFORE TransformQuestions
            // deletes the legacy keys.
            new RebuildQuizQuestions(),
            new TransformQuestions(),
            new RebuildChapterContents(),
            new MirrorCourseAliases(),
            new MigrateEnrollments(),
            new MigrateProgress(),
            new MigrateOrders(),
            new MigrateSettings(),
            new MigrateUserMeta(),
            new FlushRewriteRules(),
        ];
    }

    private static function scheduleNextBatch(): void
    {
        if (!function_exists('wp_schedule_single_event')) {
            return;
        }
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }
        wp_schedule_single_event(time() + 30, self::CRON_HOOK);
    }
}
