<?php
/**
 * Email Settings Tab Template
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
            <i class="fas fa-envelope"></i>
            <?php _e('Email Configuration', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="from_name"><?php _e('From Name', 'sikshya'); ?></label>
                <input type="text" id="from_name" name="from_name" 
                       value="<?php echo esc_attr(get_option('sikshya_from_name', get_bloginfo('name'))); ?>" 
                       placeholder="<?php _e('Your LMS Name', 'sikshya'); ?>">
                <p class="description"><?php _e('Name to use in email "From" field', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="from_email"><?php _e('From Email', 'sikshya'); ?></label>
                <input type="email" id="from_email" name="from_email" 
                       value="<?php echo esc_attr(get_option('sikshya_from_email', get_option('admin_email'))); ?>" 
                       placeholder="noreply@yoursite.com">
                <p class="description"><?php _e('Email address to use in "From" field', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="reply_to_email"><?php _e('Reply-To Email', 'sikshya'); ?></label>
                <input type="email" id="reply_to_email" name="reply_to_email" 
                       value="<?php echo esc_attr(get_option('sikshya_reply_to_email', get_option('admin_email'))); ?>" 
                       placeholder="support@yoursite.com">
                <p class="description"><?php _e('Email address for replies', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-bell"></i>
            <?php _e('Email Notifications', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_welcome_email" value="1" 
                           <?php checked(get_option('sikshya_enable_welcome_email', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Welcome Email', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send welcome email to new students', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_enrollment_email" value="1" 
                           <?php checked(get_option('sikshya_enable_enrollment_email', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enrollment Email', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send confirmation email when students enroll', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_completion_email" value="1" 
                           <?php checked(get_option('sikshya_enable_completion_email', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Completion Email', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email when students complete courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_reminder_email" value="1" 
                           <?php checked(get_option('sikshya_enable_reminder_email', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Progress Reminder Emails', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send reminder emails to inactive students', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-edit"></i>
            <?php _e('Email Templates', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="email_template_header"><?php _e('Email Header', 'sikshya'); ?></label>
                <textarea id="email_template_header" name="email_template_header" rows="4" 
                          placeholder="<?php _e('Enter your email header HTML...', 'sikshya'); ?>"><?php echo esc_textarea(get_option('sikshya_email_template_header', '')); ?></textarea>
                <p class="description"><?php _e('HTML header for all LMS emails', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="email_template_footer"><?php _e('Email Footer', 'sikshya'); ?></label>
                <textarea id="email_template_footer" name="email_template_footer" rows="4" 
                          placeholder="<?php _e('Enter your email footer HTML...', 'sikshya'); ?>"><?php echo esc_textarea(get_option('sikshya_email_template_footer', '')); ?></textarea>
                <p class="description"><?php _e('HTML footer for all LMS emails', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 