<?php

class Sikshya_Core_Course
{
    public function save()
    {

    }

    public function get_course_meta($course_id = null)
    {

        if ($course_id instanceof \WP_Post && !is_null($course_id)) {
            $course_id = $course_id->ID;
        } else {
            global $post;
            $course_id = isset($post->ID) ? $post->ID : 0;
        }

        $data = array(
            'sikshya_course_duration' => get_post_meta($course_id, 'sikshya_course_duration', true),
            'sikshya_course_duration_time' => get_post_meta($course_id, 'sikshya_course_duration_time', true),
            'sikshya_course_level' => get_post_meta($course_id, 'sikshya_course_level', true),
            'sikshya_instructor' => get_post_meta($course_id, 'sikshya_instructor', true),
            'sikshya_course_requirements' => get_post_meta($course_id, 'sikshya_course_requirements', true),
            'sikshya_course_outcomes' => get_post_meta($course_id, 'sikshya_course_outcomes', true),
            'sikshya_course_video_url' => get_post_meta($course_id, 'sikshya_course_video_url', true),
        );

        return $data;

    }

    public function get_all($course_id)
    {
        $data = get_post($course_id);
        if (!empty($data)) {
            $sections = sikshya()->section->get_all_by_course($course_id);

            if (!empty($sections)) {

                foreach ($sections as $index => $section) {

                    $lessons = sikshya()->lesson->get_all_by_section($section->ID);

                    if (!empty($lessons)) {

                        foreach ($lessons as $lesson_index => $lesson) {

                            $quizzes = sikshya()->quiz->get_all_by_lesson($lesson->ID);

                            if (!empty($quizzes)) {

                                foreach ($quizzes as $quiz_index => $question) {

                                    $questions = sikshya()->question->get_all_by_quiz($question->ID);

                                    $quizzes[$quiz_index]->questions = $questions;
                                }
                            }
                            $lessons[$lesson_index]->quizzes = $quizzes;

                        }
                    }
                    $sections[$index]->lessons = $lessons;
                }
            }
            $data->sections = $sections;
        }

        return $data;
    }

    public function get_all_sections($course_id)
    {

        $course_data = $this->get_all($course_id);

        if (isset($course_data->sections)) {

            return $course_data->sections;
        }

        return array();
    }

    public function get_id()
    {
        $id = get_the_ID();

        $post = get_post($id);

        $post_type = isset($post->post_type) ? $post->post_type : '';

        $course_id = 0;
        switch ($post_type) {
            case SIKSHYA_COURSES_CUSTOM_POST_TYPE:
                $course_id = $id;
                break;
            case SIKSHYA_SECTIONS_CUSTOM_POST_TYPE:
                $course_id = get_post_meta($id, 'course_id', true);
                break;
            case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
                $course_id = get_post_meta($id, 'course_id', true);
                break;
            case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
                $course_id = get_post_meta($id, 'course_id', true);
                break;
            case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
                $course_id = get_post_meta($id, 'course_id', true);
                break;

        }
        return $course_id;

    }

    public function get_all_lessons($course_id)
    {
        $course_sections = $this->get_all_sections($course_id);

        $course_lessons = array();

        foreach ($course_sections as $section) {

            if (isset($section->lessons) && count($section->lessons) > 0) {

                foreach ($section->lessons as $lesson) {

                    $course_lessons[$lesson->ID] = $lesson;
                }

            }

        }

        return $course_lessons;
    }

    public function get_all_by_lesson($lesson_id)
    {
        if ($lesson_id instanceof \WP_Post) {
            $lesson_id = $lesson_id->ID;
        }

        $course_ids = get_post_meta($lesson_id, 'course_id');

        if (count($course_ids) < 1) {
            return array();
        }

        $args = array(
            'numberposts' => -1,
            'post__in' => $course_ids,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE,
        );

        $data = get_posts($args);

        return $data;
    }

    public function get_all_by_quiz($quiz_id)
    {
        if ($quiz_id instanceof \WP_Post) {
            $quiz_id = $quiz_id->ID;
        }

        $course_ids = get_post_meta($quiz_id, 'course_id');

        if (count($course_ids) < 1) {
            return array();
        }

        $args = array(
            'numberposts' => -1,
            'post__in' => $course_ids,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE,
        );

        $data = get_posts($args);

        return $data;
    }

    public function has_enrolled($course_id = 0)
    {

        $user_id = get_current_user_id();
        global $wpdb;

        $sql = "SELECT ui.* FROM " . $wpdb->prefix . 'posts p INNER JOIN ' . SIKSHYA_DB_PREFIX . "user_items ui
        ON ui.reference_id=p.ID 
        WHERE 
        p.post_type=%s AND ui.reference_type=%s and ui.user_id=%d and ui.item_id=%d and ui.status in (%s, %s) and ui.item_type=%s
        ";
        $query = $wpdb->prepare($sql,
            SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            $user_id,
            $course_id,
            'enrolled',
            'completed',
            SIKSHYA_COURSES_CUSTOM_POST_TYPE


        );
        $results = $wpdb->get_results($query);

        if (count($results) > 0) {
            return true;
        }
        return false;
    }

    public function enroll($course_id, $user_id)
    {

        if (absint($course_id) > 1 && absint($user_id) > 0) {


            $args = array(
                'post_title' => 'Order on ' . get_the_time('l jS F Y g:i:s A'),
                'post_content' => 'sikshya-booked',
                'post_status' => 'sikshya-booked',
                'post_type' => SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            );
            $id = wp_insert_post($args);


            $item_name = get_the_title($course_id);


            if ($id > 0) {

                global $wpdb;


                $query = $wpdb->prepare("INSERT INTO " . SIKSHYA_DB_PREFIX . 'order_items(item_name, order_id, order_datetime) VALUES(%s, %d, %s)',
                    $item_name,
                    $id,
                    current_time('mysql'));


                $wpdb->query($query);

                $order_item_id = $wpdb->insert_id;


                if ($order_item_id > 0) {

                    sikshya_update_order_item_meta(
                        $order_item_id, '_course_id', $course_id
                    );
                    sikshya_update_order_item_meta(
                        $order_item_id, '_user_id', $user_id
                    );
                    $this->insert_user_table($user_id, $course_id, $id);

                    sikshya()->role->add_student($user_id);

                    return true;
                }

            }

        }
        return false;
    }

    private function insert_user_table($user_id, $course_id, $order_item_id)
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
            $course_id,
            current_time('mysql'),
            current_time('mysql', true),
            current_time('mysql'),
            current_time('mysql', true),
            SIKSHYA_COURSES_CUSTOM_POST_TYPE,
            'enrolled',
            $order_item_id,
            SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            0


        );

        return $wpdb->query($sql);
    }

    public function get_enrolled_course($current_user_id)
    {

        $course_list = array();

        if ($current_user_id != get_current_user_id()) {
            return;
        }

        $enrolled_course = sikshya_get_user_items(array(), array(
            'user_id' => $current_user_id,
            'item_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE,
            'status' => 'enrolled',
            'reference_type' => SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            'parent_id' => 0
        ));

        foreach ($enrolled_course as $item) {
            $list_item['enrolled_date'] = date('F j, Y', strtotime($item->start_time_gmt));
            $list_item['course_title'] = get_the_title($item->item_id);
            $list_item['permalink'] = get_the_permalink($item->item_id);

            $_completed_lessons = sikshya_get_user_items(array(), array(
                'user_id' => $current_user_id,
                'item_type' => SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
                'status' => 'completed',
                'reference_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE,
                'parent_id' => $item->user_item_id
            ));

            $query = new WP_Query(
                array(
                    'meta_key' => 'course_id',                    //(string) - Custom field key.
                    'meta_value' => $item->item_id,
                    'post_type' => SIKSHYA_LESSONS_CUSTOM_POST_TYPE
                )

            );
            $list_item['total_lessons'] = $query->found_posts;
            $list_item['completed_lessons'] = count($_completed_lessons);
            array_push($course_list, $list_item);
        }
        return $course_list;

    }

    public function get_all_by_question($question_id)
    {


        if ($question_id instanceof \WP_Post) {
            $question_id = $question_id->ID;
        }

        $quiz_ids = get_post_meta($question_id, 'quiz_id');

        if (count($quiz_ids) < 1) {
            return array();
        }

        $course_ids = array();

        foreach ($quiz_ids as $quiz_id) {

            $course_id = (int)get_post_meta($question_id, 'course_id', true);

            if ($course_id > 0) {
                array_push($course_ids, $course_id);
            }
        }


        $args = array(
            'numberposts' => -1,
            'post__in' => $course_ids,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE,
        );

        $data = get_posts($args);

        return $data;

    }
}