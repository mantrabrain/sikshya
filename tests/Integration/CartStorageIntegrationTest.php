<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Frontend\Site\CartStorage;
use WP_UnitTestCase;

/**
 * Behaviour coverage for `CartStorage` — the cart-state surface checkout
 * and the bundle UI both read and write.
 *
 * For logged-in users the cart lives in usermeta; for guests it lives in a
 * cookie. We exercise the logged-in path here (the cookie path is much
 * harder to test inside a PHPUnit kernel without polluting `$_COOKIE`
 * across tests).
 *
 * @covers \Sikshya\Frontend\Site\CartStorage::addCourse
 * @covers \Sikshya\Frontend\Site\CartStorage::removeCourse
 * @covers \Sikshya\Frontend\Site\CartStorage::clear
 * @covers \Sikshya\Frontend\Site\CartStorage::getCourseIds
 * @covers \Sikshya\Frontend\Site\CartStorage::setBundleCart
 * @covers \Sikshya\Frontend\Site\CartStorage::getBundleId
 */
final class CartStorageIntegrationTest extends WP_UnitTestCase
{
    private int $user_id = 0;
    private int $course_a = 0;
    private int $course_b = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);

        $this->course_a = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'Course A',
        ]);
        $this->course_b = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'Course B',
        ]);
    }

    public function tearDown(): void
    {
        CartStorage::clear();
        parent::tearDown();
    }

    public function testAddCoursePersistsToUsermeta(): void
    {
        self::assertSame([], CartStorage::getCourseIds(), 'Cart must start empty.');

        $added = CartStorage::addCourse($this->course_a);
        self::assertTrue($added, 'addCourse must return true when the course is newly added.');
        self::assertSame(
            [$this->course_a],
            CartStorage::getCourseIds(),
            'Course must appear in getCourseIds after add.'
        );
    }

    public function testAddCourseIsIdempotent(): void
    {
        CartStorage::addCourse($this->course_a);
        $second = CartStorage::addCourse($this->course_a);

        self::assertFalse(
            $second,
            'Re-adding the same course must return false (no change).'
        );
        self::assertSame(
            [$this->course_a],
            CartStorage::getCourseIds(),
            'No duplicate course id may appear in the cart list.'
        );
    }

    public function testAddMultipleCoursesAccumulates(): void
    {
        CartStorage::addCourse($this->course_a);
        CartStorage::addCourse($this->course_b);

        $ids = CartStorage::getCourseIds();
        sort($ids); // Don't lock ordering — that's an implementation detail.
        $expected = [$this->course_a, $this->course_b];
        sort($expected);

        self::assertSame($expected, $ids);
    }

    public function testRemoveCourseDropsItFromCart(): void
    {
        CartStorage::addCourse($this->course_a);
        CartStorage::addCourse($this->course_b);

        $removed = CartStorage::removeCourse($this->course_a);
        self::assertTrue($removed, 'removeCourse must return true when the course was present.');
        self::assertSame(
            [$this->course_b],
            array_values(CartStorage::getCourseIds()),
            'Only the removed course must drop out — the other items must remain.'
        );
    }

    public function testRemoveCourseIsIdempotentWhenNotPresent(): void
    {
        // Current implementation always returns true (the method's
        // "ensure-this-id-is-not-in-the-cart" semantics). Removing
        // a non-existent id is a no-op but doesn't error — that's the
        // safer contract for a cart that other tabs may have already
        // emptied.
        self::assertTrue(
            CartStorage::removeCourse($this->course_a),
            'removeCourse must succeed (no-op) even when the course is not in the cart — idempotent ensure-absent semantics.'
        );
        self::assertSame(
            [],
            CartStorage::getCourseIds(),
            'Cart must remain empty after a no-op remove.'
        );
    }

    public function testClearEmptiesCart(): void
    {
        CartStorage::addCourse($this->course_a);
        CartStorage::addCourse($this->course_b);
        CartStorage::clear();

        self::assertSame([], CartStorage::getCourseIds());
        self::assertSame(0, CartStorage::getBundleId(), 'Clear must also reset the bundle id.');
    }

    public function testSetBundleCartReplacesItems(): void
    {
        CartStorage::addCourse($this->course_a);

        $bundle_id = 4242;
        CartStorage::setBundleCart([$this->course_a, $this->course_b], $bundle_id);

        $ids = CartStorage::getCourseIds();
        sort($ids);
        $expected = [$this->course_a, $this->course_b];
        sort($expected);

        self::assertSame($expected, $ids, 'Bundle setter must replace the cart contents with the bundle\'s courses.');
        self::assertSame(
            $bundle_id,
            CartStorage::getBundleId(),
            'Bundle id must persist after setBundleCart.'
        );
    }

    public function testCartIsScopedPerUser(): void
    {
        CartStorage::addCourse($this->course_a);
        self::assertSame([$this->course_a], CartStorage::getCourseIds());

        // Switch to a different logged-in user — they must see an empty
        // cart, not the previous user's items.
        $other = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($other);

        self::assertSame(
            [],
            CartStorage::getCourseIds(),
            'A different user must not see the previous user\'s cart contents — cart is per-user.'
        );
    }
}
