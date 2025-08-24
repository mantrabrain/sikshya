<?php
/**
 * Pricing Tab for Course Builder
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

class PricingTab extends AbstractTab
{
    /**
     * Get the unique identifier for this tab
     * 
     * @return string
     */
    public function getId(): string
    {
        return 'pricing';
    }
    
    /**
     * Get the display title for this tab
     * 
     * @return string
     */
    public function getTitle(): string
    {
        return __('Pricing & Access', 'sikshya');
    }
    
    /**
     * Get the description for this tab
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return __('Set price and enrollment options', 'sikshya');
    }
    
    /**
     * Get the SVG icon for this tab
     * 
     * @return string
     */
    public function getIcon(): string
    {
        return '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>';
    }
    
    /**
     * Get the tab order
     * 
     * @return int
     */
    public function getOrder(): int
    {
        return 2;
    }
    
    /**
     * Get the fields configuration for this tab
     * 
     * @return array
     */
    public function getFields(): array
    {
        return [
            'course_type' => [
                'type' => 'select',
                'label' => __('Course Type', 'sikshya'),
                'options' => [
                    'free' => __('Free Course', 'sikshya'),
                    'paid' => __('Paid Course', 'sikshya'),
                    'subscription' => __('Subscription Only', 'sikshya'),
                ],
                'default' => 'paid',
                'description' => __('Choose how your course will be monetized', 'sikshya'),
            ],
            'price' => [
                'type' => 'number',
                'label' => __('Course Price', 'sikshya'),
                'placeholder' => '99.99',
                'step' => 0.01,
                'min' => 0,
                'description' => __('Regular price of the course', 'sikshya'),
            ],
            'sale_price' => [
                'type' => 'number',
                'label' => __('Discount Price', 'sikshya'),
                'placeholder' => '79.99',
                'step' => 0.01,
                'min' => 0,
                'description' => __('Sale price when discounted', 'sikshya'),
            ],
            'enrollment_status' => [
                'type' => 'select',
                'label' => __('Enrollment Status', 'sikshya'),
                'options' => [
                    'open' => __('Open Enrollment', 'sikshya'),
                    'closed' => __('Closed Enrollment', 'sikshya'),
                    'invite_only' => __('Invite Only', 'sikshya'),
                ],
                'default' => 'open',
                'description' => __('Control who can enroll in the course', 'sikshya'),
            ],
            'max_students' => [
                'type' => 'number',
                'label' => __('Maximum Students', 'sikshya'),
                'placeholder' => '100',
                'min' => 1,
                'description' => __('Leave empty for unlimited enrollment', 'sikshya'),
            ],
            'course_duration' => [
                'type' => 'number',
                'label' => __('Course Duration (Days)', 'sikshya'),
                'placeholder' => '90',
                'min' => 1,
                'description' => __('How long students have access', 'sikshya'),
            ],
            'prerequisites' => [
                'type' => 'textarea',
                'label' => __('Prerequisites', 'sikshya'),
                'placeholder' => __('What should students know before taking this course?', 'sikshya'),
                'description' => __('List any requirements or prior knowledge needed', 'sikshya'),
            ],
            'certificate_enabled' => [
                'type' => 'checkbox',
                'label' => __('Enable Certificate', 'sikshya'),
                'description' => __('Allow students to earn a certificate upon completion', 'sikshya'),
            ],
            'certificate_template' => [
                'type' => 'select',
                'label' => __('Certificate Template', 'sikshya'),
                'options' => [
                    'default' => __('Default Template', 'sikshya'),
                    'modern' => __('Modern Template', 'sikshya'),
                    'classic' => __('Classic Template', 'sikshya'),
                ],
                'default' => 'default',
                'description' => __('Choose the certificate design', 'sikshya'),
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
        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Pricing', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Set up pricing and access controls for your course', 'sikshya'); ?></p>
                </div>
            </div>
            
            <?php echo $this->renderField('course_type', $this->getFields()['course_type'], $data['course_type'] ?? ''); ?>

            <div class="sikshya-form-grid" id="pricing-fields">
                <?php echo $this->renderField('price', $this->getFields()['price'], $data['price'] ?? ''); ?>
                <?php echo $this->renderField('sale_price', $this->getFields()['sale_price'], $data['sale_price'] ?? ''); ?>
            </div>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Access & Enrollment', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Control who can enroll and how long they have access', 'sikshya'); ?></p>
                </div>
            </div>
            
            <?php echo $this->renderField('enrollment_status', $this->getFields()['enrollment_status'], $data['enrollment_status'] ?? ''); ?>
            
            <div class="sikshya-form-grid">
                <?php echo $this->renderField('max_students', $this->getFields()['max_students'], $data['max_students'] ?? ''); ?>
                <?php echo $this->renderField('course_duration', $this->getFields()['course_duration'], $data['course_duration'] ?? ''); ?>
            </div>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Prerequisites', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('What should students know before taking this course?', 'sikshya'); ?></p>
                </div>
            </div>
            
            <?php echo $this->renderField('prerequisites', $this->getFields()['prerequisites'], $data['prerequisites'] ?? ''); ?>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Certificates', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Configure certificate settings for course completion', 'sikshya'); ?></p>
                </div>
            </div>
            
            <?php echo $this->renderField('certificate_enabled', $this->getFields()['certificate_enabled'], $data['certificate_enabled'] ?? ''); ?>
            <?php echo $this->renderField('certificate_template', $this->getFields()['certificate_template'], $data['certificate_template'] ?? ''); ?>
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
        
        // Add conditional logic for pricing fields
        if ($field_id === 'course_type') {
            $field_html = str_replace(
                '<select name="course_type"',
                '<select name="course_type" onchange="togglePricing(this)"',
                $field_html
            );
        }
        
        // Add conditional logic for certificate template
        if ($field_id === 'certificate_template') {
            $field_html = str_replace(
                '<div class="sikshya-form-row">',
                '<div class="sikshya-form-row" id="certificate-template-field" style="display: none;">',
                $field_html
            );
        }
        
        return $field_html;
    }
}
