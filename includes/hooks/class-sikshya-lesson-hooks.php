<?php

class Sikshya_Lesson_ooks
{

	public function __construct()
	{
		add_action('sikshya_lesson_content_area', array($this, 'lesson_content_area'));
		add_action('sikshya_lesson_sidebar_area', array($this, 'lesson_sidebar'));
		add_action('sikshya_lesson_content_top_bar', array($this, 'top_bar'));
		add_action('sikshya_lesson_content_after_top_bar', array($this, 'after_top_bar'));
		add_action('sikshya_lesson_navigation_area', array($this, 'navigation'));

	}

	public function navigation()
	{
		$all_lesson_quiz_ids = sikshya()->course->get_lesson_quiz_ids();

		$prev_params = sikshya()->lesson->get_prev_params($all_lesson_quiz_ids);

		$next_params = sikshya()->lesson->get_next_params($all_lesson_quiz_ids);

		if (!empty($prev_params)) {
			sikshya_load_template('parts.lesson.lesson-prev-nav', $prev_params);
		}

		if (!empty($next_params)) {
			sikshya_load_template('parts.lesson.lesson-next-nav', $next_params);
		}


	}

	public function lesson_content_area()
	{


		$post_type = sikshya_get_current_post_type();

		switch ($post_type) {

			case SIKSHYA_LESSONS_CUSTOM_POST_TYPE:


				if (!sikshya_is_content_available_for_user(get_the_ID(), SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {

					sikshya_load_template('global.protected-content');

					return;
				}
				wp_reset_query();
				the_content();

				break;
			case SIKSHYA_QUIZZES_CUSTOM_POST_TYPE:

				if (!sikshya_is_content_available_for_user(get_the_ID(), SIKSHYA_QUIZZES_CUSTOM_POST_TYPE)) {

					sikshya_load_template('global.protected-content');

					return;
				}

				$quiz_report = isset($_GET['quiz_report']) ? (boolean)$_GET['quiz_report'] : false;

				if ($quiz_report) {

					sikshya_load_template('parts.quiz.report');

				} else {
					sikshya_load_template('parts.quiz.quiz');
				}
				break;

			case SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE:

				if (!sikshya_is_content_available_for_user(get_the_ID(), SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE)) {

					sikshya_load_template('global.protected-content');

					return;
				}
				$question_id = get_the_ID();

				$data['type'] = get_post_meta($question_id, 'type', true);

				$data['answers'] = get_post_meta($question_id, 'answers', true);

				$data['correct_answers'] = get_post_meta($question_id, 'correct_answers', true);

				sikshya_load_template('parts.question.loop-start');

				sikshya_load_template('parts.question.question');

				$data['ids'] = array(
					'quiz_id' => get_post_meta($question_id, 'quiz_id', true),
					'course_id' => sikshya()->course->get_id(),
					'question_id' => $question_id
				);

				do_action('sikshya_quiz_question_answer', $data);

				sikshya_load_template('parts.question.loop-end');


				break;

		}
	}

	public function lesson_sidebar()
	{
		sikshya_load_template('parts.lesson.sidebar');
	}

	public function after_top_bar()
	{
		if (SIKSHYA_LESSONS_CUSTOM_POST_TYPE == sikshya_get_current_post_type()) {

			$lesson_id = get_the_ID();

			if (!sikshya_is_content_available_for_user(get_the_ID(), SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {

				return;
			}

			$params = array(
				'lesson_id' => $lesson_id,
				'course_id' => sikshya()->course->get_id()
			);
			$is_lesson_completed = sikshya()->lesson->is_completed($lesson_id);

			if (!$is_lesson_completed) {

				sikshya_load_template('parts.lesson.lesson-form', $params);
			}
		}
	}

	public function top_bar()
	{
		sikshya_load_template('parts.lesson.top-bar');
	}

}

new Sikshya_Lesson_ooks();
