<?php
/**
 * Progress Settings Tab Template
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
            <i class="fas fa-chart-line"></i>
            <?php _e('Progress Tracking', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_progress_tracking" value="1" 
                           <?php checked(get_option('sikshya_enable_progress_tracking', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Progress Tracking', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Track student progress through courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="progress_update_frequency"><?php _e('Progress Update Frequency', 'sikshya'); ?></label>
                <select id="progress_update_frequency" name="progress_update_frequency">
                    <option value="realtime" <?php selected(get_option('sikshya_progress_update_frequency', 'realtime'), 'realtime'); ?>><?php _e('Real-time', 'sikshya'); ?></option>
                    <option value="minute" <?php selected(get_option('sikshya_progress_update_frequency', 'realtime'), 'minute'); ?>><?php _e('Every Minute', 'sikshya'); ?></option>
                    <option value="5minutes" <?php selected(get_option('sikshya_progress_update_frequency', 'realtime'), '5minutes'); ?>><?php _e('Every 5 Minutes', 'sikshya'); ?></option>
                    <option value="session" <?php selected(get_option('sikshya_progress_update_frequency', 'realtime'), 'session'); ?>><?php _e('Per Session', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How often to update progress tracking', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="track_time_spent" value="1" 
                           <?php checked(get_option('sikshya_track_time_spent', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Track Time Spent', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Track time spent on each lesson and course', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-trophy"></i>
            <?php _e('Completion Criteria', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="completion_method"><?php _e('Completion Method', 'sikshya'); ?></label>
                <select id="completion_method" name="completion_method">
                    <option value="all_lessons" <?php selected(get_option('sikshya_completion_method', 'all_lessons'), 'all_lessons'); ?>><?php _e('All Lessons Completed', 'sikshya'); ?></option>
                    <option value="percentage" <?php selected(get_option('sikshya_completion_method', 'all_lessons'), 'percentage'); ?>><?php _e('Percentage Based', 'sikshya'); ?></option>
                    <option value="time_spent" <?php selected(get_option('sikshya_completion_method', 'all_lessons'), 'time_spent'); ?>><?php _e('Time Spent Based', 'sikshya'); ?></option>
                    <option value="manual" <?php selected(get_option('sikshya_completion_method', 'all_lessons'), 'manual'); ?>><?php _e('Manual Completion', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Method used to determine course completion', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="completion_percentage" id="completion_percentage_label" style="display: none;"><?php _e('Completion Percentage (%)', 'sikshya'); ?></label>
                <input type="number" id="completion_percentage" name="completion_percentage" 
                       value="<?php echo esc_attr(get_option('sikshya_completion_percentage', 80)); ?>" 
                       min="1" max="100" style="display: none;">
                <p class="description" id="completion_percentage_desc" style="display: none;"><?php _e('Percentage of course content required for completion', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="minimum_time_minutes" id="minimum_time_label" style="display: none;"><?php _e('Minimum Time (minutes)', 'sikshya'); ?></label>
                <input type="number" id="minimum_time_minutes" name="minimum_time_minutes" 
                       value="<?php echo esc_attr(get_option('sikshya_minimum_time_minutes', 60)); ?>" 
                       min="1" max="1440" style="display: none;">
                <p class="description" id="minimum_time_desc" style="display: none;"><?php _e('Minimum time required to spend on course', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-eye"></i>
            <?php _e('Progress Display', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_progress_bar" value="1" 
                           <?php checked(get_option('sikshya_show_progress_bar', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Progress Bar', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Display progress bar in course interface', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_percentage" value="1" 
                           <?php checked(get_option('sikshya_show_percentage', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Percentage', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Display completion percentage', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_time_spent" value="1" 
                           <?php checked(get_option('sikshya_show_time_spent', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Time Spent', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Display time spent on course', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_remaining_time" value="1" 
                           <?php checked(get_option('sikshya_show_remaining_time', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Remaining Time', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Display estimated time remaining', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-sync"></i>
            <?php _e('Progress Reset', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="allow_progress_reset" value="1" 
                           <?php checked(get_option('sikshya_allow_progress_reset', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Allow Progress Reset', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to reset their progress', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="progress_reset_limit"><?php _e('Reset Limit', 'sikshya'); ?></label>
                <input type="number" id="progress_reset_limit" name="progress_reset_limit" 
                       value="<?php echo esc_attr(get_option('sikshya_progress_reset_limit', 3)); ?>" 
                       min="0" max="10">
                <p class="description"><?php _e('Maximum number of times a student can reset progress (0 = unlimited)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="progress_reset_cooldown"><?php _e('Reset Cooldown (hours)', 'sikshya'); ?></label>
                <input type="number" id="progress_reset_cooldown" name="progress_reset_cooldown" 
                       value="<?php echo esc_attr(get_option('sikshya_progress_reset_cooldown', 24)); ?>" 
                       min="0" max="168">
                <p class="description"><?php _e('Hours to wait between progress resets (0 = no cooldown)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 