<?php

class Sikshya_Payment_Gateway_PayPal extends Sikshya_Payment_Gateways
{
	protected $id = 'paypal';

	public function __construct()
	{

		include_once 'paypal-functions.php';

		$configuration = array(

			'settings' => array(
				'title' => __('PayPal Standard', 'sikshya'),
				'default' => 'no',
				'id' => $this->id,
				'frontend_title' => __('PayPal Standard', 'sikshya'),

			),
		);


		add_action('init', array($this, 'sikshya_listen_paypal_ipn'));
		add_action('sikshya_verify_paypal_ipn', array($this, 'sikshya_paypal_ipn_process'));


		parent::__construct($configuration);


	}

	public function admin_setting_tab()
	{
		$settings =

			array(
				array(
					'title' => __('PayPal Settings', 'sikshya'),
					'type' => 'title',
					'desc' => '',
					'id' => 'sikshya_payment_gateways_paypal_options',
				),
				array(
					'title' => __('PayPal Email Address', 'sikshya'),
					'desc' => __(' Enter your PayPal account\'s email', 'sikshya'),
					'id' => 'sikshya_payment_gateway_paypal_email',
					'type' => 'text',
				),
				array(
					'title' => __('Description', 'sikshya'),
					'desc' => __(' Description for paypal payment gateway', 'sikshya'),
					'id' => 'sikshya_payment_gateway_paypal_description',
					'type' => 'text',
					'default' => 'Pay via PayPal; you can pay with your credit card if you don’t have a PayPal account.'
				),
				array(
					'title' => __('Image URL', 'sikshya'),
					'desc' => __('Image URL', 'sikshya'),
					'id' => 'sikshya_payment_gateway_paypal_image_url',
					'type' => 'url',
					'default' => SIKSHYA_ASSETS_URL . '/images/paypal.png'
				),

				array(
					'title' => __('Help Text', 'sikshya'),
					'desc' => __('Help texts for PayPal payment gateway', 'sikshya'),
					'id' => 'sikshya_payment_gateway_paypal_help_text',
					'type' => 'text',
					'default' => 'What is PayPal?'
				),
				array(
					'title' => __('Help URL', 'sikshya'),
					'desc' => __('Help URL for PayPal payment gateway', 'sikshya'),
					'id' => 'sikshya_payment_gateway_paypal_help_url',
					'type' => 'url',
					'default' => 'https://www.paypal.com/gb/webapps/mpp/paypal-popup'
				),

				array(
					'type' => 'sectionend',
					'id' => 'sikshya_payment_gateways_paypal_options',
				),

			);


		return $settings;
	}

	public function process_payment($sikshya_order_id)
	{

		$order = get_post($sikshya_order_id);

		$order_status = isset($order->post_status) ? $order->post_status : '';


		if ($order_status == 'sikshya-completed') {

			return;
		}

		include_once dirname(__FILE__) . '/class-sikshya-gateway-paypal-request.php';

		do_action('sikshya_before_payment_process', $sikshya_order_id);

		$paypal_request = new Sikshya_Gateway_Paypal_Request();

		$redirect_url = $paypal_request->get_request_url($sikshya_order_id);

		wp_redirect($redirect_url);

		exit;
	}


	/**
	 * Listen for a $_GET request from our PayPal IPN.
	 * This would also do the "set-up" for an "alternate purchase verification"
	 */
	public function sikshya_listen_paypal_ipn()
	{

		if (isset($_GET['sikshya_listener']) && $_GET['sikshya_listener'] == 'IPN' && isset($_POST['custom'])) {
			/*			file_put_contents('sikshya-ipn_response-post.log', print_r($_POST, true) . PHP_EOL, LOCK_EX | FILE_APPEND);
						file_put_contents('sikshya-ipn_response-get.log', print_r($_GET, true) . PHP_EOL, LOCK_EX | FILE_APPEND);
						file_put_contents('sikshya-ipn_response-request.log', print_r($_REQUEST, true) . PHP_EOL, LOCK_EX | FILE_APPEND);*/

			$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;
			$nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
			//if (wp_verify_nonce($nonce, 'sikshiya_paypal_payment_ipn_nonce_' . substr(md5($order_id), 5, 5))) {
			do_action('sikshya_verify_paypal_ipn');
			//}
		}
		// echo WP_CONTENT_DIR;die;
	}


	/**
	 * When a payment is made PayPal will send us a response and this function is
	 * called. From here we will confirm arguments that we sent to PayPal which
	 * the ones PayPal is sending back to us.
	 * This is the Pink Lilly of the whole operation.
	 */
	public function sikshya_paypal_ipn_process()
	{


		/*1. Check that $_POST['payment_status'] is "Completed"
		2. Check that $_POST['txn_id'] has not been previously processed
		3. Check that $_POST['receiver_email'] is your Primary PayPal email
		4. Check that $_POST['payment_amount'] and $_POST['payment_currency'] are correct
		/**
		 * Instantiate the IPNListener class
		 */
		include dirname(__FILE__) . '/php-paypal-ipn/IPNListener.php';

		$listener = new IPNListener();

		$sikshya_order_id = isset($_POST['custom']) ? absint($_POST['custom']) : 0;


		if ($sikshya_order_id < 1) {

			return;
		}

		$message = '';


		/**
		 * Set to PayPal sandbox or live mode
		 */
		$listener->use_sandbox = sikshya_payment_gateway_test_mode();

		/**
		 * Check if IPN was successfully processed
		 */
		if ($verified = $listener->processIpn()) {


			/**
			 * Log successful purchases
			 */
			$transactionData = $listener->getPostData(); // POST data array

			file_put_contents('sikshya-ipn_success.log', print_r($transactionData, true) . PHP_EOL, LOCK_EX | FILE_APPEND);

			$message = null;
			/**
			 * Verify seller PayPal email with PayPal email in settings
			 *
			 * Check if the seller email that was processed by the IPN matches what is saved as
			 * the seller email in our DB
			 */
			if ($_POST['receiver_email'] != get_option('sikshya_payment_gateway_paypal_email')) {
				$message .= "\nEmail seller email does not match email in settings\n";
			}

			/**
			 * Verify currency
			 *
			 * Check if the currency that was processed by the IPN matches what is saved as
			 * the currency setting
			 */
			if (trim($_POST['mc_currency']) != trim(get_option('sikshya_currency'))) {
				$message .= "\nCurrency does not match those assigned in settings\n";
			}

			/**
			 * Check if this payment was already processed
			 *
			 * PayPal transaction id (txn_id) is stored in the database, we check
			 * that against the txn_id returned.
			 */
			$txn_id = get_post_meta($sikshya_order_id, 'txn_id', true);
			if (empty($txn_id)) {
				update_post_meta($sikshya_order_id, 'txn_id', $_POST['txn_id']);
			} else {
				$message .= "\nThis payment was already processed\n";
			}

			/**
			 * Verify the payment is set to "Completed".
			 *
			 * Create a new payment, send customer an email and empty the cart
			 */

			update_post_meta($sikshya_order_id, '_paypal_args', $_POST);

			if (!empty($_POST['payment_status']) && $_POST['payment_status'] == 'Completed') {
				// Update booking status and Payment args.

				sikshya_update_order_status($sikshya_order_id, 'sikshya-completed');

				sikshya_update_payment_status($sikshya_order_id);

				$payment_id = get_post_meta($sikshya_order_id, 'sikshya_payment_id', true);

				update_post_meta($payment_id, '_paypal_args', $_POST);

				delete_post_meta($sikshya_order_id, '_paypal_args');

				update_post_meta($payment_id, 'sikshya_total_paid_amount', $_POST['mc_gross']);

				update_post_meta($payment_id, 'sikshya_total_paid_currency', $_POST['mc_currency']);

				do_action('sikshya_after_successful_payment', $sikshya_order_id, $message);

			} else {

				$message .= "\nPayment status not set to Completed\n";

			}

		} else {

			/**
			 * Log errors
			 */
			$errors = $listener->getErrors();

			file_put_contents('sikshya-ipn_errors.log', print_r($errors, true) . PHP_EOL, LOCK_EX | FILE_APPEND);

			do_action('sikshya_after_failed_payment', $sikshya_order_id, $message);

		}
		file_put_contents('sikshya-ipn_message.log', print_r($message, true) . PHP_EOL, LOCK_EX | FILE_APPEND);


		update_post_meta($sikshya_order_id, 'sikshya_payment_message', $message);
	}

}
