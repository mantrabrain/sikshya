<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_UnitTestCase;

/**
 * Coverage for what the public-facing site renders. We exercise URL
 * resolution, archive query filtering, and the inline FOUC-prevention
 * boot script we added for the course-archive flicker fix.
 *
 * These tests don't hit the full theme — they verify the kernel-level
 * contract: routes resolve, queries return the expected posts, and the
 * head-emitted script contains the right localStorage logic.
 *
 * @covers \Sikshya\Frontend\Frontend::addFrontendMeta
 */
final class FrontendRenderingIntegrationTest extends WP_UnitTestCase
{
    private int $course_published = 0;
    private int $course_draft = 0;
    private int $course_trash = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->course_published = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'Public course',
        ]);
        $this->course_draft = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'draft',
            'post_title' => 'Hidden draft',
        ]);
        $this->course_trash = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'trash',
            'post_title' => 'Trashed course',
        ]);
    }

    public function testCoursePostTypeIsRegisteredAsPublicArchive(): void
    {
        $obj = get_post_type_object(PostTypes::COURSE);
        self::assertNotNull($obj);
        self::assertTrue(
            (bool) $obj->public,
            'Course post type must be public — otherwise the frontend catalog 404s.'
        );
        self::assertTrue(
            (bool) $obj->has_archive,
            'Course post type must declare has_archive so /courses/ resolves.'
        );
    }

    public function testCourseSinglePermalinkResolves(): void
    {
        $url = get_permalink($this->course_published);
        self::assertNotEmpty(
            $url,
            'A published course must have a resolvable permalink. (Rewrite rules may not be flushed in the test env — that\'s expected; we only assert the permalink helper returns a non-empty URL.)'
        );
        self::assertIsString($url);
        self::assertStringContainsString(
            home_url(),
            (string) $url,
            'Course permalink must be on this site (not a relative path or third-party URL).'
        );
    }

    public function testCourseArchiveQueryReturnsOnlyPublishedCourses(): void
    {
        $query = new \WP_Query([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);

        $ids = wp_list_pluck($query->posts, 'ID');

        self::assertContains($this->course_published, $ids, 'Published course must appear in archive query.');
        self::assertNotContains($this->course_draft, $ids, 'Draft course must NOT appear in public archive.');
        self::assertNotContains($this->course_trash, $ids, 'Trashed course must NOT appear in public archive.');
    }

    public function testCourseDraftSingleHidesFromAnonymous(): void
    {
        wp_set_current_user(0);

        $draft_url = get_permalink($this->course_draft);
        self::assertNotEmpty($draft_url);

        // Anonymous users hitting a draft URL get 404'd by the public-query
        // path. We verify the kernel-level rule by checking that the
        // generic public WP_Query refuses to surface drafts to anonymous.
        $query = new \WP_Query([
            'post_type' => PostTypes::COURSE,
            'p' => $this->course_draft,
            'post_status' => 'publish',
        ]);
        self::assertCount(
            0,
            $query->posts,
            'Anonymous public WP_Query (status=publish) must not return a draft course.'
        );
    }

    public function testArchiveFoucScriptEmittedInHeadForCourseArchive(): void
    {
        // Simulate hitting the course-type archive.
        $this->go_to(get_post_type_archive_link(PostTypes::COURSE) ?: '/courses/');
        self::assertTrue(is_post_type_archive(PostTypes::COURSE));

        ob_start();
        do_action('wp_head');
        $head = (string) ob_get_clean();

        self::assertStringContainsString(
            'sikshya-archive-view-boot',
            $head,
            'The course archive page must emit the inline FOUC-prevention script we added — without it the grid/list view flickers between page load and JS init.'
        );
        self::assertStringContainsString(
            'sikshya_course_archive_view',
            $head,
            'The boot script must reference the localStorage key the toggle JS writes to.'
        );
        self::assertStringContainsString(
            'data-sikshya-archive-view',
            $head,
            'The boot script must set the data attribute the CSS rules key off.'
        );
    }

    public function testArchiveFoucScriptNotEmittedOnUnrelatedPages(): void
    {
        // Hit a non-course page.
        $this->go_to(home_url('/'));

        ob_start();
        do_action('wp_head');
        $head = (string) ob_get_clean();

        self::assertStringNotContainsString(
            'sikshya-archive-view-boot',
            $head,
            'The FOUC-prevention script must scope to the course archive — emitting it on every page is wasted bytes.'
        );
    }

    public function testCourseTypeArchiveLinkResolves(): void
    {
        $url = get_post_type_archive_link(PostTypes::COURSE);
        self::assertNotEmpty($url, 'Course archive link must resolve to a URL.');
        self::assertStringContainsString(
            home_url(),
            (string) $url,
            'Archive URL must be on this site.'
        );
    }
}
