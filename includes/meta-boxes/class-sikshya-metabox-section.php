<?php
if (!class_exists('Sikshya_Metabox_Section')) {

    class Sikshya_Metabox_Section
    {

        private $_meta_prefix = 'sikshya_';

        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'));
            add_action('save_post', array($this, 'save'));


        }

        public function metabox()
        {
            add_meta_box(SIKSHYA_SECTIONS_CUSTOM_POST_TYPE . '_section', __('Section Options', 'sikshya'), array($this, 'section_options'), SIKSHYA_SECTIONS_CUSTOM_POST_TYPE, 'normal', 'high');

        }

        public function save($section_id)
        {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (empty($_POST[SIKSHYA_SECTIONS_CUSTOM_POST_TYPE . '_nonce']) || !wp_verify_nonce($_POST[SIKSHYA_SECTIONS_CUSTOM_POST_TYPE . '_nonce'], SIKSHYA_FILE)) {
                return;
            }

            if (!current_user_can('edit_post', $section_id)) {
                return;
            }

            $section_object = get_post($section_id);

            if ($section_object->post_type != SIKSHYA_SECTIONS_CUSTOM_POST_TYPE) {
                return;
            }

            $sections = isset($_POST['sikshya_section']) ? $_POST['sikshya_section'] : array();

            if (count($sections) > 0) {

                sikshya()->section->update_meta($sections, $section_id);
            }


        }

        public function section_options()
        {
            global $post;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_SECTIONS_CUSTOM_POST_TYPE . '_nonce');

            $section_image = get_post_meta($post->ID, 'image', true);
            echo sikshya()->section->render_tmpl(
                $post->ID,
                $post->post_title,
                $post->post_content,
                $section_image, ''
            );

        }


    }
}