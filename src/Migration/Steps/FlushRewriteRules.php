<?php

/**
 * Final cleanup step that flushes the WordPress rewrite-rule cache. The
 * post-type and taxonomy renames performed earlier in the run change the
 * permalinks the new plugin needs to register, so this guarantees the
 * site doesn't 404 on freshly migrated archive URLs.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class FlushRewriteRules extends AbstractStep
{
    public function id(): string
    {
        return 'flush_rewrites';
    }

    public function description(): string
    {
        return __('Flush rewrite rules so renamed slugs resolve.', 'sikshya');
    }

    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int {
        $this->markRunning($state);

        if ($dryRun) {
            $logger->info('[dry-run] Would call flush_rewrite_rules().');
        } else {
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules(false);
                $logger->info('flush_rewrite_rules() invoked.');
                $state->incrementStepCount($this->id(), 'flushed', 1);
            }
        }

        $this->markComplete($state);
        return 1;
    }
}
