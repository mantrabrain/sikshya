<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\PostTypes\PostTypeManager;
use Sikshya\Services\LessonAttachments;
use WP_UnitTestCase;

/**
 * Lesson attachments: the storage and read path behind the advertised
 * `lesson_attachments` feature.
 *
 * The meta is REST-writable, so these tests lean on the cases a real site hits
 * rather than the happy path only: ids that arrive as strings, ids for posts
 * that are not attachments, ids for files that have since been deleted, and the
 * un-prefixed key the old frontend code read.
 */
final class LessonAttachmentsIntegrationTest extends WP_UnitTestCase
{
    private function makeLesson(): int
    {
        return (int) self::factory()->post->create(['post_type' => PostTypes::LESSON]);
    }

    private function makeAttachment(string $title = 'Worksheet'): int
    {
        return (int) self::factory()->attachment->create_object([
            'file' => 'worksheet.pdf',
            'post_title' => $title,
            'post_mime_type' => 'application/pdf',
            'post_status' => 'inherit',
        ]);
    }

    public function test_a_lesson_with_no_attachments_reads_as_empty(): void
    {
        $this->assertSame([], LessonAttachments::idsFor($this->makeLesson()));
        $this->assertSame([], LessonAttachments::idsFor(0));
    }

    public function test_round_trips_attachment_ids_in_author_order(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment('A');
        $b = $this->makeAttachment('B');

        $stored = LessonAttachments::set($lesson, [$b, $a]);

        $this->assertSame([$b, $a], $stored, 'author order must be preserved');
        $this->assertSame([$b, $a], LessonAttachments::idsFor($lesson));
    }

    public function test_numeric_strings_from_a_rest_write_are_accepted(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment();

        $this->assertSame([$a], LessonAttachments::set($lesson, [(string) $a]));
    }

    public function test_duplicates_are_collapsed(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment();

        $this->assertSame([$a], LessonAttachments::set($lesson, [$a, $a, (string) $a]));
    }

    /**
     * Without this, a lesson could "attach" a course, a page, or an arbitrary
     * post id, and the renderer would emit a link to it.
     */
    public function test_ids_that_are_not_attachments_are_rejected(): void
    {
        $lesson = $this->makeLesson();
        $course = (int) self::factory()->post->create(['post_type' => PostTypes::COURSE]);
        $a = $this->makeAttachment();

        $this->assertSame([$a], LessonAttachments::set($lesson, [$course, $a, 999999]));
    }

    public function test_reads_the_legacy_unprefixed_key_when_the_canonical_one_is_empty(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment();

        // The key the old frontend controller read from.
        update_post_meta($lesson, 'sikshya_lesson_attachments', [$a]);

        $this->assertSame([$a], LessonAttachments::idsFor($lesson));
    }

    public function test_the_canonical_key_wins_over_the_legacy_one(): void
    {
        $lesson = $this->makeLesson();
        $canonical = $this->makeAttachment('canonical');
        $legacy = $this->makeAttachment('legacy');

        update_post_meta($lesson, 'sikshya_lesson_attachments', [$legacy]);
        LessonAttachments::set($lesson, [$canonical]);

        $this->assertSame([$canonical], LessonAttachments::idsFor($lesson));
    }

    public function test_a_deleted_attachment_drops_out_of_the_read(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment('kept');
        $b = $this->makeAttachment('deleted');
        LessonAttachments::set($lesson, [$a, $b]);

        wp_delete_attachment($b, true);

        $this->assertSame([$a], LessonAttachments::idsFor($lesson));
    }

    public function test_for_display_exposes_a_title_and_a_url(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment('Course workbook');
        LessonAttachments::set($lesson, [$a]);

        $rows = LessonAttachments::forDisplay($lesson, 1);

        $this->assertCount(1, $rows);
        $this->assertSame($a, $rows[0]['id']);
        $this->assertSame('Course workbook', $rows[0]['title']);
        $this->assertNotSame('', $rows[0]['url'], 'a download URL must be issued');
        $this->assertSame('application/pdf', $rows[0]['mime']);
    }

    /**
     * Attachment protection is on by default, so the URL handed to a learner
     * must not be the bare uploads path.
     */
    public function test_for_display_issues_a_protected_url_by_default(): void
    {
        $lesson = $this->makeLesson();
        $a = $this->makeAttachment();
        LessonAttachments::set($lesson, [$a]);

        $raw = (string) wp_get_attachment_url($a);
        $rows = LessonAttachments::forDisplay($lesson, 7);

        $this->assertNotSame($raw, $rows[0]['url'], 'the raw uploads URL must not be handed out');
    }

    public function test_for_display_falls_back_to_the_filename_when_the_title_is_blank(): void
    {
        $lesson = $this->makeLesson();
        $a = (int) self::factory()->attachment->create_object([
            'file' => 'slides-deck.pdf',
            'post_title' => '',
            'post_mime_type' => 'application/pdf',
            'post_status' => 'inherit',
        ]);
        LessonAttachments::set($lesson, [$a]);

        $rows = LessonAttachments::forDisplay($lesson, 1);

        $this->assertSame('slides-deck.pdf', $rows[0]['title']);
    }

    public function test_the_meta_is_rest_registered_for_lessons(): void
    {
        // Registration runs on `init`, and WP_UnitTestCase re-registers post
        // types between tests, which clears the meta registry for them. Invoke
        // the registration path directly so this asserts the plugin's wiring
        // rather than the harness's teardown order.
        (new PostTypeManager())->registerRestAccessiblePostMeta();

        $registered = get_registered_meta_keys('post', PostTypes::LESSON);

        $this->assertArrayHasKey(
            LessonAttachments::META_KEY,
            $registered,
            'the lesson editor writes this over the REST meta API, so it must be registered'
        );
        $this->assertNotEmpty(
            $registered[LessonAttachments::META_KEY]['show_in_rest'],
            'the key must be exposed in REST for the editor to read and write it'
        );
    }

    /**
     * The registered sanitize callback is the last line of defence for a write
     * that bypasses the service. Asserting on the *raw* stored value is what
     * distinguishes "the callback ran" from "the reader cleaned up after it".
     */
    public function test_a_rest_meta_write_is_sanitized_on_the_way_in(): void
    {
        (new PostTypeManager())->registerRestAccessiblePostMeta();

        $lesson = $this->makeLesson();
        $a = $this->makeAttachment();
        $course = (int) self::factory()->post->create(['post_type' => PostTypes::COURSE]);

        update_post_meta($lesson, LessonAttachments::META_KEY, [$a, $course, 'nonsense']);

        $this->assertSame(
            [$a],
            get_post_meta($lesson, LessonAttachments::META_KEY, true),
            'the sanitize callback must drop ids that are not attachments before they are stored'
        );
    }

    public function test_supports_only_lessons(): void
    {
        $lesson = $this->makeLesson();
        $course = (int) self::factory()->post->create(['post_type' => PostTypes::COURSE]);

        $this->assertTrue(LessonAttachments::supports($lesson));
        $this->assertFalse(LessonAttachments::supports($course));
        $this->assertFalse(LessonAttachments::supports(0));
    }
}
