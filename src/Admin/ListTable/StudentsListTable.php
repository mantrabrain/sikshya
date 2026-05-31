<?php

namespace Sikshya\Admin\ListTable;

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * Students List Table
 *
 * @package Sikshya
 * @since 1.0.0
 */
class StudentsListTable extends AbstractListTable
{
    /**
     * Lazy repository handle for enrollment aggregates (count, completion).
     * Resolved on first use because constructor runs before the DB layer is
     * guaranteed to be ready in some bootstrap orders.
     */
    private ?EnrollmentRepository $enrollment_repo = null;

    /**
     * @var array<int, array{enrolled:int, completed:int, total:int}>|null
     *   Aggregate enrollment counts keyed by user_id, populated once per
     *   `get_items()` call to avoid N+1 queries across column renderers.
     */
    private ?array $aggregates_cache = null;

    /**
     * @var int|null Cached found-rows count from the last user query.
     */
    private $total_items_cache = null;

    /**
     * Constructor
     */
    public function __construct($plugin = null)
    {
        $config = [
            'title' => __('Students', 'sikshya'),
            'description' => __('Manage your students', 'sikshya'),
            'singular' => 'student',
            'plural' => 'students',
            'per_page' => 20,
            'primary_column' => 'name',
            'search' => true,
            'empty_message' => __('No students found. Students will appear here once they enroll in courses.', 'sikshya'),
            'columns' => [
                'cb' => '<input type="checkbox" />',
                'name' => __('Student Name', 'sikshya'),
                'email' => __('Email', 'sikshya'),
                'courses' => __('Courses', 'sikshya'),
                'progress' => __('Progress', 'sikshya'),
                'status' => __('Status', 'sikshya'),
                'joined' => __('Joined', 'sikshya'),
            ],
            'sortable_columns' => [
                'name' => ['name', true],
                'email' => ['email', false],
                'courses' => ['courses', false],
                'progress' => ['progress', false],
                'status' => ['status', false],
                'joined' => ['joined', false],
            ],
            'bulk_actions' => [
                'delete' => __('Delete', 'sikshya'),
                'activate' => __('Activate', 'sikshya'),
                'deactivate' => __('Deactivate', 'sikshya'),
            ],
            'filters' => [
                'status' => [
                    'type' => 'select',
                    'title' => __('Status', 'sikshya'),
                    'options' => [
                        '' => __('All Statuses', 'sikshya'),
                        'active' => __('Active', 'sikshya'),
                        'inactive' => __('Inactive', 'sikshya'),
                        'pending' => __('Pending', 'sikshya'),
                    ],
                ],
                'course' => [
                    'type' => 'select',
                    'title' => __('Course', 'sikshya'),
                    'options' => $this->getCoursesList(),
                ],
            ],
        ];

        parent::__construct($plugin, $config);
    }

    /**
     * Get items for the table.
     *
     * Two-stage query:
     *
     * 1. **Pick the user set** via `WP_User_Query`. If a *course filter* is
     *    active we narrow the user set to learners actually enrolled in
     *    that course (a `user_id IN (...)` subset of the enrollments table)
     *    so the table reflects "students of this course" rather than "all
     *    site users". Otherwise we list all users; the LMS doesn't have a
     *    single "student" role gate, so anyone with a WP account is a
     *    potential learner.
     *
     * 2. **Aggregate enrollments** in one SQL grouped by `user_id` so the
     *    column renderers don't run N+1 lookups. The result is memoised in
     *    `$aggregates_cache` keyed by user id.
     *
     * Status filter (`active`/`inactive`/`pending`) maps to:
     *   - active   → has at least one row with status='enrolled'
     *   - inactive → has zero enrollments OR all completed
     *   - pending  → has at least one row with status='pending'
     *
     * @return array<int, \WP_User>
     */
    public function get_items(): array
    {
        $per_page = $this->get_items_per_page($this->config['per_page']);
        $paged    = max(1, (int) $this->get_pagenum());

        $args = [
            'number'  => $per_page,
            'paged'   => $paged,
            'orderby' => $this->getOrderBy(),
            'order'   => $this->getOrder(),
            'search'  => '',
            'fields'  => 'all',
            'count_total' => true,
        ];

        $term = $this->getSearchTerm();
        if ($term !== '') {
            // WP_User_Query LIKE wildcards search login/email/url/nicename/display_name.
            $args['search'] = '*' . $term . '*';
            $args['search_columns'] = ['user_login', 'user_nicename', 'user_email', 'display_name'];
        }

        $course_filter = $this->getCourseFilter();
        $status_filter = $this->getStatusFilterRaw();
        $include_ids   = $this->resolveIncludeUserIds($course_filter, $status_filter);
        if ($include_ids !== null) {
            if ($include_ids === []) {
                // Filter matched zero users — skip the WP_User_Query entirely.
                $this->total_items_cache = 0;
                $this->aggregates_cache  = [];
                return [];
            }
            $args['include'] = $include_ids;
        }

        $query = new \WP_User_Query($args);
        $users = (array) $query->get_results();

        $this->total_items_cache = (int) $query->get_total();
        $this->aggregates_cache  = $this->aggregateEnrollments(
            array_map(static fn ($u) => (int) $u->ID, $users)
        );

        return $users;
    }

    /**
     * Get total number of items for pagination.
     *
     * Reuses the count cached on the most recent `get_items()` call. Falls
     * back to a count-only `WP_User_Query` if the table is rendered without
     * items first (defensive).
     */
    public function get_total_items(): int
    {
        if ($this->total_items_cache !== null) {
            return $this->total_items_cache;
        }

        $args = ['number' => 1, 'count_total' => true, 'fields' => 'ID'];

        $term = $this->getSearchTerm();
        if ($term !== '') {
            $args['search'] = '*' . $term . '*';
            $args['search_columns'] = ['user_login', 'user_nicename', 'user_email', 'display_name'];
        }

        $include_ids = $this->resolveIncludeUserIds(
            $this->getCourseFilter(),
            $this->getStatusFilterRaw()
        );
        if ($include_ids !== null) {
            if ($include_ids === []) {
                return $this->total_items_cache = 0;
            }
            $args['include'] = $include_ids;
        }

        $query = new \WP_User_Query($args);
        return $this->total_items_cache = (int) $query->get_total();
    }

    /**
     * Resolve the user IDs to restrict the user query to, based on the
     * course and status filters.
     *
     * @return int[]|null  null → no restriction, [] → restriction is empty (no rows)
     */
    private function resolveIncludeUserIds(int $course_id, string $status): ?array
    {
        global $wpdb;
        $repo = $this->repo();
        if (!$repo->tableExists()) {
            return null;
        }

        $where = [];
        $args  = [];

        if ($course_id > 0) {
            $where[] = 'course_id = %d';
            $args[]  = $course_id;
        }

        // Map UI status to enrollment-table status.
        $map = ['active' => 'enrolled', 'pending' => 'pending'];
        if (isset($map[$status])) {
            $where[] = 'status = %s';
            $args[]  = $map[$status];
        } elseif ($status === 'inactive') {
            // Inactive = zero enrollments OR all completed. We can't express
            // "zero enrollments" via an INCLUDE list, so we fall back to no
            // restriction and let the column renderers reflect the state.
            return null;
        }

        if (empty($where)) {
            return null;
        }

        $table = \Sikshya\Database\Tables\EnrollmentsTable::getTableName();
        $sql   = "SELECT DISTINCT user_id FROM {$table} WHERE " . implode(' AND ', $where);
        $rows  = $wpdb->get_col($wpdb->prepare($sql, $args));

        return array_map('intval', (array) $rows);
    }

    /**
     * Aggregate enrollment counts for a set of users in a single query.
     *
     * @param  int[] $user_ids
     * @return array<int, array{enrolled:int, completed:int, total:int}>
     */
    private function aggregateEnrollments(array $user_ids): array
    {
        $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
        if ($user_ids === []) {
            return [];
        }

        $repo = $this->repo();
        if (!$repo->tableExists()) {
            return [];
        }

        global $wpdb;
        $table = \Sikshya\Database\Tables\EnrollmentsTable::getTableName();
        $in    = implode(',', array_fill(0, count($user_ids), '%d'));
        $sql   = "SELECT user_id, status, COUNT(*) AS n
                  FROM {$table}
                  WHERE user_id IN ($in)
                  GROUP BY user_id, status";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $user_ids));

        $out = [];
        foreach ($user_ids as $uid) {
            $out[$uid] = ['enrolled' => 0, 'completed' => 0, 'total' => 0];
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $uid    = (int) $row->user_id;
                $status = (string) $row->status;
                $n      = (int) $row->n;
                if (!isset($out[$uid])) {
                    continue;
                }
                $out[$uid]['total'] += $n;
                if ($status === 'enrolled') {
                    $out[$uid]['enrolled'] = $n;
                } elseif ($status === 'completed') {
                    $out[$uid]['completed'] = $n;
                }
            }
        }
        return $out;
    }

    /**
     * Lazy repository accessor.
     */
    private function repo(): EnrollmentRepository
    {
        if ($this->enrollment_repo === null) {
            $this->enrollment_repo = new EnrollmentRepository();
        }
        return $this->enrollment_repo;
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
     * Filter helpers.
     */
    private function getCourseFilter(): int
    {
        return max(0, intval($_GET['course'] ?? 0));
    }

    private function getStatusFilterRaw(): string
    {
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        return in_array($status, ['active', 'inactive', 'pending'], true) ? $status : '';
    }

    private function getSearchTerm(): string
    {
        return sanitize_text_field((string) ($_GET['s'] ?? ''));
    }

    private function getOrderBy(): string
    {
        $orderby = sanitize_key((string) ($_GET['orderby'] ?? 'registered'));
        $map = [
            'name'     => 'display_name',
            'email'    => 'user_email',
            'joined'   => 'user_registered',
            'created'  => 'user_registered',
        ];
        if (isset($map[$orderby])) {
            return $map[$orderby];
        }
        $allowed = ['display_name', 'user_email', 'user_registered', 'user_login', 'ID'];
        return in_array($orderby, $allowed, true) ? $orderby : 'user_registered';
    }

    private function getOrder(): string
    {
        $order = strtoupper((string) ($_GET['order'] ?? 'DESC'));
        return in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
    }

    /**
     * Resolve aggregate counts for a user from the cache, falling back to a
     * fresh single-user query when the cache hasn't been warmed (defensive).
     *
     * @return array{enrolled:int, completed:int, total:int}
     */
    private function getAggregates(int $user_id): array
    {
        if ($this->aggregates_cache !== null && isset($this->aggregates_cache[$user_id])) {
            return $this->aggregates_cache[$user_id];
        }

        $repo = $this->repo();
        $total = $repo->countByUser($user_id);
        return [
            'enrolled'  => $repo->countActiveEnrollmentsForUser($user_id),
            'completed' => max(0, $total - $repo->countActiveEnrollmentsForUser($user_id)),
            'total'     => $total,
        ];
    }

    /**
     * Column: Checkbox
     *
     * @param \WP_User $item
     */
    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            esc_attr((string) $item->ID)
        );
    }

    /**
     * Column: Name
     *
     * @param \WP_User $item
     */
    protected function columnName($item): string
    {
        $name     = (string) $item->display_name;
        $edit_url = admin_url('user-edit.php?user_id=' . (int) $item->ID);

        $delete_url = wp_nonce_url(
            ReactAdminConfig::reactAppUrl('students', ['action' => 'delete', 'id' => (string) $item->ID]),
            'delete-student_' . $item->ID
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
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this student?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';

        $output = '<div class="sikshya-student-title-wrapper">';
        $output .= '<div class="sikshya-student-thumbnail">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>';
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="sikshya-student-content">';
        $output .= sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($name)
        );
        $output .= sprintf(
            '<div class="sikshya-student-email">%s</div>',
            esc_html((string) $item->user_email)
        );
        $output .= '</div>';
        $output .= '</div>';

        $output .= $row_actions;

        return $output;
    }

    /**
     * Column: Email
     *
     * @param \WP_User $item
     */
    protected function columnEmail($item): string
    {
        $email = (string) $item->user_email;
        return sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr($email),
            esc_html($email)
        );
    }

    /**
     * Column: Courses (enrolled count).
     *
     * Reads the pre-aggregated count cached on the most recent `get_items()`
     * call. We show the **total** count (active + completed) so the column
     * matches the user's mental model of "courses they've taken".
     *
     * @param \WP_User $item
     */
    protected function columnCourses($item): string
    {
        $agg = $this->getAggregates((int) $item->ID);
        if ($agg['total'] <= 0) {
            return '<span class="sikshya-no-courses">' . esc_html__('No courses', 'sikshya') . '</span>';
        }

        $output  = '<div class="sikshya-courses-info">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
        $output .= '</svg>';
        $output .= sprintf(
            '<span>%s</span>',
            esc_html(sprintf(
                /* translators: %d: number of courses the student is enrolled in */
                _n('%d course', '%d courses', $agg['total'], 'sikshya'),
                $agg['total']
            ))
        );
        $output .= '</div>';
        return $output;
    }

    /**
     * Column: Progress.
     *
     * Surfaces the completion ratio (completed / total enrollments) as a
     * percentage. We deliberately *don't* compute a per-lesson rollup here
     * — the per-lesson computation lives in `ProgressRepository::getCourseProgress`
     * and is expensive enough that calling it for every row in an admin
     * table would be wrong (use the gradebook / reports addon for that).
     *
     * @param \WP_User $item
     */
    protected function columnProgress($item): string
    {
        $agg = $this->getAggregates((int) $item->ID);
        if ($agg['total'] <= 0) {
            return '<span class="sikshya-no-progress">0%</span>';
        }

        $pct = (int) round(($agg['completed'] / $agg['total']) * 100);

        $output  = '<div class="sikshya-progress-wrapper">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>';
        $output .= '</svg>';
        $output .= sprintf(
            '<span class="sikshya-progress">%s%%</span>',
            esc_html((string) $pct)
        );
        $output .= '</div>';
        return $output;
    }

    /**
     * Column: Status.
     *
     * Derived from enrollment aggregates:
     *   - active   → at least one enrolled-status row
     *   - inactive → zero enrollments
     *   - pending  → only pending rows (no enrolled, no completed)
     *
     * @param \WP_User $item
     */
    protected function columnStatus($item): string
    {
        $agg = $this->getAggregates((int) $item->ID);

        if ($agg['enrolled'] > 0) {
            $status = 'active';
        } elseif ($agg['total'] === 0) {
            $status = 'inactive';
        } elseif ($agg['completed'] === 0) {
            $status = 'pending';
        } else {
            // All enrollments completed but none currently active.
            $status = 'inactive';
        }

        $status_labels = [
            'active'   => __('Active', 'sikshya'),
            'inactive' => __('Inactive', 'sikshya'),
            'pending'  => __('Pending', 'sikshya'),
        ];

        $status_class = 'sikshya-status-' . $status;
        $status_text  = $status_labels[$status];

        return sprintf(
            '<span class="sikshya-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Column: Joined Date
     *
     * @param \WP_User $item
     */
    protected function columnJoined($item): string
    {
        $date_format = get_option('date_format', 'M j, Y');
        $time_format = get_option('time_format', 'g:i a');
        $registered  = (string) $item->user_registered;

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(mysql2date($date_format . ' ' . $time_format, $registered)),
            esc_html(mysql2date($date_format, $registered))
        );
    }

    /**
     * Default column
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    protected function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'email':
                return $this->columnEmail($item);
            case 'courses':
                return $this->columnCourses($item);
            case 'progress':
                return $this->columnProgress($item);
            case 'joined':
                return $this->columnJoined($item);
            default:
                return esc_html($item->$column_name ?? '');
        }
    }

    /**
     * Display empty state
     */
    protected function display_empty_state(): void
    {
        echo '<tr>';
        echo '<td colspan="' . count($this->get_columns()) . '" class="sikshya-no-items">';
        echo '<div class="sikshya-empty-state">';
        echo '<svg class="sikshya-empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>';
        echo '</svg>';
        echo '<h3 class="sikshya-empty-state-title">' . esc_html__('No students found', 'sikshya') . '</h3>';
        echo '<p class="sikshya-empty-state-description">' . esc_html__('Students will appear here once they enroll in courses.', 'sikshya') . '</p>';
        echo '<a href="' . admin_url('user-new.php') . '" class="sikshya-btn sikshya-btn-primary">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>';
        echo '</svg>';
        echo esc_html__('Add Student', 'sikshya');
        echo '</a>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
}
