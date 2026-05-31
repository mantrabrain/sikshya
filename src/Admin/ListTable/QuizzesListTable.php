<?php

namespace Sikshya\Admin\ListTable;

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Constants\PostTypes;
use Sikshya\Services\LessonCourseLink;

/**
 * Quizzes List Table
 *
 * @package Sikshya
 * @since 1.0.0
 */
class QuizzesListTable extends AbstractListTable
{
    /**
     * Constructor
     */
    public function __construct($plugin = null)
    {
        $config = [
            'title' => __('Quizzes', 'sikshya'),
            'description' => __('Manage your quizzes', 'sikshya'),
            'singular' => 'quiz',
            'plural' => 'quizzes',
            'per_page' => 20,
            'primary_column' => 'title',
            'search' => true,
            'empty_message' => __('No quizzes found. Create your first quiz to get started.', 'sikshya'),
            'columns' => [
                'cb' => '<input type="checkbox" />',
                'title' => __('Quiz Title', 'sikshya'),
                'course' => __('Course', 'sikshya'),
                'type' => __('Type', 'sikshya'),
                'questions' => __('Questions', 'sikshya'),
                'duration' => __('Duration', 'sikshya'),
                'instructor' => __('Instructor', 'sikshya'),
                'status' => __('Status', 'sikshya'),
                'created' => __('Date', 'sikshya'),
            ],
            'sortable_columns' => [
                'title' => ['title', true],
                'course' => ['course', false],
                'type' => ['type', false],
                'questions' => ['questions', false],
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
                    'title' => __('Quiz Type', 'sikshya'),
                    'options' => [
                        '' => __('All Types', 'sikshya'),
                        'multiple_choice' => __('Multiple Choice', 'sikshya'),
                        'true_false' => __('True/False', 'sikshya'),
                        'fill_blank' => __('Fill in the Blank', 'sikshya'),
                        'essay' => __('Essay', 'sikshya'),
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
        $counts = wp_count_posts(PostTypes::QUIZ);
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
     * Returns a list of `WP_Post` rows for the QUIZ post type. The original
     * list-table prototype used hand-rolled SQL against meta keys that the
     * plugin doesn't actually persist (`_sikshya_quiz_type`,
     * `_sikshya_questions_count`); the *real* schema lives on a different
     * set of meta keys (`_sikshya_quiz_course`, `_sikshya_quiz_questions`,
     * `_sikshya_quiz_time_limit`). We use `WP_Query` so the meta lookup
     * stays compatible with WP's caching layer, post type registration, and
     * any filter that hooks into archive queries (e.g. translation plugins).
     *
     * Per-row decoration (course title, questions count, duration) is
     * derived from post meta inside the column renderers, not joined in
     * the listing query — admin pages list at most `per_page` (20) items so
     * the extra meta lookups are bounded and benefit from WP's object cache.
     *
     * @return array<int, \WP_Post>
     */
    public function get_items(): array
    {
        $args = [
            'post_type'      => PostTypes::QUIZ,
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
                'key'   => '_sikshya_quiz_course',
                'value' => $course_filter,
                'type'  => 'NUMERIC',
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
     * don't run the same query twice. If the table is rendered without
     * `get_items()` running first (defensive), fall back to a lightweight
     * `WP_Query` with `fields => 'ids'` and the same filters applied.
     *
     * @return int
     */
    public function get_total_items(): int
    {
        if ($this->total_items_cache !== null) {
            return $this->total_items_cache;
        }

        $args = [
            'post_type'      => PostTypes::QUIZ,
            'post_status'    => $this->getStatusFilter(),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            's'              => $this->getSearchTerm(),
            'no_found_rows'  => false,
        ];

        $author = $this->getInstructorFilter();
        if ($author) {
            $args['author'] = $author;
        }

        $course_filter = $this->getCourseFilter();
        if ($course_filter > 0) {
            $args['meta_query'] = [[
                'key'   => '_sikshya_quiz_course',
                'value' => $course_filter,
                'type'  => 'NUMERIC',
            ]];
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
            'suppress_filters' => false,
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
     * Sanitise the `status` filter from the request.
     *
     * @return array<int, string>|string
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

    /**
     * Sanitise the `instructor` filter from the request.
     *
     * @return int 0 when unset/invalid.
     */
    private function getInstructorFilter(): int
    {
        return max(0, intval($_GET['instructor'] ?? 0));
    }

    /**
     * Sanitise the `course` filter from the request.
     *
     * @return int 0 when unset/invalid.
     */
    private function getCourseFilter(): int
    {
        return max(0, intval($_GET['course'] ?? 0));
    }

    /**
     * Sanitise the search term.
     */
    private function getSearchTerm(): string
    {
        return sanitize_text_field((string) ($_GET['s'] ?? ''));
    }

    /**
     * Sanitise the orderby parameter against an allow-list.
     */
    private function getOrderBy(): string
    {
        $orderby = sanitize_key((string) ($_GET['orderby'] ?? 'date'));
        $allowed = ['title', 'created', 'date', 'modified'];
        if ($orderby === 'created') {
            $orderby = 'date';
        }
        return in_array($orderby, $allowed, true) ? $orderby : 'date';
    }

    /**
     * Sanitise the order direction.
     */
    private function getOrder(): string
    {
        $order = strtoupper((string) ($_GET['order'] ?? 'DESC'));
        return in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
    }

    /**
     * Map an internal lesson/quiz-content type slug to a display label.
     *
     * Quizzes don't have a single "type" — questions inside a quiz can mix
     * multiple_choice/true_false/essay/etc. The filter dropdown lets admins
     * find quizzes whose question pool includes a given type, but a quiz
     * row in the list table shows "Quiz" as the category. This helper is
     * kept for backwards compat with any caller that resolves a label
     * from a slug.
     */
    private function getQuizTypeName(string $type): string
    {
        $types = [
            'multiple_choice'   => __('Multiple Choice', 'sikshya'),
            'true_false'        => __('True/False', 'sikshya'),
            'multiple_response' => __('Multiple Response', 'sikshya'),
            'fill_blank'        => __('Fill in the Blank', 'sikshya'),
            'short_answer'      => __('Short Answer', 'sikshya'),
            'essay'             => __('Essay', 'sikshya'),
            'ordering'          => __('Ordering', 'sikshya'),
            'matching'          => __('Matching', 'sikshya'),
        ];

        return $types[$type] ?? __('Quiz', 'sikshya');
    }

    /**
     * Resolve the parent course ID for a quiz post.
     *
     * Delegates to `LessonCourseLink::resolvedCourseIdForQuiz` so this table
     * stays in lock-step with Learn / REST / admin-bar callers that already
     * use the canonical resolver. The canonical resolver memoises results
     * per-request, so iterating `per_page` (20) rows here stays cheap.
     */
    private function resolveCourseId(int $quiz_id): int
    {
        return LessonCourseLink::resolvedCourseIdForQuiz($quiz_id);
    }

    /**
     * Count questions assigned to a quiz.
     *
     * Reads the `_sikshya_quiz_questions` array (list of question IDs).
     * Returns 0 for quizzes that have never had questions assigned.
     */
    private function getQuestionsCount(int $quiz_id): int
    {
        $stored = get_post_meta($quiz_id, '_sikshya_quiz_questions', true);
        if (is_array($stored)) {
            return count($stored);
        }
        return 0;
    }

    /**
     * Get the configured time limit (in minutes) for a quiz, or 0 if unset.
     */
    private function getDuration(int $quiz_id): int
    {
        return (int) get_post_meta($quiz_id, '_sikshya_quiz_time_limit', true);
    }

    /**
     * Delete a quiz post (used by bulk-delete).
     */
    protected function delete_item($id): bool
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== PostTypes::QUIZ) {
            return false;
        }
        return wp_delete_post((int) $id, true) !== false;
    }

    /**
     * Update a quiz post status (used by bulk publish/draft).
     */
    protected function update_item_status($id, $status): bool
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== PostTypes::QUIZ) {
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
     * Default column handler.
     *
     * The list-table item is a `WP_Post`; derived facts (course title,
     * question count, time limit, instructor) are looked up on demand via
     * post meta / user queries. Admin pages list at most `per_page` (20)
     * rows so the extra lookups are bounded.
     *
     * @param \WP_Post $item
     * @param string   $column_key
     */
    protected function column_default($item, $column_key): string
    {
        switch ($column_key) {
            case 'questions':
                return '<span class="sikshya-questions-count">' . esc_html((string) $this->getQuestionsCount((int) $item->ID)) . '</span>';
            case 'duration':
                $duration = $this->getDuration((int) $item->ID);
                return '<span class="sikshya-duration">' . esc_html((string) $duration) . ' ' . esc_html__('min', 'sikshya') . '</span>';
            case 'status':
                $status_class = 'sikshya-status-' . sanitize_html_class((string) $item->post_status);
                return '<span class="sikshya-status-badge ' . esc_attr($status_class) . '">' . esc_html(ucfirst((string) $item->post_status)) . '</span>';
            case 'created':
                return '<span class="sikshya-date">' . esc_html(mysql2date(get_option('date_format', 'M j, Y'), (string) $item->post_date)) . '</span>';
            default:
                return '';
        }
    }

    /**
     * Column: Checkbox
     *
     * @param \WP_Post $item
     */
    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            esc_attr((string) $item->ID)
        );
    }

    /**
     * Column: Title
     *
     * @param \WP_Post $item
     */
    protected function columnTitle($item): string
    {
        $quiz_id   = (int) $item->ID;
        $course_id = $this->resolveCourseId($quiz_id);
        $title     = (string) $item->post_title;
        if ($title === '') {
            $title = __('(no title)', 'sikshya');
        }

        $edit_url = ReactAdminConfig::reactAppUrl('add-course', [
            'course_id' => (string) $course_id,
            'tab' => 'curriculum',
        ]);

        $delete_url = wp_nonce_url(
            ReactAdminConfig::reactAppUrl('quizzes', ['action' => 'delete', 'id' => (string) $quiz_id]),
            'delete-quiz_' . $quiz_id
        );

        $row_actions = '<div class="row-actions">';
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
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this quiz?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';

        $questions_count = $this->getQuestionsCount($quiz_id);

        $output  = '<div class="sikshya-quiz-title-wrapper">';
        $output .= '<div class="sikshya-quiz-thumbnail">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="sikshya-quiz-content">';
        $output .= sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($title)
        );
        $output .= sprintf(
            '<div class="sikshya-questions-count">%s</div>',
            esc_html(sprintf(
                /* translators: %d: number of questions in the quiz */
                _n('%d question', '%d questions', $questions_count, 'sikshya'),
                $questions_count
            ))
        );
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
        $course_id = $this->resolveCourseId((int) $item->ID);
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
     * A quiz aggregates many questions of varying types, so a single "type"
     * doesn't really exist at the quiz level. We display the dominant
     * question type if one is detectable (questions_count > 0), otherwise
     * a generic "Quiz" label. Question types are read from each question's
     * `_sikshya_question_type` meta.
     *
     * @param \WP_Post $item
     */
    protected function columnType($item): string
    {
        $stored = get_post_meta((int) $item->ID, '_sikshya_quiz_questions', true);
        $type   = '';
        if (is_array($stored) && !empty($stored)) {
            $first_qid = (int) reset($stored);
            if ($first_qid > 0) {
                $type = sanitize_key((string) get_post_meta($first_qid, '_sikshya_question_type', true));
            }
        }

        $type_name = $this->getQuizTypeName($type);

        $output  = '<div class="sikshya-quiz-type">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
        $output .= '</svg>';
        $output .= sprintf('<span>%s</span>', esc_html($type_name));
        $output .= '</div>';

        return $output;
    }

    /**
     * Column: Questions
     *
     * @param \WP_Post $item
     */
    protected function columnQuestions($item): string
    {
        $count = $this->getQuestionsCount((int) $item->ID);
        if ($count > 0) {
            return sprintf('<span class="sikshya-questions">%s</span>', esc_html((string) $count));
        }
        return '<span class="sikshya-no-questions">0</span>';
    }

    /**
     * Column: Duration
     *
     * Reads the quiz time limit (in minutes) from `_sikshya_quiz_time_limit`.
     *
     * @param \WP_Post $item
     */
    protected function columnDuration($item): string
    {
        $duration = $this->getDuration((int) $item->ID);
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
     * Uses WP's locale-aware date formatting so the admin date matches the
     * site's configured `date_format` (instead of a hard-coded English one).
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
     * Display empty state
     */
    protected function display_empty_state(): void
    {
        ?>
        <div class="sikshya-empty-state">
            <div class="sikshya-empty-state-content">
                <div class="sikshya-empty-state-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3><?php esc_html_e('No quizzes found', 'sikshya'); ?></h3>
                <p><?php esc_html_e('Get started by creating your first quiz.', 'sikshya'); ?></p>
                <a href="<?php echo esc_url(ReactAdminConfig::reactAppUrl('add-course')); ?>" class="button button-primary">
                    <?php esc_html_e('Create Quiz', 'sikshya'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
