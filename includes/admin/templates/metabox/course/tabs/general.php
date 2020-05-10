<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_duration"><?php echo esc_html__('Course Duration', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <input class="widefat" id="sikshya_course_duration" name="sikshya_course_duration" type="number"
               value="<?php echo esc_attr($sikshya_course_duration); ?>"
               placeholder="<?php echo esc_attr__('Course Duration', 'sikshya') ?>">

        <select name="sikshya_course_duration_time" id="sikshya_course_duration_time">
            <option value="minute" <?php echo selected('minute', $sikshya_course_duration_time); ?>><?php echo esc_html__('Minute(s)', 'sikshya') ?></option>
            <option value="hour" <?php echo selected('hour', $sikshya_course_duration_time); ?>><?php echo esc_html__('Hour(s)', 'sikshya') ?></option>
            <option value="day" <?php echo selected('day', $sikshya_course_duration_time); ?>><?php echo esc_html__('Day(s)', 'sikshya') ?></option>
            <option value="week" <?php echo selected('week', $sikshya_course_duration_time); ?>><?php echo esc_html__('Week(s)', 'sikshya') ?></option>
        </select>
    </div>

</div>

<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_level"><?php echo esc_html__('Level', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <select name="sikshya_course_level" id="sikshya_course_level">
            <option value="all" <?php echo selected('all', $sikshya_course_level); ?>><?php echo esc_html__('All Levels', 'sikshya') ?></option>
            <option value="beginner" <?php echo selected('beginner', $sikshya_course_level); ?>><?php echo esc_html__('Beginner', 'sikshya') ?></option>
            <option value="intermediate" <?php echo selected('intermediate', $sikshya_course_level); ?>><?php echo esc_html__('Intermediate', 'sikshya') ?></option>
            <option value="expert" <?php echo selected('expert', $sikshya_course_level); ?>><?php echo esc_html__('Expert', 'sikshya') ?></option>
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
                <option <?php echo selected($instructor_id, $sikshya_instructor);?>
                        value="<?php echo absint($instructor_id) ?>"><?php echo $instructor->name; ?></option>
                <?php
            }
            ?>
        </select>
    </div>

</div>