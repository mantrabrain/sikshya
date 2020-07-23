<?php
if (!class_exists('Sikshya_Payment_Gateways_Core')) {

	final class Sikshya_Payment_Gateways_Core
	{
		private static $instance;

		public static function instance()
		{
			if (empty(self::$instance)) {

				self::$instance = new self;
			}
			return self::$instance;
		}

		public function init()
		{
			$this->includes();

			$this->register();
		}

		public function includes()
		{
			include_once SIKSHYA_PATH . '/includes/payment-gateways/function-sikshya-payments.php';

			// Include PayPal Payment gateways
			include_once SIKSHYA_PATH . '/includes/payment-gateways/paypal/class-sikshya-payment-gateway-paypal.php';

		}

		public function register()
		{
			$payment_gateways = apply_filters('sikshya_registered_payment_gateways', array(

				'Sikshya_Payment_Gateway_PayPal'
			));

			foreach ($payment_gateways as $gateway) {

				if (class_exists($gateway)) {

					new $gateway;
				}
			}

		}


	}

}
Sikshya_Payment_Gateways_Core::instance()->init();
