<?php
/**
 * Settings Tab for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SettingsTab extends AbstractTab
{
    /**
     * Get the unique identifier for this tab
     * 
     * @return string
     */
    public function getId(): string
    {
        return 'settings';
    }
    
    /**
     * Get the display title for this tab
     * 
     * @return string
     */
    public function getTitle(): string
    {
        return __('Settings', 'sikshya');
    }
    
    /**
     * Get the description for this tab
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return __('Advanced options and SEO', 'sikshya');
    }
    
    /**
     * Get the SVG icon for this tab
     * 
     * @return string
     */
    public function getIcon(): string
    {
        return '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>';
    }
    
    /**
     * Get the tab order
     * 
     * @return int
     */
    public function getOrder(): int
    {
        return 4;
    }
    
    /**
     * Get the fields configuration for this tab
     * 
     * @return array
     */
    public function getFields(): array
    {
        return [
            'course_status' => [
                'type' => 'select',
                'label' => __('Course Status', 'sikshya'),
                'options' => [
                    'draft' => __('Draft', 'sikshya'),
                    'published' => __('Published', 'sikshya'),
                    'private' => __('Private', 'sikshya'),
                    'password_protected' => __('Password Protected', 'sikshya'),
                ],
                'default' => 'draft',
                'description' => __('Set the visibility status of your course', 'sikshya'),
            ],
            'course_password' => [
                'type' => 'password',
                'label' => __('Course Password', 'sikshya'),
                'placeholder' => __('Enter course password', 'sikshya'),
                'description' => __('Password required to access the course', 'sikshya'),
            ],
            'featured_course' => [
                'type' => 'checkbox',
                'label' => __('Mark as Featured Course', 'sikshya'),
                'description' => __('Featured courses appear prominently on your site', 'sikshya'),
            ],
            'enable_discussions' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Discussions', 'sikshya'),
                'default' => '1',
                'description' => __('Allow students to ask questions and discuss topics', 'sikshya'),
            ],
            'enable_qa' => [
                'type' => 'checkbox',
                'label' => __('Enable Q&A Section', 'sikshya'),
                'description' => __('Dedicated section for course-related questions', 'sikshya'),
            ],
            'enable_reviews' => [
                'type' => 'checkbox',
                'label' => __('Allow Course Reviews', 'sikshya'),
                'description' => __('Students can rate and review the course', 'sikshya'),
            ],
            'enable_certificate' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Completion Certificate', 'sikshya'),
                'description' => __('Award certificate when students complete the course', 'sikshya'),
            ],
            'certificate_template' => [
                'type' => 'select',
                'label' => __('Certificate Template', 'sikshya'),
                'options' => [
                    'default' => __('Default Template', 'sikshya'),
                    'modern' => __('Modern Template', 'sikshya'),
                    'classic' => __('Classic Template', 'sikshya'),
                    'custom' => __('Custom Template', 'sikshya'),
                ],
                'default' => 'default',
                'description' => __('Choose the certificate design', 'sikshya'),
            ],
            'completion_threshold' => [
                'type' => 'number',
                'label' => __('Completion Threshold (%)', 'sikshya'),
                'placeholder' => '80',
                'min' => 0,
                'max' => 100,
                'default' => 80,
                'description' => __('Percentage of course completion required for certificate', 'sikshya'),
            ],
            'seo_title' => [
                'type' => 'text',
                'label' => __('SEO Title', 'sikshya'),
                'placeholder' => __('Enter SEO title for search engines', 'sikshya'),
                'description' => __('Custom title for search engine optimization', 'sikshya'),
            ],
            'seo_description' => [
                'type' => 'textarea',
                'label' => __('SEO Description', 'sikshya'),
                'placeholder' => __('Enter SEO description for search engines', 'sikshya'),
                'description' => __('Custom description for search engine optimization', 'sikshya'),
            ],
            'seo_keywords' => [
                'type' => 'text',
                'label' => __('SEO Keywords', 'sikshya'),
                'placeholder' => __('keyword1, keyword2, keyword3', 'sikshya'),
                'description' => __('Comma-separated keywords for SEO', 'sikshya'),
            ],
        ];
    }
    
    /**
     * Render the tab content with exact same HTML markup
     * 
     * @param array $data
     * @return string
     */
    protected function renderContent(array $data): string
    {
        ob_start();
        ?>
        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Course Visibility', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Status', 'sikshya'); ?></label>
                <select name="course_status" onchange="togglePasswordField(this)">
                    <option value="draft" <?php selected($data['course_status'] ?? 'draft', 'draft'); ?>><?php _e('Draft', 'sikshya'); ?></option>
                    <option value="published" <?php selected($data['course_status'] ?? '', 'published'); ?>><?php _e('Published', 'sikshya'); ?></option>
                    <option value="private" <?php selected($data['course_status'] ?? '', 'private'); ?>><?php _e('Private', 'sikshya'); ?></option>
                    <option value="password_protected" <?php selected($data['course_status'] ?? '', 'password_protected'); ?>><?php _e('Password Protected', 'sikshya'); ?></option>
                </select>
            </div>
            
            <div class="sikshya-form-row" id="password-field" style="display: none;">
                <label><?php _e('Course Password', 'sikshya'); ?></label>
                <input type="password" name="course_password" value="<?php echo esc_attr($data['course_password'] ?? ''); ?>" placeholder="<?php _e('Enter course password', 'sikshya'); ?>">
            </div>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="featured_course" class="sikshya-checkbox" <?php checked($data['featured_course'] ?? '', '1'); ?>>
                    <?php _e('Mark as Featured Course', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Featured courses appear prominently on your site', 'sikshya'); ?></p>
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Discussion & Community', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="enable_discussions" class="sikshya-checkbox" <?php checked($data['enable_discussions'] ?? '1', '1'); ?>>
                        <?php _e('Enable Course Discussions', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Allow students to ask questions and discuss topics', 'sikshya'); ?></p>
                </div>
                
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="enable_qa" class="sikshya-checkbox" <?php checked($data['enable_qa'] ?? '', '1'); ?>>
                        <?php _e('Enable Q&A Section', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Dedicated section for course-related questions', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_reviews" class="sikshya-checkbox" <?php checked($data['enable_reviews'] ?? '', '1'); ?>>
                    <?php _e('Allow Course Reviews', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Students can rate and review the course', 'sikshya'); ?></p>
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Certificates & Completion', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_certificate" class="sikshya-checkbox" <?php checked($data['enable_certificate'] ?? '', '1'); ?> onchange="toggleCertificateSettings(this)">
                    <?php _e('Enable Course Completion Certificate', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Award certificate when students complete the course', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-form-grid" id="certificate-settings" style="display: none;">
                <div class="sikshya-form-row">
                    <label><?php _e('Certificate Template', 'sikshya'); ?></label>
                    <select name="certificate_template">
                        <option value="default" <?php selected($data['certificate_template'] ?? 'default', 'default'); ?>><?php _e('Default Template', 'sikshya'); ?></option>
                        <option value="modern" <?php selected($data['certificate_template'] ?? '', 'modern'); ?>><?php _e('Modern Template', 'sikshya'); ?></option>
                        <option value="classic" <?php selected($data['certificate_template'] ?? '', 'classic'); ?>><?php _e('Classic Template', 'sikshya'); ?></option>
                        <option value="custom" <?php selected($data['certificate_template'] ?? '', 'custom'); ?>><?php _e('Custom Template', 'sikshya'); ?></option>
                    </select>
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Completion Threshold (%)', 'sikshya'); ?></label>
                    <input type="number" name="completion_threshold" value="<?php echo esc_attr($data['completion_threshold'] ?? '100'); ?>" min="50" max="100">
                </div>
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('SEO & Metadata', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label><?php _e('SEO Title', 'sikshya'); ?></label>
                    <input type="text" name="seo_title" value="<?php echo esc_attr($data['seo_title'] ?? ''); ?>" placeholder="<?php _e('Optimized title for search engines', 'sikshya'); ?>">
                    <p class="sikshya-help-text"><?php _e('Leave empty to use course title', 'sikshya'); ?></p>
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Focus Keywords', 'sikshya'); ?></label>
                    <input type="text" name="focus_keywords" value="<?php echo esc_attr($data['focus_keywords'] ?? ''); ?>" placeholder="<?php _e('keyword1, keyword2, keyword3', 'sikshya'); ?>">
                    <p class="sikshya-help-text"><?php _e('Comma-separated keywords for SEO', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Meta Description', 'sikshya'); ?></label>
                <textarea name="meta_description" placeholder="<?php _e('Brief description for search engine results (155 characters max)', 'sikshya'); ?>" maxlength="155"><?php echo esc_textarea($data['meta_description'] ?? ''); ?></textarea>
                <p class="sikshya-help-text"><?php _e('Recommended: 150-155 characters', 'sikshya'); ?></p>
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Advanced Settings', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="enable_progress_tracking" class="sikshya-checkbox" <?php checked($data['enable_progress_tracking'] ?? '1', '1'); ?>>
                        <?php _e('Track Student Progress', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Monitor how students progress through the course', 'sikshya'); ?></p>
                </div>
                
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="enable_analytics" class="sikshya-checkbox" <?php checked($data['enable_analytics'] ?? '', '1'); ?>>
                        <?php _e('Enable Course Analytics', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Detailed analytics and reporting for this course', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_offline_access" class="sikshya-checkbox" <?php checked($data['enable_offline_access'] ?? '', '1'); ?>>
                    <?php _e('Allow Offline Access', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Students can download content for offline viewing', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Expiry', 'sikshya'); ?></label>
                <select name="course_expiry_type">
                    <option value="never" <?php selected($data['course_expiry_type'] ?? 'never', 'never'); ?>><?php _e('Never Expires', 'sikshya'); ?></option>
                    <option value="fixed_date" <?php selected($data['course_expiry_type'] ?? '', 'fixed_date'); ?>><?php _e('Fixed Date', 'sikshya'); ?></option>
                    <option value="relative" <?php selected($data['course_expiry_type'] ?? '', 'relative'); ?>><?php _e('Relative to Enrollment', 'sikshya'); ?></option>
                </select>
            </div>
            
            <div class="sikshya-form-row" id="expiry-date-field" style="display: none;">
                <label><?php _e('Expiry Date', 'sikshya'); ?></label>
                <input type="date" name="expiry_date" value="<?php echo esc_attr($data['expiry_date'] ?? ''); ?>">
            </div>
            
            <div class="sikshya-form-row" id="expiry-duration-field" style="display: none;">
                <label><?php _e('Access Duration (Days)', 'sikshya'); ?></label>
                <input type="number" name="access_duration" value="<?php echo esc_attr($data['access_duration'] ?? ''); ?>" placeholder="365" min="1">
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Notifications', 'sikshya'); ?></h3>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="notify_enrollment" class="sikshya-checkbox" <?php checked($data['notify_enrollment'] ?? '1', '1'); ?>>
                        <?php _e('Email on New Enrollment', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Get notified when someone enrolls in this course', 'sikshya'); ?></p>
                </div>
                
                <div class="sikshya-form-row">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="notify_completion" class="sikshya-checkbox" <?php checked($data['notify_completion'] ?? '', '1'); ?>>
                        <?php _e('Email on Course Completion', 'sikshya'); ?>
                    </label>
                    <p class="sikshya-help-text"><?php _e('Get notified when someone completes this course', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="send_welcome_email" class="sikshya-checkbox" <?php checked($data['send_welcome_email'] ?? '1', '1'); ?>>
                    <?php _e('Send Welcome Email to Students', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Automatically send welcome email upon enrollment', 'sikshya'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
}
