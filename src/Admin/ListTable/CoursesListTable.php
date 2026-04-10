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

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

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
                'rating' => __('Rating', 'sikshya'),
                'status' => __('Status', 'sikshya'),
                'created' => __('Date', 'sikshya'),
            ],
            'sortable_columns' => [
                'title' => ['title', true],
                'instructor' => ['instructor', false],
                'status' => ['status', false],
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
     * Post status counts for subsubsub tabs.
     *
     * @return array<string, int>
     */
    protected function get_status_counts(): array
    {
        $counts = wp_count_posts(PostTypes::COURSE);
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
                    'course_original_price' => '129.99',
                    'course_lessons' => 45,
                    'course_quizzes' => 12,
                    'course_assignments' => 8,
                    'course_duration' => 32,
                    'course_enrollments' => 1245,
                    'course_rating' => 4.8,
                    'course_categories' => ['Development', 'JavaScript'],
                    'course_instructor' => 'John Smith, Mike Wilson'
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
                    'course_original_price' => '99.99',
                    'course_lessons' => 38,
                    'course_quizzes' => 10,
                    'course_assignments' => 15,
                    'course_duration' => 28,
                    'course_enrollments' => 892,
                    'course_rating' => 4.6,
                    'course_categories' => ['Design', 'UI/UX'],
                    'course_instructor' => 'Sarah Johnson, Emma Wilson'
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
        $edit_url = ReactAdminConfig::reactAppUrl('add-course', ['course_id' => (string) $item->ID]);

        $delete_url = wp_nonce_url(
            ReactAdminConfig::reactAppUrl('courses', ['action' => 'delete', 'id' => (string) $item->ID]),
            'delete-course_' . $item->ID
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
        $row_actions .= '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this course?', 'sikshya')) . '\');">';
        $row_actions .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $row_actions .= '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        $row_actions .= '</svg>';
        $row_actions .= esc_html__('Delete', 'sikshya') . '</a></span>';
        $row_actions .= '</div>';

        // Get student count
        $student_count = get_post_meta($item->ID, 'course_enrollments', true) ?: 0;

        $output = '<div class="sikshya-course-title-wrapper">';
        $output .= '<div class="sikshya-course-thumbnail">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>';
        $output .= '</div>';
        $output .= '<div class="sikshya-course-content">';
        $output .= sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url($edit_url),
            esc_html($title)
        );
        $output .= sprintf(
            '<div class="sikshya-student-count">%s students</div>',
            esc_html($student_count)
        );
        $output .= '</div>';
        $output .= '</div>';

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
        // Get the author of the course
        $author_id = $item->post_author;
        $author = get_userdata($author_id);

        if (!$author) {
            return '<span class="sikshya-no-instructor">—</span>';
        }

        $output = '<div class="sikshya-instructors">';
        $output .= sprintf(
            '<div class="sikshya-instructor">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>%s</span>
            </div>',
            esc_html($author->display_name)
        );
        $output .= '</div>';

        return $output;
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

        $status_class = 'sikshya-status-' . $status;
        $status_text = $status_labels[$status] ?? $status;

        return sprintf(
            '<span class="sikshya-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Column enrollments
     *
     * @param object $item
     * @return string
     */
    public function column_enrollments($item): string
    {
        $enrollments = get_post_meta($item->ID, 'course_enrollments', true) ?: 0;

        return sprintf(
            '<a href="#">%d</a>',
            $enrollments
        );
    }

    /**
     * Column price
     *
     * @param object $item
     * @return string
     */
    public function column_categories($item): string
    {
        $categories = get_the_terms($item->ID, Taxonomies::COURSE_CATEGORY);

        if (empty($categories) || is_wp_error($categories)) {
            return '<span class="sikshya-no-categories">—</span>';
        }

        $output = '<div class="sikshya-categories">';
        foreach ($categories as $category) {
            $output .= sprintf(
                '<span class="sikshya-category-tag">%s</span>',
                esc_html($category->name)
            );
        }
        $output .= '</div>';

        return $output;
    }

    public function column_rating($item): string
    {
        $rating = get_post_meta($item->ID, 'course_rating', true) ?: 0;

        if ($rating == 0) {
            return '<span class="sikshya-no-rating">—</span>';
        }

        return sprintf(
            '<span class="sikshya-rating-text">%s/5</span>',
            number_format($rating, 1)
        );
    }

    public function column_price($item): string
    {
        $price = get_post_meta($item->ID, 'course_price', true) ?: '0.00';
        $original_price = get_post_meta($item->ID, 'course_original_price', true) ?: null;

        if ($price === '0.00' || empty($price)) {
            return '<span class="sikshya-price-free">FREE</span>';
        }

        $formatted_price = '$' . number_format($price, 2);

        if ($original_price && $original_price > $price) {
            $formatted_original = '$' . number_format($original_price, 2);
            $discount_percent = round((($original_price - $price) / $original_price) * 100);

            return sprintf(
                '<div class="sikshya-price-discounted">
                    <div class="sikshya-price-current">%s</div>
                    <div class="sikshya-price-original">%s</div>
                </div>',
                esc_html($formatted_price),
                esc_html($formatted_original)
            );
        }

        return '<span class="sikshya-price-paid">' . esc_html($formatted_price) . '</span>';
    }

    /**
     * Column lessons
     *
     * @param object $item
     * @return string
     */
    public function column_lessons($item): string
    {
        $lessons_count = get_post_meta($item->ID, 'course_lessons', true) ?: 0;

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
        $status = sanitize_key($_GET['status'] ?? '');

        if (empty($status)) {
            return ['publish', 'draft', 'private', 'pending'];
        }

        $allowed = ['publish', 'draft', 'private', 'pending'];
        return in_array($status, $allowed, true) ? $status : ['publish', 'draft', 'private', 'pending'];
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
        $price_type = sanitize_key($_GET['price_type'] ?? '');

        if (empty($price_type)) {
            return false;
        }

        return in_array($price_type, ['free', 'paid'], true) ? $price_type : false;
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
        $orderby = sanitize_key($_GET['orderby'] ?? 'date');

        $allowed_orderby = ['title', 'instructor', 'status', 'enrollments', 'price', 'created', 'date'];

        return in_array($orderby, $allowed_orderby, true) ? $orderby : 'date';
    }

    /**
     * Get order
     *
     * @return string
     */
    private function getOrder(): string
    {
        $order = sanitize_key($_GET['order'] ?? 'desc');

        $order = strtoupper($order);
        return in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
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
        echo '<a href="' . esc_url(ReactAdminConfig::reactAppUrl('add-course')) . '" class="button button-primary">';
        echo '<span class="dashicons dashicons-plus-alt2"></span> ' . __('Add New Course', 'sikshya');
        echo '</a>';
        echo '</div>';
    }
}
