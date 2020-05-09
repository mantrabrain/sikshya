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
            add_action('sikshya_course_tab_general', array($this, 'general_tab'));
            add_action('sikshya_course_tab_requirements', array($this, 'requirements_tab'));
            add_action('sikshya_course_tab_outcomes', array($this, 'outcomes_tab'));
            add_action('sikshya_course_tab_pricing', array($this, 'pricing_tab'));
            add_action('sikshya_course_tab_media', array($this, 'media_tab'));
            add_action('sikshya_course_curriculum_tab_before', array($this, 'curriculum_tab_before'));
            add_action('sikshya_course_curriculum_tab_lesson_quiz_template', array($this, 'curriculum_tab_lesson_quiz'), 10, 1);
            add_action('sikshya_course_tab_others', array($this, 'others_tab'));


        }

        public function curriculum_tab_before()
        {
            global $post;

            $post_id = isset($post->ID) ? absint($post->ID) : 0;

            if ($post_id > 0) {

                $sections = array();

                if ($post instanceof \WP_Post) {

                    $sections = sikshya()->section->get_all_by_course($post_id);
                }

                foreach ($sections as $section) {

                    $section_data = array(
                        'section_id' => $section->ID,
                        'section_title' => $section->post_title,
                    );
                    sikshya_load_admin_template('metabox.course.tabs.curriculum.section-template', $section_data);
                }

            }

        }

        public function curriculum_tab_lesson_quiz($section_id)
        {
            global $post;

            $post_id = isset($post->ID) ? absint($post->ID) : 0;

            $section_id = absint($section_id) > 0 ? absint($section_id) : 0;

            if ($post_id > 0 && $section_id > 0) {

                $lesson_and_quizes = array();

                if ($post instanceof \WP_Post) {

                    $lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section_id);
                }

                foreach ($lesson_and_quizes as $lesson_and_quize) {

                    $type = $lesson_and_quize->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE ? 'lesson_ids' : 'quiz_ids';

                    $icon = $lesson_and_quize->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE ? 'dashicons-media-text' : 'dashicons-clock';

                    $post_title = '' == $lesson_and_quize->post_title ? '(no-title)' : $lesson_and_quize->post_title;

                    $lesson_quiz_datas = array(
                        'id' => $lesson_and_quize->ID,
                        'title' => $post_title,
                        'type' => $type,
                        'icon' => $icon,
                        'section_id' => $section_id,
                        'order_number' => (absint(get_post_meta($lesson_and_quize->ID, 'sikshya_order_number', true)))
                    );
                    sikshya_load_admin_template('metabox.course.tabs.curriculum.lesson-quiz-template', $lesson_quiz_datas);
                }

            }

        }

        public function curriculum_tab()
        {
            global $post;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce');


            sikshya_load_admin_template('metabox.course.tabs.curriculum', array());

        }

        public function general_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.general', array());
        }

        public function requirements_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.requirements', array());
        }

        public function outcomes_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.outcomes', array());
        }

        public function pricing_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.pricing', array());
        }

        public function media_tab()
        {
            sikshya_load_admin_template('metabox.course.tabs.media', array());
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
                'requirements' => array(
                    'title' => esc_html__('Requirements', 'sikshya'),
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

            $lesson_quiz_order = isset($_POST['sikshya_lesson_quiz_order']) ? $_POST['sikshya_lesson_quiz_order'] : array();

            $section_ids = array_unique(array_keys($sikshya_course_content));

            if (count($section_ids) > 0) {

                $saved_section_ids = sikshya()->section->save($section_ids, $post_id);
            }

            foreach ($sikshya_course_content as $section_id => $course_content) {
                $section_id = absint($section_id);

                $lesson_ids = isset($course_content['lesson_ids']) ? $course_content['lesson_ids'] : array();

                $quiz_ids = isset($course_content['quiz_ids']) ? $course_content['quiz_ids'] : array();


                if (count($lesson_ids) > 0) {

                    $saved_lesson_ids = sikshya()->lesson->save($lesson_ids, $section_id, $lesson_quiz_order);
                }

                if (count($quiz_ids) > 0) {

                    $saved_quizzes_ids = sikshya()->quiz->save(
                        $quiz_ids, $section_id, SIKSHYA_COURSES_CUSTOM_POST_TYPE, $lesson_quiz_order);
                }

            }


        }


    }
}