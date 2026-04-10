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
     * Get items for the table
     *
     * @return array
     */
    protected function get_items(): array
    {
        // For demo purposes, return dummy data
        return $this->getDummyData();

        // Original code (commented out for demo)
        /*
        global $wpdb;

        $per_page = $this->get_items_per_page();
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Build query
        $where = ['1=1'];
        $join = '';
        $args = [];

        // Search
        $search = $this->getSearchTerm();
        if (!empty($search)) {
            $where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // Status filter
        $status_filter = $this->getFilterValue('status');
        if (!empty($status_filter)) {
            $where[] = 'p.post_status = %s';
            $args[] = $status_filter;
        } else {
            $where[] = 'p.post_status IN ("publish", "draft", "private", "pending")';
        }

        // Course filter
        $course_filter = $this->getFilterValue('course');
        if (!empty($course_filter)) {
            $where[] = 'c.ID = %d';
            $args[] = intval($course_filter);
        }

        // Type filter
        $type_filter = $this->getFilterValue('type');
        if (!empty($type_filter)) {
            $where[] = 'p.post_type = %s';
            $args[] = 'sikshya_' . $type_filter;
        } else {
            $where[] = 'p.post_type IN ("sikshya_text", "sikshya_video", "sikshya_audio", "sikshya_quiz", "sikshya_assignment")';
        }

        // Instructor filter
        $instructor_filter = $this->getFilterValue('instructor');
        if (!empty($instructor_filter)) {
            $where[] = 'c.post_author = %d';
            $args[] = intval($instructor_filter);
        }

        // Build the main query
        $query = "
            SELECT
                p.ID,
                p.post_title,
                p.post_content,
                p.post_status,
                p.post_type,
                p.post_author,
                p.post_date,
                p.post_modified,
                c.post_title as course_title,
                c.ID as course_id,
                u.display_name as instructor_name,
                pm_duration.meta_value as duration,
                pm_order.meta_value as lesson_order
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->posts} c ON c.ID = (
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = '_sikshya_course_id'
                LIMIT 1
            )
            LEFT JOIN {$wpdb->posts} c ON c.ID = (
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = '_sikshya_course_id'
                LIMIT 1
            )
            LEFT JOIN {$wpdb->users} u ON u.ID = c.post_author
            LEFT JOIN {$wpdb->postmeta} pm_duration ON pm_duration.post_id = p.ID AND pm_duration.meta_key = '_sikshya_duration'
            LEFT JOIN {$wpdb->postmeta} pm_order ON pm_order.post_id = p.ID AND pm_order.meta_key = '_sikshya_order'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";

        $args[] = $per_page;
        $args[] = $offset;

        // Only use prepare if we have arguments, otherwise use the query directly
        if (!empty($args)) {
            $items = $wpdb->get_results($wpdb->prepare($query, $args));
        } else {
            $items = $wpdb->get_results($query);
        }

        return $items;
        */
    }

    /**
     * Get total number of items
     *
     * @return int
     */
    protected function get_total_items(): int
    {
        // For demo purposes, return count of dummy data
        return count($this->getDummyData());

        // Original code (commented out for demo)
        /*
        global $wpdb;

        // Build query for count
        $where = ['1=1'];
        $args = [];

        // Status filter
        $status_filter = $this->getFilterValue('status');
        if (!empty($status_filter)) {
            $where[] = 'p.post_status = %s';
            $args[] = $status_filter;
        } else {
            $where[] = 'p.post_status IN ("publish", "draft", "private", "pending")';
        }

        // Course filter
        $course_filter = $this->getFilterValue('course');
        if (!empty($course_filter)) {
            $where[] = 'c.ID = %d';
            $args[] = intval($course_filter);
        }

        // Type filter
        $type_filter = $this->getFilterValue('type');
        if (!empty($type_filter)) {
            $where[] = 'p.post_type = %s';
            $args[] = 'sikshya_' . $type_filter;
        } else {
            $where[] = 'p.post_type IN ("sikshya_text", "sikshya_video", "sikshya_audio", "sikshya_quiz", "sikshya_assignment")';
        }

        // Instructor filter
        $instructor_filter = $this->getFilterValue('instructor');
        if (!empty($instructor_filter)) {
            $where[] = 'c.post_author = %d';
            $args[] = intval($instructor_filter);
        }

        // Search
        $search = $this->getSearchTerm();
        if (!empty($search)) {
            $where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $count_query = "
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->posts} c ON c.ID = (
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = '_sikshya_course_id'
                LIMIT 1
            )
            WHERE " . implode(' AND ', $where);

        // Only use prepare if we have arguments, otherwise use the query directly
        if (!empty($args)) {
            return (int) $wpdb->get_var($wpdb->prepare($count_query, $args));
        } else {
            return (int) $wpdb->get_var($count_query);
        }
        */
    }

    /**
     * Get dummy data for demo purposes
     *
     * @return array
     */
    protected function getDummyData(): array
    {
        $dummy_lessons = [
            (object) [
                'ID' => 1,
                'post_title' => 'Introduction to JavaScript Basics',
                'post_content' => 'Learn the fundamentals of JavaScript programming language.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_text',
                'post_author' => 1,
                'post_date' => '2025-01-15 10:30:00',
                'course_title' => 'Complete JavaScript Masterclass 2025',
                'course_id' => 1,
                'instructor_name' => 'John Smith',
                'duration' => '45',
                'lesson_order' => 1
            ],
            (object) [
                'ID' => 2,
                'post_title' => 'Variables and Data Types',
                'post_content' => 'Understanding variables, strings, numbers, and booleans in JavaScript.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_text',
                'post_author' => 1,
                'post_date' => '2025-01-15 11:00:00',
                'course_title' => 'Complete JavaScript Masterclass 2025',
                'course_id' => 1,
                'instructor_name' => 'John Smith',
                'duration' => '30',
                'lesson_order' => 2
            ],
            (object) [
                'ID' => 3,
                'post_title' => 'JavaScript Functions Deep Dive',
                'post_content' => 'Master function declarations, expressions, and arrow functions.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_video',
                'post_author' => 1,
                'post_date' => '2025-01-15 12:00:00',
                'course_title' => 'Complete JavaScript Masterclass 2025',
                'course_id' => 1,
                'instructor_name' => 'John Smith',
                'duration' => '60',
                'lesson_order' => 3
            ],
            (object) [
                'ID' => 4,
                'post_title' => 'UI Design Principles',
                'post_content' => 'Core principles of user interface design and user experience.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_text',
                'post_author' => 2,
                'post_date' => '2025-01-10 14:15:00',
                'course_title' => 'UI/UX Design Fundamentals',
                'course_id' => 2,
                'instructor_name' => 'Sarah Johnson',
                'duration' => '40',
                'lesson_order' => 1
            ],
            (object) [
                'ID' => 5,
                'post_title' => 'Color Theory in Design',
                'post_content' => 'Understanding color psychology and color schemes in design.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_video',
                'post_author' => 2,
                'post_date' => '2025-01-10 15:00:00',
                'course_title' => 'UI/UX Design Fundamentals',
                'course_id' => 2,
                'instructor_name' => 'Sarah Johnson',
                'duration' => '35',
                'lesson_order' => 2
            ],
            (object) [
                'ID' => 6,
                'post_title' => 'Python Basics Quiz',
                'post_content' => 'Test your knowledge of Python fundamentals with this comprehensive quiz.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_quiz',
                'post_author' => 3,
                'post_date' => '2025-01-08 09:45:00',
                'course_title' => 'Python for Data Science',
                'course_id' => 3,
                'instructor_name' => 'Mike Wilson',
                'duration' => '20',
                'lesson_order' => 5
            ],
            (object) [
                'ID' => 7,
                'post_title' => 'Data Visualization Assignment',
                'post_content' => 'Create compelling data visualizations using Python libraries.',
                'post_status' => 'draft',
                'post_type' => 'sikshya_assignment',
                'post_author' => 3,
                'post_date' => '2025-01-08 10:30:00',
                'course_title' => 'Python for Data Science',
                'course_id' => 3,
                'instructor_name' => 'Mike Wilson',
                'duration' => '120',
                'lesson_order' => 8
            ],
            (object) [
                'ID' => 8,
                'post_title' => 'Marketing Strategy Overview',
                'post_content' => 'Introduction to digital marketing strategies and best practices.',
                'post_status' => 'draft',
                'post_type' => 'sikshya_text',
                'post_author' => 2,
                'post_date' => '2025-01-28 13:25:00',
                'course_title' => 'Digital Marketing Strategy',
                'course_id' => 4,
                'instructor_name' => 'Sarah Johnson',
                'duration' => '25',
                'lesson_order' => 1
            ],
            (object) [
                'ID' => 9,
                'post_title' => 'Photoshop Tools Tutorial',
                'post_content' => 'Learn essential Photoshop tools and techniques for photo editing.',
                'post_status' => 'pending',
                'post_type' => 'sikshya_video',
                'post_author' => 1,
                'post_date' => '2025-01-29 15:45:00',
                'course_title' => 'Adobe Photoshop Complete Course',
                'course_id' => 5,
                'instructor_name' => 'John Smith',
                'duration' => '55',
                'lesson_order' => 2
            ],
            (object) [
                'ID' => 10,
                'post_title' => 'React Native Setup Guide',
                'post_content' => 'Step-by-step guide to setting up your React Native development environment.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_text',
                'post_author' => 3,
                'post_date' => '2025-01-12 11:20:00',
                'course_title' => 'React Native Mobile Development',
                'course_id' => 6,
                'instructor_name' => 'Mike Wilson',
                'duration' => '30',
                'lesson_order' => 1
            ],
            (object) [
                'ID' => 11,
                'post_title' => 'WordPress Plugin Development',
                'post_content' => 'Learn how to create custom WordPress plugins from scratch.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_video',
                'post_author' => 1,
                'post_date' => '2025-01-18 16:45:00',
                'course_title' => 'Advanced WordPress Development',
                'course_id' => 7,
                'instructor_name' => 'John Smith',
                'duration' => '75',
                'lesson_order' => 3
            ],
            (object) [
                'ID' => 12,
                'post_title' => 'Design Portfolio Review',
                'post_content' => 'Submit your design portfolio for professional review and feedback.',
                'post_status' => 'publish',
                'post_type' => 'sikshya_assignment',
                'post_author' => 2,
                'post_date' => '2025-01-20 09:30:00',
                'course_title' => 'Graphic Design Masterclass',
                'course_id' => 8,
                'instructor_name' => 'Sarah Johnson',
                'duration' => '90',
                'lesson_order' => 10
            ]
        ];

        return $dummy_lessons;
    }

    /**
     * Get courses list for filter
     *
     * @return array
     */
    private function getCoursesList(): array
    {
        $options = ['' => __('All Courses', 'sikshya')];

        $courses = get_posts([
            'post_type' => PostTypes::COURSE,
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        foreach ($courses as $course_id) {
            $options[$course_id] = get_the_title($course_id);
        }

        return $options;
    }

    /**
     * Get instructors list for filter
     *
     * @return array
     */
    private function getInstructorsList(): array
    {
        $options = ['' => __('All Instructors', 'sikshya')];

        $users = get_users([
            'role__in' => ['administrator', 'sikshya_instructor'],
            'orderby' => 'display_name',
            'fields' => ['ID', 'display_name'],
        ]);

        foreach ($users as $user) {
            $options[$user->ID] = $user->display_name;
        }

        return $options;
    }

    /**
     * Get lesson type display name
     *
     * @param string $post_type
     * @return string
     */
    private function getLessonTypeName(string $post_type): string
    {
        $type_map = [
            'sikshya_text' => __('Text Lesson', 'sikshya'),
            'sikshya_video' => __('Video Lesson', 'sikshya'),
            'sikshya_audio' => __('Audio Lesson', 'sikshya'),
            'sikshya_quiz' => __('Quiz', 'sikshya'),
            'sikshya_assignment' => __('Assignment', 'sikshya'),
        ];

        return $type_map[$post_type] ?? ucfirst(str_replace('sikshya_', '', $post_type));
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
        $edit_url = ReactAdminConfig::reactAppUrl('add-course', [
            'course_id' => (string) $item->course_id,
            'tab' => 'curriculum',
        ]);

        $delete_url = wp_nonce_url(
            ReactAdminConfig::reactAppUrl('lessons', ['action' => 'delete', 'id' => (string) $item->ID]),
            'delete-lesson_' . $item->ID
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
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this lesson?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';

        // Get lesson type icon
        $type_icons = [
            'sikshya_text' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
            'sikshya_video' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>',
            'sikshya_audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>',
            'sikshya_quiz' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'sikshya_assignment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>'
        ];

        $icon_path = $type_icons[$item->post_type] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';

        $output = '<div class="sikshya-lesson-title-wrapper">';
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
        $output .= sprintf(
            '<div class="sikshya-lesson-duration">%s min</div>',
            esc_html($item->duration)
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
                esc_url(ReactAdminConfig::reactAppUrl('add-course', ['course_id' => (string) $item->course_id])),
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
        $type_name = $this->getLessonTypeName($item->post_type);
        $type_icons = [
            'sikshya_text' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
            'sikshya_video' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>',
            'sikshya_audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>',
            'sikshya_quiz' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'sikshya_assignment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>'
        ];

        $icon_path = $type_icons[$item->post_type] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';

        $output = '<div class="sikshya-lesson-type">';
        $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= $icon_path;
        $output .= '</svg>';
        $output .= sprintf('<span>%s</span>', $type_name);
        $output .= '</div>';

        return $output;
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
