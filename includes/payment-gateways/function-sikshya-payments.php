<?php
if (!function_exists('sikshya_get_payment_gateways')) {
	function sikshya_get_payment_gateways()
	{

		$all_gateways = apply_filters('sikshya_payment_gateways', array());
		foreach ($all_gateways as $gateway_index => $gateway) {
			$gateway_id = isset($gateway['id']) ? $gateway['id'] : null;
			$all_gateways[$gateway_index]['description'] = get_option("sikshya_payment_gateway_{$gateway_id}_description");
			$all_gateways[$gateway_index]['image_url'] = get_option("sikshya_payment_gateway_{$gateway_id}_image_url");
			$all_gateways[$gateway_index]['help_text'] = get_option("sikshya_payment_gateway_{$gateway_id}_help_text");
			$all_gateways[$gateway_index]['help_url'] = get_option("sikshya_payment_gateway_{$gateway_id}_help_url");
		}
		return $all_gateways;
	}
}

if (!function_exists('sikshya_get_active_payment_gateways')) {

	function sikshya_get_active_payment_gateways()
	{
		$sikshya_payment_gateways = get_option('sikshya_payment_gateways', array());

		return array_keys($sikshya_payment_gateways);
	}
}


function sikshya_update_payment_status($order_id)
{
	if (!$order_id || $order_id < 1) {
		return;
	}
	$payment_id = get_post_meta($order_id, 'sikshya_payment_id', true);
	if (!$payment_id) {
		$title = 'Payment - #' . $order_id;
		$post_array = array(
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'publish',
			'post_slug' => uniqid(),
			'post_type' => 'sikshya-payment',
		);
		$payment_id = wp_insert_post($post_array);

		update_post_meta($order_id, 'sikshya_payment_id', $payment_id);

		$order_details = get_post_meta($order_id, 'sikshya_order_meta', true);

		update_post_meta($payment_id, 'order_details', $order_details);

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

function sikshya_payment_gateway_logging_enabled()
{
	if ('yes' === get_option('sikshya_payment_gateway_enable_logging', 'no')) {
		return true;
	}
	return false;
}

function sikshya_save_payment_gateway_log($source, $log_message)
{
	if (sikshya_payment_gateway_logging_enabled()) {

		$logger = sikshya_get_logger();

		$log_message = is_array($log_message) || is_object($log_message) ? json_encode($log_message) : $log_message;


		$logger->info($log_message, array('source' => $source));
	}

}

