<?php
/**
 * Courses Settings Tab Template
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
            <i class="fas fa-list"></i>
            <?php _e('Course Display', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="courses_per_page"><?php _e('Courses per Page', 'sikshya'); ?></label>
                <input type="number" id="courses_per_page" name="courses_per_page" 
                       value="<?php echo esc_attr(get_option('sikshya_courses_per_page', 12)); ?>" 
                       min="1" max="50">
                <p class="description"><?php _e('Number of courses to display per page in course listings', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="course_archive_layout"><?php _e('Course Archive Layout', 'sikshya'); ?></label>
                <select id="course_archive_layout" name="course_archive_layout">
                    <option value="grid" <?php selected(get_option('sikshya_course_archive_layout', 'grid'), 'grid'); ?>><?php _e('Grid Layout', 'sikshya'); ?></option>
                    <option value="list" <?php selected(get_option('sikshya_course_archive_layout', 'grid'), 'list'); ?>><?php _e('List Layout', 'sikshya'); ?></option>
                    <option value="masonry" <?php selected(get_option('sikshya_course_archive_layout', 'grid'), 'masonry'); ?>><?php _e('Masonry Layout', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Layout style for course archive pages', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="course_single_layout"><?php _e('Single Course Layout', 'sikshya'); ?></label>
                <select id="course_single_layout" name="course_single_layout">
                    <option value="default" <?php selected(get_option('sikshya_course_single_layout', 'default'), 'default'); ?>><?php _e('Default Layout', 'sikshya'); ?></option>
                    <option value="sidebar" <?php selected(get_option('sikshya_course_single_layout', 'default'), 'sidebar'); ?>><?php _e('Sidebar Layout', 'sikshya'); ?></option>
                    <option value="fullwidth" <?php selected(get_option('sikshya_course_single_layout', 'default'), 'fullwidth'); ?>><?php _e('Full Width Layout', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Layout style for individual course pages', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-star"></i>
            <?php _e('Reviews & Ratings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_reviews" value="1" 
                           <?php checked(get_option('sikshya_enable_reviews', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Reviews', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to write detailed reviews for courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_ratings" value="1" 
                           <?php checked(get_option('sikshya_enable_ratings', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Ratings', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to rate courses with stars', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="review_approval"><?php _e('Review Approval', 'sikshya'); ?></label>
                <select id="review_approval" name="review_approval">
                    <option value="auto" <?php selected(get_option('sikshya_review_approval', 'auto'), 'auto'); ?>><?php _e('Auto-approve', 'sikshya'); ?></option>
                    <option value="manual" <?php selected(get_option('sikshya_review_approval', 'auto'), 'manual'); ?>><?php _e('Manual approval required', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Whether reviews need manual approval before being published', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-tags"></i>
            <?php _e('Categories & Tags', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_course_categories" value="1" 
                           <?php checked(get_option('sikshya_enable_course_categories', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Categories', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow organizing courses into categories', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_course_tags" value="1" 
                           <?php checked(get_option('sikshya_enable_course_tags', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Tags', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow tagging courses for better organization', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="category_display"><?php _e('Category Display', 'sikshya'); ?></label>
                <select id="category_display" name="category_display">
                    <option value="list" <?php selected(get_option('sikshya_category_display', 'list'), 'list'); ?>><?php _e('List View', 'sikshya'); ?></option>
                    <option value="grid" <?php selected(get_option('sikshya_category_display', 'list'), 'grid'); ?>><?php _e('Grid View', 'sikshya'); ?></option>
                    <option value="dropdown" <?php selected(get_option('sikshya_category_display', 'list'), 'dropdown'); ?>><?php _e('Dropdown Menu', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('How to display course categories on the frontend', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-search"></i>
            <?php _e('Search & Filters', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_course_search" value="1" 
                           <?php checked(get_option('sikshya_enable_course_search', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Search', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow users to search through available courses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_course_filters" value="1" 
                           <?php checked(get_option('sikshya_enable_course_filters', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Filters', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow filtering courses by price, level, duration, etc.', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="search_fields"><?php _e('Search Fields', 'sikshya'); ?></label>
                <div class="sikshya-checkbox-group">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="search_title" value="1" 
                               <?php checked(get_option('sikshya_search_title', true)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Course Title', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="search_description" value="1" 
                               <?php checked(get_option('sikshya_search_description', true)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Course Description', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="search_instructor" value="1" 
                               <?php checked(get_option('sikshya_search_instructor', true)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Instructor Name', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="search_categories" value="1" 
                               <?php checked(get_option('sikshya_search_categories', true)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Categories & Tags', 'sikshya'); ?>
                    </label>
                </div>
                <p class="description"><?php _e('Which fields to include in course search', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-user-plus"></i>
            <?php _e('Enrollment Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_enroll" value="1" 
                           <?php checked(get_option('sikshya_auto_enroll', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-enroll on Purchase', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically enroll students when they purchase a course', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="enrollment_button_text"><?php _e('Enrollment Button Text', 'sikshya'); ?></label>
                <input type="text" id="enrollment_button_text" name="enrollment_button_text" 
                       value="<?php echo esc_attr(get_option('sikshya_enrollment_button_text', 'Enroll Now')); ?>">
                <p class="description"><?php _e('Text to display on course enrollment buttons', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="free_course_text"><?php _e('Free Course Button Text', 'sikshya'); ?></label>
                <input type="text" id="free_course_text" name="free_course_text" 
                       value="<?php echo esc_attr(get_option('sikshya_free_course_text', 'Start Learning')); ?>">
                <p class="description"><?php _e('Text to display on free course enrollment buttons', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-cog"></i>
            <?php _e('Advanced Course Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="course_completion_criteria"><?php _e('Completion Criteria', 'sikshya'); ?></label>
                <select id="course_completion_criteria" name="course_completion_criteria">
                    <option value="all_lessons" <?php selected(get_option('sikshya_course_completion_criteria', 'all_lessons'), 'all_lessons'); ?>><?php _e('All Lessons Completed', 'sikshya'); ?></option>
                    <option value="all_lessons_quizzes" <?php selected(get_option('sikshya_course_completion_criteria', 'all_lessons'), 'all_lessons_quizzes'); ?>><?php _e('All Lessons + Quizzes', 'sikshya'); ?></option>
                    <option value="percentage" <?php selected(get_option('sikshya_course_completion_criteria', 'all_lessons'), 'percentage'); ?>><?php _e('Percentage Based', 'sikshya'); ?></option>
                    <option value="manual" <?php selected(get_option('sikshya_course_completion_criteria', 'all_lessons'), 'manual'); ?>><?php _e('Manual Completion', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Criteria for marking a course as completed', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="completion_percentage" id="completion_percentage_label" style="display: none;"><?php _e('Completion Percentage (%)', 'sikshya'); ?></label>
                <input type="number" id="completion_percentage" name="completion_percentage" 
                       value="<?php echo esc_attr(get_option('sikshya_completion_percentage', 80)); ?>" 
                       min="1" max="100" style="display: none;">
                <p class="description" id="completion_percentage_desc" style="display: none;"><?php _e('Percentage of course content that must be completed', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_course_preview" value="1" 
                           <?php checked(get_option('sikshya_enable_course_preview', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Course Preview', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow non-enrolled users to preview course content', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="preview_lessons_count"><?php _e('Preview Lessons Count', 'sikshya'); ?></label>
                <input type="number" id="preview_lessons_count" name="preview_lessons_count" 
                       value="<?php echo esc_attr(get_option('sikshya_preview_lessons_count', 3)); ?>" 
                       min="0" max="10">
                <p class="description"><?php _e('Number of lessons available for preview (0 = disabled)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 