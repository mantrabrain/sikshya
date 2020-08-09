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
		$currency_symbol_position = get_option('sikshya_currency_position', 'left');

		$currency_symbol = is_null($symbol) ? sikshya_get_active_currency_symbol() : $symbol;

		if ($currency_symbol_position == 'left') {

			return $currency_symbol . $price;
		} else {
			return $price . $currency_symbol;

		}

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
