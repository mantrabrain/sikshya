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
            'pricing' => [
                'section' => [
                    'title' => __('Pricing', 'sikshya'),
                    'description' => __('Set up pricing and access controls for your course', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>',
                ],
                'fields' => [
            'course_type' => [
                'type' => 'select',
                'label' => __('Course Type', 'sikshya'),
                'options' => [
                    'free' => __('Free Course', 'sikshya'),
                    'paid' => __('Paid Course', 'sikshya'),
                    'subscription' => __('Subscription Only', 'sikshya'),
                ],
                'default' => 'paid',
                        'validation' => 'required',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'three_column',
            ],
            'price' => [
                'type' => 'number',
                'label' => __('Course Price', 'sikshya'),
                'placeholder' => '99.99',
                'step' => '0.01',
                'min' => '0',
                'required' => true,
                        'validation' => 'numeric|min:0',
                        'sanitization' => 'floatval',
                        'layout' => 'three_column',
            ],
            'sale_price' => [
                'type' => 'number',
                'label' => __('Discount Price', 'sikshya'),
                'placeholder' => '79.99',
                'step' => '0.01',
                'min' => '0',
                        'validation' => 'numeric|min:0',
                        'sanitization' => 'floatval',
                        'layout' => 'three_column',
                    ],
                ],
            ],
            'access_enrollment' => [
                'section' => [
                    'title' => __('Access & Enrollment', 'sikshya'),
                    'description' => __('Control who can enroll and how long they have access', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>',
                ],
                'fields' => [
            'enrollment_status' => [
                'type' => 'select',
                'label' => __('Enrollment Status', 'sikshya'),
                'options' => [
                    'open' => __('Open Enrollment', 'sikshya'),
                    'closed' => __('Closed Enrollment', 'sikshya'),
                    'invite_only' => __('Invite Only', 'sikshya'),
                ],
                'default' => 'open',
                        'validation' => 'required',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'three_column',
            ],
            'max_students' => [
                'type' => 'number',
                'label' => __('Maximum Students', 'sikshya'),
                'placeholder' => '100',
                'min' => '1',
                        'description' => __('Leave empty for unlimited enrollment', 'sikshya'),
                        'validation' => 'numeric|min:1',
                        'sanitization' => 'intval',
                        'layout' => 'three_column',
            ],
            'course_duration' => [
                'type' => 'number',
                'label' => __('Course Duration (Days)', 'sikshya'),
                'placeholder' => '90',
                'min' => '1',
                        'description' => __('How long students have access', 'sikshya'),
                        'validation' => 'numeric|min:1',
                        'sanitization' => 'intval',
                        'layout' => 'three_column',
                    ],
                ],
            ],
            'prerequisites' => [
                'section' => [
                    'title' => __('Prerequisites', 'sikshya'),
                    'description' => __('What should students know before taking this course?', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>',
                ],
                'fields' => [
            'prerequisites' => [
                'type' => 'repeater',
                'label' => __('Prerequisites', 'sikshya'),
                'placeholder' => __('Basic knowledge of...', 'sikshya'),
                        'add_button_text' => __('Add Prerequisite', 'sikshya'),
                        'description' => __('What should students know before taking this course?', 'sikshya'),
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
            ],
            'requirements' => [
                'type' => 'repeater',
                'label' => __('Course Requirements', 'sikshya'),
                'placeholder' => __('Computer with internet access', 'sikshya'),
                        'add_button_text' => __('Add Requirement', 'sikshya'),
                        'description' => __('What tools or resources do students need?', 'sikshya'),
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
            'advanced_features' => [
                'section' => [
                    'title' => __('Advanced Features', 'sikshya'),
                    'description' => __('Configure advanced course features', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>',
                ],
                'fields' => [
            'enable_drip' => [
                'type' => 'checkbox',
                'label' => __('Enable Drip Content', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
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
                        'validation' => 'in:interval,completion,specific_date',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
            ],
            'drip_interval' => [
                'type' => 'number',
                'label' => __('Drip Interval (Days)', 'sikshya'),
                'placeholder' => '7',
                'min' => '1',
                        'validation' => 'numeric|min:1',
                        'sanitization' => 'intval',
                        'layout' => 'two_column',
            ],
            'enable_certificate' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Completion Certificate', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
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
                        'validation' => 'in:default,modern,classic,custom',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
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
        return $this->renderSections($data);
    }
    
}
