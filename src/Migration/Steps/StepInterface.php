<?php

/**
 * Common interface implemented by every legacy migration step.
 *
 * Steps are intentionally narrow: they each own one logical entity
 * (course meta, enrollments, taxonomies, etc.) so they can be re-run
 * independently and so failures stay contained. The orchestrator iterates
 * `Steps::all()`, persisting cursor + status into {@see MigrationState}.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

interface StepInterface
{
    /**
     * Stable identifier persisted in `MigrationState` (`courses_post_type`,
     * `enrollments`, …). Must remain identical across releases so
     * resumption keeps working.
     */
    public function id(): string;

    /**
     * Short human description rendered in the admin UI / WP-CLI output.
     */
    public function description(): string;

    /**
     * Cheap pre-flight count of remaining items. May return `null` when an
     * exact count is too expensive to compute.
     */
    public function expectedItemCount(): ?int;

    /**
     * Already finished? Used by the orchestrator to skip done steps without
     * touching the database.
     */
    public function isComplete(MigrationState $state): bool;

    /**
     * Process one batch of work. Must update the state's cursor + counters
     * and return the number of items actually processed in this call. A
     * return value of `0` signals "nothing left to do for this step".
     *
     * @param MigrationState           $state   Mutable state object.
     * @param LegacyMigrationLogger    $logger  For step-local logging.
     * @param int                      $batchSize Maximum items to process.
     * @param bool                     $dryRun  When true, do not write — log only.
     */
    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int;
}
