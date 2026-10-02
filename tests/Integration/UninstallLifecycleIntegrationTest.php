<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Core\Activator;
use Sikshya\Core\Uninstaller;
use Sikshya\Migration\LegacyMigrator;
use Sikshya\Services\StatsUsage;
use WP_UnitTestCase;

/**
 * Uninstall lifecycle: does opting in to data erasure actually erase the data?
 *
 * Two separate defects motivated this suite.
 *
 * 1. The routine never ran. WordPress's {@see uninstall_plugin()} includes
 *    `uninstall.php` and nothing else — the plugin's main file is never loaded,
 *    so Composer's autoloader is not registered. `Uninstaller::uninstall()`
 *    references `Settings`, `PostTypes`, `Taxonomies`, `Tables` and a
 *    repository, so it raised a fatal on its first statement. Every erasure
 *    guarantee the plugin advertised was unreachable in production.
 *
 * 2. What it would have erased was incomplete. `removeOptions()` held a list of
 *    19 names, 13 of which have never existed, while ~28 options the plugin
 *    does write were missing from it — including the JWT signing secret.
 *    `removeScheduledEvents()` named four hooks that nothing schedules, and
 *    user meta was not touched at all.
 *
 * DESTRUCTIVE: this drops Sikshya's tables and removes its roles. `DROP TABLE`
 * causes an implicit commit, which defeats the transaction wp-phpunit wraps each
 * test in, so these tests are isolated behind `@group uninstall` (excluded from
 * the default run) and rebuild the schema and roles in tearDown.
 *
 * @group uninstall
 * @covers \Sikshya\Core\Uninstaller
 */
final class UninstallLifecycleIntegrationTest extends WP_UnitTestCase
{
    private const ERASE_FLAG = '_sikshya_erase_data_on_uninstall';

    /** Options a user would recognise as theirs, plus a Pro-owned control. */
    private const FREE_OPTIONS = [
        'sikshya_jwt_secret' => 'super-secret-signing-key',
        'sikshya_general_settings' => 'a',
        'sikshya_login_page' => '42',
        'sikshya_currency' => 'USD',
        'sikshya_version' => '1.0.9',
        '_sikshya_internal_flag' => '1',
    ];
    private const PRO_OPTIONS = [
        'sikshya_pro_license_tier' => 'growth',
        '_sikshya_pro_private' => 'x',
        // Pro addon schema markers that predate the `sikshya_pro_` prefix, so
        // they sit inside the namespace this sweep matches.
        'sikshya_webhooks_db_version' => '3',
        'sikshya_marketplace_db_version' => '2',
        'sikshya_enterprise_reports_db_version' => '5',
    ];

    private int $admin = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin);
    }

    public function tearDown(): void
    {
        // DDL above committed, so rebuild rather than relying on rollback.
        Activator::activate();
        parent::tearDown();
    }

    /**
     * @return array{posts: int[], terms: int[], core_post: int}
     */
    private function seed(): array
    {
        $posts = [];
        foreach (
            [
                PostTypes::COURSE,
                PostTypes::LESSON,
                PostTypes::QUIZ,
                PostTypes::ASSIGNMENT,
                PostTypes::QUESTION,
                PostTypes::CHAPTER,
                PostTypes::CERTIFICATE,
            ] as $type
        ) {
            $posts[$type] = (int) self::factory()->post->create([
                'post_type' => $type,
                'post_status' => 'publish',
                'post_title' => 'Seed ' . $type,
            ]);
        }

        $terms = [];
        foreach (
            [
                Taxonomies::COURSE_CATEGORY,
                Taxonomies::COURSE_TAG,
                Taxonomies::DIFFICULTY,
                Taxonomies::LESSON_TYPE,
                Taxonomies::QUESTION_TYPE,
            ] as $tax
        ) {
            $term = wp_insert_term('Seed ' . $tax, $tax);
            if (!is_wp_error($term)) {
                $terms[$tax] = (int) $term['term_id'];
            }
        }

        foreach (self::FREE_OPTIONS as $k => $v) {
            update_option($k, $v);
        }
        foreach (self::PRO_OPTIONS as $k => $v) {
            update_option($k, $v);
        }

        update_user_meta($this->admin, 'sikshya_cart_course_ids', [1, 2]);
        update_user_meta($this->admin, '_sikshya_learn_notes', 'my notes');
        update_user_meta($this->admin, 'sikshya_pro_cd_last_read_v1', 'keep me');

        // Meta Sikshya attached to a post that is NOT ours; it survives the
        // post-type sweep and so needs its own cleanup.
        $core_post = (int) self::factory()->post->create(['post_type' => 'post']);
        update_post_meta($core_post, '_sikshya_some_annotation', 'x');
        update_post_meta($core_post, '_sikshya_pro_private', 'keep me');

        wp_schedule_single_event(time() + 3600, StatsUsage::IMMEDIATE_HOOK);
        wp_schedule_event(time() + 3600, 'weekly', StatsUsage::CRON_HOOK);
        wp_schedule_single_event(time() + 3600, LegacyMigrator::CRON_HOOK);

        return ['posts' => $posts, 'terms' => $terms, 'core_post' => $core_post];
    }

    public function testErasureIsStrictlyOptInAndLeavesContentAloneByDefault(): void
    {
        $seeded = $this->seed();
        delete_option(self::ERASE_FLAG);

        Uninstaller::uninstall();

        foreach ($seeded['posts'] as $type => $id) {
            self::assertNotNull(get_post($id), "Content of type {$type} must survive an uninstall without opt-in.");
        }
        self::assertSame(
            'super-secret-signing-key',
            get_option('sikshya_jwt_secret'),
            'Settings must survive an uninstall without opt-in.'
        );
        self::assertNotEmpty(
            get_user_meta($this->admin, 'sikshya_cart_course_ids', true),
            'User meta must survive an uninstall without opt-in.'
        );
    }

    public function testUninstallAlwaysCleansUpItsOwnRolesAndCronEvenWithoutOptIn(): void
    {
        $this->seed();
        delete_option(self::ERASE_FLAG);

        self::assertNotNull(get_role('sikshya_instructor'), 'Precondition: role installed.');
        self::assertNotFalse(wp_next_scheduled(StatsUsage::CRON_HOOK), 'Precondition: cron scheduled.');

        Uninstaller::uninstall();

        // Roles and scheduled events are the plugin's own litter, not the
        // user's data, so they go regardless of the erase setting.
        self::assertNull(get_role('sikshya_instructor'), 'Custom roles must be removed on uninstall.');
        self::assertNull(get_role('sikshya_student'));
        self::assertNull(get_role('sikshya_assistant'));
        self::assertNull(get_role('sikshya_auditor'));

        foreach ([StatsUsage::CRON_HOOK, StatsUsage::IMMEDIATE_HOOK, LegacyMigrator::CRON_HOOK] as $hook) {
            self::assertFalse(
                wp_next_scheduled($hook),
                "Scheduled hook {$hook} must be cleared. The old list named four hooks that nothing schedules, "
                . 'so every real one survived deletion.'
            );
        }
    }

    public function testOptingInErasesEveryRegisteredPostTypeAndTaxonomy(): void
    {
        $seeded = $this->seed();
        update_option(self::ERASE_FLAG, '1');

        Uninstaller::uninstall();

        foreach ($seeded['posts'] as $type => $id) {
            self::assertNull(get_post($id), "Content of type {$type} must be erased when the user opts in.");
        }
        foreach ($seeded['terms'] as $tax => $term_id) {
            self::assertFalse(
                get_term($term_id, $tax) instanceof \WP_Term,
                "Terms in {$tax} must be erased when the user opts in."
            );
        }
    }

    public function testOptingInErasesSettingsIncludingTheJwtSecret(): void
    {
        $this->seed();
        update_option(self::ERASE_FLAG, '1');

        Uninstaller::uninstall();

        foreach (array_keys(self::FREE_OPTIONS) as $name) {
            self::assertFalse(
                get_option($name, false),
                "Option {$name} must be erased. A hardcoded list of 19 names left ~28 real options behind, "
                . 'including the JWT signing secret.'
            );
        }
    }

    public function testOptingInErasesUserMetaAndStrayPostMeta(): void
    {
        $seeded = $this->seed();
        update_option(self::ERASE_FLAG, '1');

        Uninstaller::uninstall();

        self::assertSame(
            '',
            get_user_meta($this->admin, 'sikshya_cart_course_ids', true),
            'User meta was never cleaned up at all.'
        );
        self::assertSame(
            '',
            get_user_meta($this->admin, '_sikshya_learn_notes', true),
            'Learner-authored notes must be erased when the user opts in.'
        );
        self::assertSame(
            '',
            get_post_meta($seeded['core_post'], '_sikshya_some_annotation', true),
            'Meta left on non-Sikshya posts must be erased too.'
        );
    }

    public function testProOwnedDataIsLeftForProsOwnUninstaller(): void
    {
        $seeded = $this->seed();
        update_option(self::ERASE_FLAG, '1');

        Uninstaller::uninstall();

        foreach (self::PRO_OPTIONS as $name => $value) {
            self::assertSame(
                $value,
                get_option($name),
                "Free must not delete the Pro-owned option {$name}; Sikshya Pro ships its own uninstall.php."
            );
        }
        self::assertSame(
            'keep me',
            get_user_meta($this->admin, 'sikshya_pro_cd_last_read_v1', true),
            'Pro-owned user meta must be left to Pro.'
        );
        self::assertSame(
            'keep me',
            get_post_meta($seeded['core_post'], '_sikshya_pro_private', true),
            'Pro-owned post meta must be left to Pro.'
        );
    }

    public function testOptingInDropsTheCustomTables(): void
    {
        global $wpdb;

        /*
         * WP_UnitTestCase rewrites `CREATE TABLE` to `CREATE TEMPORARY TABLE`
         * and `DROP TABLE` to `DROP TEMPORARY TABLE` through the `query`
         * filter, so inside a normal test the uninstaller's DROP cannot touch
         * the real tables the bootstrap created and this test would fail for a
         * reason that has nothing to do with the plugin. Opt out of the rewrite
         * so the statement reaches MySQL as written; tearDown rebuilds the
         * schema afterwards.
         */
        remove_filter('query', [$this, '_create_temporary_tables']);
        remove_filter('query', [$this, '_drop_temporary_tables']);

        $this->seed();
        $table = $wpdb->prefix . 'sikshya_enrollments';
        self::assertSame(
            $table,
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)),
            'Precondition: the enrollments table exists.'
        );

        update_option(self::ERASE_FLAG, '1');
        Uninstaller::uninstall();

        self::assertNull(
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)),
            'Custom tables must be dropped when the user opts in.'
        );
    }

    public function testUninstallEntryPointRegistersTheAutoloader(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/uninstall.php');

        self::assertStringContainsString(
            'vendor/autoload.php',
            $src,
            'uninstall.php must register Composer\'s autoloader. WordPress includes only this file — the plugin '
            . 'is never loaded — so without it every class reference in Uninstaller is a fatal error and nothing '
            . 'is cleaned up at all.'
        );
        self::assertStringContainsString(
            'Sikshya\\\\Services\\\\Settings',
            $src,
            'uninstall.php must verify the dependency graph resolved before starting, so a failure cannot leave '
            . 'a half-erased site.'
        );
    }

    public function testPreserveOptionsFilterProtectsThirdPartyAddons(): void
    {
        $this->seed();
        update_option('sikshya_someaddon_db_version', '9');
        update_option(self::ERASE_FLAG, '1');

        $filter = static function (array $options): array {
            $options[] = 'sikshya_someaddon_db_version';

            return $options;
        };
        add_filter('sikshya_uninstall_preserve_options', $filter);

        Uninstaller::uninstall();

        remove_filter('sikshya_uninstall_preserve_options', $filter);

        self::assertSame(
            '9',
            get_option('sikshya_someaddon_db_version'),
            'An addon must be able to protect its own options from the sweep.'
        );
    }

    // ------------------------------------------------------ file erasure

    /**
     * Point `wp_upload_dir()` at a throwaway directory for the duration of a test.
     *
     * {@see Uninstaller::removeFiles()} recursively deletes
     * `wp_upload_dir()['basedir'] . '/sikshya'`. Under wp-phpunit, ABSPATH is the
     * real site, so without this redirection these tests would delete the actual
     * uploads directory. The tests assert the redirection took effect before
     * invoking anything destructive.
     *
     * @return string The temporary base directory.
     */
    private function redirectUploadsToTempDir(): string
    {
        $base = rtrim(sys_get_temp_dir(), '/') . '/sikshya-uninstall-' . wp_generate_password(8, false);
        wp_mkdir_p($base . '/sikshya/nested');
        file_put_contents($base . '/sikshya/nested/asset.txt', 'course asset');
        file_put_contents($base . '/sikshya/top.txt', 'top level');

        // A sibling that must survive: proof the recursive delete stays scoped.
        wp_mkdir_p($base . '/not-sikshya');
        file_put_contents($base . '/not-sikshya/keep.txt', 'keep me');
        file_put_contents($base . '/loose.txt', 'keep me too');

        add_filter('upload_dir', static function (array $dirs) use ($base): array {
            $dirs['basedir'] = $base;
            $dirs['path'] = $base;

            return $dirs;
        });

        self::assertSame(
            $base,
            wp_upload_dir()['basedir'],
            'Safety check: uploads must be redirected to the temp directory before anything is deleted.'
        );

        return $base;
    }

    public function testFileErasureRequiresItsOwnOptInOnTopOfDataErasure(): void
    {
        $base = $this->redirectUploadsToTempDir();
        $this->seed();

        // Data erasure on, file erasure off — the default.
        update_option(self::ERASE_FLAG, '1');
        delete_option('_sikshya_erase_files_on_uninstall');

        Uninstaller::uninstall();

        self::assertDirectoryExists(
            $base . '/sikshya',
            'Uploaded files are a second, separate opt-in; erasing data must not remove them.'
        );
    }

    public function testFileErasureDoesNothingWithoutDataErasure(): void
    {
        $base = $this->redirectUploadsToTempDir();
        $this->seed();

        delete_option(self::ERASE_FLAG);
        update_option('_sikshya_erase_files_on_uninstall', '1');

        Uninstaller::uninstall();

        self::assertDirectoryExists(
            $base . '/sikshya',
            'The files flag alone must not delete anything; it only qualifies data erasure.'
        );
    }

    public function testOptingIntoBothRemovesTheSikshyaUploadsDirectoryAndNothingElse(): void
    {
        $base = $this->redirectUploadsToTempDir();
        $this->seed();

        update_option(self::ERASE_FLAG, '1');
        update_option('_sikshya_erase_files_on_uninstall', '1');

        Uninstaller::uninstall();

        self::assertDirectoryDoesNotExist(
            $base . '/sikshya',
            'With both opt-ins the plugin\'s upload directory must be removed, nested files included.'
        );

        // The recursive delete must not escape its subdirectory.
        self::assertDirectoryExists($base . '/not-sikshya', 'A sibling directory must survive.');
        self::assertFileExists($base . '/not-sikshya/keep.txt');
        self::assertFileExists($base . '/loose.txt', 'A file beside the directory must survive.');
        self::assertDirectoryExists($base, 'The uploads base itself must survive.');
    }
}
