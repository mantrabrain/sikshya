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
                'show_in_menu' => 'edit.php?post_type=sik_courses',
                'rewrite' => array(
                    'slug' => 'questions',
                    'with_front' => false
                ),
                'capability_type' => 'post',
                'hierarchical' => false,
                'menu_position' => 40,
                'supports' => array(
                    'title',
                    'editor',
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

        public function update_message($messages)
        {
            $post = get_post();
            $post_type = get_post_type($post);
            $post_type_object = get_post_type_object($post_type);

            $question_post_type = SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE;

            $messages[$question_post_type] = array(
                0 => '', // Unused. Messages start at index 1.
                1 => __('Question updated.', 'sikshya'),
                2 => __('Custom field updated.', 'sikshya'),
                3 => __('Custom field deleted.', 'sikshya'),
                4 => __('Question updated.', 'sikshya'),
                /* translators: %s: date and time of the revision */
                5 => isset($_GET['revision']) ? sprintf(__('Question restored to revision from %s', 'sikshya'), wp_post_revision_title((int)$_GET['revision'], false)) : false,
                6 => __('Question published.', 'sikshya'),
                7 => __('Question saved.', 'sikshya'),
                8 => __('Question submitted.', 'sikshya'),
                9 => sprintf(
                    __('Question scheduled for: <strong>%1$s</strong>.', 'sikshya'),
                    // translators: Publish box date format, see http://php.net/date
                    date_i18n(__('M j, Y @ G:i', 'sikshya'), strtotime($post->post_date))
                ),
                10 => __('Question draft updated.', 'sikshya')
            );

            if ($post_type_object->publicly_queryable && $question_post_type === $post_type) {
                $permalink = get_permalink($post->ID);

                $view_link = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View question', 'sikshya'));
                $messages[$post_type][1] .= $view_link;
                $messages[$post_type][6] .= $view_link;
                $messages[$post_type][9] .= $view_link;

                $preview_permalink = add_query_arg('preview', 'true', $permalink);
                $preview_link = sprintf(' <a target="_blank" href="%s">%s</a>', esc_url($preview_permalink), __('Preview question', 'sikshya'));
                $messages[$post_type][8] .= $preview_link;
                $messages[$post_type][10] .= $preview_link;
            }

            return $messages;

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
            add_filter('post_updated_messages', array($this, 'update_message'));

        }
    }


}
