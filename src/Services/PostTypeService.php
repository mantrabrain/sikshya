<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Post Type Management Service
 *
 * @package Sikshya\Services
 */
class PostTypeService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register post types
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerCoursePostType']);
        add_action('init', [$this, 'registerLessonPostType']);
        add_action('init', [$this, 'registerQuizPostType']);
        add_action('init', [$this, 'registerAssignmentPostType']);
        add_action('init', [$this, 'registerCertificatePostType']);
    }

    /**
     * Register Course post type
     */
    public function registerCoursePostType(): void
    {
        $labels = [
            'name' => __('Courses', 'sikshya'),
            'singular_name' => __('Course', 'sikshya'),
            'menu_name' => __('Courses', 'sikshya'),
            'add_new' => __('Add New Course', 'sikshya'),
            'add_new_item' => __('Add New Course', 'sikshya'),
            'edit_item' => __('Edit Course', 'sikshya'),
            'new_item' => __('New Course', 'sikshya'),
            'view_item' => __('View Course', 'sikshya'),
            'search_items' => __('Search Courses', 'sikshya'),
            'not_found' => __('No courses found', 'sikshya'),
            'not_found_in_trash' => __('No courses found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'course'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type('sikshya_course', $args);
    }

    /**
     * Register Lesson post type
     */
    public function registerLessonPostType(): void
    {
        $labels = [
            'name' => __('Lessons', 'sikshya'),
            'singular_name' => __('Lesson', 'sikshya'),
            'menu_name' => __('Lessons', 'sikshya'),
            'add_new' => __('Add New Lesson', 'sikshya'),
            'add_new_item' => __('Add New Lesson', 'sikshya'),
            'edit_item' => __('Edit Lesson', 'sikshya'),
            'new_item' => __('New Lesson', 'sikshya'),
            'view_item' => __('View Lesson', 'sikshya'),
            'search_items' => __('Search Lessons', 'sikshya'),
            'not_found' => __('No lessons found', 'sikshya'),
            'not_found_in_trash' => __('No lessons found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'lesson'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 6,
            'menu_icon' => 'dashicons-book',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type('sikshya_lesson', $args);
    }

    /**
     * Register Quiz post type
     */
    public function registerQuizPostType(): void
    {
        $labels = [
            'name' => __('Quizzes', 'sikshya'),
            'singular_name' => __('Quiz', 'sikshya'),
            'menu_name' => __('Quizzes', 'sikshya'),
            'add_new' => __('Add New Quiz', 'sikshya'),
            'add_new_item' => __('Add New Quiz', 'sikshya'),
            'edit_item' => __('Edit Quiz', 'sikshya'),
            'new_item' => __('New Quiz', 'sikshya'),
            'view_item' => __('View Quiz', 'sikshya'),
            'search_items' => __('Search Quizzes', 'sikshya'),
            'not_found' => __('No quizzes found', 'sikshya'),
            'not_found_in_trash' => __('No quizzes found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'quiz'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 7,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type('sikshya_quiz', $args);
    }

    /**
     * Register Assignment post type
     */
    public function registerAssignmentPostType(): void
    {
        $labels = [
            'name' => __('Assignments', 'sikshya'),
            'singular_name' => __('Assignment', 'sikshya'),
            'menu_name' => __('Assignments', 'sikshya'),
            'add_new' => __('Add New Assignment', 'sikshya'),
            'add_new_item' => __('Add New Assignment', 'sikshya'),
            'edit_item' => __('Edit Assignment', 'sikshya'),
            'new_item' => __('New Assignment', 'sikshya'),
            'view_item' => __('View Assignment', 'sikshya'),
            'search_items' => __('Search Assignments', 'sikshya'),
            'not_found' => __('No assignments found', 'sikshya'),
            'not_found_in_trash' => __('No assignments found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'assignment'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 8,
            'menu_icon' => 'dashicons-portfolio',
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type('sikshya_assignment', $args);
    }

    /**
     * Register Certificate post type
     */
    public function registerCertificatePostType(): void
    {
        $labels = [
            'name' => __('Certificates', 'sikshya'),
            'singular_name' => __('Certificate', 'sikshya'),
            'menu_name' => __('Certificates', 'sikshya'),
            'add_new' => __('Add New Certificate', 'sikshya'),
            'add_new_item' => __('Add New Certificate', 'sikshya'),
            'edit_item' => __('Edit Certificate', 'sikshya'),
            'new_item' => __('New Certificate', 'sikshya'),
            'view_item' => __('View Certificate', 'sikshya'),
            'search_items' => __('Search Certificates', 'sikshya'),
            'not_found' => __('No certificates found', 'sikshya'),
            'not_found_in_trash' => __('No certificates found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'certificate'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 9,
            'menu_icon' => 'dashicons-awards',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type('sikshya_certificate', $args);
    }
} 