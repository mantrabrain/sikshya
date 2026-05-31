<?php

/**
 * Lessons List Table
 *
 * Displays lessons in a WordPress-style list table with sorting, filtering, and bulk actions
 *
 * @package Sikshya\Admin\ListTable
 * @since 1.0.0
 */

namespace Sikshya\Admin\ListTable;

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Constants\PostTypes;
use Sikshya\Services\LessonCourseLink;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lessons List Table Class
 *
 * Handles the display and management of lessons in the admin area
 */
class LessonsListTable extends AbstractListTable
{
    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $config = [
            'title' => __('Lessons', 'sikshya'),
            'description' => __('Manage your lessons', 'sikshya'),
            'singular' => 'lesson',
            'plural' => 'lessons',
            'per_page' => 20,
            'primary_column' => 'title',
            'search' => true,
            'empty_message' => __('No lessons found. Create your first lesson to get started.', 'sikshya'),
            'columns' => [
                'cb' => '<input type="checkbox" />',
                'title' => __('Lesson Title', 'sikshya'),
                'course' => __('Course', 'sikshya'),
                'type' => __('Type', 'sikshya'),
                'duration' => __('Duration', 'sikshya'),
                'instructor' => __('Instructor', 'sikshya'),
                'status' => __('Status', 'sikshya'),
                'created' => __('Date', 'sikshya'),
            ],
            'sortable_columns' => [
                'title' => ['title', true],
                'course' => ['course', false],
                'type' => ['type', false],
                'duration' => ['duration', false],
                'instructor' => ['instructor', false],
                'status' => ['status', false],
                'created' => ['created', false],
            ],
            'bulk_actions' => [
                'delete' => __('Delete', 'sikshya'),
                'publish' => __('Publish', 'sikshya'),
                'draft' => __('Move to Draft', 'sikshya'),
            ],
            'filters' => [
                'status' => [
                    'type' => 'select',
                    'title' => __('Status', 'sikshya'),
                    'options' => [
                        '' => __('All Statuses', 'sikshya'),
                        'publish' => __('Published', 'sikshya'),
                        'draft' => __('Draft', 'sikshya'),
                        'private' => __('Private', 'sikshya'),
                        'pending' => __('Pending Review', 'sikshya'),
                    ],
                ],
                'course' => [
                    'type' => 'select',
                    'title' => __('Course', 'sikshya'),
                    'options' => $this->getCoursesList(),
                ],
                'type' => [
                    'type' => 'select',
                    'title' => __('Lesson Type', 'sikshya'),
                    'options' => [
                        '' => __('All Types', 'sikshya'),
                        'text' => __('Text Lesson', 'sikshya'),
                        'video' => __('Video Lesson', 'sikshya'),
                        'audio' => __('Audio Lesson', 'sikshya'),
                        'quiz' => __('Quiz', 'sikshya'),
                        'assignment' => __('Assignment', 'sikshya'),
                    ],
                ],
                'instructor' => [
                    'type' => 'select',
                    'title' => __('Instructor', 'sikshya'),
                    'options' => $this->getInstructorsList(),
                ],
            ],
        ];

        parent::__construct($plugin, $config);
    }

    /**
     * Post status counts for subsubsub tabs.
     *
     * @return array<string, int>
     */
    protected function get_status_counts(): array
    {
        $counts = wp_count_posts(PostTypes::LESSON);
        if (!$counts || is_wp_error($counts)) {
            return parent::get_status_counts();
        }

        return [
            'publish' => (int) ($counts->publish ?? 0),
            'draft' => (int) ($counts->draft ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'private' => (int) ($counts->private ?? 0),
        ];
    }

    /**
     * Get items for the table.
     *
     * Lessons are stored as `sik_lesson` posts; the per-lesson kind
     * (`text`/`video`/`audio`) is *meta*, not a post type, so we filter on
     * `_sikshya_lesson_type` rather than the original prototype's
     * `sikshya_text`/`sikshya_video` post types (which never existed).
     *
     * The course filter uses `_sikshya_lesson_course` (the canonical key
     * persisted by `LessonCourseLink::persistLessonCourseId`); the legacy
     * `_sikshya_course_id` mirror is still written too, so either key
     * resolves correctly when an admin clicks "edit lesson".
     *
     * Per-row decoration (course title, instructor) is derived on demand in
     * the column renderers — admin pages list at most `per_page` (20) rows
     * so the extra lookups are bounded and benefit from WP's object cache.
     *
     * @return array<int, \WP_Post>
     */
    protected function get_items(): array
    {
        $args = [
            'post_type'      => PostTypes::LESSON,
            'post_status'    => $this->getStatusFilter(),
            'posts_per_page' => $this->get_items_per_page($this->config['per_page']),
            'paged'          => $this->get_pagenum(),
            'orderby'        => $this->getOrderBy(),
            'order'          => $this->getOrder(),
            's'              => $this->getSearchTerm(),
        ];

        $author = $this->getInstructorFilter();
        if ($author) {
            $args['author'] = $author;
        }

        $meta_query = [];
        $course_filter = $this->getCourseFilter();
        if ($course_filter > 0) {
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => '_sikshya_lesson_course', 'value' => $course_filter, 'type' => 'NUMERIC'],
                ['key' => '_sikshya_course_id',    'value' => $course_filter, 'type' => 'NUMERIC'],
            ];
        }
        $type_filter = $this->getTypeFilter();
        if ($type_filter !== '') {
            $meta_query[] = [
                'key'   => '_sikshya_lesson_type',
                'value' => $type_filter,
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query($args);
        $this->total_items_cache = (int) $query->found_posts;

        return $query->posts;
    }

    /**
     * Get total number of items for pagination.
     *
     * Reuses the count cached on the most recent `get_items()` call so we
     * don't re-run the query. Falls back to a `WP_Query` with the same
     * filters if the table is rendered without items first (defensive).
     */
    protected function get_total_items(): int
    {
        if ($this->total_items_cache !== null) {
            return $this->total_items_cache;
        }

        $args = [
            'post_type'      => PostTypes::LESSON,
            'post_status'    => $this->getStatusFilter(),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            's'              => $this->getSearchTerm(),
        ];

        $author = $this->getInstructorFilter();
        if ($author) {
            $args['author'] = $author;
        }

        $meta_query = [];
        $course_filter = $this->getCourseFilter();
        if ($course_filter > 0) {
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => '_sikshya_lesson_course', 'value' => $course_filter, 'type' => 'NUMERIC'],
                ['key' => '_sikshya_course_id',    'value' => $course_filter, 'type' => 'NUMERIC'],
            ];
        }
        $type_filter = $this->getTypeFilter();
        if ($type_filter !== '') {
            $meta_query[] = [
                'key'   => '_sikshya_lesson_type',
                'value' => $type_filter,
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query($args);
        $this->total_items_cache = (int) $query->found_posts;

        return $this->total_items_cache;
    }

    /**
     * @var int|null Cached found-rows count from the last items query.
     */
    private $total_items_cache = null;

    /**
     * Sanitise request filter helpers.
     */
    private function getStatusFilter()
    {
        $allowed = ['publish', 'draft', 'private', 'pending'];
        $status  = sanitize_key((string) ($_GET['status'] ?? $_GET['post_status'] ?? ''));
        if ($status === '' || !in_array($status, $allowed, true)) {
            return $allowed;
        }
        return $status;
    }

    private function getInstructorFilter(): int
    {
        return max(0, intval($_GET['instructor'] ?? 0));
    }

    private function getCourseFilter(): int
    {
        return max(0, intval($_GET['course'] ?? 0));
    }

    /**
     * Sanitise the lesson `type` filter. The dropdown still labels options
     * `text`/`video`/`audio`/`quiz`/`assignment` for back-compat, but this
     * table lists only `sik_lesson` posts — so `quiz` and `assignment`
     * are mapped to the empty string (no filter), since those are
     * separate post types listed in their own tables.
     */
    private function getTypeFilter(): string
    {
        $type = sanitize_key((string) ($_GET['type'] ?? ''));
        $allowed = ['text', 'video', 'audio'];
        return in_array($type, $allowed, true) ? $type : '';
    }

    private function getSearchTerm(): string
    {
        return sanitize_text_field((string) ($_GET['s'] ?? ''));
    }

    private function getOrderBy(): string
    {
        $orderby = sanitize_key((string) ($_GET['orderby'] ?? 'date'));
        $allowed = ['title', 'created', 'date', 'modified'];
        if ($orderby === 'created') {
            $orderby = 'date';
        }
        return in_array($orderby, $allowed, true) ? $orderby : 'date';
    }

    private function getOrder(): string
    {
        $order = strtoupper((string) ($_GET['order'] ?? 'DESC'));
        return in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
    }

    /**
     * Delete a lesson post (used by bulk-delete).
     */
    protected function delete_item($id): bool
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== PostTypes::LESSON) {
            return false;
        }
        return wp_delete_post((int) $id, true) !== false;
    }

    /**
     * Update a lesson post status (used by bulk publish/draft).
     */
    protected function update_item_status($id, $status): bool
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== PostTypes::LESSON) {
            return false;
        }
        $allowed = ['publish', 'draft', 'private', 'pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $result = wp_update_post(['ID' => (int) $id, 'post_status' => $status], true);
        return !is_wp_error($result);
    }

    /**
     * Get courses list for filter dropdown.
     *
     * @return array<int|string, string>
     */
    private function getCoursesList(): array
    {
        $options = ['' => __('All Courses', 'sikshya')];

        $course_ids = get_posts([
            'post_type'      => PostTypes::COURSE,
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        foreach ($course_ids as $course_id) {
            $options[(int) $course_id] = get_the_title((int) $course_id);
        }

        return $options;
    }

    /**
     * Get instructors list for filter dropdown.
     *
     * @return array<int|string, string>
     */
    private function getInstructorsList(): array
    {
        $options = ['' => __('All Instructors', 'sikshya')];

        $users = get_users([
            'role__in' => ['administrator', 'sikshya_instructor', 'instructor'],
            'orderby'  => 'display_name',
            'fields'   => ['ID', 'display_name'],
        ]);

        foreach ($users as $user) {
            $options[(int) $user->ID] = $user->display_name;
        }

        return $options;
    }

    /**
     * Map a lesson kind slug to a display label.
     *
     * Lesson kind is meta (`text`/`video`/`audio`), not a post type. The
     * legacy `sikshya_<kind>` mapping is kept for forward-compat with any
     * cached payload that still passes a prefixed post type slug in.
     */
    private function getLessonTypeName(string $kind): string
    {
        $kind = ltrim($kind, '_');
        if (str_starts_with($kind, 'sikshya_')) {
            $kind = substr($kind, strlen('sikshya_'));
        }

        $type_map = [
            'text'       => __('Text Lesson', 'sikshya'),
            'video'      => __('Video Lesson', 'sikshya'),
            'audio'      => __('Audio Lesson', 'sikshya'),
            'quiz'       => __('Quiz', 'sikshya'),
            'assignment' => __('Assignment', 'sikshya'),
        ];

        return $type_map[$kind] ?? __('Lesson', 'sikshya');
    }

    /**
     * Read the per-lesson kind slug (`text`/`video`/`audio`) from meta.
     */
    private function getLessonKind(int $lesson_id): string
    {
        return sanitize_key((string) get_post_meta($lesson_id, '_sikshya_lesson_type', true));
    }

    /**
     * Read the per-lesson duration (minutes) from meta. Falls back across
     * `_sikshya_lesson_duration` (current) and `_sikshya_duration` (legacy).
     */
    private function getLessonDuration(int $lesson_id): int
    {
        $duration = (int) get_post_meta($lesson_id, '_sikshya_lesson_duration', true);
        if ($duration > 0) {
            return $duration;
        }
        return (int) get_post_meta($lesson_id, '_sikshya_duration', true);
    }

    /**
     * SVG path lookup for a lesson kind icon. Keys are bare slugs
     * (`text`/`video`/`audio`) matching `_sikshya_lesson_type` values.
     *
     * @var array<string, string>
     */
    private const KIND_ICON_PATHS = [
        'text'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
        'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>',
        'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>',
    ];

    private const KIND_ICON_FALLBACK = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';

    /**
     * Column: Title
     *
     * @param \WP_Post $item
     */
    protected function columnTitle($item): string
    {
        $lesson_id = (int) $item->ID;
        $course_id = LessonCourseLink::resolvedCourseIdForLesson($lesson_id);
        $kind      = $this->getLessonKind($lesson_id);
        $title     = (string) $item->post_title;
        if ($title === '') {
            $title = __('(no title)', 'sikshya');
        }

        $edit_url = ReactAdminConfig::reactAppUrl('add-course', [
            'course_id' => (string) $course_id,
            'tab' => 'curriculum',
        ]);

        $delete_url = wp_nonce_url(
            ReactAdminConfig::reactAppUrl('lessons', ['action' => 'delete', 'id' => (string) $lesson_id]),
            'delete-lesson_' . $lesson_id
        );

        $row_actions  = '<div class="row-actions">';
        $row_actions .= '<span class="edit">';
        $row_actions .= '<a href="' . esc_url($edit_url) . '">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Edit', 'sikshya') . '</a> | </span>';

        $row_actions .= '<span class="view">';
        $row_actions .= '<a href="#" onclick="return false;">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('View', 'sikshya') . '</a> | </span>';

        $row_actions .= '<span class="delete">';
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this lesson?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';

        $icon_path = self::KIND_ICON_PATHS[$kind] ?? self::KIND_ICON_FALLBACK;
        $duration  = $this->getLessonDuration($lesson_id);

        $output  = '<div class="sikshya-lesson-title-wrapper">';
        $output .= '<div class="sikshya-lesson-thumbnail">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= $icon_path;
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="sikshya-lesson-content">';
        $output .= sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($title)
        );
        if ($duration > 0) {
            $output .= sprintf(
                '<div class="sikshya-lesson-duration">%s %s</div>',
                esc_html((string) $duration),
                esc_html__('min', 'sikshya')
            );
        }
        $output .= '</div>';
        $output .= '</div>';

        $output .= $row_actions;

        return $output;
    }

    /**
     * Column: Course
     *
     * @param \WP_Post $item
     */
    protected function columnCourse($item): string
    {
        $course_id = LessonCourseLink::resolvedCourseIdForLesson((int) $item->ID);
        if ($course_id <= 0) {
            return '<span class="sikshya-no-course">' . esc_html__('No Course', 'sikshya') . '</span>';
        }

        $course_title = (string) get_the_title($course_id);
        if ($course_title === '') {
            $course_title = __('(no title)', 'sikshya');
        }

        $output  = '<div class="sikshya-course-info">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
        $output .= '</svg>';
        $output .= sprintf(
            '<a href="%s">%s</a>',
            esc_url(ReactAdminConfig::reactAppUrl('add-course', ['course_id' => (string) $course_id])),
            esc_html($course_title)
        );
        $output .= '</div>';
        return $output;
    }

    /**
     * Column: Type
     *
     * @param \WP_Post $item
     */
    protected function columnType($item): string
    {
        $kind      = $this->getLessonKind((int) $item->ID);
        $type_name = $this->getLessonTypeName($kind);
        $icon_path = self::KIND_ICON_PATHS[$kind] ?? self::KIND_ICON_FALLBACK;

        $output  = '<div class="sikshya-lesson-type">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= $icon_path;
        $output .= '</svg>';
        $output .= sprintf('<span>%s</span>', esc_html($type_name));
        $output .= '</div>';

        return $output;
    }

    /**
     * Column: Duration
     *
     * @param \WP_Post $item
     */
    protected function columnDuration($item): string
    {
        $duration = $this->getLessonDuration((int) $item->ID);
        if ($duration <= 0) {
            return '<span class="sikshya-no-duration">' . esc_html__('Not set', 'sikshya') . '</span>';
        }

        $output  = '<div class="sikshya-duration-wrapper">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        $output .= '</svg>';
        $output .= sprintf(
            '<span class="sikshya-duration">%s %s</span>',
            esc_html((string) $duration),
            esc_html__('min', 'sikshya')
        );
        $output .= '</div>';
        return $output;
    }

    /**
     * Column: Instructor
     *
     * @param \WP_Post $item
     */
    protected function columnInstructor($item): string
    {
        $user = $item->post_author ? get_user_by('id', (int) $item->post_author) : null;
        if (!$user) {
            return '<span class="sikshya-no-instructor">' . esc_html__('Unknown', 'sikshya') . '</span>';
        }

        $output  = '<div class="sikshya-instructors">';
        $output .= sprintf(
            '<div class="sikshya-instructor">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>%s</span>
            </div>',
            esc_html((string) $user->display_name)
        );
        $output .= '</div>';
        return $output;
    }

    /**
     * Column: Status
     *
     * @param \WP_Post $item
     */
    protected function columnStatus($item): string
    {
        $status_labels = [
            'publish' => __('Published', 'sikshya'),
            'draft'   => __('Draft', 'sikshya'),
            'private' => __('Private', 'sikshya'),
            'pending' => __('Pending Review', 'sikshya'),
        ];

        $status_class = 'sikshya-status-' . sanitize_html_class((string) $item->post_status);
        $status_text  = $status_labels[$item->post_status] ?? (string) $item->post_status;

        return sprintf(
            '<span class="sikshya-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Column: Created Date
     *
     * @param \WP_Post $item
     */
    protected function columnCreated($item): string
    {
        $date_format = get_option('date_format', 'M j, Y');
        $time_format = get_option('time_format', 'g:i a');

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(mysql2date($date_format . ' ' . $time_format, (string) $item->post_date)),
            esc_html(mysql2date($date_format, (string) $item->post_date))
        );
    }

    /**
     * Column default
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'title':
                return $this->columnTitle($item);
            case 'course':
                return $this->columnCourse($item);
            case 'type':
                return $this->columnType($item);
            case 'duration':
                return $this->columnDuration($item);
            case 'instructor':
                return $this->columnInstructor($item);
            case 'status':
                return $this->columnStatus($item);
            case 'created':
                return $this->columnCreated($item);
            default:
                return esc_html($item->$column_name ?? '');
        }
    }

    /**
     * Column checkbox
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            $item->ID
        );
    }

    /**
     * Display empty state
     *
     * @return void
     */
    protected function display_empty_state(): void
    {
        echo '<tr>';
        echo '<td colspan="' . count($this->get_columns()) . '" class="sikshya-no-items">';
        echo '<div class="sikshya-empty-state">';
        echo '<svg class="sikshya-empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
        echo '</svg>';
        echo '<h3 class="sikshya-empty-state-title">' . esc_html($this->config['empty_message']) . '</h3>';
        echo '<p class="sikshya-empty-state-description">' . esc_html__('Get started by creating your first lesson.', 'sikshya') . '</p>';
        echo '<a href="' . esc_url(ReactAdminConfig::reactAppUrl('add-course')) . '" class="sikshya-btn sikshya-btn-primary">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>';
        echo '</svg>';
        echo esc_html__('Create Lesson', 'sikshya');
        echo '</a>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Get default sortable columns
     *
     * @return array
     */
    protected function getDefaultSortableColumns(): array
    {
        return ['created' => ['created', true]];
    }
}
