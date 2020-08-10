<?php
if (!class_exists('Sikshya_Metabox_Course')) {

	class Sikshya_Metabox_Course
	{

		private $course_meta = array();

		private $active_tab = 'curriculum';

		function __construct()
		{


			add_action('add_meta_boxes', array($this, 'metabox'), 10);
			add_action('save_post_' . SIKSHYA_COURSES_CUSTOM_POST_TYPE, array($this, 'save'));
			add_action('sikshya_course_metaboxes', array($this, 'course_meta'));
			add_action('sikshya_course_tab_curriculum', array($this, 'curriculum_tab'));
			add_action('sikshya_course_tab_general', array($this, 'general_tab'));
			add_action('sikshya_course_tab_requirements', array($this, 'requirements_tab'));
			add_action('sikshya_course_tab_outcomes', array($this, 'outcomes_tab'));
			add_action('sikshya_course_tab_pricing', array($this, 'pricing_tab'));
			add_action('sikshya_course_tab_media', array($this, 'media_tab'));
			add_action('sikshya_course_curriculum_tab_before', array($this, 'curriculum_tab_before'));
			add_action('sikshya_course_curriculum_tab_lesson_quiz_template', array($this, 'curriculum_tab_lesson_quiz'), 10, 1);
			add_action('sikshya_course_tab_others', array($this, 'others_tab'));


		}

		public function curriculum_tab_before()
		{
			global $post;

			$post_id = isset($post->ID) ? absint($post->ID) : 0;

			if ($post_id > 0) {

				$sections = array();

				if ($post instanceof \WP_Post) {

					$sections = sikshya()->section->get_all_by_course($post_id);
				}

				foreach ($sections as $section) {

					$section_data = array(
						'section_id' => $section->ID,
						'section_title' => $section->post_title,
					);
					sikshya_load_admin_template('metabox.course.tabs.curriculum.section-template', $section_data);
				}

			}

		}

		public function curriculum_tab_lesson_quiz($section_id)
		{
			global $post;

			$post_id = isset($post->ID) ? absint($post->ID) : 0;

			$section_id = absint($section_id) > 0 ? absint($section_id) : 0;

			if ($post_id > 0 && $section_id > 0) {

				$lesson_and_quizes = array();

				if ($post instanceof \WP_Post) {

					$lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section_id);
				}

				foreach ($lesson_and_quizes as $lesson_and_quize) {

					$type = $lesson_and_quize->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE ? 'lesson_ids' : 'quiz_ids';

					$icon = $lesson_and_quize->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE ? 'dashicons-media-text' : 'dashicons-clock';

					$post_title = '' == $lesson_and_quize->post_title ? '(no-title)' : $lesson_and_quize->post_title;

					$order_number = (absint(get_post_meta($lesson_and_quize->ID, 'sikshya_order_number', true)));

					$lesson_quiz_datas = array(
						'id' => $lesson_and_quize->ID,
						'title' => $post_title,
						'type' => $type,
						'icon' => $icon,
						'section_id' => $section_id,
						'order_number' => $order_number
					);
					sikshya_load_admin_template('metabox.course.tabs.curriculum.lesson-quiz-template', $lesson_quiz_datas);
				}

			}

		}

		public function curriculum_tab()
		{
			$active_tab = $this->active_tab;

			sikshya_load_admin_template('metabox.course.tabs.curriculum', array(
				'active_tab' => $active_tab
			));

		}

		public function general_tab()
		{
			sikshya_load_admin_template('metabox.course.tabs.general', $this->course_meta);
		}

		public function requirements_tab()
		{
			sikshya_load_admin_template('metabox.course.tabs.requirements', $this->course_meta);
		}

		public function outcomes_tab()
		{
			sikshya_load_admin_template('metabox.course.tabs.outcomes', $this->course_meta);
		}

		public function pricing_tab()
		{
			sikshya_load_admin_template('metabox.course.tabs.pricing', $this->course_meta);
		}

		public function media_tab()
		{
			sikshya_load_admin_template('metabox.course.tabs.media', $this->course_meta);
		}

		public function course_meta()
		{
			if (SIKSHYA_COURSES_CUSTOM_POST_TYPE !== get_post_type()) {
				return;
			}
			$tabs = array(
				'curriculum' => array(
					'title' => esc_html__('Curriculum', 'sikshya'),
				),
				'general' => array(
					'title' => esc_html__('General', 'sikshya'),
				),
				'requirements' => array(
					'title' => esc_html__('Requirements', 'sikshya'),
				),
				'outcomes' => array(
					'title' => esc_html__('Outcomes', 'sikshya'),
				),
				'pricing' => array(
					'title' => esc_html__('Pricing', 'sikshya'),
				),
				'media' => array(
					'title' => esc_html__('Media', 'sikshya'),
				)
			);

			wp_nonce_field(SIKSHYA_FILE, SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce');

			sikshya_load_admin_template('metabox.course.options', array(
				'active_tab' => $this->active_tab,
				'tabs' => $tabs
			));

		}

		public function init_meta_data()
		{

			global $post;
			$course_id = isset($post->ID) ? $post->ID : 0;
			$active_tab = get_post_meta($course_id, 'sikshya_course_active_tab', true);
			$this->active_tab = $active_tab != '' ? $active_tab : $this->active_tab;
			$this->course_meta = sikshya()->course->get_course_meta();

		}

		public function metabox($course_id)
		{

			$this->init_meta_data();

			add_action('edit_form_after_editor', array($this, 'course_options_meta'));


		}

		public function course_options_meta($post)
		{

			if (SIKSHYA_COURSES_CUSTOM_POST_TYPE !== get_post_type()) {
				return;
			}

			sikshya_load_admin_template('metabox.course.main');

		}

		public function save($post_id)
		{


			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}


			if (empty($_POST[SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce']) || !wp_verify_nonce($_POST[SIKSHYA_COURSES_CUSTOM_POST_TYPE . '_nonce'], SIKSHYA_FILE)) {
				return;
			}


			if (!current_user_can('edit_post', $post_id)) {
				return;
			}

			remove_action('save_post', array($this, 'save'));

			$course_object = get_post($post_id);


			if ($course_object->post_type != SIKSHYA_COURSES_CUSTOM_POST_TYPE) {
				return;
			}


			$valid_sikshya_info = array();


			/// Sikshya Information Validation

			$valid_sikshya_info['sikshya_course_duration'] = isset($_POST['sikshya_course_duration']) ? sikshya_maybe_absint($_POST['sikshya_course_duration']) : '';
			$valid_sikshya_info['sikshya_course_duration_time'] = isset($_POST['sikshya_course_duration_time']) ? sanitize_text_field($_POST['sikshya_course_duration_time']) : '';
			$valid_sikshya_info['sikshya_course_level'] = isset($_POST['sikshya_course_level']) ? sanitize_text_field($_POST['sikshya_course_level']) : '';
			$valid_sikshya_info['sikshya_instructor'] = isset($_POST['sikshya_instructor']) ? absint($_POST['sikshya_instructor']) : 0;
			$requirements = isset($_POST['sikshya_course_requirements']) ? ($_POST['sikshya_course_requirements']) : array();

			foreach ($requirements as $requirement) {

				$valid_sikshya_info['sikshya_course_requirements'][] = sanitize_text_field($requirement);
			}
			if (!isset($valid_sikshya_info['sikshya_course_requirements'])) {
				$valid_sikshya_info['sikshya_course_requirements'] = array('');
			}


			$outcomes = isset($_POST['sikshya_course_outcomes']) ? ($_POST['sikshya_course_outcomes']) : array();

			foreach ($outcomes as $outcome) {

				$valid_sikshya_info['sikshya_course_outcomes'][] = sanitize_text_field($outcome);

			}
			if (!isset($valid_sikshya_info['sikshya_course_outcomes'])) {
				$valid_sikshya_info['sikshya_course_outcomes'] = array('');
			}
			$valid_sikshya_info['sikshya_course_video_source'] = isset($_POST['sikshya_course_video_source']) ? sanitize_text_field($_POST['sikshya_course_video_source']) : 'youtube';
			$valid_sikshya_info['sikshya_course_youtube_video_url'] = isset($_POST['sikshya_course_youtube_video_url']) ? esc_url($_POST['sikshya_course_youtube_video_url']) : '';

			$valid_sikshya_info['sikshya_course_regular_price'] = isset($_POST['sikshya_course_regular_price']) && !empty($_POST['sikshya_course_regular_price']) ? absint($_POST['sikshya_course_regular_price']) : '';
			$valid_sikshya_info['sikshya_course_discounted_price'] = isset($_POST['sikshya_course_discounted_price']) && !empty($_POST['sikshya_course_discounted_price']) ? absint($_POST['sikshya_course_discounted_price']) : '';

			foreach ($valid_sikshya_info as $info_key => $info) {

				update_post_meta($post_id, $info_key, $info);

			}

			$sikshya_course_active_tab = isset($_POST['sikshya_course_active_tab']) ? sanitize_text_field($_POST['sikshya_course_active_tab']) : $this->active_tab;
			update_post_meta($post_id, 'sikshya_course_active_tab', $sikshya_course_active_tab);

			if (($valid_sikshya_info['sikshya_instructor']) > 0) {

				if (!sikshya()->role->has_instructor($valid_sikshya_info['sikshya_instructor'])) {

					sikshya()->role->add_instructor_role($valid_sikshya_info['sikshya_instructor']);

				}
			}

			$this->update_other_meta($post_id);

		}

		private function update_other_meta($post_id)
		{

			$sikshya_course_content = isset($_POST['sikshya_course_content']) ? $_POST['sikshya_course_content'] : array();

			$lesson_quiz_order = isset($_POST['sikshya_lesson_quiz_order']) ? $_POST['sikshya_lesson_quiz_order'] : array();

			$section_ids = array_unique(array_keys($sikshya_course_content));

			if (count($section_ids) > 0) {

				$saved_section_ids = sikshya()->section->save($section_ids, $post_id);
			}

			foreach ($sikshya_course_content as $section_id => $course_content) {

				$section_id = absint($section_id);

				$lesson_ids = isset($course_content['lesson_ids']) ? $course_content['lesson_ids'] : array();

				$quiz_ids = isset($course_content['quiz_ids']) ? $course_content['quiz_ids'] : array();


				if (count($lesson_ids) > 0) {

					$saved_lesson_ids = sikshya()->lesson->save($lesson_ids, $section_id, $lesson_quiz_order);
				}

				if (count($quiz_ids) > 0) {

					$saved_quizzes_ids = sikshya()->quiz->save(
						$quiz_ids, $section_id, SIKSHYA_COURSES_CUSTOM_POST_TYPE, $lesson_quiz_order);
				}

			}


		}


	}
}
