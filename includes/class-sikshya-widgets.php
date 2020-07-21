<?php

class Sikshya_Widgets
{
	public function __construct()
	{
		$this->includes();

		add_action('widgets_init', array($this, 'init_widgets'));


	}

	public function init_widgets()
	{

		register_widget('Sikshya_Courses_Widget');


	}

	public function includes()
	{


		require SIKSHYA_PATH . '/includes/widgets/class-sikshya-widget-base.php';
		require SIKSHYA_PATH . '/includes/widgets/class-sikshya-widget-validation.php';
		require SIKSHYA_PATH . '/includes/widgets/class-sikshya-courses.php';


	}

}

new Sikshya_Widgets();
