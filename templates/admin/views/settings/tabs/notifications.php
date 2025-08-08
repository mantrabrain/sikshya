<?php
/**
 * Notifications Settings Tab Template
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
            <i class="fas fa-bell"></i>
            <?php _e('Notification Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_notifications" value="1" 
                           <?php checked(get_option('sikshya_enable_notifications', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable notification system for the LMS', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="notification_frequency"><?php _e('Notification Frequency', 'sikshya'); ?></label>
                <select id="notification_frequency" name="notification_frequency">
                    <option value="immediate" <?php selected(get_option('sikshya_notification_frequency', 'immediate'), 'immediate'); ?>><?php _e('Immediate', 'sikshya'); ?></option>
                    <option value="hourly" <?php selected(get_option('sikshya_notification_frequency', 'immediate'), 'hourly'); ?>><?php _e('Hourly', 'sikshya'); ?></option>
                    <option value="daily" <?php selected(get_option('sikshya_notification_frequency', 'immediate'), 'daily'); ?>><?php _e('Daily', 'sikshya'); ?></option>
                    <option value="weekly" <?php selected(get_option('sikshya_notification_frequency', 'immediate'), 'weekly'); ?>><?php _e('Weekly', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How often to send notification digests', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="notification_retention_days"><?php _e('Notification Retention (days)', 'sikshya'); ?></label>
                <input type="number" id="notification_retention_days" name="notification_retention_days" 
                       value="<?php echo esc_attr(get_option('sikshya_notification_retention_days', 30)); ?>" 
                       min="1" max="365">
                <p class="description"><?php _e('How long to keep notification history', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-envelope"></i>
            <?php _e('Email Notifications', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_enrollment_notification" value="1" 
                           <?php checked(get_option('sikshya_email_enrollment_notification', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enrollment Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email when students enroll in courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_completion_notification" value="1" 
                           <?php checked(get_option('sikshya_email_completion_notification', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Completion Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email when students complete courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_quiz_notification" value="1" 
                           <?php checked(get_option('sikshya_email_quiz_notification', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Quiz Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email when students take quizzes', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_assignment_notification" value="1" 
                           <?php checked(get_option('sikshya_email_assignment_notification', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Assignment Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email when students submit assignments', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-bullhorn"></i>
            <?php _e('Admin Notifications', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_admin_enrollment" value="1" 
                           <?php checked(get_option('sikshya_notify_admin_enrollment', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('New Enrollment Alerts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Notify admin when new students enroll', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_admin_completion" value="1" 
                           <?php checked(get_option('sikshya_notify_admin_completion', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Course Completion Alerts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Notify admin when students complete courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_admin_payment" value="1" 
                           <?php checked(get_option('sikshya_notify_admin_payment', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Payment Alerts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Notify admin when payments are received', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_admin_issues" value="1" 
                           <?php checked(get_option('sikshya_notify_admin_issues', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('System Issues Alerts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Notify admin of system issues or errors', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-mobile-alt"></i>
            <?php _e('Push Notifications', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_push_notifications" value="1" 
                           <?php checked(get_option('sikshya_enable_push_notifications', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Push Notifications', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable browser push notifications', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="push_notification_icon"><?php _e('Push Notification Icon', 'sikshya'); ?></label>
                <input type="url" id="push_notification_icon" name="push_notification_icon" 
                       value="<?php echo esc_url(get_option('sikshya_push_notification_icon', '')); ?>" 
                       placeholder="https://example.com/icon.png">
                <p class="description"><?php _e('Icon to display in push notifications (192x192px recommended)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="push_notification_sound"><?php _e('Notification Sound', 'sikshya'); ?></label>
                <select id="push_notification_sound" name="push_notification_sound">
                    <option value="default" <?php selected(get_option('sikshya_push_notification_sound', 'default'), 'default'); ?>><?php _e('Default', 'sikshya'); ?></option>
                    <option value="chime" <?php selected(get_option('sikshya_push_notification_sound', 'default'), 'chime'); ?>><?php _e('Chime', 'sikshya'); ?></option>
                    <option value="bell" <?php selected(get_option('sikshya_push_notification_sound', 'default'), 'bell'); ?>><?php _e('Bell', 'sikshya'); ?></option>
                    <option value="none" <?php selected(get_option('sikshya_push_notification_sound', 'default'), 'none'); ?>><?php _e('No Sound', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Sound to play for push notifications', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 