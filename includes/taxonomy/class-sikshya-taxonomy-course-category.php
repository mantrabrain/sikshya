<?php
if (!class_exists('Sikshya_Taxonomy_Course_Category')) {

    class Sikshya_Taxonomy_Course_Category
    {

        public function init()
        {
            add_action('init', array($this, 'register'));
            //add_action('activity_add_form_fields', array($this, 'form'), 10, 2);
            //add_action('activity_edit_form_fields', array($this, 'edit'), 10, 2);
            //add_action('edited_activity', array($this, 'update'), 10, 2);
            //add_action('created_activity', array($this, 'save'), 10, 2);
        }

        public function register()
        {
            // Add new taxonomy, make it hierarchical (like categories)
            $labels = array(
                'name' => __('Category', 'yatra'),
                'singular_name' => __('Category', 'yatra'),
                'search_items' => __('Search Category', 'yatra'),
                'all_items' => __('All Category', 'yatra'),
                'parent_item' => __('Parent Category', 'yatra'),
                'parent_item_colon' => __('Parent Category:', 'yatra'),
                'edit_item' => __('Edit Category', 'yatra'),
                'update_item' => __('Update Category', 'yatra'),
                'add_new_item' => __('Add New Category', 'yatra'),
                'new_item_name' => __('New Category Name', 'yatra'),
                'menu_name' => __('Category', 'yatra'),
            );
            $args = array(
                'hierarchical' => true,
                'labels' => $labels,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array(
                    'slug' => 'course-category',
                    'with_front' => true
                )
            );
            register_taxonomy('course-category', array('courses'), $args);


        }

        public function form($taxonomy)
        { ?>
            <div class="form-field term-group">
                <label for="activity_image_id"><?php _e('Image', 'yatra'); ?></label>
                <input type="hidden" id="activity_image_id" name="activity_image_id" class="custom_media_url"
                       value="">
                <div id="activity_image_wrapper"></div>
                <p>
                    <input type="button" class="button button-secondary mb_taxonomy_media_upload_btn"
                           id="mb_taxonomy_media_upload_btn"
                           name="mb_taxonomy_media_upload_btn" value="<?php _e('Add Image', 'yatra'); ?>"
                           data-uploader-title="<?php _e('Choose Image', 'yatra'); ?>"
                           data-uploader-button-text="<?php _e('Choose Image', 'yatra'); ?>"
                    />
                    <input type="button" class="button button-secondary mb_taxonomy_remove_media"
                           id="mb_taxonomy_remove_media"
                           name="mb_taxonomy_remove_media" value="<?php _e('Remove Image', 'yatra'); ?>"/>
                </p>
            </div>
            <?php

        }

    }
}