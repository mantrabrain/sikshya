<?php

echo '<h2 class="sikshya-question-title">';

echo get_the_title();

echo '</h2>';

echo '<div class="sikshya-question-content">';

echo apply_filters('the_content', get_post_field('post_content', get_the_ID()));

echo '</div>';

