<div id="sikshya-lesson-sidebar-tab-content" class="sikshya-lesson-sidebar-tab-item">

    <?php

    $post_id = get_the_ID();

    $section_id = get_post_meta($post_id, 'section_id', true);

    $course_id = get_post_meta($section_id, 'course_id', true);

    $sections = sikshya()->course->get_all_sections($course_id);

    foreach ($sections as $section) {

        $section_active_class = $section->ID == $section_id ? 'sikshya-section-active' : '';
        ?>

        <div class="sikshya-sections-in-single-lesson sikshya-sections-<?php echo absint($section->ID) ?> <?php echo esc_attr($section_active_class); ?>">
            <div class="sikshya-sections-title ">
                <h3>
                    <span class="dashicons dashicons-menu"></span> <?php echo esc_html($section->post_title); ?>
                </h3>
                <button class="sikshya-single-lesson-topic-toggle">
                    <i class="dashicons <?php echo $section->ID == $section_id ? 'dashicons-minus' : 'dashicons-plus'; ?>"></i>
                </button>
            </div>

            <div class="sikshya-lessons-under-section"
                 style="<?php echo $section->ID == $section_id ? '' : 'display:none;'; ?>">
                <?php
                $lessons = isset($section->lessons) ? $section->lessons : array();

                foreach ($lessons as $lesson) {

                    $sikshya_lesson_class = $lesson->ID == $post_id ? 'active' : '';

                    $is_lesson_completed = sikshya()->lesson->is_completed($lesson->ID);

                    $sikshya_lesson_class .= $is_lesson_completed ? ' lesson-completed' : '';

                    ?>

                    <div class="sikshya-single-lesson-items <?php echo esc_attr($sikshya_lesson_class); ?>">
                        <a href="<?php echo esc_url(get_permalink($lesson->ID)) ?>"
                           class="sikshya-single-lesson-a"
                           data-lesson-id="<?php echo absint($lesson->ID); ?>">

                            <i class="dashicons dashicons-media-text"></i> <span
                                    class="lesson_title"><?php echo esc_html($lesson->post_title); ?></span>
                            <span class="sikshya-lesson-right-icons">

                                                <?php

                                                if (!sikshya_is_content_available_for_user($lesson->ID, SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {
                                                    echo '<i class="sikshya-content-locked dashicons dashicons-admin-network"></i>';
                                                }

                                                if ($is_lesson_completed) { ?>
                                                    <i class="dashicons dashicons-yes-alt"></i>
                                                <?php } ?>

                                            </span>
                        </a>
                    </div>
                    <div class="sikshya-lessons-quiz-under-section">

                        <?php
                        $quizzes = isset($lesson->quizzes) ? $lesson->quizzes : array();

                        foreach ($quizzes as $quiz) {

                            if (sikshya_get_current_post_type() == SIKSHYA_QUESTIONS_CUSTOM_POST_TYPE) {

                                $quiz_id = get_post_meta($post_id, 'quiz_id', true);

                                $quiz_active_class = $quiz->ID == $quiz_id ? 'active' : '';

                            } else {

                                $quiz_active_class = $quiz->ID == $post_id ? 'active' : '';
                            }

                            ?>
                            <div class="sikshya-single-lesson-items <?php echo esc_attr($quiz_active_class); ?>">
                                <a href="<?php echo esc_url(sikshya()->quiz->get_permalink($quiz->ID)) ?>"
                                   class="sikshya-single-lesson-a"
                                   data-quiz-id="<?php echo absint($quiz->ID); ?>">

                                    <i class="dashicons dashicons-clock"></i> <span
                                            class="lesson_title"><?php echo esc_html($quiz->post_title); ?></span>
                                    <span class="sikshya-lesson-right-icons">
                                                        <i class="sikshya-play-duration">3 questions</i><i
                                                class="sikshya-lesson-complete "></i>
                                        <?php
                                        if (!sikshya_is_content_available_for_user($quiz->ID, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE)) {
                                            echo '<i class="sikshya-content-locked dashicons dashicons-admin-network"></i>';
                                        }
                                        ?>
                                    </span>
                                </a>
                            </div>
                        <?php }
                        ?>

                    </div>
                <?php } ?>


            </div>
        </div>
    <?php } ?>


</div>