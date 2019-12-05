<?php
if (!class_exists('Sikshya_Metabox_Quiz')) {

    class Sikshya_Metabox_Quiz
    {


        function __construct()
        {

            add_action('add_meta_boxes', array($this, 'metabox'), 10);
            add_action('save_post', array($this, 'save'));


        }

        public function save($post_id)
        {

            if (SIKSHYA_QUIZZES_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }

            remove_action('save_post', array($this, 'save'));

            $nonce = isset($_POST[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_question_nonce']) ? $_POST[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_question_nonce'] : '';
            if (!wp_verify_nonce($nonce, SIKSHYA_FILE)) {
                return;
            }

            $quiz_questions = isset($_POST['quiz_questions']) ? @$_POST['quiz_questions'] : array();

            $quiz_question_answer = isset($_POST['quiz_question_answer']) ? @$_POST['quiz_question_answer'] : array();

            sikshya()->quiz->update_quiz_question($quiz_questions, $quiz_question_answer, $post_id);
        }

        public function metabox()
        {

            add_action('edit_form_after_editor', array($this, 'question_question_template'));
            add_meta_box(SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_course', __('Assigned', 'sikshya'), array($this, 'assign_options'), SIKSHYA_QUIZZES_CUSTOM_POST_TYPE, 'side', 'high');


        }

        public function question_question_template()
        {
            global $post;

            if (SIKSHYA_QUIZZES_CUSTOM_POST_TYPE !== get_post_type()) {
                return;
            }


            wp_nonce_field(SIKSHYA_FILE, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE . '_question_nonce');

            sikshya()->quiz->load($post->ID);


        }

        public function assign_options($post)
        {
            sikshya_load_admin_template('metabox.quiz.assigned-course');

        }
    }
}