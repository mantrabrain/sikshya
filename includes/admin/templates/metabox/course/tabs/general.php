<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="sikshya_course_duration"><?php echo esc_html__('Course Duration', 'sikshya') ?></label>
	</div>
	<div class="sikshya-field-content">
		<input class="widefat" id="sikshya_course_duration" name="sikshya_course_duration" type="number"
			   value="<?php echo esc_attr($sikshya_course_duration); ?>"
			   placeholder="<?php echo esc_attr__('Course Duration', 'sikshya') ?>">

		<select name="sikshya_course_duration_time" id="sikshya_course_duration_time">
			<?php
			$sikshya_duration_times = sikshya_duration_times();

			foreach ($sikshya_duration_times as $time_id => $duration_time) {
				?>
				<option
					value="<?php echo esc_attr($time_id); ?>" <?php echo selected($time_id, $sikshya_course_duration_time); ?>><?php echo esc_html($duration_time) ?></option>
			<?php } ?>
		</select>
	</div>

</div>

<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="sikshya_course_level"><?php echo esc_html__('Level', 'sikshya') ?></label>
	</div>
	<div class="sikshya-field-content">
		<select name="sikshya_course_level" id="sikshya_course_level">
			<?php
			$sikshya_course_levels = sikshya_course_levels();

			foreach ($sikshya_course_levels as $level_id => $course_level) {
				?>
				<option
					value="<?php echo esc_attr($level_id); ?>" <?php echo selected($level_id, $sikshya_course_level); ?>><?php echo esc_html($course_level) ?></option>
			<?php } ?>
		</select>
	</div>

</div>
<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="sikshya_instructor"><?php echo esc_html__('Instructor', 'sikshya') ?></label>
	</div>
	<div class="sikshya-field-content">
		<select name="sikshya_instructor" id="sikshya_instructor">

			<?php
			$instructors = sikshya_get_instructors_list();

			foreach ($instructors as $instructor_id => $instructor) {

				?>
				<option <?php echo selected($instructor_id, $sikshya_instructor); ?>
					value="<?php echo absint($instructor_id) ?>"><?php echo $instructor->name; ?></option>
				<?php
			}
			?>
		</select>
	</div>

</div>

<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="sikshya_course_maximum_students"><?php echo esc_html__('Maximum Students', 'sikshya') ?></label>
	</div>
	<div class="sikshya-field-content">
		<input class="widefat" id="sikshya_course_maximum_students" name="sikshya_course_maximum_students" type="number"
			   value="<?php echo esc_attr($sikshya_course_maximum_students); ?>"
			   placeholder="<?php echo esc_attr__('Maximum Students', 'sikshya') ?>">
	</div>

</div>
