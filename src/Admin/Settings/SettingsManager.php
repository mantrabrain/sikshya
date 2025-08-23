<?php

namespace Sikshya\Admin\Settings;

use Sikshya\Core\Plugin;

/**
 * Settings Manager Class
 * 
 * @package Sikshya\Admin\Settings
 * @since 1.0.0
 */
class SettingsManager
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    protected Plugin $plugin;

    /**
     * Settings arrays for each tab
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->initSettings();
    }

    /**
     * Initialize all settings arrays
     */
    protected function initSettings(): void
    {
        // Don't initialize settings arrays here to avoid translation loading too early
        // They will be initialized when first accessed
        $this->settings = [];
    }

    /**
     * Get all settings (lazy loading)
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        if (empty($this->settings)) {
            $this->settings = [
                'general' => $this->getGeneralSettings(),
                'courses' => $this->getCoursesSettings(),
                'enrollment' => $this->getEnrollmentSettings(),
                'payment' => $this->getPaymentSettings(),
                'certificates' => $this->getCertificatesSettings(),
                'email' => $this->getEmailSettings(),
                'instructors' => $this->getInstructorsSettings(),
                'students' => $this->getStudentsSettings(),
                'quizzes' => $this->getQuizzesSettings(),
                'assignments' => $this->getAssignmentsSettings(),
                'progress' => $this->getProgressSettings(),
                'notifications' => $this->getNotificationsSettings(),
                'integrations' => $this->getIntegrationsSettings(),
                'security' => $this->getSecuritySettings(),
                'advanced' => $this->getAdvancedSettings(),
            ];
        }
        return $this->settings;
    }



    /**
     * Get settings for a specific tab
     *
     * @param string $tab
     * @return array
     */
    public function getTabSettings(string $tab): array
    {
        $all_settings = $this->getAllSettings();
        return $all_settings[$tab] ?? [];
    }

    /**
     * Get setting value with _sikshya_ prefix
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = '')
    {
        return get_option('_sikshya_' . $key, $default);
    }

    /**
     * Save setting with _sikshya_ prefix
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function saveSetting(string $key, $value): bool
    {
        return update_option('_sikshya_' . $key, $value);
    }

    /**
     * Save multiple settings
     *
     * @param array $settings
     * @return bool
     */
    public function saveSettings(array $settings): bool
    {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->saveSetting($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Render settings form for a tab
     *
     * @param string $tab
     * @return string
     */
    public function renderTabSettings(string $tab): string
    {
        $settings = $this->getTabSettings($tab);
        if (empty($settings)) {
            return '<p>No settings found for this tab.</p>';
        }

        $output = '<div class="sikshya-settings-tab-content">';
        
        foreach ($settings as $section) {
            $output .= $this->renderSection($section);
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render a settings section
     *
     * @param array $section
     * @return string
     */
    protected function renderSection(array $section): string
    {
        $output = '<div class="sikshya-settings-section">';
        
        if (!empty($section['title'])) {
            $icon = $section['icon'] ?? 'fas fa-cog';
            $output .= '<h3 class="sikshya-settings-section-title">';
            $output .= '<i class="' . esc_attr($icon) . '"></i>';
            $output .= esc_html($section['title']);
            $output .= '</h3>';
        }
        
        if (!empty($section['fields'])) {
            $output .= '<div class="sikshya-settings-grid">';
            foreach ($section['fields'] as $field) {
                $output .= $this->renderField($field);
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render a settings field
     *
     * @param array $field
     * @return string
     */
    protected function renderField(array $field): string
    {
        $key = $field['key'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $description = $field['description'] ?? '';
        $default = $field['default'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $options = $field['options'] ?? [];
        $required = $field['required'] ?? false;
        
        $current_value = $this->getSetting($key, $default);
        
        $output = '<div class="sikshya-settings-field">';
        
        // Label
        if (!empty($label)) {
            $required_mark = $required ? ' <span class="required">*</span>' : '';
            $output .= '<label for="' . esc_attr($key) . '">' . esc_html($label) . $required_mark . '</label>';
        }
        
        // Field input
        switch ($type) {
            case 'textarea':
                $output .= '<textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="3"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                if ($required) {
                    $output .= ' required';
                }
                $output .= '>' . esc_textarea($current_value) . '</textarea>';
                break;
                
            case 'select':
                $output .= '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                if ($required) {
                    $output .= ' required';
                }
                $output .= '>';
                foreach ($options as $option_value => $option_label) {
                    $selected = selected($current_value, $option_value, false);
                    $output .= '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
                }
                $output .= '</select>';
                break;
                
            case 'checkbox':
                $checked = checked($current_value, '1', false);
                $output .= '<input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1"' . $checked . '>';
                break;
                
            case 'radio':
                foreach ($options as $option_value => $option_label) {
                    $checked = checked($current_value, $option_value, false);
                    $output .= '<label class="radio-label">';
                    $output .= '<input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($option_value) . '"' . $checked . '>';
                    $output .= '<span>' . esc_html($option_label) . '</span>';
                    $output .= '</label>';
                }
                break;
                
            case 'number':
                $output .= '<input type="number" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                if (isset($field['min'])) {
                    $output .= ' min="' . esc_attr($field['min']) . '"';
                }
                if (isset($field['max'])) {
                    $output .= ' max="' . esc_attr($field['max']) . '"';
                }
                if ($required) {
                    $output .= ' required';
                }
                $output .= '>';
                break;
                
            default: // text
                $output .= '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                if ($required) {
                    $output .= ' required';
                }
                $output .= '>';
                break;
        }
        
        // Description
        if (!empty($description)) {
            $output .= '<p class="description">' . esc_html($description) . '</p>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get General Settings
     *
     * @return array
     */
    protected function getGeneralSettings(): array
    {
        return [
            [
                'title' => __('Basic Information', 'sikshya'),
                'icon' => 'fas fa-info-circle',
                'fields' => [
                    [
                        'key' => 'site_title',
                        'type' => 'text',
                        'label' => __('LMS Site Title', 'sikshya'),
                        'description' => __('The title of your learning management system', 'sikshya'),
                        'placeholder' => __('Enter your LMS site title', 'sikshya'),
                        'default' => get_bloginfo('name'),
                        'required' => true
                    ],
                    [
                        'key' => 'site_description',
                        'type' => 'textarea',
                        'label' => __('LMS Description', 'sikshya'),
                        'description' => __('A brief description of your learning platform', 'sikshya'),
                        'placeholder' => __('Enter a description for your LMS', 'sikshya'),
                        'default' => get_bloginfo('description')
                    ]
                ]
            ],
            [
                'title' => __('Currency & Pricing', 'sikshya'),
                'icon' => 'fas fa-dollar-sign',
                'fields' => [
                    [
                        'key' => 'currency',
                        'type' => 'select',
                        'label' => __('Default Currency', 'sikshya'),
                        'description' => __('Default currency for course pricing and payments', 'sikshya'),
                        'default' => 'USD',
                        'options' => [
                            'USD' => __('US Dollar ($)', 'sikshya'),
                            'EUR' => __('Euro (€)', 'sikshya'),
                            'GBP' => __('British Pound (£)', 'sikshya'),
                            'CAD' => __('Canadian Dollar (C$)', 'sikshya'),
                            'AUD' => __('Australian Dollar (A$)', 'sikshya'),
                            'JPY' => __('Japanese Yen (¥)', 'sikshya'),
                            'INR' => __('Indian Rupee (₹)', 'sikshya'),
                            'BRL' => __('Brazilian Real (R$)', 'sikshya'),
                            'MXN' => __('Mexican Peso ($)', 'sikshya'),
                            'SGD' => __('Singapore Dollar (S$)', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'currency_position',
                        'type' => 'select',
                        'label' => __('Currency Position', 'sikshya'),
                        'description' => __('Position of currency symbol relative to the amount', 'sikshya'),
                        'default' => 'left',
                        'options' => [
                            'left' => __('Left ($100)', 'sikshya'),
                            'right' => __('Right (100$)', 'sikshya'),
                            'left_space' => __('Left with space ($ 100)', 'sikshya'),
                            'right_space' => __('Right with space (100 $)', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Date & Time', 'sikshya'),
                'icon' => 'fas fa-clock',
                'fields' => [
                    [
                        'key' => 'timezone',
                        'type' => 'select',
                        'label' => __('Timezone', 'sikshya'),
                        'description' => __('Timezone for displaying dates and times', 'sikshya'),
                        'default' => get_option('timezone_string'),
                        'options' => $this->getTimezoneOptions()
                    ],
                    [
                        'key' => 'date_format',
                        'type' => 'select',
                        'label' => __('Date Format', 'sikshya'),
                        'description' => __('Format for displaying dates', 'sikshya'),
                        'default' => 'F j, Y',
                        'options' => [
                            'F j, Y' => date('F j, Y'),
                            'Y-m-d' => date('Y-m-d'),
                            'm/d/Y' => date('m/d/Y'),
                            'd/m/Y' => date('d/m/Y'),
                            'j F Y' => date('j F Y')
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Courses Settings
     *
     * @return array
     */
    protected function getCoursesSettings(): array
    {
        return [
            [
                'title' => __('Course Display', 'sikshya'),
                'icon' => 'fas fa-eye',
                'fields' => [
                    [
                        'key' => 'courses_per_page',
                        'type' => 'number',
                        'label' => __('Courses per Page', 'sikshya'),
                        'description' => __('Number of courses to display per page', 'sikshya'),
                        'default' => 12,
                        'min' => 1,
                        'max' => 50
                    ],
                    [
                        'key' => 'show_course_rating',
                        'type' => 'checkbox',
                        'label' => __('Show Course Ratings', 'sikshya'),
                        'description' => __('Display course ratings and reviews', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'show_course_duration',
                        'type' => 'checkbox',
                        'label' => __('Show Course Duration', 'sikshya'),
                        'description' => __('Display total course duration', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ],
            [
                'title' => __('Course Features', 'sikshya'),
                'icon' => 'fas fa-star',
                'fields' => [
                    [
                        'key' => 'enable_course_preview',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Preview', 'sikshya'),
                        'description' => __('Allow students to preview course content before enrollment', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'enable_course_progress',
                        'type' => 'checkbox',
                        'label' => __('Enable Progress Tracking', 'sikshya'),
                        'description' => __('Track student progress through courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'enable_course_certificates',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Certificates', 'sikshya'),
                        'description' => __('Issue certificates upon course completion', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Enrollment Settings
     *
     * @return array
     */
    protected function getEnrollmentSettings(): array
    {
        return [
            [
                'title' => __('Enrollment Options', 'sikshya'),
                'icon' => 'fas fa-user-plus',
                'fields' => [
                    [
                        'key' => 'auto_enroll_free_courses',
                        'type' => 'checkbox',
                        'label' => __('Auto-enroll Free Courses', 'sikshya'),
                        'description' => __('Automatically enroll students in free courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'require_login_for_preview',
                        'type' => 'checkbox',
                        'label' => __('Require Login for Preview', 'sikshya'),
                        'description' => __('Require users to be logged in to preview courses', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'max_enrollments_per_user',
                        'type' => 'number',
                        'label' => __('Max Enrollments per User', 'sikshya'),
                        'description' => __('Maximum number of courses a user can enroll in (0 for unlimited)', 'sikshya'),
                        'default' => 0,
                        'min' => 0
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Payment Settings
     *
     * @return array
     */
    protected function getPaymentSettings(): array
    {
        return [
            [
                'title' => __('Payment Gateways', 'sikshya'),
                'icon' => 'fas fa-credit-card',
                'fields' => [
                    [
                        'key' => 'enable_paypal',
                        'type' => 'checkbox',
                        'label' => __('Enable PayPal', 'sikshya'),
                        'description' => __('Accept payments via PayPal', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'enable_stripe',
                        'type' => 'checkbox',
                        'label' => __('Enable Stripe', 'sikshya'),
                        'description' => __('Accept payments via Stripe', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'enable_bank_transfer',
                        'type' => 'checkbox',
                        'label' => __('Enable Bank Transfer', 'sikshya'),
                        'description' => __('Accept payments via bank transfer', 'sikshya'),
                        'default' => '0'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Certificates Settings
     *
     * @return array
     */
    protected function getCertificatesSettings(): array
    {
        return [
            [
                'title' => __('Certificate Settings', 'sikshya'),
                'icon' => 'fas fa-certificate',
                'fields' => [
                    [
                        'key' => 'certificate_logo',
                        'type' => 'text',
                        'label' => __('Certificate Logo URL', 'sikshya'),
                        'description' => __('URL to the logo to display on certificates', 'sikshya'),
                        'placeholder' => __('https://example.com/logo.png', 'sikshya')
                    ],
                    [
                        'key' => 'certificate_signature',
                        'type' => 'text',
                        'label' => __('Certificate Signature', 'sikshya'),
                        'description' => __('Name or title to appear as signature on certificates', 'sikshya'),
                        'placeholder' => __('Course Instructor', 'sikshya')
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Email Settings
     *
     * @return array
     */
    protected function getEmailSettings(): array
    {
        return [
            [
                'title' => __('Email Notifications', 'sikshya'),
                'icon' => 'fas fa-envelope',
                'fields' => [
                    [
                        'key' => 'enable_enrollment_emails',
                        'type' => 'checkbox',
                        'label' => __('Enrollment Emails', 'sikshya'),
                        'description' => __('Send emails when students enroll in courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'enable_completion_emails',
                        'type' => 'checkbox',
                        'label' => __('Completion Emails', 'sikshya'),
                        'description' => __('Send emails when students complete courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'from_email',
                        'type' => 'text',
                        'label' => __('From Email', 'sikshya'),
                        'description' => __('Email address to send notifications from', 'sikshya'),
                        'default' => get_option('admin_email'),
                        'required' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Instructors Settings
     *
     * @return array
     */
    protected function getInstructorsSettings(): array
    {
        return [
            [
                'title' => __('Instructor Permissions', 'sikshya'),
                'icon' => 'fas fa-chalkboard-teacher',
                'fields' => [
                    [
                        'key' => 'instructors_can_create_courses',
                        'type' => 'checkbox',
                        'label' => __('Can Create Courses', 'sikshya'),
                        'description' => __('Allow instructors to create new courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'instructors_can_edit_courses',
                        'type' => 'checkbox',
                        'label' => __('Can Edit Courses', 'sikshya'),
                        'description' => __('Allow instructors to edit their courses', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'instructors_can_delete_courses',
                        'type' => 'checkbox',
                        'label' => __('Can Delete Courses', 'sikshya'),
                        'description' => __('Allow instructors to delete their courses', 'sikshya'),
                        'default' => '0'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Students Settings
     *
     * @return array
     */
    protected function getStudentsSettings(): array
    {
        return [
            [
                'title' => __('Student Features', 'sikshya'),
                'icon' => 'fas fa-users',
                'fields' => [
                    [
                        'key' => 'students_can_see_progress',
                        'type' => 'checkbox',
                        'label' => __('Show Progress to Students', 'sikshya'),
                        'description' => __('Allow students to see their course progress', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'students_can_download_certificates',
                        'type' => 'checkbox',
                        'label' => __('Download Certificates', 'sikshya'),
                        'description' => __('Allow students to download their certificates', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Quizzes Settings
     *
     * @return array
     */
    protected function getQuizzesSettings(): array
    {
        return [
            [
                'title' => __('Quiz Settings', 'sikshya'),
                'icon' => 'fas fa-question-circle',
                'fields' => [
                    [
                        'key' => 'quiz_time_limit',
                        'type' => 'number',
                        'label' => __('Default Time Limit (minutes)', 'sikshya'),
                        'description' => __('Default time limit for quizzes in minutes (0 for no limit)', 'sikshya'),
                        'default' => 30,
                        'min' => 0
                    ],
                    [
                        'key' => 'quiz_attempts_limit',
                        'type' => 'number',
                        'label' => __('Default Attempts Limit', 'sikshya'),
                        'description' => __('Default number of attempts allowed for quizzes (0 for unlimited)', 'sikshya'),
                        'default' => 3,
                        'min' => 0
                    ],
                    [
                        'key' => 'show_quiz_results',
                        'type' => 'checkbox',
                        'label' => __('Show Quiz Results', 'sikshya'),
                        'description' => __('Show quiz results to students after completion', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Assignments Settings
     *
     * @return array
     */
    protected function getAssignmentsSettings(): array
    {
        return [
            [
                'title' => __('Assignment Settings', 'sikshya'),
                'icon' => 'fas fa-tasks',
                'fields' => [
                    [
                        'key' => 'assignment_file_types',
                        'type' => 'text',
                        'label' => __('Allowed File Types', 'sikshya'),
                        'description' => __('Comma-separated list of allowed file extensions (e.g., pdf,doc,docx)', 'sikshya'),
                        'default' => 'pdf,doc,docx,txt,jpg,jpeg,png',
                        'placeholder' => __('pdf,doc,docx,txt,jpg,jpeg,png', 'sikshya')
                    ],
                    [
                        'key' => 'max_file_size',
                        'type' => 'number',
                        'label' => __('Max File Size (MB)', 'sikshya'),
                        'description' => __('Maximum file size for assignment submissions in MB', 'sikshya'),
                        'default' => 10,
                        'min' => 1,
                        'max' => 100
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Progress Settings
     *
     * @return array
     */
    protected function getProgressSettings(): array
    {
        return [
            [
                'title' => __('Progress Tracking', 'sikshya'),
                'icon' => 'fas fa-chart-line',
                'fields' => [
                    [
                        'key' => 'track_lesson_progress',
                        'type' => 'checkbox',
                        'label' => __('Track Lesson Progress', 'sikshya'),
                        'description' => __('Track individual lesson completion', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'track_quiz_progress',
                        'type' => 'checkbox',
                        'label' => __('Track Quiz Progress', 'sikshya'),
                        'description' => __('Track quiz completion and scores', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'track_assignment_progress',
                        'type' => 'checkbox',
                        'label' => __('Track Assignment Progress', 'sikshya'),
                        'description' => __('Track assignment submissions and grades', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Notifications Settings
     *
     * @return array
     */
    protected function getNotificationsSettings(): array
    {
        return [
            [
                'title' => __('Notification Settings', 'sikshya'),
                'icon' => 'fas fa-bell',
                'fields' => [
                    [
                        'key' => 'enable_browser_notifications',
                        'type' => 'checkbox',
                        'label' => __('Browser Notifications', 'sikshya'),
                        'description' => __('Enable browser push notifications', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'enable_email_notifications',
                        'type' => 'checkbox',
                        'label' => __('Email Notifications', 'sikshya'),
                        'description' => __('Enable email notifications', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Integrations Settings
     *
     * @return array
     */
    protected function getIntegrationsSettings(): array
    {
        return [
            [
                'title' => __('Third-party Integrations', 'sikshya'),
                'icon' => 'fas fa-plug',
                'fields' => [
                    [
                        'key' => 'google_analytics_id',
                        'type' => 'text',
                        'label' => __('Google Analytics ID', 'sikshya'),
                        'description' => __('Google Analytics tracking ID (e.g., GA_MEASUREMENT_ID)', 'sikshya'),
                        'placeholder' => __('G-XXXXXXXXXX', 'sikshya')
                    ],
                    [
                        'key' => 'facebook_pixel_id',
                        'type' => 'text',
                        'label' => __('Facebook Pixel ID', 'sikshya'),
                        'description' => __('Facebook Pixel tracking ID', 'sikshya'),
                        'placeholder' => __('XXXXXXXXXX', 'sikshya')
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Security Settings
     *
     * @return array
     */
    protected function getSecuritySettings(): array
    {
        return [
            [
                'title' => __('Security Options', 'sikshya'),
                'icon' => 'fas fa-shield-alt',
                'fields' => [
                    [
                        'key' => 'enable_captcha',
                        'type' => 'checkbox',
                        'label' => __('Enable CAPTCHA', 'sikshya'),
                        'description' => __('Enable CAPTCHA for forms', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'session_timeout',
                        'type' => 'number',
                        'label' => __('Session Timeout (minutes)', 'sikshya'),
                        'description' => __('User session timeout in minutes', 'sikshya'),
                        'default' => 120,
                        'min' => 15,
                        'max' => 1440
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Advanced Settings
     *
     * @return array
     */
    protected function getAdvancedSettings(): array
    {
        return [
            [
                'title' => __('Advanced Options', 'sikshya'),
                'icon' => 'fas fa-tools',
                'fields' => [
                    [
                        'key' => 'enable_debug_mode',
                        'type' => 'checkbox',
                        'label' => __('Debug Mode', 'sikshya'),
                        'description' => __('Enable debug mode for development', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'cache_enabled',
                        'type' => 'checkbox',
                        'label' => __('Enable Caching', 'sikshya'),
                        'description' => __('Enable caching for better performance', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'cache_duration',
                        'type' => 'number',
                        'label' => __('Cache Duration (hours)', 'sikshya'),
                        'description' => __('How long to cache data in hours', 'sikshya'),
                        'default' => 24,
                        'min' => 1,
                        'max' => 168
                    ]
                ]
            ]
        ];
    }

    /**
     * Get timezone options
     *
     * @return array
     */
    protected function getTimezoneOptions(): array
    {
        $timezones = \DateTimeZone::listIdentifiers();
        $options = [];
        
        foreach ($timezones as $timezone) {
            $options[$timezone] = $timezone;
        }
        
        return $options;
    }
}
