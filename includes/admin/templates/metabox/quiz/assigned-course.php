<?php
defined('ABSPATH') || exit();

global $post;

$courses = sikshya()->course->get_all_by_quiz($post);

?>
<div class="sikshya-lesson-course-assigned">
    <?php if ($courses) { ?>
        <ul>
            <?php foreach ($courses as $course) { ?>
                <li>
                    <strong><a href="<?php echo get_edit_post_link($course->ID); ?>"
                               target="_blank"><?php echo get_the_title($course->ID); ?></a></strong>
                </li>
            <?php } ?>
        </ul>
    <?php } else {
        _e('Not assigned yet', 'sikshya');
    } ?>
</div>