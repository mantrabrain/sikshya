<?php

class Sikshya_Admin_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'assets'));

    }

    public function assets()
    {
        /**
         * @since 1.0.0
         */

        wp_enqueue_script('jquery-ui-core', array('jquery'));
        wp_enqueue_script('jquery-ui-sortable', array('jquery'));
        wp_enqueue_script('jquery-ui-accordion', array('jquery'));
        wp_enqueue_script('jquery-ui-datepicker', array('jquery'));
        wp_enqueue_script('jquery-ui-tabs', array('jquery'));


        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        wp_enqueue_script('tiny_mce');

        wp_enqueue_script('sweetalert2-script', SIKSHYA_ASSETS_URL . '/vendor/sweetalert2/js/sweetalert2.js', array('jquery'), SIKSHYA_VERSION);

        wp_enqueue_style('sweetalert2-style', SIKSHYA_ASSETS_URL . '/vendor/sweetalert2/css/sweetalert2.css', array(), SIKSHYA_VERSION);

        wp_register_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-common', SIKSHYA_ADMIN_ASSETS_URL . '/js/common.js', array('jquery', 'jquery-ui-tabs', 'media-upload', 'thickbox'), SIKSHYA_VERSION);


        wp_register_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-custom-ace-editor', SIKSHYA_ASSETS_URL . '/vendor/ace.js', array('jquery'), '1.2.9');
        wp_enqueue_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-custom-ace-editor');

        global $post;
        $post_id = isset($post->ID) ? $post->ID : 0;
        $data =
            array(
                'general' =>
                    array(
                        'pro_only' => __('This function will be available in Sikshya PRO.', 'sikshya')
                    ),
                'import' =>
                    array(
                        'label' => __('Import courses', 'sikshya'),
                        'link' => admin_url('options-general.php?page=sikshya#import')
                    ),
                'ajax_url' => admin_url('admin-ajax.php'),
                'remove_section_from_course_nonce' => wp_create_nonce('wp_sikshya_remove_section_from_course_nonce'),
                'remove_lesson_from_course_nonce' => wp_create_nonce('wp_sikshya_remove_lesson_from_course_nonce'),
                'remove_quiz_from_course_nonce' => wp_create_nonce('wp_sikshya_remove_quiz_from_course_nonce'),
                'remove_question_from_course_nonce' => wp_create_nonce('wp_sikshya_remove_question_from_course_nonce'),
                'course_id' => $post_id

            );
        wp_localize_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-common', 'sikshya', $data);
        wp_enqueue_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-common');
        wp_enqueue_style(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-common-style', SIKSHYA_ADMIN_ASSETS_URL . '/css/sikshya-admin.css', false, SIKSHYA_VERSION);

        /*wp_register_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-sikshya', SIKSHYA_ADMIN_ASSETS_URL . '/js/custom/sikshya.js', array('jquery'), SIKSHYA_VERSION);
        wp_enqueue_script(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-sikshya');*/

    }
}

new Sikshya_Admin_Assets();