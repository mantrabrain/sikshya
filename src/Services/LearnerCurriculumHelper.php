<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

/**
 * Resolve lesson IDs from course curriculum meta (chapters → contents).
 *
 * @package Sikshya\Services
 */
final class LearnerCurriculumHelper
{
    /**
     * @return array<int, int>
     */
    public static function lessonIdsForCourse(int $course_id): array
    {
        if ($course_id <= 0) {
            return [];
        }

        $chapter_ids = get_post_meta($course_id, '_sikshya_chapters', true);
        if (!is_array($chapter_ids)) {
            return [];
        }

        $lessons = [];
        foreach ($chapter_ids as $ch_id) {
            $ch_id = (int) $ch_id;
            if ($ch_id <= 0) {
                continue;
            }
            $contents = get_post_meta($ch_id, '_sikshya_contents', true);
            if (!is_array($contents)) {
                continue;
            }
            foreach ($contents as $cid) {
                $cid = (int) $cid;
                if ($cid <= 0) {
                    continue;
                }
                if (get_post_type($cid) === PostTypes::LESSON) {
                    $lessons[] = $cid;
                }
            }
        }

        return array_values(array_unique($lessons));
    }
}
