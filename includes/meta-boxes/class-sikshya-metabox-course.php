<?php
if (!class_exists('Sikshya_Metabox_Course')) {

    class Sikshya_Metabox_Course
    {

        private $_meta_prefix = 'sikshya_';

        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);

            add_action('save_post', array($this, 'save'));

            add_action('sikshya_course_metaboxes', array($this, 'course_meta'));

            add_action('sikshya_course_tab_curriculum', array($this, 'curriculum_tab'));
            add_action('sikshya_course_curriculum_tab_before', array($this, 'curriculum_tab_before'));
            add_action('sikshya_course_tab_others', array($this, 'others_tab'));


        }

        public function curriculum_tab_before()
        {
            global $post;

            $post_id = isset($post->ID) ? absint($post->ID) : 0;

            if ($post_id > 0) {

                $template_vars = array();

                if ($post instanceof \WP_Post) {

                    $template_vars = sikshya()->course->get_all($post_id);
                }

                $sections = isset($template_vars->sections) ? $template_vars->sections : array();

                foreach ($sections as $section) {
                    $section_data = array(
                        'section_id' => $section->ID,
                        'section_title' => $section->post_title,
                    );
                    sikshya_load_admin_template('metabox.course.tabs.curriculum.section-template', $section_data);
                }

            }

        }

        public function curriculum_tab()
        {
            global $post;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce');


            sikshya_load_admin_template('metabox.course.tabs.curriculum', array());

            ///include_once "views/sections-and-lessons.php";
        }

        public function others_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.others', array());
        }

        public function course_meta()
        {
            $tabs = array(
                'curriculum' => array(
                    'is_active' => true,
                    'title' => esc_html__('Curriculum', 'sikshya'),
                ),
                'general' => array(
                    'title' => esc_html__('General', 'sikshya'),
                ),
                'requirement' => array(
                    'title' => esc_html__('Requirement', 'sikshya'),
                ),
                'outcomes' => array(
                    'title' => esc_html__('Outcomes', 'sikshya'),
                ),
                'pricing' => array(
                    'title' => esc_html__('Pricing', 'sikshya'),
                ),
                'media' => array(
                    'title' => esc_html__('Media', 'sikshya'),
                )
            );

            sikshya_load_admin_template('metabox.course.options', array(

                'tabs' => $tabs
            ));

        }

        public function metabox()
        {
            add_action('edit_form_after_editor', array($this, 'course_options_meta'));


        }


        public function course_options_meta($post)
        {
            global $post;

            if (SIKSHYA_COURSES_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }

            $template_vars = sikshya_get_course_info($post->ID);

            $template_vars['show_on_login'] = get_post_meta($post->ID, $this->_meta_prefix . 'info_show_on_login', true);
            $template_vars['instructor'] = get_post_meta($post->ID, 'instructor', true);

            $params['template_vars'] = $template_vars;

            sikshya_load_admin_template('metabox.course.main', $params);

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

            remove_action('save_post', array($this, 'save'));

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
            $instructor = isset($sikshya_info['instructor']) ? absint($sikshya_info['instructor']) : 0;
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

            if (($instructor) > 0) {

                update_post_meta($post_id, 'sikshya_instructor', $instructor);

                if (!sikshya()->role->has_instructor($instructor)) {

                    sikshya()->role->add_instructor_role($instructor);

                }
            }

            unset($valid_sikshya_info['show_on_login']);
            update_post_meta($post_id, $this->_meta_prefix . 'info', $valid_sikshya_info);

            // Update Sikshya Information from here
            $this->update_other_meta($post_id);

        }

        private function update_other_meta($post_id)
        {

            $sikshya_course_content = isset($_POST['sikshya_course_content']) ? $_POST['sikshya_course_content'] : array();

            $section_ids = isset($sikshya_course_content['section_ids']) ? $sikshya_course_content['section_ids'] : array();

            $lesson_ids = isset($sikshya_course_content['lesson_ids']) ? $sikshya_course_content['lesson_ids'] : array();

            $quiz_ids = isset($sikshya_course_content['quiz_ids']) ? $sikshya_course_content['quiz_ids'] : array();

            if (count($section_ids) > 0) {

                $saved_section_ids = sikshya()->section->save($section_ids, $post_id);
            }

            if (count($lesson_ids) > 0) {

                $saved_lesson_ids = sikshya()->lesson->save($lesson_ids, $post_id);
            }

            if (count($quiz_ids) > 0) {

                $saved_quizzes_ids = sikshya()->quiz->save(
                    $quiz_ids, $post_id, SIKSHYA_COURSES_CUSTOM_POST_TYPE);
            }

        }


    }
}