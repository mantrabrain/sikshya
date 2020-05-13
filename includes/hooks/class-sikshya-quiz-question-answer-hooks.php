<?php

class Sikshya_Quiz_Question_Asnwer
{

    public function __construct()
    {
        add_action('sikshya_quiz_question_answer', array($this, 'question_answer'), 2);

    }

    public function question_answer($data = array())
    {
        $ids = isset($data['ids']) ? $data['ids'] : array();

        $quiz_id = isset($ids['quiz_id']) ? $ids['quiz_id'] : 0;

        $course_id = isset($ids['course_id']) ? $ids['course_id'] : 0;

        $type = isset($data['type']) ? $data['type'] : 'single';

        $answers = isset($data['answers']) ? $data['answers'] : array();

        foreach ($answers as $answer_key => $answer) {

            $params['answer'] = $answer;

            $params['answer_key'] = $answer_key;

            $params['type'] = $type;

            sikshya_load_template('parts.answer.loop-start', $params);

            sikshya_load_template('parts.answer.' . $type . '-answer', $params);

            sikshya_load_template('parts.answer.loop-end', $params);
        }

        if (sikshya()->question->get_prev_question(get_the_ID())) {

            sikshya_load_template('parts.answer.prev-form', $ids);

        }

        //if (!sikshya()->quiz->is_completed(get_current_user_id(), $quiz_id, $course_id)) {

        sikshya_load_template('parts.answer.complete-form', $ids);
        //}

        if (sikshya()->question->get_next_question(get_the_ID())) {
            sikshya_load_template('parts.answer.next-form', $ids);
        }


    }
}

new Sikshya_Quiz_Question_Asnwer();