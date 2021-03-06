<?php
defined('ABSPATH') || exit();

global $post;

$courses = sikshya()->course->get_all_by_question($post);
$quizzes = sikshya()->quiz->get_all_by_question($post);

?>
<div class="sikshya-lesson-course-assigned">
    <?php if ($courses) { ?>
        <ul class="sik-course-list-meta">
            <?php foreach ($courses as $course) { ?>
                <li>
                    <strong><a href="<?php echo get_edit_post_link($course->ID); ?>"
                               target="_blank"><?php echo get_the_title($course->ID); ?></a></strong>
                    <?php if ($quizzes) {
                        echo '<ul class="sik-course-list-meta-child">';

                        foreach ($quizzes as $quiz) {
                            ?>
                            <li><strong><a href="<?php echo get_edit_post_link($quiz->ID); ?>"
                                           target="_blank"><?php echo get_the_title($quiz->ID); ?></a></strong></li>
                            <?php
                        }
                        echo '</ul>';
                    }
                    ?>
                </li>
            <?php } ?>
        </ul>
    <?php } else {
        _e('Not assigned yet', 'sikshya');
    } ?>
</div>