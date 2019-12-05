<?php
if (!class_exists('Sikshya_Custom_Post_Type_Lesson')) {

    class Sikshya_Custom_Post_Type_Lesson
    {

        public function register()
        {

            $labels = array(
                'name' => _x('Lessons', 'post type general name', 'sikshya'),
                'singular_name' => _x('Lesson', 'post type singular name', 'sikshya'),
                'menu_name' => _x('Lessons', 'admin menu', 'sikshya'),
                'name_admin_bar' => _x('Lesson', 'add new on admin bar', 'sikshya'),
                'add_new' => _x('Add New', 'lessons', 'sikshya'),
                'add_new_item' => __('Add New Lesson', 'sikshya'),
                'new_item' => __('New Lesson', 'sikshya'),
                'edit_item' => __('Edit Lesson', 'sikshya'),
                'view_item' => __('View Lesson', 'sikshya'),
                'all_items' => __('Lessons', 'sikshya'),
                'search_items' => __('Search Lessons', 'sikshya'),
                'parent_item_colon' => __('Parent Lessons:', 'sikshya'),
                'not_found' => __('No lessons found.', 'sikshya'),
                'not_found_in_trash' => __('No lessons found in Trash.', 'sikshya')
            );
            $args = array(
                'labels' => $labels,
                'public' => true,
                'publicly_queryable' => true,
                'has_archive' => false,
                'exclude_from_search' => true,
                'show_in_nav_menus' => false,
                'show_ui' => true,
                'query_var' => true,
                'show_in_menu' => 'sikshya',
                'rewrite' => array(
                    'slug' => 'lessons',
                    'with_front' => false
                ),
                'capability_type' => 'post',
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => array(
                    'title',
                    'thumbnail',
                    'excerpt',
                    'editor',
                ),
                'show_in_rest' => true,
            );
            register_post_type(SIKSHYA_LESSONS_CUSTOM_POST_TYPE, $args);
            remove_post_type_support(SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'comments');
            if (function_exists('remove_meta_box')) {
                remove_meta_box('commentstatusdiv', SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'normal'); //removes comments status
                remove_meta_box('commentsdiv', SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'normal'); //removes comments
            }
            do_action('sikshya_after_register_post_type');

        }

        function disable($current_status, $post_type)
        {
            // Use your post type key instead of 'product'
            if ($post_type === SIKSHYA_LESSONS_CUSTOM_POST_TYPE) return false;
            return $current_status;
        }

        public function init()
        {
            add_filter('use_block_editor_for_post_type', array($this, 'disable'), 10, 2);

            add_action('init', array($this, 'register'));
        }
    }


}
