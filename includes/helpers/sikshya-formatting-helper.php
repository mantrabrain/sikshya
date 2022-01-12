<?php
if (!function_exists('sikshya_let_to_num')) {
	function sikshya_let_to_num($size)
	{
		$l = substr($size, -1);
		$ret = (int)substr($size, 0, -1);
		switch (strtoupper($l)) {
			case 'P':
				$ret *= 1024;
// No break.
			case 'T':
				$ret *= 1024;
// No break.
			case 'G':
				$ret *= 1024;
// No break.
			case 'M':
				$ret *= 1024;
// No break.
			case 'K':
				$ret *= 1024;
// No break.
		}
		return $ret;
	}
}
if (!function_exists('sikshya_get_price_decimal_separator')) {
	function sikshya_get_price_decimal_separator()
	{
		$separator = apply_filters('sikshya_get_price_decimal_separator', false);

		return $separator ? stripslashes($separator) : '.';
	}
}

if (!function_exists('sikshya_get_price_decimals')) {
	function sikshya_get_price_decimals()
	{
		return absint(apply_filters('sikshya_get_price_decimals', 2));
	}
}

if (!function_exists('sikshya_get_rounding_precision')) {
	function sikshya_get_rounding_precision()
	{
		$precision = sikshya_get_price_decimals() + 2;
		if (absint(SIKSHYA_ROUNDING_PRECISION) > $precision) {
			$precision = absint(SIKSHYA_ROUNDING_PRECISION);
		}
		return $precision;
	}
}

if (!function_exists('sikshya_format_decimal')) {
	function sikshya_format_decimal($number, $dp = false, $trim_zeros = false)
	{
		$locale = localeconv();
		$decimals = array(sikshya_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point']);

		// Remove locale from string.
		if (!is_float($number)) {
			$number = str_replace($decimals, '.', $number);

			// Convert multiple dots to just one.
			$number = preg_replace('/\.(?![^.]+$)|[^0-9.-]/', '', sikshya_clean($number));
		}

		if (false !== $dp) {
			$dp = intval('' === $dp ? sikshya_get_price_decimals() : $dp);
			$number = number_format(floatval($number), $dp, '.', '');
		} elseif (is_float($number)) {
			// DP is false - don't use number format, just return a string using whatever is given. Remove scientific notation using sprintf.
			$number = str_replace($decimals, '.', sprintf('%.' . sikshya_get_rounding_precision() . 'f', $number));
			// We already had a float, so trailing zeros are not needed.
			$trim_zeros = true;
		}

		if ($trim_zeros && strstr($number, '.')) {
			$number = rtrim(rtrim($number, '0'), '.');
		}

		return $number;
	}

}

if (!function_exists('sikshya_date_format')) {
	function sikshya_date_format()
	{
		$date_format = get_option('date_format');
		if (empty($date_format)) {
			// Return default date format if the option is empty.
			$date_format = 'F j, Y';
		}
		return apply_filters('sikshya_date_format', $date_format);
	}
}

if (!function_exists('sikshya_time_format')) {
	function sikshya_time_format()
	{
		$time_format = get_option('time_format');
		if (empty($time_format)) {
			// Return default time format if the option is empty.
			$time_format = 'g:i a';
		}
		return apply_filters('sikshya_time_format', $time_format);
	}
}
