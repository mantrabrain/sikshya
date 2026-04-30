# Sikshya legacy migration

This directory contains the entire system that migrates a site from the
legacy `sikshya-old` plugin (versions `0.0.x`) to the rewritten Sikshya
plugin (`1.0.0`+).

The new plugin's bootstrap intentionally guards every reference to this
package with `class_exists(\Sikshya\Migration\LegacyMigrator::class)`, so
once the entire installed base has been migrated the directory is **safe
to delete** in a future minor release. Removing the directory and
regenerating the autoloader (`composer dump-autoload -o`) turns the
migration system into a clean no-op without any other code changes.

## What runs when

1. On `register_activation_hook`, the bootstrap calls
   `LegacyMigrator::scheduleIfPending()`. If `LegacyDataDetector`
   reports any legacy fingerprint (options, CPTs, taxonomies, tables) we
   set the `sikshya_legacy_migration_pending` option and schedule a single
   cron event so the work starts even if the activation handler can't.
2. On `plugins_loaded` (priority 20) `LegacyMigrator::register()` is wired,
   which adds the cron callback and (in admin) the notice + Tools page.
   If no pending flag exists but the migration also isn't finished (e.g.
   the plugin was upgraded via SFTP / dropin and the activation hook never
   fired), the listener runs a transient-cached defensive detection to
   pick up legacy data anyway.
3. The Tools page lives at `Tools` → `Sikshya Migration`
   (`tools.php?page=sikshya-legacy-migration`). It exposes Run / Dry-run /
   Reset actions plus a per-step status table.
4. WP-CLI: `wp sikshya migrate-legacy [--dry-run] [--reset] [--status] [--all]`.

## Files

- `Plan.md` — human-readable audit + transform plan (mapping tables).
- `LegacyMigrator.php` — orchestrator + public entry points.
- `LegacyDataDetector.php` — detects legacy options/CPTs/taxonomies/tables.
- `LegacyMigrationLogger.php` — appends to `wp-content/uploads/sikshya-logs/legacy-migration-<date>.log`.
- `LegacyMigrationAdminNotice.php` — admin notice + Tools page.
- `LegacyMigrationCli.php` — WP-CLI command.
- `MigrationState.php` — persistent state, cursor + counter store.
- `Steps/StepInterface.php` + `Steps/AbstractStep.php` — base classes.
- `Steps/MigrateRolesAndCapabilities.php`
- `Steps/MigratePostTypes.php`
- `Steps/MigrateSectionsToChapters.php`
- `Steps/MigrateTaxonomies.php`
- `Steps/MigratePostMeta.php`
- `Steps/RebuildChapterContents.php`
- `Steps/MigrateEnrollments.php`
- `Steps/MigrateProgress.php`
- `Steps/MigrateOrders.php`
- `Steps/MigrateSettings.php`
- `Steps/MigrateUserMeta.php`
- `Steps/FlushRewriteRules.php`

## Removal hatch

```sh
# Once every customer has migrated:
rm -r app/public/wp-content/plugins/sikshya/src/Migration
composer dump-autoload -o
```

The bootstrap remains correct — `class_exists(...)` returns false and the
migrator is silently skipped. The `sikshya_legacy_migration_state` and
`sikshya_legacy_migration_pending` options can be deleted manually after
removal; they're harmless if left behind.
