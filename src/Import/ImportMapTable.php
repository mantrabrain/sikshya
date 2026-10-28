<?php

declare(strict_types=1);

namespace Sikshya\Import;

use Sikshya\Database\Tables\ImportMapTable as CoreImportMapTable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The import map's table name, and a guarantee that it exists.
 *
 * The schema itself lives with the other core tables
 * ({@see \Sikshya\Database\Tables\ImportMapTable}) so that activation
 * creates it and uninstall drops it along with everything else. This class is
 * only the importer's door to it, plus the lazy create below — because a site
 * that was upgraded rather than freshly activated has never run the installer
 * for this table, and the screen that reads it would otherwise print a
 * database error over the page.
 *
 * @package Sikshya\Import
 */
final class ImportMapTable
{
    private const VERSION = '1.0.0';
    private const OPT_VERSION = 'sikshya_import_map_version';

    public static function name(): string
    {
        return CoreImportMapTable::name();
    }

    public static function ensure(): void
    {
        if (!defined('ABSPATH')) {
            return;
        }

        if (get_option(self::OPT_VERSION) === self::VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(CoreImportMapTable::createSql($wpdb->get_charset_collate()));

        update_option(self::OPT_VERSION, self::VERSION, false);
    }
}
