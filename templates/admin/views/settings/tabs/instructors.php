<?php
/**
 * Instructors Settings Tab Template
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
            <i class="fas fa-chalkboard-teacher"></i>
            <?php _e('Instructor Management', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_instructor_registration" value="1" 
                           <?php checked(get_option('sikshya_allow_instructor_registration', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Instructor Registration', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow users to register as instructors', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_approve_instructors" value="1" 
                           <?php checked(get_option('sikshya_auto_approve_instructors', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-approve Instructors', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically approve new instructor registrations', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="instructor_role"><?php _e('Instructor Role', 'sikshya'); ?></label>
                <select id="instructor_role" name="instructor_role">
                    <option value="sikshya_instructor" <?php selected(get_option('sikshya_instructor_role', 'sikshya_instructor'), 'sikshya_instructor'); ?>><?php _e('Sikshya Instructor', 'sikshya'); ?></option>
                    <option value="author" <?php selected(get_option('sikshya_instructor_role', 'sikshya_instructor'), 'author'); ?>><?php _e('Author', 'sikshya'); ?></option>
                    <option value="editor" <?php selected(get_option('sikshya_instructor_role', 'sikshya_instructor'), 'editor'); ?>><?php _e('Editor', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('WordPress role assigned to instructors', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-percentage"></i>
            <?php _e('Commission Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="default_instructor_commission"><?php _e('Default Commission (%)', 'sikshya'); ?></label>
                <input type="number" id="default_instructor_commission" name="default_instructor_commission" 
                       value="<?php echo esc_attr(get_option('sikshya_default_instructor_commission', 70)); ?>" 
                       min="0" max="100" step="0.01">
                <p class="description"><?php _e('Default commission percentage for instructors', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="commission_payout_threshold"><?php _e('Payout Threshold', 'sikshya'); ?></label>
                <input type="number" id="commission_payout_threshold" name="commission_payout_threshold" 
                       value="<?php echo esc_attr(get_option('sikshya_commission_payout_threshold', 50)); ?>" 
                       min="0" step="0.01">
                <p class="description"><?php _e('Minimum amount required for commission payout', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="commission_payout_schedule"><?php _e('Payout Schedule', 'sikshya'); ?></label>
                <select id="commission_payout_schedule" name="commission_payout_schedule">
                    <option value="weekly" <?php selected(get_option('sikshya_commission_payout_schedule', 'monthly'), 'weekly'); ?>><?php _e('Weekly', 'sikshya'); ?></option>
                    <option value="biweekly" <?php selected(get_option('sikshya_commission_payout_schedule', 'monthly'), 'biweekly'); ?>><?php _e('Bi-weekly', 'sikshya'); ?></option>
                    <option value="monthly" <?php selected(get_option('sikshya_commission_payout_schedule', 'monthly'), 'monthly'); ?>><?php _e('Monthly', 'sikshya'); ?></option>
                    <option value="quarterly" <?php selected(get_option('sikshya_commission_payout_schedule', 'monthly'), 'quarterly'); ?>><?php _e('Quarterly', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How often to process commission payouts', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-chart-line"></i>
            <?php _e('Instructor Analytics', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_instructor_analytics" value="1" 
                           <?php checked(get_option('sikshya_show_instructor_analytics', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Analytics to Instructors', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow instructors to view their course analytics', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_student_details" value="1" 
                           <?php checked(get_option('sikshya_show_student_details', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Student Details', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow instructors to view student information', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_instructor_communication" value="1" 
                           <?php checked(get_option('sikshya_allow_instructor_communication', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Instructor Communication', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow instructors to communicate with students', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 