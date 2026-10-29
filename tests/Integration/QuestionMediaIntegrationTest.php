<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Frontend\Site\QuizTemplateData;
use Sikshya\Security\AttachmentTokenService;
use Sikshya\Services\QuestionMedia;
use WP_UnitTestCase;

/**
 * Question media: resolution, delivery, and the markup a learner gets.
 *
 * Three things carry more weight than the feature:
 *
 *   1. **The kind is never guessed.** Rendering an audio file inside an
 *      `<img>` produces a broken-image icon where the question used to be, so
 *      an unrecognisable source resolves to nothing instead.
 *   2. **An image stem needs alt text or it is not a question.** The image is
 *      what is being asked about, so a learner using a screen reader has
 *      nothing to work from. The authoring UI refuses to save without it; this
 *      suite pins what the renderer does with the imports and older rows that
 *      the UI never saw.
 *   3. **No inline token.** `<img>` and `<audio>` ignore
 *      `Content-Disposition`, so question media rides the existing download
 *      token and the proxy's inline allowlist stays at one entry.
 *
 * @group question_media
 */
final class QuestionMediaIntegrationTest extends WP_UnitTestCase
{
    /** @var string[] */
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
        parent::tear_down();
    }

    // ---------------------------------------------------------------- helpers

    private function makeAttachment(string $mime, string $ext, string $title = 'Supply curve'): int
    {
        $dir = wp_upload_dir();
        $path = rtrim($dir['path'], '/') . '/sikshya-test-' . wp_generate_password(8, false) . '.' . $ext;
        if (!is_dir(dirname($path))) {
            wp_mkdir_p(dirname($path));
        }
        file_put_contents($path, 'test-bytes');
        $this->files[] = $path;

        return (int) wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => $title,
            'post_status' => 'inherit',
        ], $path);
    }

    /** @param array<string, mixed> $meta */
    private function makeQuestion(array $meta = [], string $title = 'What is the equilibrium price?'): int
    {
        $qid = (int) self::factory()->post->create([
            'post_type' => PostTypes::QUESTION,
            'post_title' => $title,
            'post_status' => 'publish',
        ]);
        update_post_meta($qid, '_sikshya_question_type', 'multiple_choice');
        update_post_meta($qid, '_sikshya_question_options', ['£10', '£12', '£14']);
        foreach ($meta as $key => $value) {
            update_post_meta($qid, $key, $value);
        }

        return $qid;
    }

    private function renderFieldset(int $qid, int $index = 0): string
    {
        $row = QuizTemplateData::buildQuestionViewRowForId($qid);
        $q = $row ?? [];
        $qi = $index;

        ob_start();
        require dirname(__DIR__, 2) . '/templates/partials/quiz-question-fieldset.php';

        return (string) ob_get_clean();
    }

    /**
     * Render the partial with a row the caller built, not one we resolved.
     * The partial is a template: a theme override or another renderer can hand
     * it any row, so its own guards are part of its contract.
     *
     * @param array<string, mixed> $row
     */
    private function renderFieldsetWithRow(array $row, int $index = 0): string
    {
        $q = $row;
        $qi = $index;

        ob_start();
        require dirname(__DIR__, 2) . '/templates/partials/quiz-question-fieldset.php';

        return (string) ob_get_clean();
    }

    private function tokenFrom(string $url): string
    {
        return substr($url, strrpos($url, '/') + 1);
    }

    // ------------------------------------------------------------- resolution

    public function test_a_question_without_media_resolves_to_nothing(): void
    {
        $qid = $this->makeQuestion();

        $this->assertFalse(QuestionMedia::has($qid));
        $this->assertNull(QuestionMedia::forDisplay($qid, 1));
    }

    public function test_an_image_attachment_resolves_as_an_image(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'A supply curve crossing demand at 40 units',
        ]);

        $media = QuestionMedia::forDisplay($qid, $uid);

        $this->assertSame('image', $media['kind']);
        $this->assertSame('attachment', $media['source']);
        $this->assertSame('A supply curve crossing demand at 40 units', $media['alt']);
        $this->assertStringContainsString('sikshya/v1/file/', $media['url']);
        $this->assertStringNotContainsString('/uploads/', $media['url']);
    }

    public function test_an_audio_attachment_resolves_as_audio(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('audio/mpeg', 'mp3', 'Heart murmur');
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

        $media = QuestionMedia::forDisplay($qid, $uid);

        $this->assertSame('audio', $media['kind']);
        $this->assertStringContainsString('sikshya/v1/file/', $media['url']);
    }

    /**
     * Question media rides the ordinary download token. `<img>` and `<audio>`
     * load a subresource, where `Content-Disposition` is not consulted, so
     * nothing here needs the proxy's inline allowlist — which decides what the
     * browser will parse *as a document* from our own origin — widened past
     * PDFs.
     */
    public function test_question_media_never_asks_for_inline_delivery(): void
    {
        $uid = (int) self::factory()->user->create();
        foreach ([['image/png', 'png'], ['audio/mpeg', 'mp3']] as [$mime, $ext]) {
            $att = $this->makeAttachment($mime, $ext);
            $qid = $this->makeQuestion([
                QuestionMedia::META_ID => $att,
                QuestionMedia::META_ALT => 'described',
            ]);

            $media = QuestionMedia::forDisplay($qid, $uid);
            $verified = AttachmentTokenService::verify($this->tokenFrom($media['url']));

            $this->assertSame(
                'attachment',
                $verified['disposition'],
                sprintf('%s must not be served with an inline token.', $mime)
            );
        }
    }

    public function test_alt_text_falls_back_to_the_media_library(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('image/png', 'png');
        update_post_meta($att, '_wp_attachment_image_alt', 'From the media library');
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

        $this->assertSame('From the media library', QuestionMedia::forDisplay($qid, $uid)['alt']);
    }

    public function test_the_questions_own_alt_text_wins(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('image/png', 'png');
        update_post_meta($att, '_wp_attachment_image_alt', 'Generic library description');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'What this question needs read off it',
        ]);

        $this->assertSame(
            'What this question needs read off it',
            QuestionMedia::forDisplay($qid, $uid)['alt']
        );
    }

    public function test_a_file_that_is_neither_image_nor_audio_is_refused(): void
    {
        foreach ([['application/pdf', 'pdf'], ['video/mp4', 'mp4'], ['text/html', 'html']] as [$mime, $ext]) {
            $att = $this->makeAttachment($mime, $ext);
            $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

            $this->assertSame(
                0,
                QuestionMedia::attachmentIdFor($qid),
                sprintf('%s is not question media.', $mime)
            );
            $this->assertNull(QuestionMedia::forDisplay($qid, 1));
        }
    }

    /**
     * A script inside an SVG does not run through `<img>`, but it does the
     * moment anyone opens the file in a tab — and "safe only while nobody
     * clicks it" is not a property to build on for an uploaded file.
     */
    public function test_svg_is_not_question_media(): void
    {
        $this->assertSame('', QuestionMedia::kindForMime('image/svg+xml'));
        $this->assertSame('image', QuestionMedia::kindForMime('image/png'));
        $this->assertSame('audio', QuestionMedia::kindForMime('audio/mpeg'));
        $this->assertSame('', QuestionMedia::kindForMime(''));
        $this->assertSame('', QuestionMedia::kindForMime('application/octet-stream'));
    }

    public function test_an_id_that_is_not_an_attachment_is_refused(): void
    {
        $post_id = (int) self::factory()->post->create(['post_mime_type' => 'image/png']);
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $post_id]);

        $this->assertSame(0, QuestionMedia::attachmentIdFor($qid));
    }

    public function test_an_attachment_with_no_file_reference_is_refused(): void
    {
        $att = (int) wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'Lost diagram',
            'post_status' => 'inherit',
        ]);
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

        $this->assertSame(0, QuestionMedia::attachmentIdFor($qid));
        $this->assertNull(QuestionMedia::forDisplay($qid, 0));
    }

    /**
     * Media offload plugins filter `wp_get_attachment_url`, and one that is
     * mid-migration can return nothing. An `<img src="">` re-requests the page
     * itself, so there has to be no image.
     */
    public function test_an_attachment_whose_url_resolves_to_nothing_is_refused(): void
    {
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'described',
        ]);
        add_filter('wp_get_attachment_url', '__return_empty_string');
        // Signing would otherwise paper over the empty URL, so the guest path
        // is the one that exercises this.
        add_filter('sikshya_protect_attachments', '__return_false');

        try {
            $this->assertNull(QuestionMedia::forDisplay($qid, 0));
            $this->assertStringNotContainsString('<img', $this->renderFieldset($qid));
        } finally {
            remove_filter('wp_get_attachment_url', '__return_empty_string');
            remove_filter('sikshya_protect_attachments', '__return_false');
        }
    }

    public function test_an_external_url_takes_its_kind_from_the_extension(): void
    {
        foreach (
            [
                'https://example.com/curve.png' => 'image',
                'https://example.com/curve.JPG' => 'image',
                'https://example.com/a/b/photo.webp' => 'image',
                'https://example.com/clip.mp3' => 'audio',
                'https://example.com/clip.m4a' => 'audio',
                'https://example.com/clip.wav?v=2' => 'audio',
                'https://example.com/curve.png#fig1' => 'image',
            ] as $url => $expected
        ) {
            $qid = $this->makeQuestion([QuestionMedia::META_URL => $url]);
            $media = QuestionMedia::forDisplay($qid, 1);

            $this->assertNotNull($media, $url);
            $this->assertSame($expected, $media['kind'], $url);
            $this->assertSame('url', $media['source']);
            $this->assertSame($url, $media['url']);
        }
    }

    /**
     * Guessing would mean rendering an audio file inside an `<img>`, which
     * replaces the question with a broken-image icon.
     */
    public function test_a_url_with_no_usable_extension_needs_a_stated_kind(): void
    {
        $qid = $this->makeQuestion([QuestionMedia::META_URL => 'https://cdn.example.com/asset/8812']);
        $this->assertNull(QuestionMedia::externalFor($qid));
        $this->assertNull(QuestionMedia::forDisplay($qid, 1));

        update_post_meta($qid, QuestionMedia::META_KIND, 'audio');
        $this->assertSame('audio', QuestionMedia::forDisplay($qid, 1)['kind']);
    }

    public function test_an_unrecognised_stated_kind_is_refused(): void
    {
        foreach (['video', 'pdf', 'picture', '1', ''] as $kind) {
            $qid = $this->makeQuestion([
                QuestionMedia::META_URL => 'https://cdn.example.com/asset/8812',
                QuestionMedia::META_KIND => $kind,
            ]);

            $this->assertNull(
                QuestionMedia::externalFor($qid),
                sprintf('kind "%s" must not be accepted.', $kind)
            );
        }
    }

    /**
     * A stated kind goes through `sanitize_key()`, so case and stray
     * whitespace are normalised rather than rejected — an import writing
     * "Image" meant `image`.
     */
    public function test_a_stated_kind_is_normalised_not_rejected(): void
    {
        foreach (['IMAGE ', ' Audio', 'Image'] as $kind) {
            $qid = $this->makeQuestion([
                QuestionMedia::META_URL => 'https://cdn.example.com/asset/8812',
                QuestionMedia::META_KIND => $kind,
            ]);

            $resolved = QuestionMedia::externalFor($qid);
            $this->assertNotNull($resolved, $kind);
            $this->assertSame(strtolower(trim($kind)), $resolved['kind']);
        }
    }

    /**
     * The extension is the stronger signal: a stated kind must not be able to
     * show a `.mp3` as a picture.
     */
    public function test_the_extension_beats_a_contradicting_stated_kind(): void
    {
        $qid = $this->makeQuestion([
            QuestionMedia::META_URL => 'https://example.com/clip.mp3',
            QuestionMedia::META_KIND => 'image',
        ]);

        $this->assertSame('audio', QuestionMedia::forDisplay($qid, 1)['kind']);
    }

    /** This value lands in a `src` attribute. */
    public function test_a_non_http_media_url_is_refused(): void
    {
        foreach (
            [
                'javascript:alert(document.cookie)',
                'JavaScript:alert(1).png',
                'data:image/svg+xml;base64,PHN2Zz48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PC9zdmc+',
                'vbscript:msgbox(1)',
                '//example.com/curve.png',
                'curve.png',
            ] as $candidate
        ) {
            $qid = $this->makeQuestion([QuestionMedia::META_URL => $candidate]);

            $this->assertNull(
                QuestionMedia::externalFor($qid),
                sprintf('"%s" must not be accepted.', $candidate)
            );
            $this->assertNull(QuestionMedia::forDisplay($qid, 1));
        }
    }

    public function test_an_attachment_wins_over_a_url(): void
    {
        $uid = (int) self::factory()->user->create();
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_URL => 'https://example.com/other.png',
            QuestionMedia::META_ALT => 'described',
        ]);

        $media = QuestionMedia::forDisplay($qid, $uid);

        $this->assertSame('attachment', $media['source']);
        $this->assertStringNotContainsString('example.com', $media['url']);
    }

    public function test_a_guest_on_a_free_preview_still_gets_a_url(): void
    {
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'described',
        ]);

        $media = QuestionMedia::forDisplay($qid, 0);

        $this->assertNotNull($media);
        $this->assertNotSame('', $media['url']);
    }

    // --------------------------------------------------------------- view row

    public function test_the_view_row_carries_the_media_when_there_is_some(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'described',
        ]);

        $row = QuizTemplateData::buildQuestionViewRowForId($qid);

        $this->assertArrayHasKey('media', $row);
        $this->assertSame('image', $row['media']['kind']);
    }

    /**
     * Absent rather than null, so every existing consumer of this row — the
     * template, the learner REST payload, Pro's question-bank runtime — keeps
     * the shape it already handled.
     */
    public function test_the_view_row_omits_the_key_when_there_is_no_media(): void
    {
        $row = QuizTemplateData::buildQuestionViewRowForId($this->makeQuestion());

        $this->assertArrayNotHasKey('media', $row);
    }

    /** The row is learner-facing: it must not leak the answer key. */
    public function test_the_view_row_still_exposes_no_correct_answer(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'described',
            '_sikshya_question_correct_answer' => '1',
        ]);

        $row = QuizTemplateData::buildQuestionViewRowForId($qid);

        $this->assertArrayNotHasKey('correct_answer', $row);
        $this->assertStringNotContainsString('correct', strtolower(wp_json_encode($row)));
    }

    // -------------------------------------------------------------- rendering

    public function test_an_image_question_renders_an_img_with_its_description(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'A supply curve crossing demand at 40 units',
        ]);

        $html = $this->renderFieldset($qid);

        $this->assertStringContainsString('sikshya-quizQ__media--image', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="A supply curve crossing demand at 40 units"', $html);
        // Still a question: the options have to be there too.
        $this->assertStringContainsString('type="radio"', $html);
    }

    public function test_an_audio_question_renders_a_player(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('audio/mpeg', 'mp3');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => 'A 20-second clip of a heart murmur',
        ]);

        $html = $this->renderFieldset($qid);

        $this->assertStringContainsString('sikshya-quizQ__media--audio', $html);
        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('controls', $html);
        $this->assertStringContainsString('aria-label="A 20-second clip of a heart murmur"', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * The player must still be identifiable when an author wrote no
     * description — unlike an image, audio has no answer-giving risk in a
     * generated name.
     */
    public function test_an_undescribed_player_is_still_named(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('audio/mpeg', 'mp3');
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

        $html = $this->renderFieldset($qid, 2);

        $this->assertStringContainsString('aria-label="Audio for question 3"', $html);
    }

    /**
     * An image with no description is marked decorative rather than given the
     * filename: a screen reader announcing "diagram-final-v2.png" is worse
     * than silence, and only the authoring UI can actually fix it.
     */
    public function test_an_undescribed_image_is_marked_decorative(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('image/png', 'png', 'diagram-final-v2');
        $qid = $this->makeQuestion([QuestionMedia::META_ID => $att]);

        $html = $this->renderFieldset($qid);

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringNotContainsString('diagram-final-v2', $html);
    }

    public function test_a_question_without_media_renders_no_media_block(): void
    {
        $html = $this->renderFieldset($this->makeQuestion());

        $this->assertStringNotContainsString('sikshya-quizQ__media', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('type="radio"', $html);
    }

    public function test_a_hostile_media_url_renders_no_media_block(): void
    {
        $qid = $this->makeQuestion([QuestionMedia::META_URL => 'javascript:alert(document.cookie)']);

        $html = $this->renderFieldset($qid);

        $this->assertStringNotContainsString('sikshya-quizQ__media', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /** Alt text is author-supplied and lands in an attribute. */
    public function test_a_description_cannot_break_out_of_its_attribute(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);
        $att = $this->makeAttachment('image/png', 'png');
        $qid = $this->makeQuestion([
            QuestionMedia::META_ID => $att,
            QuestionMedia::META_ALT => '"><script>alert(1)</script>',
        ]);

        $html = $this->renderFieldset($qid);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * A kind the partial does not know how to draw must produce nothing. It
     * cannot come from `forDisplay()`, which only ever returns image or audio
     * — but the partial is a template anyone can call with any row, and
     * falling through to `<img>` would put a broken-image icon where the
     * question used to be.
     */
    public function test_the_partial_refuses_a_kind_it_cannot_draw(): void
    {
        foreach (['video', '', 'IMAGE', 'audio/mpeg'] as $kind) {
            $html = $this->renderFieldsetWithRow([
                'id' => $this->makeQuestion(),
                'type' => 'multiple_choice',
                'title' => 'Which one?',
                'options' => ['a', 'b'],
                'media' => [
                    'kind' => $kind,
                    'source' => 'url',
                    'url' => 'https://example.com/thing.bin',
                    'alt' => 'described',
                ],
            ]);

            $this->assertStringNotContainsString(
                'sikshya-quizQ__media',
                $html,
                sprintf('kind "%s" must not be drawn.', $kind)
            );
            $this->assertStringContainsString('type="radio"', $html, 'the question itself still renders');
        }
    }

    public function test_the_partial_ignores_a_media_row_with_no_url(): void
    {
        $html = $this->renderFieldsetWithRow([
            'id' => $this->makeQuestion(),
            'type' => 'multiple_choice',
            'title' => 'Which one?',
            'options' => ['a', 'b'],
            'media' => ['kind' => 'image', 'source' => 'attachment', 'url' => '', 'alt' => 'described'],
        ]);

        $this->assertStringNotContainsString('sikshya-quizQ__media', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    // ------------------------------------------------------------ registration

    public function test_the_media_meta_is_writable_through_rest(): void
    {
        (new \Sikshya\PostTypes\PostTypeManager())->registerRestAccessiblePostMeta();

        foreach (
            [
                QuestionMedia::META_ID,
                QuestionMedia::META_URL,
                QuestionMedia::META_KIND,
                QuestionMedia::META_ALT,
            ] as $key
        ) {
            $this->assertTrue(
                registered_meta_key_exists('post', $key, PostTypes::QUESTION),
                sprintf('%s must be registered or the editor cannot save it.', $key)
            );
        }
    }

    public function test_only_questions_carry_question_media(): void
    {
        $this->assertTrue(QuestionMedia::supports($this->makeQuestion()));
        $this->assertFalse(QuestionMedia::supports((int) self::factory()->post->create()));
        $this->assertFalse(QuestionMedia::supports(0));
    }
}
