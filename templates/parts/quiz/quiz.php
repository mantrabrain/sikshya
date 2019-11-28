<?php

do_action('sikshya_before_quiz_content');

echo '<h2 class="sikshya-quiz-title">';

echo get_the_title();

echo '</h2>';

echo '<div class="sikshya-quiz-content">';

echo apply_filters('the_content', get_post_field('post_content', get_the_ID()));

echo '</div>';

do_action('sikshya_after_quiz_content');
