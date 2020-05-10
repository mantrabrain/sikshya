<?php
if (!class_exists('Sikshya_Metabox_Lesson')) {

    class Sikshya_Metabox_Lesson
    {
        private $lesson_meta = array();


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);
            add_action('save_post', array($this, 'save'));


        }

        public function metabox()
        {
            add_meta_box(SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_course', __('Assigned', 'sikshya'), array($this, 'assign_options'), SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'side', 'high');

            $this->init_meta_data();


            add_action('edit_form_after_editor', array($this, 'lesson_options'));

        }

        public function init_meta_data()
        {

            $this->lesson_meta = sikshya()->lesson->get_lesson_meta();

        }

        public function lesson_options()
        {
            if (SIKSHYA_LESSONS_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_nonce');

            sikshya_load_admin_template('metabox.lesson.main', $this->lesson_meta);
        }

        public function assign_options($post)
        {
            sikshya_load_admin_template('metabox.lesson.assigned-course');

        }

        public function save($post_id)
        {

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            if (SIKSHYA_LESSONS_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }

            if (empty($_POST[SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_nonce']) || !wp_verify_nonce($_POST[SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_nonce'], SIKSHYA_FILE)) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            remove_action('save_post', array($this, 'save'));

            $course_object = get_post($post_id);

            if ($course_object->post_type != SIKSHYA_LESSONS_CUSTOM_POST_TYPE) {
                return;
            }


            $valid_lesson_info = array();


            $valid_lesson_info['sikshya_lesson_duration'] = isset($_POST['sikshya_lesson_duration']) ? sikshya_maybe_absint($_POST['sikshya_lesson_duration']) : '';
            $valid_lesson_info['sikshya_lesson_duration_time'] = isset($_POST['sikshya_lesson_duration_time']) ? sanitize_text_field($_POST['sikshya_lesson_duration_time']) : '';
            $valid_lesson_info['sikshya_is_preview_lesson'] = isset($_POST['sikshya_is_preview_lesson']) ? boolval($_POST['sikshya_is_preview_lesson']) : false;
            $valid_lesson_info['sikshya_lesson_video_source'] = isset($_POST['sikshya_lesson_video_source']) ? sanitize_text_field($_POST['sikshya_lesson_video_source']) : 'youtube';
            $valid_lesson_info['sikshya_lesson_youtube_video_url'] = isset($_POST['sikshya_lesson_youtube_video_url']) ? esc_url($_POST['sikshya_lesson_youtube_video_url']) : '';

            foreach ($valid_lesson_info as $info_key => $info) {

                update_post_meta($post_id, $info_key, $info);

            }

        }


    }
}