<?php
if (!class_exists('Sikshya_Metabox_Quiz')) {

    class Sikshya_Metabox_Quiz
    {


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);


        }

        public function metabox()
        {

            add_meta_box(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_options', __('Quiz Options', 'sikshya'), array($this, 'quiz_options'), SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, 'normal', 'high');

        }

        public function quiz_options()
        {
            global $post;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_nonce');


        }


    }
}