<?php
do_action('sikshya_archive_before_loop');

if (have_posts()) :
	/* Start the Loop */

	sikshya_course_loop_start();

	while (have_posts()) : the_post();

		do_action('sikshya_course_archive_before_loop_course');

		sikshya_load_template('loop.course');

		do_action('sikshya_course_archive_after_loop_course');

	endwhile;

	sikshya_course_loop_end();

	sikshya_course_archive_pagination();
else :

	/**
	 * No course found
	 */
	sikshya_load_template('course-none');

endif;


do_action('sikshya_archive_after_loop');
