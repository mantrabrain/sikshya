<?php
/**
 * Class Sikshya_Gateway_Paypal_Request file.
 *
 * @package Sikshya\Gateways
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Generates requests to send to PayPal.
 */
class Sikshya_Gateway_Paypal_Request
{


	/**
	 * Endpoint for requests to PayPal.
	 *
	 * @var string
	 */
	protected $endpoint;


	public function __construct()
	{

	}


	/**
	 * Get the PayPal request URL for an order.
	 *
	 * @param bool $sandbox Whether to use sandbox mode or not.
	 * @return string
	 */
	public function get_request_url($order_id)
	{

		$args = $this->get_paypal_args($order_id);

		$redirect_uri = esc_url(home_url('/'));


		if ($args) {

			$paypal_args = http_build_query($args);

			$redirect_uri = (sikshya_get_paypal_api_endpoint()) . '?' . $paypal_args;
		}

		return $redirect_uri;
	}

	private function get_paypal_args($order_id)
	{
		$paypal_email = get_option('sikshya_payment_gateway_paypal_email');

		$order_details = get_post_meta($order_id, 'sikshya_order_meta', true);

		$user_id = isset($order_details['student_id']) ? $order_details['student_id'] : get_current_user_id();

		$user = new WP_User($user_id);


		$order_details_cart = isset($order_details['cart']) ? $order_details['cart'] : array();

		$currency_code = isset($order_details['currency']) ? $order_details['currency'] : sikshya_get_active_currency(true);

		$nonce = '';

		$total_order_amount = isset($order_details['total_order_amount']) ? absint($order_details['total_order_amount']) : 0;


		if (count($order_details_cart) > 0) {  // Normal Payment.

			$thank_you_page_id = get_option('sikshya_thankyou_page');

			$cancel_page_id = get_option('sikshya_failed_transaction_page');

			$thank_you_page = 'publish' == get_post_status($thank_you_page_id) ? get_permalink($thank_you_page_id) : home_url();

			$cancel_page_url = 'publish' == get_post_status($cancel_page_id) ? get_permalink($cancel_page_id) : home_url();

			$custom = array('order_id' => $order_id, 'order_key' => 'order_key');

			$return_nonce = wp_create_nonce('sikshiya_paypal_payment_return_nonce_' . substr(md5($order_id), 5, 5));
			$ipn_nonce = wp_create_nonce('sikshiya_paypal_payment_ipn_nonce_' . substr(md5($order_id), 5, 5));
			$query = array(
				'cmd' => '_xclick',
				'amount' => $total_order_amount,
				'quantity' => '1',
				'business' => $paypal_email,
				'item_name' => sikshya()->order->get_item_details($order_id),
				'return' =>
					add_query_arg(
						array(
							'order_id' => $order_id,
							'ordered' => true,
							'status' => 'success',
							'nonce' => $return_nonce

						),
						$thank_you_page
					),
				'currency_code' => $currency_code,
				'notify_url' => add_query_arg(
					array(
						'sikshya_listener' => 'IPN',
						'nonce' => $ipn_nonce,
						'order_id' => $order_id
					), home_url('index.php')
				),
				'no_note' => '1',
				'shipping' => '0',
				'email' => $user->user_email,
				'rm' => '2',
				'cancel_return' => add_query_arg(
					array(
						'order_id' => $order_id,
						'ordered' => true,
						'status' => 'cancel',
					),
					$cancel_page_url
				),
				'custom' => $order_id,
				'no_shipping' => '1'
			);

			sikshya_save_payment_gateway_log('paypal_request', $query);

			return apply_filters('sikshya_paypal_args', $query);
		}
	}

}
