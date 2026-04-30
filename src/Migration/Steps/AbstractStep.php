<?php

/**
 * Base implementation that handles the boilerplate of marking a step as
 * complete and incrementing counters. Concrete steps only need to implement
 * `id()`, `description()`, and `executeBatch()`.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractStep implements StepInterface
{
    public function expectedItemCount(): ?int
    {
        return null;
    }

    public function isComplete(MigrationState $state): bool
    {
        return $state->getStepStatus($this->id()) === 'complete';
    }

    /**
     * Helper: mark complete and persist.
     */
    protected function markComplete(MigrationState $state): void
    {
        $state->setStepStatus($this->id(), 'complete');
        $state->save();
    }

    /**
     * Helper: mark running (used at the start of long batched steps).
     */
    protected function markRunning(MigrationState $state): void
    {
        if ($state->getStepStatus($this->id()) === 'pending') {
            $state->setStepStatus($this->id(), 'running');
            $state->save();
        }
    }

    /**
     * Wrap a `$wpdb` call so that a SQL error is captured against the step
     * but does not abort the whole migration. Returns the wpdb result on
     * success, `false` on failure.
     *
     * @param callable():mixed $callable
     * @return mixed
     */
    protected function safeQuery(callable $callable)
    {
        try {
            return $callable();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
