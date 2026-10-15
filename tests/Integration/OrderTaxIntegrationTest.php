<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Commerce\TaxService;
use Sikshya\Services\Settings;
use WP_UnitTestCase;

/**
 * Tax on orders.
 *
 * Settings → Commerce has offered a tax rate and an "already includes tax"
 * switch since before this release, and nothing read either one: a store owner
 * could configure 8.25% and the store would charge nothing extra. That is the
 * site owner's financial and legal exposure, so these tests concentrate on the
 * two ways the arithmetic can be wrong in a way that costs somebody money.
 *
 * The expensive direction is treating an inclusive price as exclusive, which
 * charges every customer the tax twice over.
 */
final class OrderTaxIntegrationTest extends WP_UnitTestCase
{
    public function tear_down(): void
    {
        Settings::set('tax_rate', 0);
        Settings::set('tax_inclusive', false);
        remove_all_filters('sikshya_tax_rate');
        parent::tear_down();
    }

    private function configure(float $rate, bool $inclusive = false): void
    {
        Settings::set('tax_rate', $rate);
        Settings::set('tax_inclusive', $inclusive);
    }

    /**
     * The shipped default. Every existing store must be unaffected by tax being
     * wired up at all.
     */
    public function test_a_zero_rate_leaves_the_amount_untouched(): void
    {
        $this->configure(0);

        $taxed = TaxService::apply(100.00);

        self::assertSame(0.0, $taxed['tax']);
        self::assertSame(100.00, $taxed['total']);
        self::assertSame(100.00, $taxed['net']);
    }

    public function test_exclusive_tax_is_added_on_top(): void
    {
        $this->configure(10);

        $taxed = TaxService::apply(100.00);

        self::assertSame(100.00, $taxed['net']);
        self::assertSame(10.00, $taxed['tax']);
        self::assertSame(110.00, $taxed['total']);
        self::assertFalse($taxed['inclusive']);
    }

    /**
     * The costly case. An inclusive price already contains the tax, so the
     * payable total must not move — only the portion is reported. Treating it as
     * exclusive would bill 110.00 for an advertised 100.00 course.
     */
    public function test_inclusive_tax_does_not_change_what_is_payable(): void
    {
        $this->configure(10, true);

        $taxed = TaxService::apply(100.00);

        self::assertSame(
            100.00,
            $taxed['total'],
            'an inclusive price is what the customer was advertised and must be what they pay'
        );
        self::assertSame(9.09, $taxed['tax'], 'the tax portion of 100.00 at 10% inclusive');
        self::assertSame(90.91, $taxed['net']);
        self::assertTrue($taxed['inclusive']);
    }

    /**
     * net + tax must reconstruct the total exactly, or an accountant's
     * reconciliation fails by a penny per order.
     */
    public function test_inclusive_components_sum_back_to_the_total(): void
    {
        $this->configure(20, true);

        foreach ([19.99, 49.95, 100.00, 123.45, 999.99] as $amount) {
            $taxed = TaxService::apply($amount);
            self::assertSame(
                round($taxed['net'] + $taxed['tax'], 2),
                $taxed['total'],
                sprintf('net + tax must equal the total for %.2f', $amount)
            );
        }
    }

    public function test_exclusive_components_sum_back_to_the_total(): void
    {
        $this->configure(8.25);

        foreach ([19.99, 49.95, 100.00, 123.45] as $amount) {
            $taxed = TaxService::apply($amount);
            self::assertSame(
                round($taxed['net'] + $taxed['tax'], 2),
                $taxed['total'],
                sprintf('net + tax must equal the total for %.2f', $amount)
            );
        }
    }

    public function test_a_free_order_is_never_taxed(): void
    {
        $this->configure(20);

        $taxed = TaxService::apply(0.00);

        self::assertSame(0.0, $taxed['tax']);
        self::assertSame(0.00, $taxed['total']);
    }

    /**
     * A negative rate would be a discount hiding in a tax field.
     */
    public function test_a_negative_rate_is_clamped_away(): void
    {
        $this->configure(-15);

        self::assertSame(0.0, TaxService::rate());
        self::assertSame(100.00, TaxService::apply(100.00)['total']);
    }

    public function test_an_absurd_rate_is_clamped(): void
    {
        $this->configure(900);

        self::assertSame(100.0, TaxService::rate(), 'a rate above 100 is a typo, not a jurisdiction');
    }

    public function test_a_non_numeric_rate_is_treated_as_none(): void
    {
        Settings::set('tax_rate', 'eight percent');

        self::assertSame(0.0, TaxService::rate());
        self::assertSame(50.00, TaxService::apply(50.00)['total']);
    }

    /**
     * The extension point for per-country rules.
     */
    public function test_the_rate_can_be_filtered(): void
    {
        $this->configure(0);
        add_filter('sikshya_tax_rate', static fn (): float => 25.0);

        $taxed = TaxService::apply(80.00);

        self::assertSame(20.00, $taxed['tax']);
        self::assertSame(100.00, $taxed['total']);
    }

    public function test_a_filtered_rate_is_clamped_too(): void
    {
        add_filter('sikshya_tax_rate', static fn (): float => -5.0);

        self::assertSame(0.0, TaxService::rate());
    }

    public function test_rounding_stays_at_two_decimals(): void
    {
        $this->configure(7.5);

        $taxed = TaxService::apply(33.33);

        self::assertSame(2.50, $taxed['tax']);
        self::assertSame(35.83, $taxed['total']);
    }

    /**
     * The orders table must be able to record it, otherwise every order reports
     * zero tax however much was charged.
     */
    public function test_the_orders_table_has_a_tax_column(): void
    {
        global $wpdb;
        $table = \Sikshya\Database\Tables\OrdersTable::name();

        // phpcs:ignore WordPress.DB.PreparedSQL
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");

        self::assertContains('tax_total', array_map('strval', (array) $columns));
    }

    public function test_an_order_persists_its_tax(): void
    {
        $repo = new \Sikshya\Database\Repositories\OrderRepository();

        $order_id = $repo->createOrder([
            'user_id' => (int) self::factory()->user->create(),
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal' => 100.00,
            'discount_total' => 0.00,
            'tax_total' => 8.25,
            'total' => 108.25,
        ]);

        self::assertGreaterThan(0, $order_id);

        global $wpdb;
        $table = \Sikshya\Database\Tables\OrdersTable::name();
        // phpcs:ignore WordPress.DB.PreparedSQL
        $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $order_id));

        self::assertSame('8.25', (string) $stored->tax_total, 'the charged tax must be on the order record');
    }

    /**
     * An order created without a tax figure must record zero rather than
     * failing — every call site predating this column does exactly that.
     */
    public function test_an_order_without_tax_defaults_to_zero(): void
    {
        $repo = new \Sikshya\Database\Repositories\OrderRepository();

        $order_id = $repo->createOrder([
            'user_id' => (int) self::factory()->user->create(),
            'subtotal' => 10.00,
            'total' => 10.00,
        ]);

        self::assertGreaterThan(0, $order_id);

        global $wpdb;
        $table = \Sikshya\Database\Tables\OrdersTable::name();
        // phpcs:ignore WordPress.DB.PreparedSQL
        $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $order_id));

        self::assertSame('0.00', (string) $stored->tax_total);
    }
}
