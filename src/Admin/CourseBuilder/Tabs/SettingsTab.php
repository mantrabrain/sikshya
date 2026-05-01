<?php

/**
 * Settings Tab for Course Builder
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;
use Sikshya\Addons\Addons;
use Sikshya\Constants\PostTypes;
use Sikshya\Licensing\Pro;
use Sikshya\Services\CertificateIssuanceService;

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
        return __('Course options', 'sikshya');
    }

    /**
     * Get the description for this tab
     *
     * @return string
     */
    public function getDescription(): string
    {
        return __('Per-course rules: who can see it, when it counts as complete, certificates, reviews, and add-on overrides.', 'sikshya');
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
        $certificate_template_options = CertificateIssuanceService::getPublishedCertificateTemplateChoices();
        if ($certificate_template_options === []) {
            $certificate_template_options['0'] = __(
                'No published certificate templates yet — create one under Certificates → Templates.',
                'sikshya'
            );
        }

        $fields = [
            'course_settings' => [
                'section' => [
                    'title' => __('Visibility & catalog', 'sikshya'),
                    'description' => __('Whether the course is published, who can reach it, and how it appears in public catalogs.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>',
                ],
                'fields' => [
                'course_status' => [
                'type' => 'select',
                'label' => __('Who can see this course?', 'sikshya'),
                'select_placeholder' => __('Choose one…', 'sikshya'),
                'options' => [
                    'draft' => __('Draft', 'sikshya'),
                    'published' => __('Published', 'sikshya'),
                    'private' => __('Private', 'sikshya'),
                    'password_protected' => __('Password Protected', 'sikshya'),
                ],
                'default' => 'draft',
                'description' => __('Draft hides from most visitors; published is live; private needs a login; password adds a gate.', 'sikshya'),
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
                'email_marketing_disabled' => [
                    'type' => 'checkbox',
                    'label' => __('Disable email marketing sync for this course', 'sikshya'),
                    'description' => __('When checked, email marketing automations (Mailchimp/MailerLite sync) will ignore enrollments and completions for this course.', 'sikshya'),
                    'validation' => 'boolval',
                    'sanitization' => 'boolval',
                    'layout' => 'two_column',
                ],
                ],
            ],
            'completion_rules' => [
                'section' => [
                    'title' => __('Completion & progress', 'sikshya'),
                    'description' => __('When a student is considered finished, and whether progress is tracked through the course.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 00-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>',
                ],
                'fields' => [
                    'completion_rule' => [
                        'type' => 'select',
                        'label' => __('When is the course “finished”?', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
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
                    'enable_progress_tracking' => [
                        'type' => 'checkbox',
                        'label' => __('Track student progress', 'sikshya'),
                        'default' => '1',
                        'description' => __('Record which lessons each learner has finished so you can see progress and trigger certificates.', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
            'learn_layout' => [
                'section' => [
                    'title' => __('Learn layout', 'sikshya'),
                    'description' => __('How the curriculum outline behaves in lesson, quiz, and course Learn pages.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M9 18h11"/>
                    </svg>',
                ],
                'fields' => [
                    'learn_curriculum_sidebar_scrollable' => [
                        'type' => 'checkbox',
                        'label' => __('Scroll outline inside the sidebar (recommended)', 'sikshya'),
                        'description' => __(
                            'On by default: the outline list scrolls inside the left rail so the page does not grow endlessly. Turn off only if you prefer the legacy whole-column scroll.',
                            'sikshya'
                        ),
                        'default' => '1',
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
            'interaction_features' => [
                'section' => [
                    'title' => __('Discussions & reviews', 'sikshya'),
                    'description' => __('Star ratings, written reviews, and — with the Community Discussions add-on — questions and Q&A on lesson and quiz pages.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>',
                ],
                'fields' => [
                    'enable_reviews' => [
                        'type' => 'checkbox',
                        'label' => __('Allow course reviews', 'sikshya'),
                        'description' => __('Students can rate and review the course.', 'sikshya'),
                        'validation' => 'boolval',
                        'sanitization' => 'boolval',
                    ],
                ],
            ],
            'certificate_settings' => [
                'section' => [
                    'title' => __('Certificates', 'sikshya'),
                    'description' => __('Issue a certificate when a learner finishes. Pick a template and how much progress is required.', 'sikshya'),
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
                        'select_placeholder' => __('Choose a template…', 'sikshya'),
                        'options' => $certificate_template_options,
                        'description' => __(
                            'Uses the same certificate posts as Certificates → Templates. Pick the layout learners receive when a certificate is issued.',
                            'sikshya'
                        ),
                        'depends_on' => 'enable_certificate',
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
        ];

        // Append or adjust fields on the Settings tab (React course builder).
        $fields = apply_filters('sikshya_course_builder_settings_tab_fields', $fields, $this->plugin);

        // Reviews are provided by the Pro `course_reviews` add-on. Hide the toggle when
        // the feature is not licensed or the add-on is disabled.
        $reviews_available = Pro::feature('course_reviews') && Addons::isEnabled('course_reviews');
        if (!$reviews_available) {
            if (isset($fields['interaction_features']['fields']['enable_reviews'])) {
                unset($fields['interaction_features']['fields']['enable_reviews']);
            }
            // If no other add-on injected fields remain, remove the section entirely.
            if (
                isset($fields['interaction_features']['fields'])
                && is_array($fields['interaction_features']['fields'])
                && $fields['interaction_features']['fields'] === []
            ) {
                unset($fields['interaction_features']);
            }
        }

        return $fields;
    }

    /**
     * @param int $course_id
     * @return array<string, mixed>
     */
    public function load(int $course_id): array
    {
        $data = parent::load($course_id);
        if ($course_id > 0 && array_key_exists('certificate_template', $data)) {
            $raw = $data['certificate_template'];
            $data['certificate_template'] = CertificateIssuanceService::normalizeBuilderCertificateTemplateValue(
                $course_id,
                is_scalar($raw) ? (string) $raw : ''
            );
        }

        return $data;
    }

    /**
     * Keep `_sikshya_certificate` aligned with the selected template for integrations that read that meta.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data, int $course_id): bool
    {
        $ok = parent::save($data, $course_id);
        if (!$ok || $course_id <= 0) {
            return $ok;
        }

        $enabled = !empty($data['enable_certificate']);
        $tid = isset($data['certificate_template']) ? absint($data['certificate_template']) : 0;

        if (!$enabled || $tid <= 0) {
            delete_post_meta($course_id, '_sikshya_certificate');
            return $ok;
        }

        if (get_post_type($tid) === PostTypes::CERTIFICATE && get_post_status($tid) === 'publish') {
            update_post_meta($course_id, '_sikshya_certificate', $tid);
        }

        return $ok;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $field_config
     * @return mixed
     */
    protected function sanitizeField(string $field_id, $value, array $field_config)
    {
        if ($field_id === 'certificate_template') {
            $id = absint($value);
            if ($id <= 0) {
                return '';
            }
            if (get_post_type($id) !== PostTypes::CERTIFICATE || get_post_status($id) !== 'publish') {
                return '';
            }

            return (string) $id;
        }

        return parent::sanitizeField($field_id, $value, $field_config);
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
