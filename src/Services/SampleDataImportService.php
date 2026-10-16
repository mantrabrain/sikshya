<?php

/**
 * Business logic for Tools → “Import sample data” (chapters, lessons, quizzes, questions, assignments).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Repositories\SampleDataPackRepository;
use Sikshya\Models\SampleData\SampleDataPack;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SampleDataImportService
{
    /**
     * Marks a post as created by the sample importer, and which pack course it came from.
     *
     * Without it the importer had no idea what it had already done, so running
     * it twice — the Tools button, or the setup wizard after a first attempt —
     * produced a second copy of every course, and a run cut short by
     * `max_execution_time` left a half-finished pack that a retry duplicated
     * rather than completed.
     */
    public const META_SOURCE = '_sikshya_sample_source';

    /** Marks whether that post's import finished. */
    public const META_SOURCE_STATE = '_sikshya_sample_source_state';

    private SampleDataPackRepository $packRepository;

    private CurriculumService $curriculum;

    private CourseCurriculumActions $actions;

    public function __construct(
        SampleDataPackRepository $packRepository,
        CurriculumService $curriculum,
        CourseCurriculumActions $actions
    ) {
        $this->packRepository = $packRepository;
        $this->curriculum = $curriculum;
        $this->actions = $actions;
    }

    /**
     * Load pack from `sample-data/` by key and import (Tools → import default pack).
     *
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importByPackKey(string $packKey): array
    {
        $key = sanitize_key($packKey) !== '' ? sanitize_key($packKey) : 'default';
        $pack = $this->packRepository->findByPackKey($key);
        if ($pack === null) {
            return [
                'success' => false,
                'message' => __('Sample data file not found or invalid.', 'sikshya'),
            ];
        }

        return $this->importFromPack($pack);
    }

    /**
     * Import from a validated in-memory pack model.
     *
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importFromPack(SampleDataPack $pack): array
    {
        return $this->importPack($pack->toServiceArray());
    }

    /**
     * @param array<string, mixed> $pack
     * @return array{success: bool, message: string, counts?: array<string, int>}
     */
    public function importPack(array $pack): array
    {
        if (!in_array((int) ($pack['version'] ?? 0), SampleDataPack::SUPPORTED_VERSIONS, true)) {
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

        // Importing a pack copies and resizes a bundled image per course and
        // writes a few hundred posts. On a shared host the default 30s limit
        // can cut that off part-way, which used to leave a half-built pack
        // behind; the work is bounded and admin-initiated, so lift the ceiling
        // the way core's own importers do.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }

        $counts['skipped'] = 0;

        $packCategories = $pack['course_categories'] ?? [];
        if (is_array($packCategories) && $packCategories !== []) {
            foreach ($packCategories as $catDef) {
                if (is_array($catDef)) {
                    $this->ensureCourseCategoryTerm($catDef);
                }
            }
            $counts['categories'] = count($packCategories);
        }

        foreach ($courses as $courseDef) {
            if (!is_array($courseDef)) {
                continue;
            }

            $source = self::sourceKeyFor($courseDef);

            // Already imported and finished: leave it alone. Re-importing would
            // give the site a second copy of a course it already has, which is
            // what the Tools button used to do every time it was pressed.
            if ($source !== '' && self::findFinishedImport($source) > 0) {
                ++$counts['skipped'];
                continue;
            }

            // Started but never finished — a previous run was cut off. Its
            // posts are the importer's own, seconds old and carrying its
            // marker, so they are removed and the course is built again rather
            // than left as a half-course beside a duplicate.
            if ($source !== '') {
                self::discardUnfinishedImport($source);
            }

            $courseId = $this->createCourse($courseDef);
            if ($courseId <= 0) {
                continue;
            }

            if ($source !== '') {
                update_post_meta($courseId, self::META_SOURCE, $source);
                update_post_meta($courseId, self::META_SOURCE_STATE, 'importing');
            }

            ++$counts['courses'];

            $chapters = $courseDef['chapters'] ?? [];
            if (!is_array($chapters)) {
                // No curriculum to build, so this course is already as complete
                // as the pack describes it.
                if ($source !== '') {
                    update_post_meta($courseId, self::META_SOURCE_STATE, 'done');
                }
                continue;
            }

            $order = 1;
            $chapterIds = [];

            foreach ($chapters as $ch) {
                if (!is_array($ch)) {
                    continue;
                }

                $res = $this->actions->restCreateChapter(
                    [
                        'course_id' => $courseId,
                        'title' => (string) ($ch['title'] ?? __('Chapter', 'sikshya')),
                        'description' => (string) ($ch['content'] ?? ''),
                        'order' => $order,
                    ]
                );

                if (empty($res['success']) || empty($res['data']['chapter_id'])) {
                    continue;
                }

                $chapterId = (int) $res['data']['chapter_id'];
                ++$counts['chapters'];
                $chapterIds[] = $chapterId;
                ++$order;

                $contents = $ch['contents'] ?? [];
                if (!is_array($contents)) {
                    continue;
                }

                foreach ($contents as $contentIndex => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $this->importContentItem($courseId, $chapterId, $order, (int) $contentIndex, $item, $counts);
                }
            }

            if ($chapterIds !== []) {
                update_post_meta($courseId, '_sikshya_chapters', $chapterIds);
                update_post_meta($courseId, '_sikshya_chapter_order', $chapterIds);
            }

            $this->invalidateCourseCurriculumCache($courseId, $chapterIds);

            // Marked complete last, so an interruption anywhere above leaves
            // the course identifiable as unfinished.
            if ($source !== '') {
                update_post_meta($courseId, self::META_SOURCE_STATE, 'done');
            }
        }

        // Nothing created *because it was already there* is not a failure. The
        // guard below is for a genuinely broken import, and telling an admin
        // who pressed the button twice to check their file permissions would
        // send them looking for a problem that is not there.
        if ($counts['courses'] === 0 && $counts['skipped'] > 0) {
            return [
                'success' => true,
                'message' => __('Sample data is already imported.', 'sikshya'),
                'counts' => $counts,
            ];
        }

        if ($counts['courses'] === 0) {
            return [
                'success' => false,
                'message' => __(
                    'Sample data could not be imported: no courses were created. Check file permissions, JSON pack format, and that curriculum actions are available.',
                    'sikshya'
                ),
                'counts' => $counts,
            ];
        }

        return [
            'success' => true,
            'message' => __('Sample data imported.', 'sikshya'),
            'counts' => $counts,
        ];
    }


    /**
     * Stable identity for a pack course: what makes a re-import recognisable.
     *
     * The pack's slugs are unique, so the slug is the identity, falling back to
     * the title for a pack that omits one.
     *
     * @param array<string, mixed> $courseDef
     */
    private static function sourceKeyFor(array $courseDef): string
    {
        $slug = sanitize_title((string) ($courseDef['slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        return sanitize_title((string) ($courseDef['title'] ?? ''));
    }

    /**
     * The id of a finished import of this pack course, or 0.
     */
    private static function findFinishedImport(string $source): int
    {
        foreach (self::findImports($source) as $id) {
            if ((string) get_post_meta($id, self::META_SOURCE_STATE, true) === 'done') {
                return $id;
            }
        }

        return 0;
    }

    /**
     * Remove courses from an interrupted import of this pack course.
     *
     * Deliberately narrow: only a course post carrying this importer's marker
     * for this source and *not* marked done. A course the site has finished
     * importing, or any course a human made, is never matched.
     */
    private static function discardUnfinishedImport(string $source): void
    {
        foreach (self::findImports($source) as $id) {
            if ((string) get_post_meta($id, self::META_SOURCE_STATE, true) === 'done') {
                continue;
            }

            // Chapters are the course's own children, so they go with it.
            $children = get_posts([
                'post_parent' => $id,
                'post_type' => 'any',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
            ]);
            foreach ($children as $childId) {
                wp_delete_post((int) $childId, true);
            }

            wp_delete_post($id, true);
        }
    }

    /**
     * Course ids previously imported for this pack course.
     *
     * @return int[]
     */
    private static function findImports(string $source): array
    {
        if ($source === '') {
            return [];
        }

        return array_map('intval', get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'any',
            'numberposts' => 20,
            'fields' => 'ids',
            'meta_key' => self::META_SOURCE,
            'meta_value' => $source,
            'suppress_filters' => true,
        ]));
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

        $excerpt = isset($def['excerpt']) ? (string) $def['excerpt'] : '';
        if ($excerpt !== '') {
            $excerpt = wp_strip_all_tags($excerpt);
        }

        $post = [
            'post_title' => $title,
            'post_content' => wp_kses_post($def['content'] ?? ''),
            'post_type' => PostTypes::COURSE,
            'post_status' => sanitize_key((string) ($def['status'] ?? 'publish')) ?: 'publish',
        ];
        $courseSlug = sanitize_title((string) ($def['slug'] ?? ''));
        if ($courseSlug !== '') {
            $post['post_name'] = $courseSlug;
        }
        if ($excerpt !== '') {
            $post['post_excerpt'] = $excerpt;
        }

        $postId = wp_insert_post($post, true);

        if (is_wp_error($postId)) {
            return 0;
        }

        $postId = (int) $postId;

        $postAfter = get_post($postId);
        if ($postAfter instanceof \WP_Post && trim((string) $postAfter->post_name) === '') {
            $fallback = sanitize_title($title);
            if ($fallback === '') {
                $fallback = sanitize_key((string) PostTypes::COURSE) . '-' . (string) $postId;
            }
            // Ensure the generated slug is unique (avoid collisions when importing
            // multiple courses with the same title).
            $fallback = wp_unique_post_slug($fallback, $postId, $postAfter->post_status, $postAfter->post_type, 0);
            wp_update_post(
                [
                    'ID' => $postId,
                    'post_name' => $fallback,
                ]
            );
        }

        $featured = isset($def['featured_image']) ? (string) $def['featured_image'] : '';
        if ($featured !== '') {
            $aid = $this->importBundledImageAttachment($featured, $title);
            if ($aid > 0) {
                set_post_thumbnail($postId, $aid);
            }
        }

        $meta = $def['meta'] ?? [];
        if (is_array($meta)) {
            foreach ($meta as $key => $value) {
                if (!is_string($key) || $key === '' || $key[0] !== '_') {
                    continue;
                }
                $canonical = self::canonicalCourseMetaKey($key);
                update_post_meta($postId, $canonical, $value);
                if ($canonical !== $key) {
                    update_post_meta($postId, $key, $value);
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
                wp_set_object_terms($postId, $slugs, Taxonomies::COURSE_CATEGORY, false);
            }
        }

        return $postId;
    }

    private function sampleDataBaseDir(): ?string
    {
        if (!defined('SIKSHYA_PLUGIN_FILE')) {
            return null;
        }

        $base = dirname((string) constant('SIKSHYA_PLUGIN_FILE'));
        if ($base === '') {
            return null;
        }

        $dir = $base . '/sample-data';
        return is_dir($dir) ? $dir : null;
    }

    /**
     * Import a bundled image from `sample-data/` into the media library and return attachment ID.
     *
     * v1.0.0: expects small JPG/PNG/WebP assets under `sample-data/images/`.
     */
    private function importBundledImageAttachment(string $relativePath, string $fallbackTitle = ''): int
    {
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($rel === '' || strpos($rel, '../') !== false) {
            return 0;
        }

        $base = $this->sampleDataBaseDir();
        if ($base === null) {
            return 0;
        }

        $abs = $base . '/' . $rel;
        if (!is_readable($abs)) {
            return 0;
        }

        $filename = basename($abs);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 0;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return 0;
        }

        $bytes = @file_get_contents($abs);
        if (!is_string($bytes) || $bytes === '') {
            return 0;
        }

        $upload = wp_upload_bits($filename, null, $bytes);
        if (!empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
            return 0;
        }

        $file = (string) $upload['file'];
        $url = (string) $upload['url'];

        $type = wp_check_filetype($file);
        $mime = isset($type['type']) && is_string($type['type']) && $type['type'] !== '' ? $type['type'] : 'image/jpeg';

        $attachment = [
            'post_mime_type' => $mime,
            'post_title' => $fallbackTitle !== '' ? $fallbackTitle : preg_replace('/\\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $url,
        ];

        $aid = wp_insert_attachment($attachment, $file);
        $aid = is_int($aid) ? (int) $aid : 0;
        if ($aid <= 0) {
            return 0;
        }

        // Generate intermediate sizes / attachment metadata so thumbnails work across themes.
        if (function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            try {
                $meta = wp_generate_attachment_metadata($aid, $file);
                if (is_array($meta)) {
                    wp_update_attachment_metadata($aid, $meta);
                }
            } catch (\Throwable $e) {
                // Non-fatal: the attachment itself exists.
            }
        }

        update_post_meta($aid, '_sikshya_sample_data_asset', 1);

        return $aid;
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
            '_sikshya_sale_price' => '_sikshya_course_sale_price',
        ];

        return $map[$key] ?? $key;
    }

    /**
     * Drop curriculum cache and refresh post caches so catalog / Learn work without re-saving courses.
     *
     * @param int        $courseId
     * @param list<int>  $chapterIds
     */
    private function invalidateCourseCurriculumCache(int $courseId, array $chapterIds): void
    {
        $courseId = absint($courseId);
        if ($courseId <= 0) {
            return;
        }

        delete_transient('sikshya_cache_curriculum_' . $courseId);
        clean_post_cache($courseId);

        foreach ($chapterIds as $chId) {
            $chId = absint((int) $chId);
            if ($chId > 0) {
                clean_post_cache($chId);
            }
        }
    }

    /**
     * @param array<string, mixed> $c Keys: name, slug (optional).
     * @return int|null Term ID.
     */
    private function ensureCourseCategoryTerm(array $c): ?int
    {
        $name = sanitize_text_field((string) ($c['name'] ?? ''));
        $slugIn = isset($c['slug']) ? sanitize_title((string) $c['slug']) : '';
        if ($name === '' && $slugIn === '') {
            return null;
        }
        $slug = $slugIn !== '' ? $slugIn : sanitize_title($name);
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
     * When the pack omits `slug`, build a stable, unique slug from course/chapter position
     * so Learn URLs never collide (duplicate title slugs confuse `get_page_by_path` and
     * can interact badly with canonical redirects).
     */
    private function buildFallbackContentSlug(
        int $courseId,
        int $chapterOrder,
        int $contentIndex,
        string $contentType,
        string $title
    ): string {
        $type = sanitize_key($contentType) !== '' ? sanitize_key($contentType) : 'content';
        $titleSlice = function_exists('mb_substr') ? mb_substr($title, 0, 72) : substr($title, 0, 72);
        $snippet = sanitize_title((string) $titleSlice);
        if ($snippet === '') {
            $snippet = $type;
        }

        $base = sprintf(
            's-%d-ch%d-u%d-%s',
            max(1, $courseId),
            max(1, $chapterOrder),
            max(1, $contentIndex + 1),
            $type
        );

        $candidate = $base . '-' . $snippet;
        if (strlen($candidate) > 180) {
            $candidate = $base . '-' . substr($snippet, 0, 40);
        }

        $out = sanitize_title($candidate);

        return $out !== '' ? $out : sanitize_title($base);
    }

    /**
     * @param array<string, mixed>   $item
     * @param array<string, int>     $counts
     */
    private function importContentItem(
        int $courseId,
        int $chapterId,
        int $chapterOrder,
        int $contentIndex,
        array $item,
        array &$counts
    ): void {
        $type = sanitize_key((string) ($item['type'] ?? 'lesson'));
        $map = [
            'lesson' => 'lesson',
            'quiz' => 'quiz',
            'assignment' => 'assignment',
        ];
        $contentType = $map[$type] ?? 'lesson';

        $slugPacked = sanitize_title((string) ($item['slug'] ?? ''));
        if ($slugPacked === '') {
            $slugPacked = $this->buildFallbackContentSlug(
                $courseId,
                $chapterOrder,
                $contentIndex,
                $contentType,
                (string) ($item['title'] ?? '')
            );
        }

        $createParams = [
            'title' => (string) ($item['title'] ?? __('Content', 'sikshya')),
            'description' => (string) ($item['content'] ?? ''),
            'type' => $contentType,
            'lesson_type' => sanitize_key((string) ($item['lesson_type'] ?? 'text')),
            'slug' => $slugPacked,
        ];

        $durationRaw = $item['duration'] ?? $item['duration_minutes'] ?? null;
        if ($durationRaw !== null && (string) $durationRaw !== '') {
            $createParams['duration'] = (string) $durationRaw;
        }

        $created = $this->actions->createContentForService($createParams);

        if (empty($created['success']) || empty($created['data']['content_id'])) {
            return;
        }

        $pid = (int) $created['data']['content_id'];

        // Link to course before applying pack `meta` so `_sikshya_*_course` cannot be missing on the frontend.
        if ($contentType === 'lesson') {
            LessonCourseLink::persistLessonCourseId($pid, $courseId);
        } elseif ($contentType === 'quiz') {
            update_post_meta($pid, '_sikshya_quiz_course', $courseId);
        } elseif ($contentType === 'assignment') {
            update_post_meta($pid, '_sikshya_assignment_course', $courseId);
        }

        if ($contentType === 'lesson') {
            ++$counts['lessons'];

            if (($createParams['lesson_type'] ?? '') === 'video') {
                $videoUrl = (string) ($item['video_url'] ?? $item['video'] ?? '');
                if ($videoUrl !== '') {
                    $safe = esc_url_raw($videoUrl);
                    update_post_meta($pid, '_sikshya_lesson_video_url', $safe);
                    update_post_meta($pid, 'sikshya_lesson_video_url', $safe);
                }
            }
        } elseif ($contentType === 'quiz') {
            ++$counts['quizzes'];
            update_post_meta($pid, '_sikshya_quiz_course', $courseId);
            update_post_meta($pid, '_sikshya_quiz_passing_score', (float) ($item['passing_score'] ?? 70));
            update_post_meta($pid, '_sikshya_quiz_time_limit', (int) ($item['time_limit'] ?? 0));
            update_post_meta($pid, '_sikshya_quiz_attempts_allowed', (int) ($item['attempts_allowed'] ?? 0));
            if (array_key_exists('randomize', $item)) {
                $r = !empty($item['randomize']) ? '1' : '0';
                update_post_meta($pid, '_sikshya_quiz_randomize_questions', $r);
                update_post_meta($pid, 'sikshya_quiz_randomize_questions', $r);
            }

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
        } elseif ($contentType === 'assignment') {
            ++$counts['assignments'];
        }

        static $skippedPackMetaLead = ['_sikshya_lesson_course', '_sikshya_quiz_course', '_sikshya_assignment_course'];

        $itemMeta = $item['meta'] ?? [];
        if (is_array($itemMeta)) {
            foreach ($itemMeta as $mk => $mv) {
                if (is_string($mk) && $mk !== '' && $mk[0] === '_') {
                    if (in_array($mk, $skippedPackMetaLead, true)) {
                        continue;
                    }
                    update_post_meta($pid, $mk, $mv);
                }
            }
        }

        $this->curriculum->linkContentToChapter($pid, $chapterId);
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

        $postId = wp_insert_post(
            [
                'post_title' => $title,
                'post_content' => wp_kses_post($q['explanation'] ?? ''),
                'post_type' => PostTypes::QUESTION,
                'post_status' => sanitize_key((string) ($q['status'] ?? 'publish')) ?: 'publish',
            ],
            true
        );

        if (is_wp_error($postId)) {
            return 0;
        }

        $postId = (int) $postId;
        $qt = sanitize_key((string) ($q['question_type'] ?? 'multiple_choice'));
        update_post_meta($postId, '_sikshya_question_type', $qt);
        update_post_meta($postId, '_sikshya_question_points', (int) ($q['points'] ?? 1));

        $opts = $q['options'] ?? [];
        if (is_array($opts)) {
            update_post_meta($postId, '_sikshya_question_options', array_map('strval', $opts));
        }

        $ca = $q['correct_answer'] ?? '';
        if (is_array($ca)) {
            update_post_meta($postId, '_sikshya_question_correct_answer', wp_json_encode($ca));
        } else {
            update_post_meta($postId, '_sikshya_question_correct_answer', (string) $ca);
        }

        return $postId;
    }
}
