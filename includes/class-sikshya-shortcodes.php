<?php
/**
 * Shortcodes
 *
 * @package Sikshya/Classes
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Sikshya Shortcodes class.
 */
class Sikshya_Shortcodes
{

	/**
	 * Init shortcodes.
	 */
	public static function init()
	{
		$shortcodes = array(

			'sikshya_registration' => __CLASS__ . '::registration',
			'sikshya_account' => __CLASS__ . '::account',
			'sikshya_login' => __CLASS__ . '::login',
			'sikshya_cart' => __CLASS__ . '::cart',
			'sikshya_checkout' => __CLASS__ . '::checkout',

		);

		foreach ($shortcodes as $shortcode => $function) {
			add_shortcode(apply_filters("{$shortcode}_shortcode_tag", $shortcode), $function);
		}


	}

	/**
	 * Shortcode Wrapper.
	 *
	 * @param string[] $function Callback function.
	 * @param array $atts Attributes. Default to empty array.
	 * @param array $wrapper Customer wrapper data.
	 *
	 * @return string
	 */
	public static function shortcode_wrapper(
		$function,
		$atts = array(),
		$wrapper = array(
			'class' => 'sikshya-shortcode-wrapper',
			'before' => null,
			'after' => null,
		)
	)
	{
		ob_start();

		// @codingStandardsIgnoreStart
		echo empty($wrapper['before']) ? '<div class="' . esc_attr($wrapper['class']) . '">' : $wrapper['before'];
		call_user_func($function, $atts);
		echo empty($wrapper['after']) ? '</div>' : $wrapper['after'];
		// @codingStandardsIgnoreEnd

		return ob_get_clean();
	}

	/**
	 * Registration page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function registration($atts)
	{
		return self::shortcode_wrapper(array('Sikshya_Shortcode_Registration', 'output'), $atts);
	}

	/**
	 * account page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function account($atts)
	{
		return self::shortcode_wrapper(array('Sikshya_Shortcode_Account', 'output'), $atts);
	}

	/**
	 * login page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function login($atts)
	{
		return self::shortcode_wrapper(array('Sikshya_Shortcode_Login', 'output'), $atts);
	}

	/**
	 * cart page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function cart($atts)
	{
		return self::shortcode_wrapper(array('Sikshya_Shortcode_Cart', 'output'), $atts);
	}

	/**
	 * checkout page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function checkout($atts)
	{
		return self::shortcode_wrapper(array('Sikshya_Shortcode_Checkout', 'output'), $atts);
	}

}
