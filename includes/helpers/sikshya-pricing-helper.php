<?php
if (!function_exists('sikshya_get_course_price')) {
	function sikshya_get_course_price($course_id)
	{
		$currency_symbol = esc_html(sikshya_get_active_currency_symbol());

		if (sikshya()->course->is_premium()) {

			$prices = sikshya()->course->get_prices($course_id);

			if (absint($prices['discounted']) > 0) {

				echo '<span class="regular-price"><del>' . esc_html(sikshya_get_price_with_symbol($prices['regular'])) . '</del></span>';
				echo '<span class="current-price discounted-price">' . esc_html(sikshya_get_price_with_symbol($prices['discounted'])) . '</span>';

			} else {
				echo '<span class="regular-price">' . esc_html(sikshya_get_price_with_symbol($prices['regular'])) . '</span>';

			}

		} else {
			echo '<span class="current-price free-course">Free</span>';
		}
	}

}

if (!function_exists('sikshya_get_price_with_symbol')) {

	function sikshya_get_price_with_symbol($price)
	{
		$currency_symbol_position = 'left';

		$currency_symbol = sikshya_get_active_currency_symbol();

		if ($currency_symbol_position == 'left') {

			return $currency_symbol . $price;
		} else {
			return $price . $currency_symbol_position;

		}

	}
}
