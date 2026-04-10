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
                        'validation' => 'numeric|min:0',
                        'sanitization' => 'floatval',
                        'layout' => 'three_column',
                    'description' => __('Currency is set globally under Settings → Payment.', 'sikshya'),
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
            'schedule' => [
                'section' => [
                    'title' => __('Schedule', 'sikshya'),
                    'description' => __('Optional dates for the course run and enrollment window.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>',
                ],
                'fields' => [
                    'course_start_date' => [
                        'type' => 'date',
                        'label' => __('Course start date', 'sikshya'),
                        'description' => __('When the course content becomes available.', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                    'course_end_date' => [
                        'type' => 'date',
                        'label' => __('Course end date', 'sikshya'),
                        'description' => __('When the course run ends (optional).', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                    'enrollment_start_date' => [
                        'type' => 'date',
                        'label' => __('Enrollment opens', 'sikshya'),
                        'description' => __('First day students can enroll (optional).', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                    'enrollment_end_date' => [
                        'type' => 'date',
                        'label' => __('Enrollment closes', 'sikshya'),
                        'description' => __('Last day students can enroll (optional).', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
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
                'label' => __('Maximum students', 'sikshya'),
                'placeholder' => '',
                'min' => '0',
                        'description' => __('0 or leave empty for unlimited enrollment.', 'sikshya'),
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
                    'label' => __('Enable drip content', 'sikshya'),
                    'description' => __('Release lessons over time instead of all at once.', 'sikshya'),
                    'validation' => 'boolval',
                    'sanitization' => 'boolval',
                ],
                'drip_type' => [
                    'type' => 'select',
                    'label' => __('Drip type', 'sikshya'),
                    'options' => [
                        'interval' => __('Time interval', 'sikshya'),
                        'completion' => __('After previous completion', 'sikshya'),
                        'specific_date' => __('Specific dates', 'sikshya'),
                    ],
                    'default' => 'interval',
                    'depends_on' => 'enable_drip',
                    'validation' => 'in:interval,completion,specific_date',
                    'sanitization' => 'sanitize_text_field',
                    'layout' => 'two_column',
                ],
                'drip_interval' => [
                    'type' => 'number',
                    'label' => __('Drip interval (days)', 'sikshya'),
                    'placeholder' => '7',
                    'min' => '1',
                    'depends_all' => [
                        ['on' => 'enable_drip'],
                        ['on' => 'drip_type', 'value' => 'interval'],
                    ],
                    'validation' => 'numeric|min:1',
                    'sanitization' => 'intval',
                    'layout' => 'two_column',
                ],
                ],
            ],
        ];
    }

    /**
     * Validate pricing fields; price is required only for paid / subscription courses.
     *
     * @param array $data Flat form data.
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        $type = isset($data['course_type']) ? sanitize_text_field(wp_unslash((string) $data['course_type'])) : 'paid';

        if (in_array($type, ['paid', 'subscription'], true)) {
            $price_raw = $data['price'] ?? '';
            if ($price_raw === '' || $price_raw === null) {
                $errors['price'] = __('Course price is required for paid or subscription courses.', 'sikshya');
            } elseif (!is_numeric($price_raw) || floatval($price_raw) < 0) {
                $errors['price'] = __('Please enter a valid price.', 'sikshya');
            }
        } else {
            unset($errors['price']);
        }

        $course_start = $this->parseScheduleDate($data['course_start_date'] ?? '');
        $course_end = $this->parseScheduleDate($data['course_end_date'] ?? '');
        if ($course_start && $course_end && $course_end < $course_start) {
            $errors['course_end_date'] = __('Course end date must be on or after the start date.', 'sikshya');
        }

        $enroll_start = $this->parseScheduleDate($data['enrollment_start_date'] ?? '');
        $enroll_end = $this->parseScheduleDate($data['enrollment_end_date'] ?? '');
        if ($enroll_start && $enroll_end && $enroll_end < $enroll_start) {
            $errors['enrollment_end_date'] = __('Enrollment close date must be on or after the open date.', 'sikshya');
        }

        return $errors;
    }

    /**
     * @param mixed $value
     */
    private function parseScheduleDate($value): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        $t = strtotime((string) $value);

        return $t ? (int) $t : 0;
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
