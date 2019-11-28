<?php

if (!defined('ABSPATH')) {
    exit;
}

do_action('sikshya_before_single_answer');
?>

<div class="sikshya-question-answer sikshya-question-answer-loop-wrap sikshya-answer-<?php echo esc_attr($type); ?>">