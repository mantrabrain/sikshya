<?php

class Sikshya_Permalink_Manager
{
    public function __construct()
    {
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('generate_rewrite_rules', array($this, 'rewrite_rules'));
        add_action('sikshya_flush_rewrite_rules', array($this, 'flush_rewrite'));
        add_action('sikshya_after_register_post_type', array($this, 'maybe_flush_rewrite_rules'));

        add_filter('post_type_link', array($this, 'post_type_links'), 10, 2);


    }

    public function maybe_flush_rewrite_rules()
    {
        if ('yes' === get_option('sikshya_queue_flush_rewrite_rules')) {
            update_option('sikshya_queue_flush_rewrite_rules', 'no');
            $this->flush_rewrite();
        }
    }

    public function flush_rewrite()
    {
        flush_rewrite_rules();

    }

    public function register_query_vars($vars)
    {
        $vars[] = 'sikshya_account_page';

        return $vars;
    }

    public function rewrite_rules($wp_rewrite)
    {
        $account_pages = sikshya_account_page_nav_items();

        $course_permalink = 'courses';

        $lesson_permalink = 'lessons';

        $quiz_permalink = 'quizzes';

        $lesson_post_type = SIKSHYA_LESSONS_CUSTOM_POST_TYPE;

        $quiz_post_type = SIKSHYA_QUIZZES_CUSTOM_POST_TYPE;

        $question_post_type = SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE;


        $new_rules = array(
            // Questions
            $course_permalink . "/(.+?)/{$quiz_permalink}/(.+?)/(.+?)/?$" => "index.php?post_type={$question_post_type}&name=" . $wp_rewrite->preg_index(3),

            //Lesson Permalink
            $course_permalink . "/(.+?)/{$lesson_permalink}/(.+?)/?$" => "index.php?post_type={$lesson_post_type}&name=" . $wp_rewrite->preg_index(2),

            // Quizzes
            $course_permalink . "/(.+?)/{$quiz_permalink}/(.+?)/?$" => "index.php?post_type={$quiz_post_type}&name=" . $wp_rewrite->preg_index(2),


        );
        foreach ($account_pages as $key => $dashboard_page) {

            $new_rules["(.+?)/{$key}/?$"] = 'index.php?pagename=' . $wp_rewrite->preg_index(1) . '&sikshya_account_page=' . $key;


        }

        $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

    }


    public function post_type_links($post_link, $post)
    {

        $course_permalink = 'courses';

        $lesson_permalink = 'lessons';

        $quiz_permalink = 'quizzes';

        $post = get_post($post);

        global $wpdb;

        $course_base_slug = 'sample-course';

        $quiz_base_slug = 'sample-quiz';

        if (is_object($post) && $post->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE) {
            //Lesson Permalink
            $section_id = get_post_meta($post->ID, 'section_id', true);

            $course_id = sikshya()->section->get_course_id($section_id);

            if ($course_id) {

                $course = $wpdb->get_row("select {$wpdb->posts}.post_name from {$wpdb->posts} where ID = {$course_id} ");

                $course_base_slug = $course ? $course->post_name : $course_base_slug;
            }

            return home_url("/{$course_permalink}/{$course_base_slug}/{$lesson_permalink}/" . $post->post_name . '/');

        } elseif (is_object($post) && $post->post_type == SIKSHYA_QUIZZES_CUSTOM_POST_TYPE) {
            //Quizzes Permalink

            $section_id = get_post_meta($post->ID, 'section_id', true);

            $course_id = sikshya()->section->get_course_id($section_id);

            if ($course_id) {

                $course = $wpdb->get_row("select {$wpdb->posts}.post_name from {$wpdb->posts} where ID = {$course_id} ");

                $course_base_slug = $course ? $course->post_name : $course_base_slug;


            }
            return home_url("/{$course_permalink}/{$course_base_slug}/{$quiz_permalink}/" . $post->post_name . '/');

        } elseif (is_object($post) && $post->post_type == SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE) {
            //Questions Permalink

            $quiz_id = get_post_meta($post->ID, 'quiz_id', true);

            $section_id = get_post_meta($quiz_id, 'section_id', true);

            $course_id = sikshya()->section->get_course_id($section_id);



            if ($course_id) {

                $course = $wpdb->get_row("select {$wpdb->posts}.post_name from {$wpdb->posts} where ID = {$course_id} ");

                $course_base_slug = $course ? $course->post_name : $course_base_slug;


            }

            if ($quiz_id) {

                $quiz = $wpdb->get_row("select {$wpdb->posts}.post_name from {$wpdb->posts} where ID = {$quiz_id} ");

                $quiz_base_slug = $quiz ? $quiz->post_name : $quiz_base_slug;


            }
            return home_url("/{$course_permalink}/{$course_base_slug}/{$quiz_permalink}/{$quiz_base_slug}/" . $post->post_name . '/');
        }
        return $post_link;


    }


}

new Sikshya_Permalink_Manager();