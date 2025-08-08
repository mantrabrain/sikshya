<?php
/**
 * Quizzes Settings Tab Template
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
            <i class="fas fa-question-circle"></i>
            <?php _e('Quiz Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_quizzes" value="1" 
                           <?php checked(get_option('sikshya_enable_quizzes', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Quizzes', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable quiz functionality in courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="quiz_time_limit"><?php _e('Default Time Limit (minutes)', 'sikshya'); ?></label>
                <input type="number" id="quiz_time_limit" name="quiz_time_limit" 
                       value="<?php echo esc_attr(get_option('sikshya_quiz_time_limit', 30)); ?>" 
                       min="1" max="480">
                <p class="description"><?php _e('Default time limit for quizzes (1-480 minutes)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="quiz_attempts"><?php _e('Default Attempts Allowed', 'sikshya'); ?></label>
                <input type="number" id="quiz_attempts" name="quiz_attempts" 
                       value="<?php echo esc_attr(get_option('sikshya_quiz_attempts', 3)); ?>" 
                       min="1" max="10">
                <p class="description"><?php _e('Default number of attempts allowed per quiz', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-eye"></i>
            <?php _e('Quiz Display', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_quiz_results" value="1" 
                           <?php checked(get_option('sikshya_show_quiz_results', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Quiz Results', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Show results immediately after quiz completion', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="show_correct_answers" value="1" 
                           <?php checked(get_option('sikshya_show_correct_answers', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Show Correct Answers', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Show correct answers after quiz completion', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="randomize_questions" value="1" 
                           <?php checked(get_option('sikshya_randomize_questions', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Randomize Questions', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Randomize question order for each attempt', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="randomize_answers" value="1" 
                           <?php checked(get_option('sikshya_randomize_answers', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Randomize Answer Options', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Randomize answer options for multiple choice questions', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-percentage"></i>
            <?php _e('Grading & Scoring', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="passing_grade"><?php _e('Default Passing Grade (%)', 'sikshya'); ?></label>
                <input type="number" id="passing_grade" name="passing_grade" 
                       value="<?php echo esc_attr(get_option('sikshya_passing_grade', 70)); ?>" 
                       min="0" max="100">
                <p class="description"><?php _e('Default passing grade percentage for quizzes', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="question_points"><?php _e('Default Points per Question', 'sikshya'); ?></label>
                <input type="number" id="question_points" name="question_points" 
                       value="<?php echo esc_attr(get_option('sikshya_question_points', 1)); ?>" 
                       min="1" max="100">
                <p class="description"><?php _e('Default points awarded for each correct answer', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="negative_marking"><?php _e('Negative Marking (%)', 'sikshya'); ?></label>
                <input type="number" id="negative_marking" name="negative_marking" 
                       value="<?php echo esc_attr(get_option('sikshya_negative_marking', 0)); ?>" 
                       min="0" max="100" step="0.1">
                <p class="description"><?php _e('Percentage of points deducted for wrong answers', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-shield-alt"></i>
            <?php _e('Quiz Security', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="prevent_cheating" value="1" 
                           <?php checked(get_option('sikshya_prevent_cheating', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Prevent Cheating', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable anti-cheating measures (fullscreen, tab switching detection)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="require_fullscreen" value="1" 
                           <?php checked(get_option('sikshya_require_fullscreen', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Require Fullscreen Mode', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Require students to enter fullscreen mode during quizzes', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="disable_copy_paste" value="1" 
                           <?php checked(get_option('sikshya_disable_copy_paste', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Disable Copy/Paste', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Disable copy and paste functionality during quizzes', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 