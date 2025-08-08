<?php
/**
 * Students Settings Tab Template
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
            <i class="fas fa-user-graduate"></i>
            <?php _e('Student Registration', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_student_registration" value="1" 
                           <?php checked(get_option('sikshya_allow_student_registration', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Student Registration', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow users to register as students', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_approve_students" value="1" 
                           <?php checked(get_option('sikshya_auto_approve_students', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-approve Students', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically approve new student registrations', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="student_role"><?php _e('Student Role', 'sikshya'); ?></label>
                <select id="student_role" name="student_role">
                    <option value="sikshya_student" <?php selected(get_option('sikshya_student_role', 'sikshya_student'), 'sikshya_student'); ?>><?php _e('Sikshya Student', 'sikshya'); ?></option>
                    <option value="subscriber" <?php selected(get_option('sikshya_student_role', 'sikshya_student'), 'subscriber'); ?>><?php _e('Subscriber', 'sikshya'); ?></option>
                    <option value="customer" <?php selected(get_option('sikshya_student_role', 'sikshya_student'), 'customer'); ?>><?php _e('Customer', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('WordPress role assigned to students', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-user-profile"></i>
            <?php _e('Student Profiles', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_student_profiles" value="1" 
                           <?php checked(get_option('sikshya_enable_student_profiles', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Student Profiles', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to create and edit their profiles', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="require_profile_completion" value="1" 
                           <?php checked(get_option('sikshya_require_profile_completion', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Require Profile Completion', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Require students to complete their profile before enrolling', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="profile_fields"><?php _e('Required Profile Fields', 'sikshya'); ?></label>
                <div class="sikshya-checkbox-group">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="require_phone" value="1" 
                               <?php checked(get_option('sikshya_require_phone', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Phone Number', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="require_address" value="1" 
                               <?php checked(get_option('sikshya_require_address', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Address', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="require_bio" value="1" 
                               <?php checked(get_option('sikshya_require_bio', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Biography', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="require_avatar" value="1" 
                               <?php checked(get_option('sikshya_require_avatar', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Profile Picture', 'sikshya'); ?>
                    </label>
                </div>
                <p class="description"><?php _e('Select which profile fields are required', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-chart-bar"></i>
            <?php _e('Student Analytics', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_student_progress" value="1" 
                           <?php checked(get_option('sikshya_show_student_progress', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Progress to Students', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to view their course progress', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_student_analytics" value="1" 
                           <?php checked(get_option('sikshya_show_student_analytics', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Analytics to Students', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to view their learning analytics', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_certificates" value="1" 
                           <?php checked(get_option('sikshya_show_certificates', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Certificates', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to view and download their certificates', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 