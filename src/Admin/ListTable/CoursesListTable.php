<?php
/**
 * Courses List Table
 * 
 * Displays courses in a WordPress-style list table with sorting, filtering, and bulk actions
 * 
 * @package Sikshya\Admin\ListTable
 * @since 1.0.0
 */

namespace Sikshya\Admin\ListTable;

use Sikshya\Constants\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Courses List Table Class
 * 
 * Handles the display and management of courses in the admin area
 */
class CoursesListTable extends AbstractListTable
{
    /**
     * Constructor
     * 
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $config = [
            'title' => __('Courses', 'sikshya'),
            'description' => __('Manage your courses', 'sikshya'),
            'singular' => 'course',
            'plural' => 'courses',
            'per_page' => 20,
            'primary_column' => 'title',
            'search' => true,
            'empty_message' => __('No courses found. Create your first course to get started.', 'sikshya'),
            'columns' => [
                'cb' => '<input type="checkbox" />',
                'title' => __('Course Title', 'sikshya'),
                'categories' => __('Categories', 'sikshya'),
                'instructor' => __('Instructor', 'sikshya'),
                'price' => __('Price', 'sikshya'),
                'enrollments' => __('Students', 'sikshya'),
                'rating' => __('Rating', 'sikshya'),
                'status' => __('Status', 'sikshya'),
                'created' => __('Date', 'sikshya'),
            ],
            'sortable_columns' => [
                'title' => ['title', true],
                'instructor' => ['instructor', false],
                'status' => ['status', false],
                'enrollments' => ['enrollments', false],
                'price' => ['price', false],
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
                'instructor' => [
                    'type' => 'select',
                    'title' => __('Instructor', 'sikshya'),
                    'options' => $this->getInstructorsList(),
                ],
                'price_type' => [
                    'type' => 'select',
                    'title' => __('Price Type', 'sikshya'),
                    'options' => [
                        '' => __('All Types', 'sikshya'),
                        'free' => __('Free', 'sikshya'),
                        'paid' => __('Paid', 'sikshya'),
                    ],
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
    protected function get_items(): array
    {
        // For demo purposes, return dummy data
        return $this->getDummyData();
        
        // Original code (commented out for demo)
        /*
        $args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => $this->getStatusFilter(),
            'posts_per_page' => $this->get_items_per_page($this->config['per_page']),
            'paged' => $this->get_pagenum(),
            'orderby' => $this->getOrderBy(),
            'order' => $this->getOrder(),
            's' => $this->getSearchTerm(),
        ];

        // Add instructor filter
        $instructor_filter = $this->getInstructorFilter();
        if ($instructor_filter) {
            $args['author'] = $instructor_filter;
        }

        // Add price type filter
        $price_type_filter = $this->getPriceTypeFilter();
        if ($price_type_filter) {
            $args['meta_query'] = $this->getPriceTypeMetaQuery($price_type_filter);
        }

        $query = new \WP_Query($args);
        return $query->posts;
        */
    }

    /**
     * Get dummy data for demo purposes
     * 
     * @return array
     */
    protected function getDummyData(): array
    {
        $dummy_courses = [
            (object) [
                'ID' => 1,
                'post_title' => 'Complete JavaScript Masterclass 2025',
                'post_author' => 1,
                'post_status' => 'publish',
                'post_date' => '2025-01-15 10:30:00',
                'meta' => [
                    'course_price' => '89.99',
                    'course_lessons' => 45,
                    'course_quizzes' => 12,
                    'course_assignments' => 8,
                    'course_duration' => 32,
                    'course_enrollments' => 1245,
                    'course_rating' => 4.8,
                    'course_categories' => ['Development', 'JavaScript'],
                    'course_instructor' => 'John Smith'
                ]
            ],
            (object) [
                'ID' => 2,
                'post_title' => 'UI/UX Design Fundamentals',
                'post_author' => 2,
                'post_status' => 'publish',
                'post_date' => '2025-01-10 14:15:00',
                'meta' => [
                    'course_price' => '79.99',
                    'course_lessons' => 38,
                    'course_quizzes' => 10,
                    'course_assignments' => 15,
                    'course_duration' => 28,
                    'course_enrollments' => 892,
                    'course_rating' => 4.6,
                    'course_categories' => ['Design', 'UI/UX'],
                    'course_instructor' => 'Sarah Johnson'
                ]
            ],
            (object) [
                'ID' => 3,
                'post_title' => 'Python for Data Science',
                'post_author' => 3,
                'post_status' => 'publish',
                'post_date' => '2025-01-08 09:45:00',
                'meta' => [
                    'course_price' => '99.99',
                    'course_lessons' => 62,
                    'course_quizzes' => 18,
                    'course_assignments' => 12,
                    'course_duration' => 45,
                    'course_enrollments' => 2103,
                    'course_rating' => 4.9,
                    'course_categories' => ['Development', 'Data Science'],
                    'course_instructor' => 'Mike Wilson'
                ]
            ],
            (object) [
                'ID' => 4,
                'post_title' => 'Digital Marketing Strategy',
                'post_author' => 2,
                'post_status' => 'draft',
                'post_date' => '2025-01-28 13:25:00',
                'meta' => [
                    'course_price' => '0.00',
                    'course_lessons' => 25,
                    'course_quizzes' => 6,
                    'course_assignments' => 4,
                    'course_duration' => 18,
                    'course_enrollments' => 3456,
                    'course_rating' => 4.5,
                    'course_categories' => ['Marketing', 'Business'],
                    'course_instructor' => 'Sarah Johnson'
                ]
            ],
            (object) [
                'ID' => 5,
                'post_title' => 'Adobe Photoshop Complete Course',
                'post_author' => 1,
                'post_status' => 'pending',
                'post_date' => '2025-01-29 15:45:00',
                'meta' => [
                    'course_price' => '69.99',
                    'course_lessons' => 55,
                    'course_quizzes' => 14,
                    'course_assignments' => 20,
                    'course_duration' => 40,
                    'course_enrollments' => 567,
                    'course_rating' => 4.7,
                    'course_categories' => ['Design', 'Photography'],
                    'course_instructor' => 'John Smith'
                ]
            ],
            (object) [
                'ID' => 6,
                'post_title' => 'React Native Mobile Development',
                'post_author' => 3,
                'post_status' => 'publish',
                'post_date' => '2025-01-12 11:20:00',
                'meta' => [
                    'course_price' => '119.99',
                    'course_lessons' => 48,
                    'course_quizzes' => 15,
                    'course_assignments' => 10,
                    'course_duration' => 35,
                    'course_enrollments' => 1567,
                    'course_rating' => 4.7,
                    'course_categories' => ['Development', 'Mobile'],
                    'course_instructor' => 'Mike Wilson'
                ]
            ],
            (object) [
                'ID' => 7,
                'post_title' => 'Advanced WordPress Development',
                'post_author' => 1,
                'post_status' => 'publish',
                'post_date' => '2025-01-18 16:45:00',
                'meta' => [
                    'course_price' => '89.99',
                    'course_lessons' => 42,
                    'course_quizzes' => 12,
                    'course_assignments' => 8,
                    'course_duration' => 30,
                    'course_enrollments' => 987,
                    'course_rating' => 4.6,
                    'course_categories' => ['Development', 'WordPress'],
                    'course_instructor' => 'John Smith'
                ]
            ],
            (object) [
                'ID' => 8,
                'post_title' => 'Graphic Design Masterclass',
                'post_author' => 2,
                'post_status' => 'publish',
                'post_date' => '2025-01-20 09:30:00',
                'meta' => [
                    'course_price' => '94.99',
                    'course_lessons' => 52,
                    'course_quizzes' => 16,
                    'course_assignments' => 18,
                    'course_duration' => 38,
                    'course_enrollments' => 1234,
                    'course_rating' => 4.8,
                    'course_categories' => ['Design', 'Graphic Design'],
                    'course_instructor' => 'Sarah Johnson'
                ]
            ],
            (object) [
                'ID' => 9,
                'post_title' => 'Machine Learning Fundamentals',
                'post_author' => 3,
                'post_status' => 'draft',
                'post_date' => '2025-01-30 14:15:00',
                'meta' => [
                    'course_price' => '0.00',
                    'course_lessons' => 35,
                    'course_quizzes' => 8,
                    'course_assignments' => 6,
                    'course_duration' => 25,
                    'course_enrollments' => 0,
                    'course_rating' => 0,
                    'course_categories' => ['Development', 'AI/ML'],
                    'course_instructor' => 'Mike Wilson'
                ]
            ],
            (object) [
                'ID' => 10,
                'post_title' => 'Content Marketing Strategy',
                'post_author' => 2,
                'post_status' => 'pending',
                'post_date' => '2025-01-31 10:00:00',
                'meta' => [
                    'course_price' => '74.99',
                    'course_lessons' => 30,
                    'course_quizzes' => 7,
                    'course_assignments' => 5,
                    'course_duration' => 22,
                    'course_enrollments' => 0,
                    'course_rating' => 0,
                    'course_categories' => ['Marketing', 'Content'],
                    'course_instructor' => 'Sarah Johnson'
                ]
            ]
        ];

        return $dummy_courses;
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
        $args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => $this->getStatusFilter(),
            'posts_per_page' => -1,
            's' => $this->getSearchTerm(),
        ];

        // Add instructor filter
        $instructor_filter = $this->getInstructorFilter();
        if ($instructor_filter) {
            $args['author'] = $instructor_filter;
        }

        // Add price type filter
        $price_type_filter = $this->getPriceTypeFilter();
        if ($price_type_filter) {
            $args['meta_query'] = $this->getPriceTypeMetaQuery($price_type_filter);
        }

        $query = new \WP_Query($args);
        return $query->found_posts;
        */
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
                return $this->column_title($item);
            case 'content':
                return $this->column_content($item);
            case 'categories':
                return $this->column_categories($item);
            case 'instructor':
                return $this->column_instructor($item);
            case 'status':
                return $this->column_status($item);
            case 'enrollments':
                return $this->column_enrollments($item);
            case 'price':
                return $this->column_price($item);
            case 'rating':
                return $this->column_rating($item);
            case 'created':
                return $this->column_created($item);
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
     * Column title
     * 
     * @param object $item
     * @return string
     */
    public function column_title($item): string
    {
        $title = $item->post_title;
        $edit_url = admin_url('admin.php?page=sikshya-add-course&course_id=' . $item->ID);
        
        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'sikshya')),
            'view' => sprintf('<a href="#" onclick="return false;">%s</a>', __('View', 'sikshya')),
        ];

        $row_actions = $this->row_actions($actions);
        
        $output = sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($title)
        );
        
        $output .= $row_actions;
        
        return $output;
    }

    /**
     * Column instructor
     * 
     * @param object $item
     * @return string
     */
    public function column_instructor($item): string
    {
        $instructor_name = $item->meta['course_instructor'] ?? __('Unknown', 'sikshya');
        
        return esc_html($instructor_name);
    }

    /**
     * Column status
     * 
     * @param object $item
     * @return string
     */
    public function column_status($item): string
    {
        $status = $item->post_status;
        $status_labels = [
            'publish' => __('Published', 'sikshya'),
            'draft' => __('Draft', 'sikshya'),
            'private' => __('Private', 'sikshya'),
            'pending' => __('Pending Review', 'sikshya'),
        ];

        $status_text = $status_labels[$status] ?? $status;

        return esc_html($status_text);
    }

    /**
     * Column enrollments
     * 
     * @param object $item
     * @return string
     */
    public function column_enrollments($item): string
    {
        $enrollments = $item->meta['course_enrollments'] ?? 0;
        
        return number_format($enrollments);
    }

    /**
     * Column price
     * 
     * @param object $item
     * @return string
     */
    public function column_categories($item): string
    {
        $categories = $item->meta['course_categories'] ?? [];
        
        if (empty($categories)) {
            return '—';
        }
        
        return esc_html(implode(', ', $categories));
    }

    public function column_rating($item): string
    {
        $rating = $item->meta['course_rating'] ?? 0;
        
        if ($rating == 0) {
            return '—';
        }
        
        return number_format($rating, 1);
    }

    public function column_price($item): string
    {
        $price = $item->meta['course_price'] ?? '0.00';
        
        if ($price === '0.00' || empty($price)) {
            return 'Free';
        }

        return '$' . number_format($price, 2);
    }

    /**
     * Column lessons
     * 
     * @param object $item
     * @return string
     */
    public function column_lessons($item): string
    {
        $lessons_count = $item->meta['course_lessons'] ?? 0;
        
        return sprintf(
            '<a href="#">%d</a>',
            $lessons_count
        );
    }

    /**
     * Column created
     * 
     * @param object $item
     * @return string
     */
    public function column_created($item): string
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
     * Delete a single course
     * 
     * @param int $id
     * @return bool
     */
    protected function delete_item($id): bool
    {
        $course = get_post($id);
        
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            return false;
        }

        // Delete course meta
        delete_post_meta($id, '_sikshya_course_price');
        delete_post_meta($id, '_sikshya_course_price_type');
        delete_post_meta($id, '_sikshya_course_duration');
        delete_post_meta($id, '_sikshya_course_difficulty');
        
        // Delete the course
        $result = wp_delete_post($id, true);
        
        return $result !== false;
    }

    /**
     * Update course status
     * 
     * @param int $id
     * @param string $status
     * @return bool
     */
    protected function update_item_status($id, $status): bool
    {
        $course = get_post($id);
        
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            return false;
        }

        $result = wp_update_post([
            'ID' => $id,
            'post_status' => $status,
        ]);
        
        return !is_wp_error($result);
    }

    /**
     * Get instructors list for filter
     * 
     * @return array
     */
    private function getInstructorsList(): array
    {
        $instructors = get_users([
            'role__in' => ['administrator', 'instructor'],
            'orderby' => 'display_name',
        ]);
        
        $list = ['' => __('All Instructors', 'sikshya')];
        
        foreach ($instructors as $instructor) {
            $list[$instructor->ID] = $instructor->display_name;
        }
        
        // If no instructors found, add dummy instructors for demo
        if (count($instructors) === 0) {
            $list[1] = 'John Smith';
            $list[2] = 'Sarah Johnson';
            $list[3] = 'Mike Wilson';
        }
        
        return $list;
    }

    /**
     * Get status filter
     * 
     * @return array|string
     */
    private function getStatusFilter()
    {
        $status = $_GET['status'] ?? '';
        
        if (empty($status)) {
            return ['publish', 'draft', 'private', 'pending'];
        }
        
        return $status;
    }

    /**
     * Get instructor filter
     * 
     * @return int|false
     */
    private function getInstructorFilter()
    {
        $instructor = $_GET['instructor'] ?? '';
        
        if (empty($instructor)) {
            return false;
        }
        
        return intval($instructor);
    }

    /**
     * Get price type filter
     * 
     * @return string|false
     */
    private function getPriceTypeFilter()
    {
        $price_type = $_GET['price_type'] ?? '';
        
        if (empty($price_type)) {
            return false;
        }
        
        return $price_type;
    }

    /**
     * Get price type meta query
     * 
     * @param string $price_type
     * @return array
     */
    private function getPriceTypeMetaQuery($price_type): array
    {
        if ($price_type === 'free') {
            return [
                'relation' => 'OR',
                [
                    'key' => '_sikshya_course_price_type',
                    'value' => 'free',
                    'compare' => '=',
                ],
                [
                    'key' => '_sikshya_course_price',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => '_sikshya_course_price',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        } else {
            return [
                [
                    'key' => '_sikshya_course_price_type',
                    'value' => 'paid',
                    'compare' => '=',
                ],
                [
                    'key' => '_sikshya_course_price',
                    'value' => '',
                    'compare' => '>',
                ],
            ];
        }
    }

    /**
     * Get search term
     * 
     * @return string
     */
    private function getSearchTerm(): string
    {
        return sanitize_text_field($_GET['s'] ?? '');
    }

    /**
     * Get order by
     * 
     * @return string
     */
    private function getOrderBy(): string
    {
        $orderby = $_GET['orderby'] ?? 'date';
        
        $allowed_orderby = ['title', 'instructor', 'status', 'enrollments', 'price', 'created', 'date'];
        
        return in_array($orderby, $allowed_orderby) ? $orderby : 'date';
    }

    /**
     * Get order
     * 
     * @return string
     */
    private function getOrder(): string
    {
        $order = $_GET['order'] ?? 'DESC';
        
        return in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';
    }

    /**
     * Get course enrollments count
     * 
     * @param int $course_id
     * @return int
     */
    private function getCourseEnrollments($course_id): int
    {
        // This would typically query a custom table or post meta
        // For now, return a placeholder
        return get_post_meta($course_id, '_sikshya_enrollments_count', true) ?: 0;
    }

    /**
     * Get course lessons count
     * 
     * @param int $course_id
     * @return int
     */
    private function getCourseLessonsCount($course_id): int
    {
        $args = [
            'post_type' => PostTypes::LESSON,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sikshya_lesson_course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ],
            ],
        ];
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Extra table navigation
     * 
     * @param string $which
     * @return void
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        
        echo '<div class="alignleft actions">';
        echo '<a href="' . admin_url('admin.php?page=sikshya-add-course') . '" class="button button-primary">';
        echo '<span class="dashicons dashicons-plus-alt2"></span> ' . __('Add New Course', 'sikshya');
        echo '</a>';
        echo '</div>';
    }
}
