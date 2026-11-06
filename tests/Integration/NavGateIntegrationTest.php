<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Addons\Addons;
use Sikshya\Services\Settings;
use WP_UnitTestCase;

/**
 * Sidebar gating for add-on-backed routes.
 *
 * This exists because of a support case: a customer on Growth could not find
 * Discussions anywhere. Their licence was right and the feature was included,
 * but the nav row was built with a hard `if` that **removed** it unless the
 * add-on was already on — the one row of thirteen that hid itself instead of
 * reporting "off". So a site that had paid for the feature saw no trace of it
 * and no route to switch it on, while `DiscussionsPage` sat there the whole
 * time with an enable prompt nobody could reach.
 *
 * The rule these tests pin: a licensed route is always listed, badged `off`
 * when its add-on is disabled. Hiding is reserved for `upgrade` on free sites,
 * which {@see ReactAdminConfig::stripUpgradeGatedItems()} does deliberately.
 *
 * @group nav_gate
 */
final class NavGateIntegrationTest extends WP_UnitTestCase
{
    /** @var callable[] */
    private array $filters = [];

    public function tear_down(): void
    {
        foreach ($this->filters as [$hook, $cb]) {
            remove_filter($hook, $cb);
        }
        $this->filters = [];
        parent::tear_down();
    }

    /** The sidebar is capability-filtered, so an anonymous request sees one row. */
    private function asStaff(): void
    {
        wp_set_current_user((int) self::factory()->user->create(['role' => 'administrator']));
    }

    private function licenseAs(string $tier): void
    {
        $active = static fn(): bool => true;
        $plan = static fn(): string => $tier;
        add_filter('sikshya_commercial_is_active', $active, 99);
        add_filter('sikshya_commercial_site_tier', $plan, 99);
        $this->filters[] = ['sikshya_commercial_is_active', $active];
        $this->filters[] = ['sikshya_commercial_site_tier', $plan];
    }

    /** Find a row by id anywhere in the nav tree. */
    private function findRow(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $hit = $this->findRow($item['children'], $id);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }

    private function setAddonEnabled(string $id, bool $on): void
    {
        $ids = Addons::enabledIds();
        $ids = array_values(array_diff($ids, [$id]));
        if ($on) {
            $ids[] = $id;
        }
        Settings::set('addons_enabled', $ids);
    }

    public function test_a_licensed_route_is_listed_even_while_its_addon_is_off(): void
    {
        $this->asStaff();
        $this->licenseAs('growth');
        $this->setAddonEnabled('community_discussions', false);

        $row = $this->findRow(ReactAdminConfig::navigationItems(), 'discussions');

        $this->assertNotNull(
            $row,
            'A Growth site pays for Discussions; hiding the row leaves no way to discover or enable it.'
        );
        $this->assertSame('off', $row['badge'] ?? null, 'A disabled add-on should read as off, not vanish.');
    }

    public function test_the_route_loses_its_badge_once_the_addon_is_on(): void
    {
        $this->asStaff();
        $this->licenseAs('growth');
        $this->setAddonEnabled('community_discussions', true);

        $row = $this->findRow(ReactAdminConfig::navigationItems(), 'discussions');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('badge', $row, 'A licensed, enabled route carries no badge.');
    }

    public function test_a_tier_below_the_requirement_is_marked_for_upgrade(): void
    {
        // Discussions is catalogued at `pro`, which is the Growth plan; Starter
        // sits one rank below it.
        $this->asStaff();
        $this->licenseAs('starter');
        $this->setAddonEnabled('community_discussions', false);

        $row = $this->findRow(ReactAdminConfig::navigationItems(), 'discussions');

        $this->assertNotNull($row, 'Paid sites see the whole tree, upgrade rows included.');
        $this->assertSame('upgrade', $row['badge'] ?? null);
    }

    public function test_every_addon_backed_row_reports_state_rather_than_disappearing(): void
    {
        // The defect was one row behaving unlike its twelve siblings, so the
        // guard is over the whole file rather than over Discussions alone.
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Admin/ReactAdminConfig.php');

        $this->assertSame(
            0,
            preg_match_all('/if \(\s*(TierCapabilities::feature|Addons::isEnabled)\(/', (string) $src),
            'A nav row gated by a bare if() hides itself instead of badging; use withNavGate().'
        );
    }
}
