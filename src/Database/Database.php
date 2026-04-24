<?php

namespace Sikshya\Database;

use Sikshya\Core\Plugin;
use Sikshya\Services\Settings;
use Sikshya\Database\Tables\Tables;
use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Database\Tables\CertificatesTable;
use Sikshya\Database\Tables\AssignmentSubmissionsTable;
use Sikshya\Database\Tables\QuizAttemptItemsTable;
use Sikshya\Database\Tables\OrdersTable;
use Sikshya\Database\Tables\OrderItemsTable;
use Sikshya\Database\Tables\CouponsTable;
use Sikshya\Database\Tables\CouponRedemptionsTable;

/**
 * Database Management Class
 *
 * @package Sikshya\Database
 */
class Database
{
    /**
     * Bump when schema or migrations change (incremental upgrades via maybeUpgrade).
     */
    public const SCHEMA_VERSION = '1.5.0';

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Create all database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Tables::all() as $tableClass) {
            dbDelta($tableClass::createSql($charset_collate));
        }
    }

    /**
     * Run incremental migrations after updates (safe to call on every request; version-gated).
     */
    public function maybeUpgrade(): void
    {
        // Idempotent normalization: ensure tables exist even when version flags are already current.
        $this->ensureTablesPresent();

        $current = Settings::getRaw('sikshya_db_version', '0');
        if (version_compare((string) $current, '1.1.0', '<')) {
            $this->migrateTo110();
            Settings::setRaw('sikshya_db_version', '1.1.0');
            $current = '1.1.0';
        }
        if (version_compare((string) $current, '1.2.0', '<')) {
            $this->migrateTo120();
            Settings::setRaw('sikshya_db_version', '1.2.0');
            $current = '1.2.0';
        }
        if (version_compare((string) $current, '1.3.0', '<')) {
            $this->migrateTo130();
            Settings::setRaw('sikshya_db_version', '1.3.0');
            $current = '1.3.0';
        }
        if (version_compare((string) $current, '1.4.0', '<')) {
            $this->migrateTo140();
            Settings::setRaw('sikshya_db_version', '1.4.0');
            $current = '1.4.0';
        }
        if (version_compare((string) $current, '1.5.0', '<')) {
            $this->migrateTo150();
            Settings::setRaw('sikshya_db_version', '1.5.0');
            $current = '1.5.0';
        }
        if (version_compare((string) $current, '1.6.0', '<')) {
            $this->migrateTo160();
            Settings::setRaw('sikshya_db_version', '1.6.0');
            $current = '1.6.0';
        }
    }

    /**
     * Idempotent schema normalization without bumping versions.
     *
     * Ensures that all expected custom tables exist even if an earlier upgrade path
     * skipped dbDelta (e.g. due to partial installs or manual DB restores).
     */
    private function ensureTablesPresent(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        foreach (Tables::all() as $tableClass) {
            $name = $tableClass::getTableName();
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)) !== $name) {
                dbDelta($tableClass::createSql($charset_collate));
            }
        }
    }

    private function migrateTo110(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta(QuizAttemptItemsTable::createSql($charset_collate));
        dbDelta(EnrollmentsTable::createSql($charset_collate));
        dbDelta(CertificatesTable::createSql($charset_collate));
    }

    private function migrateTo120(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta(OrdersTable::createSql($charset_collate));
        dbDelta(OrderItemsTable::createSql($charset_collate));
        dbDelta(CouponsTable::createSql($charset_collate));
        dbDelta(CouponRedemptionsTable::createSql($charset_collate));
    }

    private function migrateTo130(): void
    {
        // Hook point for future additive migrations.
    }

    private function migrateTo140(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->getOrdersCreateSql());

        global $wpdb;
        $table = OrdersTable::getTableName();
        $repo = new \Sikshya\Database\Repositories\OrderRepository();
        $ids = $wpdb->get_col("SELECT id FROM {$table} WHERE public_token IS NULL OR public_token = ''");
        if (!is_array($ids)) {
            return;
        }
        foreach ($ids as $id) {
            $repo->ensurePublicToken((int) $id);
        }
    }

    private function migrateTo150(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta(AssignmentSubmissionsTable::createSql($charset_collate));
    }

    /**
     * Ensure issued certificate verification codes are 64-hex (URL-safe, consistent with public routing).
     *
     * Older installations may have short codes; these cannot resolve to public hash URLs and break QR/verify links.
     */
    private function migrateTo160(): void
    {
        global $wpdb;
        $table = CertificatesTable::getTableName();

        // Table may not exist yet in fresh installs.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ((string) $exists !== (string) $table) {
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, verification_code FROM {$table} WHERE verification_code IS NULL OR verification_code = '' OR CHAR_LENGTH(verification_code) <> 64 LIMIT 5000"
        );
        if (!is_array($rows) || $rows === []) {
            return;
        }

        foreach ($rows as $r) {
            $id = isset($r->id) ? (int) $r->id : 0;
            if ($id <= 0) {
                continue;
            }
            $code = isset($r->verification_code) ? (string) $r->verification_code : '';
            $clean = strtolower(preg_replace('/[^a-f0-9]/', '', $code) ?? '');
            if (strlen($clean) === 64) {
                // Stored value might contain non-hex; normalize to hex only.
                if ($clean !== $code) {
                    $wpdb->update($table, ['verification_code' => $clean], ['id' => $id], ['%s'], ['%d']);
                }
                continue;
            }

            // Generate a fresh 64-hex verification token.
            try {
                $new = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $new = bin2hex(openssl_random_pseudo_bytes(32) ?: random_bytes(32));
            }

            $wpdb->update($table, ['verification_code' => $new], ['id' => $id], ['%s'], ['%d']);
        }
    }


    private function getEnrollmentsCreateSql(): string
    {
        global $wpdb;
        return EnrollmentsTable::createSql($wpdb->get_charset_collate());
    }

    private function getCertificatesCreateSql(): string
    {
        global $wpdb;
        return CertificatesTable::createSql($wpdb->get_charset_collate());
    }

    private function getAssignmentSubmissionsCreateSql(): string
    {
        global $wpdb;
        return AssignmentSubmissionsTable::createSql($wpdb->get_charset_collate());
    }

    private function getQuizAttemptItemsCreateSql(): string
    {
        global $wpdb;
        return QuizAttemptItemsTable::createSql($wpdb->get_charset_collate());
    }

    private function getOrdersCreateSql(): string
    {
        global $wpdb;
        return OrdersTable::createSql($wpdb->get_charset_collate());
    }

    private function getOrderItemsCreateSql(): string
    {
        global $wpdb;
        return OrderItemsTable::createSql($wpdb->get_charset_collate());
    }

    private function getCouponsCreateSql(): string
    {
        global $wpdb;
        return CouponsTable::createSql($wpdb->get_charset_collate());
    }

    private function getCouponRedemptionsCreateSql(): string
    {
        global $wpdb;
        return CouponRedemptionsTable::createSql($wpdb->get_charset_collate());
    }

    /**
     * Get table name with prefix
     */
    public function getTableName(string $table): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sikshya_' . $table;
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table);
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $result === $table_name;
    }

    /**
     * Get database version
     */
    public function getVersion(): string
    {
        return (string) Settings::getRaw('sikshya_db_version', '0.0.0');
    }
}
