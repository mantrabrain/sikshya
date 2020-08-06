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

			$paypal_args = http_build_query($args, '', '&');

			$redirect_uri = esc_url(sikshya_get_paypal_api_endpoint()) . '?' . $paypal_args;
		}



		return $redirect_uri;
	}

	private function get_paypal_args($order_id)
	{
		$paypal_email = get_option('sikshya_payment_gateway_paypal_email');


		$order = get_post($order_id);

		$order_details = get_post_meta($order_id, 'sikshya_order_meta', true);

		$order_details_cart = isset($order_details['cart']) ? $order_details['cart'] : array();

		$currency_code = isset($order_details['currency']) ? $order_details['currency'] : sikshya_get_active_currency(true);

		if (count($order_details_cart) > 0) {  // Normal Payment.


			$thank_you_page_id = get_option('sikshya_thankyou_page');


			$cancel_page_id = get_option('sikshya_failed_transaction_page');

			$thank_you_page = 'publish' == get_post_status($thank_you_page_id) ? get_permalink($thank_you_page_id) : home_url();

			$cancel_page_url = 'publish' == get_post_status($cancel_page_id) ? get_permalink($cancel_page_id) : home_url();

			$args['cmd'] = '_cart';

			$args['upload'] = '1';

			$args['currency_code'] = sanitize_text_field($currency_code);

			$args['business'] = sanitize_email($paypal_email);
			//$args['bn'] = '';
			$args['rm'] = '2';
			$args['discount_amount_cart'] = 0;
			$args['tax_cart'] = 0;
			$args['charset'] = get_bloginfo('charset');
			$args['cbt'] = get_bloginfo('name');
			$args['return'] = add_query_arg(
				array(
					'order_id' => $order_id,
					'ordered' => true,
					'status' => 'success',
				),
				$thank_you_page
			);
			$args['cancel'] = add_query_arg(
				array(
					'order_id' => $order_id,
					'ordered' => true,
					'status' => 'cancel',
				),
				$cancel_page_url
			);
			$args['handling'] = 0;
			$args['handling_cart'] = 0;
			$args['no_shipping'] = 0;
			$args['notify_url'] = esc_url(add_query_arg(
					array(
						'sikshya_listener' => 'IPN'
					), home_url('index.php')
				)
			);

			// Cart Item.
			$agrs_index = 1;

			foreach ($order_details_cart as $course_id => $item) {

				$course_id = absint($course_id);

				$item_name = isset($item->title) ? $item->title : '';

				//$trip_code = isset($sikshya_booking_meta_params['booking_code']) ? $sikshya_booking_meta_params['booking_code'] : '';

				$payment_amount = isset($item->total_price) ? $item->total_price : 0;

				$args['item_name_' . $agrs_index] = $item_name;

				$args['quantity_' . $agrs_index] = 1;

				$args['amount_' . $agrs_index] = $payment_amount;

				$args['item_number_' . $agrs_index] = $course_id;

				$args['on0_' . $agrs_index] = __('Code', 'sikshya');
				// $args['on1_' . $agrs_index ] = __( 'Payment Mode', 'sikshya' );
				$args['on2_' . $agrs_index] = __('Price', 'sikshya');

				//$args['os0_' . $agrs_index] = $trip_code;
				// $args['os1_' . $agrs_index ] = $payment_mode;
				$args['os2_' . $agrs_index] = $payment_amount;

				$args = apply_filters('sikshya_extra_paypal_args', $args, $item, $course_id, $agrs_index);

				$agrs_index++;
			}
		} else {
			return;
		}

		$args['option_index_0'] = $agrs_index;

		$args['custom'] = $order_id;

		return apply_filters('sikshya_paypal_args', $args);
	}

}
