<?php

$sections = sikshya()->section->get_all_by_course(get_the_ID());

$child_counts = sikshya()->course->get_all_child_count(get_the_ID());

$course_meta = sikshya()->course->get_course_meta(get_the_ID());

$duration_times = sikshya_duration_times();

$sikshya_course_duration_time = $course_meta['sikshya_course_duration_time'];

$total_time = $course_meta['sikshya_course_duration'];

$total_time .= isset($duration_times[$sikshya_course_duration_time]) ? ' ' . $duration_times[$sikshya_course_duration_time] : '';

?>
<div class="course-curriculum-box">
	<?php
	do_action('sikshya_before_single_course_curriculum_box');
	?>
	<div class="course-curriculum-title clearfix">
		<div class="title float-left"><?php echo esc_html__('Curriculum for this course', 'sikshya'); ?></div>
		<div class="float-right">
            <span class="total-lectures"><?php
				echo isset($child_counts[SIKSHYA_LESSONS_CUSTOM_POST_TYPE]) ? absint($child_counts[SIKSHYA_LESSONS_CUSTOM_POST_TYPE]) : 0;
				echo ' Lessons'
				?>
            </span>
			<span class="total-lectures"><?php
				echo isset($child_counts[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE]) ? absint($child_counts[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE]) : 0;
				echo ' Quizzes'
				?>
            </span>
			<?php if ('' !== $course_meta['sikshya_course_duration']) { ?>
				<span class="total-time"><?php echo esc_html($total_time); ?></span>
			<?php } ?>
		</div>
	</div>
	<div class="course-curriculum-accordion">
		<?php

		$index = 0;

		if (count($sections) > 0) {

			foreach ($sections as $section) {

				$index++;
				$section_child_count = sikshya()->section->get_all_child_count($section->ID);
				$toggle_class = $index == 1 ? 'icon dashicons dashicons-minus' : 'icon dashicons dashicons-plus';

				?>
				<div class="lecture-group-wrapper">
					<div class="lecture-group-title clearfix ">
						<div class="title float-left">
							<span class="<?php echo esc_attr($toggle_class); ?>"></span>
							<?php
							echo '' !== ($section->post_title) ? esc_html($section->post_title) : '(no-title)';

							?>
						</div>
						<div class="float-right">
                            <span class="total-lectures">
                                <?php
								echo $section_child_count[SIKSHYA_LESSONS_CUSTOM_POST_TYPE];
								echo ' Lessons'
								?>
                            </span>
							<span class="total-lectures"><?php
								echo $section_child_count[SIKSHYA_QUIZZES_CUSTOM_POST_TYPE];
								echo ' Quizzes'
								?>
                            </span>

						</div>
					</div>

					<?php
					$lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section->ID);

					if (count($lesson_and_quizes) > 0) {


						?>
						<div class="lecture-list <?php echo $index == 1 ? 'show block' : ''; ?>">
							<ul>
								<?php foreach ($lesson_and_quizes as $lesson_and_quiz) {
									$total_lesson_count_from_section = sikshya()->lesson->count_total_from_section_id($section->ID);

									$class = $lesson_and_quiz->post_type === SIKSHYA_LESSONS_CUSTOM_POST_TYPE ? 'lesson' : 'quiz';

									$href = sikshya_is_content_available_for_user($lesson_and_quiz->ID, $lesson_and_quiz->post_type) ? get_post_permalink($lesson_and_quiz->ID) : '#';

									?>
									<li class="lecture has-preview <?php echo esc_attr($class); ?>">
										<a href="<?php echo esc_attr($href) ?>">
                                        <span class="lecture-title">
                                            <?php if ($lesson_and_quiz->post_type === SIKSHYA_LESSONS_CUSTOM_POST_TYPE) { ?>
												<span class="icon dashicons dashicons-media-text"></span><?php
											} else {
												echo '<span class="icon dashicons dashicons-clock"></span>';
											}
											echo '' !== ($lesson_and_quiz->post_title) ? esc_html($lesson_and_quiz->post_title) : '(no-title)';
											?>
                                        </span>
											<?php
											$total_lesson_time_string = get_post_meta($lesson_and_quiz->ID, 'sikshya_lesson_duration', true);
											$sikshya_lesson_duration_time = get_post_meta($lesson_and_quiz->ID, 'sikshya_lesson_duration_time', true);

											$total_lesson_time = $total_lesson_time_string;
											$total_lesson_time .= isset($duration_times[$sikshya_lesson_duration_time]) ? ' ' . $duration_times[$sikshya_lesson_duration_time] : '';

											?>
											<?php if ('' != $total_lesson_time_string) { ?>
												<span
													class="lecture-time float-right"><?php echo esc_html($total_lesson_time); ?></span>
											<?php } ?>
										</a>
									</li>
								<?php } ?>
							</ul>
						</div>
						<?php
					} ?>
				</div>
				<?php

			}
		}
		?>
	</div>
</div>
