<?php
defined('ABSPATH') || exit;

class Sikshya_Ajax
{

    private function admin_ajax_actions()
    {
        $actions = array(
            'remove_lesson_from_course',
            'remove_quiz_from_course',
            'remove_question_from_course',
            'load_section_settings',
            'load_lesson_form',
            'load_quiz_form',
            'add_section',
            'add_lesson',
            'add_quiz',
            // Remove
            'remove_lesson_quiz_from_section',
            'remove_section_from_course'
        );

        return $actions;

    }

    private function public_ajax_actions()
    {
        $actions = array();
        return $actions;
    }

    private function ajax_error()
    {
        return array('message' => __('Something wrong, please try again.', 'sikshya'), 'status' => false);
    }

    public function __construct()
    {
        $admin_actions = $this->admin_ajax_actions();
        $public_ajax_actions = $this->public_ajax_actions();
        $all_ajax_actions = array_unique(array_merge($admin_actions, $public_ajax_actions));

        foreach ($all_ajax_actions as $action) {
            add_action('wp_ajax_sikshya_' . $action, array($this, $action));
            if (in_array($action, $public_ajax_actions)) {
                add_action('wp_ajax_nopriv_sikshya_' . $action, array($this, $action));
            }

        }


    }

    public function remove_lesson_from_course()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : 0;

        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;

        $lesson_id = isset($_POST['lesson_id']) ? $_POST['lesson_id'] : 0;

        if ($section_id < 1 || $course_id < 1 || $lesson_id < 1) {
            wp_send_json_error($this->ajax_error());
        }

        sikshya()->lesson->remove_from_course($lesson_id, $course_id);

        sikshya()->lesson->remove_from_section($lesson_id, $section_id);

        $message = __('Successfully removed from course', 'sikshya');

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        if ($status) {
            wp_send_json_success(
                array('message' => $message)
            );
        }

    }

    public function remove_quiz_from_course()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : 0;

        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;

        $lesson_id = isset($_POST['lesson_id']) ? $_POST['lesson_id'] : 0;

        $quiz_id = isset($_POST['quiz_id']) ? $_POST['quiz_id'] : 0;

        if ($section_id < 1 || $course_id < 1 || $lesson_id < 1 || $quiz_id < 1) {
            wp_send_json_error($this->ajax_error());
        }

        sikshya()->quiz->remove_from_course($quiz_id, $course_id);

        sikshya()->quiz->remove_from_section($quiz_id, $section_id);

        sikshya()->quiz->remove_from_lesson($quiz_id, $lesson_id);

        $message = __('Successfully removed from course', 'sikshya');

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        if ($status) {
            wp_send_json_success(
                array('message' => $message)
            );
        }

    }

    public function remove_question_from_course()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : 0;

        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;

        $lesson_id = isset($_POST['lesson_id']) ? $_POST['lesson_id'] : 0;

        $quiz_id = isset($_POST['quiz_id']) ? $_POST['quiz_id'] : 0;

        $question_id = isset($_POST['question_id']) ? $_POST['question_id'] : 0;

        if ($section_id < 1 || $course_id < 1 || $lesson_id < 1 || $quiz_id < 1 || $question_id < 1) {
            wp_send_json_error($this->ajax_error());
        }

        if (!sikshya_is_new_post($quiz_id)) {
            sikshya()->question->remove_from_quiz($question_id, $quiz_id);
        }

        if (!sikshya_is_new_post($lesson_id)) {
            sikshya()->question->remove_from_lesson($question_id, $lesson_id);
        }

        if (!sikshya_is_new_post($section_id)) {
            sikshya()->question->remove_from_section($question_id, $section_id);
        }

        if (!sikshya_is_new_post($section_id)) {
            sikshya()->question->remove_from_course($question_id, $course_id);
        }


        $message = __('Successfully removed from course', 'sikshya');

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        if ($status) {
            wp_send_json_success(
                array('message' => $message)
            );
        }

    }

    public function load_section_settings()
    {

        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $sections = array();
        sikshya_load_admin_template('metabox.course.tabs.curriculum.section-form', array('sections' => $sections));

        exit;

    }

    public function load_lesson_form()
    {

        $status = sikshya()->helper->validate_nonce();

        $section_id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;

        if (!$status || $section_id < 1) {

            wp_send_json_error($this->ajax_error());
        }

        sikshya_load_admin_template('metabox.course.tabs.curriculum.lesson-form', array('section_id' => $section_id));

        exit;

    }

    public function load_quiz_form()
    {

        $status = sikshya()->helper->validate_nonce();

        $section_id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;


        if (!$status || $section_id < 1) {
            wp_send_json_error($this->ajax_error());
        }

        sikshya_load_admin_template('metabox.course.tabs.curriculum.quiz-form', array('section_id' => $section_id));

        exit;

    }

    public function add_section()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        $section_title = isset($_POST['section_title']) ? sanitize_text_field($_POST['section_title']) : '';

        $section_data = sikshya()->section->add_section($section_title);

        $section_id = isset($section_data['section_id']) ? $section_data['section_id'] : '';

        if ('' == $section_id) {
            wp_send_json_error();
        }

        sikshya_load_admin_template('metabox.course.tabs.curriculum.section-template', $section_data);

        exit;
    }

    public function add_lesson()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $lesson_title = isset($_POST['lesson_title']) ? sanitize_text_field($_POST['lesson_title']) : '';

        $section_id = isset($_POST['section_id']) ? absint($_POST['section_id']) : '';

        $lesson_data = sikshya()->lesson->add_lesson($lesson_title);

        $lesson_id = isset($lesson_data['id']) ? $lesson_data['id'] : '';

        if ('' == $lesson_id) {
            wp_send_json_error();
        }
        $lesson_data['icon'] = 'dashicons-media-text';
        $lesson_data['section_id'] = $section_id;
        $lesson_data['order_number'] = 0;


        sikshya_load_admin_template('metabox.course.tabs.curriculum.lesson-quiz-template', $lesson_data);

        exit;
    }

    public function add_quiz()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        $quiz_title = isset($_POST['quiz_title']) ? sanitize_text_field($_POST['quiz_title']) : '';
        $section_id = isset($_POST['section_id']) ? absint($_POST['section_id']) : '';

        $quiz_data = sikshya()->quiz->add_quiz($quiz_title);

        $quiz_id = isset($quiz_data['id']) ? $quiz_data['id'] : '';

        if ('' == $quiz_id) {
            wp_send_json_error();
        }

        $quiz_data['icon'] = 'dashicons-clock';
        $quiz_data['section_id'] = $section_id;
        $quiz_data['order_number'] = 0;
        sikshya_load_admin_template('metabox.course.tabs.curriculum.lesson-quiz-template', $quiz_data);

        exit;
    }

    public function remove_lesson_quiz_from_section()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : 0;

        $post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;

        if ($section_id < 1 || $post_id < 1) {
            wp_send_json_error($this->ajax_error());
        }
        sikshya_remove_post_meta($post_id, 'section_id', $section_id);

        $message = __('Successfully removed from course', 'sikshya');

        if ($status) {
            wp_send_json_success(
                array('message' => $message)
            );
        }

    }

    public function remove_section_from_course()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }
        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : 0;

        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;

        if ($section_id < 1 || $course_id < 1) {
            wp_send_json_error($this->ajax_error());
        }
        sikshya()->section->remove_from_course($section_id, $course_id);

        $message = __('Successfully removed from course', 'sikshya');

        if ($status) {
            wp_send_json_success(
                array('message' => $message)
            );
        }

    }


}

new Sikshya_Ajax();