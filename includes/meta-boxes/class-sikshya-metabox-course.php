<?php
if (!class_exists('Sikshya_Metabox_Course')) {

    class Sikshya_Metabox_Course
    {

        private $_meta_prefix = 'sikshya_';

        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);

            add_action('save_post', array($this, 'save'));


        }

        public function metabox()
        {
            add_meta_box(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_info', __('Course Options', 'sikshya'), array($this, 'course_options_meta'), SIKSHYA_COURSES_CUSTOM_POST_TYPE, 'normal', 'high');
            add_meta_box(SIKSHYA_COURSES_CUSTOM_POST_TYPE . 'sikshya_section_lessons', __('Sections & Lessons', 'sikshya'), array($this, 'section_lesson_meta'), SIKSHYA_COURSES_CUSTOM_POST_TYPE, 'normal', 'high');


        }

        public function section_lesson_meta($post)
        {
            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce');

            $get_all_lessons = sikshya()->course->get_all_lessons($post->ID);

            $template_vars = array();

            if ($post instanceof \WP_Post) {

                $template_vars = sikshya()->course->get_all($post->ID);
            }

            include_once "views/sections-and-lessons.php";
        }

        public function course_options_meta($post)
        {
            $template_vars = sikshya_get_course_info($post->ID);

            $template_vars['show_on_login'] = get_post_meta($post->ID, $this->_meta_prefix . 'info_show_on_login', true);

            include_once "views/tmpl/course.php";

        }

        public function save($post_id)
        {


            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (empty($_POST[SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce']) || !wp_verify_nonce($_POST[SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce'], SIKSHYA_FILE)) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            $course_object = get_post($post_id);

            if ($course_object->post_type != SIKSHYA_COURSES_CUSTOM_POST_TYPE) {
                return;
            }


            $sikshya_info = isset($_POST['sikshya_info']) ? $_POST['sikshya_info'] : array();

            $valid_sikshya_info = array();


            /// Sikshya Information Validation

            $valid_sikshya_info['show_on_login'] = isset($sikshya_info['show_on_login']) ? 1 : 0;
            $valid_sikshya_info['subject'] = isset($sikshya_info['subject']) ? sanitize_text_field($sikshya_info['subject']) : '';
            $valid_sikshya_info['level'] = isset($sikshya_info['level']) ? sanitize_text_field($sikshya_info['level']) : '';
            $valid_sikshya_info['duration'] = isset($sikshya_info['duration']) ? absint($sikshya_info['duration']) : '';
            $valid_sikshya_info['currency'] = isset($sikshya_info['currency']) ? sanitize_text_field($sikshya_info['currency']) : '';
            $valid_sikshya_info['price'] = isset($sikshya_info['price']) ? absint($sikshya_info['price']) : '';
            $valid_sikshya_info['payment_period'] = isset($sikshya_info['payment_period']) ? sanitize_text_field($sikshya_info['payment_period']) : '';
            $valid_sikshya_info['price_display_options_bp'] = isset($sikshya_info['price_display_options_bp']) ? sanitize_text_field($sikshya_info['price_display_options_bp']) : '';
            $valid_sikshya_info['tax_display_options'] = isset($sikshya_info['tax_display_options']) ? sanitize_text_field($sikshya_info['tax_display_options']) : '';
            $valid_sikshya_info['description'] = isset($sikshya_info['description']) ? wp_kses($sikshya_info['description'], array(
                'a' => array(
                    'href' => array(),
                    'title' => array()
                ),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'img' => array(
                    'src' => array(),
                    'class' => array(),
                    'alt' => array(),
                    'title' => array(),
                    'height' => array(),
                    'width' => array()
                )
            )) : '';


            update_post_meta($post_id, $this->_meta_prefix . 'info_show_on_login', $valid_sikshya_info['show_on_login']);

            unset($valid_sikshya_info['show_on_login']);
            update_post_meta($post_id, $this->_meta_prefix . 'info', $valid_sikshya_info);

            // Update Sikshya Information from here
            $this->update_other_meta($post_id);

        }

        private function update_other_meta($post_id)
        {
            $sections = isset($_POST['sikshya_section']) ? $_POST['sikshya_section'] : array();

            $lessons = isset($_POST['sikshya_lesson']) ? $_POST['sikshya_lesson'] : array();

            $quizzes = isset($_POST['lessons_quiz']) ? $_POST['lessons_quiz'] : array();

            $lessons_questions = isset($_POST['lessons_questions']) ? $_POST['lessons_questions'] : array();

            $saved_section_ids = array();

            if (count($sections) > 0) {

                $saved_section_ids = sikshya()->section->save($sections, $post_id);
            }

            $saved_lesson_ids = array();


            if (count($lessons) > 0) {

                $saved_lesson_ids = sikshya()->lesson->save($lessons, $saved_section_ids, $post_id);
            }

            $saved_quizzes_ids = array();


            if (count($quizzes) > 0) {

                $saved_quizzes_ids = sikshya()->quiz->save(
                    $quizzes, $saved_section_ids, $saved_lesson_ids, $post_id);
            }

            $saved_question_ids = array();

            if (count($lessons_questions) > 0) {

                $saved_question_ids = sikshya()->question->save(

                    $lessons_questions, $saved_section_ids, $saved_lesson_ids, $saved_quizzes_ids, $post_id);
            }
        }


    }
}