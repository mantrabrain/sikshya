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
            
            <?php echo $this->renderField('course_status', $this->getFields()['course_status'], $data['course_status'] ?? ''); ?>
            
            <div class="sikshya-form-row" id="password-field" style="display: none;">
                <?php echo $this->renderField('course_password', $this->getFields()['course_password'], $data['course_password'] ?? ''); ?>
            </div>
            
            <?php echo $this->renderField('featured_course', $this->getFields()['featured_course'], $data['featured_course'] ?? ''); ?>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Discussion & Community', 'sikshya'); ?></h3>
            
            <?php echo $this->renderField('enable_discussions', $this->getFields()['enable_discussions'], $data['enable_discussions'] ?? ''); ?>
            <?php echo $this->renderField('enable_qa', $this->getFields()['enable_qa'], $data['enable_qa'] ?? ''); ?>
            <?php echo $this->renderField('enable_reviews', $this->getFields()['enable_reviews'], $data['enable_reviews'] ?? ''); ?>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('Certificates & Completion', 'sikshya'); ?></h3>
            
            <?php echo $this->renderField('enable_certificate', $this->getFields()['enable_certificate'], $data['enable_certificate'] ?? ''); ?>
            
            <div class="sikshya-form-grid" id="certificate-settings" style="display: none;">
                <?php echo $this->renderField('certificate_template', $this->getFields()['certificate_template'], $data['certificate_template'] ?? ''); ?>
                <?php echo $this->renderField('completion_threshold', $this->getFields()['completion_threshold'], $data['completion_threshold'] ?? ''); ?>
            </div>
        </div>

        <div class="sikshya-section">
            <h3 class="sikshya-section-title"><?php _e('SEO & Meta', 'sikshya'); ?></h3>
            
            <?php echo $this->renderField('seo_title', $this->getFields()['seo_title'], $data['seo_title'] ?? ''); ?>
            <?php echo $this->renderField('seo_description', $this->getFields()['seo_description'], $data['seo_description'] ?? ''); ?>
            <?php echo $this->renderField('seo_keywords', $this->getFields()['seo_keywords'], $data['seo_keywords'] ?? ''); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Override renderField to handle conditional fields
     * 
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @return string
     */
    protected function renderField(string $field_id, array $field_config, $value = ''): string
    {
        $field_html = parent::renderField($field_id, $field_config, $value);
        
        // Add conditional logic for course status
        if ($field_id === 'course_status') {
            $field_html = str_replace(
                '<select name="course_status"',
                '<select name="course_status" onchange="togglePasswordField(this)"',
                $field_html
            );
        }
        
        // Add conditional logic for certificate settings
        if ($field_id === 'enable_certificate') {
            $field_html = str_replace(
                '<input type="checkbox" name="enable_certificate"',
                '<input type="checkbox" name="enable_certificate" onchange="toggleCertificateSettings(this)"',
                $field_html
            );
        }
        
        return $field_html;
    }
}
