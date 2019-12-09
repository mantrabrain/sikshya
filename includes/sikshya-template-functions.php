<?php

if (!function_exists('sikshya_header')) {
    function sikshya_header()
    {
        get_header();
    }
}
if (!function_exists('sikshya_footer')) {
    function sikshya_footer()
    {

        do_action('sikshya_before_body_end');
        get_footer();
    }
}

if (!function_exists('sikshya_single_content')) {
    function sikshya_single_content()
    {
        sikshya_load_template('parts.lesson.content');

    }
}


if (!function_exists('sikshya_get_template')) {
    function sikshya_get_template($template = null, $sikshya_pro = false)
    {
        if (!$template) {
            return false;
        }
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        /**
         * Get template first from child-theme if exists
         * If child theme not exists, then get template from parent theme
         */
        $template_location = trailingslashit(get_stylesheet_directory()) . "sikshya/{$template}.php";
        if (!file_exists($template_location)) {
            $template_location = trailingslashit(get_template_directory()) . "sikshya/{$template}.php";
        }
        $file_in_theme = $template_location;
        if (!file_exists($template_location)) {
            $template_location = trailingslashit(SIKSHYA_PATH) . "/templates/{$template}.php";

            if (!file_exists($template_location)) {
                echo '<div class="sikshya-notice-warning"> ' . __(sprintf('The file you are trying to load is not exists in your theme or sikshya plugins location, if you are a developer and extending sikshya plugin, please create a php file at location %s ', "<code>{$file_in_theme}</code>"), 'sikshya') . ' </div>';
            }
        }

        return apply_filters('sikshya_get_template_path', $template_location, $template);
    }
}

if (!function_exists('sikshya_get_template_path')) {
    function sikshya_get_template_path($template = null, $sikshya_pro = false)
    {
        if (!$template) {
            return false;
        }
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        /**
         * Get template first from child-theme if exists
         * If child theme not exists, then get template from parent theme
         */
        $template_location = trailingslashit(get_stylesheet_directory()) . "sikshya/{$template}.php";
        if (!file_exists($template_location)) {
            $template_location = trailingslashit(get_template_directory()) . "sikshya/{$template}.php";
        }
        if (!file_exists($template_location)) {
            $template_location = trailingslashit(SIKSHYA_PATH) . "/templates/{$template}.php";
        }
        if (!file_exists($template_location) && $sikshya_pro && function_exists('sikshya_pro')) {
            $template_location = trailingslashit(sikshya_pro()->path) . "/templates/{$template}.php";
        }

        return apply_filters('sikshya_get_template_path', $template_location, $template);
    }
}

/**
 * @param null $template
 *
 * @param array $variables
 *
 * Load template for Sikshya
 *
 * @since v.1.0.0
 *
 * @updated v.1.1.2
 */

if (!function_exists('sikshya_load_template')) {
    function sikshya_load_template($template = null, $variables = array(), $sikshya_pro = false)
    {
        $variables = (array)$variables;
        $variables = apply_filters('get_sikshya_load_template_variables', $variables);

        extract($variables);

        $isLoad = apply_filters('should_sikshya_load_template', true, $template, $variables);
        if (!$isLoad) {
            return;
        }

        do_action('sikshya_load_template_before', $template, $variables);
        include sikshya_get_template($template, $sikshya_pro);
        do_action('sikshya_load_template_after', $template, $variables);
    }
}


if (!function_exists('sikshya_course_loop_start')) {
    function sikshya_course_loop_start($echo = true)
    {
        ob_start();
        sikshya_load_template('loop.loop-start');
        $output = apply_filters('sikshya_course_loop_start', ob_get_clean());

        if ($echo) {
            echo $output;
        }
        return $output;
    }
}

if (!function_exists('sikshya_course_loop_end')) {
    function sikshya_course_loop_end($echo = true)
    {
        ob_start();
        sikshya_load_template('loop.loop-end');

        $output = apply_filters('sikshya_course_loop_end', ob_get_clean());
        if ($echo) {
            echo $output;
        }

        return $output;
    }
}

if (!function_exists('sikshya_course_archive_pagination')) {
    function sikshya_course_archive_pagination($echo = true)
    {
        ob_start();
        sikshya_load_template('loop.sikshya-pagination');

        $output = apply_filters('sikshya_course_archive_pagination', ob_get_clean());
        if ($echo) {
            echo $output;
        }

        return $output;
    }
}
if (!function_exists('get_sikshya_course_thumbnail')) {
    function get_sikshya_course_thumbnail($size = 'post-thumbnail', $url = false)
    {
        $post_id = get_the_ID();
        $post_thumbnail_id = (int)get_post_thumbnail_id($post_id);

        if ($post_thumbnail_id) {
            //$size = apply_filters( 'post_thumbnail_size', $size, $post_id );
            $size = apply_filters('sikshya_course_thumbnail_size', $size, $post_id);
            if ($url) {
                return wp_get_attachment_image_url($post_thumbnail_id, $size);
            }

            $html = wp_get_attachment_image($post_thumbnail_id, $size, false);
        } else {
            $placeHolderUrl = SIKSHYA_ASSETS_URL . '/images/placeholder.jpg';
            if ($url) {
                return $placeHolderUrl;
            }
            $html = sprintf('<img alt="%s" src="' . $placeHolderUrl . '" />', __('Placeholder', 'sikshya'));
        }

        echo $html;
    }
}


if (!function_exists('sikshya_notices')) {

    function sikshya_notices($notice_code = '')
    {
        if (empty($notice_code)) {

            $notice_code = sikshya()->notice_key;
        }
        if (is_sikshya_error(sikshya()->errors)) {

            $error_messages = sikshya()->errors->get_error_messages($notice_code);

            if (count($error_messages) > 0) {

                echo '<ul class="sikshya-messages sikshya-error">';

                foreach ($error_messages as $message_key => $message) {

                    echo '<li>' . esc_html($message) . '</li>';
                }

                echo '</ul>';
            }

        }

        if (sikshya()->messages->has_messages($notice_code)) {

            $messages = sikshya()->messages->get_messages($notice_code);

            if (count($messages) > 0) {

                foreach ($messages as $message_type => $message_list) {

                    echo '<ul class="sikshya-messages sikshya-' . esc_attr($message_type) . '">';

                    foreach ($message_list as $message_text) {

                        echo '<li>' . esc_html($message_text) . '</li>';
                    }

                    echo '</ul>';
                }
            }

        }
    }
}
if (!function_exists('sikshya_get_course_tabs')) {
    /**
     * Return an array of tabs display in single course page.
     *
     * @return array
     */
    function sikshya_get_course_tabs()
    {

        $defaults = array();

        // Description tab - shows product content

        $defaults['overview'] = array(
            'title' => __('Overview', 'sikshya'),
            'priority' => 10,
            'callback' => 'sikshya_course_overview_tab'
        );


        // Curriculum
        $defaults['curriculum'] = array(
            'title' => __('Curriculum', 'sikshya'),
            'priority' => 30,
            'callback' => 'sikshya_course_curriculum_tab'
        );

        $defaults['instructor'] = array(
            'title' => __('Instructor', 'sikshya'),
            'priority' => 40,
            'callback' => 'sikshya_course_instructor_tab'
        );


        // Filter
        if ($tabs = apply_filters('sikshya-course-tabs', $defaults)) {
            // Sort tabs by priority
            $request_tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : 'tab-overview';

            foreach ($tabs as $k => $v) {
                $v['id'] = !empty($v['id']) ? $v['id'] : 'tab-' . $k;

                if ($request_tab === $v['id']) {
                    $v['active'] = true;

                }
                $tabs[$k] = $v;
            }


        }

        return $tabs;
    }

}

if (!function_exists('sikshya_course_overview_tab')) {
    /**
     * Output course overview
     *
     */
    function sikshya_course_overview_tab()
    {
        sikshya_load_template('parts.course.tabs.overview');

    }
}

if (!function_exists('sikshya_course_curriculum_tab')) {
    /**
     * Output course overview
     *
     */
    function sikshya_course_curriculum_tab()
    {
        sikshya_load_template('parts.course.tabs.curriculum');

    }
}

if (!function_exists('sikshya_course_instructor_tab')) {
    /**
     * Output course overview
     *
     */
    function sikshya_course_instructor_tab()
    {
        $course_id = get_the_ID();

        $instructor_id = get_post_meta($course_id, 'sikshya_instructor', true);


        $params['user_bio'] = get_user_meta($instructor_id, 'description', true);

        $params['user_avatar_url'] = get_avatar_url($instructor_id);

        sikshya_load_template('parts.course.tabs.instructor', $params);

    }
}
if (!function_exists('sikshya_content_item_edit_links')) {
    /**
     * Add edit links for course item question to admin bar.
     */
    function sikshya_content_item_edit_links()
    {
        global $wp_admin_bar, $post;

        if (!(!is_admin() && is_user_logged_in())) {
            return;
        }
        if (is_singular(SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {
            // conditional content/code

            $lesson_post_type_object = get_post_type_object(SIKSHYA_LESSONS_CUSTOM_POST_TYPE);

            $edit_lesson_link = get_edit_post_link($post->ID);

            $course_post_type_object = get_post_type_object(SIKSHYA_COURSES_CUSTOM_POST_TYPE);

            $lesson_id = $post->ID;

            $section_id = get_post_meta($lesson_id, 'section_id', true);

            $course_id = get_post_meta($section_id, 'course_id', true);

            $edit_course_link = get_edit_post_link($course_id);

            $wp_admin_bar->remove_menu('edit');

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_COURSES_CUSTOM_POST_TYPE,
                'title' => $course_post_type_object->labels->edit_item,
                'href' => $edit_course_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
                'title' => $lesson_post_type_object->labels->edit_item,
                'href' => $edit_lesson_link
            ));
        } else if (is_singular(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE)) {

            $quiz_post_type_object = get_post_type_object(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE);

            $edit_quiz_link = get_edit_post_link($post->ID);

            $quiz_id = $post->ID;

            $lesson_id = get_post_meta($quiz_id, 'lesson_id', true);

            $section_id = get_post_meta($lesson_id, 'section_id', true);

            $course_id = get_post_meta($section_id, 'course_id', true);

            $lesson_post_type_object = get_post_type_object(SIKSHYA_LESSONS_CUSTOM_POST_TYPE);

            $edit_lesson_link = get_edit_post_link($lesson_id);

            $course_post_type_object = get_post_type_object(SIKSHYA_COURSES_CUSTOM_POST_TYPE);


            $edit_course_link = get_edit_post_link($course_id);

            $wp_admin_bar->remove_menu('edit');

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_COURSES_CUSTOM_POST_TYPE,
                'title' => $course_post_type_object->labels->edit_item,
                'href' => $edit_course_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
                'title' => $lesson_post_type_object->labels->edit_item,
                'href' => $edit_lesson_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                'title' => $quiz_post_type_object->labels->edit_item,
                'href' => $edit_quiz_link
            ));
        } else if (is_singular(SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE)) {

            $question_post_type_object = get_post_type_object(SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE);

            $edit_question_link = get_edit_post_link($post->ID);

            $question_id = $post->ID;

            $quiz_id = get_post_meta($question_id, 'quiz_id', true);

            $lesson_id = get_post_meta($quiz_id, 'lesson_id', true);

            $section_id = get_post_meta($lesson_id, 'section_id', true);

            $course_id = get_post_meta($section_id, 'course_id', true);


            $quiz_post_type_object = get_post_type_object(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE);

            $edit_quiz_link = get_edit_post_link($quiz_id);


            $lesson_post_type_object = get_post_type_object(SIKSHYA_LESSONS_CUSTOM_POST_TYPE);

            $edit_lesson_link = get_edit_post_link($lesson_id);


            $course_post_type_object = get_post_type_object(SIKSHYA_COURSES_CUSTOM_POST_TYPE);

            $edit_course_link = get_edit_post_link($course_id);

            $wp_admin_bar->remove_menu('edit');

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_COURSES_CUSTOM_POST_TYPE,
                'title' => $course_post_type_object->labels->edit_item,
                'href' => $edit_course_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_LESSONS_CUSTOM_POST_TYPE,
                'title' => $lesson_post_type_object->labels->edit_item,
                'href' => $edit_lesson_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_QUIZZES_CUSTOM_POST_TYPE,
                'title' => $quiz_post_type_object->labels->edit_item,
                'href' => $edit_quiz_link
            ));

            $wp_admin_bar->add_menu(array(
                'id' => 'edit-' . SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE,
                'title' => $question_post_type_object->labels->edit_item,
                'href' => $edit_question_link
            ));
        }


    }
}
