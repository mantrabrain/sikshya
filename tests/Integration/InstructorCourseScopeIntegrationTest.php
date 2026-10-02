<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\InstructorPermissions;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Cross-instructor authorization coverage for the course-owned REST surface.
 *
 * Background. The `/courses/{id}`, `/lessons/{id}`, `/quizzes/{id}`,
 * `curriculum/*` and `course-builder/*` routes were gated only by *plural*
 * capabilities (`edit_sikshya_courses`) or by `permissionAdmin`, neither of
 * which looks at the object being written. Every instructor holds those caps
 * for every course, so instructor A could rewrite, re-curriculum and
 * permanently force-delete instructor B's content, and enrol themselves into
 * B's paid courses.
 *
 * The obvious gate, `current_user_can('edit_post', $id)`, cannot be used here:
 * these post types register with `capability_type => 'post'`, so WordPress maps
 * `edit_post` onto `edit_published_posts` / `edit_others_posts`, while the
 * `sikshya_instructor` role is granted the parallel
 * `edit_published_sikshya_courses` family instead. It answers "no" for an
 * instructor's own published course — which is why the one place that did use
 * it (the course builder) rejected instructors editing their own courses.
 *
 * {@see InstructorPermissions::canEditObject()} is the single primitive both
 * directions now go through. These tests pin both halves: the attacks stay
 * blocked, and ordinary authoring by instructors *and* by stock WordPress
 * author/editor roles keeps working.
 *
 * @covers \Sikshya\Services\InstructorPermissions::canEditObject
 * @covers \Sikshya\Services\InstructorPermissions::owningCourseId
 */
final class InstructorCourseScopeIntegrationTest extends WP_UnitTestCase
{
    private ?WP_REST_Server $server = null;
    private int $instructorA = 0;
    private int $instructorB = 0;
    private int $learner = 0;
    private int $wpAuthor = 0;
    private int $admin = 0;
    private int $courseA = 0;
    private int $courseB = 0;
    private int $lessonB = 0;
    private int $chapterB = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server = $wp_rest_server;
        do_action('rest_api_init', $this->server);

        $this->instructorA = self::factory()->user->create(['role' => 'sikshya_instructor']);
        $this->instructorB = self::factory()->user->create(['role' => 'sikshya_instructor']);
        $this->learner = self::factory()->user->create(['role' => 'subscriber']);
        $this->wpAuthor = self::factory()->user->create(['role' => 'author']);
        $this->admin = self::factory()->user->create(['role' => 'administrator']);

        // Published on purpose: the broken `edit_post` mapping only misfires
        // once a course leaves draft, so a draft fixture would hide the bug.
        $this->courseA = $this->makeCourse($this->instructorA, 'Course A');
        $this->courseB = $this->makeCourse($this->instructorB, 'Course B');

        $this->lessonB = self::factory()->post->create([
            'post_type' => PostTypes::LESSON,
            'post_status' => 'publish',
            'post_author' => $this->instructorB,
            'post_title' => 'Lesson B',
        ]);
        update_post_meta($this->lessonB, '_sikshya_lesson_course', $this->courseB);

        $this->chapterB = self::factory()->post->create([
            'post_type' => PostTypes::CHAPTER,
            'post_status' => 'publish',
            'post_author' => $this->instructorB,
            'post_title' => 'Chapter B',
        ]);
        update_post_meta($this->chapterB, '_sikshya_chapter_course_id', $this->courseB);
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tearDown();
    }

    private function makeCourse(int $author, string $title): int
    {
        return (int) self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_author' => $author,
            'post_title' => $title,
            'post_content' => 'Body copy.',
        ]);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function call(string $method, string $route, ?array $body = null): \WP_REST_Response
    {
        $request = new WP_REST_Request($method, $route);
        if ($body !== null) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($body));
        }

        return rest_get_server()->dispatch($request);
    }

    // ---------------------------------------------------------------- primitive

    public function testOwnerOfPublishedCourseCanEditItDespiteEditPostMapping(): void
    {
        // Guards the exact mismatch that caused the bug in both directions.
        self::assertFalse(
            user_can($this->instructorA, 'edit_post', $this->courseA),
            'Precondition: WP maps edit_post to a cap the instructor role lacks. '
            . 'If this ever becomes true, the capability model changed and these gates should be revisited.'
        );
        self::assertTrue(
            InstructorPermissions::canEditObject($this->instructorA, $this->courseA),
            'An instructor must be able to edit their own published course.'
        );
    }

    public function testInstructorCannotEditAnotherInstructorsCourse(): void
    {
        self::assertFalse(InstructorPermissions::canEditObject($this->instructorA, $this->courseB));
    }

    public function testOwnershipIsInheritedFromTheOwningCourse(): void
    {
        self::assertSame($this->courseB, InstructorPermissions::owningCourseId($this->lessonB));
        self::assertSame($this->courseB, InstructorPermissions::owningCourseId($this->chapterB));
        self::assertFalse(InstructorPermissions::canEditObject($this->instructorA, $this->lessonB));
        self::assertTrue(InstructorPermissions::canEditObject($this->instructorB, $this->lessonB));
    }

    public function testLearnersAndAnonymousAreDenied(): void
    {
        self::assertFalse(
            InstructorPermissions::canEditObject($this->learner, $this->courseA),
            'A logged-in learner must never pass this gate; the front-end admin bar uses it standalone.'
        );
        self::assertFalse(InstructorPermissions::canEditObject(0, $this->courseA));
    }

    public function testStockWordPressAuthorRoleStillOwnsItsCourses(): void
    {
        // `capability_type => 'post'` means a stock author legitimately owns and
        // edits Sikshya courses. Scoping must not take that away.
        $authorCourse = $this->makeCourse($this->wpAuthor, 'Author course');

        self::assertTrue(InstructorPermissions::canEditObject($this->wpAuthor, $authorCourse));
        self::assertFalse(InstructorPermissions::canEditObject($this->wpAuthor, $this->courseB));
    }

    public function testAdministratorIsUnrestricted(): void
    {
        self::assertTrue(InstructorPermissions::canEditObject($this->admin, $this->courseB));
    }

    // -------------------------------------------------------------- route level

    public function testInstructorCannotRewriteAnotherInstructorsCourseViaRest(): void
    {
        wp_set_current_user($this->instructorA);

        $response = $this->call('POST', '/sikshya/v1/courses/' . $this->courseB, [
            'title' => 'HIJACKED',
            'content' => '<p>hijacked</p>',
        ]);

        self::assertSame(403, $response->get_status());
        self::assertSame('Course B', get_post($this->courseB)->post_title, 'The post must be unmodified.');
    }

    public function testInstructorCannotForceDeleteAnotherInstructorsCurriculum(): void
    {
        wp_set_current_user($this->instructorA);

        // bulk-delete force-deletes, bypassing the trash, so a missing gate here
        // was unrecoverable data loss rather than a reversible nuisance.
        $response = $this->call('POST', '/sikshya/v1/curriculum/bulk-delete', [
            'chapters' => [$this->chapterB],
            'lessons' => [$this->lessonB],
        ]);

        self::assertSame(403, $response->get_status());
        self::assertNotNull(get_post($this->chapterB), 'Chapter must survive.');
        self::assertNotNull(get_post($this->lessonB), 'Lesson must survive.');
    }

    public function testInstructorCannotAddCurriculumToAnotherInstructorsCourse(): void
    {
        wp_set_current_user($this->instructorA);

        $response = $this->call('POST', '/sikshya/v1/curriculum/chapters', [
            'title' => 'Intruder chapter',
            'course_id' => $this->courseB,
        ]);

        self::assertSame(403, $response->get_status());
    }

    public function testInstructorCannotEnrolIntoAnotherInstructorsCourse(): void
    {
        wp_set_current_user($this->instructorA);

        $response = $this->call('POST', '/sikshya/v1/admin/enrollments/manual', [
            'user_id' => $this->instructorA,
            'course_id' => $this->courseB,
        ]);

        self::assertSame(
            403,
            $response->get_status(),
            'Manual enrolment on someone else\'s course is a paywall bypass, not just a scoping leak.'
        );
    }

    public function testInstructorCanStillSaveTheirOwnPublishedCourseViaBuilder(): void
    {
        wp_set_current_user($this->instructorA);

        $response = $this->call('POST', '/sikshya/v1/course-builder/save', [
            'course_id' => $this->courseA,
            'title' => 'Course A renamed',
            'description' => 'Updated description.',
            'course_status' => 'publish',
        ]);

        self::assertSame(
            200,
            $response->get_status(),
            'Regression guard: the builder used to answer 403 "You are not allowed to edit this course" '
            . 'for an instructor editing their own published course.'
        );
        self::assertSame('Course A renamed', get_post($this->courseA)->post_title);
    }

    public function testOwnershipCanBeExtendedByAnAddOnFilter(): void
    {
        // Replacing `current_user_can('edit_post', $id)` with a concrete method
        // removed an extension point: that meta cap was reachable through
        // `map_meta_cap`, so a site or add-on could grant access to someone who is
        // not the post author — which is precisely what Multi-instructor exists to
        // do for a co-teacher. The filter restores it.
        self::assertFalse(
            InstructorPermissions::canEditObject($this->instructorA, $this->courseB),
            'precondition: A cannot edit B\'s course'
        );

        $grantCoTeacher = static function (bool $allowed, int $userId, int $postId, int $courseId): bool {
            unset($postId);
            return $allowed;
        };
        add_filter('sikshya_user_can_edit_course_object', $grantCoTeacher, 10, 4);

        $courseB = $this->courseB;
        $instructorA = $this->instructorA;
        $grant = static function (bool $allowed, int $userId, int $postId, int $courseId) use ($instructorA, $courseB): bool {
            unset($postId);
            if ($userId === $instructorA && $courseId === $courseB) {
                return true;
            }

            return $allowed;
        };
        add_filter('sikshya_user_can_edit_course_object', $grant, 20, 4);

        self::assertTrue(
            InstructorPermissions::canEditObject($this->instructorA, $this->courseB),
            'an add-on must be able to extend ownership to a co-teacher'
        );

        remove_filter('sikshya_user_can_edit_course_object', $grant, 20);
        remove_filter('sikshya_user_can_edit_course_object', $grantCoTeacher, 10);

        self::assertFalse(
            InstructorPermissions::canEditObject($this->instructorA, $this->courseB),
            'removing the filter must restore the default refusal'
        );
    }
}
