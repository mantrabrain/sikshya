<?php
/**
 * Registration Shortcode
 *
 * Used on this shortcode to list activity on page
 *
 * @package Sikshya/Shortcodes/Registration
 * @version 0.0.1
 */

defined('ABSPATH') || exit;

/**
 * Shortcode checkout class.
 */
class Sikshya_Shortcode_Registration
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
        if (!is_user_logged_in()) {

            sikshya_load_template('profile.registration');

        } else {

            $account_page = sikshya_get_account_page(true);
            echo '<h2>You are already logged in.</h2> <span> You can check your account page from <a href="'.esc_url($account_page).'">here</a>.</span>';

        }

    }

}