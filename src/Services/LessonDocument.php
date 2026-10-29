<?php

namespace Sikshya\Services;

use Sikshya\Security\AttachmentTokenService;

/**
 * The document behind a `pdf` lesson.
 *
 * A PDF lesson is a curriculum step whose *body* is a document, as opposed to
 * the per-lesson attachments in {@see LessonAttachments}, which are extra files
 * offered beside whatever the lesson already is. The distinction matters for
 * more than tidiness: a lesson body has to be readable in place, so the file is
 * served for display, while an attachment is only ever handed over as a
 * download.
 *
 * Two sources are accepted, in this order:
 *
 *   1. A media library attachment ({@see META_ID}). Preferred, because the file
 *      can then be delivered through {@see AttachmentTokenService} — a
 *      short-lived URL bound to one user id — rather than by its public
 *      `wp-content/uploads/` path.
 *   2. A plain URL ({@see META_URL}), for documents already hosted elsewhere.
 *      Nothing about such a URL is enforceable by us; see the note on
 *      {@see forDisplay()}.
 *
 * Resolution is deliberately strict about the type. The id is REST-writable and
 * a media picker will happily hand back a .docx, so a stored id that is not a
 * PDF resolves to nothing rather than to a frame the browser cannot render.
 *
 * @package Sikshya\Services
 */
final class LessonDocument
{
    /** Media library id of the lesson's document. */
    public const META_ID = '_sikshya_lesson_pdf_id';

    /** External document URL, used only when no attachment id is set. */
    public const META_URL = '_sikshya_lesson_pdf_url';

    /**
     * When truthy, no download link is offered.
     *
     * Stored as a "hide" flag rather than an "allow" one so that an absent
     * meta — every lesson that existed before this feature — means the link is
     * shown, which is the behaviour an author expects by default.
     */
    public const META_HIDE_DOWNLOAD = '_sikshya_lesson_pdf_hide_download';

    /** The only MIME type a PDF lesson can carry. */
    private const MIME = 'application/pdf';

    /**
     * True when this lesson has a resolvable document, regardless of viewer.
     *
     * Used by the learn shell to decide whether a `pdf` lesson has a body at
     * all, so an author who picked the type but never attached a file does not
     * produce an empty frame.
     */
    public static function has(int $lesson_id): bool
    {
        return self::attachmentIdFor($lesson_id) > 0 || self::externalUrlFor($lesson_id) !== '';
    }

    /**
     * Everything a renderer needs, with URLs already scoped to `$user_id`.
     *
     * For an attachment the view URL is a signed, inline-disposition link that
     * only `$user_id` can use and that expires within the hour; the raw uploads
     * path is never handed to the page.
     *
     * For an external URL there is no such guarantee and we do not pretend
     * otherwise: whatever the host allows, allows. That is the trade the author
     * makes by hosting the file elsewhere.
     *
     * @return array{source:string, view_url:string, download_url:string, title:string, filesize:int}|null
     */
    public static function forDisplay(int $lesson_id, int $user_id): ?array
    {
        if ($lesson_id <= 0) {
            return null;
        }

        $hide_download = (bool) get_post_meta($lesson_id, self::META_HIDE_DOWNLOAD, true);

        $attachment_id = self::attachmentIdFor($lesson_id);
        if ($attachment_id > 0) {
            $view = AttachmentTokenService::signedUrlFor($attachment_id, $user_id, 0, true);
            if ($view === '') {
                return null;
            }

            $path = get_attached_file($attachment_id);
            $title = get_the_title($attachment_id);
            $title = is_string($title) ? trim($title) : '';
            if ($title === '') {
                $title = is_string($path) && $path !== '' ? wp_basename($path) : (string) $attachment_id;
            }

            return [
                'source' => 'attachment',
                'view_url' => $view,
                // A separate token without the inline claim, so the download
                // link downloads instead of replacing the page with a viewer.
                'download_url' => $hide_download
                    ? ''
                    : AttachmentTokenService::signedUrlFor($attachment_id, $user_id),
                'title' => $title,
                'filesize' => is_string($path) && $path !== '' && file_exists($path)
                    ? (int) filesize($path)
                    : 0,
            ];
        }

        $url = self::externalUrlFor($lesson_id);
        if ($url === '') {
            return null;
        }

        return [
            'source' => 'url',
            'view_url' => $url,
            'download_url' => $hide_download ? '' : $url,
            'title' => (string) get_the_title($lesson_id),
            'filesize' => 0,
        ];
    }

    /**
     * The stored attachment id, or 0 when it is missing, deleted, or not a PDF.
     */
    public static function attachmentIdFor(int $lesson_id): int
    {
        if ($lesson_id <= 0) {
            return 0;
        }

        $id = absint(get_post_meta($lesson_id, self::META_ID, true));
        if ($id <= 0) {
            return 0;
        }
        if (get_post_type($id) !== 'attachment') {
            return 0;
        }
        if ((string) get_post_mime_type($id) !== self::MIME) {
            return 0;
        }
        /*
         * An attachment row can outlive its file reference — a half-finished
         * upload, a migration that moved the posts but not the meta. Without
         * `_wp_attached_file`, `wp_get_attachment_url()` quietly falls back to
         * the post GUID, which is how a dead frame ends up on a lesson page.
         *
         * The check is on the reference, not on `file_exists()`: media offload
         * plugins keep the reference and serve the bytes from elsewhere, and
         * refusing those would break working sites to catch broken ones.
         */
        $path = get_attached_file($id);
        if (!is_string($path) || $path === '') {
            return 0;
        }

        return $id;
    }

    /**
     * The stored external URL, or '' when absent or not http(s).
     *
     * The scheme check is not cosmetic: this value lands in an iframe `src`,
     * and `javascript:` or `data:` there would execute in the page.
     */
    public static function externalUrlFor(int $lesson_id): string
    {
        if ($lesson_id <= 0) {
            return '';
        }

        $raw = trim((string) get_post_meta($lesson_id, self::META_URL, true));
        if ($raw === '') {
            return '';
        }

        $scheme = strtolower((string) wp_parse_url($raw, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        return $raw;
    }

    /**
     * Human-readable file size, or '' when unknown.
     */
    public static function formatSize(int $bytes): string
    {
        return $bytes > 0 ? size_format($bytes) : '';
    }
}
