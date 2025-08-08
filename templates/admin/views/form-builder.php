<?php
/**
 * FormBuilder Template
 *
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-admin">
    <div class="sikshya-container">
        <!-- Form -->
        <div class="sikshya-form-wrapper">
            <!-- Form Header -->
            <div class="sikshya-form-header">
                <h1 class="sikshya-form-title"><?php echo esc_html($config['title']); ?></h1>
                <?php if (!empty($config['description'])): ?>
                    <p class="sikshya-form-description"><?php echo esc_html($config['description']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Form Body -->
            <div class="sikshya-form-body">
                <form id="<?php echo esc_attr($form_id); ?>" 
                      method="<?php echo esc_attr($config['method']); ?>" 
                      action="<?php echo esc_url($config['action']); ?>"
                      enctype="<?php echo esc_attr($config['enctype']); ?>"
                      class="<?php echo esc_attr($config['wrapper_class']); ?>">
                    
                    <?php wp_nonce_field('sikshya_form_nonce', 'sikshya_nonce'); ?>
                    <input type="hidden" name="action" value="sikshya_form_submit">
                    <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">

                    <div class="sikshya-form-layout-<?php echo esc_attr($config['layout']); ?>">
                        <?php 
                        $current_section = '';
                        foreach ($fields as $field_name => $field): 
                            // Handle sections
                            if ($field['type'] === 'section') {
                                if ($current_section !== '') {
                                    echo '</div>'; // Close previous section
                                }
                                echo '<div class="sikshya-form-section">';
                                if (!empty($field['title'])) {
                                    echo '<h3 class="sikshya-section-title">' . esc_html($field['title']) . '</h3>';
                                }
                                if (!empty($field['description'])) {
                                    echo '<div class="sikshya-section-description">' . esc_html($field['description']) . '</div>';
                                }
                                $current_section = $field_name;
                                continue;
                            }
                        ?>
                            <div class="sikshya-field-wrapper">
                                <?php if ($config['show_labels'] && !empty($field['label'])): ?>
                                    <label for="<?php echo esc_attr($field_name); ?>" class="sikshya-form-label">
                                        <?php echo esc_html($field['label']); ?>
                                        <?php if (!empty($field['required'])): ?>
                                            <span class="sikshya-required">*</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endif; ?>

                                <?php $this->renderField($field_name, $field, $data); ?>

                                <?php if ($config['show_help'] && !empty($field['help'])): ?>
                                    <div class="sikshya-form-help"><?php echo esc_html($field['help']); ?></div>
                                <?php endif; ?>

                                <?php if ($config['show_errors']): ?>
                                    <div class="sikshya-form-error" id="<?php echo esc_attr($field_name); ?>-error"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($current_section !== ''): ?>
                            </div> <!-- Close last section -->
                        <?php endif; ?>
                    </div>

                    <!-- Form Actions -->
                    <div class="sikshya-form-actions">
                        <button type="submit" class="<?php echo esc_attr($config['submit_class']); ?>">
                            <svg class="sikshya-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17,21 17,13 7,13 7,21"></polyline>
                                <polyline points="7,3 7,8 15,8"></polyline>
                            </svg>
                            <?php echo esc_html($config['submit_text']); ?>
                        </button>
                        <?php if (!empty($config['cancel_text'])): ?>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-courses'); ?>" 
                               class="<?php echo esc_attr($config['cancel_class']); ?>">
                                <svg class="sikshya-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                <?php echo esc_html($config['cancel_text']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Form submission
    $('#<?php echo esc_js($form_id); ?>').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true).html('<svg class="sikshya-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"></path></svg> <?php _e('Saving...', 'sikshya'); ?>');
        
        // Clear previous errors
        $('.sikshya-form-error').empty();
        $('.sikshya-form-field').removeClass('sikshya-error');
        
        // Submit form via AJAX
        $.post(form.attr('action'), form.serialize(), function(response) {
            if (response.success) {
                // Show success message
                const successMessage = response.data.message || '<?php _e('Form submitted successfully!', 'sikshya'); ?>';
                
                // Create success notification
                const notification = $(`
                    <div class="sikshya-notification sikshya-notification-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22,4 12,14.01 9,11.01"></polyline>
                        </svg>
                        <span>${successMessage}</span>
                    </div>
                `);
                
                // Add notification to page
                $('body').append(notification);
                
                // Auto-remove notification after 5 seconds
                setTimeout(() => {
                    notification.fadeOut(() => notification.remove());
                }, 5000);
                
                // Redirect if specified
                if (response.data.redirect) {
                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    // Reload page or update form
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            } else {
                // Show error message
                const errorMessage = response.data.message || '<?php _e('Form submission failed!', 'sikshya'); ?>';
                
                // Create error notification
                const notification = $(`
                    <div class="sikshya-notification sikshya-notification-error">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        <span>${errorMessage}</span>
                    </div>
                `);
                
                // Add notification to page
                $('body').append(notification);
                
                // Auto-remove notification after 5 seconds
                setTimeout(() => {
                    notification.fadeOut(() => notification.remove());
                }, 5000);
                
                // Show field-specific errors
                if (response.data.errors) {
                    Object.keys(response.data.errors).forEach(function(field) {
                        $(`#${field}-error`).text(response.data.errors[field]);
                        $(`[name="${field}"]`).addClass('sikshya-error');
                    });
                }
            }
        }).fail(function() {
            // Show network error notification
            const notification = $(`
                <div class="sikshya-notification sikshya-notification-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span><?php _e('Network error occurred!', 'sikshya'); ?></span>
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);
        }).always(function() {
            // Re-enable submit button
            submitBtn.prop('disabled', false).html(originalText);
        });
    });
    
    // File upload preview
    $('input[type="file"]').on('change', function() {
        const file = this.files[0];
        const preview = $(this).siblings('.sikshya-file-preview');
        
        if (file && preview.length) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.html(`<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px;">`);
                };
                reader.readAsDataURL(file);
            } else {
                preview.html(`<div class="sikshya-file-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10,9 9,9 8,9"></polyline>
                    </svg>
                    <span>${file.name}</span>
                </div>`);
            }
        } else {
            preview.empty();
        }
    });
    
    // Tags input enhancement
    $('.sikshya-tags-input').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const input = $(this);
            const value = input.val().trim();
            
            if (value) {
                // Add tag functionality here if needed
                console.log('Tag added:', value);
            }
        }
    });
});
</script>

<style>
/* Form-specific styles */
.sikshya-form-layout-vertical .sikshya-form-group {
    margin-bottom: var(--sikshya-spacing-6);
}

.sikshya-form-layout-horizontal {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: var(--sikshya-spacing-4);
    align-items: center;
}

.sikshya-form-layout-horizontal .sikshya-form-label {
    margin-bottom: 0;
}

.sikshya-required {
    color: var(--sikshya-danger);
    margin-left: var(--sikshya-spacing-1);
}

.sikshya-form-actions {
    display: flex;
    gap: var(--sikshya-spacing-4);
    margin-top: var(--sikshya-spacing-8);
    padding-top: var(--sikshya-spacing-6);
    border-top: 1px solid var(--sikshya-gray-200);
}

.sikshya-file-preview {
    margin-top: var(--sikshya-spacing-2);
    padding: var(--sikshya-spacing-3);
    background: var(--sikshya-gray-50);
    border-radius: var(--sikshya-radius-md);
    border: 1px solid var(--sikshya-gray-200);
}

.sikshya-tags-input {
    display: flex;
    flex-wrap: wrap;
    gap: var(--sikshya-spacing-2);
    padding: var(--sikshya-spacing-2);
    border: 1px solid var(--sikshya-gray-300);
    border-radius: var(--sikshya-radius-md);
    min-height: 40px;
}

.sikshya-tag {
    display: inline-flex;
    align-items: center;
    padding: var(--sikshya-spacing-1) var(--sikshya-spacing-2);
    background: var(--sikshya-primary);
    color: var(--sikshya-white);
    border-radius: var(--sikshya-radius-sm);
    font-size: var(--sikshya-font-size-xs);
}

.sikshya-tag-remove {
    margin-left: var(--sikshya-spacing-1);
    cursor: pointer;
    font-weight: bold;
}

.sikshya-tags-input input {
    border: none;
    outline: none;
    background: transparent;
    flex: 1;
    min-width: 100px;
}

@media (max-width: 768px) {
    .sikshya-form-layout-horizontal {
        grid-template-columns: 1fr;
        gap: var(--sikshya-spacing-2);
    }
    
    .sikshya-form-actions {
        flex-direction: column;
    }
}
</style>

<?php
/**
 * Render form field
 */
function renderField($field_name, $field, $data) {
    $value = $data[$field_name] ?? '';
    $field_id = $field_name;
    $field_class = 'sikshya-form-' . $field['type'];
    
    if (!empty($field['required'])) {
        $field_class .= ' sikshya-required';
    }
    
    switch ($field['type']) {
        case 'text':
        case 'email':
        case 'password':
        case 'number':
        case 'url':
            ?>
            <input type="<?php echo esc_attr($field['type']); ?>" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   class="<?php echo esc_attr($field_class); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>
                   <?php echo !empty($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : ''; ?>
                   <?php echo !empty($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : ''; ?>
                   <?php echo !empty($field['step']) ? 'step="' . esc_attr($field['step']) . '"' : ''; ?>>
            <?php
            break;
            
        case 'textarea':
            ?>
            <textarea id="<?php echo esc_attr($field_id); ?>" 
                      name="<?php echo esc_attr($field_name); ?>" 
                      class="<?php echo esc_attr($field_class); ?>"
                      <?php echo !empty($field['required']) ? 'required' : ''; ?>
                      <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>
                      <?php echo !empty($field['rows']) ? 'rows="' . esc_attr($field['rows']) . '"' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
            <?php
            break;
            
        case 'select':
            ?>
            <select id="<?php echo esc_attr($field_id); ?>" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    class="<?php echo esc_attr($field_class); ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                <?php if (!empty($field['options'])): ?>
                    <?php foreach ($field['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" 
                                <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php
            break;
            
        case 'checkbox':
            ?>
            <label class="sikshya-checkbox-label">
                <input type="checkbox" 
                       id="<?php echo esc_attr($field_id); ?>" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="1" 
                       class="<?php echo esc_attr($field_class); ?>"
                       <?php checked($value, '1'); ?>>
                <span class="sikshya-checkbox-text"><?php echo esc_html($field['label'] ?? ''); ?></span>
            </label>
            <?php
            break;
            
        case 'radio':
            if (!empty($field['options'])) {
                foreach ($field['options'] as $option_value => $option_label) {
                    ?>
                    <label class="sikshya-radio-label">
                        <input type="radio" 
                               name="<?php echo esc_attr($field_name); ?>" 
                               value="<?php echo esc_attr($option_value); ?>" 
                               class="<?php echo esc_attr($field_class); ?>"
                               <?php checked($value, $option_value); ?>>
                        <span class="sikshya-radio-text"><?php echo esc_html($option_label); ?></span>
                    </label>
                    <?php
                }
            }
            break;
            
        case 'image':
            ?>
            <input type="file" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   accept="image/*" 
                   class="<?php echo esc_attr($field_class); ?>">
            <div class="sikshya-file-preview"></div>
            <?php
            break;
            
        case 'wysiwyg':
            wp_editor($value, $field_id, [
                'textarea_name' => $field_name,
                'textarea_rows' => $field['rows'] ?? 10,
                'media_buttons' => false,
                'teeny' => true,
                'tinymce' => [
                    'height' => 300,
                ],
            ]);
            break;
            
        case 'tags':
            ?>
            <div class="sikshya-tags-input" id="<?php echo esc_attr($field_id); ?>-container">
                <input type="text" 
                       id="<?php echo esc_attr($field_id); ?>" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       placeholder="<?php echo esc_attr($field['placeholder'] ?? __('Add tags...', 'sikshya')); ?>"
                       class="<?php echo esc_attr($field_class); ?>">
            </div>
            <?php
            break;
            
        default:
            ?>
            <input type="text" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   class="<?php echo esc_attr($field_class); ?>">
            <?php
            break;
    }
}
?> 