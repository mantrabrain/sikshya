<?php

/**
 * Import bundled JSON sample courses (Tools → maintenance).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class SampleDataImporter
{
    public function __construct(
        private CurriculumService $curriculum,
        private CourseCurriculumActions $actions
    ) {
    }

    /**
     * @param array<string, mixed> $pack Decoded JSON.
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importPack(array $pack): array
    {
        if ((int) ($pack['version'] ?? 0) !== 1) {
            return ['success' => false, 'message' => __('Unsupported sample data version.', 'sikshya')];
        }

        $courses = $pack['courses'] ?? null;
        if (!is_array($courses) || $courses === []) {
            return ['success' => false, 'message' => __('No courses in sample file.', 'sikshya')];
        }

        $counts = [
            'courses' => 0,
            'chapters' => 0,
            'lessons' => 0,
            'quizzes' => 0,
            'questions' => 0,
            'assignments' => 0,
            'categories' => 0,
        ];

        $pack_categories = $pack['course_categories'] ?? [];
        if (is_array($pack_categories)) {
            foreach ($pack_categories as $cat_def) {
                if (is_array($cat_def)) {
                    $this->ensureCourseCategoryTerm($cat_def);
                }
            }
            $counts['categories'] = count($pack_categories);
        }

        foreach ($courses as $course_def) {
            if (!is_array($course_def)) {
                continue;
            }

            $course_id = $this->createCourse($course_def);
            if ($course_id <= 0) {
                continue;
            }

            ++$counts['courses'];

            $chapters = $course_def['chapters'] ?? [];
            if (!is_array($chapters)) {
                continue;
            }

            $order = 1;
            $chapter_ids = [];

            foreach ($chapters as $ch) {
                if (!is_array($ch)) {
                    continue;
                }

                $res = $this->actions->restCreateChapter(
                    [
                        'course_id' => $course_id,
                        'title' => (string) ($ch['title'] ?? __('Chapter', 'sikshya')),
                        'description' => (string) ($ch['content'] ?? ''),
                        'order' => $order,
                    ]
                );

                if (empty($res['success']) || empty($res['data']['chapter_id'])) {
                    continue;
                }

                $chapter_id = (int) $res['data']['chapter_id'];
                ++$counts['chapters'];
                $chapter_ids[] = $chapter_id;
                ++$order;

                $contents = $ch['contents'] ?? [];
                if (!is_array($contents)) {
                    continue;
                }

                foreach ($contents as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $this->importContentItem($course_id, $chapter_id, $item, $counts);
                }
            }

            if ($chapter_ids !== []) {
                update_post_meta($course_id, '_sikshya_chapters', $chapter_ids);
                update_post_meta($course_id, '_sikshya_chapter_order', $chapter_ids);
            }
        }

        return [
            'success' => true,
            'message' => __('Sample data imported.', 'sikshya'),
            'counts' => $counts,
        ];
    }

    /**
     * @param array<string, mixed> $def
     */
    private function createCourse(array $def): int
    {
        $title = sanitize_text_field($def['title'] ?? '');
        if ($title === '') {
            return 0;
        }

        $post_id = wp_insert_post(
            [
                'post_title' => $title,
                'post_content' => wp_kses_post($def['content'] ?? ''),
                'post_type' => PostTypes::COURSE,
                'post_status' => sanitize_key((string) ($def['status'] ?? 'publish')) ?: 'publish',
            ],
            true
        );

        if (is_wp_error($post_id)) {
            return 0;
        }

        $post_id = (int) $post_id;
        $meta = $def['meta'] ?? [];
        if (is_array($meta)) {
            foreach ($meta as $key => $value) {
                if (!is_string($key) || $key === '' || $key[0] !== '_') {
                    continue;
                }
                $canonical = self::canonicalCourseMetaKey($key);
                update_post_meta($post_id, $canonical, $value);
                // Keep legacy key in sync when sample uses short names.
                if ($canonical !== $key) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }

        $cats = $def['categories'] ?? [];
        if (is_array($cats) && $cats !== []) {
            $slugs = [];
            foreach ($cats as $c) {
                if (is_string($c) && $c !== '') {
                    $slugs[] = sanitize_title($c);
                }
            }
            $slugs = array_values(array_filter(array_unique($slugs)));
            if ($slugs !== []) {
                wp_set_object_terms($post_id, $slugs, Taxonomies::COURSE_CATEGORY, false);
            }
        }

        return $post_id;
    }

    /**
     * Map sample JSON meta keys to keys the LMS uses everywhere (checkout, templates).
     */
    private static function canonicalCourseMetaKey(string $key): string
    {
        static $map = [
            '_sikshya_price' => '_sikshya_course_price',
            '_sikshya_duration' => '_sikshya_course_duration',
            '_sikshya_difficulty' => '_sikshya_course_level',
        ];

        return $map[$key] ?? $key;
    }

    /**
     * @param array<string, mixed> $c Keys: name, slug (optional).
     * @return int|null Term ID.
     */
    private function ensureCourseCategoryTerm(array $c): ?int
    {
        $name = sanitize_text_field((string) ($c['name'] ?? ''));
        $slug_in = isset($c['slug']) ? sanitize_title((string) $c['slug']) : '';
        if ($name === '' && $slug_in === '') {
            return null;
        }
        $slug = $slug_in !== '' ? $slug_in : sanitize_title($name);
        $term = term_exists($slug, Taxonomies::COURSE_CATEGORY);
        if (is_array($term)) {
            return (int) $term['term_id'];
        }
        $args = ['slug' => $slug];
        $ins = wp_insert_term($name !== '' ? $name : $slug, Taxonomies::COURSE_CATEGORY, $args);
        if (is_wp_error($ins)) {
            return null;
        }

        return isset($ins['term_id']) ? (int) $ins['term_id'] : null;
    }

    /**
     * @param array<string, mixed>   $item
     * @param array<string, int>     $counts
     */
    private function importContentItem(int $course_id, int $chapter_id, array $item, array &$counts): void
    {
        $type = sanitize_key((string) ($item['type'] ?? 'lesson'));
        $map = [
            'lesson' => 'lesson',
            'quiz' => 'quiz',
            'assignment' => 'assignment',
        ];
        $content_type = $map[$type] ?? 'lesson';

        $created = $this->actions->createContentForService(
            [
                'title' => (string) ($item['title'] ?? __('Content', 'sikshya')),
                'description' => (string) ($item['content'] ?? ''),
                'type' => $content_type,
                'lesson_type' => sanitize_key((string) ($item['lesson_type'] ?? 'text')),
            ]
        );

        if (empty($created['success']) || empty($created['data']['content_id'])) {
            return;
        }

        $pid = (int) $created['data']['content_id'];

        if ($content_type === 'lesson') {
            ++$counts['lessons'];
            update_post_meta($pid, '_sikshya_lesson_course', $course_id);
        } elseif ($content_type === 'quiz') {
            ++$counts['quizzes'];
            update_post_meta($pid, '_sikshya_quiz_course', $course_id);
            update_post_meta($pid, '_sikshya_quiz_passing_score', (float) ($item['passing_score'] ?? 70));
            update_post_meta($pid, '_sikshya_quiz_time_limit', (int) ($item['time_limit'] ?? 0));
            update_post_meta($pid, '_sikshya_quiz_attempts_allowed', (int) ($item['attempts_allowed'] ?? 0));

            $qids = [];
            $questions = $item['questions'] ?? [];
            if (is_array($questions)) {
                foreach ($questions as $q) {
                    if (!is_array($q)) {
                        continue;
                    }
                    $qid = $this->createQuestion($q);
                    if ($qid > 0) {
                        $qids[] = $qid;
                        ++$counts['questions'];
                    }
                }
            }
            if ($qids !== []) {
                update_post_meta($pid, '_sikshya_quiz_questions', $qids);
            }
        } elseif ($content_type === 'assignment') {
            ++$counts['assignments'];
            update_post_meta($pid, '_sikshya_assignment_course', $course_id);
        }

        $item_meta = $item['meta'] ?? [];
        if (is_array($item_meta)) {
            foreach ($item_meta as $mk => $mv) {
                if (is_string($mk) && $mk !== '' && $mk[0] === '_') {
                    update_post_meta($pid, $mk, $mv);
                }
            }
        }

        $this->curriculum->linkContentToChapter($pid, $chapter_id);
    }

    /**
     * @param array<string, mixed> $q
     */
    private function createQuestion(array $q): int
    {
        $title = sanitize_text_field($q['title'] ?? '');
        if ($title === '') {
            return 0;
        }

        $post_id = wp_insert_post(
            [
                'post_title' => $title,
                'post_content' => wp_kses_post($q['explanation'] ?? ''),
                'post_type' => PostTypes::QUESTION,
                'post_status' => sanitize_key((string) ($q['status'] ?? 'publish')) ?: 'publish',
            ],
            true
        );

        if (is_wp_error($post_id)) {
            return 0;
        }

        $post_id = (int) $post_id;
        $qt = sanitize_key((string) ($q['question_type'] ?? 'multiple_choice'));
        update_post_meta($post_id, '_sikshya_question_type', $qt);
        update_post_meta($post_id, '_sikshya_question_points', (int) ($q['points'] ?? 1));

        $opts = $q['options'] ?? [];
        if (is_array($opts)) {
            update_post_meta($post_id, '_sikshya_question_options', array_map('strval', $opts));
        }

        $ca = $q['correct_answer'] ?? '';
        if (is_array($ca)) {
            update_post_meta($post_id, '_sikshya_question_correct_answer', wp_json_encode($ca));
        } else {
            update_post_meta($post_id, '_sikshya_question_correct_answer', (string) $ca);
        }

        return $post_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function loadJsonFile(string $absolute_path): ?array
    {
        if (!is_readable($absolute_path)) {
            return null;
        }

        $raw = file_get_contents($absolute_path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
