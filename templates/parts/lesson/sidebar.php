<div id="sikshya-lesson-sidebar-tab-content" class="sikshya-lesson-sidebar-tab-item">

	<?php

	$post_id = get_the_ID();

	$section_id = sikshya()->section->get_id();

	$course_id = sikshya()->course->get_id();

	$sections = sikshya()->section->get_all_by_course($course_id);

	$section_num = 0;

	foreach ($sections as $section_index => $section) {

		$section_num++;

		$section_active_class = $section->ID == $section_id ? 'sikshya-section-active' : '';
		?>

		<div
			class="sikshya-sections-in-single-lesson sikshya-sections-<?php echo absint($section->ID) ?> <?php echo esc_attr($section_active_class); ?>">
			<div class="sikshya-sections-title ">
				<span class="section-num">Section <?php echo absint($section_num); ?></span>
				<h6 class="section-title">

					<?php echo esc_html($section->post_title); ?>
				</h6>
				<button class="sikshya-single-lesson-topic-toggle">
					<i class="dashicons <?php echo $section->ID == $section_id ? 'dashicons-minus' : 'dashicons-plus'; ?>"></i>
				</button>
			</div>

			<div class="sikshya-lessons-under-section"
				 style="<?php echo $section->ID == $section_id ? '' : 'display:none;'; ?>">
				<?php
				$lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section->ID);

				foreach ($lesson_and_quizes as $lesson_and_quize) {

					$sikshya_lesson_class = sikahy_is_active_lesson_quizes($lesson_and_quize->ID) ? 'active' : '';

					$is_lesson_completed = sikshya()->lesson->is_completed($lesson_and_quize->ID, 0, $lesson_and_quize->post_type);

					$sikshya_lesson_class .= $is_lesson_completed ? ' lesson-completed' : '';

					?>

					<div class="sikshya-single-lesson-items <?php echo esc_attr($sikshya_lesson_class); ?>">
						<a href="<?php echo esc_url(get_permalink($lesson_and_quize->ID)) ?>"
						   class="sikshya-single-lesson-a"
						   data-lesson-id="<?php echo absint($lesson_and_quize->ID); ?>"
						   data-post-type="<?php echo esc_attr($lesson_and_quize->post_type); ?>">
							<?php if ($lesson_and_quize->post_type == SIKSHYA_LESSONS_CUSTOM_POST_TYPE) { ?>
								<i class="dashicons dashicons-media-text"></i>
							<?php } else { ?>
								<i class="dashicons dashicons-clock"></i>
							<?php } ?>
							<span
								class="lesson_title"><?php echo $lesson_and_quize->post_title == '' ? '(no title)' : esc_html($lesson_and_quize->post_title); ?></span>
							<span class="sikshya-lesson-right-icons">

                                                <?php

												if (!sikshya_is_content_available_for_user($lesson_and_quize->ID, SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {
													echo '<i class="sikshya-content-locked dashicons dashicons-admin-network"></i>';
												}

												if ($is_lesson_completed) { ?>
													<i class="dashicons dashicons-yes-alt"></i>
												<?php } ?>

                                            </span>
						</a>
					</div>
				<?php } ?>


			</div>
		</div>
	<?php } ?>


</div>
