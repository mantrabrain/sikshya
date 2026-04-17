<?php

/**
 * Course curriculum orchestration (chapters, content links, ordering).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\ContentPostRepository;
use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\PostMetaRepository;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class CurriculumService
{
    public function __construct(
        private Plugin $plugin,
        private PostMetaRepository $meta,
        private CourseRepository $courses,
        private ContentPostRepository $content_posts
    ) {
    }

    private function curriculumActions(): ?CourseCurriculumActions
    {
        $ui = $this->plugin->getService('courseBuilderUi');
        return $ui instanceof CourseCurriculumActions ? $ui : null;
    }

    /**
     * @return array{success:bool,message?:string,data?:array}
     */
    public function createContent(array $params): array
    {
        $actions = $this->curriculumActions();
        if (!$actions) {
            return ['success' => false, 'message' => __('Course handler unavailable', 'sikshya')];
        }

        return $actions->createContentForService($params);
    }

    public function linkContentToChapter(int $content_id, int $chapter_id): array
    {
        if ($content_id <= 0 || $chapter_id <= 0) {
            return ['success' => false, 'message' => __('Invalid content or chapter ID', 'sikshya')];
        }

        $content_post = get_post($content_id);
        if (!$content_post) {
            return ['success' => false, 'message' => __('Content not found', 'sikshya')];
        }

        $chapter_contents = $this->meta->get($chapter_id, '_sikshya_contents', true);
        if (!is_array($chapter_contents)) {
            $chapter_contents = [];
        }
        $chapter_contents[] = $content_id;
        $this->meta->update($chapter_id, '_sikshya_contents', $chapter_contents);

        $course_id = (int) get_post_field('post_parent', $chapter_id);
        if ($course_id > 0) {
            $course_chapters = $this->meta->get($course_id, '_sikshya_chapters', true);
            if (!is_array($course_chapters)) {
                $course_chapters = [];
            }
            if (!in_array($chapter_id, $course_chapters, true)) {
                $course_chapters[] = $chapter_id;
                $this->meta->update($course_id, '_sikshya_chapters', $course_chapters);
            }

            // Ensure newly-created content is linked back to its course (Learn templates rely on this meta).
            if ((string) $content_post->post_type === PostTypes::LESSON) {
                update_post_meta($content_id, '_sikshya_lesson_course', $course_id);
                // Back-compat for older readers (non-prefixed).
                update_post_meta($content_id, 'sikshya_lesson_course', $course_id);
            } elseif ((string) $content_post->post_type === PostTypes::QUIZ) {
                update_post_meta($content_id, '_sikshya_quiz_course', $course_id);
            } elseif ((string) $content_post->post_type === PostTypes::ASSIGNMENT) {
                update_post_meta($content_id, '_sikshya_assignment_course', $course_id);
            }
        }

        return ['success' => true, 'message' => __('Content linked to chapter successfully', 'sikshya'), 'data' => []];
    }

    /**
     * Reorder chapters for a course. Updates each chapter's `_sikshya_order` (used by the curriculum tree)
     * and stores the ID list on the course for legacy AJAX consumers.
     *
     * @param array<int,mixed> $order Chapter post IDs in display order.
     */
    public function saveChapterOrder(int $course_id, array $order): array
    {
        if ($course_id <= 0 || !$this->courses->isCourse($course_id)) {
            return ['success' => false, 'message' => __('Invalid course ID', 'sikshya')];
        }

        $order = array_values(array_map('absint', $order));
        $order = array_values(array_unique(array_filter($order, static fn ($id) => $id > 0)));

        $existing = get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_parent' => $course_id,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]
        );
        $existing = array_map('intval', $existing);
        sort($existing);
        $sorted_incoming = $order;
        sort($sorted_incoming);
        if ($sorted_incoming !== $existing) {
            return ['success' => false, 'message' => __('Chapter list must include every chapter for this course exactly once.', 'sikshya')];
        }

        foreach ($order as $i => $chapter_id) {
            update_post_meta((int) $chapter_id, '_sikshya_order', $i + 1);
        }
        $this->meta->update($course_id, '_sikshya_chapter_order', $order);

        return ['success' => true, 'message' => __('Chapter order saved successfully', 'sikshya')];
    }

    /**
     * Replace a chapter's curriculum content list (`_sikshya_contents`). Order matches the outline.
     *
     * @param array<int,mixed> $order Content post IDs in order.
     */
    public function saveLessonOrder(int $chapter_id, array $order): array
    {
        if ($chapter_id <= 0) {
            return ['success' => false, 'message' => __('Invalid chapter ID', 'sikshya')];
        }
        $chapter = get_post($chapter_id);
        if (!$chapter || PostTypes::CHAPTER !== (string) $chapter->post_type) {
            return ['success' => false, 'message' => __('Chapter not found', 'sikshya')];
        }

        $order = array_values(array_map('absint', $order));
        $order = array_values(array_unique(array_filter($order, static fn ($id) => $id > 0)));

        $allowed = [PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT, PostTypes::QUESTION];
        $clean = [];
        foreach ($order as $pid) {
            $pt = get_post_type($pid);
            if ($pt && in_array($pt, $allowed, true)) {
                $clean[] = $pid;
            }
        }

        $this->meta->update($chapter_id, '_sikshya_contents', $clean);
        $this->meta->update($chapter_id, '_sikshya_lesson_order', $clean);

        return ['success' => true, 'message' => __('Lesson order saved successfully', 'sikshya')];
    }

    /**
     * Atomically save chapter order and every chapter's content list (for drag-and-drop outline).
     *
     * @param array<int,int> $chapter_order
     * @param array<int,array<string,mixed>> $blocks Each: chapter_id (int), content_ids (int[])
     */
    public function saveOutlineStructure(int $course_id, array $chapter_order, array $blocks): array
    {
        if ($course_id <= 0 || !$this->courses->isCourse($course_id)) {
            return ['success' => false, 'message' => __('Invalid course ID', 'sikshya')];
        }

        $existing_chapter_ids = get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_parent' => $course_id,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]
        );
        $existing_chapter_ids = array_map('intval', $existing_chapter_ids);
        sort($existing_chapter_ids);

        $chapter_order = array_values(array_map('absint', $chapter_order));
        $chapter_order = array_values(array_unique(array_filter($chapter_order, static fn ($id) => $id > 0)));
        $sorted_order = $chapter_order;
        sort($sorted_order);
        if ($sorted_order !== $existing_chapter_ids) {
            return ['success' => false, 'message' => __('Chapter order must list every chapter for this course exactly once.', 'sikshya')];
        }

        foreach ($chapter_order as $i => $cid) {
            update_post_meta((int) $cid, '_sikshya_order', $i + 1);
        }
        $this->meta->update($course_id, '_sikshya_chapter_order', $chapter_order);

        $by_chapter = [];
        foreach ($blocks as $row) {
            if (!is_array($row)) {
                return ['success' => false, 'message' => __('Invalid outline payload.', 'sikshya')];
            }
            $ch_id = absint($row['chapter_id'] ?? 0);
            $raw_ids = isset($row['content_ids']) && is_array($row['content_ids']) ? $row['content_ids'] : [];
            if ($ch_id <= 0) {
                return ['success' => false, 'message' => __('Invalid chapter in outline.', 'sikshya')];
            }
            if (!in_array($ch_id, $existing_chapter_ids, true)) {
                return ['success' => false, 'message' => __('Outline references a chapter that does not belong to this course.', 'sikshya')];
            }
            $by_chapter[$ch_id] = array_values(array_map('absint', $raw_ids));
        }

        if (count($by_chapter) !== count($existing_chapter_ids)) {
            return ['success' => false, 'message' => __('Outline must include every chapter.', 'sikshya')];
        }

        $allowed = [PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT, PostTypes::QUESTION];
        $seen_content = [];

        foreach ($existing_chapter_ids as $ecid) {
            if (!array_key_exists($ecid, $by_chapter)) {
                return ['success' => false, 'message' => __('Outline must include every chapter.', 'sikshya')];
            }
            $clean = [];
            foreach ($by_chapter[$ecid] as $pid) {
                if ($pid <= 0) {
                    return ['success' => false, 'message' => __('Invalid content in curriculum outline.', 'sikshya')];
                }
                if (isset($seen_content[$pid])) {
                    return ['success' => false, 'message' => __('Each lesson, quiz, or assignment may only appear once in the outline.', 'sikshya')];
                }
                $pt = get_post_type($pid);
                if (!$pt || !in_array($pt, $allowed, true)) {
                    return ['success' => false, 'message' => __('Invalid content in curriculum outline.', 'sikshya')];
                }
                $seen_content[$pid] = true;
                $clean[] = $pid;
            }
            $this->meta->update($ecid, '_sikshya_contents', $clean);
            $this->meta->update($ecid, '_sikshya_lesson_order', $clean);
        }

        return ['success' => true, 'message' => __('Curriculum outline saved.', 'sikshya')];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function saveContentItem(int $item_id, array $data): array
    {
        if ($item_id <= 0) {
            return ['success' => false, 'message' => __('Invalid item ID', 'sikshya')];
        }

        $ok = $this->content_posts->updateCore(
            $item_id,
            (string) ($data['title'] ?? ''),
            (string) ($data['description'] ?? '')
        );

        return $ok
            ? ['success' => true, 'message' => __('Content saved successfully', 'sikshya')]
            : ['success' => false, 'message' => __('Failed to save content', 'sikshya')];
    }

    public function deleteCourse(int $course_id): array
    {
        if ($course_id <= 0 || !$this->courses->isCourse($course_id)) {
            return ['success' => false, 'message' => __('Invalid course', 'sikshya')];
        }

        return $this->courses->delete($course_id)
            ? ['success' => true, 'message' => __('Course deleted successfully', 'sikshya')]
            : ['success' => false, 'message' => __('Failed to delete course', 'sikshya')];
    }
}
