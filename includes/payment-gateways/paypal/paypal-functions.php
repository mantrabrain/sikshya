<?php

if (!function_exists('sikshya_get_paypal_api_endpoint')) {

	function sikshya_get_paypal_api_endpoint($ssl_check = false)
	{
		if (is_ssl() || !$ssl_check) {

			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}

		if (sikshya_payment_gateway_test_mode()) {

			$paypal_uri = 'https://www.sandbox.paypal.com/';
		} else {
			$paypal_uri = 'https://www.sandbox.paypal.com/cgi-bin/webscr/';
		}

		return $paypal_uri;
	}
}
if (!function_exists('sikshya_payment_gateway_paypal_validate_settings')) {

	function sikshya_payment_gateway_paypal_validate_settings()
	{

		$sikshya_payment_gateway_paypal_email = get_option('sikshya_payment_gateway_paypal_email');

		if ('' == $sikshya_payment_gateway_paypal_email) {
			return [
				'status' => false,
				'message' => __('PayPal email address not setup. Please contact site administrator', 'sikshya')
			];
		}
		return ['status' => true, 'message' => 'success'];
	}
}
