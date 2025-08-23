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
        $option_name = '_sikshya_' . $key;
        $old_value = get_option($option_name);
        $result = update_option($option_name, $value);
        
        // Debug logging
        error_log('Sikshya SettingsManager - Saving option: ' . $option_name . ' = ' . $value . ' (result: ' . ($result ? 'true' : 'false') . ')');
        
        // Check if the option was actually saved
        $saved_value = get_option($option_name);
        error_log('Sikshya SettingsManager - Retrieved option: ' . $option_name . ' = ' . $saved_value);
        
        // WordPress update_option returns false if value didn't change, but that's not a failure
        // We consider it successful if the value is now what we wanted it to be
        // Handle type differences (string vs int, empty string vs null, etc.)
        $value_normalized = $this->normalizeValue($value);
        $saved_normalized = $this->normalizeValue($saved_value);
        
        error_log('Sikshya SettingsManager - Value comparison: original="' . $value . '" normalized="' . $value_normalized . '", saved="' . $saved_value . '" normalized="' . $saved_normalized . '"');
        
        if ($value_normalized === $saved_normalized) {
            error_log('Sikshya SettingsManager - Option saved successfully (value matches)');
            return true;
        } else {
            error_log('Sikshya SettingsManager - Option save failed (value mismatch: expected "' . $value_normalized . '", got "' . $saved_normalized . '")');
            return false;
        }
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
     * @param array $field_errors
     * @return string
     */
    public function renderTabSettings(string $tab, array $field_errors = []): string
    {
        $settings = $this->getTabSettings($tab);
        if (empty($settings)) {
            return '<p>No settings found for this tab.</p>';
        }

        $output = '<div class="sikshya-settings-tab-content">';
        
        foreach ($settings as $section) {
            $output .= $this->renderSection($section, $field_errors);
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render a settings section
     *
     * @param array $section
     * @param array $field_errors
     * @return string
     */
    protected function renderSection(array $section, array $field_errors = []): string
    {
        $output = '<div class="sikshya-settings-section">' . "\n";
        
        if (!empty($section['title'])) {
            $icon = $section['icon'] ?? 'fas fa-cog';
            $output .= '        <h3 class="sikshya-settings-section-title">' . "\n";
            $output .= '            <i class="' . esc_attr($icon) . '"></i>' . "\n";
            $output .= '            ' . esc_html($section['title']) . '        ' . "\n";
            $output .= '        </h3>' . "\n";
        }
        
        if (!empty($section['fields'])) {
            $output .= '        ' . "\n";
            $output .= '        <div class="sikshya-settings-grid">' . "\n";
            $field_count = count($section['fields']);
            foreach ($section['fields'] as $index => $field) {
                $field_error = $field_errors[$field['key'] ?? ''] ?? '';
                $output .= $this->renderField($field, $field_error);
                // Add empty line between fields (except after the last field)
                if ($index < $field_count - 1) {
                    $output .= '            ' . "\n";
                }
            }
            $output .= '        </div>' . "\n";
        }
        
        $output .= '    </div>';
        
        return $output;
    }

    /**
     * Render a settings field
     *
     * @param array $field
     * @param string $error_message
     * @return string
     */
    protected function renderField(array $field, string $error_message = ''): string
    {
        $key = $field['key'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $description = $field['description'] ?? '';
        $default = $field['default'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $options = $field['options'] ?? [];
        
        $current_value = $this->getSetting($key, $default);
        
        $field_class = 'sikshya-settings-field';
        if (!empty($error_message)) {
            $field_class .= ' has-error';
        }
        
        $output = '            <div class="' . $field_class . '">' . "\n";
        
        // Label (skip for checkbox as it's handled in the checkbox case)
        if (!empty($label) && $type !== 'checkbox') {
            $output .= '                <label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>' . "\n";
        }
        
        // Field input
        switch ($type) {
            case 'textarea':
                $output .= '                <textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="3"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . esc_textarea($current_value) . '</textarea>' . "\n";
                break;
                
            case 'select':
                $output .= '                <select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . "\n";
                foreach ($options as $option_value => $option_label) {
                    $selected = selected($current_value, $option_value, false);
                    $output .= '                    <option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>' . "\n";
                }
                $output .= '                </select>' . "\n";
                break;
                
            case 'checkbox':
                $checked = checked($current_value, '1', false);
                $output .= '                <div class="sikshya-checkbox-wrapper">' . "\n";
                $output .= '                    <input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1"' . $checked . '>' . "\n";
                if (!empty($label)) {
                    $output .= '                    <label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>' . "\n";
                }
                $output .= '                </div>' . "\n";
                break;
                
            case 'radio':
                foreach ($options as $option_value => $option_label) {
                    $checked = checked($current_value, $option_value, false);
                    $output .= '                <label class="radio-label">' . "\n";
                    $output .= '                    <input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($option_value) . '"' . $checked . '>' . "\n";
                    $output .= '                    <span>' . esc_html($option_label) . '</span>' . "\n";
                    $output .= '                </label>' . "\n";
                }
                break;
                
            case 'number':
                $output .= '                <input type="number" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
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
                $output .= '>' . "\n";
                break;
                
            default: // text
                $output .= '                <input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . "\n";
                break;
        }
        
        // Description
        if (!empty($description)) {
            $output .= '                <p class="description">' . esc_html($description) . '</p>' . "\n";
        }
        
        // Error message
        if (!empty($error_message)) {
            $output .= '                <div class="sikshya-field-error">' . "\n";
            $output .= '                    <i class="fas fa-exclamation-triangle"></i>' . "\n";
            $output .= '                    <span>' . esc_html($error_message) . '</span>' . "\n";
            $output .= '                </div>' . "\n";
        }
        
        $output .= '            </div>' . "\n";
        
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
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return !empty(trim($value)) ? true : __('Site title cannot be empty.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'site_description',
                        'type' => 'textarea',
                        'label' => __('LMS Description', 'sikshya'),
                        'description' => __('A brief description of your learning platform', 'sikshya'),
                        'placeholder' => __('Enter a description for your LMS', 'sikshya'),
                        'default' => get_bloginfo('description'),
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => function($value) {
                            return strlen($value) <= 500 ? true : __('Description cannot exceed 500 characters.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'admin_email',
                        'type' => 'text',
                        'label' => __('Admin Email', 'sikshya'),
                        'description' => __('Primary email address for system notifications', 'sikshya'),
                        'placeholder' => __('admin@example.com', 'sikshya'),
                        'default' => get_option('admin_email'),
                        'required' => true,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => function($value) {
                            return is_email($value) ? true : __('Please enter a valid email address.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'max_file_size',
                        'type' => 'number',
                        'label' => __('Maximum File Upload Size (MB)', 'sikshya'),
                        'description' => __('Maximum allowed file size for course materials', 'sikshya'),
                        'placeholder' => __('10', 'sikshya'),
                        'default' => 10,
                        'min' => 1,
                        'max' => 100,
                        'sanitize_callback' => 'intval',
                        'validate_callback' => function($value) {
                            $value = intval($value);
                            if ($value < 1) {
                                return __('File size must be at least 1 MB.', 'sikshya');
                            }
                            if ($value > 100) {
                                return __('File size cannot exceed 100 MB.', 'sikshya');
                            }
                            return true;
                        }
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
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            $valid_currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'INR', 'BRL', 'MXN', 'SGD'];
                            return in_array($value, $valid_currencies) ? true : __('Invalid currency selected.', 'sikshya');
                        }
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
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            $valid_positions = ['left', 'right', 'left_space', 'right_space'];
                            return in_array($value, $valid_positions) ? true : __('Invalid currency position selected.', 'sikshya');
                        }
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
                        'options' => $this->getTimezoneOptions(),
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, \DateTimeZone::listIdentifiers()) ? true : __('Invalid timezone selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'date_format',
                        'type' => 'select',
                        'label' => __('Date Format', 'sikshya'),
                        'description' => __('Format for displaying dates throughout the LMS', 'sikshya'),
                        'default' => get_option('date_format'),
                        'options' => [
                            'F j, Y' => date('F j, Y'),
                            'Y-m-d' => date('Y-m-d'),
                            'm/d/Y' => date('m/d/Y'),
                            'd/m/Y' => date('d/m/Y'),
                            'j F Y' => date('j F Y')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            $valid_formats = ['F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'j F Y'];
                            return in_array($value, $valid_formats) ? true : __('Invalid date format selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'time_format',
                        'type' => 'select',
                        'label' => __('Time Format', 'sikshya'),
                        'description' => __('Format for displaying times throughout the LMS', 'sikshya'),
                        'default' => get_option('time_format'),
                        'options' => [
                            'g:i a' => date('g:i a'),
                            'H:i' => date('H:i')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            $valid_formats = ['g:i a', 'H:i'];
                            return in_array($value, $valid_formats) ? true : __('Invalid time format selected.', 'sikshya');
                        }
                    ]
                ]
            ],
            [
                'title' => __('Language & Localization', 'sikshya'),
                'icon' => 'fas fa-language',
                'fields' => [
                    [
                        'key' => 'language',
                        'type' => 'select',
                        'label' => __('Default Language', 'sikshya'),
                        'description' => __('Default language for the LMS interface', 'sikshya'),
                        'default' => 'en',
                        'options' => [
                            'en' => __('English', 'sikshya'),
                            'es' => __('Spanish', 'sikshya'),
                            'fr' => __('French', 'sikshya'),
                            'de' => __('German', 'sikshya'),
                            'it' => __('Italian', 'sikshya'),
                            'pt' => __('Portuguese', 'sikshya'),
                            'ru' => __('Russian', 'sikshya'),
                            'zh' => __('Chinese', 'sikshya'),
                            'ja' => __('Japanese', 'sikshya'),
                            'ko' => __('Korean', 'sikshya'),
                            'ar' => __('Arabic', 'sikshya'),
                            'hi' => __('Hindi', 'sikshya')
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
                'icon' => 'fas fa-list',
                'fields' => [
                    [
                        'key' => 'courses_per_page',
                        'type' => 'number',
                        'label' => __('Courses per Page', 'sikshya'),
                        'description' => __('Number of courses to display per page in course listings', 'sikshya'),
                        'default' => 12,
                        'min' => 1,
                        'max' => 50
                    ],
                    [
                        'key' => 'course_archive_layout',
                        'type' => 'select',
                        'label' => __('Course Archive Layout', 'sikshya'),
                        'description' => __('Layout style for course archive pages', 'sikshya'),
                        'default' => 'grid',
                        'options' => [
                            'grid' => __('Grid Layout', 'sikshya'),
                            'list' => __('List Layout', 'sikshya'),
                            'masonry' => __('Masonry Layout', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'course_single_layout',
                        'type' => 'select',
                        'label' => __('Single Course Layout', 'sikshya'),
                        'description' => __('Layout style for individual course pages', 'sikshya'),
                        'default' => 'default',
                        'options' => [
                            'default' => __('Default Layout', 'sikshya'),
                            'sidebar' => __('Sidebar Layout', 'sikshya'),
                            'fullwidth' => __('Full Width Layout', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Reviews & Ratings', 'sikshya'),
                'icon' => 'fas fa-star',
                'fields' => [
                    [
                        'key' => 'enable_reviews',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Reviews', 'sikshya'),
                        'description' => __('Allow students to write detailed reviews for courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_ratings',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Ratings', 'sikshya'),
                        'description' => __('Allow students to rate courses with stars', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'review_approval',
                        'type' => 'select',
                        'label' => __('Review Approval', 'sikshya'),
                        'description' => __('Whether reviews need manual approval before being published', 'sikshya'),
                        'default' => 'auto',
                        'options' => [
                            'auto' => __('Auto-approve', 'sikshya'),
                            'manual' => __('Manual approval required', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Categories & Tags', 'sikshya'),
                'icon' => 'fas fa-tags',
                'fields' => [
                    [
                        'key' => 'enable_course_categories',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Categories', 'sikshya'),
                        'description' => __('Allow organizing courses into categories', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_course_tags',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Tags', 'sikshya'),
                        'description' => __('Allow tagging courses for better organization', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'category_display',
                        'type' => 'select',
                        'label' => __('Category Display', 'sikshya'),
                        'description' => __('How to display course categories on the frontend', 'sikshya'),
                        'default' => 'list',
                        'options' => [
                            'list' => __('List View', 'sikshya'),
                            'grid' => __('Grid View', 'sikshya'),
                            'dropdown' => __('Dropdown Menu', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Search & Filters', 'sikshya'),
                'icon' => 'fas fa-search',
                'fields' => [
                    [
                        'key' => 'enable_course_search',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Search', 'sikshya'),
                        'description' => __('Allow users to search through available courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_course_filters',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Filters', 'sikshya'),
                        'description' => __('Allow filtering courses by price, level, duration, etc.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_title',
                        'type' => 'checkbox',
                        'label' => __('Search Course Title', 'sikshya'),
                        'description' => __('Include course title in search results', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_description',
                        'type' => 'checkbox',
                        'label' => __('Search Course Description', 'sikshya'),
                        'description' => __('Include course description in search results', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_instructor',
                        'type' => 'checkbox',
                        'label' => __('Search Instructor Name', 'sikshya'),
                        'description' => __('Include instructor name in search results', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_categories',
                        'type' => 'checkbox',
                        'label' => __('Search Categories & Tags', 'sikshya'),
                        'description' => __('Include categories and tags in search results', 'sikshya'),
                        'default' => true
                    ]
                ]
            ],
            [
                'title' => __('Enrollment Settings', 'sikshya'),
                'icon' => 'fas fa-user-plus',
                'fields' => [
                    [
                        'key' => 'auto_enroll',
                        'type' => 'checkbox',
                        'label' => __('Auto-enroll on Purchase', 'sikshya'),
                        'description' => __('Automatically enroll students when they purchase a course', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enrollment_button_text',
                        'type' => 'text',
                        'label' => __('Enrollment Button Text', 'sikshya'),
                        'description' => __('Text to display on course enrollment buttons', 'sikshya'),
                        'default' => 'Enroll Now'
                    ],
                    [
                        'key' => 'free_course_text',
                        'type' => 'text',
                        'label' => __('Free Course Button Text', 'sikshya'),
                        'description' => __('Text to display on free course enrollment buttons', 'sikshya'),
                        'default' => 'Start Learning'
                    ]
                ]
            ],
            [
                'title' => __('Advanced Course Settings', 'sikshya'),
                'icon' => 'fas fa-cog',
                'fields' => [
                    [
                        'key' => 'course_completion_criteria',
                        'type' => 'select',
                        'label' => __('Completion Criteria', 'sikshya'),
                        'description' => __('Criteria for marking a course as completed', 'sikshya'),
                        'default' => 'all_lessons',
                        'options' => [
                            'all_lessons' => __('All Lessons Completed', 'sikshya'),
                            'all_lessons_quizzes' => __('All Lessons + Quizzes', 'sikshya'),
                            'percentage' => __('Percentage Based', 'sikshya'),
                            'manual' => __('Manual Completion', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'completion_percentage',
                        'type' => 'number',
                        'label' => __('Completion Percentage (%)', 'sikshya'),
                        'description' => __('Percentage of course content that must be completed', 'sikshya'),
                        'default' => 80,
                        'min' => 1,
                        'max' => 100
                    ],
                    [
                        'key' => 'enable_course_preview',
                        'type' => 'checkbox',
                        'label' => __('Enable Course Preview', 'sikshya'),
                        'description' => __('Allow non-enrolled users to preview course content', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'preview_lessons_count',
                        'type' => 'number',
                        'label' => __('Preview Lessons Count', 'sikshya'),
                        'description' => __('Number of lessons available for preview (0 = disabled)', 'sikshya'),
                        'default' => 3,
                        'min' => 0,
                        'max' => 10
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
                'title' => __('Enrollment Access', 'sikshya'),
                'icon' => 'fas fa-user-plus',
                'fields' => [
                    [
                        'key' => 'allow_guest_enrollment',
                        'type' => 'checkbox',
                        'label' => __('Allow Guest Enrollment', 'sikshya'),
                        'description' => __('Allow guests to enroll in courses without registration', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'require_login',
                        'type' => 'checkbox',
                        'label' => __('Require Login for Course Access', 'sikshya'),
                        'description' => __('Require users to be logged in to access course content', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_waitlist',
                        'type' => 'checkbox',
                        'label' => __('Enable Waitlist', 'sikshya'),
                        'description' => __('Allow students to join waitlist for full courses', 'sikshya'),
                        'default' => false
                    ]
                ]
            ],
            [
                'title' => __('Enrollment Limits', 'sikshya'),
                'icon' => 'fas fa-users',
                'fields' => [
                    [
                        'key' => 'max_students_per_course',
                        'type' => 'number',
                        'label' => __('Max Students per Course', 'sikshya'),
                        'description' => __('Maximum number of students per course (0 = unlimited)', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 10000
                    ],
                    [
                        'key' => 'enrollment_expiry_days',
                        'type' => 'number',
                        'label' => __('Enrollment Expiry (days)', 'sikshya'),
                        'description' => __('Days until enrollment expires (0 = never expires)', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 3650
                    ],
                    [
                        'key' => 'max_courses_per_student',
                        'type' => 'number',
                        'label' => __('Max Courses per Student', 'sikshya'),
                        'description' => __('Maximum courses a student can enroll in (0 = unlimited)', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 1000
                    ]
                ]
            ],
            [
                'title' => __('Unenrollment Settings', 'sikshya'),
                'icon' => 'fas fa-sign-out-alt',
                'fields' => [
                    [
                        'key' => 'allow_unenroll',
                        'type' => 'checkbox',
                        'label' => __('Allow Unenrollment', 'sikshya'),
                        'description' => __('Allow students to unenroll from courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'unenroll_refund',
                        'type' => 'checkbox',
                        'label' => __('Auto Refund on Unenrollment', 'sikshya'),
                        'description' => __('Automatically refund payment when student unenrolls', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'unenroll_deadline_days',
                        'type' => 'number',
                        'label' => __('Unenrollment Deadline (days)', 'sikshya'),
                        'description' => __('Days after enrollment when unenrollment is no longer allowed', 'sikshya'),
                        'default' => 7,
                        'min' => 0,
                        'max' => 365
                    ]
                ]
            ],
            [
                'title' => __('Prerequisites & Restrictions', 'sikshya'),
                'icon' => 'fas fa-lock',
                'fields' => [
                    [
                        'key' => 'enable_prerequisites',
                        'type' => 'checkbox',
                        'label' => __('Enable Prerequisites', 'sikshya'),
                        'description' => __('Allow setting course prerequisites', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'prerequisite_check_type',
                        'type' => 'select',
                        'label' => __('Prerequisite Check Type', 'sikshya'),
                        'description' => __('How to check if prerequisites are met', 'sikshya'),
                        'default' => 'completion',
                        'options' => [
                            'completion' => __('Course Completion', 'sikshya'),
                            'enrollment' => __('Course Enrollment', 'sikshya'),
                            'grade' => __('Minimum Grade', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'minimum_grade_prerequisite',
                        'type' => 'number',
                        'label' => __('Minimum Grade (%)', 'sikshya'),
                        'description' => __('Minimum grade required in prerequisite courses', 'sikshya'),
                        'default' => 70,
                        'min' => 0,
                        'max' => 100
                    ]
                ]
            ],
            [
                'title' => __('Enrollment Periods', 'sikshya'),
                'icon' => 'fas fa-calendar-alt',
                'fields' => [
                    [
                        'key' => 'enable_enrollment_periods',
                        'type' => 'checkbox',
                        'label' => __('Enable Enrollment Periods', 'sikshya'),
                        'description' => __('Restrict enrollment to specific time periods', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'default_enrollment_start',
                        'type' => 'datetime-local',
                        'label' => __('Default Enrollment Start', 'sikshya'),
                        'description' => __('Default start date for course enrollment periods', 'sikshya'),
                        'default' => ''
                    ],
                    [
                        'key' => 'default_enrollment_end',
                        'type' => 'datetime-local',
                        'label' => __('Default Enrollment End', 'sikshya'),
                        'description' => __('Default end date for course enrollment periods', 'sikshya'),
                        'default' => ''
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
                        'key' => 'payment_gateway',
                        'type' => 'select',
                        'label' => __('Primary Payment Gateway', 'sikshya'),
                        'description' => __('Primary payment gateway for processing transactions', 'sikshya'),
                        'default' => '',
                        'options' => [
                            '' => __('Select Gateway', 'sikshya'),
                            'stripe' => __('Stripe', 'sikshya'),
                            'paypal' => __('PayPal', 'sikshya'),
                            'razorpay' => __('Razorpay', 'sikshya'),
                            'mollie' => __('Mollie', 'sikshya'),
                            'manual' => __('Manual Payment', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'enable_test_mode',
                        'type' => 'checkbox',
                        'label' => __('Enable Test Mode', 'sikshya'),
                        'description' => __('Use test/sandbox mode for payment gateways', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'accept_credit_cards',
                        'type' => 'checkbox',
                        'label' => __('Credit/Debit Cards', 'sikshya'),
                        'description' => __('Accept credit and debit card payments', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'accept_bank_transfer',
                        'type' => 'checkbox',
                        'label' => __('Bank Transfer', 'sikshya'),
                        'description' => __('Accept bank transfer payments', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'accept_digital_wallets',
                        'type' => 'checkbox',
                        'label' => __('Digital Wallets', 'sikshya'),
                        'description' => __('Accept digital wallet payments (PayPal, Apple Pay, etc.)', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'accept_cryptocurrency',
                        'type' => 'checkbox',
                        'label' => __('Cryptocurrency', 'sikshya'),
                        'description' => __('Accept cryptocurrency payments', 'sikshya'),
                        'default' => false
                    ]
                ]
            ],
            [
                'title' => __('Stripe Settings', 'sikshya'),
                'icon' => 'fas fa-stripe',
                'fields' => [
                    [
                        'key' => 'stripe_publishable_key',
                        'type' => 'text',
                        'label' => __('Publishable Key', 'sikshya'),
                        'description' => __('Your Stripe publishable key (starts with pk_)', 'sikshya'),
                        'placeholder' => 'pk_test_...',
                        'default' => ''
                    ],
                    [
                        'key' => 'stripe_secret_key',
                        'type' => 'password',
                        'label' => __('Secret Key', 'sikshya'),
                        'description' => __('Your Stripe secret key (starts with sk_)', 'sikshya'),
                        'placeholder' => 'sk_test_...',
                        'default' => ''
                    ],
                    [
                        'key' => 'stripe_webhook_secret',
                        'type' => 'password',
                        'label' => __('Webhook Secret', 'sikshya'),
                        'description' => __('Stripe webhook endpoint secret for payment confirmations', 'sikshya'),
                        'placeholder' => 'whsec_...',
                        'default' => ''
                    ]
                ]
            ],
            [
                'title' => __('PayPal Settings', 'sikshya'),
                'icon' => 'fab fa-paypal',
                'fields' => [
                    [
                        'key' => 'paypal_client_id',
                        'type' => 'text',
                        'label' => __('Client ID', 'sikshya'),
                        'description' => __('Your PayPal application client ID', 'sikshya'),
                        'placeholder' => 'Your PayPal Client ID',
                        'default' => ''
                    ],
                    [
                        'key' => 'paypal_secret',
                        'type' => 'password',
                        'label' => __('Secret', 'sikshya'),
                        'description' => __('Your PayPal application secret key', 'sikshya'),
                        'placeholder' => 'Your PayPal Secret',
                        'default' => ''
                    ],
                    [
                        'key' => 'paypal_mode',
                        'type' => 'select',
                        'label' => __('PayPal Mode', 'sikshya'),
                        'description' => __('PayPal environment mode', 'sikshya'),
                        'default' => 'sandbox',
                        'options' => [
                            'sandbox' => __('Sandbox (Test)', 'sikshya'),
                            'live' => __('Live (Production)', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Pricing & Taxes', 'sikshya'),
                'icon' => 'fas fa-percentage',
                'fields' => [
                    [
                        'key' => 'tax_rate',
                        'type' => 'number',
                        'label' => __('Tax Rate (%)', 'sikshya'),
                        'description' => __('Default tax rate applied to course prices', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.01
                    ],
                    [
                        'key' => 'tax_inclusive',
                        'type' => 'checkbox',
                        'label' => __('Tax Inclusive Pricing', 'sikshya'),
                        'description' => __('Course prices include tax (vs. tax added on top)', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'currency_decimal_places',
                        'type' => 'select',
                        'label' => __('Decimal Places', 'sikshya'),
                        'description' => __('Number of decimal places for currency display', 'sikshya'),
                        'default' => 2,
                        'options' => [
                            0 => __('0 (Whole numbers)', 'sikshya'),
                            2 => __('2 (e.g., $10.99)', 'sikshya'),
                            3 => __('3 (e.g., $10.999)', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Discounts & Coupons', 'sikshya'),
                'icon' => 'fas fa-tags',
                'fields' => [
                    [
                        'key' => 'enable_coupons',
                        'type' => 'checkbox',
                        'label' => __('Enable Coupons', 'sikshya'),
                        'description' => __('Allow students to use discount coupons', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'max_discount_percentage',
                        'type' => 'number',
                        'label' => __('Max Discount (%)', 'sikshya'),
                        'description' => __('Maximum discount percentage allowed', 'sikshya'),
                        'default' => 50,
                        'min' => 0,
                        'max' => 100
                    ],
                    [
                        'key' => 'coupon_expiry_days',
                        'type' => 'number',
                        'label' => __('Coupon Expiry (days)', 'sikshya'),
                        'description' => __('Default expiry period for new coupons', 'sikshya'),
                        'default' => 30,
                        'min' => 1,
                        'max' => 365
                    ]
                ]
            ],
            [
                'title' => __('Invoicing & Receipts', 'sikshya'),
                'icon' => 'fas fa-receipt',
                'fields' => [
                    [
                        'key' => 'auto_generate_invoices',
                        'type' => 'checkbox',
                        'label' => __('Auto-generate Invoices', 'sikshya'),
                        'description' => __('Automatically generate invoices for successful payments', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'send_payment_receipts',
                        'type' => 'checkbox',
                        'label' => __('Send Payment Receipts', 'sikshya'),
                        'description' => __('Email payment receipts to students', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'invoice_prefix',
                        'type' => 'text',
                        'label' => __('Invoice Number Prefix', 'sikshya'),
                        'description' => __('Prefix for invoice numbers (e.g., INV-2024-001)', 'sikshya'),
                        'placeholder' => 'INV-',
                        'default' => 'INV-'
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
                        'key' => 'enable_certificates',
                        'type' => 'checkbox',
                        'label' => __('Enable Certificates', 'sikshya'),
                        'description' => __('Enable certificate generation for completed courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'certificate_template',
                        'type' => 'select',
                        'label' => __('Default Certificate Template', 'sikshya'),
                        'description' => __('Default template for certificate design', 'sikshya'),
                        'default' => 'default',
                        'options' => [
                            'default' => __('Default Template', 'sikshya'),
                            'modern' => __('Modern Template', 'sikshya'),
                            'classic' => __('Classic Template', 'sikshya'),
                            'minimal' => __('Minimal Template', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'certificate_format',
                        'type' => 'select',
                        'label' => __('Certificate Format', 'sikshya'),
                        'description' => __('Format for generated certificates', 'sikshya'),
                        'default' => 'pdf',
                        'options' => [
                            'pdf' => __('PDF', 'sikshya'),
                            'png' => __('PNG Image', 'sikshya'),
                            'jpg' => __('JPG Image', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Certificate Design', 'sikshya'),
                'icon' => 'fas fa-image',
                'fields' => [
                    [
                        'key' => 'certificate_logo',
                        'type' => 'url',
                        'label' => __('Certificate Logo', 'sikshya'),
                        'description' => __('Logo to display on certificates', 'sikshya'),
                        'placeholder' => 'https://example.com/logo.png',
                        'default' => ''
                    ],
                    [
                        'key' => 'certificate_signature',
                        'type' => 'url',
                        'label' => __('Certificate Signature', 'sikshya'),
                        'description' => __('Signature image for certificates', 'sikshya'),
                        'placeholder' => 'https://example.com/signature.png',
                        'default' => ''
                    ],
                    [
                        'key' => 'certificate_font',
                        'type' => 'select',
                        'label' => __('Certificate Font', 'sikshya'),
                        'description' => __('Font family for certificate text', 'sikshya'),
                        'default' => 'Arial',
                        'options' => [
                            'Arial' => __('Arial', 'sikshya'),
                            'Times New Roman' => __('Times New Roman', 'sikshya'),
                            'Helvetica' => __('Helvetica', 'sikshya'),
                            'Georgia' => __('Georgia', 'sikshya'),
                            'Verdana' => __('Verdana', 'sikshya')
                        ]
                    ],
                    [
                        'key' => 'certificate_font_size',
                        'type' => 'number',
                        'label' => __('Font Size', 'sikshya'),
                        'description' => __('Base font size for certificate text', 'sikshya'),
                        'default' => 12,
                        'min' => 8,
                        'max' => 72
                    ],
                    [
                        'key' => 'certificate_color',
                        'type' => 'color',
                        'label' => __('Text Color', 'sikshya'),
                        'description' => __('Primary text color for certificates', 'sikshya'),
                        'default' => '#000000'
                    ]
                ]
            ],
            [
                'title' => __('Certificate Behavior', 'sikshya'),
                'icon' => 'fas fa-cog',
                'fields' => [
                    [
                        'key' => 'auto_generate_certificates',
                        'type' => 'checkbox',
                        'label' => __('Auto-generate Certificates', 'sikshya'),
                        'description' => __('Automatically generate certificates when students complete courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'email_certificates',
                        'type' => 'checkbox',
                        'label' => __('Email Certificates', 'sikshya'),
                        'description' => __('Automatically email certificates to students upon completion', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'certificate_expiry_days',
                        'type' => 'number',
                        'label' => __('Certificate Expiry (days)', 'sikshya'),
                        'description' => __('Days until certificates expire (0 = never expire)', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 3650
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
                'title' => __('Email Configuration', 'sikshya'),
                'icon' => 'fas fa-envelope',
                'fields' => [
                    [
                        'key' => 'from_name',
                        'type' => 'text',
                        'label' => __('From Name', 'sikshya'),
                        'description' => __('Name to use in email "From" field', 'sikshya'),
                        'placeholder' => __('Your LMS Name', 'sikshya'),
                        'default' => get_bloginfo('name')
                    ],
                    [
                        'key' => 'from_email',
                        'type' => 'email',
                        'label' => __('From Email', 'sikshya'),
                        'description' => __('Email address to use in "From" field', 'sikshya'),
                        'placeholder' => 'noreply@yoursite.com',
                        'default' => get_option('admin_email')
                    ],
                    [
                        'key' => 'reply_to_email',
                        'type' => 'email',
                        'label' => __('Reply-To Email', 'sikshya'),
                        'description' => __('Email address for replies', 'sikshya'),
                        'placeholder' => 'support@yoursite.com',
                        'default' => get_option('admin_email')
                    ]
                ]
            ],
            [
                'title' => __('Email Notifications', 'sikshya'),
                'icon' => 'fas fa-bell',
                'fields' => [
                    [
                        'key' => 'enable_welcome_email',
                        'type' => 'checkbox',
                        'label' => __('Welcome Email', 'sikshya'),
                        'description' => __('Send welcome email to new students', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_enrollment_email',
                        'type' => 'checkbox',
                        'label' => __('Enrollment Email', 'sikshya'),
                        'description' => __('Send confirmation email when students enroll', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_completion_email',
                        'type' => 'checkbox',
                        'label' => __('Completion Email', 'sikshya'),
                        'description' => __('Send email when students complete courses', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_reminder_email',
                        'type' => 'checkbox',
                        'label' => __('Progress Reminder Emails', 'sikshya'),
                        'description' => __('Send reminder emails to inactive students', 'sikshya'),
                        'default' => false
                    ]
                ]
            ],
            [
                'title' => __('Email Templates', 'sikshya'),
                'icon' => 'fas fa-edit',
                'fields' => [
                    [
                        'key' => 'email_template_header',
                        'type' => 'textarea',
                        'label' => __('Email Header', 'sikshya'),
                        'description' => __('HTML header for all LMS emails', 'sikshya'),
                        'placeholder' => __('Enter your email header HTML...', 'sikshya'),
                        'default' => ''
                    ],
                    [
                        'key' => 'email_template_footer',
                        'type' => 'textarea',
                        'label' => __('Email Footer', 'sikshya'),
                        'description' => __('HTML footer for all LMS emails', 'sikshya'),
                        'placeholder' => __('Enter your email footer HTML...', 'sikshya'),
                        'default' => ''
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

    /**
     * Normalize value for comparison (handle type differences)
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeValue($value): string
    {
        // Convert null to empty string
        if ($value === null) {
            return '';
        }
        
        // Convert to string and trim
        $normalized = (string) $value;
        $normalized = trim($normalized);
        
        // Handle numeric values (convert "0" to "0", not empty)
        if (is_numeric($value) && $value == 0) {
            return '0';
        }
        
        return $normalized;
    }
}
