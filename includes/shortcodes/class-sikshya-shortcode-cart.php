<?php
/**
 * Cart Shortcode
 *
 * Used on this shortcode to list activity on page
 *
 * @package Sikshya/Shortcodes/Cart
 * @version 0.0.1
 */

defined('ABSPATH') || exit;

/**
 * Shortcode checkout class.
 */
class Sikshya_Shortcode_Cart
{

	/**
	 * Get the shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function get($atts)
	{
		return Sikshya_Shortcodes::shortcode_wrapper(array(__CLASS__, 'output'), $atts);
	}

	/**
	 * Output the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function output($atts)
	{


		$cart_items = sikshya()->cart->get_cart_items();


		echo '<pre>';
		print_r($cart_items);
		echo '</pre>';

	}

}
