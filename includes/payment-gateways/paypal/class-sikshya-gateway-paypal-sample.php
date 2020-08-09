<?php

class Sikshya_Gateway_PayPal_Sample
{
	private $live = false;

	private $paypal_url = '';

	public function __construct()
	{
		$paypal_sandbox_url = 'https://www.sandbox.paypal.com/';
		$paypal_payment_sandbox_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

		if ($this->live) {
			$this->paypal_url = $paypal_sandbox_url;
		} else {
			$this->paypal_url = $paypal_payment_sandbox_url;

		}

		return;
		$this->send_request();
	}

	public function send_request()
	{

		$custom = array('order_id' => 1, 'order_key' => 'Sample KEY');

		$query = array(
			'cmd' => '_xclick',
			'amount' => 500,
			'quantity' => '1',
			'business' => 'sampleemail@business.example.com',
			'item_name' => 'Course One, Course Two',
			'return' => add_query_arg(
				array(
					'sikshya-transaction-method' => 'paypal-standard',
					'paypal-nonce' => 'nonce'
				), 'http://localhost/WordPressThemes/'),
			'currency_code' => 'USD',
			'notify_url' => 'http://localhost/WordPressThemes/',
			'no_note' => '1',
			'shipping' => '0',
			'email' => 'user@gmail.com',
			'rm' => '2',
			'cancel_return' => 'http://localhost/WordPressThemes/',
			'custom' => json_encode($custom),
			'no_shipping' => '1'
		);


		$paypal_payment_url = $this->paypal_url . '?' . http_build_query($query);

		wp_redirect($paypal_payment_url);
		exit;
	}
}

new Sikshya_Gateway_PayPal_Sample();
