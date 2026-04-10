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
            'course_settings' => [
                'section' => [
                    'title' => __('Course Settings', 'sikshya'),
                    'description' => __('Configure basic course settings and visibility', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>',
                ],
                'fields' => [
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
                        'validation' => 'required',
                        'sanitization' => 'sanitize_text_field',
                ],
                'course_password' => [
                    'type' => 'password',
                    'label' => __('Course password', 'sikshya'),
                    'placeholder' => __('Enter password', 'sikshya'),
                    'description' => __('Required when visibility is password protected.', 'sikshya'),
                    'depends_on' => 'course_status',
                    'depends_value' => 'password_protected',
                    'validation' => 'min:6',
                    'sanitization' => 'sanitize_text_field',
                ],
                'featured_course' => [
                    'type' => 'checkbox',
                    'label' => __('Featured course', 'sikshya'),
                    'description' => __('Highlight this course in catalogs and widgets.', 'sikshya'),
                    'validation' => 'boolval',
                    'sanitization' => 'boolval',
                ],
                'featured_badge_text' => [
                    'type' => 'text',
                    'label' => __('Featured badge text', 'sikshya'),
                    'placeholder' => __('e.g. Popular, New', 'sikshya'),
                    'description' => __('Short label shown on the course card (optional).', 'sikshya'),
                    'depends_on' => 'featured_course',
                    'sanitization' => 'sanitize_text_field',
                    'layout' => 'two_column',
                ],
                'hide_from_catalog' => [
                    'type' => 'checkbox',
                    'label' => __('Hide from catalog', 'sikshya'),
                    'description' => __('Course stays published but is hidden from public listings (direct link only).', 'sikshya'),
                    'validation' => 'boolval',
                    'sanitization' => 'boolval',
                    'layout' => 'two_column',
                ],
                'prerequisites_required' => [
                    'type' => 'checkbox',
                    'label' => __('Require prerequisites before enrollment', 'sikshya'),
                    'description' => __('When enabled, enrollment checks prerequisites from the Pricing tab (implementation in enrollment flow).', 'sikshya'),
                    'validation' => 'boolval',
                    'sanitization' => 'boolval',
                    'layout' => 'two_column',
                ],
                ],
            ],
            'completion_rules' => [
                'section' => [
                    'title' => __('Completion rules', 'sikshya'),
                    'description' => __('Define what “finished” means for this course.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 00-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>',
                ],
                'fields' => [
                    'completion_rule' => [
                        'type' => 'select',
                        'label' => __('Completion rule', 'sikshya'),
                        'options' => [
                            'all_lessons' => __('All lessons completed', 'sikshya'),
                            'all_content' => __('All lessons, quizzes, and assignments completed', 'sikshya'),
                            'pass_quizzes' => __('Pass all quizzes (minimum score)', 'sikshya'),
                            'manual' => __('Mark complete manually (instructor)', 'sikshya'),
                        ],
                        'default' => 'all_lessons',
                        'validation' => 'required',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                    'min_quiz_score' => [
                        'type' => 'number',
                        'label' => __('Minimum quiz score (%)', 'sikshya'),
                        'min' => 0,
                        'max' => 100,
                        'default' => 70,
                        'depends_on' => 'completion_rule',
                        'depends_value' => 'pass_quizzes',
                        'validation' => 'numeric|min:0|max:100',
                        'sanitization' => 'intval',
                        'layout' => 'two_column',
                    ],
                    'require_final_quiz' => [
                        'type' => 'checkbox',
                        'label' => __('Require a final quiz', 'sikshya'),
                        'description' => __('Students must pass the designated final quiz to complete the course.', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
            'interaction_features' => [
                'section' => [
                    'title' => __('Interaction Features', 'sikshya'),
                    'description' => __('Enable student interaction and engagement features', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>',
                ],
                'fields' => [
                'enable_discussions' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Discussions', 'sikshya'),
                'default' => '1',
                'description' => __('Allow students to ask questions and discuss topics', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                ],
                'enable_qa' => [
                'type' => 'checkbox',
                'label' => __('Enable Q&A Section', 'sikshya'),
                'description' => __('Dedicated section for course-related questions', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                ],
                'enable_reviews' => [
                'type' => 'checkbox',
                'label' => __('Allow Course Reviews', 'sikshya'),
                'description' => __('Students can rate and review the course', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
            'certificate_settings' => [
                'section' => [
                    'title' => __('Certificates', 'sikshya'),
                    'description' => __('Certificates are issued from this tab only (single place to configure).', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>',
                ],
                'fields' => [
                    'enable_certificate' => [
                        'type' => 'checkbox',
                        'label' => __('Enable completion certificate', 'sikshya'),
                        'description' => __('Allow issuing a certificate when completion rules are met.', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                    'certificate_requires_completion' => [
                        'type' => 'checkbox',
                        'label' => __('Certificate requires course completion', 'sikshya'),
                        'description' => __('Only issue a certificate after completion rules are satisfied.', 'sikshya'),
                        'depends_on' => 'enable_certificate',
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                    'certificate_template' => [
                        'type' => 'select',
                        'label' => __('Certificate template', 'sikshya'),
                        'options' => [
                            'default' => __('Default', 'sikshya'),
                            'modern' => __('Modern', 'sikshya'),
                            'classic' => __('Classic', 'sikshya'),
                            'custom' => __('Custom', 'sikshya'),
                        ],
                        'default' => 'default',
                        'description' => __('Visual style for the PDF or print view.', 'sikshya'),
                        'depends_on' => 'enable_certificate',
                        'validation' => 'in:default,modern,classic,custom',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                    'completion_threshold' => [
                        'type' => 'number',
                        'label' => __('Progress threshold for certificate (%)', 'sikshya'),
                        'placeholder' => '80',
                        'min' => 0,
                        'max' => 100,
                        'default' => 80,
                        'description' => __('Minimum overall progress before a certificate can be issued.', 'sikshya'),
                        'depends_on' => 'enable_certificate',
                        'validation' => 'numeric|min:0|max:100',
                        'sanitization' => 'intval',
                        'layout' => 'two_column',
                    ],
                ],
            ],
            'seo_settings' => [
                'section' => [
                    'title' => __('SEO Settings', 'sikshya'),
                    'description' => __('Optimize your course for search engines', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>',
                ],
                'fields' => [
                'seo_title' => [
                'type' => 'text',
                'label' => __('SEO Title', 'sikshya'),
                'placeholder' => __('Enter SEO title for search engines', 'sikshya'),
                'description' => __('Custom title for search engine optimization', 'sikshya'),
                        'validation' => 'max:60',
                        'sanitization' => 'sanitize_text_field',
                ],
                    'meta_description' => [
                'type' => 'textarea',
                        'label' => __('Meta Description', 'sikshya'),
                        'placeholder' => __('Enter meta description for search engines', 'sikshya'),
                'description' => __('Custom description for search engine optimization', 'sikshya'),
                        'validation' => 'max:160',
                        'sanitization' => 'sanitize_textarea_field',
                ],
                    'focus_keywords' => [
                'type' => 'text',
                        'label' => __('Focus Keywords', 'sikshya'),
                'placeholder' => __('keyword1, keyword2, keyword3', 'sikshya'),
                'description' => __('Comma-separated keywords for SEO', 'sikshya'),
                        'validation' => 'max:200',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
            'advanced_settings' => [
                'section' => [
                    'title' => __('Advanced Settings', 'sikshya'),
                    'description' => __('Configure advanced course features', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>',
                ],
                'fields' => [
                    'enable_progress_tracking' => [
                        'type' => 'checkbox',
                        'label' => __('Enable Progress Tracking', 'sikshya'),
                        'default' => '1',
                        'description' => __('Track student progress through the course', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                    'send_welcome_email' => [
                        'type' => 'checkbox',
                        'label' => __('Send Welcome Email', 'sikshya'),
                        'default' => '1',
                        'description' => __('Send welcome email to new students', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        $status = isset($data['course_status']) ? sanitize_text_field(wp_unslash((string) $data['course_status'])) : '';
        if ($status === 'password_protected') {
            $pw = $data['course_password'] ?? '';
            if ($pw === '' || $pw === null) {
                $errors['course_password'] = __('Please enter a course password.', 'sikshya');
            } elseif (strlen((string) $pw) < 6) {
                $errors['course_password'] = __('Password must be at least 6 characters.', 'sikshya');
            }
        } else {
            unset($errors['course_password']);
        }

        return $errors;
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
