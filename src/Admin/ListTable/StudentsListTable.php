<?php

namespace Sikshya\Admin\ListTable;

/**
 * Students List Table
 * 
 * @package Sikshya
 * @since 1.0.0
 */
class StudentsListTable extends AbstractListTable
{
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
     * Get items for the table
     * 
     * @return array
     */
    public function get_items(): array
    {
        // For demo purposes, return dummy data
        return $this->getDummyData();
        
        // TODO: Implement actual database query
        /*
        global $wpdb;
        
        $per_page = $this->get_items_per_page();
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $where_clauses = [];
        $args = [];
        
        // Apply filters
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $where_clauses[] = 'um_status.meta_value = %s';
            $args[] = sanitize_text_field($_GET['status']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "
            SELECT 
                u.*,
                um_status.meta_value as student_status,
                COUNT(DISTINCT e.course_id) as enrolled_courses,
                AVG(e.progress) as avg_progress
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_student_status'
            LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON u.ID = e.student_id
            {$where_sql}
            GROUP BY u.ID
            ORDER BY u.user_registered DESC
            LIMIT %d OFFSET %d
        ";
        
        $args = array_merge($args, [$per_page, $offset]);
        
        if (!empty($args)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $args));
        } else {
            $results = $wpdb->get_results($query);
        }
        
        return $results ?: [];
        */
    }

    /**
     * Get total number of items
     * 
     * @return int
     */
    public function get_total_items(): int
    {
        // For demo purposes, return dummy count
        return count($this->getDummyData());
        
        // TODO: Implement actual count query
        /*
        global $wpdb;
        
        $where_clauses = [];
        $args = [];
        
        // Apply filters
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $where_clauses[] = 'um_status.meta_value = %s';
            $args[] = sanitize_text_field($_GET['status']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_student_status'
            {$where_sql}
        ";
        
        if (!empty($args)) {
            return (int) $wpdb->get_var($wpdb->prepare($query, $args));
        } else {
            return (int) $wpdb->get_var($query);
        }
        */
    }

    /**
     * Get dummy data for demonstration
     * 
     * @return array
     */
    private function getDummyData(): array
    {
        return [
            (object) [
                'ID' => 1,
                'display_name' => 'Alice Johnson',
                'user_email' => 'alice.johnson@example.com',
                'user_registered' => '2024-01-15 10:30:00',
                'student_status' => 'active',
                'enrolled_courses' => 3,
                'avg_progress' => 85,
            ],
            (object) [
                'ID' => 2,
                'display_name' => 'Bob Smith',
                'user_email' => 'bob.smith@example.com',
                'user_registered' => '2024-01-14 14:20:00',
                'student_status' => 'active',
                'enrolled_courses' => 2,
                'avg_progress' => 72,
            ],
            (object) [
                'ID' => 3,
                'display_name' => 'Carol Davis',
                'user_email' => 'carol.davis@example.com',
                'user_registered' => '2024-01-13 09:15:00',
                'student_status' => 'active',
                'enrolled_courses' => 1,
                'avg_progress' => 45,
            ],
            (object) [
                'ID' => 4,
                'display_name' => 'David Wilson',
                'user_email' => 'david.wilson@example.com',
                'user_registered' => '2024-01-12 16:45:00',
                'student_status' => 'inactive',
                'enrolled_courses' => 0,
                'avg_progress' => 0,
            ],
            (object) [
                'ID' => 5,
                'display_name' => 'Eva Brown',
                'user_email' => 'eva.brown@example.com',
                'user_registered' => '2024-01-11 11:30:00',
                'student_status' => 'active',
                'enrolled_courses' => 4,
                'avg_progress' => 93,
            ],
            (object) [
                'ID' => 6,
                'display_name' => 'Frank Miller',
                'user_email' => 'frank.miller@example.com',
                'user_registered' => '2024-01-10 13:20:00',
                'student_status' => 'pending',
                'enrolled_courses' => 0,
                'avg_progress' => 0,
            ],
            (object) [
                'ID' => 7,
                'display_name' => 'Grace Taylor',
                'user_email' => 'grace.taylor@example.com',
                'user_registered' => '2024-01-09 15:10:00',
                'student_status' => 'active',
                'enrolled_courses' => 2,
                'avg_progress' => 68,
            ],
            (object) [
                'ID' => 8,
                'display_name' => 'Henry Anderson',
                'user_email' => 'henry.anderson@example.com',
                'user_registered' => '2024-01-08 10:45:00',
                'student_status' => 'active',
                'enrolled_courses' => 1,
                'avg_progress' => 25,
            ],
        ];
    }

    /**
     * Get courses list for filter
     * 
     * @return array
     */
    private function getCoursesList(): array
    {
        return [
            1 => 'Web Development Basics',
            2 => 'Advanced CSS',
            3 => 'React Development',
            4 => 'Database Management',
            5 => 'Version Control Systems',
            6 => 'Python Programming',
            7 => 'API Development',
            8 => 'Mobile Development',
        ];
    }

    /**
     * Column: Checkbox
     * 
     * @param object $item
     * @return string
     */
    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            $item->ID
        );
    }

    /**
     * Column: Name
     * 
     * @param object $item
     * @return string
     */
    protected function columnName($item): string
    {
        $name = $item->display_name;
        $edit_url = admin_url('user-edit.php?user_id=' . $item->ID);
        
        $delete_url = wp_nonce_url(admin_url('admin.php?page=sikshya-students&action=delete&id=' . $item->ID), 'delete-student_' . $item->ID);
        
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
            esc_html($item->user_email)
        );
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= $row_actions;
        
        return $output;
    }

    /**
     * Column: Email
     * 
     * @param object $item
     * @return string
     */
    protected function columnEmail($item): string
    {
        return sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr($item->user_email),
            esc_html($item->user_email)
        );
    }

    /**
     * Column: Courses
     * 
     * @param object $item
     * @return string
     */
    protected function columnCourses($item): string
    {
        if (!empty($item->enrolled_courses)) {
            $output = '<div class="sikshya-courses-info">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<span>%s courses</span>',
                esc_html($item->enrolled_courses)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-courses">' . __('No courses', 'sikshya') . '</span>';
    }

    /**
     * Column: Progress
     * 
     * @param object $item
     * @return string
     */
    protected function columnProgress($item): string
    {
        if (!empty($item->avg_progress)) {
            $progress = round($item->avg_progress);
            $output = '<div class="sikshya-progress-wrapper">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<span class="sikshya-progress">%s%%</span>',
                esc_html($progress)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-progress">' . __('0%', 'sikshya') . '</span>';
    }

    /**
     * Column: Status
     * 
     * @param object $item
     * @return string
     */
    protected function columnStatus($item): string
    {
        $status_labels = [
            'active' => __('Active', 'sikshya'),
            'inactive' => __('Inactive', 'sikshya'),
            'pending' => __('Pending', 'sikshya'),
        ];

        $status_class = 'sikshya-status-' . $item->student_status;
        $status_text = $status_labels[$item->student_status] ?? $item->student_status;

        return sprintf(
            '<span class="sikshya-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Column: Joined Date
     * 
     * @param object $item
     * @return string
     */
    protected function columnJoined($item): string
    {
        $date = new \DateTime($item->user_registered);
        $date_format = 'M j, Y';
        
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr($date->format('F j, Y g:i A')),
            esc_html($date->format($date_format))
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
