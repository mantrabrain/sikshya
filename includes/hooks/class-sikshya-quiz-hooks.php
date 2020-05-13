<?php

class Sikshya_Quiz_Hooks
{

    public function __construct()
    {
        add_action('sikshya_after_quiz_content', array($this, 'quiz_button_form'));

    }

    public function quiz_button_form()
    {
        $start_text = __('Start', 'sikshya');
        $quiz_id = get_the_ID();
        $user_id = get_current_user_id();

        if (sikshya()->quiz->is_started($user_id, $quiz_id)) {
            $start_text = __('Continue to Questions', 'sikshya');

        }

        $params = array(
            'quiz_id' => $quiz_id,
            'course_id' => sikshya()->course->get_id(),
            'start_text' => $start_text
        );

        sikshya_load_template('parts.quiz.start-form', $params);


    }
}

new Sikshya_Quiz_Hooks();