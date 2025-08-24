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
            ],
            'price' => [
                'type' => 'number',
                'label' => __('Course Price', 'sikshya'),
                'placeholder' => '99.99',
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'sale_price' => [
                'type' => 'number',
                'label' => __('Discount Price', 'sikshya'),
                'placeholder' => '79.99',
                'step' => '0.01',
                'min' => '0',
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
            ],
            'max_students' => [
                'type' => 'number',
                'label' => __('Maximum Students', 'sikshya'),
                'placeholder' => '100',
                'min' => '1',
            ],
            'course_duration' => [
                'type' => 'number',
                'label' => __('Course Duration (Days)', 'sikshya'),
                'placeholder' => '90',
                'min' => '1',
            ],
            'prerequisites' => [
                'type' => 'repeater',
                'label' => __('Prerequisites', 'sikshya'),
                'placeholder' => __('Basic knowledge of...', 'sikshya'),
            ],
            'requirements' => [
                'type' => 'repeater',
                'label' => __('Course Requirements', 'sikshya'),
                'placeholder' => __('Computer with internet access', 'sikshya'),
            ],
            'enable_drip' => [
                'type' => 'checkbox',
                'label' => __('Enable Drip Content', 'sikshya'),
            ],
            'drip_type' => [
                'type' => 'select',
                'label' => __('Drip Type', 'sikshya'),
                'options' => [
                    'interval' => __('Time Interval', 'sikshya'),
                    'completion' => __('After Completion', 'sikshya'),
                    'specific_date' => __('Specific Dates', 'sikshya'),
                ],
                'default' => 'interval',
            ],
            'drip_interval' => [
                'type' => 'number',
                'label' => __('Drip Interval (Days)', 'sikshya'),
                'placeholder' => '7',
                'min' => '1',
            ],
            'enable_certificate' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Completion Certificate', 'sikshya'),
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
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Type', 'sikshya'); ?></label>
                <select name="course_type" onchange="togglePricing(this)">
                    <option value="free" <?php selected($data['course_type'] ?? '', 'free'); ?>><?php _e('Free Course', 'sikshya'); ?></option>
                    <option value="paid" <?php selected($data['course_type'] ?? 'paid', 'paid'); ?>><?php _e('Paid Course', 'sikshya'); ?></option>
                    <option value="subscription" <?php selected($data['course_type'] ?? '', 'subscription'); ?>><?php _e('Subscription Only', 'sikshya'); ?></option>
                </select>
            </div>

            <div class="sikshya-form-grid" id="pricing-fields">
                <div class="sikshya-form-row">
                    <label><?php _e('Course Price', 'sikshya'); ?> *</label>
                    <input type="number" name="price" value="<?php echo esc_attr($data['price'] ?? ''); ?>" placeholder="99.99" step="0.01" min="0">
                </div>

                <div class="sikshya-form-row">
                    <label><?php _e('Discount Price', 'sikshya'); ?></label>
                    <input type="number" name="sale_price" value="<?php echo esc_attr($data['sale_price'] ?? ''); ?>" placeholder="79.99" step="0.01" min="0">
                </div>
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
            
            <div class="sikshya-form-row">
                <label><?php _e('Enrollment Status', 'sikshya'); ?></label>
                <select name="enrollment_status">
                    <option value="open" <?php selected($data['enrollment_status'] ?? 'open', 'open'); ?>><?php _e('Open Enrollment', 'sikshya'); ?></option>
                    <option value="closed" <?php selected($data['enrollment_status'] ?? '', 'closed'); ?>><?php _e('Closed Enrollment', 'sikshya'); ?></option>
                    <option value="invite_only" <?php selected($data['enrollment_status'] ?? '', 'invite_only'); ?>><?php _e('Invite Only', 'sikshya'); ?></option>
                </select>
            </div>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label><?php _e('Maximum Students', 'sikshya'); ?></label>
                    <input type="number" name="max_students" value="<?php echo esc_attr($data['max_students'] ?? ''); ?>" placeholder="100" min="1">
                    <p class="sikshya-help-text"><?php _e('Leave empty for unlimited enrollment', 'sikshya'); ?></p>
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Course Duration (Days)', 'sikshya'); ?></label>
                    <input type="number" name="course_duration" value="<?php echo esc_attr($data['course_duration'] ?? ''); ?>" placeholder="90" min="1">
                    <p class="sikshya-help-text"><?php _e('How long students have access', 'sikshya'); ?></p>
                </div>
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
            
            <div class="sikshya-repeater" id="prerequisites">
                <div class="sikshya-repeater-item">
                    <div class="sikshya-repeater-input">
                        <input type="text" name="prerequisites[]" value="<?php echo esc_attr($data['prerequisites'][0] ?? ''); ?>" placeholder="<?php _e('Basic knowledge of...', 'sikshya'); ?>">
                    </div>
                    <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('prerequisites', 'prerequisites[]', '<?php _e('Basic knowledge of...', 'sikshya'); ?>')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <?php _e('Add Prerequisite', 'sikshya'); ?>
            </button>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Course Requirements', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('What tools or materials will students need?', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-repeater" id="requirements">
                <div class="sikshya-repeater-item">
                    <div class="sikshya-repeater-input">
                        <input type="text" name="requirements[]" value="<?php echo esc_attr($data['requirements'][0] ?? ''); ?>" placeholder="<?php _e('Computer with internet access', 'sikshya'); ?>">
                    </div>
                    <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('requirements', 'requirements[]', '<?php _e('Computer with internet access', 'sikshya'); ?>')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <?php _e('Add Requirement', 'sikshya'); ?>
            </button>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Drip Content', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Release lessons gradually over time', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_drip" <?php checked($data['enable_drip'] ?? '', '1'); ?> onchange="toggleDripContent(this)">
                    <span class="sikshya-checkbox"></span>
                    <?php _e('Enable Drip Content', 'sikshya'); ?>
                </label>
                <p class="sikshya-help-text"><?php _e('Release lessons gradually over time', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-form-grid" id="drip-settings" style="display: none;">
                <div class="sikshya-form-row">
                    <label><?php _e('Drip Type', 'sikshya'); ?></label>
                    <select name="drip_type">
                        <option value="interval" <?php selected($data['drip_type'] ?? 'interval', 'interval'); ?>><?php _e('Time Interval', 'sikshya'); ?></option>
                        <option value="completion" <?php selected($data['drip_type'] ?? '', 'completion'); ?>><?php _e('After Completion', 'sikshya'); ?></option>
                        <option value="specific_date" <?php selected($data['drip_type'] ?? '', 'specific_date'); ?>><?php _e('Specific Dates', 'sikshya'); ?></option>
                    </select>
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Drip Interval (Days)', 'sikshya'); ?></label>
                    <input type="number" name="drip_interval" value="<?php echo esc_attr($data['drip_interval'] ?? ''); ?>" placeholder="7" min="1">
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
}
