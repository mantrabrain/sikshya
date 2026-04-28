<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

/**
 * Read-only curriculum tree for learner-facing templates.
 *
 * @package Sikshya\Services
 */
final class PublicCurriculumService
{
    /**
     * @return array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}>
     */
    public static function getCourseCurriculum(int $course_id): array
    {
        if ($course_id <= 0) {
            return [];
        }

        $use_cache = Settings::isTruthy(Settings::get('cache_enabled', '0'));
        $cache_key = 'sikshya_cache_curriculum_' . $course_id;

        if ($use_cache) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                $hydrated = self::hydrateCurriculumFromCache($cached);
                if ($hydrated !== []) {
                    return $hydrated;
                }
            }
        }

        $out = self::buildCurriculumUncached($course_id);

        if ($use_cache && $out !== []) {
            set_transient($cache_key, self::dehydrateCurriculumForCache($out), 6 * HOUR_IN_SECONDS);
        }

        return $out;
    }

    /**
     * @return array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}>
     */
    private static function buildCurriculumUncached(int $course_id): array
    {
        $chapter_ids = get_post_meta($course_id, '_sikshya_chapters', true);
        if (!is_array($chapter_ids) || $chapter_ids === []) {
            return [];
        }

        $out = [];
        foreach ($chapter_ids as $ch_id) {
            $ch_id = (int) $ch_id;
            if ($ch_id <= 0) {
                continue;
            }
            $chapter = get_post($ch_id);
            if (!$chapter || $chapter->post_type !== PostTypes::CHAPTER || $chapter->post_status !== 'publish') {
                continue;
            }
            $raw = get_post_meta($ch_id, '_sikshya_contents', true);
            $contents = [];
            if (is_array($raw)) {
                foreach ($raw as $cid) {
                    $cid = (int) $cid;
                    if ($cid <= 0) {
                        continue;
                    }
                    $p = get_post($cid);
                    if ($p && $p->post_status === 'publish') {
                        $contents[] = $p;
                    }
                }
            }
            $out[] = [
                'chapter' => $chapter,
                'contents' => $contents,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $tree
     * @return array<int, array{chapter_id: int, content_ids: int[]}>
     */
    private static function dehydrateCurriculumForCache(array $tree): array
    {
        $payload = [];
        foreach ($tree as $block) {
            $chapter = $block['chapter'] ?? null;
            if (!$chapter instanceof \WP_Post) {
                continue;
            }
            $ids = [];
            foreach ((array) ($block['contents'] ?? []) as $p) {
                if ($p instanceof \WP_Post) {
                    $ids[] = (int) $p->ID;
                }
            }
            $payload[] = [
                'chapter_id' => (int) $chapter->ID,
                'content_ids' => $ids,
            ];
        }

        return $payload;
    }

    /**
     * @param array<int, array{chapter_id?: int, content_ids?: int[]}> $payload
     * @return array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}>
     */
    private static function hydrateCurriculumFromCache(array $payload): array
    {
        $out = [];
        foreach ($payload as $row) {
            $ch_id = isset($row['chapter_id']) ? (int) $row['chapter_id'] : 0;
            if ($ch_id <= 0) {
                continue;
            }
            $chapter = get_post($ch_id);
            if (!$chapter || $chapter->post_type !== PostTypes::CHAPTER || $chapter->post_status !== 'publish') {
                continue;
            }
            $contents = [];
            foreach ((array) ($row['content_ids'] ?? []) as $cid) {
                $cid = (int) $cid;
                if ($cid <= 0) {
                    continue;
                }
                $p = get_post($cid);
                if ($p && $p->post_status === 'publish') {
                    $contents[] = $p;
                }
            }
            $out[] = [
                'chapter' => $chapter,
                'contents' => $contents,
            ];
        }

        return $out;
    }
}
