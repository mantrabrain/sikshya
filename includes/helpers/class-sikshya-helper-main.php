<?php

class Sikshya_Helper_Main
{
	public function validate_nonce($die = false, $nonce_action = '', $nonce_value = '')
	{
		$debug_backtrace = debug_backtrace();
		if (@isset($debug_backtrace[1]['function'])) {

			$nonce_action = 'wp_sikshya_' . $debug_backtrace[1]['function'] . '_nonce';
		}

		if (empty($nonce_value)) {
			$nonce_value = isset($_REQUEST['sikshya_nonce']) ? $_REQUEST['sikshya_nonce'] : '';
		}

		if ($die) {
			if (!wp_verify_nonce($nonce_value, $nonce_action)) {

				die('Sikshya nonce doesnt match');
			}
		}

		return wp_verify_nonce($nonce_value, $nonce_action);

	}

	public function input($input = '', $old_data = null)
	{
		if (!$old_data) {
			$old_data = $_REQUEST;
		}
		$value = $this->avalue_dot($input, $old_data);
		if ($value) {
			return $value;
		}
		return '';
	}

	public function array_get($key = null, $array = array(), $default = false)
	{
		return $this->avalue_dot($key, $array, $default);
	}

	public function avalue_dot($key = null, $array = array(), $default = false)
	{
		$array = (array)$array;
		if (!$key || !count($array)) {
			return $default;
		}
		$option_key_array = explode('.', $key);

		$value = $array;

		foreach ($option_key_array as $dotKey) {
			if (isset($value[$dotKey])) {
				$value = $value[$dotKey];
			} else {
				return $default;
			}
		}
		return $value;
	}


	public function account_sidebar_nav_permalink($key = '', $page_id = 0)
	{
		if ($key === 'dashboard') {

			$key = '';
		}
		$page_id = $page_id > 0 ? $page_id : get_the_ID();

		$structure = get_option('permalink_structure');
		
		if ($structure == '' || empty($structure)) {
			return add_query_arg(array(
				'sikshya_account_page' => $key,
			), get_permalink($page_id));
		}

		return trailingslashit(get_permalink($page_id)) . $key;
	}
}
