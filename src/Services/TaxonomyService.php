<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

/**
 * Taxonomy Management Service
 *
 * @package Sikshya\Services
 */
class TaxonomyService
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
     * Register taxonomies
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerCourseCategory']);
        add_action('init', [$this, 'registerCourseTag']);
        add_action('init', [$this, 'registerDifficultyLevel']);
        add_action('init', [$this, 'registerLessonType']);
        add_action('init', [$this, 'registerQuestionType']);
    }

    /**
     * Register Course Category taxonomy
     */
    public function registerCourseCategory(): void
    {
        $labels = [
            'name' => __('Course Categories', 'sikshya'),
            'singular_name' => __('Course Category', 'sikshya'),
            'search_items' => __('Search Course Categories', 'sikshya'),
            'all_items' => __('All Course Categories', 'sikshya'),
            'parent_item' => __('Parent Course Category', 'sikshya'),
            'parent_item_colon' => __('Parent Course Category:', 'sikshya'),
            'edit_item' => __('Edit Course Category', 'sikshya'),
            'update_item' => __('Update Course Category', 'sikshya'),
            'add_new_item' => __('Add New Course Category', 'sikshya'),
            'new_item_name' => __('New Course Category Name', 'sikshya'),
            'menu_name' => __('Categories', 'sikshya'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'course-category'],
            'show_in_rest' => true,
        ];

        register_taxonomy(Taxonomies::COURSE_CATEGORY, [PostTypes::COURSE], $args);
    }

    /**
     * Register Course Tag taxonomy
     */
    public function registerCourseTag(): void
    {
        $labels = [
            'name' => __('Course Tags', 'sikshya'),
            'singular_name' => __('Course Tag', 'sikshya'),
            'search_items' => __('Search Course Tags', 'sikshya'),
            'all_items' => __('All Course Tags', 'sikshya'),
            'edit_item' => __('Edit Course Tag', 'sikshya'),
            'update_item' => __('Update Course Tag', 'sikshya'),
            'add_new_item' => __('Add New Course Tag', 'sikshya'),
            'new_item_name' => __('New Course Tag Name', 'sikshya'),
            'menu_name' => __('Tags', 'sikshya'),
        ];

        $args = [
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'course-tag'],
            'show_in_rest' => true,
        ];

        register_taxonomy(Taxonomies::COURSE_TAG, [PostTypes::COURSE], $args);
    }

    /**
     * Register Difficulty Level taxonomy
     */
    public function registerDifficultyLevel(): void
    {
        $labels = [
            'name' => __('Difficulty Levels', 'sikshya'),
            'singular_name' => __('Difficulty Level', 'sikshya'),
            'search_items' => __('Search Difficulty Levels', 'sikshya'),
            'all_items' => __('All Difficulty Levels', 'sikshya'),
            'edit_item' => __('Edit Difficulty Level', 'sikshya'),
            'update_item' => __('Update Difficulty Level', 'sikshya'),
            'add_new_item' => __('Add New Difficulty Level', 'sikshya'),
            'new_item_name' => __('New Difficulty Level Name', 'sikshya'),
            'menu_name' => __('Difficulty', 'sikshya'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'difficulty'],
            'show_in_rest' => true,
        ];

        register_taxonomy(Taxonomies::DIFFICULTY, [PostTypes::COURSE, PostTypes::LESSON], $args);
    }

    /**
     * Register Lesson Type taxonomy
     */
    public function registerLessonType(): void
    {
        $labels = [
            'name' => __('Lesson Types', 'sikshya'),
            'singular_name' => __('Lesson Type', 'sikshya'),
            'search_items' => __('Search Lesson Types', 'sikshya'),
            'all_items' => __('All Lesson Types', 'sikshya'),
            'edit_item' => __('Edit Lesson Type', 'sikshya'),
            'update_item' => __('Update Lesson Type', 'sikshya'),
            'add_new_item' => __('Add New Lesson Type', 'sikshya'),
            'new_item_name' => __('New Lesson Type Name', 'sikshya'),
            'menu_name' => __('Lesson Types', 'sikshya'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'lesson-type'],
            'show_in_rest' => true,
        ];

        register_taxonomy('sikshya_lesson_type', ['sikshya_lesson'], $args);
    }

    /**
     * Register Question Type taxonomy
     */
    public function registerQuestionType(): void
    {
        $labels = [
            'name' => __('Question Types', 'sikshya'),
            'singular_name' => __('Question Type', 'sikshya'),
            'search_items' => __('Search Question Types', 'sikshya'),
            'all_items' => __('All Question Types', 'sikshya'),
            'edit_item' => __('Edit Question Type', 'sikshya'),
            'update_item' => __('Update Question Type', 'sikshya'),
            'add_new_item' => __('Add New Question Type', 'sikshya'),
            'new_item_name' => __('New Question Type Name', 'sikshya'),
            'menu_name' => __('Question Types', 'sikshya'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'question-type'],
            'show_in_rest' => true,
        ];

        register_taxonomy('sikshya_question_type', ['sikshya_quiz'], $args);
    }

    /**
     * Get taxonomy terms
     *
     * @param string $taxonomy
     * @param array $args
     * @return array
     */
    public function getTerms(string $taxonomy, array $args = []): array
    {
        $default_args = [
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ];

        $args = wp_parse_args($args, $default_args);
        
        return get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => $args['hide_empty'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ]);
    }

    /**
     * Get term by ID
     *
     * @param int $termId
     * @param string $taxonomy
     * @return \WP_Term|null
     */
    public function getTerm(int $termId, string $taxonomy): ?\WP_Term
    {
        $term = get_term($termId, $taxonomy);
        return $term instanceof \WP_Term ? $term : null;
    }

    /**
     * Create term
     *
     * @param string $name
     * @param string $taxonomy
     * @param array $args
     * @return \WP_Term|\WP_Error
     */
    public function createTerm(string $name, string $taxonomy, array $args = [])
    {
        return wp_insert_term($name, $taxonomy, $args);
    }

    /**
     * Update term
     *
     * @param int $termId
     * @param string $taxonomy
     * @param array $args
     * @return \WP_Term|\WP_Error
     */
    public function updateTerm(int $termId, string $taxonomy, array $args = [])
    {
        return wp_update_term($termId, $taxonomy, $args);
    }

    /**
     * Delete term
     *
     * @param int $termId
     * @param string $taxonomy
     * @return bool|\WP_Error
     */
    public function deleteTerm(int $termId, string $taxonomy)
    {
        return wp_delete_term($termId, $taxonomy);
    }
} 