<?php
if (!class_exists('Sikshya_Metabox_Question')) {

    class Sikshya_Metabox_Question
    {


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);
            add_action('save_post', array($this, 'save'));


        }

        public function save($post_id)
        {

            if (SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }
            remove_action('save_post', array($this, 'save'));

            $nonce = isset($_POST[SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE . '_answer_nonce']) ? $_POST[SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE . '_answer_nonce'] : '';
            if (!wp_verify_nonce($nonce, SIKSHYA_FILE)) {
                return;
            }
            $quiz_question_answer = isset($_POST['quiz_question_answer']) ? @$_POST['quiz_question_answer'][$post_id] : array();


            sikshya()->question->update_answer_meta($quiz_question_answer, $post_id);
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

            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE . '_answer_nonce');

            sikshya()->question->load($question_id);


        }


    }
}