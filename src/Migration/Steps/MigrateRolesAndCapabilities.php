<?php

/**
 * Defensive role + capability install. The new plugin's `Installer` adds
 * the `sikshya_instructor` / `sikshya_student` / `sikshya_assistant` roles
 * on activation, but a site that arrived here without the activation hook
 * firing (dropin upgrades, manual SQL migration, etc.) needs these roles
 * present before any later step relies on them.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Core\Installer;
use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateRolesAndCapabilities extends AbstractStep
{
    public function id(): string
    {
        return 'roles_and_capabilities';
    }

    public function description(): string
    {
        return __('Ensure Sikshya roles and capabilities are present.', 'sikshya');
    }

    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int {
        $this->markRunning($state);

        if ($dryRun) {
            $logger->info('[dry-run] Would invoke Installer::syncSikshyaRoleCapabilities() to ensure roles exist.');
            $this->markComplete($state);
            return 0;
        }

        if (class_exists(Installer::class)) {
            try {
                Installer::syncSikshyaRoleCapabilities();
                $state->incrementStepCount($this->id(), 'roles_synced', 3);
                $logger->info('Installer::syncSikshyaRoleCapabilities() completed.');
            } catch (\Throwable $e) {
                $logger->warning('Could not invoke Installer::syncSikshyaRoleCapabilities(): ' . $e->getMessage());
                $this->fallbackRoles();
            }
        } else {
            $this->fallbackRoles();
        }

        $this->markComplete($state);
        return 1;
    }

    private function fallbackRoles(): void
    {
        if (!function_exists('add_role')) {
            return;
        }
        if (!get_role('sikshya_instructor')) {
            add_role('sikshya_instructor', __('Instructor', 'sikshya'), [
                'read' => true,
                'edit_posts' => true,
                'upload_files' => true,
            ]);
        }
        if (!get_role('sikshya_student')) {
            add_role('sikshya_student', __('Student', 'sikshya'), [
                'read' => true,
            ]);
        }
    }
}
