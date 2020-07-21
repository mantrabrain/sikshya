<?php


class Sikshya_Courses_Widget extends Sikshya_Widget_Base
{

	/**
	 * Register widget with WordPress.
	 */
	public function __construct()
	{
		$widget_ops = array(
			'classname' => 'sikshya_courses_widget',
			'description' => __('List all courses', 'sikshya')
		);
		parent::__construct('sikshya_courses_widget', __('Sikshya::Courses', 'sikshya'), $widget_ops);
	}

	public function widget_fields()
	{


		$fields = array(

			'widget_title' => array(
				'name' => 'widget_title',
				'title' => esc_html__('Title', 'sikshya'),
				'type' => 'text',
				'default' => esc_html__('', 'sikshya'),

			)
		);

		return $fields;
	}

	function widget($args, $instance_arg)
	{


		$instance = Sikshya_Widget_Validation::instance()->validate($instance_arg, $this->widget_fields());

		echo $args['before_widget'];

		?>
		<div class="sikshya-courses-widget-wrapper">

			<?php echo $args['before_title'] . $instance['widget_title'] . $args['after_title'];

			global $wp_query;

			$wp_query = new WP_Query('post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE);

			sikshya_load_template('parts.course.archive-content');

			wp_reset_postdata();

			?>

		</div><!-- .sikshya-courses-widget-wrapper -->
		<?php

		echo $args['after_widget'];
	}
}
