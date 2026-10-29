<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Admin\SiteHealthNotices;
use WP_UnitTestCase;

/**
 * The "Pro is older than this Sikshya" notice.
 *
 * It exists because of release mechanics, not code: the free plugin updates
 * from WordPress.org, often automatically, while Pro updates come from the
 * store. So for a while after every release the ordinary state of a paying
 * site is *free ahead of Pro* — and a screen the free plugin renders for a
 * newer Pro add-on has no REST route behind it until that add-on ships,
 * because add-on routes are registered in the add-on's own boot.
 *
 * What is pinned here is mostly the silence. A false alarm costs more than the
 * real warning earns, since the symptom is one screen failing to load rather
 * than anything breaking, and the overwhelming majority of installs are free
 * with no Pro at all.
 *
 * @group pro_version_notice
 */
final class ProVersionNoticeIntegrationTest extends WP_UnitTestCase
{
    private ?string $reported = null;

    public function set_up(): void
    {
        parent::set_up();
        add_filter('sikshya_installed_pro_version', [$this, 'reportVersion']);
    }

    public function tear_down(): void
    {
        remove_filter('sikshya_installed_pro_version', [$this, 'reportVersion']);
        $this->reported = null;
        wp_set_current_user(0);
        parent::tear_down();
    }

    public function reportVersion($version): string
    {
        return $this->reported === null ? (string) $version : $this->reported;
    }

    /** @return array<int, array<string, mixed>> */
    private function checksFor(?string $proVersion): array
    {
        $this->reported = $proVersion;
        $method = new \ReflectionMethod(SiteHealthNotices::class, 'checks');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $checks */
        $checks = $method->invoke(null);

        return array_values(array_filter($checks, static function (array $c): bool {
            return ($c['id'] ?? '') === 'pro_version_behind';
        }));
    }

    public function test_an_older_pro_is_reported(): void
    {
        $found = $this->checksFor('1.0.10');

        $this->assertCount(1, $found);
        $this->assertSame('warning', $found[0]['severity']);
        // Both numbers have to be in the body: "update Pro" is not actionable
        // without saying from what, to what.
        $this->assertStringContainsString('1.0.10', $found[0]['body']);
        $this->assertStringContainsString(SiteHealthNotices::EXPECTED_PRO_VERSION, $found[0]['body']);
    }

    public function test_the_expected_version_is_not_reported(): void
    {
        $this->assertSame([], $this->checksFor(SiteHealthNotices::EXPECTED_PRO_VERSION));
    }

    public function test_a_newer_pro_is_not_reported(): void
    {
        $this->assertSame([], $this->checksFor('2.5.0'));
        $this->assertSame([], $this->checksFor('1.1.0'));
    }

    /**
     * The ordinary free install. Nagging it about a plugin it has not bought
     * would be the worst outcome this check could produce.
     */
    public function test_a_site_without_pro_is_never_nagged(): void
    {
        $this->assertSame([], $this->checksFor(''));
        $this->assertSame([], $this->checksFor('   '));
    }

    /**
     * A Pro that does not state its version cannot be compared, and guessing
     * "probably old" would warn people whose setup is fine.
     */
    public function test_an_unstated_version_stays_silent(): void
    {
        $this->assertSame([], $this->checksFor(''));
    }

    /**
     * Pre-release Pro builds sort below the release they lead to, which is the
     * correct answer: those really are behind.
     */
    public function test_a_prerelease_of_the_expected_version_is_behind(): void
    {
        $this->assertCount(1, $this->checksFor(SiteHealthNotices::EXPECTED_PRO_VERSION . '-beta1'));
    }

    /**
     * The notice has to reach someone who can act on it, and the renderer is
     * capability-gated, so the link must point at a real admin screen.
     */
    public function test_the_notice_points_somewhere_useful(): void
    {
        $found = $this->checksFor('1.0.10');

        $this->assertNotSame('', (string) $found[0]['settings_url']);
        $this->assertStringContainsString('page=', (string) $found[0]['settings_url']);
        $this->assertNotSame('', (string) $found[0]['settings_label']);
    }

    /**
     * The expected version is a release-coordination value: it must name a real
     * version, and it must not be left behind at whatever the last release was.
     */
    public function test_the_expected_version_is_a_sane_version_string(): void
    {
        $expected = SiteHealthNotices::EXPECTED_PRO_VERSION;

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $expected);
        $this->assertTrue(
            version_compare($expected, '1.0.10', '>'),
            'the expected Pro version must be newer than the last released one, or the check can never fire'
        );
    }
}
