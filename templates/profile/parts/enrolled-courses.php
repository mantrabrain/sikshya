<h2><?php echo __('Enrolled Courses', 'sikshya') ?></h2>

<table class="sikshya-list-table profile-list-courses profile-list-table">
    <thead>
    <tr>
        <th class="column-course"><?php esc_html_e('Course', 'sikshya') ?></th>
        <th class="column-enrolled-date"><?php esc_html_e('Enrolled Date', 'sikshya') ?></th>
        <th class="column-total-lessons"><?php esc_html_e('Total Lessons', 'sikshya') ?></th>
        <th class="column-completed-lessons"><?php esc_html_e('Completed Lessons', 'sikshya') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php $course_list = isset($courses['list']) ? $courses['list'] : array();
    foreach ($course_list as $list) { ?>
        <tr>
            <td class="column-course">
                <a target="_blank" href="<?php echo esc_url($list['permalink']) ?>"><?php echo esc_html($list['course_title']); ?> </a>
            </td>
            <td class="column-enrolled-date"><?php echo esc_html($list['enrolled_date']); ?></td>
            <td class="column-total-lessons"><?php echo esc_html($list['total_lessons']); ?></td>
            <td class="column-completed-lessons"><?php echo esc_html($list['completed_lessons']); ?></td>
        </tr>
    <?php } ?>
    </tbody>
    <tfoot>

    </tfoot>
</table>