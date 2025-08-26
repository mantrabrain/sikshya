<?php

namespace Sikshya\Admin\ListTable;

/**
 * Instructors List Table
 * 
 * @package Sikshya
 * @since 1.0.0
 */
class InstructorsListTable extends AbstractListTable
{
    /**
     * Constructor
     */
    public function __construct($plugin = null)
    {
        $config = [
            'singular' => 'instructor',
            'plural' => 'instructors',
            'ajax' => false,
            'columns' => [
                'cb' => '<input type="checkbox" />',
                'name' => __('INSTRUCTOR NAME', 'sikshya'),
                'email' => __('EMAIL', 'sikshya'),
                'courses' => __('COURSES', 'sikshya'),
                'students' => __('STUDENTS', 'sikshya'),
                'rating' => __('RATING', 'sikshya'),
                'status' => __('STATUS', 'sikshya'),
                'joined' => __('JOINED DATE', 'sikshya'),
            ],
            'sortable_columns' => [
                'name' => ['name', true],
                'email' => ['email', false],
                'courses' => ['courses', false],
                'students' => ['students', false],
                'rating' => ['rating', false],
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
                    'label' => __('All Statuses', 'sikshya'),
                    'options' => [
                        'active' => __('Active', 'sikshya'),
                        'inactive' => __('Inactive', 'sikshya'),
                        'pending' => __('Pending', 'sikshya'),
                    ],
                ],
            ],
            'empty_message' => __('No instructors found. Create your first instructor to get started.', 'sikshya'),
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
                um_status.meta_value as instructor_status,
                COUNT(DISTINCT c.ID) as courses_count,
                COUNT(DISTINCT e.student_id) as students_count,
                AVG(um_rating.meta_value) as avg_rating
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_instructor_status'
            LEFT JOIN {$wpdb->posts} c ON u.ID = c.post_author AND c.post_type = 'sik_course'
            LEFT JOIN {$wpdb->prefix}sikshya_enrollments e ON c.ID = e.course_id
            LEFT JOIN {$wpdb->usermeta} um_rating ON u.ID = um_rating.user_id AND um_rating.meta_key = '_sikshya_instructor_rating'
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
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_instructor_status'
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
                'display_name' => 'John Smith',
                'user_email' => 'john.smith@example.com',
                'user_registered' => '2024-01-15 10:30:00',
                'instructor_status' => 'active',
                'courses_count' => 5,
                'students_count' => 125,
                'avg_rating' => 4.8,
            ],
            (object) [
                'ID' => 2,
                'display_name' => 'Sarah Johnson',
                'user_email' => 'sarah.johnson@example.com',
                'user_registered' => '2024-01-14 14:20:00',
                'instructor_status' => 'active',
                'courses_count' => 3,
                'students_count' => 89,
                'avg_rating' => 4.6,
            ],
            (object) [
                'ID' => 3,
                'display_name' => 'Mike Wilson',
                'user_email' => 'mike.wilson@example.com',
                'user_registered' => '2024-01-13 09:15:00',
                'instructor_status' => 'active',
                'courses_count' => 2,
                'students_count' => 67,
                'avg_rating' => 4.9,
            ],
            (object) [
                'ID' => 4,
                'display_name' => 'Emily Davis',
                'user_email' => 'emily.davis@example.com',
                'user_registered' => '2024-01-12 16:45:00',
                'instructor_status' => 'inactive',
                'courses_count' => 0,
                'students_count' => 0,
                'avg_rating' => 0,
            ],
            (object) [
                'ID' => 5,
                'display_name' => 'David Brown',
                'user_email' => 'david.brown@example.com',
                'user_registered' => '2024-01-11 11:30:00',
                'instructor_status' => 'active',
                'courses_count' => 4,
                'students_count' => 156,
                'avg_rating' => 4.7,
            ],
            (object) [
                'ID' => 6,
                'display_name' => 'Lisa Anderson',
                'user_email' => 'lisa.anderson@example.com',
                'user_registered' => '2024-01-10 13:20:00',
                'instructor_status' => 'pending',
                'courses_count' => 0,
                'students_count' => 0,
                'avg_rating' => 0,
            ],
            (object) [
                'ID' => 7,
                'display_name' => 'Robert Taylor',
                'user_email' => 'robert.taylor@example.com',
                'user_registered' => '2024-01-09 15:10:00',
                'instructor_status' => 'active',
                'courses_count' => 1,
                'students_count' => 34,
                'avg_rating' => 4.5,
            ],
            (object) [
                'ID' => 8,
                'display_name' => 'Jennifer Lee',
                'user_email' => 'jennifer.lee@example.com',
                'user_registered' => '2024-01-08 10:45:00',
                'instructor_status' => 'active',
                'courses_count' => 3,
                'students_count' => 78,
                'avg_rating' => 4.4,
            ],
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
        
        $delete_url = wp_nonce_url(admin_url('admin.php?page=sikshya-instructors&action=delete&id=' . $item->ID), 'delete-instructor_' . $item->ID);
        
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
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this instructor?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';
        
        $output = '<div class="sikshya-instructor-title-wrapper">';
        $output .= '<div class="sikshya-instructor-thumbnail">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>';
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="sikshya-instructor-content">';
        $output .= sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($name)
        );
        $output .= sprintf(
            '<div class="sikshya-instructor-email">%s</div>',
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
        if (!empty($item->courses_count)) {
            $output = '<div class="sikshya-courses-info">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<span>%s courses</span>',
                esc_html($item->courses_count)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-courses">' . __('0 courses', 'sikshya') . '</span>';
    }

    /**
     * Column: Students
     * 
     * @param object $item
     * @return string
     */
    protected function columnStudents($item): string
    {
        if (!empty($item->students_count)) {
            $output = '<div class="sikshya-students-info">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<span>%s students</span>',
                esc_html($item->students_count)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-students">' . __('0 students', 'sikshya') . '</span>';
    }

    /**
     * Column: Rating
     * 
     * @param object $item
     * @return string
     */
    protected function columnRating($item): string
    {
        if (!empty($item->avg_rating) && $item->avg_rating > 0) {
            $rating = number_format($item->avg_rating, 1);
            $output = '<div class="sikshya-rating-wrapper">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<span class="sikshya-rating">%s/5</span>',
                esc_html($rating)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-rating">' . __('No rating', 'sikshya') . '</span>';
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

        $status_class = 'sikshya-status-' . $item->instructor_status;
        $status_text = $status_labels[$item->instructor_status] ?? $item->instructor_status;

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
            case 'students':
                return $this->columnStudents($item);
            case 'rating':
                return $this->columnRating($item);
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
        echo '<h3 class="sikshya-empty-state-title">' . esc_html__('No instructors found', 'sikshya') . '</h3>';
        echo '<p class="sikshya-empty-state-description">' . esc_html__('Get started by adding your first instructor.', 'sikshya') . '</p>';
        echo '<a href="' . admin_url('user-new.php') . '" class="sikshya-btn sikshya-btn-primary">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>';
        echo '</svg>';
        echo esc_html__('Add Instructor', 'sikshya');
        echo '</a>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
}
