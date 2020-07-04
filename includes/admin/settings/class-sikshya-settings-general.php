<?php
/**
 * Sikshya General Settings
 *
 * @package Sikshya/Admin
 */

defined('ABSPATH') || exit;

if (class_exists('Sikshya_Settings_General', false)) {
    return new Sikshya_Settings_General();
}

/**
 * Sikshya_Admin_Settings_General.
 */
class Sikshya_Settings_General extends Sikshya_Settings_Page
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'general';
        $this->label = __('General', 'sikshya');

        parent::__construct();
    }

    /**
     * Get settings array.
     *
     * @return array
     */
    public function get_settings()
    {


        $settings = apply_filters(
            'sikshya_general_settings',
            array(

                array(
                    'title' => __('Pages', 'sikshya'),
                    'type' => 'title',
                    'id' => 'general_options',
                ),

                array(
                    'title' => __('Account page', 'sikshya'),
                    'id' => 'sikshya_account_page',
                    'default' => '',
                    'type' => 'single_select_page',
                ),
                array(
                    'title' => __('Registration page', 'sikshya'),
                    'id' => 'sikshya_registration_page',
                    'default' => '',
                    'type' => 'single_select_page',
                ),
                array(
                    'title' => __('Login page', 'sikshya'),
                    'id' => 'sikshya_login_page',
                    'default' => '',
                    'type' => 'single_select_page',
                ),
                array(
                    'type' => 'sectionend',
                    'id' => 'pricing_options',
                ),

            )
        );

        return apply_filters('sikshya_get_settings_' . $this->id, $settings);
    }

    /**
     * Output a color picker input box.
     *
     * @param mixed $name Name of input.
     * @param string $id ID of input.
     * @param mixed $value Value of input.
     * @param string $desc (default: '') Description for input.
     */
    public function color_picker($name, $id, $value, $desc = '')
    {
        echo '<div class="color_box">' . ($desc) . '
			<input name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" type="text" value="' . esc_attr($value) . '" class="colorpick" /> <div id="colorPickerDiv_' . esc_attr($id) . '" class="colorpickdiv"></div>
		</div>';
    }

    /**
     * Output the settings.
     */
    public function output()
    {
        $settings = $this->get_settings();

        Sikshya_Admin_Settings::output_fields($settings);
    }

    /**
     * Save settings.
     */
    public function save()
    {
        $settings = $this->get_settings();

        Sikshya_Admin_Settings::save_fields($settings);
    }
}

return new Sikshya_Settings_General();
