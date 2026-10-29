<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Security\AttachmentTokenService;

/**
 * One image or audio clip attached to a quiz question's stem.
 *
 * "Listen to this and answer", "what is wrong with this diagram" — a whole
 * class of question cannot be asked in text, and the question title is stored
 * and rendered as plain text on purpose (see
 * {@see \Sikshya\Frontend\Site\QuizTemplateData::buildQuestionViewRowForId()},
 * which runs it through `wp_strip_all_tags()`), so an author could not smuggle
 * an `<img>` into it even by trying. This is the supported way.
 *
 * Deliberately **one** item, and deliberately on the stem rather than on the
 * options. Media per option would change what a learner submits — the answer
 * index would stop being readable — and it is the half of the feature a
 * grading bug would be worst in. See `docs/QUESTION-MEDIA.md`.
 *
 * Two sources, same order as {@see LessonDocument}: a media library
 * attachment, which can be served through the signed per-user proxy, or a
 * plain URL for something already hosted elsewhere.
 *
 * @package Sikshya\Services
 */
final class QuestionMedia
{
    /** Media library id of the question's image or audio clip. */
    public const META_ID = '_sikshya_question_media_id';

    /** External media URL, used only when no attachment id is set. */
    public const META_URL = '_sikshya_question_media_url';

    /**
     * `image` or `audio`, and only consulted for an external URL — for an
     * attachment the kind is derived from the file's own MIME type, so a
     * stored kind cannot disagree with the file it describes.
     */
    public const META_KIND = '_sikshya_question_media_kind';

    /**
     * Alternative text. For an image this is not decoration: the image *is*
     * part of the question, so without it the question cannot be answered with
     * a screen reader at all.
     */
    public const META_ALT = '_sikshya_question_media_alt';

    public const KIND_IMAGE = 'image';
    public const KIND_AUDIO = 'audio';

    /**
     * Image types that render in an `<img>` without executing anything.
     *
     * SVG is absent on purpose. A script inside an SVG does not run through
     * `<img>`, but it does the moment anyone links it or opens it in a tab,
     * and "safe only while nobody clicks it" is not a property worth relying
     * on for a file an author uploaded.
     *
     * @var string[]
     */
    private const IMAGE_MIME = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    /** @var string[] */
    private const AUDIO_MIME = [
        'audio/mpeg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/aac',
        'audio/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/webm',
        'audio/flac',
    ];

    /** @var array<string, string> Extension → kind, for external URLs. */
    private const URL_EXTENSIONS = [
        'jpg' => self::KIND_IMAGE,
        'jpeg' => self::KIND_IMAGE,
        'png' => self::KIND_IMAGE,
        'gif' => self::KIND_IMAGE,
        'webp' => self::KIND_IMAGE,
        'avif' => self::KIND_IMAGE,
        'mp3' => self::KIND_AUDIO,
        'm4a' => self::KIND_AUDIO,
        'aac' => self::KIND_AUDIO,
        'oga' => self::KIND_AUDIO,
        'ogg' => self::KIND_AUDIO,
        'wav' => self::KIND_AUDIO,
        'weba' => self::KIND_AUDIO,
        'flac' => self::KIND_AUDIO,
    ];

    /**
     * True when this question has resolvable media, regardless of viewer.
     */
    public static function has(int $question_id): bool
    {
        return self::attachmentIdFor($question_id) > 0 || self::externalFor($question_id) !== null;
    }

    /**
     * Render-ready media for one question, with the URL scoped to `$user_id`.
     *
     * @return array{kind:string, source:string, url:string, alt:string}|null
     */
    public static function forDisplay(int $question_id, int $user_id): ?array
    {
        if ($question_id <= 0) {
            return null;
        }

        $attachment_id = self::attachmentIdFor($question_id);
        if ($attachment_id > 0) {
            /*
             * An ordinary signed download URL — no inline claim. `<img>` and
             * `<audio>` load a subresource, where `Content-Disposition` is not
             * consulted at all, so the bytes render from this URL exactly as
             * they would from the uploads path. Nothing here needs the proxy's
             * inline allowlist widened beyond PDFs, which is the point: that
             * list decides what the browser will parse *as a document* from
             * our own origin.
             */
            $url = AttachmentTokenService::signedUrlFor($attachment_id, $user_id);
            if ($url === '') {
                return null;
            }

            return [
                'kind' => self::kindForMime((string) get_post_mime_type($attachment_id)),
                'source' => 'attachment',
                'url' => $url,
                'alt' => self::altFor($question_id, $attachment_id),
            ];
        }

        $external = self::externalFor($question_id);
        if ($external === null) {
            return null;
        }

        return [
            'kind' => $external['kind'],
            'source' => 'url',
            'url' => $external['url'],
            'alt' => self::altFor($question_id, 0),
        ];
    }

    /**
     * The stored attachment id, or 0 when it is missing, deleted, not an
     * image or audio file, or has lost its file reference.
     */
    public static function attachmentIdFor(int $question_id): int
    {
        if ($question_id <= 0) {
            return 0;
        }

        $id = absint(get_post_meta($question_id, self::META_ID, true));
        if ($id <= 0) {
            return 0;
        }
        if (get_post_type($id) !== 'attachment') {
            return 0;
        }
        if (self::kindForMime((string) get_post_mime_type($id)) === '') {
            return 0;
        }
        // Same trap as LessonDocument: without `_wp_attached_file`,
        // `wp_get_attachment_url()` falls back to the post GUID and the page
        // gets a broken <img> instead of no <img>.
        $path = get_attached_file($id);
        if (!is_string($path) || $path === '') {
            return 0;
        }

        return $id;
    }

    /**
     * The stored external URL and its kind, or null when unusable.
     *
     * The kind is taken from the file extension and **not** guessed: an
     * unrecognised extension resolves to nothing, because the alternative is
     * rendering an audio file in an `<img>` or vice versa, and a question that
     * silently shows nothing is worse than a question with no media.
     *
     * @return array{url:string, kind:string}|null
     */
    public static function externalFor(int $question_id): ?array
    {
        if ($question_id <= 0) {
            return null;
        }

        $raw = trim((string) get_post_meta($question_id, self::META_URL, true));
        if ($raw === '') {
            return null;
        }

        // This lands in a `src` attribute, where `javascript:` executes.
        $scheme = strtolower((string) wp_parse_url($raw, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $path = (string) wp_parse_url($raw, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $kind = self::URL_EXTENSIONS[$ext] ?? '';

        if ($kind === '') {
            // A stored kind is the author's last word when the URL has no
            // usable extension (a CDN delivering `/asset/8812`, for example).
            $stored = sanitize_key((string) get_post_meta($question_id, self::META_KIND, true));
            if ($stored !== self::KIND_IMAGE && $stored !== self::KIND_AUDIO) {
                return null;
            }
            $kind = $stored;
        }

        return ['url' => $raw, 'kind' => $kind];
    }

    /**
     * Resolved alternative text: the question's own, else the attachment's.
     *
     * Falling back to the media library's alt text matters because that is
     * where a careful site already keeps it, and asking an author to retype it
     * per question is how it ends up empty.
     */
    public static function altFor(int $question_id, int $attachment_id = 0): string
    {
        $alt = trim((string) get_post_meta($question_id, self::META_ALT, true));
        if ($alt !== '') {
            return $alt;
        }

        if ($attachment_id <= 0) {
            $attachment_id = self::attachmentIdFor($question_id);
        }
        if ($attachment_id > 0) {
            $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
        }

        return $alt;
    }

    /**
     * `image`, `audio`, or '' for anything we will not render.
     */
    public static function kindForMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        if (in_array($mime, self::IMAGE_MIME, true)) {
            return self::KIND_IMAGE;
        }
        if (in_array($mime, self::AUDIO_MIME, true)) {
            return self::KIND_AUDIO;
        }

        return '';
    }

    /**
     * True when this post type can carry question media.
     */
    public static function supports(int $post_id): bool
    {
        return $post_id > 0 && get_post_type($post_id) === PostTypes::QUESTION;
    }
}
