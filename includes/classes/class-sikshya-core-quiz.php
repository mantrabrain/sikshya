<?php

class Sikshya_Core_Quiz
{
    public function save($quizzes = array(), $saved_section_ids = array(), $saved_lesson_ids = array(), $course_id = 0)
    {
        $menu_order = 100;

        $quiz_ids = array();

        $saved_section_ids_keys = array_keys($saved_section_ids);

        $saved_lesson_ids_keys = array_keys($saved_lesson_ids);

        foreach ($quizzes as $raw_section_id => $raw_content) {

            if (in_array($raw_section_id, $saved_section_ids_keys)) {

                foreach ($raw_content as $raw_lesson_id => $quiz_array) {

                    if (in_array($raw_lesson_id, $saved_lesson_ids_keys)) {

                        foreach ($quiz_array as $raw_quiz_id => $quiz_content) {


                            $title = isset($quiz_content['title']) ? sanitize_text_field($quiz_content['title']) : '';
                            $content = isset($quiz_content['content']) ? ($quiz_content['content']) : '';
                            $args = array(
                                'post_title' => $title,
                                'post_content' => $content,
                                'post_status' => 'publish',
                                'post_type' => SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                                'menu_order' => $menu_order,
                            );
                            $updated_quiz_id = 0;

                            if (!sikshya_is_new_post($raw_quiz_id)) {

                                $args['ID'] = $raw_quiz_id;

                                $updated_quiz_id = wp_update_post($args);

                            }

                            $updated_quiz_id = wp_insert_post($args);

                            if (!is_wp_error($updated_quiz_id) && $updated_quiz_id > 0) {

                                update_post_meta($updated_quiz_id, 'section_id', $saved_section_ids[$raw_section_id]);
                                update_post_meta($updated_quiz_id, 'lesson_id', $saved_lesson_ids[$raw_lesson_id]);

                                if ($course_id > 0) {
                                    update_post_meta($updated_quiz_id, 'course_id', $course_id);
                                }
                                $quiz_ids[$raw_quiz_id] = $updated_quiz_id;

                            }

                            $menu_order++;
                        }
                    }
                }
            }
        }

        return $quiz_ids;
    }

    function render_tmpl($id, $name, $quiz_obj, $content, $questionsHtml = '')
    {
        $title = isset($quiz_obj->post_title) ? $quiz_obj->post_title : '';
        $allowedRetakes = array(
            'unlimited' => __('As many times as the user wants', 'sikshya'),
            'once_per_day' => __('Once per day', 'sikshya'),
            'once_per_month' => __('Once per month', 'sikshya')
        );
        ob_start();

        include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/quiz.php';

        return ob_get_clean();
    }

    public function get_all_by_lesson($lesson_id)
    {
        if ($lesson_id instanceof \WP_Post) {
            $lesson_id = $lesson_id->ID;
        }

        $args = array(
            'numberposts' => -1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => 'lesson_id',
                    'value' => (int)$lesson_id
                )
            )
        );
        $data = get_posts($args);

        return $data;
    }

    public function get_child_count_text($quiz_id)
    {
        if (sikshya_is_new_post($quiz_id)) {
            return '';
        }
        $quiz_id = absint($quiz_id);
        if ($quiz_id < 1) {
            return '';
        }
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) as total, p.post_type           
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm
ON p.ID=pm.post_id
WHERE pm.meta_key = 'quiz_id' 
AND pm.meta_value = %d  and p.post_status='publish'
GROUP BY p.post_type having p.post_type in (%s)",
            $quiz_id,
            SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE
        );

        $results = $wpdb->get_results($sql);

        $count_string = '';

        foreach ($results as $result) {

            $total = isset($result->total) ? $result->total : 0;

            $post_type = isset($result->post_type) ? $result->post_type : '';

            switch ($post_type) {

                case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
                    $count_string .= $total . ' Question';
                    break;
            }

        }


        echo !empty($count_string) ? '( ' . $count_string . ' )' : '';
    }

    public function remove_from_section($quiz_id = 0, $section_id = 0)
    {

        if ($section_id < 1 || $quiz_id < 1) {
            return false;
        }

        return delete_post_meta($quiz_id, 'section_id', $section_id);
    }

    public function remove_from_course($quiz_id = 0, $course_id = 0)
    {
        if ($course_id < 1 || $quiz_id < 1) {
            return false;
        }

        return delete_post_meta($quiz_id, 'course_id', $course_id);
    }

    public function remove_from_lesson($quiz_id, $lesson_id)
    {
        if ($lesson_id < 1 || $quiz_id < 1) {
            return false;
        }

        return delete_post_meta($quiz_id, 'lesson_id', $lesson_id);
    }

    public function get($id)
    {
        return get_post($id);
    }


    public function start_quiz($user_id = 0, $quiz_id = 0, $course_id = 0, $order_item_id = 0)
    {
        $user_id = $user_id < 1 ? get_current_user_id() : $user_id;

        if ($user_id < 1 || $order_item_id < 1 || $course_id < 1) {

            return false;
        }
        global $wpdb;
        $sql = $wpdb->prepare(
            "INSERT INTO " . SIKSHYA_DB_PREFIX . "user_items 
            (user_id, item_id, start_time, start_time_gmt, end_time,end_time_gmt, item_type, status,reference_id,reference_type,parent_id) 
            values
            (%d, %d, %s, %s, %s, %s, %s, %s, %d, %s, %d)",
            $user_id,
            $quiz_id,
            current_time('mysql'),
            current_time('mysql', true),
            current_time('mysql'),
            current_time('mysql', true),
            SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
            'started',
            $course_id,
            SIKSHYA_COURSES_CUSTOM_POST_TYPE,
            $order_item_id


        );

        return $wpdb->query($sql);
    }

    public function is_started($user_id, $quiz_id)
    {
        $user_id = $user_id < 1 ? get_current_user_id() : $user_id;

        if ($user_id < 1 || $quiz_id < 1) {

            return false;
        }

        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM " . SIKSHYA_DB_PREFIX . "user_items WHERE user_id= %d and item_id=%d and status=%s and item_type=%s",
            $user_id,
            $quiz_id,
            'started',
            SIKSHYA_QUIZZES_CUSTOM_POST_TYPE
        );

        $results = $wpdb->get_results($sql);

        return count($results) > 0 ? true : false;
    }

    public function get_permalink($quiz_id)
    {

        if ($this->is_started(0, $quiz_id)) {

            $course_id = get_post_meta($quiz_id, 'course_id', true);

            return sikshya()->question->first_question_permalink($course_id, $quiz_id);
        }
        return get_permalink($quiz_id);
    }

    public function is_completed($user_id, $quiz_id, $course_id)
    {
        $results = sikshya_get_user_items(array(
            'user_item_id',
            'status'
        ), array(
                'item_id' => absint($quiz_id),
                'item_type' => SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                'status' => 'completed',
                'reference_id' => absint($course_id),
                'user_id' => absint($user_id)
            )
        );

        $user_item_id = isset($results[0]) ? absint($results[0]->user_item_id) : 0;
        $status = isset($results[0]) ? $results[0]->status : '';
        if ($user_item_id > 0 && $status == 'completed') {
            return true;
        }
        return false;
    }

    public function load($quiz_id)
    {


        $questions = sikshya()->question->get_all_by_quiz($quiz_id);
        //sikshya_load_admin_template('metabox.question.template', $params);
        $params = array(
            'quiz_id' => $quiz_id,
            'question_id' => 0,
            'questions' => $questions
        );

        sikshya_load_admin_template('metabox.question.template', array(), true);

        sikshya_load_admin_template('metabox.answer.template', array(), true);

        sikshya_load_admin_template('metabox.question.main', $params);

    }

    public function update_quiz_question($quiz_questions = array(), $quiz_question_answer = array(), $quiz_id = 0)
    {

        foreach ($quiz_questions as $question_id => $question_content) {

            $title = isset($question_content['title']) ? sanitize_text_field($question_content['title']) : '';

            $post_data = array(
                'ID' => $question_id,
                'post_title' => $title,
                'post_type' => SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
                'post_status' => 'publish'
            );;
            if (!sikshya_is_new_post($question_id)) {

                $updated_question_id = wp_update_post($post_data);

            } else {

                $updated_question_id = wp_insert_post($post_data);
            }

            $quiz_question_ans = isset($quiz_question_answer[$question_id]) ? $quiz_question_answer[$question_id] : array();

            sikshya()->question->update_answer_meta($quiz_question_ans, $updated_question_id);

            update_post_meta($updated_question_id, 'quiz_id', $quiz_id);
        }
    }


}