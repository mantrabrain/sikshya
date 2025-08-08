<?php
/**
 * Enrollment Settings Tab Template
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
            <i class="fas fa-user-plus"></i>
            <?php _e('Enrollment Access', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_guest_enrollment" value="1" 
                           <?php checked(get_option('sikshya_allow_guest_enrollment', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Guest Enrollment', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow guests to enroll in courses without registration', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="require_login" value="1" 
                           <?php checked(get_option('sikshya_require_login', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Require Login for Course Access', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Require users to be logged in to access course content', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_waitlist" value="1" 
                           <?php checked(get_option('sikshya_enable_waitlist', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Waitlist', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to join waitlist for full courses', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-users"></i>
            <?php _e('Enrollment Limits', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="max_students_per_course"><?php _e('Max Students per Course', 'sikshya'); ?></label>
                <input type="number" id="max_students_per_course" name="max_students_per_course" 
                       value="<?php echo esc_attr(get_option('sikshya_max_students_per_course', 0)); ?>" 
                       min="0" max="10000">
                <p class="description"><?php _e('Maximum number of students per course (0 = unlimited)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="enrollment_expiry_days"><?php _e('Enrollment Expiry (days)', 'sikshya'); ?></label>
                <input type="number" id="enrollment_expiry_days" name="enrollment_expiry_days" 
                       value="<?php echo esc_attr(get_option('sikshya_enrollment_expiry_days', 0)); ?>" 
                       min="0" max="3650">
                <p class="description"><?php _e('Days until enrollment expires (0 = never expires)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="max_courses_per_student"><?php _e('Max Courses per Student', 'sikshya'); ?></label>
                <input type="number" id="max_courses_per_student" name="max_courses_per_student" 
                       value="<?php echo esc_attr(get_option('sikshya_max_courses_per_student', 0)); ?>" 
                       min="0" max="1000">
                <p class="description"><?php _e('Maximum courses a student can enroll in (0 = unlimited)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-sign-out-alt"></i>
            <?php _e('Unenrollment Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_unenroll" value="1" 
                           <?php checked(get_option('sikshya_allow_unenroll', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Unenrollment', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to unenroll from courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="unenroll_refund" value="1" 
                           <?php checked(get_option('sikshya_unenroll_refund', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto Refund on Unenrollment', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically refund payment when student unenrolls', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="unenroll_deadline_days"><?php _e('Unenrollment Deadline (days)', 'sikshya'); ?></label>
                <input type="number" id="unenroll_deadline_days" name="unenroll_deadline_days" 
                       value="<?php echo esc_attr(get_option('sikshya_unenroll_deadline_days', 7)); ?>" 
                       min="0" max="365">
                <p class="description"><?php _e('Days after enrollment when unenrollment is no longer allowed', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-lock"></i>
            <?php _e('Prerequisites & Restrictions', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_prerequisites" value="1" 
                           <?php checked(get_option('sikshya_enable_prerequisites', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Prerequisites', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow setting course prerequisites', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="prerequisite_check_type"><?php _e('Prerequisite Check Type', 'sikshya'); ?></label>
                <select id="prerequisite_check_type" name="prerequisite_check_type">
                    <option value="completion" <?php selected(get_option('sikshya_prerequisite_check_type', 'completion'), 'completion'); ?>><?php _e('Course Completion', 'sikshya'); ?></option>
                    <option value="enrollment" <?php selected(get_option('sikshya_prerequisite_check_type', 'completion'), 'enrollment'); ?>><?php _e('Course Enrollment', 'sikshya'); ?></option>
                    <option value="grade" <?php selected(get_option('sikshya_prerequisite_check_type', 'completion'), 'grade'); ?>><?php _e('Minimum Grade', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How to check if prerequisites are met', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="minimum_grade_prerequisite" id="minimum_grade_label" style="display: none;"><?php _e('Minimum Grade (%)', 'sikshya'); ?></label>
                <input type="number" id="minimum_grade_prerequisite" name="minimum_grade_prerequisite" 
                       value="<?php echo esc_attr(get_option('sikshya_minimum_grade_prerequisite', 70)); ?>" 
                       min="0" max="100" style="display: none;">
                <p class="description" id="minimum_grade_desc" style="display: none;"><?php _e('Minimum grade required in prerequisite courses', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-calendar-alt"></i>
            <?php _e('Enrollment Periods', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_enrollment_periods" value="1" 
                           <?php checked(get_option('sikshya_enable_enrollment_periods', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Enrollment Periods', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Restrict enrollment to specific time periods', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="default_enrollment_start"><?php _e('Default Enrollment Start', 'sikshya'); ?></label>
                <input type="datetime-local" id="default_enrollment_start" name="default_enrollment_start" 
                       value="<?php echo esc_attr(get_option('sikshya_default_enrollment_start', '')); ?>">
                <p class="description"><?php _e('Default start date for course enrollment periods', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="default_enrollment_end"><?php _e('Default Enrollment End', 'sikshya'); ?></label>
                <input type="datetime-local" id="default_enrollment_end" name="default_enrollment_end" 
                       value="<?php echo esc_attr(get_option('sikshya_default_enrollment_end', '')); ?>">
                <p class="description"><?php _e('Default end date for course enrollment periods', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 