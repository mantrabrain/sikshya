<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Security\AttachmentTokenService;

/**
 * Per-lesson downloadable attachments.
 *
 * `lesson_attachments` has been an advertised free feature with no delivery
 * path: the meta was declared in {@see \Sikshya\Models\Lesson} and read back in
 * {@see \Sikshya\Frontend\Controllers\LessonController}, but under two different
 * keys (`_sikshya_lesson_attachments` vs `sikshya_lesson_attachments`), nothing
 * ever wrote either from an authoring surface, and no template rendered the
 * result. The lesson "Downloads" tab said "No resources available for this
 * lesson yet" while listing only *course*-level resources.
 *
 * This class is the single place that decides what a lesson's attachments are.
 * The stored shape is the one both pre-existing code paths already assumed — a
 * flat list of media library IDs — so sites that wrote the meta directly keep
 * working, and titles come from the media library rather than being duplicated
 * into post meta where they would drift.
 *
 * Reads are deliberately defensive. The meta is REST-writable, so it can arrive
 * as a list of numeric strings, contain ids that have since been deleted, point
 * at posts that are not attachments at all, or repeat the same file twice.
 *
 * Download URLs are issued through {@see AttachmentTokenService} exactly as
 * course resources are, so a lesson attachment behind a paid course is not
 * leaked by its uploads URL.
 *
 * @package Sikshya\Services
 */
final class LessonAttachments
{
    /**
     * Canonical meta key. Underscore-prefixed, so WordPress treats it as
     * protected meta and it stays out of the custom-fields box.
     */
    public const META_KEY = '_sikshya_lesson_attachments';

    /**
     * Key the frontend controller used to read. No authoring surface ever wrote
     * it, but a site that followed that code could have, so it is still read.
     */
    private const LEGACY_META_KEY = 'sikshya_lesson_attachments';

    /**
     * Attachment ids stored against a lesson, in author order.
     *
     * Deduplicated, and limited to ids that still resolve to a real attachment,
     * so callers never have to re-check.
     *
     * @return int[]
     */
    public static function idsFor(int $lesson_id): array
    {
        if ($lesson_id <= 0) {
            return [];
        }

        $raw = get_post_meta($lesson_id, self::META_KEY, true);
        if (!is_array($raw) || $raw === []) {
            $legacy = get_post_meta($lesson_id, self::LEGACY_META_KEY, true);
            if (is_array($legacy) && $legacy !== []) {
                $raw = $legacy;
            }
        }

        return self::normalizeIds($raw);
    }

    /**
     * Replace a lesson's attachments.
     *
     * @param array<int, mixed> $ids
     * @return int[] The ids actually stored, after normalization.
     */
    public static function set(int $lesson_id, array $ids): array
    {
        if ($lesson_id <= 0) {
            return [];
        }

        $clean = self::normalizeIds($ids);
        update_post_meta($lesson_id, self::META_KEY, $clean);

        return $clean;
    }

    /**
     * Render-ready attachment rows for one lesson.
     *
     * Each row carries a download URL already scoped to `$user_id`, so the
     * caller does not need to know about attachment protection.
     *
     * @return array<int, array{id:int, title:string, url:string, mime:string, filesize:int}>
     */
    public static function forDisplay(int $lesson_id, int $user_id): array
    {
        $out = [];

        foreach (self::idsFor($lesson_id) as $id) {
            $url = AttachmentTokenService::signedUrlFor($id, $user_id);
            if ($url === '') {
                // No readable URL means nothing to offer, and a dead link in a
                // Downloads list is worse than an absent row.
                continue;
            }

            $title = get_the_title($id);
            $title = is_string($title) ? trim($title) : '';
            if ($title === '') {
                $file = get_attached_file($id);
                $title = is_string($file) && $file !== '' ? wp_basename($file) : (string) $id;
            }

            $path = get_attached_file($id);

            $out[] = [
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'mime' => (string) get_post_mime_type($id),
                'filesize' => is_string($path) && $path !== '' && file_exists($path)
                    ? (int) filesize($path)
                    : 0,
            ];
        }

        /**
         * Filter a lesson's render-ready attachment rows.
         *
         * @param array<int, array<string, mixed>> $out
         * @param int $lesson_id
         * @param int $user_id
         */
        return apply_filters('sikshya_lesson_attachments_for_display', $out, $lesson_id, $user_id);
    }

    /**
     * Coerce arbitrary stored/submitted input into a clean id list.
     *
     * @param mixed $raw
     * @return int[]
     */
    public static function normalizeIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            // Accept the `{id: n}` object shape too, since that is what a media
            // picker hands back and it is an easy thing for an integration to
            // post verbatim.
            if (is_array($value) && isset($value['id'])) {
                $value = $value['id'];
            }

            if (!is_scalar($value)) {
                continue;
            }

            $id = absint($value);
            if ($id <= 0 || isset($ids[$id])) {
                continue;
            }
            if (get_post_type($id) !== 'attachment') {
                continue;
            }

            $ids[$id] = true;
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * True when this post type can carry lesson attachments.
     */
    public static function supports(int $post_id): bool
    {
        return $post_id > 0 && get_post_type($post_id) === PostTypes::LESSON;
    }
}
