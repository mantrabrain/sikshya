<?php

/**
 * Course Information Tab for Course Builder
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;
use Sikshya\Constants\Taxonomies;
use Sikshya\Models\Course;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CourseInfoTab extends AbstractTab
{
    /**
     * Get the unique identifier for this tab
     *
     * @return string
     */
    public function getId(): string
    {
        return 'course';
    }

    /**
     * Get the display title for this tab
     *
     * @return string
     */
    public function getTitle(): string
    {
        return __('Course Information', 'sikshya');
    }

    /**
     * Get the description for this tab
     *
     * @return string
     */
    public function getDescription(): string
    {
        return __('Title, description, and basic details', 'sikshya');
    }

    /**
     * Get the SVG icon for this tab
     *
     * @return string
     */
    public function getIcon(): string
    {
        return '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>';
    }

    /**
     * Get the tab order
     *
     * @return int
     */
    public function getOrder(): int
    {
        return 1;
    }

    /**
     * Options for course category select (WordPress taxonomy terms).
     *
     * @return array<string, string>
     */
    private function getCourseCategoryOptions(): array
    {
        $options = [
            '' => __('Select category', 'sikshya'),
        ];

        $terms = get_terms(
            [
                'taxonomy' => Taxonomies::COURSE_CATEGORY,
                'hide_empty' => false,
            ]
        );

        if (is_wp_error($terms) || empty($terms)) {
            return $options;
        }

        foreach ($terms as $term) {
            $options[$term->slug] = $term->name;
        }

        return $options;
    }

    /**
     * Get the fields configuration for this tab
     *
     * @return array
     */
    public function getFields(): array
    {
        $category_options = $this->getCourseCategoryOptions();

        $fields = [
            'basic_info' => [
                'section' => [
                    'title' => __('Basic Information', 'sikshya'),
                    'description' => __('Set up the fundamental details of your course', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>',
                ],
                'fields' => [
                'title' => [
                'type' => 'text',
                'label' => __('Course Title', 'sikshya'),
                'placeholder' => __('Enter an engaging course title', 'sikshya'),
                'required' => true,
                'description' => __('The main title of your course', 'sikshya'),
                        'validation' => 'required|min:3|max:200',
                        'sanitization' => 'sanitize_text_field',
                    ],
                    'slug' => [
                        'type' => 'permalink',
                        'label' => __('Permalink', 'sikshya'),
                        'description' => __('URL-friendly version of the course title', 'sikshya'),
                        'validation' => 'required|alpha_dash',
                        'sanitization' => 'sanitize_title',
                ],
                'short_description' => [
                'type' => 'text',
                'label' => __('Short Description', 'sikshya'),
                'placeholder' => __('Brief one-line description for course cards', 'sikshya'),
                'description' => __('A concise description for course listings', 'sikshya'),
                        'validation' => 'max:255',
                        'sanitization' => 'sanitize_text_field',
                ],
                'description' => [
                'type' => 'textarea',
                'label' => __('Detailed Description', 'sikshya'),
                'placeholder' => __('Detailed description of what students will learn, course benefits, and outcomes', 'sikshya'),
                'required' => true,
                'description' => __('Comprehensive description of your course content and objectives', 'sikshya'),
                        'validation' => 'required|min:10',
                        'sanitization' => 'wp_kses_post',
                ],
                'category' => [
                'type' => 'select',
                'label' => __('Course category', 'sikshya'),
                'options' => $category_options,
                'description' => __('Uses your course categories (manage under Sikshya LMS → Categories).', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                ],
                'course_tags' => [
                'type' => 'text',
                'label' => __('Tags', 'sikshya'),
                'placeholder' => __('e.g. javascript, beginner, career', 'sikshya'),
                'description' => __('Comma-separated tags for search and filtering.', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                ],
                'difficulty' => [
                'type' => 'select',
                'label' => __('Difficulty Level', 'sikshya'),
                'options' => [
                    'beginner' => __('Beginner', 'sikshya'),
                    'intermediate' => __('Intermediate', 'sikshya'),
                    'advanced' => __('Advanced', 'sikshya'),
                ],
                'default' => 'beginner',
                'description' => __('The skill level required for this course', 'sikshya'),
                        'validation' => 'required|in:beginner,intermediate,advanced',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                ],
                'duration' => [
                'type' => 'number',
                'label' => __('Estimated Duration (hours)', 'sikshya'),
                'placeholder' => '10',
                'min' => 0.5,
                'step' => 0.5,
                'description' => __('Estimated time to complete the course', 'sikshya'),
                        'validation' => 'numeric|min:0.5',
                        'sanitization' => 'floatval',
                        'layout' => 'two_column',
                ],
                'language' => [
                'type' => 'select',
                'label' => __('Course Language', 'sikshya'),
                'options' => [
                    'en' => __('English', 'sikshya'),
                    'es' => __('Spanish', 'sikshya'),
                    'fr' => __('French', 'sikshya'),
                    'de' => __('German', 'sikshya'),
                    'it' => __('Italian', 'sikshya'),
                    'pt' => __('Portuguese', 'sikshya'),
                    'other' => __('Other', 'sikshya'),
                ],
                'default' => 'en',
                'description' => __('Primary language of instruction', 'sikshya'),
                        'validation' => 'required',
                        'sanitization' => 'sanitize_text_field',
                        'layout' => 'two_column',
                    ],
                'target_audience' => [
                'type' => 'textarea',
                'label' => __('Target audience', 'sikshya'),
                'placeholder' => __('Who is this course for? Prior experience, roles, or goals.', 'sikshya'),
                'description' => __('Helps instructors and students set expectations.', 'sikshya'),
                        'sanitization' => 'wp_kses_post',
                    ],
                ],
            ],
            'media_visuals' => [
                'section' => [
                    'title' => __('Media & Visuals', 'sikshya'),
                    'description' => __('Add visual elements to make your course more engaging', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>',
                ],
                'fields' => [
                'featured_image' => [
                        'type' => 'media_upload',
                        'label' => __('Course Featured Image', 'sikshya'),
                        'placeholder' => __('Recommended 1200×675 or larger', 'sikshya'),
                        'description' => __('Recommended: 1200x675px (16:9 ratio)', 'sikshya'),
                        'media_type' => 'image',
                        'layout' => 'two_column',
                        'validation' => 'url',
                        'sanitization' => 'esc_url_raw',
                    ],
                    'video_url' => [
                        'type' => 'media_upload',
                        'label' => __('Course Trailer Video', 'sikshya'),
                        'placeholder' => __('https://youtube.com/… or Vimeo URL', 'sikshya'),
                        'description' => __('Optional promotional video for your course', 'sikshya'),
                        'media_type' => 'video',
                        'layout' => 'two_column',
                        'validation' => 'url',
                        'sanitization' => 'esc_url_raw',
                    ],
                ],
            ],
            'learning_outcomes' => [
                'section' => [
                    'title' => __('Learning Outcomes', 'sikshya'),
                    'description' => __('What will students learn from this course?', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>',
                ],
                'fields' => [
                    'learning_outcomes' => [
                        'type' => 'repeater',
                        'label' => __('Learning outcomes', 'sikshya'),
                        'placeholder' => __('Students will be able to…', 'sikshya'),
                        'add_button_text' => __('Add outcome', 'sikshya'),
                        'description' => __('Clear, measurable outcomes shown on the course page.', 'sikshya'),
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
            'instructors_section' => [
                'section' => [
                    'title' => __('Instructors', 'sikshya'),
                    'description' => __('Who teaches this course? Shown on the course page when your theme supports it.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>',
                ],
                'fields' => [
                    'instructors' => [
                        'type' => 'user_select',
                        'label' => __('Instructors', 'sikshya'),
                        'multiple' => true,
                        'role_filter' => ['administrator', 'editor', 'author'],
                        'description' => __('Hold Cmd/Ctrl (Mac/Windows) to select multiple users.', 'sikshya'),
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
            'marketing' => [
                'section' => [
                    'title' => __('Highlights, FAQ & resources', 'sikshya'),
                    'description' => __('Optional content for the course landing page.', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>',
                ],
                'fields' => [
                    'course_highlights' => [
                        'type' => 'repeater',
                        'label' => __('Course highlights', 'sikshya'),
                        'placeholder' => __('Short benefit or bullet point', 'sikshya'),
                        'add_button_text' => __('Add highlight', 'sikshya'),
                        'description' => __('Short bullets for marketing (e.g. “Lifetime access”).', 'sikshya'),
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
                    ],
                    'course_faq' => [
                        'type' => 'repeater_group',
                        'label' => __('FAQ', 'sikshya'),
                        'add_button_text' => __('Add FAQ item', 'sikshya'),
                        'subfields' => [
                            'question' => [
                                'type' => 'text',
                                'label' => __('Question', 'sikshya'),
                                'placeholder' => __('Question students often ask', 'sikshya'),
                            ],
                            'answer' => [
                                'type' => 'textarea',
                                'label' => __('Answer', 'sikshya'),
                                'placeholder' => __('Clear, helpful answer', 'sikshya'),
                            ],
                        ],
                        'sanitization' => 'sanitize_text_field',
                    ],
                    'course_resources' => [
                        'type' => 'repeater_group',
                        'label' => __('Downloadable resources', 'sikshya'),
                        'add_button_text' => __('Add resource', 'sikshya'),
                        'description' => __('Links to PDFs, files, or external materials.', 'sikshya'),
                        'subfields' => [
                            'title' => [
                                'type' => 'text',
                                'label' => __('Title', 'sikshya'),
                                'placeholder' => __('Resource name', 'sikshya'),
                            ],
                            'url' => [
                                'type' => 'url',
                                'label' => __('URL', 'sikshya'),
                                'placeholder' => 'https://',
                            ],
                        ],
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
        ];

        // Allow pro plugins to add/modify fields
        $fields = apply_filters('sikshya_course_info_tab_fields', $fields);

        return $fields;
    }

    /**
     * Render the tab content dynamically based on field definitions
     *
     * @param array $data
     * @return string
     */
    protected function renderContent(array $data): string
    {
        return $this->renderSections($data);
    }

    /**
     * Override save method to handle post title and content
     *
     * @param array $data
     * @param int $course_id
     * @return bool
     */
    public function save(array $data, int $course_id): bool
    {
        // Use the new Course model
        $course = Course::find($course_id);

        if (!$course || !$course->exists()) {
            return false;
        }

        $success = true;

        // Prepare update data for post fields
        $update_data = [];

        // Save title, description, and slug to post
        if (!empty($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }

        if (!empty($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
        }

        if (!empty($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
        }

        // Update post data
        if (!empty($update_data)) {
            $result = $course->update($update_data);
            if (!$result) {
                $success = false;
            }
        }

        // Save meta fields using parent method
        $meta_success = parent::save($data, $course_id);

        $this->syncFeaturedThumbnail($course_id, $data);

        $this->syncCourseTaxonomies($course_id, $data);

        // Allow pro plugins to save additional fields
        do_action('sikshya_course_save_meta', $course_id);

        return $success && $meta_success;
    }

    /**
     * Assign course category and tags from builder data.
     *
     * @param int   $course_id Course post ID.
     * @param array $data      Form data.
     */
    private function syncCourseTaxonomies(int $course_id, array $data): void
    {
        $slug = isset($data['category']) ? sanitize_text_field($data['category']) : '';
        if ($slug !== '') {
            $term = get_term_by('slug', $slug, Taxonomies::COURSE_CATEGORY);
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($course_id, [(int) $term->term_id], Taxonomies::COURSE_CATEGORY, false);
            }
        } else {
            wp_set_object_terms($course_id, [], Taxonomies::COURSE_CATEGORY, false);
        }

        if (array_key_exists('course_tags', $data)) {
            $raw = sanitize_text_field(wp_unslash((string) $data['course_tags']));
            $tags = array_filter(array_map('trim', explode(',', $raw)));
            if (!empty($tags)) {
                wp_set_object_terms($course_id, $tags, Taxonomies::COURSE_TAG, false);
            } else {
                wp_set_object_terms($course_id, [], Taxonomies::COURSE_TAG, false);
            }
        }
    }

    /**
     * Override load method to get title and content from post
     *
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array
    {
        // Use the new Course model
        $course = Course::find($course_id);

        if (!$course || !$course->exists()) {
            return [];
        }

        // Load post data (from wp_posts table)
        $data = [
            'title' => $course->getTitle(), // From post table
            'description' => $course->getDescription(), // From post table
            'slug' => $course->getSlug(), // From post table
            'id' => $course->getId(), // From post table
        ];

        // Load meta fields using parent method (from wp_postmeta table)
        $meta_data = parent::load($course_id);

        // Merge data, but don't override post fields with meta fields
        $data = array_merge($meta_data, $data);

        $cat_terms = wp_get_post_terms($course_id, Taxonomies::COURSE_CATEGORY, ['number' => 1]);
        if (!is_wp_error($cat_terms) && !empty($cat_terms)) {
            $data['category'] = $cat_terms[0]->slug;
        }

        $tag_terms = wp_get_post_terms($course_id, Taxonomies::COURSE_TAG, ['fields' => 'names']);
        if (!is_wp_error($tag_terms) && !empty($tag_terms)) {
            $data['course_tags'] = implode(', ', $tag_terms);
        }

        $thumb_id = (int) get_post_thumbnail_id($course_id);
        $data['featured_image_id'] = $thumb_id > 0 ? $thumb_id : 0;
        if ($thumb_id > 0) {
            $url = wp_get_attachment_image_url($thumb_id, 'large') ?: '';
            if ($url !== '') {
                $data['featured_image'] = $url;
            }
        }

        return $data;
    }

    /**
     * Set or clear the WordPress featured image from builder payload (attachment ID and/or URL).
     *
     * @param int                  $course_id Course post ID.
     * @param array<string, mixed> $data      Builder save payload.
     */
    private function syncFeaturedThumbnail(int $course_id, array $data): void
    {
        $has_id_key = array_key_exists('featured_image_id', $data);
        $aid = $has_id_key ? (int) $data['featured_image_id'] : -1;
        $url = isset($data['featured_image']) ? esc_url_raw((string) $data['featured_image']) : '';

        if ($aid > 0 && wp_attachment_is_image($aid)) {
            set_post_thumbnail($course_id, $aid);

            return;
        }

        if ($has_id_key && $aid === 0) {
            if ($url === '') {
                delete_post_thumbnail($course_id);
            } else {
                $found = attachment_url_to_postid($url);
                if ($found > 0) {
                    set_post_thumbnail($course_id, $found);
                }
            }

            return;
        }

        if ($aid === -1 && $url !== '') {
            $found = attachment_url_to_postid($url);
            if ($found > 0) {
                set_post_thumbnail($course_id, $found);
            }
        }
    }
}
