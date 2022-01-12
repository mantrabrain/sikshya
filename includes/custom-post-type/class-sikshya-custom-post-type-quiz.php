<?php
if (!class_exists('Sikshya_Custom_Post_Type_Quiz')) {

    class Sikshya_Custom_Post_Type_Quiz
    {

        public function register()
        {
            $labels = array(
                'name' => _x('Quizzes', 'post type general name', 'sikshya'),
                'singular_name' => _x('Quiz', 'post type singular name', 'sikshya'),
                'menu_name' => _x('Quizzes', 'admin menu', 'sikshya'),
                'name_admin_bar' => _x('Quiz', 'add new on admin bar', 'sikshya'),
                'add_new' => _x('Add New', 'quizzes', 'sikshya'),
                'add_new_item' => __('Add New Quiz', 'sikshya'),
                'new_item' => __('New Quiz', 'sikshya'),
                'edit_item' => __('Edit Quiz', 'sikshya'),
                'view_item' => __('View Quiz', 'sikshya'),
                'all_items' => __('Quizzes', 'sikshya'),
                'search_items' => __('Search Quizzes', 'sikshya'),
                'parent_item_colon' => __('Parent Quizzes:', 'sikshya'),
                'not_found' => __('No quizzes found.', 'sikshya'),
                'not_found_in_trash' => __('No quizzes found in Trash.', 'sikshya')
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
                'show_in_menu' => 'edit.php?post_type=sik_courses',
                'rewrite' => array(
                    'slug' => 'quizzes',
                    'with_front' => false
                ),
                'capability_type' => 'post',
                'hierarchical' => false,
                'menu_position' => 30,
                'supports' => array(
                    'title',
                    'editor',
                ),
                'show_in_rest' => true,
            );
            register_post_type(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, $args);
            remove_post_type_support(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, 'comments');
            if (function_exists('remove_meta_box')) {
                remove_meta_box('commentstatusdiv', SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, 'normal'); //removes comments status
                remove_meta_box('commentsdiv', SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, 'normal'); //removes comments
            }
            do_action('sikshya_after_register_post_type');

        }

        function disable($current_status, $post_type)
        {
            // Use your post type key instead of 'product'
            if ($post_type === SIKSHYA_QUIZZES_CUSTOM_POST_TYPE) return false;
            return $current_status;
        }

        public function init()
        {
            add_filter('use_block_editor_for_post_type', array($this, 'disable'), 10, 2);

            add_action('init', array($this, 'register'));
        }
    }


}
