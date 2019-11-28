<?php
$sections = sikshya()->course->get_all_sections(get_the_ID());

if (count($sections) > 0) {

    echo '<ul class="sikshya-section-list">';

    foreach ($sections as $section) {

        echo '<li>';

        echo '<a class="item-link">';

        echo '<span class="item-name">' . esc_html($section->post_title) . '</span>';

        echo '<span class="item-meta">';

        echo '<small class="item-count">';

        sikshya()->section->get_child_count_text($section->ID);

        echo '</small>';

        echo '</span>';

        echo '</a>';

        $lessons = isset($section->lessons) ? $section->lessons : array();

        if (count($lessons) > 0) {

            echo '<ul class="sikshya-lesson-list">';

            foreach ($lessons as $lesson) {

                echo '<li>';

                echo '<a class="item-link" href="' . esc_url(get_post_permalink($lesson->ID)) . '">';

                echo '<span class="item-name">';

                echo esc_html($lesson->post_title);

                echo '</span>';

                echo '<span class="item-meta">';

                if (!sikshya_is_content_available_for_user($lesson->ID, SIKSHYA_LESSONS_CUSTOM_POST_TYPE)) {

                    echo '<span class="dashicons dashicons-admin-network"></span>';
                }
                echo '</span>';

                echo '</a>';

                $quizzes = isset($lesson->quizzes) ? $lesson->quizzes : array();

                if (count($quizzes) > 0) {

                    echo '<ul class="sikshya-quiz-list">';

                    foreach ($quizzes as $quiz) {

                        echo '<li>';

                        echo '<a class="item-link" href="' . esc_url(sikshya()->quiz->get_permalink($quiz->ID)) . '">';

                        echo '<span class="item-name">';

                        echo esc_html($quiz->post_title);

                        echo '</span>';

                        echo '<span class="item-meta">';

                        if (!sikshya_is_content_available_for_user($quiz->ID, SIKSHYA_QUIZZES_CUSTOM_POST_TYPE)) {

                            echo '<span class="dashicons dashicons-admin-network"></span>';
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