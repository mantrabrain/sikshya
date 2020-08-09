<?php

class Sikshya_Order_Hooks
{

	public function __construct()
	{
		add_action('sikshya_after_place_order', array($this, 'after_place_order'), 10, 1);
		add_action('sikshya_after_order_status_change', array($this, 'after_order_status_change'), 10, 1);

	}

	public function after_place_order($order_args)
	{
		$total_cart_amount = $order_args['total_order_amount'];

		$payment_gateway_id = $order_args['payment_gateway'];

		$sikshya_order_id = $order_args['order_id'];

		$order_meta = get_post_meta($sikshya_order_id, 'sikshya_order_meta', true);

		$sikshya_get_active_payment_gateways = sikshya_get_active_payment_gateways();

		if ($total_cart_amount > 0) { // If need to redirect any payment gateway

			if (in_array($payment_gateway_id, $sikshya_get_active_payment_gateways)) {

				do_action('sikshya_payment_checkout_payment_gateway_' . $payment_gateway_id, $sikshya_order_id);

			}

		} else {
			$student_id = isset($order_meta['student_id']) ? $order_meta['student_id'] : get_current_user_id();

			sikshya()->role->add_student($student_id);

			sikshya_update_order_status($sikshya_order_id, 'sikshya-completed');
		}
	}

	public function after_order_status_change($status_data)
	{

		$order_id = isset($status_data['order_id']) ? $status_data['order_id'] : 0;
		$status = isset($status_data['status']) ? $status_data['status'] : '';
		if ($status == 'sikshya-completed') {

			sikshya()->course->enroll($order_id);

		}
	}
}

new Sikshya_Order_Hooks();
