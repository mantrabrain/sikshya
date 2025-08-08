<?php
/**
 * Certificates Settings Tab Template
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
            <i class="fas fa-certificate"></i>
            <?php _e('Certificate Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_certificates" value="1" 
                           <?php checked(get_option('sikshya_enable_certificates', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Certificates', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable certificate generation for completed courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_template"><?php _e('Default Certificate Template', 'sikshya'); ?></label>
                <select id="certificate_template" name="certificate_template">
                    <option value="default" <?php selected(get_option('sikshya_certificate_template', 'default'), 'default'); ?>><?php _e('Default Template', 'sikshya'); ?></option>
                    <option value="modern" <?php selected(get_option('sikshya_certificate_template', 'default'), 'modern'); ?>><?php _e('Modern Template', 'sikshya'); ?></option>
                    <option value="classic" <?php selected(get_option('sikshya_certificate_template', 'default'), 'classic'); ?>><?php _e('Classic Template', 'sikshya'); ?></option>
                    <option value="minimal" <?php selected(get_option('sikshya_certificate_template', 'default'), 'minimal'); ?>><?php _e('Minimal Template', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Default template for certificate design', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_format"><?php _e('Certificate Format', 'sikshya'); ?></label>
                <select id="certificate_format" name="certificate_format">
                    <option value="pdf" <?php selected(get_option('sikshya_certificate_format', 'pdf'), 'pdf'); ?>><?php _e('PDF', 'sikshya'); ?></option>
                    <option value="png" <?php selected(get_option('sikshya_certificate_format', 'pdf'), 'png'); ?>><?php _e('PNG Image', 'sikshya'); ?></option>
                    <option value="jpg" <?php selected(get_option('sikshya_certificate_format', 'pdf'), 'jpg'); ?>><?php _e('JPG Image', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Format for generated certificates', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-image"></i>
            <?php _e('Certificate Design', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="certificate_logo"><?php _e('Certificate Logo', 'sikshya'); ?></label>
                <input type="url" id="certificate_logo" name="certificate_logo" 
                       value="<?php echo esc_url(get_option('sikshya_certificate_logo', '')); ?>" 
                       placeholder="https://example.com/logo.png">
                <p class="description"><?php _e('Logo to display on certificates', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_signature"><?php _e('Certificate Signature', 'sikshya'); ?></label>
                <input type="url" id="certificate_signature" name="certificate_signature" 
                       value="<?php echo esc_url(get_option('sikshya_certificate_signature', '')); ?>" 
                       placeholder="https://example.com/signature.png">
                <p class="description"><?php _e('Signature image for certificates', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_font"><?php _e('Certificate Font', 'sikshya'); ?></label>
                <select id="certificate_font" name="certificate_font">
                    <option value="Arial" <?php selected(get_option('sikshya_certificate_font', 'Arial'), 'Arial'); ?>><?php _e('Arial', 'sikshya'); ?></option>
                    <option value="Times New Roman" <?php selected(get_option('sikshya_certificate_font', 'Arial'), 'Times New Roman'); ?>><?php _e('Times New Roman', 'sikshya'); ?></option>
                    <option value="Helvetica" <?php selected(get_option('sikshya_certificate_font', 'Arial'), 'Helvetica'); ?>><?php _e('Helvetica', 'sikshya'); ?></option>
                    <option value="Georgia" <?php selected(get_option('sikshya_certificate_font', 'Arial'), 'Georgia'); ?>><?php _e('Georgia', 'sikshya'); ?></option>
                    <option value="Verdana" <?php selected(get_option('sikshya_certificate_font', 'Arial'), 'Verdana'); ?>><?php _e('Verdana', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Font family for certificate text', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_font_size"><?php _e('Font Size', 'sikshya'); ?></label>
                <input type="number" id="certificate_font_size" name="certificate_font_size" 
                       value="<?php echo esc_attr(get_option('sikshya_certificate_font_size', 12)); ?>" 
                       min="8" max="72">
                <p class="description"><?php _e('Base font size for certificate text', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_color"><?php _e('Text Color', 'sikshya'); ?></label>
                <input type="color" id="certificate_color" name="certificate_color" 
                       value="<?php echo esc_attr(get_option('sikshya_certificate_color', '#000000')); ?>">
                <p class="description"><?php _e('Primary text color for certificates', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-cog"></i>
            <?php _e('Certificate Behavior', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_generate_certificates" value="1" 
                           <?php checked(get_option('sikshya_auto_generate_certificates', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-generate Certificates', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically generate certificates when students complete courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_certificates" value="1" 
                           <?php checked(get_option('sikshya_email_certificates', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Email Certificates', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically email certificates to students upon completion', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="certificate_expiry_days"><?php _e('Certificate Expiry (days)', 'sikshya'); ?></label>
                <input type="number" id="certificate_expiry_days" name="certificate_expiry_days" 
                       value="<?php echo esc_attr(get_option('sikshya_certificate_expiry_days', 0)); ?>" 
                       min="0" max="3650">
                <p class="description"><?php _e('Days until certificates expire (0 = never expire)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 