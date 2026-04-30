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
            $logger->info('[dry-run] Would invoke Installer::installRoles() to ensure roles exist.');
            $this->markComplete($state);
            return 0;
        }

        // Installer keeps the role grants idempotent — `add_role` is a no-op
        // on existing roles, and admin caps are added via `add_cap` which is
        // also idempotent. We invoke the public install entry point via
        // reflection of the private method to avoid duplicating the cap list.
        if (class_exists(Installer::class) && method_exists(Installer::class, 'install')) {
            try {
                $reflection = new \ReflectionClass(Installer::class);
                if ($reflection->hasMethod('installRoles')) {
                    $method = $reflection->getMethod('installRoles');
                    $method->setAccessible(true);
                    $method->invoke(null);
                    $state->incrementStepCount($this->id(), 'roles_synced', 3);
                    $logger->info('Installer::installRoles() completed.');
                }
            } catch (\Throwable $e) {
                $logger->warning('Could not invoke Installer::installRoles(): ' . $e->getMessage());
                // Fall back to defensive add_role calls so subsequent steps
                // depending on the roles continue to work.
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
