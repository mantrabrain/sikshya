<?php
if (!class_exists('Sikshya_Metabox_Question')) {

    class Sikshya_Metabox_Question
    {


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);


        }

        public function metabox()
        {

            add_action('edit_form_after_editor', array($this, 'question_answer_template'));

        }

        public function question_answer_template()
        {

            if (SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }
            global $post;

            $question_id = $post->ID;

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE . '_nonce');

            sikshya()->question->load($question_id);



        }


    }
}