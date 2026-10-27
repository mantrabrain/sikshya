<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use WP_UnitTestCase;

/**
 * What the LMS does when a person is removed from WordPress.
 *
 * The behaviour under test is deliberately narrow and deliberately
 * non-destructive: open enrolments close, completed ones do not, nothing is
 * deleted, and one action fires carrying who it was — because after the delete
 * the user record is gone and anything listening (group seats, the audit log)
 * cannot look the person up any more.
 *
 * @covers \Sikshya\Services\UserDeprovisionService
 * @covers \Sikshya\Database\Repositories\EnrollmentRepository::cancelOpenForUser
 */
final class UserDeprovisionTest extends WP_UnitTestCase
{
    private EnrollmentRepository $enrollments;

    /** @var array<int, array{0:int, 1:array<string, mixed>}> */
    private array $captured = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->enrollments = new EnrollmentRepository();
        $this->captured = [];
        add_action('sikshya_user_deprovisioned', [$this, 'capture'], 10, 2);
    }

    public function tearDown(): void
    {
        remove_action('sikshya_user_deprovisioned', [$this, 'capture'], 10);
        parent::tearDown();
    }

    /**
     * @param int|string           $user_id
     * @param array<string, mixed> $details
     */
    public function capture($user_id, $details = []): void
    {
        $this->captured[] = [(int) $user_id, is_array($details) ? $details : []];
    }

    private function makeCourse(): int
    {
        return (int) self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
        ]);
    }

    private function enrol(int $user_id, int $course_id, string $status = 'enrolled'): int
    {
        return $this->enrollments->create([
            'user_id' => $user_id,
            'course_id' => $course_id,
            'status' => $status,
        ]);
    }

    public function testDeletingAUserClosesTheirOpenEnrollments(): void
    {
        $user = (int) self::factory()->user->create(['role' => 'subscriber']);
        $open = $this->enrol($user, $this->makeCourse());

        wp_delete_user($user);

        $row = $this->enrollments->findById($open);
        self::assertNotNull($row, 'the enrolment row must survive — it is the record of what happened');
        self::assertSame('cancelled', (string) $row->status);
    }

    public function testACompletedEnrollmentIsLeftAloneBecauseItIsTheEvidence(): void
    {
        $user = (int) self::factory()->user->create(['role' => 'subscriber']);
        $done = $this->enrol($user, $this->makeCourse(), 'completed');

        wp_delete_user($user);

        // "Did they finish the mandatory course before they left?" is answered
        // by this row, and cancelling it would destroy the answer.
        self::assertSame('completed', (string) $this->enrollments->findById($done)->status);
    }

    public function testTheActionCarriesWhoItWasAndWhatWasClosed(): void
    {
        $user_id = (int) self::factory()->user->create([
            'role' => 'sikshya_student',
            'user_login' => 'leaver_' . wp_generate_password(6, false, false),
            'user_email' => 'leaver' . wp_rand(1000, 9999) . '@example.com',
            'display_name' => 'Departing Person',
        ]);
        $login = (string) get_userdata($user_id)->user_login;
        $email = (string) get_userdata($user_id)->user_email;
        $this->enrol($user_id, $this->makeCourse());
        $this->enrol($user_id, $this->makeCourse());

        wp_delete_user($user_id);

        self::assertCount(1, $this->captured);
        [$reported_id, $details] = $this->captured[0];

        self::assertSame($user_id, $reported_id);
        // The identity has to travel with the event: by now there is no user
        // to look up.
        self::assertSame($login, $details['user_login']);
        self::assertSame($email, $details['user_email']);
        self::assertSame('Departing Person', $details['display_name']);
        self::assertContains('sikshya_student', $details['roles']);
        self::assertSame(2, $details['enrollments_cancelled']);
    }

    public function testTheActionStillFiresForSomeoneWithNoEnrollments(): void
    {
        $user = (int) self::factory()->user->create(['role' => 'subscriber']);

        wp_delete_user($user);

        self::assertCount(1, $this->captured, 'a departure is an event whether or not they were studying');
        self::assertSame(0, $this->captured[0][1]['enrollments_cancelled']);
    }

    public function testASiteCanOptOutOfClosingEnrollments(): void
    {
        $user = (int) self::factory()->user->create(['role' => 'subscriber']);
        $open = $this->enrol($user, $this->makeCourse());

        add_filter('sikshya_cancel_enrollments_on_user_delete', '__return_false');
        wp_delete_user($user);
        remove_filter('sikshya_cancel_enrollments_on_user_delete', '__return_false');

        self::assertSame('enrolled', (string) $this->enrollments->findById($open)->status);
        self::assertSame(0, $this->captured[0][1]['enrollments_cancelled']);
    }

    public function testOnlyTheDeletedUsersEnrollmentsAreTouched(): void
    {
        $leaver = (int) self::factory()->user->create(['role' => 'subscriber']);
        $stayer = (int) self::factory()->user->create(['role' => 'subscriber']);
        $course = $this->makeCourse();
        $this->enrol($leaver, $course);
        $theirs = $this->enrol($stayer, $course);

        wp_delete_user($leaver);

        self::assertSame('enrolled', (string) $this->enrollments->findById($theirs)->status);
    }
}
