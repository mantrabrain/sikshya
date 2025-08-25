<?php
/**
 * Abstract Tab Class for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractTab implements TabInterface
{
    /**
     * Plugin instance
     * 
     * @var \Sikshya\Core\Plugin
     */
    protected $plugin;
    
    /**
     * Constructor
     * 
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Get the tab order (default implementation)
     * 
     * @return int
     */
    public function getOrder(): int
    {
        return 10;
    }
    
    /**
     * Validate the form data (default implementation)
     * 
     * @param array $data
     * @return array Array of errors, empty if valid
     */
    public function validate(array $data): array
    {
        $errors = [];
        $fields = $this->getFields();
        
        foreach ($fields as $field_id => $field_config) {
            $value = $data[$field_id] ?? '';
            
            // Check required fields
            if (!empty($field_config['required']) && empty($value)) {
                $errors[$field_id] = sprintf(
                    __('%s is required.', 'sikshya'),
                    $field_config['label'] ?? $field_id
                );
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value)) {
                continue;
            }
            
            // Validate based on field type
            $field_errors = $this->validateField($field_id, $value, $field_config);
            if (!empty($field_errors)) {
                $errors[$field_id] = $field_errors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single field
     * 
     * @param string $field_id
     * @param mixed $value
     * @param array $field_config
     * @return string|false Error message or false if valid
     */
    protected function validateField(string $field_id, $value, array $field_config)
    {
        $field_type = $field_config['type'] ?? 'text';
        
        switch ($field_type) {
            case 'email':
                if (!is_email($value)) {
                    return __('Please enter a valid email address.', 'sikshya');
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return __('Please enter a valid URL.', 'sikshya');
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    return __('Please enter a valid number.', 'sikshya');
                }
                
                if (isset($field_config['min']) && $value < $field_config['min']) {
                    return sprintf(
                        __('Value must be at least %s.', 'sikshya'),
                        $field_config['min']
                    );
                }
                
                if (isset($field_config['max']) && $value > $field_config['max']) {
                    return sprintf(
                        __('Value must be no more than %s.', 'sikshya'),
                        $field_config['max']
                    );
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Save the form data (default implementation)
     * 
     * @param array $data
     * @param int $course_id
     * @return bool
     */
    public function save(array $data, int $course_id): bool
    {
        $fields = $this->getFields();
        $success = true;
        
        foreach ($fields as $field_id => $field_config) {
            $value = $data[$field_id] ?? '';
            
            // Sanitize the value
            $value = $this->sanitizeField($field_id, $value, $field_config);
            
            // Save to post meta with _sikshya_ prefix
            $meta_key = '_sikshya_' . $field_id;
            $result = update_post_meta($course_id, $meta_key, $value);
            
            if ($result === false) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Sanitize a field value
     * 
     * @param string $field_id
     * @param mixed $value
     * @param array $field_config
     * @return mixed
     */
    protected function sanitizeField(string $field_id, $value, array $field_config)
    {
        $field_type = $field_config['type'] ?? 'text';
        
        switch ($field_type) {
            case 'textarea':
                return wp_kses_post($value);
                
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
                
            case 'checkbox':
                return $value ? '1' : '0';
                
            case 'select':
            case 'radio':
                $options = $field_config['options'] ?? [];
                return in_array($value, array_keys($options)) ? $value : '';
                
            case 'date':
                return sanitize_text_field($value);
                
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Load existing data (default implementation)
     * 
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array
    {
        $fields = $this->getFields();
        $data = [];
        
        foreach ($fields as $field_id => $field_config) {
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
    
    /**
     * Render the HTML for this tab (default implementation)
     * 
     * @param array $data
     * @param string $active_tab
     * @return string
     */
    public function render(array $data = [], string $active_tab = ''): string
    {
        $tab_id = $this->getId();
        $is_active = ($active_tab === $tab_id);
        
        ob_start();
        ?>
        <!-- <?php echo esc_html($this->getTitle()); ?> Tab -->
        <div class="sikshya-tab-content <?php echo $is_active ? 'active' : ''; ?>" id="<?php echo esc_attr($tab_id); ?>">
            <?php echo $this->renderContent($data); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the tab content (to be implemented by child classes)
     * 
     * @param array $data
     * @return string
     */
    abstract protected function renderContent(array $data): string;
    
    /**
     * Render a form field
     * 
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @return string
     */
    protected function renderField(string $field_id, array $field_config, $value = ''): string
    {
        $field_type = $field_config['type'] ?? 'text';
        $label = $field_config['label'] ?? $field_id;
        $placeholder = $field_config['placeholder'] ?? '';
        $required = !empty($field_config['required']) ? 'required' : '';
        $description = $field_config['description'] ?? '';
        
        $field_html = '';
        
        switch ($field_type) {
            case 'textarea':
                $field_html = sprintf(
                    '<textarea name="%s" placeholder="%s" %s>%s</textarea>',
                    esc_attr($field_id),
                    esc_attr($placeholder),
                    $required,
                    esc_textarea($value)
                );
                break;
                
            case 'select':
                $field_html = '<select name="' . esc_attr($field_id) . '" ' . $required . '>';
                $options = $field_config['options'] ?? [];
                foreach ($options as $option_value => $option_label) {
                    $selected = ($value == $option_value) ? 'selected' : '';
                    $field_html .= sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option_value),
                        $selected,
                        esc_html($option_label)
                    );
                }
                $field_html .= '</select>';
                break;
                
            case 'checkbox':
                $checked = $value ? 'checked' : '';
                $field_html = sprintf(
                    '<input type="checkbox" name="%s" value="1" %s %s>',
                    esc_attr($field_id),
                    $checked,
                    $required
                );
                break;
                
            case 'number':
                $min = isset($field_config['min']) ? 'min="' . esc_attr($field_config['min']) . '"' : '';
                $max = isset($field_config['max']) ? 'max="' . esc_attr($field_config['max']) . '"' : '';
                $step = isset($field_config['step']) ? 'step="' . esc_attr($field_config['step']) . '"' : '';
                
                $field_html = sprintf(
                    '<input type="number" name="%s" value="%s" placeholder="%s" %s %s %s %s>',
                    esc_attr($field_id),
                    esc_attr($value),
                    esc_attr($placeholder),
                    $min,
                    $max,
                    $step,
                    $required
                );
                break;
                
            case 'date':
                $field_html = sprintf(
                    '<input type="date" name="%s" value="%s" %s>',
                    esc_attr($field_id),
                    esc_attr($value),
                    $required
                );
                break;
                
            default:
                $field_html = sprintf(
                    '<input type="%s" name="%s" value="%s" placeholder="%s" %s>',
                    esc_attr($field_type),
                    esc_attr($field_id),
                    esc_attr($value),
                    esc_attr($placeholder),
                    $required
                );
        }
        
        return sprintf(
            '<div class="sikshya-form-row">
                <label>%s %s</label>
                %s
            </div>',
            esc_html($label),
            $required ? '<span class="required">*</span>' : '',
            $field_html
        );
    }
}
