<?php

class Sikshya_Order_Hooks
{

	public function __construct()
	{
		add_action('sikshya_after_order_status_change', array($this, 'after_order_status_change'));

	}

	public function after_order_status_change($order_id, $status)
	{

		if ($status == 'sikshya-completed') {

			sikshya()->course->enroll($order_id);

		}
	}
}

new Sikshya_Order_Hooks();
