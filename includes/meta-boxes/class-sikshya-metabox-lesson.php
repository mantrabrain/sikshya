<?php
if (!class_exists('Sikshya_Metabox_Lesson')) {

    class Sikshya_Metabox_Lesson
    {


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);


        }

        public function metabox()
        {
            add_meta_box(SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_course', __('Assigned', 'sikshya'), array($this, 'assign_options'), SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'side', 'high');

            add_meta_box(SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_options', __('Lesson Options', 'sikshya'), array($this, 'lesson_options'), SIKSHYA_LESSONS_CUSTOM_POST_TYPE, 'normal', 'high');

        }

        public function lesson_options()
        {
            global $post;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_LESSONS_CUSTOM_POST_TYPE . '_nonce');


            $section_id = get_post_meta($post->ID, 'section_id', true);

            $lesson_editor_name = 'sikshya_lesson[' . $section_id . '][' . $post->ID . '][lessons_content]';

            $lesson_editor_id = 'lesson_editor_' . $post->ID;

            $editor = sikshya_render_editor($post->post_content, $lesson_editor_name, $lesson_editor_id);

            echo sikshya()->lesson->render_tmpl($post->ID, '[' . $section_id . '][' . $post->ID . ']', $post->post_title, $editor, false, '');

        }

        public function assign_options($post)
        {
            include_once "views/lesson-course-assign.php";

        }


    }
}