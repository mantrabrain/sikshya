<?php

class Sikshya_Template_Hooks
{

    public function __construct()
    {
        add_action('sikshya_before_registration_form', array($this, 'notices'));
        add_action('sikshya_before_login_form', array($this, 'notices'));
        add_action('sikshya_account_page_sidebar', array($this, 'account_sidebar'));
        add_action('sikshya_account_page_content', array($this, 'account_page_content'));
        add_action('sikshya_account_content_item', array($this, 'account_content_item'));
        add_action('sikshya_course_tab_content', array($this, 'tab_content'));
        add_filter('admin_bar_menu', 'sikshya_content_item_edit_links', 90);
        add_filter('template_include', array($this, 'template_include'), 1000, 1);


    }


    public function tab_content()
    {

        $course_id = get_the_ID();

        sikshya_load_template('parts.course.tabs');

    }

    public function account_sidebar()
    {
        sikshya_load_template('profile.account-sidebar');
    }

    public function account_page_content()
    {
        sikshya_load_template('profile.account-content');

    }

    public function account_content_item()
    {

        global $sikshya_current_account_page;

        switch ($sikshya_current_account_page) {
            case "profile":
                sikshya_load_template('profile.parts.profile');
                break;
            case "enrolled-courses":
                sikshya_load_template('profile.parts.enrolled-courses');
                break;
            case "update-profile":
                sikshya_load_template('profile.parts.update-profile');
                break;
            case "logout":
                //sikshya_load_template('profile.parts.dashboard');
                break;
            default:
                sikshya_load_template('profile.parts.dashboard');
                break;

        }
    }

    public function notices()
    {

        sikshya_notices();
    }

    public function template_include($template)
    {

        $post_type = sikshya_get_current_post_type();

        if (is_single() || is_page()) {

            switch ($post_type) {

                case SIKSHYA_COURSES_CUSTOM_POST_TYPE:

                    $template = sikshya_get_template('single-course');

                    break;

                case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:

                    $template = sikshya_get_template('single-lesson');

                    break;

                case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:

                    $template = sikshya_get_template('single-quiz');

                    break;
                case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:

                    $template = sikshya_get_template('single-question');

                    break;

            }
        } else if (is_post_type_archive(SIKSHYA_COURSES_CUSTOM_POST_TYPE)) {

            $template = sikshya_get_template('archive-course');

        }

        return $template;

    }

}

new Sikshya_Template_Hooks();