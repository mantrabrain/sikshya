<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Api\AttachmentProxyRoutes;
use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\LessonLearnContent;
use Sikshya\Security\AttachmentTokenService;
use Sikshya\Services\LessonDocument;
use WP_UnitTestCase;

/**
 * PDF lessons: document resolution, inline delivery, byte ranges, rendering.
 *
 * Three things here are worth more than the feature itself and are pinned
 * hardest:
 *
 *   1. **Inline disposition is a signed claim.** A URL parameter would let a
 *      learner ask the proxy to *render* an arbitrary upload from our origin,
 *      which for an `.html` or `.svg` file is stored XSS. So the claim lives
 *      inside the HMAC payload, and the proxy additionally refuses to render
 *      anything outside a MIME allowlist.
 *   2. **The stored id is not trusted to be a PDF.** It is REST-writable and a
 *      media picker will hand back anything.
 *   3. **An external URL lands in an iframe `src`.** `javascript:` there runs
 *      in the page.
 *
 * @group pdf_lessons
 */
final class PdfLessonIntegrationTest extends WP_UnitTestCase
{
    /** @var string[] Files written into uploads/ by this test. */
    private array $files = [];

    public function tear_down(): void
    {
        foreach ($this->files as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->files = [];
        wp_set_current_user(0);
        unset($_SERVER['HTTP_RANGE']);
        parent::tear_down();
    }

    // ---------------------------------------------------------------- helpers

    private function makeAttachment(string $mime, string $bytes, string $title = 'Handbook'): int
    {
        $ext = $mime === 'application/pdf' ? 'pdf' : 'bin';
        $dir = wp_upload_dir();
        $path = rtrim($dir['path'], '/') . '/sikshya-test-' . wp_generate_password(8, false) . '.' . $ext;
        if (!is_dir(dirname($path))) {
            wp_mkdir_p(dirname($path));
        }
        file_put_contents($path, $bytes);
        $this->files[] = $path;

        $id = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => $title,
            'post_status' => 'inherit',
        ], $path);

        return (int) $id;
    }

    private function makePdfLesson(array $meta = []): int
    {
        $lesson_id = (int) self::factory()->post->create([
            'post_type' => PostTypes::LESSON,
            'post_title' => 'Employee handbook',
            'post_status' => 'publish',
            // Explicitly empty: the factory's default filler content would
            // make every lesson here look like it has a body.
            'post_content' => '',
        ]);
        update_post_meta($lesson_id, '_sikshya_lesson_type', 'pdf');
        foreach ($meta as $key => $value) {
            update_post_meta($lesson_id, $key, $value);
        }

        return $lesson_id;
    }

    private function disposition(string $mime, string $claim): string
    {
        $routes = new AttachmentProxyRoutes(Plugin::getInstance());
        $method = new \ReflectionMethod($routes, 'dispositionFor');
        $method->setAccessible(true);

        return (string) $method->invoke($routes, $mime, $claim);
    }

    private function parseRange(string $header, int $size)
    {
        if ($header === '') {
            unset($_SERVER['HTTP_RANGE']);
        } else {
            $_SERVER['HTTP_RANGE'] = $header;
        }

        $routes = new AttachmentProxyRoutes(Plugin::getInstance());
        $method = new \ReflectionMethod($routes, 'requestedRange');
        $method->setAccessible(true);

        return $method->invoke($routes, $size);
    }

    // ------------------------------------------------------- document resolve

    public function test_a_lesson_with_no_document_resolves_to_nothing(): void
    {
        $lesson_id = $this->makePdfLesson();

        $this->assertFalse(LessonDocument::has($lesson_id));
        $this->assertNull(LessonDocument::forDisplay($lesson_id, 1));
    }

    public function test_an_attachment_resolves_with_a_signed_inline_view_url(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test', 'Employee handbook');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        $doc = LessonDocument::forDisplay($lesson_id, $uid);

        $this->assertNotNull($doc);
        $this->assertSame('attachment', $doc['source']);
        $this->assertSame('Employee handbook', $doc['title']);
        $this->assertGreaterThan(0, $doc['filesize']);
        $this->assertStringContainsString('sikshya/v1/file/', $doc['view_url']);
        // The raw uploads path must not reach the page at all.
        $this->assertStringNotContainsString('/uploads/', $doc['view_url']);

        $token = substr($doc['view_url'], strrpos($doc['view_url'], '/') + 1);
        $verified = AttachmentTokenService::verify($token);
        $this->assertIsArray($verified);
        $this->assertSame('inline', $verified['disposition']);
        $this->assertSame($att, $verified['attachment_id']);
        $this->assertSame($uid, $verified['user_id']);
    }

    /**
     * The download link is a separate token *without* the inline claim —
     * otherwise clicking Download would replace the page with a viewer.
     */
    public function test_the_download_link_is_not_an_inline_token(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        $doc = LessonDocument::forDisplay($lesson_id, $uid);
        $this->assertNotSame('', $doc['download_url']);

        $token = substr($doc['download_url'], strrpos($doc['download_url'], '/') + 1);
        $verified = AttachmentTokenService::verify($token);
        $this->assertSame('attachment', $verified['disposition']);
    }

    public function test_hiding_the_download_link_leaves_the_viewer_working(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_ID => $att,
            LessonDocument::META_HIDE_DOWNLOAD => '1',
        ]);

        $doc = LessonDocument::forDisplay($lesson_id, $uid);

        $this->assertSame('', $doc['download_url']);
        $this->assertNotSame('', $doc['view_url']);
    }

    public function test_an_attachment_that_is_not_a_pdf_resolves_to_nothing(): void
    {
        $att = $this->makeAttachment('image/png', 'not-really-a-png');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        $this->assertSame(0, LessonDocument::attachmentIdFor($lesson_id));
        $this->assertFalse(LessonDocument::has($lesson_id));
    }

    public function test_an_id_that_is_not_an_attachment_resolves_to_nothing(): void
    {
        $post_id = (int) self::factory()->post->create();
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $post_id]);

        $this->assertSame(0, LessonDocument::attachmentIdFor($lesson_id));
    }

    /**
     * `post_mime_type` is a column on every post row, not just attachments, so
     * the MIME check alone would accept an ordinary post that happens to carry
     * the right value — and the file lookup behind it would then come back
     * empty. Both checks have to be there.
     */
    public function test_a_post_wearing_a_pdf_mime_type_is_still_not_an_attachment(): void
    {
        $post_id = (int) self::factory()->post->create(['post_mime_type' => 'application/pdf']);
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $post_id]);

        $this->assertSame('application/pdf', (string) get_post_mime_type($post_id));
        $this->assertSame(0, LessonDocument::attachmentIdFor($lesson_id));
        $this->assertNull(LessonDocument::forDisplay($lesson_id, 1));
    }

    /**
     * An attachment row can outlive its file reference — a half-finished
     * upload, a broken migration. There is then no URL to point a frame at,
     * and an empty frame is worse than an empty lesson.
     */
    public function test_an_attachment_with_no_file_behind_it_resolves_to_nothing(): void
    {
        $att = (int) wp_insert_attachment([
            'post_mime_type' => 'application/pdf',
            'post_title' => 'Lost handbook',
            'post_status' => 'inherit',
        ]);
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        // Not merely unrenderable — unresolvable. `wp_get_attachment_url()`
        // falls back to the post GUID when the file reference is gone, so
        // without this guard the page gets a frame pointed at a dead URL.
        $this->assertSame(0, LessonDocument::attachmentIdFor($lesson_id));
        $this->assertFalse(LessonDocument::has($lesson_id));
        $this->assertNull(LessonDocument::forDisplay($lesson_id, 0));
        $this->assertNull(LessonDocument::forDisplay($lesson_id, 1));
        $this->assertSame('', LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf'));
    }

    public function test_a_deleted_attachment_resolves_to_nothing(): void
    {
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);
        wp_delete_attachment($att, true);

        $this->assertSame(0, LessonDocument::attachmentIdFor($lesson_id));
        $this->assertNull(LessonDocument::forDisplay($lesson_id, 1));
    }

    /**
     * Media offload plugins filter `wp_get_attachment_url`, and one that is
     * mid-migration can hand back nothing at all. A frame with an empty `src`
     * reloads the lesson page inside itself, so there has to be no frame.
     */
    public function test_an_attachment_whose_url_resolves_to_nothing_is_refused(): void
    {
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);
        add_filter('wp_get_attachment_url', '__return_empty_string');
        // Signing is what would otherwise paper over the empty URL, so the
        // guest path (no signing) is the one that exercises it.
        add_filter('sikshya_protect_attachments', '__return_false');

        try {
            $this->assertNull(LessonDocument::forDisplay($lesson_id, 0));
            $this->assertSame('', LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf'));
        } finally {
            remove_filter('wp_get_attachment_url', '__return_empty_string');
            remove_filter('sikshya_protect_attachments', '__return_false');
        }
    }

    public function test_an_external_url_resolves_as_its_own_source(): void
    {
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_URL => 'https://example.com/handbook.pdf',
        ]);

        $doc = LessonDocument::forDisplay($lesson_id, 1);

        $this->assertSame('url', $doc['source']);
        $this->assertSame('https://example.com/handbook.pdf', $doc['view_url']);
        $this->assertSame('https://example.com/handbook.pdf', $doc['download_url']);
        $this->assertSame(0, $doc['filesize']);
    }

    /**
     * This value is written into an iframe `src`. A `javascript:` URL there
     * executes in the page, with the learner's session.
     */
    public function test_a_non_http_document_url_is_refused(): void
    {
        foreach (
            [
                'javascript:alert(document.cookie)',
                'JavaScript:alert(1)',
                'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
                'vbscript:msgbox(1)',
                '//example.com/handbook.pdf',
                'handbook.pdf',
            ] as $candidate
        ) {
            $lesson_id = $this->makePdfLesson([LessonDocument::META_URL => $candidate]);

            $this->assertSame(
                '',
                LessonDocument::externalUrlFor($lesson_id),
                sprintf('"%s" must not be accepted as a document URL.', $candidate)
            );
            $this->assertNull(LessonDocument::forDisplay($lesson_id, 1));
        }
    }

    public function test_an_attachment_wins_over_a_url_when_both_are_set(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_ID => $att,
            LessonDocument::META_URL => 'https://example.com/other.pdf',
        ]);

        $doc = LessonDocument::forDisplay($lesson_id, $uid);

        $this->assertSame('attachment', $doc['source']);
        $this->assertStringNotContainsString('example.com', $doc['view_url']);
    }

    /**
     * A guest reading a free-preview lesson still has to see the document. The
     * token service cannot bind a URL to nobody, so it falls back to the public
     * attachment URL rather than returning nothing.
     */
    public function test_a_guest_gets_a_usable_url_rather_than_nothing(): void
    {
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        $doc = LessonDocument::forDisplay($lesson_id, 0);

        $this->assertNotNull($doc);
        $this->assertNotSame('', $doc['view_url']);
    }

    // -------------------------------------------------------- the token claim

    public function test_a_token_without_an_inline_claim_is_a_download(): void
    {
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $uid = (int) self::factory()->user->create();

        $url = AttachmentTokenService::signedUrlFor($att, $uid);
        $token = substr($url, strrpos($url, '/') + 1);

        $this->assertSame('attachment', AttachmentTokenService::verify($token)['disposition']);
    }

    /**
     * Backward compatibility: tokens minted before inline delivery existed have
     * no `dsp` field at all, and must still verify and still download.
     */
    public function test_a_token_predating_inline_delivery_still_verifies(): void
    {
        $payload = ['att' => 7, 'uid' => 3, 'exp' => time() + 600];
        $token = $this->mintRaw($payload);

        $verified = AttachmentTokenService::verify($token);

        $this->assertIsArray($verified);
        $this->assertSame('attachment', $verified['disposition']);
    }

    public function test_an_unrecognised_disposition_claim_falls_back_to_download(): void
    {
        $token = $this->mintRaw(['att' => 7, 'uid' => 3, 'exp' => time() + 600, 'dsp' => 'INLINE']);

        $this->assertSame('attachment', AttachmentTokenService::verify($token)['disposition']);

        $token = $this->mintRaw(['att' => 7, 'uid' => 3, 'exp' => time() + 600, 'dsp' => ['inline']]);

        $this->assertSame('attachment', AttachmentTokenService::verify($token)['disposition']);
    }

    /**
     * The whole reason the claim is inside the signed payload: editing it has
     * to break the signature, not change the behaviour.
     */
    public function test_adding_an_inline_claim_to_a_signed_token_invalidates_it(): void
    {
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $uid = (int) self::factory()->user->create();
        $url = AttachmentTokenService::signedUrlFor($att, $uid);
        $token = substr($url, strrpos($url, '/') + 1);
        [$encoded, $sig] = explode('.', $token, 2);

        $decoded = json_decode($this->base64UrlDecode($encoded), true);
        $decoded['dsp'] = 'inline';
        $tampered = $this->base64UrlEncode((string) wp_json_encode($decoded)) . '.' . $sig;

        $result = AttachmentTokenService::verify($tampered);

        $this->assertWPError($result);
        $this->assertSame('bad_token', $result->get_error_code());
    }

    /**
     * Inline delivery hands the browser bytes to *render* from our own origin.
     * The allowlist is the only thing standing between that and stored XSS, so
     * assert what it must never grow.
     */
    public function test_only_non_executable_types_may_be_served_inline(): void
    {
        $allowlist = (new \ReflectionClass(AttachmentProxyRoutes::class))
            ->getConstant('INLINE_SAFE_MIME');

        $this->assertSame(['application/pdf'], $allowlist);

        foreach (
            [
                'text/html',
                'image/svg+xml',
                'application/xhtml+xml',
                'text/xml',
                'application/xml',
                'application/octet-stream',
                'text/plain',
            ] as $forbidden
        ) {
            $this->assertNotContains(
                $forbidden,
                $allowlist,
                sprintf('%s must never be served with Content-Disposition: inline.', $forbidden)
            );
        }
    }

    public function test_a_pdf_with_an_inline_token_is_rendered(): void
    {
        $this->assertSame('inline', $this->disposition('application/pdf', 'inline'));
    }

    /**
     * Both halves of the decision are load-bearing, so both are pinned: a
     * signed inline token for an executable type is still a download, and an
     * allowlisted type without the claim is still a download.
     */
    public function test_an_inline_token_cannot_render_an_executable_type(): void
    {
        foreach (['text/html', 'image/svg+xml', 'application/xml', 'text/plain'] as $mime) {
            $this->assertSame(
                'attachment',
                $this->disposition($mime, 'inline'),
                sprintf('%s must be downloaded even with a valid inline token.', $mime)
            );
        }
    }

    public function test_a_pdf_without_an_inline_token_is_still_a_download(): void
    {
        $this->assertSame('attachment', $this->disposition('application/pdf', 'attachment'));
        $this->assertSame('attachment', $this->disposition('application/pdf', ''));
        $this->assertSame('attachment', $this->disposition('application/pdf', 'INLINE'));
    }

    // ------------------------------------------------------------ byte ranges

    public function test_no_range_header_means_send_the_whole_file(): void
    {
        $this->assertNull($this->parseRange('', 1000));
    }

    public function test_a_plain_range_is_served_as_asked(): void
    {
        $this->assertSame([0, 99], $this->parseRange('bytes=0-99', 1000));
        $this->assertSame([100, 199], $this->parseRange('bytes=100-199', 1000));
    }

    public function test_a_single_byte_range_is_valid(): void
    {
        $this->assertSame([0, 0], $this->parseRange('bytes=0-0', 1000));
        $this->assertSame([999, 999], $this->parseRange('bytes=999-999', 1000));
    }

    public function test_an_open_ended_range_runs_to_the_last_byte(): void
    {
        $this->assertSame([100, 999], $this->parseRange('bytes=100-', 1000));
    }

    public function test_a_suffix_range_counts_back_from_the_end(): void
    {
        $this->assertSame([950, 999], $this->parseRange('bytes=-50', 1000));
    }

    /**
     * A PDF viewer's first request is often a suffix range for the cross
     * reference table, and it guesses the length. Asking for more than exists
     * is the normal case, not an error.
     */
    public function test_a_suffix_range_larger_than_the_file_is_the_whole_file(): void
    {
        $this->assertSame([0, 999], $this->parseRange('bytes=-99999', 1000));
    }

    public function test_an_end_past_the_file_is_clamped(): void
    {
        $this->assertSame([5, 999], $this->parseRange('bytes=5-99999', 1000));
    }

    public function test_an_unsatisfiable_range_is_rejected(): void
    {
        $this->assertFalse($this->parseRange('bytes=1000-', 1000));
        $this->assertFalse($this->parseRange('bytes=5000-6000', 1000));
        $this->assertFalse($this->parseRange('bytes=50-10', 1000));
        $this->assertFalse($this->parseRange('bytes=-0', 1000));
    }

    /**
     * Anything we do not understand must mean "send it all" rather than a
     * wrong slice — a 206 carrying the wrong bytes is a corrupt download,
     * while a 200 carrying everything is merely less efficient.
     */
    public function test_a_range_we_do_not_support_sends_the_whole_file(): void
    {
        $this->assertNull($this->parseRange('bytes=0-9,20-29', 1000), 'multi-range');
        $this->assertNull($this->parseRange('items=0-9', 1000), 'unknown unit');
        $this->assertNull($this->parseRange('bytes=abc-def', 1000), 'non-numeric');
        // Each half is guarded separately: a digit on one side must not carry
        // a non-digit on the other into a wrong slice.
        $this->assertNull($this->parseRange('bytes=abc-99', 1000), 'non-numeric start');
        $this->assertNull($this->parseRange('bytes=0-abc', 1000), 'non-numeric end');
        $this->assertNull($this->parseRange('bytes=-abc', 1000), 'non-numeric suffix');
        $this->assertNull($this->parseRange('bytes=', 1000), 'empty spec');
        $this->assertNull($this->parseRange('bytes=-', 1000), 'no numbers');
        $this->assertNull($this->parseRange('bytes=0-99', 0), 'empty file');
    }

    // --------------------------------------------------------------- rendering

    public function test_a_pdf_lesson_renders_a_viewer_and_a_way_out_of_it(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);
        $lesson = get_post($lesson_id);

        $html = LessonLearnContent::primaryMediaHtml($lesson, 'pdf');

        $this->assertStringContainsString('sikshya-lesson-embed--pdf', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('sikshya/v1/file/', $html);
        // The frame is never the only way in: embedded PDF support is uneven
        // and fails silently, so the link row is permanent.
        $this->assertStringContainsString('Open the document in a new tab', $html);
        $this->assertStringContainsString('download', $html);
        $this->assertTrue(LessonLearnContent::hasRenderableBody($lesson, 'pdf'));
    }

    public function test_hiding_the_download_link_removes_it_from_the_markup(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_ID => $att,
            LessonDocument::META_HIDE_DOWNLOAD => '1',
        ]);

        $html = LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf');

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString(' download>', $html);
        $this->assertStringContainsString('Open the document in a new tab', $html);
    }

    /**
     * An author who picks the type and attaches nothing must get an empty
     * lesson, not an empty grey frame.
     */
    public function test_a_pdf_lesson_with_no_document_renders_nothing(): void
    {
        $lesson_id = $this->makePdfLesson();
        $lesson = get_post($lesson_id);

        $this->assertSame('', LessonLearnContent::primaryMediaHtml($lesson, 'pdf'));
        $this->assertFalse(LessonLearnContent::hasRenderableBody($lesson, 'pdf'));
    }

    public function test_prose_alone_still_counts_as_a_body(): void
    {
        $lesson_id = $this->makePdfLesson();
        wp_update_post(['ID' => $lesson_id, 'post_content' => 'Read the handout in class.']);

        $this->assertTrue(LessonLearnContent::hasRenderableBody(get_post($lesson_id), 'pdf'));
    }

    public function test_an_external_document_is_framed_from_its_own_host(): void
    {
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_URL => 'https://example.com/handbook.pdf',
        ]);

        $html = LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf');

        $this->assertStringContainsString('https://example.com/handbook.pdf', $html);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function test_a_hostile_document_url_renders_no_frame_at_all(): void
    {
        $lesson_id = $this->makePdfLesson([
            LessonDocument::META_URL => 'javascript:alert(document.cookie)',
        ]);

        $html = LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf');

        $this->assertSame('', $html);
    }

    /**
     * The learn template runs this markup through kses. A sanitizer that strips
     * the frame would turn the feature into a blank panel.
     */
    public function test_the_viewer_survives_the_lesson_sanitizer(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('application/pdf', '%PDF-1.4 test');
        $lesson_id = $this->makePdfLesson([LessonDocument::META_ID => $att]);

        $html = LessonLearnContent::ksesLessonBody(
            LessonLearnContent::primaryMediaHtml(get_post($lesson_id), 'pdf')
        );

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('sikshya-lesson-pdf__frame', $html);
        $this->assertStringContainsString('Open the document in a new tab', $html);
        $this->assertStringContainsString('download', $html);
    }

    // ------------------------------------------------------------ registration

    /**
     * Audio lessons shipped half-built because the meta was never registered
     * for REST, so the admin could not author one. Same trap, same guard.
     */
    public function test_the_document_meta_is_writable_through_rest(): void
    {
        // The WP test case unregisters every meta key in tear_down, so the
        // registrations from bootstrap are gone by the time this runs.
        (new \Sikshya\PostTypes\PostTypeManager())->registerRestAccessiblePostMeta();

        foreach (
            [
                LessonDocument::META_ID,
                LessonDocument::META_URL,
                LessonDocument::META_HIDE_DOWNLOAD,
            ] as $key
        ) {
            $this->assertTrue(
                registered_meta_key_exists('post', $key, PostTypes::LESSON),
                sprintf('%s must be registered on %s or the editor cannot save it.', $key, PostTypes::LESSON)
            );
        }
    }

    /**
     * Every curriculum surface draws its glyph from one function. A missing
     * `pdf` case there is invisible in review and shows up as a text-lesson
     * icon on a document.
     */
    public function test_a_pdf_lesson_has_its_own_curriculum_glyph(): void
    {
        // The learn-shell icon library is a template partial, not autoloaded.
        require_once dirname(__DIR__, 2) . '/templates/partials/learn-icons.php';

        $pdf = sikshya_curriculum_outline_row_type_icon_html('lesson', 'pdf');
        $text = sikshya_curriculum_outline_row_type_icon_html('lesson', 'text');

        $this->assertNotSame('', $pdf);
        $this->assertNotSame($text, $pdf);

        // The two helpers wrap the same paths in different <svg> attributes
        // (sizes and classes differ per surface), so compare the drawing.
        $paths = static function (string $svg): string {
            return (string) preg_replace('~^<svg[^>]*>|</svg>$~', '', $svg);
        };
        $this->assertSame(
            $paths($pdf),
            $paths(sikshya_learn_icon('pdf')),
            'Learn shell and curriculum glyphs must draw the same thing.'
        );
    }

    public function test_a_pdf_lesson_is_labelled_in_the_curriculum_outline(): void
    {
        $lesson_id = $this->makePdfLesson();
        $meta = \Sikshya\Frontend\Site\CurriculumOutlineMeta::itemMetaLine(get_post($lesson_id), 'lesson');

        $this->assertStringContainsString('PDF', $meta);
    }

    // ----------------------------------------------------------------- sizing

    public function test_an_unknown_file_size_is_not_printed_as_zero_bytes(): void
    {
        $this->assertSame('', LessonDocument::formatSize(0));
        $this->assertNotSame('', LessonDocument::formatSize(2048));
    }

    // ------------------------------------------------------------------ utils

    /** @param array<string, mixed> $payload */
    private function mintRaw(array $payload): string
    {
        $secret = new \ReflectionMethod(AttachmentTokenService::class, 'secret');
        $secret->setAccessible(true);
        $encoded = $this->base64UrlEncode((string) wp_json_encode($payload));

        return $encoded . '.' . hash_hmac('sha256', $encoded, (string) $secret->invoke(null));
    }

    private function base64UrlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
