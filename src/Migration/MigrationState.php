<?php

/**
 * Persistent state for the legacy migration runner.
 *
 * Stores a single `wp_options` row (`sikshya_legacy_migration_state`) that
 * captures: schema version, dry-run flag, started_at/finished_at timestamps,
 * per-step status (`pending` / `running` / `complete` / `failed`), per-step
 * cursors, per-step counts, and the last error message (if any). Calling
 * the same step twice is therefore a no-op once it reaches `complete`.
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrationState
{
    public const OPTION_KEY = 'sikshya_legacy_migration_state';
    public const PENDING_FLAG = 'sikshya_legacy_migration_pending';
    public const SCHEMA_VERSION = 1;

    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Load state from `wp_options`, seeding defaults on first access.
     */
    public static function load(): self
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored) || empty($stored)) {
            $stored = self::defaults();
        } else {
            $stored = array_merge(self::defaults(), $stored);
        }

        return new self($stored);
    }

    /**
     * Persist current state.
     */
    public function save(): void
    {
        $this->data['updated_at'] = current_time('mysql', true);
        update_option(self::OPTION_KEY, $this->data, false);
    }

    public function reset(): void
    {
        $this->data = self::defaults();
        $this->save();
    }

    public function markStarted(bool $dryRun = false): void
    {
        if (empty($this->data['started_at'])) {
            $this->data['started_at'] = current_time('mysql', true);
        }
        $this->data['dry_run'] = $dryRun;
        $this->data['finished_at'] = null;
        $this->data['last_error'] = '';
        $this->save();
    }

    public function markFinished(): void
    {
        $this->data['finished_at'] = current_time('mysql', true);
        $this->save();
    }

    public function markFailed(string $stepId, string $message): void
    {
        $this->data['last_error'] = $message;
        $this->setStepStatus($stepId, 'failed');
        $this->save();
    }

    public function isFinished(): bool
    {
        return !empty($this->data['finished_at']);
    }

    public function isDryRun(): bool
    {
        return !empty($this->data['dry_run']);
    }

    public function startedAt(): string
    {
        return (string) ($this->data['started_at'] ?? '');
    }

    public function finishedAt(): string
    {
        return (string) ($this->data['finished_at'] ?? '');
    }

    public function lastError(): string
    {
        return (string) ($this->data['last_error'] ?? '');
    }

    public function getStepStatus(string $stepId): string
    {
        $steps = (array) ($this->data['steps'] ?? []);
        return (string) ($steps[$stepId]['status'] ?? 'pending');
    }

    public function setStepStatus(string $stepId, string $status): void
    {
        $this->data['steps'][$stepId]['status'] = $status;
    }

    /**
     * Per-step cursor used by chunked steps to remember where they left off.
     */
    public function getStepCursor(string $stepId): int
    {
        return (int) ($this->data['steps'][$stepId]['cursor'] ?? 0);
    }

    public function setStepCursor(string $stepId, int $cursor): void
    {
        $this->data['steps'][$stepId]['cursor'] = max(0, $cursor);
    }

    public function incrementStepCount(string $stepId, string $bucket, int $delta): void
    {
        $current = (int) ($this->data['steps'][$stepId]['counts'][$bucket] ?? 0);
        $this->data['steps'][$stepId]['counts'][$bucket] = $current + $delta;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function steps(): array
    {
        return (array) ($this->data['steps'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'dry_run' => false,
            'last_error' => '',
            'steps' => [],
        ];
    }
}
