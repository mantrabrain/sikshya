<?php

/**
 * Admin notice + Tools page for the legacy migration runner.
 *
 * Notices:
 *
 *   - Pending: shows "Sikshya legacy data detected" with a "Run migration"
 *     CTA linking to the Tools page.
 *   - Failed: shows the captured error message and a "Retry" CTA.
 *   - Completed: shows a one-time success summary that can be dismissed.
 *
 * Tools page (registered under `tools.php?page=sikshya-legacy-migration`)
 * exposes Run / Dry-run / Reset buttons and a per-step status table.
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyMigrationAdminNotice
{
    public const PAGE_SLUG = 'sikshya-legacy-migration';
    private const DISMISSED_OPTION = 'sikshya_legacy_migration_notice_dismissed';

    /** One-time: clears mistaken "completed" banner after table-only false-positive migrations. */
    private const SPUROUS_NOTICE_FLAG = 'sikshya_legacy_spurious_completion_notice_cleared';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('admin_menu', [self::class, 'registerToolsPage']);
        add_action('admin_init', [self::class, 'maybeAutoDismissSpuriousCompletionNotice'], 5);
        add_action('admin_notices', [self::class, 'renderNotice']);
        add_action('admin_post_sikshya_legacy_migration_run', [self::class, 'handleRun']);
        add_action('admin_post_sikshya_legacy_migration_dry_run', [self::class, 'handleDryRun']);
        add_action('admin_post_sikshya_legacy_migration_reset', [self::class, 'handleReset']);
        add_action('admin_post_sikshya_legacy_migration_dismiss', [self::class, 'handleDismiss']);
    }

    public static function registerToolsPage(): void
    {
        add_management_page(
            __('Sikshya Legacy Migration', 'sikshya'),
            __('Sikshya Migration', 'sikshya'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderToolsPage']
        );
    }

    /**
     * If migration is "finished" but no real legacy steps ran (only roles bootstrap), treat as a
     * no-op and dismiss the success notice once — fixes fresh installs that matched legacy tables only.
     */
    public static function maybeAutoDismissSpuriousCompletionNotice(): void
    {
        if (get_option(self::SPUROUS_NOTICE_FLAG, '') === '1') {
            return;
        }

        $state = LegacyMigrator::status();
        if (!$state->isFinished() || LegacyMigrator::isPending()) {
            update_option(self::SPUROUS_NOTICE_FLAG, '1', false);
            return;
        }

        if (get_option(self::DISMISSED_OPTION, '') === '1') {
            update_option(self::SPUROUS_NOTICE_FLAG, '1', false);
            return;
        }

        if ($state->hadRealMigrationWorkBeyondRoles()) {
            update_option(self::SPUROUS_NOTICE_FLAG, '1', false);
            return;
        }

        update_option(self::DISMISSED_OPTION, '1', false);
        update_option(self::SPUROUS_NOTICE_FLAG, '1', false);
    }

    public static function renderNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_pending = LegacyMigrator::isPending();
        $state = LegacyMigrator::status();
        $last_error = $state->lastError();
        $is_failed = !$is_pending && $last_error !== '';
        $is_completed_recently = $state->isFinished()
            && get_option(self::DISMISSED_OPTION, '') !== '1';

        if (!$is_pending && !$is_failed && !$is_completed_recently) {
            return;
        }

        $tools_url = admin_url('tools.php?page=' . self::PAGE_SLUG);

        echo '<div class="notice ' . esc_attr($is_failed ? 'notice-error' : ($is_pending ? 'notice-warning' : 'notice-success')) . '">';
        echo '<p><strong>' . esc_html__('Sikshya LMS', 'sikshya') . '</strong> &mdash; ';

        if ($is_pending) {
            echo esc_html__('Legacy Sikshya data was detected. The migration will continue automatically; click Open Migration Tool to monitor or retry.', 'sikshya');
        } elseif ($is_failed) {
            echo esc_html__('Legacy migration failed:', 'sikshya') . ' <code>' . esc_html($last_error) . '</code>';
        } elseif ($is_completed_recently) {
            echo esc_html__('Legacy data migration completed successfully.', 'sikshya');
        }

        echo ' <a class="button button-secondary" href="' . esc_url($tools_url) . '">' . esc_html__('Open Migration Tool', 'sikshya') . '</a> ';

        if ($is_completed_recently) {
            $dismiss_url = wp_nonce_url(
                admin_url('admin-post.php?action=sikshya_legacy_migration_dismiss'),
                LegacyMigrator::NONCE_ACTION
            );
            echo ' <a class="button" href="' . esc_url($dismiss_url) . '">' . esc_html__('Dismiss', 'sikshya') . '</a>';
        }

        echo '</p></div>';
    }

    public static function renderToolsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'sikshya'));
        }

        $state = LegacyMigrator::status();
        $is_pending = LegacyMigrator::isPending();
        $fingerprint = LegacyDataDetector::fingerprint();
        $logger = new LegacyMigrationLogger();

        $run_url = wp_nonce_url(
            admin_url('admin-post.php?action=sikshya_legacy_migration_run'),
            LegacyMigrator::NONCE_ACTION
        );
        $dry_run_url = wp_nonce_url(
            admin_url('admin-post.php?action=sikshya_legacy_migration_dry_run'),
            LegacyMigrator::NONCE_ACTION
        );
        $reset_url = wp_nonce_url(
            admin_url('admin-post.php?action=sikshya_legacy_migration_reset'),
            LegacyMigrator::NONCE_ACTION
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Sikshya Legacy Migration', 'sikshya') . '</h1>';

        echo '<h2>' . esc_html__('Detected legacy data', 'sikshya') . '</h2>';
        echo '<table class="widefat striped" style="max-width:800px"><tbody>';
        echo '<tr><th>' . esc_html__('Has legacy data', 'sikshya') . '</th><td>' . ($fingerprint['has_legacy_data'] ? 'yes' : 'no') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Legacy options', 'sikshya') . '</th><td>' . esc_html(implode(', ', $fingerprint['options']) ?: '—') . '</td></tr>';

        $post_summary = [];
        foreach ($fingerprint['post_types'] as $type => $count) {
            $post_summary[] = $type . ' (' . (int) $count . ')';
        }
        echo '<tr><th>' . esc_html__('Legacy posts', 'sikshya') . '</th><td>' . esc_html(implode(', ', $post_summary) ?: '—') . '</td></tr>';

        $tax_summary = [];
        foreach ($fingerprint['taxonomies'] as $tax => $count) {
            $tax_summary[] = $tax . ' (' . (int) $count . ')';
        }
        echo '<tr><th>' . esc_html__('Legacy taxonomies', 'sikshya') . '</th><td>' . esc_html(implode(', ', $tax_summary) ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Legacy tables', 'sikshya') . '</th><td>' . esc_html(implode(', ', $fingerprint['tables']) ?: '—') . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Migration status', 'sikshya') . '</h2>';
        echo '<table class="widefat striped" style="max-width:800px"><tbody>';
        echo '<tr><th>' . esc_html__('Pending', 'sikshya') . '</th><td>' . ($is_pending ? 'yes' : 'no') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Started at', 'sikshya') . '</th><td>' . esc_html($state->startedAt() ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Finished at', 'sikshya') . '</th><td>' . esc_html($state->finishedAt() ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Last error', 'sikshya') . '</th><td>' . esc_html($state->lastError() ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Log file', 'sikshya') . '</th><td><code>' . esc_html($logger->logFilePath()) . '</code></td></tr>';
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Steps', 'sikshya') . '</h2>';
        echo '<table class="widefat striped" style="max-width:800px"><thead><tr>';
        echo '<th>' . esc_html__('Step', 'sikshya') . '</th>';
        echo '<th>' . esc_html__('Status', 'sikshya') . '</th>';
        echo '<th>' . esc_html__('Counts', 'sikshya') . '</th>';
        echo '</tr></thead><tbody>';

        foreach (LegacyMigrator::steps() as $step) {
            $stepStatus = $state->getStepStatus($step->id());
            $steps_data = $state->steps();
            $counts = isset($steps_data[$step->id()]['counts']) && is_array($steps_data[$step->id()]['counts'])
                ? $steps_data[$step->id()]['counts']
                : [];
            $count_summary = [];
            foreach ($counts as $bucket => $value) {
                $count_summary[] = esc_html($bucket) . ': ' . (int) $value;
            }
            echo '<tr>';
            echo '<td><strong>' . esc_html($step->id()) . '</strong><br><small>' . esc_html($step->description()) . '</small></td>';
            echo '<td>' . esc_html($stepStatus) . '</td>';
            echo '<td>' . ($count_summary ? implode(', ', $count_summary) : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<p style="margin-top:20px">';
        echo '<a class="button button-primary" href="' . esc_url($run_url) . '">' . esc_html__('Run / resume migration', 'sikshya') . '</a> ';
        echo '<a class="button" href="' . esc_url($dry_run_url) . '">' . esc_html__('Dry run', 'sikshya') . '</a> ';
        echo '<a class="button" href="' . esc_url($reset_url) . '" onclick="return confirm(\'' . esc_attr__('Reset migration state? This does not roll back any data.', 'sikshya') . '\');">' . esc_html__('Reset state', 'sikshya') . '</a>';
        echo '</p>';

        echo '</div>';
    }

    public static function handleRun(): void
    {
        self::guardAction();
        try {
            LegacyMigrator::run(false);
            self::redirectBack('migration_run', 'success');
        } catch (\Throwable $e) {
            (new LegacyMigrationLogger())->error('Manual run failed: ' . $e->getMessage());
            self::redirectBack('migration_run', 'error');
        }
    }

    public static function handleDryRun(): void
    {
        self::guardAction();
        try {
            LegacyMigrator::dryRun();
            self::redirectBack('migration_dry_run', 'success');
        } catch (\Throwable $e) {
            self::redirectBack('migration_dry_run', 'error');
        }
    }

    public static function handleReset(): void
    {
        self::guardAction();
        LegacyMigrator::reset();
        delete_option(self::DISMISSED_OPTION);
        delete_option(self::SPUROUS_NOTICE_FLAG);
        self::redirectBack('migration_reset', 'success');
    }

    public static function handleDismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'sikshya'));
        }
        check_admin_referer(LegacyMigrator::NONCE_ACTION);
        update_option(self::DISMISSED_OPTION, '1', false);
        self::redirectBack('migration_dismiss', 'success');
    }

    private static function guardAction(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'sikshya'));
        }
        check_admin_referer(LegacyMigrator::NONCE_ACTION);
    }

    private static function redirectBack(string $action, string $status): void
    {
        $url = add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'sikshya_action' => $action,
                'sikshya_status' => $status,
            ],
            admin_url('tools.php')
        );
        wp_safe_redirect($url);
        exit;
    }
}
