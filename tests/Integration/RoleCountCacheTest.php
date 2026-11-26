<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Services\RoleCountCache;
use WP_UnitTestCase;

/**
 * Role counts must be right first, and cheap second.
 *
 * `count_users()` groups every `*_capabilities` row in usermeta, which no
 * index can help with, and Sikshya asked for it three times on an admin page
 * load. Caching it is only safe if the cache is dropped the moment the answer
 * could change, so that is what these pin: the number matches `count_users()`,
 * and every way a count can move invalidates it.
 *
 * @group performance
 */
final class RoleCountCacheTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        RoleCountCache::flush();
        RoleCountCache::init();
    }

    public function tear_down(): void
    {
        RoleCountCache::flush();
        parent::tear_down();
    }

    public function test_counts_match_count_users(): void
    {
        self::factory()->user->create(['role' => 'sikshya_student']);
        self::factory()->user->create(['role' => 'sikshya_student']);
        self::factory()->user->create(['role' => 'sikshya_instructor']);

        RoleCountCache::flush();

        $expected = count_users()['avail_roles'];

        $this->assertSame(
            (int) ($expected['sikshya_student'] ?? 0),
            RoleCountCache::forRole('sikshya_student')
        );
        $this->assertSame(
            (int) ($expected['sikshya_instructor'] ?? 0),
            RoleCountCache::forRole('sikshya_instructor')
        );
    }

    public function test_an_unknown_role_is_zero_not_an_error(): void
    {
        $this->assertSame(0, RoleCountCache::forRole('no_such_role_here'));
    }

    public function test_a_new_user_invalidates_the_cache(): void
    {
        $before = RoleCountCache::forRole('sikshya_student');

        self::factory()->user->create(['role' => 'sikshya_student']);

        $this->assertSame(
            $before + 1,
            RoleCountCache::forRole('sikshya_student'),
            'Registering a student did not invalidate the cached count.'
        );
    }

    public function test_a_deleted_user_invalidates_the_cache(): void
    {
        $user_id = (int) self::factory()->user->create(['role' => 'sikshya_student']);
        $before = RoleCountCache::forRole('sikshya_student');

        wp_delete_user($user_id);

        $this->assertSame(
            $before - 1,
            RoleCountCache::forRole('sikshya_student'),
            'Deleting a student did not invalidate the cached count.'
        );
    }

    public function test_a_role_change_invalidates_the_cache(): void
    {
        $user_id = (int) self::factory()->user->create(['role' => 'sikshya_student']);

        $students = RoleCountCache::forRole('sikshya_student');
        $instructors = RoleCountCache::forRole('sikshya_instructor');

        $user = get_userdata($user_id);
        $this->assertInstanceOf(\WP_User::class, $user);
        $user->set_role('sikshya_instructor');

        $this->assertSame($students - 1, RoleCountCache::forRole('sikshya_student'));
        $this->assertSame($instructors + 1, RoleCountCache::forRole('sikshya_instructor'));
    }

    /**
     * The instructor approval path adds a role rather than replacing one.
     */
    public function test_adding_a_second_role_invalidates_the_cache(): void
    {
        $user_id = (int) self::factory()->user->create(['role' => 'sikshya_student']);

        $before = RoleCountCache::forRole('sikshya_instructor');

        $user = get_userdata($user_id);
        $this->assertInstanceOf(\WP_User::class, $user);
        $user->add_role('sikshya_instructor');

        $this->assertSame($before + 1, RoleCountCache::forRole('sikshya_instructor'));
    }

    public function test_removing_a_role_invalidates_the_cache(): void
    {
        $user_id = (int) self::factory()->user->create(['role' => 'sikshya_instructor']);

        $before = RoleCountCache::forRole('sikshya_instructor');

        $user = get_userdata($user_id);
        $this->assertInstanceOf(\WP_User::class, $user);
        $user->remove_role('sikshya_instructor');

        $this->assertSame($before - 1, RoleCountCache::forRole('sikshya_instructor'));
    }

    /**
     * The whole point: repeated asks must not repeat the scan.
     */
    public function test_repeated_reads_do_not_repeat_the_query(): void
    {
        self::factory()->user->create(['role' => 'sikshya_student']);
        RoleCountCache::flush();

        // First read pays for the scan.
        RoleCountCache::forRole('sikshya_student');

        $before = get_num_queries();
        for ($i = 0; $i < 5; $i++) {
            RoleCountCache::forRole('sikshya_student');
            RoleCountCache::forRole('sikshya_instructor');
        }

        $this->assertSame(
            0,
            get_num_queries() - $before,
            'Ten role reads after a warm cache still hit the database.'
        );
    }

    /**
     * The reports snapshot must not pay for a count it then throws away:
     * `distinct_learners` comes from the enrolment table, and the role count
     * is only the fallback when that table has nothing to say.
     */
    public function test_reports_snapshot_skips_the_role_count_when_enrolments_answer(): void
    {
        $course_id = (int) self::factory()->post->create([
            'post_type' => \Sikshya\Constants\PostTypes::COURSE,
            'post_status' => 'publish',
        ]);
        $user_id = (int) self::factory()->user->create(['role' => 'sikshya_student']);

        global $wpdb;
        $table = \Sikshya\Database\Tables\EnrollmentsTable::name();
        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'active',
                'progress' => 0,
            ]
        );

        RoleCountCache::flush();
        $repo = new \Sikshya\Database\Repositories\ReportsRepository();
        $stats = $repo->getOverviewStats();

        $this->assertGreaterThan(0, (int) $stats['distinct_learners']);
        $this->assertFalse(
            get_transient(RoleCountCache::TRANSIENT),
            'The reports snapshot counted users even though enrolments had the answer.'
        );
    }
}
