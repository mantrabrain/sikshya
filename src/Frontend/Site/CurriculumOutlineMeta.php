<?php

namespace Sikshya\Frontend\Site;

/**
 * Builds human-readable outline strings for sidebar curriculum rows.
 *
 * @package Sikshya\Frontend\Site
 */
final class CurriculumOutlineMeta
{
    /**
     * Minutes for section total (lessons use duration meta; quizzes use time limit).
     */
    public static function itemDurationMinutes(\WP_Post $post, string $type_key): int
    {
        $pid = (int) $post->ID;

        switch ($type_key) {
            case 'lesson':
                $raw = get_post_meta($pid, '_sikshya_lesson_duration', true);
                if (is_numeric($raw)) {
                    return max(0, (int) $raw);
                }

                return 0;
            case 'quiz':
                return max(0, (int) get_post_meta($pid, '_sikshya_quiz_time_limit', true));
            default:
                return 0;
        }
    }

    /**
     * Compact subtitle for outline row (e.g. "2min", "15min limit").
     */
    public static function itemSubtitleCompact(\WP_Post $post, string $type_key): string
    {
        switch ($type_key) {
            case 'lesson':
                $raw = get_post_meta($post->ID, '_sikshya_lesson_duration', true);
                if (is_numeric($raw) && (int) $raw > 0) {
                    /* translators: %d: minutes */
                    return sprintf(__('%dmin', 'sikshya'), (int) $raw);
                }

                return '';
            case 'quiz':
                $time = (int) get_post_meta($post->ID, '_sikshya_quiz_time_limit', true);
                if ($time > 0) {
                    /* translators: %d: minutes */
                    return sprintf(__('%dmin', 'sikshya'), $time);
                }
                $ids = get_post_meta($post->ID, '_sikshya_quiz_questions', true);
                $n = is_array($ids) ? count($ids) : 0;
                if ($n > 0) {
                    /* translators: %d: question count */
                    return sprintf(__('%d qs', 'sikshya'), $n);
                }

                return '';
            case 'assignment':
                $pts = (int) get_post_meta($post->ID, '_sikshya_assignment_points', true);

                return $pts > 0 ? (string) sprintf(__('%d pts', 'sikshya'), $pts) : '';
            default:
                return '';
        }
    }

    /**
     * Secondary line for a curriculum item (lesson / quiz / assignment / other).
     */
    public static function itemMetaLine(\WP_Post $post, string $type_key): string
    {
        switch ($type_key) {
            case 'lesson':
                return self::lessonLine($post);
            case 'quiz':
                return self::quizLine($post);
            case 'assignment':
                return self::assignmentLine($post);
            default:
                return '';
        }
    }

    private static function lessonLine(\WP_Post $post): string
    {
        $pid = (int) $post->ID;
        $type = sanitize_key((string) get_post_meta($pid, '_sikshya_lesson_type', true));
        $type_labels = [
            'video' => __('Video', 'sikshya'),
            'text' => __('Text', 'sikshya'),
            'audio' => __('Audio', 'sikshya'),
            'document' => __('Document', 'sikshya'),
            'live' => __('Live class', 'sikshya'),
            'scorm' => __('SCORM', 'sikshya'),
            'h5p' => __('H5P', 'sikshya'),
        ];
        $type_label = $type !== '' && isset($type_labels[$type]) ? $type_labels[$type] : '';

        $duration_raw = get_post_meta($pid, '_sikshya_lesson_duration', true);
        $duration = self::formatDuration($duration_raw);

        $parts = array_filter([$type_label, $duration], static function ($v) {
            return $v !== '';
        });

        return implode(' · ', $parts);
    }

    /**
     * @param mixed $raw Meta value (minutes as number/string, or free text).
     */
    private static function formatDuration($raw): string
    {
        if ($raw === '' || $raw === null) {
            return '';
        }
        if (is_numeric($raw)) {
            $n = (int) $raw;
            if ($n <= 0) {
                return '';
            }

            /* translators: %d: duration in minutes */
            return sprintf(_n('%d min', '%d min', $n, 'sikshya'), $n);
        }

        return sanitize_text_field((string) $raw);
    }

    private static function quizLine(\WP_Post $post): string
    {
        $pid = (int) $post->ID;
        $ids = get_post_meta($pid, '_sikshya_quiz_questions', true);
        $q_count = is_array($ids) ? count($ids) : 0;

        $time = (int) get_post_meta($pid, '_sikshya_quiz_time_limit', true);
        $pass = (float) get_post_meta($pid, '_sikshya_quiz_passing_score', true);

        $parts = [];
        if ($q_count > 0) {
            /* translators: %d: number of questions */
            $parts[] = sprintf(_n('%d question', '%d questions', $q_count, 'sikshya'), $q_count);
        }
        if ($time > 0) {
            /* translators: %d: time limit in minutes */
            $parts[] = sprintf(__('%d min limit', 'sikshya'), $time);
        }
        if ($pass > 0) {
            /* translators: %s: passing score percentage */
            $parts[] = sprintf(__('Pass %s%%', 'sikshya'), (string) (int) round($pass));
        }

        return implode(' · ', $parts);
    }

    private static function assignmentLine(\WP_Post $post): string
    {
        $pid = (int) $post->ID;
        $points = (int) get_post_meta($pid, '_sikshya_assignment_points', true);
        $atype = sanitize_key((string) get_post_meta($pid, '_sikshya_assignment_type', true));

        $type_labels = [
            'file' => __('File upload', 'sikshya'),
            'text' => __('Text', 'sikshya'),
        ];
        $type_label = $atype !== '' && isset($type_labels[$atype]) ? $type_labels[$atype] : '';

        $parts = [];
        if ($type_label !== '') {
            $parts[] = $type_label;
        }
        if ($points > 0) {
            /* translators: %d: assignment points */
            $parts[] = sprintf(__('%d pts', 'sikshya'), $points);
        }

        return implode(' · ', $parts);
    }
}
