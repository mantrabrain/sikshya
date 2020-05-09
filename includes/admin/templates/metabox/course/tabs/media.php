<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_duration"><?php echo esc_html__('Course Duration', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <input class="widefat" id="sikshya_course_duration" name="sikshya_course_duration" type="number" value=""
               placeholder="<?php echo esc_attr__('Course Duration', 'sikshya') ?>">
        <select name="sikshya_course_duration_time" id="sikshya_course_duration_time">
            <option value="minute"><?php echo esc_html__('Minute(s)', 'sikshya') ?></option>
            <option value="hour"><?php echo esc_html__('Hour(s)', 'sikshya') ?></option>
            <option value="day"><?php echo esc_html__('Day(s)', 'sikshya') ?></option>
            <option value="week"><?php echo esc_html__('Week(s)', 'sikshya') ?></option>
        </select>
    </div>

</div>

<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_level"><?php echo esc_html__('Level', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <select name="sikshya_course_level" id="sikshya_course_level">
            <option value="all"><?php echo esc_html__('All Levels', 'sikshya') ?></option>
            <option value="beginner"><?php echo esc_html__('Beginner', 'sikshya') ?></option>
            <option value="intermediate"><?php echo esc_html__('Intermediate', 'sikshya') ?></option>
            <option value="expert"><?php echo esc_html__('Expert', 'sikshya') ?></option>
        </select>
    </div>

</div>
<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_instructor"><?php echo esc_html__('Instructor', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <select name="sikshya_course_instructor" id="sikshya_course_instructor">

            <?php
            $instructors = sikshya_get_instructors_list();
            $instructor_id_val = isset($template_vars['instructor']) ? 0 : 0;

            foreach ($instructors as $instructor_id => $instructor) {

                ?>
                <option <?php echo $instructor_id_val == $instructor_id ? 'selected="selected"' : '' ?>
                        value="<?php echo absint($instructor_id) ?>"><?php echo $instructor->name; ?></option>
                <?php
            }
            ?>
        </select>
    </div>

</div>