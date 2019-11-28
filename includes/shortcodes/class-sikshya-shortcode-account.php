<?php
/**
 * Account Shortcode
 *
 * Used on this shortcode to list activity on page
 *
 * @package Sikshya/Shortcodes/Account
 * @version 0.0.1
 */

defined('ABSPATH') || exit;

/**
 * Shortcode checkout class.
 */
class Sikshya_Shortcode_Account
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


        if (is_user_logged_in()) {
            global $wp_query, $sikshya_current_account_page;

            $sikshya_current_account_page = 'dashboard';

            if (isset($wp_query->query_vars['sikshya_account_page']) && $wp_query->query_vars['sikshya_account_page']) {

                $sikshya_current_account_page = $wp_query->query_vars['sikshya_account_page'];
            }
            sikshya_load_template('profile.account-main');
        } else {
            sikshya_load_template('profile.login');
        }

    }

}