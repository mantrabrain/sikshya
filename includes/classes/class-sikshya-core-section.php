<?php

class Sikshya_Core_Section
{
    public function save($sections, $course_id = 0)
    {
        $section_ids = array();

        $menu_order = 100;

        foreach ($sections as $section_id => $section_content) {

            $section_title = isset($section_content['section_title']) ? sanitize_text_field($section_content['section_title']) : '';
            $args = array(
                'post_title' => $section_title,
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => SIKSHYA_SECTIONS_CUSTOM_POST_TYPE,
                'menu_order' => $menu_order,
            );
            $updated_section_id = 0;

            if (!sikshya_is_new_post($section_id)) {

                $args['ID'] = $section_id;

                $updated_section_id = wp_update_post($args);

            }

            $updated_section_id = wp_insert_post($args);

            if (!is_wp_error($updated_section_id) && $updated_section_id > 0) {
                 if ($course_id > 0) {
                    update_post_meta($updated_section_id, 'course_id', $course_id);
                }
                $section_ids[$section_id] = $updated_section_id;

            }


            $menu_order++;
        }

        return $section_ids;

    }

    public function update_meta($sections, $section_id)
    {
        foreach ($sections as $section_id_ => $section_content) {

        }

        return $section_id;
    }


    function render_tmpl($id, $title, $description = '', $image = '', $lessonsHtml = '')
    {
        ob_start();

        include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/section.php';

        return ob_get_clean();
    }


    function get_all_by_course($course_id)
    {
        if ($course_id instanceof \WP_Post) {
            $course_id = $course_id->ID;
        }

        $args = array(
            'numberposts' => -1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_SECTIONS_CUSTOM_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => 'course_id',
                    'value' => (int)$course_id
                )
            )
        );
        $data = get_posts($args);

        return $data;
    }

    public function get_child_count_text($section_id)
    {
        if (sikshya_is_new_post($section_id)) {
            return '';
        }
        $section_id = absint($section_id);
        if ($section_id < 1) {
            return '';
        }
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) as total, p.post_type           
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm
ON p.ID=pm.post_id
WHERE pm.meta_key = 'section_id' 
AND pm.meta_value = %d  and p.post_status='publish'
GROUP BY p.post_type having p.post_type in (%s,%s,%s) ORDER BY FIELD (p.post_type, %s, %s, %s)",
            $section_id,
            SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
            SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
            SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
            SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
            SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
            SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE
        );

        $results = $wpdb->get_results($sql);

        $count_string = '';

        foreach ($results as $result) {

            $total = isset($result->total) ? $result->total : 0;

            $post_type = isset($result->post_type) ? $result->post_type : '';

            switch ($post_type) {

                case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
                    $count_string .= $total . ' Lesson';
                    break;
                case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:
                    $count_string .= ', ' . $total . ' Quiz';
                    break;
                case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:
                    $count_string .= ', ' . $total . ' Question';
                    break;
            }

        }


        echo !empty($count_string) ? '( ' . $count_string . ' )' : '';
    }

    public function remove_from_course($section_id = 0, $course_id = 0)
    {
        if ($section_id < 1) {
            return false;
        }
        return delete_post_meta($section_id, 'course_id', $course_id);
    }

    public function get_all_by_lesson_id($lesson_id = 0)
    {
        if ($lesson_id < 1) {
            return array();
        }

        $section_id = get_post_meta($lesson_id, 'section_id', true);

        $course_id = get_post_meta($section_id, 'course_id', true);

        $data = sikshya()->course->get_all_sections($course_id);

        return $data;
    }


}