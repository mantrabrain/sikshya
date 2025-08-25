<?php
/**
 * Course Information Tab for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;
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
                'type' => 'media_upload',
                'label' => __('Course Featured Image', 'sikshya'),
                'description' => __('Recommended: 1200x675px (16:9 ratio)', 'sikshya'),
                'media_type' => 'image',
                'layout' => 'media_row',
            ],
            'video_url' => [
                'type' => 'media_upload',
                'label' => __('Course Trailer Video', 'sikshya'),
                'description' => __('Optional promotional video for your course', 'sikshya'),
                'media_type' => 'video',
                'layout' => 'media_row',
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
        error_log('Sikshya CourseInfoTab: Rendering content with data: ' . print_r($data, true));
        error_log('Sikshya CourseInfoTab: Title from data: ' . ($data['title'] ?? 'NOT SET'));
        error_log('Sikshya CourseInfoTab: Description from data: ' . ($data['description'] ?? 'NOT SET'));
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
                    <?php if (empty($data['slug']) || !isset($data['id'])): ?>
                        <!-- New course - slug is auto-generated and editable -->
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
                    <?php else: ?>
                        <!-- Existing course - slug is read-only unless explicitly edited -->
                        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" id="edit-permalink-btn" onclick="togglePermalinkEdit()" title="<?php _e('Click to edit permalink', 'sikshya'); ?>">
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
                    <?php endif; ?>
                </div>
                <p class="sikshya-help-text">
                    <?php if (empty($data['slug']) || !isset($data['id'])): ?>
                        <?php _e('The URL-friendly version of your course title. Auto-generated from the title.', 'sikshya'); ?>
                    <?php else: ?>
                        <?php _e('The URL-friendly version of your course title. Click Edit to modify.', 'sikshya'); ?>
                    <?php endif; ?>
                </p>
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
            
            <div class="sikshya-media-row">
                <?php foreach ($this->getFields() as $field_id => $field_config): ?>
                    <?php if (in_array($field_id, ['featured_image', 'video_url'])): ?>
                        <div class="sikshya-form-row">
                            <?php echo $this->renderField($field_id, $field_config, $data[$field_id] ?? ''); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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
        error_log('Sikshya CourseInfoTab: Starting save for course ID: ' . $course_id);
        error_log('Sikshya CourseInfoTab: Data to save: ' . print_r($data, true));
        
        // Use the new Course model
        $course = Course::find($course_id);
        
        if (!$course || !$course->exists()) {
            error_log('Sikshya CourseInfoTab: Course not found or does not exist for ID: ' . $course_id);
            return false;
        }
        
        $success = true;
        $errors = [];
        
        // Prepare update data
        $update_data = [];
        
        // Save title, description, and slug to post
        if (!empty($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
            error_log('Sikshya CourseInfoTab: Title to save: ' . $update_data['title']);
        }
        
        if (!empty($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
            error_log('Sikshya CourseInfoTab: Description to save: ' . substr($update_data['description'], 0, 100) . '...');
        }
        
        if (!empty($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
            error_log('Sikshya CourseInfoTab: Slug to save: ' . $update_data['slug']);
        }
        
        // Update post data
        if (!empty($update_data)) {
            error_log('Sikshya CourseInfoTab: Updating post data: ' . print_r($update_data, true));
            $result = $course->update($update_data);
            if (!$result) {
                error_log('Sikshya CourseInfoTab: Failed to update post data');
                $success = false;
                $errors[] = 'Failed to update post data (title, description, slug)';
            } else {
                error_log('Sikshya CourseInfoTab: Post data updated successfully');
            }
        }
        
        // Save other fields to meta using the Course model's magic setters
        $fields = $this->getFields();
        foreach ($fields as $field_id => $field_config) {
            // Skip title, description, and slug as they're saved to post
            if (in_array($field_id, ['title', 'description', 'slug'])) {
                continue;
            }
            
            $value = $data[$field_id] ?? '';
            $value = $this->sanitizeField($field_id, $value, $field_config);
            
            error_log('Sikshya CourseInfoTab: Saving field ' . $field_id . ' with value: ' . $value);
            
            // Use the Course model's magic setter for meta fields
            $method_name = 'set' . ucfirst($field_id);
            $result = $course->$method_name($value); // This will save to meta table
            
            if (!$result) {
                error_log('Sikshya CourseInfoTab: Failed to save field: ' . $field_id);
                $success = false;
                $errors[] = 'Failed to save field: ' . $field_id;
            } else {
                error_log('Sikshya CourseInfoTab: Successfully saved field: ' . $field_id);
            }
        }
        
        if (!$success) {
            error_log('Sikshya CourseInfoTab: Save failed with errors: ' . implode(', ', $errors));
        } else {
            error_log('Sikshya CourseInfoTab: Save completed successfully');
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
        error_log('Sikshya CourseInfoTab: Loading data for course ID: ' . $course_id);
        
        // Use the new Course model
        $course = Course::find($course_id);
        
        if (!$course || !$course->exists()) {
            error_log('Sikshya CourseInfoTab: Course not found for ID: ' . $course_id);
            return [];
        }
        
        // Course found successfully
        
        $data = [
            'title' => $course->getTitle(), // From post table
            'description' => $course->getDescription(), // From post table
            'slug' => $course->getSlug(), // From post table
            'id' => $course->getId(), // From post table
        ];
        
        error_log('Sikshya CourseInfoTab: Post data loaded - Title: ' . $data['title'] . ', Description: ' . substr($data['description'], 0, 50) . '...');
        
        // Load other fields from meta using the Course model's magic getters
        $fields = $this->getFields();
        foreach ($fields as $field_id => $field_config) {
            // Skip title and description as they're loaded from post
            if (in_array($field_id, ['title', 'description'])) {
                continue;
            }
            
            // Use the Course model's magic getter for meta fields
            $method_name = 'get' . ucfirst($field_id);
            $value = $course->$method_name(); // This will get from meta table
            
            // Set default value if empty
            if (empty($value) && isset($field_config['default'])) {
                $value = $field_config['default'];
            }
            
            $data[$field_id] = $value;
        }
        
        error_log('Sikshya CourseInfoTab: Final data array: ' . print_r($data, true));
        return $data;
    }
}
