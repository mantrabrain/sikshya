<?php

echo '<div class="sikshya-answer-content">';

echo '<label for="sikshya-answer-' . get_the_ID() . '_' . esc_attr($answer_key) . '">';

echo '<input data-answer-id="' . esc_attr($answer_key) . '" class="sikshya-answer-item" type="radio" name="sikshya-answer-' . get_the_ID() . '" id="sikshya-answer-' . get_the_ID() . '_' . esc_attr($answer_key) . '" value="1"/>';

echo '<span class="sikshya-answer-title">' . esc_html($answer['value']) . '</span>';

echo '</label>';

echo '</div>';

