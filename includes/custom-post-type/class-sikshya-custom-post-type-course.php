<?php
if (!class_exists('Sikshya_Custom_Post_Type_Course')) {

    class Sikshya_Custom_Post_Type_Course
    {

        public function register()
        {
            $labels = array(
                'name' => _x('Courses', 'post type general name', 'sikshya'),
                'singular_name' => _x('Course', 'post type singular name', 'sikshya'),
                'menu_name' => _x('Courses', 'admin menu', 'sikshya'),
                'name_admin_bar' => _x('Course', 'add new on admin bar', 'sikshya'),
                'add_new' => _x('Add New', 'courses', 'sikshya'),
                'add_new_item' => __('Add New Course', 'sikshya'),
                'new_item' => __('New Course', 'sikshya'),
                'edit_item' => __('Edit Course', 'sikshya'),
                'view_item' => __('View Course', 'sikshya'),
                'all_items' => __('Courses', 'sikshya'),
                'search_items' => __('Search Courses', 'sikshya'),
                'parent_item_colon' => __('Parent Courses:', 'sikshya'),
                'not_found' => __('No courses found.', 'sikshya'),
                'not_found_in_trash' => __('No courses found in Trash.', 'sikshya')
            );
            $args = array(
                'labels' => $labels,
                'description' => __('Description.', 'sikshya'),
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => 'sikshya',
                'query_var' => true,
                'capability_type' => 'post',
                'hierarchical' => false,
                'menu_position' => null,
                'taxonomies' => array('sik_course_category', 'sik_course_tag'),
                'supports' => array(
                    'excerpt',
                    'title',
                    'thumbnail',
                    'editor',
                ),

                'show_in_rest' => true,
                'menu_icon' => 'dashicons-book-alt',
                'has_archive' => true,
                'rewrite' => array(
                    'slug' => 'courses',
                    'with_front' => false
                )
                /* 'capabilities' => array(
                     'edit_post' => 'edit_sikshya_course',
                     'read_post' => 'read_sikshya_course',
                     'delete_post' => 'delete_sikshya_course',
                     'delete_posts' => 'delete_sikshya_courses',
                     'edit_posts' => 'edit_sikshya_courses',
                     'edit_others_posts' => 'edit_others_sikshya_courses',
                     'publish_posts' => 'publish_sikshya_courses',
                     'read_private_posts' => 'read_private_sikshya_courses',
                     'create_posts' => 'edit_sikshya_courses',
                 ),*/
            );

            register_post_type(SIKSHYA_COURSES_CUSTOM_POST_TYPE, $args);
            remove_post_type_support(SIKSHYA_COURSES_CUSTOM_POST_TYPE, 'comments');
            if (function_exists('remove_meta_box')) {
                remove_meta_box('commentstatusdiv', SIKSHYA_COURSES_CUSTOM_POST_TYPE, 'normal'); //removes comments status
                remove_meta_box('commentsdiv', SIKSHYA_COURSES_CUSTOM_POST_TYPE, 'normal'); //removes comments
            }

            do_action('sikshya_after_register_post_type');

        }

        function disable($current_status, $post_type)
        {
            // Use your post type key instead of 'product'
            if ($post_type === SIKSHYA_COURSES_CUSTOM_POST_TYPE) return false;
            return $current_status;
        }

        public function update_message($messages)
        {
            $post = get_post();
            $post_type = get_post_type($post);
            $post_type_object = get_post_type_object($post_type);

            $course_post_type = SIKSHYA_COURSES_CUSTOM_POST_TYPE;

            $messages[$course_post_type] = array(
                0 => '', // Unused. Messages start at index 1.
                1 => __('Course updated.', 'sikshya'),
                2 => __('Custom field updated.', 'sikshya'),
                3 => __('Custom field deleted.', 'sikshya'),
                4 => __('Course updated.', 'sikshya'),
                /* translators: %s: date and time of the revision */
                5 => isset($_GET['revision']) ? sprintf(__('Course restored to revision from %s', 'sikshya'), wp_post_revision_title((int)$_GET['revision'], false)) : false,
                6 => __('Course published.', 'sikshya'),
                7 => __('Course saved.', 'sikshya'),
                8 => __('Course submitted.', 'sikshya'),
                9 => sprintf(
                    __('Course scheduled for: <strong>%1$s</strong>.', 'sikshya'),
                    // translators: Publish box date format, see http://php.net/date
                    date_i18n(__('M j, Y @ G:i', 'sikshya'), strtotime($post->post_date))
                ),
                10 => __('Course draft updated.', 'sikshya')
            );

            if ($post_type_object->publicly_queryable && $course_post_type === $post_type) {
                $permalink = get_permalink($post->ID);

                $view_link = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View course', 'sikshya'));
                $messages[$post_type][1] .= $view_link;
                $messages[$post_type][6] .= $view_link;
                $messages[$post_type][9] .= $view_link;

                $preview_permalink = add_query_arg('preview', 'true', $permalink);
                $preview_link = sprintf(' <a target="_blank" href="%s">%s</a>', esc_url($preview_permalink), __('Preview course', 'sikshya'));
                $messages[$post_type][8] .= $preview_link;
                $messages[$post_type][10] .= $preview_link;
            }

            return $messages;

        }

        public function init()
        {
            add_filter('use_block_editor_for_post_type', array($this, 'disable'), 10, 2);
            add_action('init', array($this, 'register'));
            add_filter('post_updated_messages', array($this, 'update_message'));

        }
    }


}
