<div id="admin-editor-sik_course" class="sik-admin-editor sik-box-data admin-editor-sik-course"
     data-quiz-id="<?php echo esc_attr($quiz_id) ?>" data-question-id="<?php echo esc_attr($question_id) ?>">
    <div class="sik-box-data-head sik-row">
        <h3 class="heading"><?php echo __('Course Options', 'sikshya') ?></h3>
    </div>
    <div class="sik-box-data-content">
        <div class="sik-box-body-content">
            <div class="sikshya-course-option-container">


                <input type="hidden" class="js-courses_info"
                       data-tooltip="<?php _e('Here you can change basic information of your courses.', 'sikshya'); ?>">
                <div class="ms-metabox-course-info sikshya-metabox-course-info">
                    <div class="ms-field ms-field-large">
                        <label class="ms-label js-sikshya-tooltip-element"
                               for="sikshya_info_subject"
                               data-tooltip="<?php _e('Course subject”.', 'sikshya'); ?>"
                        ><?php _e('Subject', 'sikshya'); ?>:</label>
                        <div class="ms-value">
                            <input type="text" class="ms-big" id="sikshya_info_subject"
                                   name="sikshya_info[subject]"<?php if (!empty($template_vars['subject'])) { ?> value="<?php echo esc_attr($template_vars['subject']); ?>"<?php } ?> />
                        </div>
                    </div>
                    <div class="ms-field ms-field-large">
                        <label class="ms-label js-sikshya-tooltip-element"
                               for="sikshya_info_level"
                               data-tooltip="<?php _e('Here you can change the difficulty level of your courses, e.g. “beginner, advanced or expert”', 'sikshya'); ?>"
                        ><?php _e('Level', 'sikshya'); ?>:</label>
                        <div class="ms-value">
                            <?php $template_vars['level'] = !isset($template_vars['level']) ? 'all' : $template_vars['level']; ?>
                            <select class="ms-big" name="sikshya_info[level]" id="sikshya_info_level">
                                <?php foreach (array_merge(array('' => ''), sikshya_get_course_level()) as $level_key => $level_value) { ?>
                                    <option value="<?php echo esc_attr($level_key); ?>"<?php if (!empty($template_vars['level']) && $template_vars['level'] == $level_key) { ?> selected="selected"<?php } ?>><?php echo esc_html($level_value); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="ms-group">
                        <div class="ms-group-item">
                            <div class="ms-field ms-field-large">
                                <label class="ms-label js-sikshya-tooltip-element"
                                       for="sikshya_info_duration"
                                       data-tooltip="<?php _e('Write down the approximate length of your course, e.g. 12 hours', 'sikshya'); ?>"
                                ><?php _e('Duration', 'sikshya'); ?>:</label>
                                <div class="ms-value">
                                    <input class="ms-small" type="text" id="sikshya_info_duration"
                                           name="sikshya_info[duration]"
                                           size="4"<?php if (!empty($template_vars['duration'])) { ?> value="<?php echo esc_attr($template_vars['duration']); ?>"<?php } ?> />
                                    <span class="ms-text ms-text-light"><?php _e('Hours', 'sikshya'); ?></span>
                                </div>
                            </div>


                        </div>
                    </div>
                    <div class="ms-group">
                        <div class="ms-group-item">
                            <div class="ms-field ms-field-large">
                                <label class="ms-label js-sikshya-tooltip-element"
                                       for="sikshya_info_duration"
                                       data-tooltip="<?php _e('Instructor', 'sikshya'); ?>"
                                ><?php _e('Instructor', 'sikshya'); ?>:</label>
                                <div class="ms-value">
                                    <select class="ms-field-large" type="text" id="sikshya_info_instructor"
                                            name="sikshya_info[instructor]">
                                        <?php
                                        $instructors = sikshya_get_instructors_list();
                                        $instructor_id_val = isset($template_vars['instructor']) ? absint($template_vars['instructor']) : 0;

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


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


