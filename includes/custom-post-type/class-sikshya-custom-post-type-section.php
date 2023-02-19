<?php
if (!class_exists('Sikshya_Custom_Post_Type_Section')) {

    class Sikshya_Custom_Post_Type_Section
    {

        public function register()
        {

            $args = array(
                'labels' => array('name' => 'Sections'),
                'public' => true,
                'publicly_queryable' => true,
                'has_archive' => true,
                'show_in_menu' => 'edit.php?post_type=sik_courses',
                'exclude_from_search' => true,
                'show_in_nav_menus' => false,
                'show_ui' => true,
                'query_var' => true,
                'rewrite' => false,
                'capability_type' => 'post',
                'hierarchical' => false,
                //'taxonomies' => $this->_getTaxArr(),
                'menu_position' => 19,
                'supports' => array(
                    'title',
                    'editor',
                ),
                'show_in_rest' => true,
            );
            register_post_type(SIKSHYA_SECTIONS_CUSTOM_POST_TYPE, $args);
            do_action('sikshya_after_register_post_type');

        }

        function disable($current_status, $post_type)
        {
            // Use your post type key instead of 'product'
            if ($post_type === SIKSHYA_SECTIONS_CUSTOM_POST_TYPE) return false;
            return $current_status;
        }

        public function init()
        {
            add_filter('use_block_editor_for_post_type', array($this, 'disable'), 10, 2);

            add_action('init', array($this, 'register'));
        }
    }


}
