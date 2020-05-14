<?php

class Sikshya_Core_Question
{
    public function save($questions = array(), $saved_section_ids = array(), $saved_lesson_ids = array(), $saved_quiz_ids = array(), $course_id = 0)
    {
        $menu_order = 100;

        $question_ids = array();

        $saved_section_ids_keys = array_keys($saved_section_ids);

        $saved_lesson_ids_keys = array_keys($saved_lesson_ids);

        $saved_quiz_ids_keys = array_keys($saved_quiz_ids);


        foreach ($questions as $raw_section_id => $raw_lesson_content) {

            if (in_array($raw_section_id, $saved_section_ids_keys)) {

                foreach ($raw_lesson_content as $raw_lesson_id => $raw_lesson_array) {

                    if (in_array($raw_lesson_id, $saved_lesson_ids_keys)) {

                        foreach ($raw_lesson_array as $raw_quiz_id => $raw_question_array) {

                            if (in_array($raw_quiz_id, $saved_quiz_ids_keys)) {

                                foreach ($raw_question_array as $raw_question_id => $question_content) {


                                    $title = isset($question_content['title']) ? sanitize_text_field($question_content['title']) : '';

                                    $answers = isset($question_content['answers']) ? ($question_content['answers']) : array();

                                    foreach ($answers as $answer_key => $answer_content) {

                                        $valid_answer_content['value'] = isset($answer_content['value']) ? sanitize_text_field($answer_content['value']) : '';

                                        $valid_answer_content['image'] = isset($answer_content['image']) ? esc_url_raw($answer_content['image']) : '';

                                        $answers[$answer_key] = $valid_answer_content;
                                    }

                                    $answers_correct = isset($question_content['answers_correct']) ? ($question_content['answers_correct']) : array();

                                    $args = array(
                                        'post_title' => $title,
                                        'post_content' => '',
                                        'post_status' => 'publish',
                                        'post_type' => SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
                                        'menu_order' => $menu_order,
                                    );
                                    $updated_question_id = 0;

                                    if (!sikshya_is_new_post($raw_question_id)) {

                                        $args['ID'] = $raw_question_id;

                                        $updated_question_id = wp_update_post($args);

                                    }
                                    $updated_question_id = wp_insert_post($args);

                                    if (!is_wp_error($updated_question_id) && $updated_question_id > 0) {
                                        update_post_meta($updated_question_id, 'type', $question_content['type']);
                                        update_post_meta($updated_question_id, 'section_id', $saved_section_ids[$raw_section_id]);
                                        update_post_meta($updated_question_id, 'lesson_id', $saved_lesson_ids[$raw_lesson_id]);
                                        update_post_meta($updated_question_id, 'quiz_id', $saved_quiz_ids[$raw_quiz_id]);
                                        update_post_meta($updated_question_id, 'answers', $answers);
                                        update_post_meta($updated_question_id, 'correct_answers', $answers_correct);

                                        if ($course_id > 0) {
                                            update_post_meta($updated_question_id, 'course_id', $course_id);
                                        }
                                        $question_ids[$raw_question_id] = $updated_question_id;

                                    }

                                    $menu_order++;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $question_ids;
    }

    public function get_question_params($question_id)
    {
        $params['type'] = get_post_meta($question_id, 'type', true);
        $params['section_id'] = get_post_meta($question_id, 'section_id', true);
        $params['lesson_id'] = get_post_meta($question_id, 'lesson_id', true);
        $params['quiz_id'] = get_post_meta($question_id, 'quiz_id', true);
        $params['answers'] = get_post_meta($question_id, 'answers', true);
        $params['correct_answers'] = get_post_meta($question_id, 'correct_answers', true);
        $params['course_id'] = get_post_meta($question_id, 'course_id', true);

        return $params;
    }

    public function get_all_by_quiz($quiz_id)
    {
        if ($quiz_id instanceof \WP_Post) {
            $quiz_id = $quiz_id->ID;
        }

        $args = array(
            'numberposts' => -1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'desc',
            'post_type' => SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => 'quiz_id',
                    'value' => (int)$quiz_id
                )
            )
        );
        $data = get_posts($args);

        return $data;
    }

    function render_tmpl($id, $name, $data, $answersHtml = '')
    {

        $title = isset($data->post_title) ? $data->post_title : __('New Question Title', 'sikshya');

        $type = isset($data->ID) ? get_post_meta($data->ID, 'type', true) : 'text';


        $allowedTypes = array(
            'single' => __('One answer', 'sikshya'),
            'single_image' => __('One answer with image', 'sikshya'),
            'multi' => __('Multiple answers', 'sikshya'),
            'multi_image' => __('Multiple answers with image', 'sikshya')
        );


        ob_start();

        include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/question.php';

        return ob_get_clean();
    }

    public function render_answer_tmpl($id, $answer_id, $name, $data, $name_correct, $data_correct, $question_type)
    {
        $value = empty($data['value']) ? '' : $data['value'];
        $image = empty($data['image']) ? '' : $data['image'];
        ob_start();

        include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/answer.php';

        return ob_get_clean();
    }

    public function remove_from_course($question_id, $course_id)
    {
        if ($question_id < 1 || $course_id < 1) {
            return false;
        }

        return delete_post_meta($question_id, 'course_id', $course_id);
    }

    public function remove_from_section($question_id = 0, $section_id = 0)
    {

        if ($question_id < 1 || $section_id < 1) {
            return false;
        }

        return delete_post_meta($question_id, 'section_id', $section_id);
    }


    public function remove_from_quiz($question_id = 0, $quiz_id = 0)
    {
        if ($question_id < 1 || $quiz_id < 1) {
            return false;
        }

        return delete_post_meta($question_id, 'quiz_id', $quiz_id);
    }

    public function remove_from_lesson($question_id = 0, $lesson_id = 0)
    {
        if ($question_id < 1 || $lesson_id < 1) {
            return false;
        }

        return delete_post_meta($question_id, 'lesson_id', $lesson_id);
    }

    public function first_question_permalink($course_id, $quiz_id)
    {
        $args = array(
            'numberposts' => 1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => 'quiz_id',
                    'value' => (int)$quiz_id
                )
            )
        );
        $data = get_posts($args);

        $question_id = isset($data[0]) ? $data[0]->ID : $quiz_id;

        return get_permalink($question_id);

    }

    public function has_question($quiz_id)
    {
        $args = array(
            'numberposts' => 1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => 'quiz_id',
                    'value' => (int)$quiz_id
                )
            )
        );
        $data = get_posts($args);

        return isset($data[0]) ? true : false;

    }

    public function get_prev_question($id)
    {
        $quiz_id = get_post_meta($id, 'quiz_id', true);
        $prev = false;
        if (($questions = $this->get_all_by_quiz($quiz_id))) {
            $question_ids = wp_list_pluck($questions, 'ID');
            if (0 < ($at = array_search($id, $question_ids))) {
                $prev = $question_ids[$at - 1];
            }
        }

        return apply_filters('sikshya_quiz_prev_question_id', $prev, $id);
    }


    public function get_next_question($id)
    {
        $quiz_id = get_post_meta($id, 'quiz_id', true);
        $next = false;
        if (($questions = $this->get_all_by_quiz($quiz_id))) {
            $question_ids = wp_list_pluck($questions, 'ID');
            if (sizeof($question_ids) - 1 > ($at = array_search($id, $question_ids))) {
                $next = $question_ids[$at + 1];
            }
        }

        return apply_filters('sikshya_quiz_next_question_id', $next, $id);
    }

    public function question_block($question_id, $answer)
    {
        $quiz_params = sikshya()->question->get_question_params($question_id);

        $is_correct_answer = false;

        switch (@$quiz_params['type']) {
            case "multi":
                $answer_from_form_array = (json_decode(urldecode($answer)));
                $is_correct_answer = count($answer_from_form_array) == count($quiz_params['correct_answers']) && sort($answer_from_form_array) == sort($quiz_params['correct_answers']) ? true : false;
                break;
            case "single":
                $answer_from_form_array = (json_decode(urldecode($answer)));
                $is_correct_answer = count($answer_from_form_array) == 1 && count($answer_from_form_array) == count($quiz_params['correct_answers']) && sort($answer_from_form_array) == sort($quiz_params['correct_answers']) ? true : false;
                break;
        }

        $question_block = array(
            'is_correct' => $is_correct_answer,
            'mark' => 0,
            'type' => $quiz_params['type'],
            'is_answered' => empty($answer) ? false : true
        );
        return $question_block;
    }

    public function update_answer($user_id, $quiz_id, $course_id, $question_id, $answer, $is_completed = false)
    {
        $question_block = $this->question_block($question_id, $answer);

        $results = sikshya_get_user_items(array(
            'user_item_id'
        ), array(
                'item_id' => absint($quiz_id),
                'item_type' => SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                'status' => 'started',
                'reference_id' => absint($course_id),
                'user_id' => $user_id
            )
        );
        $user_item_id = isset($results[0]) ? absint($results[0]->user_item_id) : 0;


        if ($user_item_id < 1) {
            return;
        }

        $_quiz_question_result = sikshya_get_user_item_meta($user_item_id, '_quiz_question_result');

        $total_questions = $this->get_all_by_quiz($quiz_id);

        $_quiz_question_result['questions'][$question_id] = $question_block;

        $_quiz_question_result['total_questions'] = count($total_questions);

        $total_answered = 0;

        $total_correct = 0;

        $total_wrong = 0;

        foreach ($_quiz_question_result['questions'] as $question_id => $params) {

            if ($params['is_answered']) {
                $total_answered++;
            }

            if (!$params['is_correct']) {
                $total_wrong++;
            }

            if ($params['is_correct']) {
                $total_correct++;
            }

            $_quiz_question_result['answered_questions'] = $total_answered;

            $_quiz_question_result['wrong_questions'] = $total_wrong;

            $_quiz_question_result['correct_questions'] = $total_correct;

        }
        $_quiz_question_result['skipped_questions'] = count($total_questions) - $total_answered;

        $_quiz_question_result['status'] = $is_completed ? 'compleed' : 'on-progress';


        sikshya_update_user_item_meta($user_item_id, '_quiz_question_result', $_quiz_question_result);

        if ($is_completed) {
            sikshya_update_user_items(
                array('status' => 'completed'),
                array(
                    'user_item_id' => absint($user_item_id),
                    'user_id' => absint($user_id),
                    'item_id' => absint($quiz_id),
                    'reference_id' => absint($course_id),
                    'item_type' => SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                    'status' => 'started'
                )
            );
        }

    }


    public function get_question_metas($question_id = 0)
    {
        $metas['type'] = get_post_meta($question_id, 'type', true);
        $metas['section_id'] = get_post_meta($question_id, 'section_id', true);
        $metas['lesson_id'] = get_post_meta($question_id, 'lesson_id', true);
        $metas['quiz_id'] = get_post_meta($question_id, 'quiz_id', true);
        $metas['answers'] = get_post_meta($question_id, 'answers', true);
        $metas['correct_answers'] = get_post_meta($question_id, 'correct_answers', true);
        $metas['question_id'] = $question_id;
        return $metas;
    }

    public function get_answer_args($args = array())
    {
        $array = array(
            'id' => '{%question_id%}_{%answer_id%}',
            'name' => 'quiz_question_answer[{%question_id%}][answers][{%answer_id%}]',
            'answer_value' => '{%answer_value%}',
            'answer_image' => '{%answer_image%}',
            'answer_id' => '{%answer_id%}',
            'correct_answer_name' => 'quiz_question_answer[{%question_id%}][answers_correct][]',
            'correct_answers' => array(),
            'question_type' => 'text'
        );
        foreach ($args as $array_key => $array_value) {

            foreach ($array as $key => $val) {

                $array[$key] = str_replace("{%" . $array_key . "%}", $array_value, $val);
            }

        }
        return $array;
    }

    public function load($question_id = 0, $load_template = true)
    {
        $metas = absint($question_id) > 0 ? sikshya()->question->get_question_metas($question_id) : array();

        if (!isset($metas['quiz_id'])) {

            $name = "quiz_question_answer[{%question_id%}][answers][{%answer_id%}]";
            $typename = "quiz_question_answer[{%question_id%}]";
        } else {
            $name = "quiz_question_answer[" . $metas['question_id'] . "][answers][{%answer_id%}]";
            $typename = "quiz_question_answer[" . $metas['question_id'] . "]";

        }


        $params = array(
            'question_id' => $question_id,
            'type' => isset($metas['type']) ? $metas['type'] : 'single',
            'metas' => $metas,
            'name' => $name,
            'typename' => $typename,
            'lesson_id' => isset($metas['lesson_id']) ? $metas['lesson_id'] : '',
            'section_id' => isset($metas['section_id']) ? $metas['section_id'] : '',
            'quiz_id' => isset($metas['quiz_id']) ? $metas['quiz_id'] : ''
        );


        if ($load_template) {
            sikshya_load_admin_template('metabox.answer.template', $params, true);
        }

        sikshya_load_admin_template('metabox.answer.main', $params);
    }

    public function load_answer_dynamic($question_id = 0)
    {
        $arg = absint($question_id) > 0 ? sikshya()->question->get_question_metas($question_id) : array();

        $answers = isset($arg['answers']) && is_array($arg['answers']) ? $arg['answers'] : array();

        foreach ($answers as $answer_id => $answer_array) {


            $params = sikshya()->question->get_answer_args(

                array(
                    'question_id' => $arg['question_id'],
                    'answer_id' => $answer_id,
                    'section_id' => $arg['section_id'],
                    'lesson_id' => $arg['lesson_id'],
                    'quiz_id' => $arg['quiz_id'],
                    'answer_value' => isset($answer_array['value']) ? $answer_array['value'] : '',
                    'answer_image' => isset($answer_array['image']) ? $answer_array['image'] : '',
                )
            );
            $params['correct_answers'] = isset($arg['correct_answers']) ? $arg['correct_answers'] : array();
            $params['question_type'] = !isset($arg['type']) ? 'single' : $arg['type'];


            sikshya_load_admin_template('metabox.answer.template-dynamic', $params);
        }


    }

    public function update_answer_meta($metas = array(), $question_id = 0)
    {
        if ($question_id < 0) {
            return false;
        }
        $type = isset($metas['type']) ? sanitize_text_field($metas['type']) : 'single';

        $answers = isset($metas['answers']) ? $metas['answers'] : array();

        foreach ($answers as $answer_key => $answer_content) {

            $valid_answer_content['value'] = isset($answer_content['value']) ? sanitize_text_field($answer_content['value']) : '';

            $valid_answer_content['image'] = isset($answer_content['image']) ? esc_url_raw($answer_content['image']) : '';

            $answers[$answer_key] = $valid_answer_content;
        }

        $answers_correct = isset($metas['answers_correct']) ? $metas['answers_correct'] : array();

        update_post_meta($question_id, 'type', $type);

        update_post_meta($question_id, 'answers', $answers);

        update_post_meta($question_id, 'correct_answers', $answers_correct);

        return true;


    }

    public function count_by_quiz($quiz_id)
    {
        return count($this->get_all_by_quiz($quiz_id));
    }
}