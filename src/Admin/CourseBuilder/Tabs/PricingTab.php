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
        return __('Pricing & access', 'sikshya');
    }

    /**
     * Get the description for this tab
     *
     * @return string
     */
    public function getDescription(): string
    {
        return __('Free or paid, sale price, enrollment dates, how many seats, and optional drip scheduling.', 'sikshya');
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
        $fields = [
            'pricing' => [
                'section' => [
                    'title' => __('Price', 'sikshya'),
                    'description' => __('Currency and tax style come from Sikshya → Settings → Payment.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>',
                ],
                'fields' => [
                'course_type' => [
                'type' => 'select',
                'label' => __('How people pay', 'sikshya'),
                'select_placeholder' => __('Choose one…', 'sikshya'),
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
                'label' => __('Regular price', 'sikshya'),
                'placeholder' => __('e.g. 99.99', 'sikshya'),
                'step' => '0.01',
                'min' => '0',
                        'validation' => 'numeric|min:0',
                        'sanitization' => 'floatval',
                        'layout' => 'three_column',
                    'description' => __('Currency is set globally under Settings → Payment.', 'sikshya'),
                    // Only relevant for paid / subscription courses (free has no price).
                    'depends_on' => 'course_type',
                    'depends_in' => ['paid', 'subscription'],
                ],
                'sale_price' => [
                'type' => 'number',
                'label' => __('Sale price (optional)', 'sikshya'),
                'placeholder' => __('e.g. 79.99', 'sikshya'),
                'step' => '0.01',
                'min' => '0',
                        'validation' => 'numeric|min:0',
                        'sanitization' => 'floatval',
                        'layout' => 'three_column',
                    'description' => __('Optional discounted price. Must be lower than the regular price to show a sale on the course page.', 'sikshya'),
                    'depends_on' => 'course_type',
                    'depends_in' => ['paid', 'subscription'],
                    ],
                'required_plan_id' => [
                    'type' => 'number',
                    'label' => __('Membership plan', 'sikshya'),
                    'description' => __(
                        'For “Subscription only” courses: pick the plan that unlocks access. Sikshya Pro creates an active subscription when checkout is paid.',
                        'sikshya'
                    ),
                    'select_placeholder' => __('Select a plan…', 'sikshya'),
                    'placeholder' => '',
                    'min' => 1,
                    'step' => 1,
                    'widget' => 'subscription_plan_picker',
                    'depends_on' => 'course_type',
                    'depends_value' => 'subscription',
                    'layout' => 'three_column',
                ],
                'bundle_course_ids' => [
                    'type' => 'array',
                    'label' => __('Courses in this bundle', 'sikshya'),
                    'description' => __('Select the courses included in this bundle. Buyers get access to all of them with one purchase.', 'sikshya'),
                    'widget' => 'multi_course_picker',
                    'depends_on' => 'course_type',
                    'depends_value' => 'bundle',
                    'layout' => 'full_width',
                ],
                'bundle_visible_in_listing' => [
                    'type' => 'checkbox',
                    'label' => __('Show in course listing', 'sikshya'),
                    'description' => __('Display this bundle on the public courses page. Uncheck to sell it via a direct link only.', 'sikshya'),
                    'default' => '1',
                    'depends_on' => 'course_type',
                    'depends_value' => 'bundle',
                    'sanitization' => 'boolval',
                ],
                ],
            ],
            'schedule' => [
                'section' => [
                    'title' => __('Schedule', 'sikshya'),
                    'description' => __('Optional: when content goes live, when the cohort ends, and when sign-up opens or closes.', 'sikshya'),
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
                    'title' => __('Access & enrollment', 'sikshya'),
                    'description' => __('Open or close sign-up, cap class size, and set how long access lasts after purchase.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>',
                ],
                'fields' => [
                'enrollment_status' => [
                'type' => 'select',
                'label' => __('Who can sign up?', 'sikshya'),
                'select_placeholder' => __('Choose one…', 'sikshya'),
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
                'placeholder' => __('0 = unlimited', 'sikshya'),
                'min' => '0',
                        'description' => __('Stop new enrollments after this many. Use 0 or leave blank for no cap.', 'sikshya'),
                        'sanitization' => 'intval',
                        'layout' => 'three_column',
                ],
                'course_duration' => [
                'type' => 'number',
                'label' => __('Access length (days)', 'sikshya'),
                'placeholder' => __('e.g. 90', 'sikshya'),
                'min' => '1',
                        'description' => __('How many days enrolled learners can view content after they join.', 'sikshya'),
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
                    'label' => __('How content unlocks', 'sikshya'),
                    'select_placeholder' => __('Choose one…', 'sikshya'),
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

        /**
         * Adjust Pricing & access tab fields (e.g. Pro add-ons removing unavailable modes).
         *
         * @param array<string, mixed> $fields
         * @param PricingTab $tab
         * @return array<string, mixed>
         */
        return apply_filters('sikshya_course_builder_pricing_tab_fields', $fields, $this);
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

        if ($type === 'subscription') {
            $pid = isset($data['required_plan_id']) ? (int) $data['required_plan_id'] : 0;
            if ($pid <= 0) {
                $errors['required_plan_id'] = __(
                    'Choose a membership plan (create plans under Sikshya Pro → Subscriptions).',
                    'sikshya'
                );
            }
        }

        if (in_array($type, ['paid', 'subscription', 'bundle'], true)) {
            $price_raw = $data['price'] ?? '';
            if ($price_raw === '' || $price_raw === null) {
                $msg = $type === 'bundle'
                    ? __('Set a list price for this bundle.', 'sikshya')
                    : __('Course price is required for paid or subscription courses.', 'sikshya');
                $errors['price'] = $msg;
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

        /**
         * Allow extensions to add or adjust validation errors for this tab.
         *
         * @param array<string, string> $errors
         * @param array<string, mixed> $data
         * @param PricingTab $tab
         * @return array<string, string>
         */
        return apply_filters('sikshya_course_builder_pricing_tab_validate_errors', $errors, $data, $this);
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

    /**
     * @param mixed $value
     * @param array<string, mixed> $field_config
     * @return mixed
     */
    protected function sanitizeField(string $field_id, $value, array $field_config)
    {
        if ($field_id === 'required_plan_id') {
            return max(0, (int) $value);
        }

        if ($field_id === 'bundle_course_ids') {
            return self::sanitizeCourseIds($value);
        }

        return parent::sanitizeField($field_id, $value, $field_config);
    }

    /**
     * Normalise a mixed course-ID input (JSON string, PHP array, comma-separated) → sorted int[].
     *
     * @param mixed $value
     * @return int[]
     */
    private static function sanitizeCourseIds($value): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $value))));
        sort($ids);
        return $ids;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, int $course_id): bool
    {
        $ok = parent::save($data, $course_id);
        $type = isset($data['course_type']) ? sanitize_text_field(wp_unslash((string) $data['course_type'])) : 'paid';

        // Clean up type-specific meta when the type changes.
        if ($type !== 'subscription') {
            $meta_repo = $this->postMetaRepository();
            if ($meta_repo) {
                $meta_repo->delete($course_id, '_sikshya_required_plan_id');
            } else {
                delete_post_meta($course_id, '_sikshya_required_plan_id');
            }
        }

        if ($type !== 'bundle') {
            delete_post_meta($course_id, '_sikshya_bundle_course_ids');
            delete_post_meta($course_id, '_sikshya_bundle_visible_in_listing');
        } else {
            // Ensure visibility defaults to '1' when set as bundle for the first time.
            $vis = get_post_meta($course_id, '_sikshya_bundle_visible_in_listing', true);
            if ($vis === '') {
                update_post_meta($course_id, '_sikshya_bundle_visible_in_listing', '1');
            }
        }

        return $ok;
    }
}
