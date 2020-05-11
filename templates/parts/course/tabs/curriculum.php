<?php

$sections = sikshya()->section->get_all_by_course(get_the_ID());

if (count($sections) > 0) {

    echo '<ul class="sikshya-section-list">';

    foreach ($sections as $section) {

        echo '<li>';

        echo '<a class="item-link">';

        echo '<span class="item-name">';

        echo '<i class="dashicons dashicons-menu"></i>';

        echo $section->post_title == '' ? '(no title)' : esc_html($section->post_title);

        echo '</span>';

        echo '<span class="item-meta">';

        echo '<small class="item-count">';

        sikshya()->section->get_child_count_text($section->ID);

        echo '</small>';

        echo '</span>';

        echo '</a>';

        $lesson_and_quizes = sikshya()->section->get_lesson_and_quiz($section->ID);

        if (count($lesson_and_quizes) > 0) {

            echo '<ul class="sikshya-lesson-list">';

            foreach ($lesson_and_quizes as $lesson_and_quiz) {

                echo '<li>';

                echo '<a class="item-link" href="' . esc_url(get_post_permalink($lesson_and_quiz->ID)) . '">';


                echo '<span class="item-name">';

                if ($lesson_and_quiz->post_type == SIKSHYA_QUIZZES_CUSTOM_POST_TYPE) {
                    echo '<i class="dashicons dashicons-clock"></i>';

                } else {
                    echo '<i class="dashicons dashicons-media-text"></i>';
                }

                echo $lesson_and_quiz->post_title == '' ? '(no title)' : esc_html($lesson_and_quiz->post_title);

                echo '</span>';

                echo '<span class="item-meta">';

                if (!sikshya_is_content_available_for_user($lesson_and_quiz->ID, SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {

                    echo '<i class="dashicons dashicons-admin-network"></i>';
                }
                echo '</span>';

                echo '</a>';

                $quizzes = isset($lesson_and_quiz->quizzes) ? $lesson->quizzes : array();

                if (count($quizzes) > 0) {

                    echo '<ul class="sikshya-quiz-list">';

                    foreach ($quizzes as $quiz) {

                        echo '<li>';

                        echo '<a class="item-link" href="' . esc_url(sikshya()->quiz->get_permalink($quiz->ID)) . '">';

                        echo '<span class="item-name">';

                        echo $quiz->post_title == '' ? '(no title)' : esc_html($quiz->post_title);

                        echo '</span>';

                        echo '<span class="item-meta">';

                        if (!sikshya_is_content_available_for_user($quiz->ID, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE)) {

                            echo '<i class="dashicons dashicons-admin-network"></i>';
                        }
                        echo '</span>';

                        echo '</a>';

                        echo '</li>';
                    }


                    echo '</ul>';
                }

                echo '</li>';
            }

            echo '</ul>';
        }


        echo '</li>';
    }
    echo '</ul>';
}