<?php
/**
 * Course Information Tab for Course Builder
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
     * Get the fields configuration for this tab
     * 
     * @return array
     */
    public function getFields(): array
    {
        return [
            'title' => [
                'type' => 'text',
                'label' => __('Course Title', 'sikshya'),
                'placeholder' => __('Enter an engaging course title', 'sikshya'),
                'required' => true,
                'description' => __('The main title of your course', 'sikshya'),
            ],
            'short_description' => [
                'type' => 'text',
                'label' => __('Short Description', 'sikshya'),
                'placeholder' => __('Brief one-line description for course cards', 'sikshya'),
                'description' => __('A concise description for course listings', 'sikshya'),
            ],
            'description' => [
                'type' => 'textarea',
                'label' => __('Detailed Description', 'sikshya'),
                'placeholder' => __('Detailed description of what students will learn, course benefits, and outcomes', 'sikshya'),
                'required' => true,
                'description' => __('Comprehensive description of your course content and objectives', 'sikshya'),
            ],
            'category' => [
                'type' => 'select',
                'label' => __('Course Category', 'sikshya'),
                'options' => [
                    '' => __('Select Category', 'sikshya'),
                    'programming' => __('Programming', 'sikshya'),
                    'design' => __('Design', 'sikshya'),
                    'business' => __('Business', 'sikshya'),
                    'marketing' => __('Marketing', 'sikshya'),
                    'photography' => __('Photography', 'sikshya'),
                    'music' => __('Music', 'sikshya'),
                    'other' => __('Other', 'sikshya'),
                ],
                'description' => __('Choose the most appropriate category for your course', 'sikshya'),
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
            ],
            'duration' => [
                'type' => 'number',
                'label' => __('Estimated Duration (hours)', 'sikshya'),
                'placeholder' => '10',
                'min' => 0.5,
                'step' => 0.5,
                'description' => __('Estimated time to complete the course', 'sikshya'),
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
            ],
            'featured_image' => [
                'type' => 'url',
                'label' => __('Featured Image URL', 'sikshya'),
                'placeholder' => __('https://example.com/image.jpg', 'sikshya'),
                'description' => __('URL for the course featured image', 'sikshya'),
            ],
            'video_url' => [
                'type' => 'url',
                'label' => __('Preview Video URL', 'sikshya'),
                'placeholder' => __('https://youtube.com/watch?v=...', 'sikshya'),
                'description' => __('URL for course preview video (YouTube, Vimeo, etc.)', 'sikshya'),
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Basic Information', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Set up the fundamental details of your course', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Title', 'sikshya'); ?> *</label>
                <input type="text" name="title" value="<?php echo esc_attr($data['title'] ?? ''); ?>" placeholder="<?php _e('Enter an engaging course title', 'sikshya'); ?>" required>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Permalink', 'sikshya'); ?></label>
                <div class="sikshya-permalink-wrapper">
                    <div class="sikshya-permalink-display" id="permalink-display">
                        <span class="sikshya-permalink-base"><?php echo esc_url(home_url('/courses/')); ?></span>
                        <span class="sikshya-permalink-slug" id="permalink-slug"><?php echo esc_html($data['slug'] ?? ''); ?></span>
                    </div>
                    <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" id="edit-permalink-btn" onclick="togglePermalinkEdit()">
                        <?php _e('Edit', 'sikshya'); ?>
                    </button>
                    <div class="sikshya-permalink-edit" id="permalink-edit" style="display: none;">
                        <input type="text" name="slug" id="permalink-input" value="<?php echo esc_attr($data['slug'] ?? ''); ?>" placeholder="<?php _e('course-slug', 'sikshya'); ?>">
                        <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-btn-sm" onclick="savePermalink()">
                            <?php _e('OK', 'sikshya'); ?>
                        </button>
                        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" onclick="cancelPermalinkEdit()">
                            <?php _e('Cancel', 'sikshya'); ?>
                        </button>
                    </div>
                </div>
                <p class="sikshya-help-text"><?php _e('The URL-friendly version of your course title', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Short Description', 'sikshya'); ?></label>
                <input type="text" name="short_description" value="<?php echo esc_attr($data['short_description'] ?? ''); ?>" placeholder="<?php _e('Brief one-line description for course cards', 'sikshya'); ?>">
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Detailed Description', 'sikshya'); ?> *</label>
                <textarea name="description" placeholder="<?php _e('Detailed description of what students will learn, course benefits, and outcomes', 'sikshya'); ?>" required><?php echo esc_textarea($data['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label><?php _e('Course Category', 'sikshya'); ?></label>
                    <select name="category">
                        <option value=""><?php _e('Select Category', 'sikshya'); ?></option>
                        <option value="programming" <?php selected($data['category'] ?? '', 'programming'); ?>><?php _e('Programming', 'sikshya'); ?></option>
                        <option value="design" <?php selected($data['category'] ?? '', 'design'); ?>><?php _e('Design', 'sikshya'); ?></option>
                        <option value="business" <?php selected($data['category'] ?? '', 'business'); ?>><?php _e('Business', 'sikshya'); ?></option>
                        <option value="marketing" <?php selected($data['category'] ?? '', 'marketing'); ?>><?php _e('Marketing', 'sikshya'); ?></option>
                        <option value="photography" <?php selected($data['category'] ?? '', 'photography'); ?>><?php _e('Photography', 'sikshya'); ?></option>
                        <option value="music" <?php selected($data['category'] ?? '', 'music'); ?>><?php _e('Music', 'sikshya'); ?></option>
                        <option value="other" <?php selected($data['category'] ?? '', 'other'); ?>><?php _e('Other', 'sikshya'); ?></option>
                    </select>
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Difficulty Level', 'sikshya'); ?></label>
                    <select name="difficulty">
                        <option value="beginner" <?php selected($data['difficulty'] ?? 'beginner', 'beginner'); ?>><?php _e('Beginner', 'sikshya'); ?></option>
                        <option value="intermediate" <?php selected($data['difficulty'] ?? '', 'intermediate'); ?>><?php _e('Intermediate', 'sikshya'); ?></option>
                        <option value="advanced" <?php selected($data['difficulty'] ?? '', 'advanced'); ?>><?php _e('Advanced', 'sikshya'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="sikshya-form-grid">
                <div class="sikshya-form-row">
                    <label><?php _e('Estimated Duration (hours)', 'sikshya'); ?></label>
                    <input type="number" name="duration" value="<?php echo esc_attr($data['duration'] ?? ''); ?>" placeholder="10" min="1" step="0.5">
                </div>
                
                <div class="sikshya-form-row">
                    <label><?php _e('Course Language', 'sikshya'); ?></label>
                    <select name="language">
                        <option value="en" <?php selected($data['language'] ?? 'en', 'en'); ?>><?php _e('English', 'sikshya'); ?></option>
                        <option value="es" <?php selected($data['language'] ?? '', 'es'); ?>><?php _e('Spanish', 'sikshya'); ?></option>
                        <option value="fr" <?php selected($data['language'] ?? '', 'fr'); ?>><?php _e('French', 'sikshya'); ?></option>
                        <option value="de" <?php selected($data['language'] ?? '', 'de'); ?>><?php _e('German', 'sikshya'); ?></option>
                        <option value="it" <?php selected($data['language'] ?? '', 'it'); ?>><?php _e('Italian', 'sikshya'); ?></option>
                        <option value="pt" <?php selected($data['language'] ?? '', 'pt'); ?>><?php _e('Portuguese', 'sikshya'); ?></option>
                        <option value="other" <?php selected($data['language'] ?? '', 'other'); ?>><?php _e('Other', 'sikshya'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="sikshya-section sikshya-section-modern">
            <div class="sikshya-section-header">
                <div class="sikshya-section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="sikshya-section-content">
                    <h3 class="sikshya-section-title"><?php _e('Media & Visuals', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('Add visual elements to make your course more engaging', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Featured Image', 'sikshya'); ?></label>
                <div class="sikshya-media-upload">
                    <div class="sikshya-media-preview" id="featured_image_preview">
                        <div class="sikshya-media-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span><?php _e('No image selected', 'sikshya'); ?></span>
                        </div>
                    </div>
                    <input type="hidden" name="featured_image" id="featured_image" value="<?php echo esc_attr($data['featured_image'] ?? ''); ?>">
                    <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-media-btn" onclick="openMediaUpload('featured_image')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        <?php _e('Upload Featured Image', 'sikshya'); ?>
                    </button>
                    <p class="sikshya-help-text"><?php _e('Recommended: 1200x675px (16:9 ratio)', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-form-row">
                <label><?php _e('Course Trailer Video', 'sikshya'); ?></label>
                <div class="sikshya-media-upload">
                    <div class="sikshya-media-preview" id="trailer_video_preview">
                        <div class="sikshya-media-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span><?php _e('No video selected', 'sikshya'); ?></span>
                        </div>
                    </div>
                    <input type="hidden" name="trailer_video" id="trailer_video" value="<?php echo esc_attr($data['trailer_video'] ?? ''); ?>">
                    <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-media-btn" onclick="openMediaUpload('trailer_video')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        <?php _e('Upload Trailer Video', 'sikshya'); ?>
                    </button>
                    <p class="sikshya-help-text"><?php _e('Optional promotional video for your course', 'sikshya'); ?></p>
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
                    <h3 class="sikshya-section-title"><?php _e('Learning Outcomes', 'sikshya'); ?></h3>
                    <p class="sikshya-section-desc"><?php _e('What will students learn from this course?', 'sikshya'); ?></p>
                </div>
            </div>
            
            <div class="sikshya-repeater" id="learning-outcomes">
                <div class="sikshya-repeater-item">
                    <div class="sikshya-repeater-input">
                        <input type="text" name="learning_outcomes[]" value="<?php echo esc_attr($data['learning_outcomes'][0] ?? ''); ?>" placeholder="<?php _e('Students will be able to...', 'sikshya'); ?>">
                    </div>
                    <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('learning-outcomes', 'learning_outcomes[]', '<?php _e('Students will be able to...', 'sikshya'); ?>')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <?php _e('Add Learning Outcome', 'sikshya'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
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
        $success = true;
        
        // Save title and description to post
        if (!empty($data['title'])) {
            $post_data = [
                'ID' => $course_id,
                'post_title' => sanitize_text_field($data['title']),
                'post_content' => wp_kses_post($data['description'] ?? ''),
            ];
            
            $result = wp_update_post($post_data);
            if (is_wp_error($result)) {
                $success = false;
            }
        }
        
        // Save other fields to meta
        $fields = $this->getFields();
        foreach ($fields as $field_id => $field_config) {
            // Skip title and description as they're saved to post
            if (in_array($field_id, ['title', 'description'])) {
                continue;
            }
            
            $value = $data[$field_id] ?? '';
            $value = $this->sanitizeField($field_id, $value, $field_config);
            
            $meta_key = '_sikshya_' . $field_id;
            $result = update_post_meta($course_id, $meta_key, $value);
            
            if ($result === false) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Override load method to get title and content from post
     * 
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array
    {
        $post = get_post($course_id);
        $data = [
            'title' => $post ? $post->post_title : '',
            'description' => $post ? $post->post_content : '',
        ];
        
        // Load other fields from meta
        $fields = $this->getFields();
        foreach ($fields as $field_id => $field_config) {
            // Skip title and description as they're loaded from post
            if (in_array($field_id, ['title', 'description'])) {
                continue;
            }
            
            $meta_key = '_sikshya_' . $field_id;
            $value = get_post_meta($course_id, $meta_key, true);
            
            // Set default value if empty
            if (empty($value) && isset($field_config['default'])) {
                $value = $field_config['default'];
            }
            
            $data[$field_id] = $value;
        }
        
        return $data;
    }
}
