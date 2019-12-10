<?php
/**
 * generate rules for show courses on login page
 *
 * @return array
 *
 * @since 1.0.0
 *
 */
function sikshya_account_page_nav_items()
{
    $items_array = array(

        'dashboard' => array(
            'title' => __('Dashboard', 'sikshya'),
            //'cap' => ''
            'icon' => 'fa fa-home'
        ),
        'profile' => array(
            'title' => __('Profile', 'sikshya'),
            //'cap' => ''
            'icon' => 'fa fa-user-o'
        ),
        'enrolled-courses' => array(
            'title' => __('Enrolled Courses', 'sikshya'),
            //'cap' => ''
            'icon' => 'fa fa-book'
        ),
        'update-profile' => array(
            'title' => __('Update Profile', 'sikshya'),
            //'cap' => ''
            'icon' => 'fa fa-pencil'
        ),
        'logout' => array(
            'title' => __('Logout', 'sikshya'),
            //'cap' => ''
            'icon' => 'fa fa-sign-out'
        )

    );

    foreach ($items_array as $key => $nav_item) {
        if (is_array($nav_item)) {

            if (isset($nav_item['cap']) && !current_user_can($nav_item['cap'])) {
                unset($items_array[$key]);
            } else if (!is_user_logged_in()) {
                unset($items_array[$key]);
            }
        }
    }

    return apply_filters('sikshya_account_page_nav_items', $items_array);
}

/**
 * get CPT meta data
 *
 * @param  integer||NULL  $id
 * @param  string $title
 * @param  string $default
 * @param  string $format
 * @param  boolean $br
 * @param  boolean $page
 * @param  boolean $mail
 *
 * @return string
 *
 * @since 1.0.0
 *
 */
function sikshya_get_meta_data($id, $title, $default = '', $format = '%s', $br = false, $page = false, $mail = false)
{
    $data = get_post_meta($id, $title, true);
    if (empty($data))
        $data = $default;

    $value = $br ? str_replace("\r", '</br>', $data) : $data;

    if ($value != null)
        if ($page)
            $value = sprintf($format, get_permalink($value), get_the_title($value));
        else
            $value = $mail ? sprintf($format, $value, $value) : sprintf($format, $value);

    return $value;
}


/**
 * return option value in format
 *
 * @param  string $option
 * @param  string $format
 * @param  string $default
 *
 * @return string
 *
 * @since 1.0.0
 *
 */
function sikshya_get_formatted_option($option, $format = '%s', $default = '')
{
    $value = sikshya_get_option($option);
    if (empty($value))
        return $default;

    return sprintf($format, $value);
}


function sikshya_render_editor($content, $name, $id)
{
    ob_start();

    include SIKSHYA_PATH . '/includes/meta-boxes/views/tmpl/editor.php';

    return ob_get_clean();

}

/**
 * @param \WP_Post|int $course_id
 * @return array
 */
function sikshya_get_course_info($course_id)
{
    if ($course_id instanceof \WP_Post) {
        $course_id = $course_id->ID;
    }
    $data = get_post_meta($course_id, 'sikshya_info', true);

    if (!is_array($data)) {
        $data = array();
    }
    return $data;
}

/**
 * @param array $args
 * @return array
 */
function sikshya_get_courses($args = array())
{
    $args = array(
            'numberposts' => -1,
            'no_found_rows' => true,
            'orderby' => 'menu_order',
            'order' => 'asc',
            'post_type' => SIKSHYA_COURSES_CUSTOM_POST_TYPE
        ) + $args;

    $data = get_posts($args);

    return $data;
}

/**
 * @param int|WP_Post|null $course_id
 * @return WP_Post|null
 */
function sikshya_get_course($course_id)
{
    if (!$course_id) {
        return null;
    }
    $course = get_post($course_id);
    if (!$course || $course->post_type != SIKSHYA_COURSES_CUSTOM_POST_TYPE) {
        return null;
    }
    return $course;
}

/**
 * @param string $url
 * @return string
 */
function sikshya_get_image_url($url)
{
    if (!$url) {
        $url = SIKSHYA_ASSETS_URL . '/images/placeholder.jpg';
    }
    return $url;
}

/**
 * @param int|null $user_id
 * @return string
 */
function sikshya_get_avatar_url($user_id = null)
{
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    if ($user_id) {
        $attachment_id = get_user_meta($user_id, 'sikshya_avatar_attachment_id', true);
        if ($attachment_id) {
            $url = wp_get_attachment_image_url($attachment_id, 'sikshya_avatar');
            if ($url) {
                return $url;
            }
        }
    }
    return get_avatar_url($user_id, array('size' => 300));
}


function sikshya_is_pro()
{
    return false;
}


function sikshya_is_new_post($post_id)
{
    if (substr($post_id, 0, 1) == '_') {

        return true;

    }
    return false;
}


function sikshya_load_metabox_html($template_vars)
{


    foreach ($template_vars->sections as $section) {

        $lessonsHtml = '';

        if (!empty($section->lessons)) {

            foreach ($section->lessons as $lesson) {

                $lesson_editor_name = 'sikshya_lesson[' . $section->ID . '][' . $lesson->ID . '][lessons_content]';

                $lesson_editor_id = 'lesson_editor_' . $lesson->ID;

                $editor = sikshya_render_editor($lesson->post_content, $lesson_editor_name, $lesson_editor_id);

                $quizesHtml = '';

                $quizzes = isset($lesson->quizzes) ? $lesson->quizzes : array();

                foreach ($quizzes as $quiz) {

                    if ($quiz) {


                        $questionsHtml = '';

                        $questions = isset($quiz->questions) ? $quiz->questions : array();

                        if ($questions) {

                            foreach ($questions as $i => $question) {


                                $question_type = get_post_meta($question->ID, 'type', true);


                                $answersHtml = '';

                                $question_answers = get_post_meta($question->ID, 'answers', true);

                                $question_answers = !empty($question_answers) ? $question_answers : array();

                                $answers_correct = get_post_meta($question->ID, 'correct_answers', true);

                                $answers_correct = !empty($answers_correct) ? $answers_correct : array();

                                if (!empty($question_answers)) {

                                    foreach ($question_answers as $answer_id => $answer) {

                                        //sikshya()->question->render_answer_tmpl('{%question_id%}_{%answer_id%}', '{%answer_id%}', 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers][{%answer_id%}]',
                                        // array(), 'lessons_questions[{%section_id%}][{%lesson_id%}][{%quiz_id%}][{%question_id%}][answers_correct][]', array(), 'text');
                                        $answersHtml .= sikshya()->question->render_answer_tmpl(

                                            $question->ID,

                                            $answer_id,

                                            'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . '][answers][' . $answer_id . ']',

                                            $answer,

                                            'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . '][answers_correct][]',

                                            $answers_correct,

                                            $question_type

                                        );

                                    }

                                }

                                $questionsHtml .= sikshya()->question->render_tmpl($question->ID, 'lessons_questions[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . '][' . $question->ID . ']', $question, $answersHtml);

                            }

                        }

                        $quizEditor = sikshya_render_editor($quiz->post_content, 'lessons_quiz[' . $section->ID . '][' . $lesson->ID . '][content]', 'lesson_quiz_editor_' . $lesson->ID);

                    }
                    $quizesHtml .= sikshya()->quiz->render_tmpl($quiz->ID, 'lessons_quiz[' . $section->ID . '][' . $lesson->ID . '][' . $quiz->ID . ']', $quiz, $quizEditor, $questionsHtml);

                }
                $lessonsHtml .= sikshya()->lesson->render_tmpl($lesson->ID, '[' . $section->ID . '][' . $lesson->ID . ']', $lesson->post_title, $editor, true, $quizesHtml);

            }
        }
        $image = get_post_meta($section->ID, 'image', true);

        echo sikshya()->section->render_tmpl($section->ID, $section->post_title, $section->post_content, $image, $lessonsHtml);
    }
}

if (!function_exists('is_sikshya_error')) {

    function is_sikshya_error($thing)
    {
        if ($thing instanceof WP_Error) {
            if ($thing->has_errors()) {
                return true;
            }
        }
        return false;
    }


}
if (!function_exists('sikshya_time')) {
    function sikshya_time()
    {
        //return current_time( 'timestamp' );
        return time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
    }
}
if (!function_exists('sikshya_get_account_page')) {

    function sikshya_get_account_page($get_permalink = false)
    {
        $page_id = absint(get_option('sikshya_account_page'));

        if ($page_id < 1) {

            global $wpdb;

            $page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_account]%" AND post_parent = 0');
        }

        $page_permalink = get_permalink($page_id);

        if ($get_permalink) {

            return $page_permalink;
        }

        return $page_id;


    }
}

if (!function_exists('sikshya_get_user_registration_page')) {

    function sikshya_get_user_registration_page($get_permalink = false)
    {
        $page_id = absint(get_option('sikshya_registration_page'));

        if ($page_id < 1) {

            global $wpdb;

            $page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_registration]%" AND post_parent = 0');
        }

        $page_permalink = get_permalink($page_id);

        if ($get_permalink) {

            return $page_permalink;
        }

        return $page_id;


    }
}
if (!function_exists('sikshya_is_screen')) {

    function sikshya_is_screen($check_screen = '')
    {
        $current_screen = get_current_screen();

        $screen_id = isset($current_screen->id) ? $current_screen->id : '';

        if ($check_screen != '' && $screen_id == $check_screen) {

            return true;
        }
        return false;


    }
}

if (!function_exists('sikshya_get_course_level')) {
    function sikshya_get_course_level($course_key = '')
    {
        $course_levels = array(
            'all' => __('All Levels', 'sikshya'),
            'beginner' => __('Beginner', 'sikshya'),
            'intermediate' => __('Intermediate', 'sikshya'),
            'expert' => __('Expert', 'sikshya'),

        );
        if (empty($course_key)) {
            return $course_levels;
        }
        $level_keys = array_keys($course_levels);
        if (in_array($course_key, $level_keys)) {
            return $course_levels[$course_key];
        }
        return $course_levels;
    }

}

if (!function_exists('sikshya_get_current_post_type')) {

    function sikshya_get_current_post_type()
    {

        $object = get_queried_object();

        $type = isset($object->post_type) ? $object->post_type : '';

        return $type;
    }
}

if (!function_exists('sikshya_is_content_available_for_user')) {

    function sikshya_is_content_available_for_user($content_id = 0, $content_type = '')
    {

        $course_id = sikshya()->course->get_id();


        if (sikshya()->course->has_enrolled($course_id)) {
            return true;

        }
        return false;
    }
}

function sikshya_get_user_items($select = array(), $where = array())
{
    global $wpdb;


    if (empty($select)) {

        $select_text = "SELECT * FROM " . SIKSHYA_DB_PREFIX . 'user_items';
    } else {
        $sanitized_select = array_map('sanitize_text_field', wp_unslash($select));

        $select_text = "SELECT " . join(', ', $sanitized_select) . " FROM " . SIKSHYA_DB_PREFIX . 'user_items';
    }
    if (empty($where)) {

        $query = $wpdb->prepare($select_text . " WHERE 1=%d", 1);

    } else {
        $where_query = ' WHERE ';
        $prepare_args = array();

        foreach ($where as $wh => $wh_value) {

            $where_query .= sanitize_text_field($wh) . "=%s AND ";

            array_push($prepare_args, $wh_value);
        }

        $where_query = rtrim($where_query, "AND ");

        $select_text .= $where_query;

        $query = $wpdb->prepare($select_text, $prepare_args);

    }
    $results = $wpdb->get_results($query);

    return $results;

}

function sikshya_update_user_items($update_values = array(), $where = array())
{
    global $wpdb;


    if (empty($update_values) || empty($where)) {
        return false;
    }

    $prepare_args = array();

    $update_query = "UPDATE " . SIKSHYA_DB_PREFIX . "user_items
SET ";

    foreach ($update_values as $up_key => $up_value) {

        $update_query .= sanitize_text_field($up_key) . "=%s, ";

        array_push($prepare_args, $up_value);
    }
    $update_query = rtrim($update_query, ", ");

    $where_query = ' WHERE ';

    foreach ($where as $wh => $wh_value) {

        $where_query .= sanitize_text_field($wh) . "=%s AND ";

        array_push($prepare_args, $wh_value);
    }

    $where_query = rtrim($where_query, "AND ");

    $update_query .= $where_query;

    $query = $wpdb->prepare($update_query, $prepare_args);

    $results = $wpdb->get_results($query);

    return $results;

}

function sikshya_get_user_item_meta($user_item_id, $meta_key, $meta_value = '')
{
    global $wpdb;

    $user_item_id = absint($user_item_id);

    $meta_key = sanitize_text_field($meta_key);

    $sql = "SELECT uim.meta_value FROM " . SIKSHYA_DB_PREFIX . "user_items ui
    INNER JOIN " . SIKSHYA_DB_PREFIX . "user_itemmeta uim ON ui.user_item_id=uim.user_item_id
    WHERE uim.meta_key=%s AND uim.user_item_id=%d";

    if (!empty($meta_value)) {

        $sql .= ' AND uim.meta_value=%s';

        $query = $wpdb->prepare($sql, $meta_key, $user_item_id, $meta_value);
    } else {
        $query = $wpdb->prepare($sql, $meta_key, $user_item_id);
    }
    $meta_data = $wpdb->get_results($query);

    if (isset($meta_data[0])) {

        return maybe_unserialize($meta_data[0]->meta_value);
    }
    return false;
}

function sikshya_update_user_item_meta($user_item_id, $meta_key, $meta_value, $prev_value = '')
{
    if ($user_item_id < 1) {
        return false;
    }
    $item = sikshya_get_user_items(array(
        'user_item_id'
    ), array(
            'user_item_id' => absint($user_item_id)
        )
    );


    if (count($item) > 0) {

        $meta_key = wp_unslash($meta_key);

        $meta_value = wp_unslash($meta_value);

        $meta_value = maybe_serialize($meta_value);

        $item_meta = sikshya_get_user_item_meta($user_item_id, $meta_key);

        if (gettype($item_meta) != 'boolean' && $item_meta) {

            global $wpdb;

            $sql = "UPDATE " . SIKSHYA_DB_PREFIX . "user_itemmeta
SET meta_value= %s
WHERE meta_key = %s AND user_item_id=%d";


            if (!empty($prev_value)) {

                $sql .= ' AND meta_value=%s';

                $query = $wpdb->prepare($sql, $meta_value, $meta_key, $user_item_id, $prev_value);
            } else {
                $query = $wpdb->prepare($sql, $meta_value, $meta_key, $user_item_id);
            }
            return $wpdb->query($query);

        } else {

            global $wpdb;

            $insert_sql_query = "INSERT INTO " . SIKSHYA_DB_PREFIX . "user_itemmeta (user_item_id,meta_key, meta_value)
VALUES (%d, %s, %s);
";

            $query = $wpdb->prepare($insert_sql_query, $user_item_id, $meta_key, $meta_value);

            return $wpdb->query($query);
        }
    }

    return false;
}


function sikshya_get_order_items($select = array(), $where = array())
{
    global $wpdb;


    if (empty($select)) {

        $select_text = "SELECT * FROM " . SIKSHYA_DB_PREFIX . 'order_items';
    } else {
        $sanitized_select = array_map('sanitize_text_field', wp_unslash($select));

        $select_text = "SELECT " . join(', ', $sanitized_select) . " FROM " . SIKSHYA_DB_PREFIX . 'user_items';
    }
    if (empty($where)) {

        $query = $wpdb->prepare($select_text . " WHERE 1=%d", 1);

    } else {
        $where_query = ' WHERE ';
        $prepare_args = array();

        foreach ($where as $wh => $wh_value) {

            $where_query .= sanitize_text_field($wh) . "=%s AND ";

            array_push($prepare_args, $wh_value);
        }

        $where_query = rtrim($where_query, "AND ");

        $select_text .= $where_query;

        $query = $wpdb->prepare($select_text, $prepare_args);

    }
    $results = $wpdb->get_results($query);

    return $results;

}

function sikshya_update_order_items($update_values = array(), $where = array())
{
    global $wpdb;


    if (empty($update_values) || empty($where)) {
        return false;
    }

    $prepare_args = array();

    $update_query = "UPDATE " . SIKSHYA_DB_PREFIX . "order_items
SET ";

    foreach ($update_values as $up_key => $up_value) {

        $update_query .= sanitize_text_field($up_key) . "=%s, ";

        array_push($prepare_args, $up_value);
    }
    $update_query = rtrim($update_query, ", ");

    $where_query = ' WHERE ';

    foreach ($where as $wh => $wh_value) {

        $where_query .= sanitize_text_field($wh) . "=%s AND ";

        array_push($prepare_args, $wh_value);
    }

    $where_query = rtrim($where_query, "AND ");

    $update_query .= $where_query;

    $query = $wpdb->prepare($update_query, $prepare_args);

    $results = $wpdb->get_results($query);

    return $results;

}

function sikshya_get_order_item_meta($order_item_id, $meta_key, $meta_value = '')
{
    global $wpdb;

    $order_item_id = absint($order_item_id);

    $meta_key = sanitize_text_field($meta_key);

    $sql = "SELECT uim.meta_value FROM " . SIKSHYA_DB_PREFIX . "order_items ui
    INNER JOIN " . SIKSHYA_DB_PREFIX . "order_itemmeta uim ON ui.order_item_id=uim.order_item_id
    WHERE uim.meta_key=%s AND uim.order_item_id=%d";

    if (!empty($meta_value)) {

        $sql .= ' AND uim.meta_value=%s';

        $query = $wpdb->prepare($sql, $meta_key, $order_item_id, $meta_value);
    } else {
        $query = $wpdb->prepare($sql, $meta_key, $order_item_id);
    }
    $meta_data = $wpdb->get_results($query);

    if (isset($meta_data[0])) {

        return maybe_unserialize($meta_data[0]->meta_value);
    }
    return false;
}

function sikshya_update_order_item_meta($order_item_id, $meta_key, $meta_value, $prev_value = '')
{
    if ($order_item_id < 1) {
        return false;
    }


    $meta_key = wp_unslash($meta_key);

    $meta_value = wp_unslash($meta_value);

    $meta_value = maybe_serialize($meta_value);

    $item_meta = sikshya_get_order_item_meta($order_item_id, $meta_key, $meta_value);

    if (gettype($item_meta) != 'boolean' && $item_meta) {

        global $wpdb;

        $sql = "UPDATE " . SIKSHYA_DB_PREFIX . "order_itemmeta
SET meta_value= %s
WHERE meta_key = %s AND order_item_id=%d";


        if (!empty($prev_value)) {

            $sql .= ' AND meta_value=%s';

            $query = $wpdb->prepare($sql, $meta_value, $meta_key, $order_item_id, $prev_value);
        } else {
            $query = $wpdb->prepare($sql, $meta_value, $meta_key, $order_item_id);
        }

        return $wpdb->query($query);

    } else {

        global $wpdb;

        $insert_sql_query = "INSERT INTO " . SIKSHYA_DB_PREFIX . "order_itemmeta (order_item_id,meta_key, meta_value)
VALUES (%d, %s, %s);
";

        $query = $wpdb->prepare($insert_sql_query, $order_item_id, $meta_key, $meta_value);


        return $wpdb->query($query);
    }


    return false;
}


if (!function_exists('sikshya_get_login_page')) {

    function sikshya_get_login_page($get_permalink = false, $redirect_url = '')
    {
        $page_id = absint(get_option('sikshya_login_page'));

        if ($page_id < 1) {

            global $wpdb;

            $page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_login]%" AND post_parent = 0');
        }


        if ($get_permalink) {

            if ($page_id > 0) {

                $page_permalink = !empty($redirect_url) ? add_query_arg('redirect_to', urlencode($redirect_url), get_permalink($page_id)) : get_permalink($page_id);

            } else {

                $page_permalink = wp_login_url($redirect_url);
            }


            return $page_permalink;
        }

        return $page_id;


    }
}
if (!function_exists('sikshya_question_answer_type')) {

    function sikshya_question_answer_type()
    {
        return array(
            'single' => __('One answer', 'sikshya'),
            'single_image' => __('One answer with image', 'sikshya'),
            'multi' => __('Multiple answers', 'sikshya'),
            'multi_image' => __('Multiple answers with image', 'sikshya'),
        );
    }
}
if (!function_exists('sikshya_get_instructors_list')) {

    function sikshya_get_instructors_list()
    {
        $users = get_users(array(
            'role__in' => array('administrator', 'sikshya_instructor'),
        ));
        $all_users = array();
        foreach ($users as $user) {

            $user_obj = new stdClass();
            $user_obj->ID = $user->ID;
            $user_obj->name = $user->user_nicename . ' (' . $user->user_login . ')';
            $all_users[$user->ID] = $user_obj;
        }
        return $all_users;
    }
}
if (!function_exists('sikshya_clean')) {

    function sikshya_clean($var)
    {
        if (is_array($var)) {
            return array_map('sikshya_clean', $var);
        } else {
            return is_scalar($var) ? sanitize_text_field($var) : $var;
        }
    }
}