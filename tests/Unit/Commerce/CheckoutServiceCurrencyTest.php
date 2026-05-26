<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Commerce;

use PHPUnit\Framework\TestCase;
use Sikshya\Commerce\CheckoutService;

/**
 * Currency conversion is pure arithmetic + a static zero-decimal allowlist. This test pins the
 * conversion direction and the zero-decimal special case so a refactor of {@see CheckoutService}
 * can't silently change how Stripe/PayPal amounts are computed (a high-blast-radius bug class).
 *
 * @covers \Sikshya\Commerce\CheckoutService::toMinorUnits
 * @covers \Sikshya\Commerce\CheckoutService::fromMinorUnits
 */
final class CheckoutServiceCurrencyTest extends TestCase
{
    /**
     * @dataProvider standardCurrencyAmounts
     */
    public function testToMinorUnitsRoundsToTwoDecimalCurrencies(float $amount, string $currency, int $expected): void
    {
        self::assertSame($expected, CheckoutService::toMinorUnits($amount, $currency));
    }

    /**
     * @return array<string, array{0: float, 1: string, 2: int}>
     */
    public function standardCurrencyAmounts(): array
    {
        return [
            'USD whole dollar' => [10.00, 'USD', 1000],
            'USD with cents' => [9.99, 'USD', 999],
            'USD bankers-rounding edge' => [0.125, 'USD', 13], // PHP round() defaults to HALF_AWAY_FROM_ZERO
            'EUR uppercase' => [100.50, 'EUR', 10050],
            'eur lowercase still works' => [100.50, 'eur', 10050],
            'negative amount clamps to zero' => [-5.00, 'USD', 0],
        ];
    }

    /**
     * @dataProvider zeroDecimalCurrencyAmounts
     */
    public function testToMinorUnitsKeepsZeroDecimalCurrenciesIntact(float $amount, string $currency, int $expected): void
    {
        self::assertSame($expected, CheckoutService::toMinorUnits($amount, $currency));
    }

    /**
     * Stripe documents JPY, KRW, VND, etc. as zero-decimal — minor units == major units.
     *
     * @return array<string, array{0: float, 1: string, 2: int}>
     */
    public function zeroDecimalCurrencyAmounts(): array
    {
        return [
            'JPY whole yen' => [1000.0, 'JPY', 1000],
            'KRW whole won' => [50000.0, 'KRW', 50000],
        ];
    }

    public function testRoundtripIsLossless(): void
    {
        $minor = CheckoutService::toMinorUnits(123.45, 'USD');
        self::assertSame(12345, $minor);
        self::assertSame(123.45, CheckoutService::fromMinorUnits($minor, 'USD'));
    }
}
