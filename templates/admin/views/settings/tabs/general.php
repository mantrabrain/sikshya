<?php
/**
 * General Settings Tab Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-settings-tab-content">
    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-info-circle"></i>
            <?php _e('Basic Information', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="site_title"><?php _e('LMS Site Title', 'sikshya'); ?></label>
                <input type="text" id="site_title" name="site_title" 
                       value="<?php echo esc_attr(get_option('sikshya_site_title', get_bloginfo('name'))); ?>" 
                       placeholder="<?php _e('Enter your LMS site title', 'sikshya'); ?>">
                <p class="description"><?php _e('The title of your learning management system', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="site_description"><?php _e('LMS Description', 'sikshya'); ?></label>
                <textarea id="site_description" name="site_description" rows="3" 
                          placeholder="<?php _e('Enter a description for your LMS', 'sikshya'); ?>"><?php echo esc_textarea(get_option('sikshya_site_description', get_bloginfo('description'))); ?></textarea>
                <p class="description"><?php _e('A brief description of your learning platform', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-dollar-sign"></i>
            <?php _e('Currency & Pricing', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="currency"><?php _e('Default Currency', 'sikshya'); ?></label>
                <select id="currency" name="currency">
                    <option value="USD" <?php selected(get_option('sikshya_currency', 'USD'), 'USD'); ?>><?php _e('US Dollar ($)', 'sikshya'); ?></option>
                    <option value="EUR" <?php selected(get_option('sikshya_currency', 'USD'), 'EUR'); ?>><?php _e('Euro (€)', 'sikshya'); ?></option>
                    <option value="GBP" <?php selected(get_option('sikshya_currency', 'USD'), 'GBP'); ?>><?php _e('British Pound (£)', 'sikshya'); ?></option>
                    <option value="CAD" <?php selected(get_option('sikshya_currency', 'USD'), 'CAD'); ?>><?php _e('Canadian Dollar (C$)', 'sikshya'); ?></option>
                    <option value="AUD" <?php selected(get_option('sikshya_currency', 'USD'), 'AUD'); ?>><?php _e('Australian Dollar (A$)', 'sikshya'); ?></option>
                    <option value="JPY" <?php selected(get_option('sikshya_currency', 'USD'), 'JPY'); ?>><?php _e('Japanese Yen (¥)', 'sikshya'); ?></option>
                    <option value="INR" <?php selected(get_option('sikshya_currency', 'USD'), 'INR'); ?>><?php _e('Indian Rupee (₹)', 'sikshya'); ?></option>
                    <option value="BRL" <?php selected(get_option('sikshya_currency', 'USD'), 'BRL'); ?>><?php _e('Brazilian Real (R$)', 'sikshya'); ?></option>
                    <option value="MXN" <?php selected(get_option('sikshya_currency', 'USD'), 'MXN'); ?>><?php _e('Mexican Peso ($)', 'sikshya'); ?></option>
                    <option value="SGD" <?php selected(get_option('sikshya_currency', 'USD'), 'SGD'); ?>><?php _e('Singapore Dollar (S$)', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Default currency for course pricing and payments', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="currency_position"><?php _e('Currency Position', 'sikshya'); ?></label>
                <select id="currency_position" name="currency_position">
                    <option value="left" <?php selected(get_option('sikshya_currency_position', 'left'), 'left'); ?>><?php _e('Left ($100)', 'sikshya'); ?></option>
                    <option value="right" <?php selected(get_option('sikshya_currency_position', 'left'), 'right'); ?>><?php _e('Right (100$)', 'sikshya'); ?></option>
                    <option value="left_space" <?php selected(get_option('sikshya_currency_position', 'left'), 'left_space'); ?>><?php _e('Left with space ($ 100)', 'sikshya'); ?></option>
                    <option value="right_space" <?php selected(get_option('sikshya_currency_position', 'left'), 'right_space'); ?>><?php _e('Right with space (100 $)', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Position of currency symbol relative to the amount', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-clock"></i>
            <?php _e('Date & Time', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="timezone"><?php _e('Timezone', 'sikshya'); ?></label>
                <select id="timezone" name="timezone">
                    <?php
                    $current_timezone = get_option('sikshya_timezone', get_option('timezone_string'));
                    $timezones = DateTimeZone::listIdentifiers();
                    foreach ($timezones as $timezone) {
                        echo '<option value="' . esc_attr($timezone) . '" ' . selected($current_timezone, $timezone, false) . '>' . esc_html($timezone) . '</option>';
                    }
                    ?>
                </select>
                <p class="description"><?php _e('Timezone for displaying dates and times', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="date_format"><?php _e('Date Format', 'sikshya'); ?></label>
                <select id="date_format" name="date_format">
                    <option value="F j, Y" <?php selected(get_option('sikshya_date_format', get_option('date_format')), 'F j, Y'); ?>><?php echo date('F j, Y'); ?></option>
                    <option value="Y-m-d" <?php selected(get_option('sikshya_date_format', get_option('date_format')), 'Y-m-d'); ?>><?php echo date('Y-m-d'); ?></option>
                    <option value="m/d/Y" <?php selected(get_option('sikshya_date_format', get_option('date_format')), 'm/d/Y'); ?>><?php echo date('m/d/Y'); ?></option>
                    <option value="d/m/Y" <?php selected(get_option('sikshya_date_format', get_option('date_format')), 'd/m/Y'); ?>><?php echo date('d/m/Y'); ?></option>
                    <option value="j F Y" <?php selected(get_option('sikshya_date_format', get_option('date_format')), 'j F Y'); ?>><?php echo date('j F Y'); ?></option>
                </select>
                <p class="description"><?php _e('Format for displaying dates throughout the LMS', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="time_format"><?php _e('Time Format', 'sikshya'); ?></label>
                <select id="time_format" name="time_format">
                    <option value="g:i a" <?php selected(get_option('sikshya_time_format', get_option('time_format')), 'g:i a'); ?>><?php echo date('g:i a'); ?></option>
                    <option value="H:i" <?php selected(get_option('sikshya_time_format', get_option('time_format')), 'H:i'); ?>><?php echo date('H:i'); ?></option>
                </select>
                <p class="description"><?php _e('Format for displaying times throughout the LMS', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-language"></i>
            <?php _e('Language & Localization', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="language"><?php _e('Default Language', 'sikshya'); ?></label>
                <select id="language" name="language">
                    <option value="en" <?php selected(get_option('sikshya_language', 'en'), 'en'); ?>><?php _e('English', 'sikshya'); ?></option>
                    <option value="es" <?php selected(get_option('sikshya_language', 'en'), 'es'); ?>><?php _e('Spanish', 'sikshya'); ?></option>
                    <option value="fr" <?php selected(get_option('sikshya_language', 'en'), 'fr'); ?>><?php _e('French', 'sikshya'); ?></option>
                    <option value="de" <?php selected(get_option('sikshya_language', 'en'), 'de'); ?>><?php _e('German', 'sikshya'); ?></option>
                    <option value="it" <?php selected(get_option('sikshya_language', 'en'), 'it'); ?>><?php _e('Italian', 'sikshya'); ?></option>
                    <option value="pt" <?php selected(get_option('sikshya_language', 'en'), 'pt'); ?>><?php _e('Portuguese', 'sikshya'); ?></option>
                    <option value="ru" <?php selected(get_option('sikshya_language', 'en'), 'ru'); ?>><?php _e('Russian', 'sikshya'); ?></option>
                    <option value="zh" <?php selected(get_option('sikshya_language', 'en'), 'zh'); ?>><?php _e('Chinese', 'sikshya'); ?></option>
                    <option value="ja" <?php selected(get_option('sikshya_language', 'en'), 'ja'); ?>><?php _e('Japanese', 'sikshya'); ?></option>
                    <option value="ko" <?php selected(get_option('sikshya_language', 'en'), 'ko'); ?>><?php _e('Korean', 'sikshya'); ?></option>
                    <option value="ar" <?php selected(get_option('sikshya_language', 'en'), 'ar'); ?>><?php _e('Arabic', 'sikshya'); ?></option>
                    <option value="hi" <?php selected(get_option('sikshya_language', 'en'), 'hi'); ?>><?php _e('Hindi', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Default language for the LMS interface', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 