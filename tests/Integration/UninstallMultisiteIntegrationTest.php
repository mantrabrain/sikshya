<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Core\Activator;
use Sikshya\Core\Uninstaller;
use WP_UnitTestCase;

/**
 * The uninstaller's multisite branches.
 *
 * {@see Uninstaller::removeOptions()} has an `is_multisite()` path that sweeps
 * network options out of `wp_sitemeta` and clears the two uninstall flags as site
 * options. None of it is reachable from a single-site run, so on a network
 * install a customer who opted in to erasing their data would have kept every
 * network-level Sikshya option — including the JWT signing secret — with nothing
 * reporting a problem.
 *
 * Requires the multisite config, which sets WP_MULTISITE=1:
 *
 *     vendor/bin/phpunit -c phpunit.multisite.xml
 *
 * @group multisite
 * @group uninstall
 * @covers \Sikshya\Core\Uninstaller::removeOptions
 */
final class UninstallMultisiteIntegrationTest extends WP_UnitTestCase
{
    private const ERASE_FLAG = '_sikshya_erase_data_on_uninstall';

    /** Network options a customer would recognise as theirs. */
    private const FREE_NETWORK_OPTIONS = [
        'sikshya_jwt_secret' => 'network-signing-secret',
        'sikshya_general_settings' => 'a',
        'sikshya_currency' => 'USD',
        '_sikshya_network_flag' => '1',
    ];

    /** Pro owns these; Pro's own uninstall.php removes them. */
    private const PRO_NETWORK_OPTIONS = [
        'sikshya_pro_license_tier' => 'growth',
        '_sikshya_pro_private' => 'x',
        'sikshya_webhooks_db_version' => '3',
    ];

    private int $admin = 0;

    public function setUp(): void
    {
        parent::setUp();

        if (!is_multisite()) {
            self::markTestSkipped('Requires a multisite install; run with -c phpunit.multisite.xml.');
        }

        // `Uninstaller::uninstall()` gates on `activate_plugins`, which on a
        // network install belongs to super admins only.
        $this->admin = (int) self::factory()->user->create(['role' => 'administrator']);
        grant_super_admin($this->admin);
        wp_set_current_user($this->admin);

        foreach (self::FREE_NETWORK_OPTIONS as $key => $value) {
            update_site_option($key, $value);
        }
        foreach (self::PRO_NETWORK_OPTIONS as $key => $value) {
            update_site_option($key, $value);
        }
        update_site_option(self::ERASE_FLAG, '1');
        update_option(self::ERASE_FLAG, '1');
    }

    public function tearDown(): void
    {
        foreach (array_keys(self::FREE_NETWORK_OPTIONS + self::PRO_NETWORK_OPTIONS) as $key) {
            delete_site_option($key);
        }
        delete_site_option(self::ERASE_FLAG);
        Activator::activate();
        parent::tearDown();
    }

    public function testNetworkOptionsAreErasedWhenTheCustomerOptsIn(): void
    {
        self::assertTrue(is_multisite(), 'Precondition: running as multisite.');
        self::assertSame(
            'network-signing-secret',
            get_site_option('sikshya_jwt_secret'),
            'Precondition: the network option is set.'
        );

        Uninstaller::uninstall();

        foreach (array_keys(self::FREE_NETWORK_OPTIONS) as $key) {
            self::assertFalse(
                get_site_option($key, false),
                "Network option {$key} must be erased. The multisite branch was never exercised, "
                . 'so on a network install these all survived an opt-in erasure.'
            );
        }
    }

    public function testProOwnedNetworkOptionsAreLeftForProsOwnUninstaller(): void
    {
        Uninstaller::uninstall();

        foreach (self::PRO_NETWORK_OPTIONS as $key => $value) {
            self::assertSame(
                $value,
                get_site_option($key),
                "Free must not delete the Pro-owned network option {$key}."
            );
        }
    }

    public function testTheUninstallFlagsThemselvesAreClearedNetworkWide(): void
    {
        Uninstaller::uninstall();

        self::assertFalse(
            get_site_option(self::ERASE_FLAG, false),
            'A stale network erase flag would silently re-arm erasure on a future uninstall.'
        );
    }

    public function testRolesAreRemovedOnAMultisiteUninstallToo(): void
    {
        self::assertNotNull(get_role('sikshya_instructor'), 'Precondition: role installed.');

        Uninstaller::uninstall();

        self::assertNull(get_role('sikshya_instructor'));
    }
}
