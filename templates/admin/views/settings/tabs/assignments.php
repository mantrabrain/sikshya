<?php
/**
 * Assignments Settings Tab Template
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
            <i class="fas fa-tasks"></i>
            <?php _e('Assignment Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_assignments" value="1" 
                           <?php checked(get_option('sikshya_enable_assignments', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Assignments', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable assignment functionality in courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="max_file_size"><?php _e('Max File Size (MB)', 'sikshya'); ?></label>
                <input type="number" id="max_file_size" name="max_file_size" 
                       value="<?php echo esc_attr(get_option('sikshya_max_file_size', 10)); ?>" 
                       min="1" max="100">
                <p class="description"><?php _e('Maximum file size allowed for assignment submissions', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="allowed_file_types"><?php _e('Allowed File Types', 'sikshya'); ?></label>
                <input type="text" id="allowed_file_types" name="allowed_file_types" 
                       value="<?php echo esc_attr(get_option('sikshya_allowed_file_types', 'pdf,doc,docx,txt,jpg,jpeg,png')); ?>" 
                       placeholder="pdf,doc,docx,txt,jpg,jpeg,png">
                <p class="description"><?php _e('Comma-separated list of allowed file extensions', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-clock"></i>
            <?php _e('Submission Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_late_submissions" value="1" 
                           <?php checked(get_option('sikshya_allow_late_submissions', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Late Submissions', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to submit assignments after the deadline', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="late_submission_penalty"><?php _e('Late Submission Penalty (%)', 'sikshya'); ?></label>
                <input type="number" id="late_submission_penalty" name="late_submission_penalty" 
                       value="<?php echo esc_attr(get_option('sikshya_late_submission_penalty', 10)); ?>" 
                       min="0" max="100">
                <p class="description"><?php _e('Percentage penalty for late submissions', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="max_submissions"><?php _e('Max Submissions per Assignment', 'sikshya'); ?></label>
                <input type="number" id="max_submissions" name="max_submissions" 
                       value="<?php echo esc_attr(get_option('sikshya_max_submissions', 3)); ?>" 
                       min="1" max="10">
                <p class="description"><?php _e('Maximum number of submissions allowed per assignment', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-star"></i>
            <?php _e('Grading Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="default_assignment_grade"><?php _e('Default Max Grade', 'sikshya'); ?></label>
                <input type="number" id="default_assignment_grade" name="default_assignment_grade" 
                       value="<?php echo esc_attr(get_option('sikshya_default_assignment_grade', 100)); ?>" 
                       min="1" max="1000">
                <p class="description"><?php _e('Default maximum grade for assignments', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_grade_assignments" value="1" 
                           <?php checked(get_option('sikshya_auto_grade_assignments', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Auto-grading', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable automatic grading for certain assignment types', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="grading_deadline_days"><?php _e('Grading Deadline (days)', 'sikshya'); ?></label>
                <input type="number" id="grading_deadline_days" name="grading_deadline_days" 
                       value="<?php echo esc_attr(get_option('sikshya_grading_deadline_days', 7)); ?>" 
                       min="1" max="30">
                <p class="description"><?php _e('Default deadline for instructors to grade assignments', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-bell"></i>
            <?php _e('Notifications', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_instructor_submission" value="1" 
                           <?php checked(get_option('sikshya_notify_instructor_submission', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Notify Instructor on Submission', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Email instructor when student submits assignment', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_student_grade" value="1" 
                           <?php checked(get_option('sikshya_notify_student_grade', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Notify Student on Grading', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Email student when assignment is graded', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="notify_late_submission" value="1" 
                           <?php checked(get_option('sikshya_notify_late_submission', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Notify on Late Submission', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Email instructor when assignment is submitted late', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 