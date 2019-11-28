<?php
defined('ABSPATH') || exit;

class Sikshya_Ajax
{

    private function admin_ajax_actions()
    {
        $actions = array(
            'remove_section_from_course',
            'remove_lesson_from_course',
            'remove_quiz_from_course',
            'remove_question_from_course'
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

    public function remove_section_from_course()
    {
        $status = sikshya()->helper->validate_nonce();

        if (!$status) {
            wp_send_json_error($this->ajax_error());
        }

        $section_id = isset($_POST['id']) ? $_POST['id'] : 0;

        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;

        if ($section_id < 1 || $course_id < 1) {
            wp_send_json_error($this->ajax_error());
        }

        $status = sikshya()->section->remove_from_course($section_id, $course_id);

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


}

new Sikshya_Ajax();