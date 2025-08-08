<?php

namespace Sikshya\Admin\Views;

/**
 * Reusable FormBuilder Component
 *
 * @package Sikshya\Admin\Views
 */
class FormBuilder extends BaseView
{
    /**
     * Form configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Form fields
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * Form data
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     * @param array $config
     */
    public function __construct(\Sikshya\Core\Plugin $plugin, array $config = [])
    {
        parent::__construct($plugin);
        $this->config = $this->getDefaultConfig();
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Add field
     *
     * @param string $name
     * @param array $field
     * @return $this
     */
    public function addField(string $name, array $field): self
    {
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Add fields
     *
     * @param array $fields
     * @return $this
     */
    public function addFields(array $fields): self
    {
        foreach ($fields as $name => $field) {
            $this->addField($name, $field);
        }
        return $this;
    }

    /**
     * Set form data
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set form configuration
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Render the form
     *
     * @return string
     */
    public function renderForm(): string
    {
        $this->enqueueAssets();
        
        return $this->render('form-builder', [
            'config' => $this->config,
            'fields' => $this->fields,
            'data' => $this->data,
            'form_id' => $this->config['id'] ?? 'sikshya-form',
        ]);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'id' => 'sikshya-form',
            'title' => '',
            'description' => '',
            'method' => 'POST',
            'action' => '',
            'enctype' => 'multipart/form-data',
            'layout' => 'vertical', // vertical, horizontal, inline
            'columns' => 1,
            'show_labels' => true,
            'show_help' => true,
            'show_errors' => true,
            'submit_text' => __('Save', 'sikshya'),
            'cancel_text' => __('Cancel', 'sikshya'),
            'submit_class' => 'button button-primary',
            'cancel_class' => 'button button-secondary',
            'wrapper_class' => 'sikshya-form-wrapper',
            'field_wrapper_class' => 'sikshya-field-wrapper',
        ];
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-form-builder');
        wp_enqueue_script('sikshya-form-builder');
    }

    /**
     * Render a form field
     *
     * @param string $field_name
     * @param array $field
     * @param array $data
     * @return void
     */
    public function renderField(string $field_name, array $field, array $data = []): void
    {
        $field_type = $field['type'] ?? 'text';
        $field_value = $data[$field_name] ?? $field['value'] ?? '';
        $field_id = $field['id'] ?? $field_name;
        $field_class = $field['class'] ?? 'sikshya-form-field';
        $field_required = !empty($field['required']) ? 'required' : '';
        $field_placeholder = $field['placeholder'] ?? '';

        switch ($field_type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
            case 'tel':
                $field_min = isset($field['min']) ? "min=\"{$field['min']}\"" : '';
                $field_max = isset($field['max']) ? "max=\"{$field['max']}\"" : '';
                $field_step = isset($field['step']) ? "step=\"{$field['step']}\"" : '';
                
                echo "<input type=\"{$field_type}\" id=\"{$field_id}\" name=\"{$field_name}\" 
                      value=\"" . esc_attr($field_value) . "\" class=\"{$field_class}\" 
                      {$field_required} placeholder=\"{$field_placeholder}\" {$field_min} {$field_max} {$field_step}>";
                break;

            case 'textarea':
                $field_rows = $field['rows'] ?? 4;
                echo "<textarea id=\"{$field_id}\" name=\"{$field_name}\" class=\"{$field_class}\" 
                      {$field_required} placeholder=\"{$field_placeholder}\" rows=\"{$field_rows}\">" . 
                      esc_textarea($field_value) . "</textarea>";
                break;

            case 'select':
                echo "<select id=\"{$field_id}\" name=\"{$field_name}\" class=\"{$field_class}\" {$field_required}>";
                if (isset($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        $selected = ($value == $field_value) ? 'selected' : '';
                        echo "<option value=\"" . esc_attr($value) . "\" {$selected}>" . esc_html($label) . "</option>";
                    }
                }
                echo "</select>";
                break;

            case 'multiselect':
                echo "<select id=\"{$field_id}\" name=\"{$field_name}[]\" class=\"{$field_class}\" {$field_required} multiple>";
                if (isset($field['options'])) {
                    $selected_values = is_array($field_value) ? $field_value : [];
                    foreach ($field['options'] as $value => $label) {
                        $selected = in_array($value, $selected_values) ? 'selected' : '';
                        echo "<option value=\"" . esc_attr($value) . "\" {$selected}>" . esc_html($label) . "</option>";
                    }
                }
                echo "</select>";
                break;

            case 'checkbox':
                $checked = $field_value ? 'checked' : '';
                echo "<input type=\"checkbox\" id=\"{$field_id}\" name=\"{$field_name}\" 
                      value=\"1\" class=\"{$field_class}\" {$field_required} {$checked}>";
                break;

            case 'radio':
                if (isset($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        $checked = ($value == $field_value) ? 'checked' : '';
                        echo "<label class=\"sikshya-radio-label\">
                              <input type=\"radio\" name=\"{$field_name}\" value=\"" . esc_attr($value) . "\" 
                              {$field_required} {$checked}> " . esc_html($label) . "</label>";
                    }
                }
                break;

            case 'file':
            case 'image':
                echo "<input type=\"file\" id=\"{$field_id}\" name=\"{$field_name}\" 
                      class=\"{$field_class}\" {$field_required} accept=\"image/*\">
                      <div class=\"sikshya-file-preview\"></div>";
                break;

            case 'wysiwyg':
                wp_editor($field_value, $field_id, [
                    'textarea_name' => $field_name,
                    'textarea_rows' => $field['rows'] ?? 10,
                    'media_buttons' => true,
                    'teeny' => false,
                    'tinymce' => true,
                ]);
                break;

            case 'tags':
                echo "<input type=\"text\" id=\"{$field_id}\" name=\"{$field_name}\" 
                      value=\"" . esc_attr($field_value) . "\" class=\"{$field_class} sikshya-tags-input\" 
                      {$field_required} placeholder=\"{$field_placeholder}\">";
                break;

            case 'button':
                echo "<button type=\"button\" id=\"{$field_id}\" class=\"{$field_class}\">" . 
                     esc_html($field['value'] ?? '') . "</button>";
                break;

            case 'section':
                echo "<div class=\"sikshya-form-section\">
                      <h3 class=\"sikshya-section-title\">" . esc_html($field['title'] ?? '') . "</h3>
                      <div class=\"sikshya-section-description\">" . esc_html($field['description'] ?? '') . "</div>
                      </div>";
                break;

            default:
                echo "<input type=\"text\" id=\"{$field_id}\" name=\"{$field_name}\" 
                      value=\"" . esc_attr($field_value) . "\" class=\"{$field_class}\" 
                      {$field_required} placeholder=\"{$field_placeholder}\">";
                break;
        }
    }
} 