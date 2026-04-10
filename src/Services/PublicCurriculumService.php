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
}
