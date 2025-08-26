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
                'label' => __('Course Password', 'sikshya'),
                'placeholder' => __('Enter course password', 'sikshya'),
                'description' => __('Password required to access the course', 'sikshya'),
                        'validation' => 'min:6',
                        'sanitization' => 'sanitize_text_field',
            ],
            'featured_course' => [
                'type' => 'checkbox',
                'label' => __('Mark as Featured Course', 'sikshya'),
                'description' => __('Featured courses appear prominently on your site', 'sikshya'),
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
                    'title' => __('Certificate Settings', 'sikshya'),
                    'description' => __('Configure course completion certificates', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>',
                ],
                'fields' => [
            'enable_certificate' => [
                'type' => 'checkbox',
                'label' => __('Enable Course Completion Certificate', 'sikshya'),
                'description' => __('Award certificate when students complete the course', 'sikshya'),
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
                'description' => __('Choose the certificate design', 'sikshya'),
                        'validation' => 'in:default,modern,classic,custom',
                        'sanitization' => 'sanitize_text_field',
            ],
            'completion_threshold' => [
                'type' => 'number',
                'label' => __('Completion Threshold (%)', 'sikshya'),
                'placeholder' => '80',
                'min' => 0,
                'max' => 100,
                'default' => 80,
                'description' => __('Percentage of course completion required for certificate', 'sikshya'),
                        'validation' => 'numeric|min:0|max:100',
                        'sanitization' => 'intval',
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
