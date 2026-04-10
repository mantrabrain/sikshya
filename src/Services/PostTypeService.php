<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;

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
        add_action('init', [$this, 'registerQuestionPostType']);
        add_action('init', [$this, 'registerChapterPostType']);
    }

    /**
     * Register Course post type
     */
    public function registerCoursePostType(): void
    {
        $rewrite = PermalinkService::get();

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
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_course'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::COURSE, $args);
    }

    /**
     * Register Lesson post type
     */
    public function registerLessonPostType(): void
    {
        $rewrite = PermalinkService::get();

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
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_lesson'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::LESSON, $args);
    }

    /**
     * Register Quiz post type
     */
    public function registerQuizPostType(): void
    {
        $rewrite = PermalinkService::get();

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
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_quiz'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::QUIZ, $args);
    }

    /**
     * Register Assignment post type
     */
    public function registerAssignmentPostType(): void
    {
        $rewrite = PermalinkService::get();

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
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_assignment'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::ASSIGNMENT, $args);
    }

    /**
     * Register Certificate post type
     */
    public function registerCertificatePostType(): void
    {
        $rewrite = PermalinkService::get();

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
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_certificate'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::CERTIFICATE, $args);
    }

    /**
     * Quiz questions (optional CPT; also used from quiz meta in builder).
     */
    public function registerQuestionPostType(): void
    {
        $labels = [
            'name' => __('Questions', 'sikshya'),
            'singular_name' => __('Question', 'sikshya'),
            'menu_name' => __('Questions', 'sikshya'),
            'add_new' => __('Add New Question', 'sikshya'),
            'add_new_item' => __('Add New Question', 'sikshya'),
            'edit_item' => __('Edit Question', 'sikshya'),
            'new_item' => __('New Question', 'sikshya'),
            'view_item' => __('View Question', 'sikshya'),
            'search_items' => __('Search Questions', 'sikshya'),
            'not_found' => __('No questions found', 'sikshya'),
            'not_found_in_trash' => __('No questions found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::QUESTION, $args);
    }

    /**
     * Course chapters (curriculum organization).
     */
    public function registerChapterPostType(): void
    {
        $labels = [
            'name' => __('Chapters', 'sikshya'),
            'singular_name' => __('Chapter', 'sikshya'),
            'menu_name' => __('Chapters', 'sikshya'),
            'add_new' => __('Add New Chapter', 'sikshya'),
            'add_new_item' => __('Add New Chapter', 'sikshya'),
            'edit_item' => __('Edit Chapter', 'sikshya'),
            'new_item' => __('New Chapter', 'sikshya'),
            'view_item' => __('View Chapter', 'sikshya'),
            'search_items' => __('Search Chapters', 'sikshya'),
            'not_found' => __('No chapters found', 'sikshya'),
            'not_found_in_trash' => __('No chapters found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'hierarchical' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::CHAPTER, $args);
    }
}
