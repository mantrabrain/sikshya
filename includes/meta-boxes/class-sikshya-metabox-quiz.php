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

            add_action('edit_form_after_editor', array($this, 'question_question_template'));


        }

        public function question_question_template()
        {
            global $post;

            if (SIKSHYA_QUIZZES_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }



            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_nonce');

            sikshya()->quiz->load($post->ID);




        }


    }
}