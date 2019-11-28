<?php
if (!class_exists('Sikshya_Custom_Post_Type_Question')) {

    class Sikshya_Custom_Post_Type_Question
    {

        public function register()
        {
            $labels = array(
                'name' => _x('Questions', 'post type general name', 'sikshya'),
                'singular_name' => _x('Question', 'post type singular name', 'sikshya'),
                'menu_name' => _x('Questions', 'admin menu', 'sikshya'),
                'name_admin_bar' => _x('Question', 'add new on admin bar', 'sikshya'),
                'add_new' => _x('Add New', 'questions', 'sikshya'),
                'add_new_item' => __('Add New Question', 'sikshya'),
                'new_item' => __('New Question', 'sikshya'),
                'edit_item' => __('Edit Question', 'sikshya'),
                'view_item' => __('View Question', 'sikshya'),
                'all_items' => __('Questions', 'sikshya'),
                'search_items' => __('Search Questions', 'sikshya'),
                'parent_item_colon' => __('Parent Questions:', 'sikshya'),
                'not_found' => __('No questions found.', 'sikshya'),
                'not_found_in_trash' => __('No questions found in Trash.', 'sikshya')
            );

            $args = array(
                'labels' => $labels,
                'public' => true,
                'publicly_queryable' => true,
                'has_archive' => true,
                'exclude_from_search' => true,
                'show_in_nav_menus' => false,
                'show_ui' => true,
                'query_var' => true,
                'show_in_menu' => 'sikshya',
                'rewrite' => array(
                    'slug' => 'questions',
                    'with_front' => false
                ),
                'capability_type' => 'post',
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => array(
                    'title',
                    'comments',
                    'thumbnail',
                    'excerpt',
                    'custom-fields',
                    'editor',
                    'page-attributes'
                ),
                'show_in_rest' => true,
            );
            register_post_type(SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE, $args);
            remove_post_type_support(SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE, 'comments');
            if (function_exists('remove_meta_box')) {
                remove_meta_box('commentstatusdiv', SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE, 'normal'); //removes comments status
                remove_meta_box('commentsdiv', SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE, 'normal'); //removes comments
            }
            do_action('sikshya_after_register_post_type');

        }

        function disable($current_status, $post_type)
        {
            // Use your post type key instead of 'product'
            if ($post_type === SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE) return false;
            return $current_status;
        }

        public function init()
        {
            add_filter('use_block_editor_for_post_type', array($this, 'disable'), 10, 2);

            add_action('init', array($this, 'register'));
        }
    }


}
