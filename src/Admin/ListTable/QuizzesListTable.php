<?php

namespace Sikshya\Admin\ListTable;

use Sikshya\Constants\PostTypes;

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
            $where_clauses[] = 'p.post_status = %s';
            $args[] = sanitize_text_field($_GET['status']);
        }
        
        if (!empty($_GET['course']) && $_GET['course'] !== 'all') {
            $where_clauses[] = 'pm_course.meta_value = %d';
            $args[] = intval($_GET['course']);
        }
        
        if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
            $where_clauses[] = 'pm_type.meta_value = %s';
            $args[] = sanitize_text_field($_GET['type']);
        }
        
        if (!empty($_GET['instructor']) && $_GET['instructor'] !== 'all') {
            $where_clauses[] = 'p.post_author = %d';
            $args[] = intval($_GET['instructor']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "
            SELECT 
                p.*,
                pm_course.meta_value as course_id,
                pm_type.meta_value as quiz_type,
                pm_questions.meta_value as questions_count,
                pm_duration.meta_value as duration,
                u.display_name as instructor_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_course ON p.ID = pm_course.post_id AND pm_course.meta_key = '_sikshya_course_id'
            LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_sikshya_quiz_type'
            LEFT JOIN {$wpdb->postmeta} pm_questions ON p.ID = pm_questions.post_id AND pm_questions.meta_key = '_sikshya_questions_count'
            LEFT JOIN {$wpdb->postmeta} pm_duration ON p.ID = pm_duration.post_id AND pm_duration.meta_key = '_sikshya_duration'
            LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
            WHERE p.post_type = %s
            {$where_sql}
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";
        
        $args = array_merge([PostTypes::QUIZ], $args, [$per_page, $offset]);
        
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
            $where_clauses[] = 'p.post_status = %s';
            $args[] = sanitize_text_field($_GET['status']);
        }
        
        if (!empty($_GET['course']) && $_GET['course'] !== 'all') {
            $where_clauses[] = 'pm_course.meta_value = %d';
            $args[] = intval($_GET['course']);
        }
        
        if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
            $where_clauses[] = 'pm_type.meta_value = %s';
            $args[] = sanitize_text_field($_GET['type']);
        }
        
        if (!empty($_GET['instructor']) && $_GET['instructor'] !== 'all') {
            $where_clauses[] = 'p.post_author = %d';
            $args[] = intval($_GET['instructor']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_course ON p.ID = pm_course.post_id AND pm_course.meta_key = '_sikshya_course_id'
            LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_sikshya_quiz_type'
            WHERE p.post_type = %s
            {$where_sql}
        ";
        
        $args = array_merge([PostTypes::QUIZ], $args);
        
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
                'post_title' => 'JavaScript Fundamentals Quiz',
                'post_status' => 'publish',
                'post_date' => '2024-01-15 10:30:00',
                'post_author' => 1,
                'course_id' => 1,
                'course_title' => 'Web Development Basics',
                'quiz_type' => 'multiple_choice',
                'questions_count' => 15,
                'duration' => 30,
                'instructor_name' => 'John Smith',
            ],
            (object) [
                'ID' => 2,
                'post_title' => 'CSS Layout Techniques',
                'post_status' => 'publish',
                'post_date' => '2024-01-14 14:20:00',
                'post_author' => 2,
                'course_id' => 2,
                'course_title' => 'Advanced CSS',
                'quiz_type' => 'true_false',
                'questions_count' => 10,
                'duration' => 20,
                'instructor_name' => 'Sarah Johnson',
            ],
            (object) [
                'ID' => 3,
                'post_title' => 'React Hooks Assessment',
                'post_status' => 'draft',
                'post_date' => '2024-01-13 09:15:00',
                'post_author' => 1,
                'course_id' => 3,
                'course_title' => 'React Development',
                'quiz_type' => 'multiple_choice',
                'questions_count' => 20,
                'duration' => 45,
                'instructor_name' => 'John Smith',
            ],
            (object) [
                'ID' => 4,
                'post_title' => 'Database Design Principles',
                'post_status' => 'publish',
                'post_date' => '2024-01-12 16:45:00',
                'post_author' => 3,
                'course_id' => 4,
                'course_title' => 'Database Management',
                'quiz_type' => 'essay',
                'questions_count' => 5,
                'duration' => 60,
                'instructor_name' => 'Mike Wilson',
            ],
            (object) [
                'ID' => 5,
                'post_title' => 'Git Version Control',
                'post_status' => 'pending',
                'post_date' => '2024-01-11 11:30:00',
                'post_author' => 2,
                'course_id' => 5,
                'course_title' => 'Version Control Systems',
                'quiz_type' => 'fill_blank',
                'questions_count' => 12,
                'duration' => 25,
                'instructor_name' => 'Sarah Johnson',
            ],
            (object) [
                'ID' => 6,
                'post_title' => 'Python Data Structures',
                'post_status' => 'publish',
                'post_date' => '2024-01-10 13:20:00',
                'post_author' => 4,
                'course_id' => 6,
                'course_title' => 'Python Programming',
                'quiz_type' => 'multiple_choice',
                'questions_count' => 18,
                'duration' => 35,
                'instructor_name' => 'Emily Davis',
            ],
            (object) [
                'ID' => 7,
                'post_title' => 'API Design Best Practices',
                'post_status' => 'draft',
                'post_date' => '2024-01-09 15:10:00',
                'post_author' => 1,
                'course_id' => 7,
                'course_title' => 'API Development',
                'quiz_type' => 'essay',
                'questions_count' => 8,
                'duration' => 50,
                'instructor_name' => 'John Smith',
            ],
            (object) [
                'ID' => 8,
                'post_title' => 'Mobile App Testing',
                'post_status' => 'publish',
                'post_date' => '2024-01-08 10:45:00',
                'post_author' => 5,
                'course_id' => 8,
                'course_title' => 'Mobile Development',
                'quiz_type' => 'true_false',
                'questions_count' => 14,
                'duration' => 28,
                'instructor_name' => 'David Brown',
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
     * Get instructors list for filter
     * 
     * @return array
     */
    private function getInstructorsList(): array
    {
        return [
            1 => 'John Smith',
            2 => 'Sarah Johnson',
            3 => 'Mike Wilson',
            4 => 'Emily Davis',
            5 => 'David Brown',
        ];
    }

    /**
     * Get quiz type name
     * 
     * @param string $type
     * @return string
     */
    private function getQuizTypeName(string $type): string
    {
        $types = [
            'multiple_choice' => __('Multiple Choice', 'sikshya'),
            'true_false' => __('True/False', 'sikshya'),
            'fill_blank' => __('Fill in the Blank', 'sikshya'),
            'essay' => __('Essay', 'sikshya'),
        ];

        return $types[$type] ?? $type;
    }

    /**
     * Default column handler
     * 
     * @param object $item
     * @param string $column_key
     * @return string
     */
    protected function column_default($item, $column_key): string
    {
        switch ($column_key) {
            case 'questions':
                return '<span class="sikshya-questions-count">' . esc_html($item->questions_count) . '</span>';
            case 'duration':
                return '<span class="sikshya-duration">' . esc_html($item->duration) . ' min</span>';
            case 'status':
                $status_class = 'sikshya-status-' . $item->post_status;
                return '<span class="sikshya-status-badge ' . esc_attr($status_class) . '">' . esc_html(ucfirst($item->post_status)) . '</span>';
            case 'created':
                return '<span class="sikshya-date">' . esc_html(date('M j, Y', strtotime($item->post_date))) . '</span>';
            default:
                return '';
        }
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
     * Column: Title
     * 
     * @param object $item
     * @return string
     */
    protected function columnTitle($item): string
    {
        $title = $item->post_title;
        $edit_url = admin_url('admin.php?page=sikshya-add-course&course_id=' . $item->course_id . '&tab=curriculum');
        
        $delete_url = wp_nonce_url(admin_url('admin.php?page=sikshya-quizzes&action=delete&id=' . $item->ID), 'delete-quiz_' . $item->ID);
        
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
        
        $output = '<div class="sikshya-quiz-title-wrapper">';
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
            '<div class="sikshya-questions-count">%s questions</div>',
            esc_html($item->questions_count)
        );
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= $row_actions;
        
        return $output;
    }

    /**
     * Column: Course
     * 
     * @param object $item
     * @return string
     */
    protected function columnCourse($item): string
    {
        if (!empty($item->course_title)) {
            $output = '<div class="sikshya-course-info">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
            $output .= '</svg>';
            $output .= sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=sikshya-add-course&course_id=' . $item->course_id),
                esc_html($item->course_title)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-course">' . __('No Course', 'sikshya') . '</span>';
    }

    /**
     * Column: Type
     * 
     * @param object $item
     * @return string
     */
    protected function columnType($item): string
    {
        $type_name = $this->getQuizTypeName($item->quiz_type);
        $type_icons = [
            'multiple_choice' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
            'true_false' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'fill_blank' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
            'essay' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'
        ];

        $icon_path = $type_icons[$item->quiz_type] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
        
        $output = '<div class="sikshya-quiz-type">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= $icon_path;
        $output .= '</svg>';
        $output .= sprintf('<span>%s</span>', $type_name);
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Column: Questions
     * 
     * @param object $item
     * @return string
     */
    protected function columnQuestions($item): string
    {
        if (!empty($item->questions_count)) {
            return sprintf('<span class="sikshya-questions">%s</span>', esc_html($item->questions_count));
        }
        return '<span class="sikshya-no-questions">' . __('0', 'sikshya') . '</span>';
    }

    /**
     * Column: Duration
     * 
     * @param object $item
     * @return string
     */
    protected function columnDuration($item): string
    {
        if (!empty($item->duration)) {
            $output = '<div class="sikshya-duration-wrapper">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
            $output .= '</svg>';
            $output .= sprintf('<span class="sikshya-duration">%s min</span>', esc_html($item->duration));
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-duration">' . __('Not set', 'sikshya') . '</span>';
    }

    /**
     * Column: Instructor
     * 
     * @param object $item
     * @return string
     */
    protected function columnInstructor($item): string
    {
        if (!empty($item->instructor_name)) {
            $output = '<div class="sikshya-instructors">';
            $output .= sprintf(
                '<div class="sikshya-instructor">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>%s</span>
                </div>',
                esc_html($item->instructor_name)
            );
            $output .= '</div>';
            return $output;
        }
        return '<span class="sikshya-no-instructor">' . __('Unknown', 'sikshya') . '</span>';
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
            'publish' => __('Published', 'sikshya'),
            'draft' => __('Draft', 'sikshya'),
            'private' => __('Private', 'sikshya'),
            'pending' => __('Pending Review', 'sikshya'),
        ];

        $status_class = 'sikshya-status-' . $item->post_status;
        $status_text = $status_labels[$item->post_status] ?? $item->post_status;

        return sprintf(
            '<span class="sikshya-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Column: Created Date
     * 
     * @param object $item
     * @return string
     */
    protected function columnCreated($item): string
    {
        $date = new \DateTime($item->post_date);
        $date_format = 'M j, Y';
        
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr($date->format('F j, Y g:i A')),
            esc_html($date->format($date_format))
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=sikshya-add-course')); ?>" class="button button-primary">
                    <?php esc_html_e('Create Quiz', 'sikshya'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
