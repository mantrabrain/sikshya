<?php

namespace Sikshya\Commerce;

use Sikshya\Services\Settings;

/**
 * Applies the store's tax rate to an order.
 *
 * Settings → Commerce has offered a **Tax rate (%)** field and a **Prices already
 * include tax** switch, with copy promising "percent added to the price (or
 * included in it)". Neither was read anywhere: a store owner could set 8.25% and
 * no total changed, so the store under-charged tax while appearing configured.
 * That is a financial and legal exposure for the site owner, not a cosmetic bug.
 *
 * Two modes, and the difference matters:
 *
 *  - **Exclusive** (the default). The configured price is net; tax is added on
 *    top. A 100.00 course at 10% becomes 110.00.
 *  - **Inclusive.** The configured price already contains tax, so the amount
 *    payable must not change — only the tax *portion* of it is reported. A
 *    100.00 course at 10% stays 100.00, of which 9.09 is tax. Getting this
 *    backwards would charge inclusive-priced customers 110.00, which is the
 *    expensive direction to be wrong in.
 *
 * Tax is computed on the amount **after** discount, which is the ordinary rule:
 * a coupon reduces the taxable base rather than the tax being charged on a price
 * nobody paid.
 *
 * A rate of 0 — the shipped default — returns the amount untouched and reports
 * no tax, so existing stores see no change whatsoever from this being wired up.
 *
 * @package Sikshya\Commerce
 */
final class TaxService
{
    /**
     * The configured rate as a percentage, clamped to something sane.
     *
     * A negative rate would be a discount hiding in a tax field, and a rate
     * above 100 is far more likely to be a typo than a real jurisdiction.
     */
    public static function rate(): float
    {
        $raw = Settings::get('tax_rate', 0);
        $rate = is_numeric($raw) ? (float) $raw : 0.0;

        /**
         * Filter the effective tax rate, for per-country or per-customer rules.
         *
         * @param float $rate Percentage.
         */
        $rate = (float) apply_filters('sikshya_tax_rate', $rate);

        return max(0.0, min(100.0, $rate));
    }

    public static function isInclusive(): bool
    {
        return Settings::isTruthy(Settings::get('tax_inclusive', false));
    }

    /**
     * Split an amount into net, tax and payable total.
     *
     * @param float $amount Post-discount amount. Treated as net when the store
     *                      prices exclusive of tax, and as gross when inclusive.
     * @return array{net: float, tax: float, total: float, rate: float, inclusive: bool}
     */
    public static function apply(float $amount): array
    {
        $amount = round(max(0.0, $amount), 2);
        $rate = self::rate();
        $inclusive = self::isInclusive();

        if ($rate <= 0.0 || $amount <= 0.0) {
            return [
                'net' => $amount,
                'tax' => 0.0,
                'total' => $amount,
                'rate' => $rate,
                'inclusive' => $inclusive,
            ];
        }

        if ($inclusive) {
            // The amount already contains the tax. Extract the portion; the
            // payable total must stay exactly what was advertised.
            $net = round($amount / (1 + $rate / 100), 2);
            $tax = round($amount - $net, 2);

            return [
                'net' => $net,
                'tax' => $tax,
                'total' => $amount,
                'rate' => $rate,
                'inclusive' => true,
            ];
        }

        $tax = round($amount * $rate / 100, 2);

        return [
            'net' => $amount,
            'tax' => $tax,
            'total' => round($amount + $tax, 2),
            'rate' => $rate,
            'inclusive' => false,
        ];
    }
}
