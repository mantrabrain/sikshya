<?php

class Sikshya_Model_Course
{
	public $ID;

	public $title;

	public $regular_price;

	public $discounted_price;

	public $total_price;

	public $total_price_string;

	public $quantity;

	public function __construct($course_id, $quantity = 1)
	{
		$course = get_post($course_id);
		if ($course instanceof WP_Post) {
			$this->ID = $course_id;
			$this->title = $course->post_title;
			$this->discounted_price = get_post_meta($course_id, 'sikshya_course_discounted_price');
			$this->regular_price = get_post_meta($course_id, 'sikshya_course_regular_price');
			$this->quantity = $quantity;
			$this->total_price = sikshya_get_course_total_price($course_id, $quantity);
			$this->total_price_string = sikshya_get_price_with_symbol($this->total_price);
		}
		return $this;

	}

}
