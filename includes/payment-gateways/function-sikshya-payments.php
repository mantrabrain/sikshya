<?php
if (!function_exists('sikshya_get_payment_gateways')) {
	function sikshya_get_payment_gateways()
	{
		$gateways = array(/* array(
                'title' => __('Another Gateway', 'sikshya'),
                'default' => 'no',
                'id' => 'manual'
            ),*/


		);

		return apply_filters('sikshya_payment_gateways', $gateways);
	}
}

if (!function_exists('sikshya_get_active_payment_gateways')) {

	function sikshya_get_active_payment_gateways()
	{
		$sikshya_payment_gateways = get_option('sikshya_payment_gateways', array());

		return array_keys($sikshya_payment_gateways);
	}
}

function sikshya_payment_gateway_test_mode()
{

	$is_test_mode = get_option('sikshya_payment_gateway_test_mode');

	if ($is_test_mode == 'yes') {
		return true;
	}
	return false;
}

function sikshya_update_payment_status($booking_id)
{
	if (!$booking_id || $booking_id < 1) {
		return;
	}
	$payment_id = get_post_meta($booking_id, 'sikshya_payment_id', true);
	if (!$payment_id) {
		$title = 'Payment - #' . $booking_id;
		$post_array = array(
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'publish',
			'post_slug' => uniqid(),
			'post_type' => 'sikshya-payment',
		);
		$payment_id = wp_insert_post($post_array);

		update_post_meta($booking_id, 'sikshya_payment_id', $payment_id);

		$booking_details = new Yatra_Tour_Booking($booking_id);

		$booking_details->get_all_booking_details();

		update_post_meta($payment_id, 'booking_details', $booking_details);

	}

}


