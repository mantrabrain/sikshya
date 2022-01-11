<?php
if (!function_exists('sikshya_get_course_price')) {
	function sikshya_get_course_price($course_id)
	{

		if (sikshya()->course->is_premium($course_id)) {

			$prices = sikshya()->course->get_prices($course_id);

			if (absint($prices['discounted']) > 0) {

				echo '<span class="regular-price"><del>' . esc_html(sikshya_get_price_with_symbol($prices['regular'])) . '</del></span>';
				echo '&nbsp;<span class="current-price discounted-price">' . esc_html(sikshya_get_price_with_symbol($prices['discounted'])) . '</span>';

			} else {
				echo '<span class="current-price regular-price">' . esc_html(sikshya_get_price_with_symbol($prices['regular'])) . '</span>';

			}

		} else {
			echo '<span class="current-price free-course">Free</span>';
		}
	}

}

if (!function_exists('sikshya_get_price_with_symbol')) {

	function sikshya_get_price_with_symbol($price, $symbol = null)
	{

		return sikshya_get_price($price, $symbol);

	}
}

if (!function_exists('sikshya_get_course_total_price')) {
	function sikshya_get_course_total_price($course_id, $quantity = 1)
	{
		$prices = sikshya()->course->get_prices($course_id);

		if (absint($prices['discounted']) > 0) {

			$final_price_per_course = $prices['discounted'];

		} else {

			$final_price_per_course = $prices['regular'];

		}
		return ($final_price_per_course * absint($quantity));
	}
}

if (!function_exists('sikshya_get_cart_price_subtotal')) {
	function sikshya_get_cart_price_subtotal($with_currency_symbol = true)
	{
		$all_cart_items = sikshya()->cart->get_cart_items();

		$subtotal_price = 0;

		foreach ($all_cart_items as $item) {
			$subtotal_price += absint($item->total_price);
		}
		return $with_currency_symbol ? sikshya_get_price_with_symbol($subtotal_price) : $subtotal_price;

	}
}


if (!function_exists('sikshya_get_cart_price_total')) {

	function sikshya_get_cart_price_total($with_currency_symbol = true)
	{
		$all_cart_items = sikshya()->cart->get_cart_items();

		$total_price = 0;

		foreach ($all_cart_items as $item) {
			$total_price += absint($item->total_price);
		}
		return $with_currency_symbol ? sikshya_get_price_with_symbol($total_price) : $total_price;
	}
}
if (!function_exists('sikshya_get_currency_symbol')) {

	function sikshya_get_currency_symbol($currency = '')
	{
		$currency = $currency === '' ? sikshya_get_currency() : $currency;

		$symbol_type = get_option('sikshya_currency_symbol_type', 'symbol');

		if ($symbol_type === 'code') {

			return $currency;

		}
		return sikshya_get_currency_symbols($currency);
	}
}

if (!function_exists('sikshya_get_currency')) {

	function sikshya_get_currency()
	{
		return get_option('sikshya_currency', 'USD');
	}
}

if (!function_exists('sikshya_get_price')) {

	function sikshya_get_price($price, $currency_symbol = null, $echo = false)
	{
		$currency_symbol = $currency_symbol === '' || is_null($currency_symbol) ? sikshya_get_currency_symbol() : $currency_symbol;

		$args = array(

			'decimals' => get_option('sikshya_price_number_decimals', 2),

			'decimal_separator' => get_option('sikshya_decimal_separator', '.'),

			'thousand_separator' => get_option('sikshya_thousand_separator', ',')

		);

		if (floatval($price) < 1) {

			$price = 0;
		}

		$price = apply_filters('sikshya_get_formatted_price',
			number_format($price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator']), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'], $price);

		$currency_position = get_option('sikshya_currency_position');

		if ($currency_position === "left_space") {

			$price_string = ($currency_symbol . ' ' . $price);

		} else if ($currency_position === "right_space") {
			$price_string = ($price . ' ' . $currency_symbol);

		} else if ($currency_position === "right") {

			$price_string = ($price . $currency_symbol);

		} else {
			$price_string = ($currency_symbol . $price);

		}


		if (!$echo) {
			return $price_string;
		}
		if ($echo) {
			echo esc_html($price_string);
		}
	}
}

if (!function_exists('sikshya_get_currency_positions')) {

	function sikshya_get_currency_positions()
	{

		return [
			'left' => __('Left', 'sikshya'),
			'right' => __('Right', 'sikshya'),
			'left_space' => __('Left with space', 'sikshya'),
			'right_space' => __('Right with space', 'sikshya')

		];
	}
}
