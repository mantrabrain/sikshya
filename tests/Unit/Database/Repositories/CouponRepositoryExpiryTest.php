<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Database\Repositories;

use PHPUnit\Framework\TestCase;
use Sikshya\Database\Repositories\CouponRepository;

/**
 * Regression guard for the coupon TOCTOU fix in `incrementUsedCount`.
 *
 * Background: pre-fix, the atomic UPDATE only enforced `max_uses`. A coupon
 * validated seconds before expiry could still be redeemed after expiry. The
 * fix adds an `expires_at IS NULL OR expires_at >= NOW()` guard to the same
 * UPDATE so the entire decision is one row-level operation.
 *
 * @covers \Sikshya\Database\Repositories\CouponRepository::incrementUsedCount
 */
final class CouponRepositoryExpiryTest extends TestCase
{
    /** @var object */
    private $wpdb;
    /** @var string */
    private $lastPreparedSql = '';
    /** @var array<int, mixed> */
    private $lastPreparedArgs = [];
    /** @var int|false */
    private $nextQueryReturn = 1;

    protected function setUp(): void
    {
        if (!class_exists('Sikshya\Database\Tables\CouponsTable')) {
            eval('namespace Sikshya\\Database\\Tables; class CouponsTable { public static function getTableName(): string { return "wp_sikshya_coupons"; } }');
        }
        if (!class_exists('Sikshya\Database\Tables\CouponRedemptionsTable')) {
            eval('namespace Sikshya\\Database\\Tables; class CouponRedemptionsTable { public static function getTableName(): string { return "wp_sikshya_coupon_redemptions"; } }');
        }

        $this->lastPreparedSql = '';
        $this->lastPreparedArgs = [];
        $this->nextQueryReturn = 1;

        $self = $this;
        $this->wpdb = new class ($self) {
            /** @var string */
            public $prefix = 'wp_';
            /** @var object */
            private $owner;
            public function __construct($owner)
            {
                $this->owner = $owner;
            }
            public function prepare($sql, ...$args)
            {
                $this->owner->setLastPrepared($sql, $args);
                return $sql;
            }
            public function query($sql)
            {
                return $this->owner->getNextQueryReturn();
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function setLastPrepared(string $sql, array $args): void
    {
        $this->lastPreparedSql = $sql;
        $this->lastPreparedArgs = $args;
    }

    /** @return int|false */
    public function getNextQueryReturn()
    {
        return $this->nextQueryReturn;
    }

    public function testUpdateIncludesExpiresAtGuard(): void
    {
        $repo = new CouponRepository();
        $repo->incrementUsedCount(42);

        // The SQL must include both the max_uses guard AND the new expiry guard
        // — verified by string match because the executed UPDATE must close the
        // TOCTOU window in a single statement.
        self::assertStringContainsString(
            'expires_at IS NULL OR expires_at >= NOW()',
            $this->lastPreparedSql,
            'Coupon UPDATE must check expires_at atomically — otherwise a coupon validated seconds before expiry can still be redeemed.'
        );
        self::assertStringContainsString(
            'max_uses = 0 OR used_count < max_uses',
            $this->lastPreparedSql,
            'Original max_uses guard must remain present.'
        );
        self::assertSame([42], $this->lastPreparedArgs);
    }

    public function testReturnsTrueWhenSingleRowUpdated(): void
    {
        $this->nextQueryReturn = 1;
        $repo = new CouponRepository();
        self::assertTrue($repo->incrementUsedCount(5));
    }

    public function testReturnsFalseWhenCapHit(): void
    {
        $this->nextQueryReturn = 0;
        $repo = new CouponRepository();
        self::assertFalse(
            $repo->incrementUsedCount(5),
            'When the WHERE clause fails (cap hit OR expired), query() returns 0 rows affected and the method must report false so callers can refuse the redemption.'
        );
    }
}
