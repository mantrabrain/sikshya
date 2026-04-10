<?php

/**
 * Abstract Tab Class for Course Builder
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Core;

use Sikshya\Constants\PostTypes;

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
     * Data passed to renderSections (for permalink helpers, etc.).
     *
     * @var array<string,mixed>
     */
    protected $renderData = [];

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
     * Post meta persistence (repository — no direct get_post_meta in tabs).
     */
    protected function postMetaRepository(): ?\Sikshya\Database\Repositories\PostMetaRepository
    {
        $repo = $this->plugin->getService('postMetaRepository');
        return $repo instanceof \Sikshya\Database\Repositories\PostMetaRepository ? $repo : null;
    }

    /**
     * Course post lookups for builder tabs.
     */
    protected function courseRepository(): ?\Sikshya\Database\Repositories\CourseRepository
    {
        $repo = $this->plugin->getService('courseRepository');
        return $repo instanceof \Sikshya\Database\Repositories\CourseRepository ? $repo : null;
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
     * Whether field dependencies (show-if) are satisfied for validation.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $field_config
     */
    protected function fieldDependenciesMet(array $data, array $field_config): bool
    {
        if (!empty($field_config['depends_all']) && is_array($field_config['depends_all'])) {
            foreach ($field_config['depends_all'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $on = (string) ($rule['on'] ?? '');
                if ($on === '') {
                    continue;
                }
                $raw = $data[$on] ?? '';
                if (array_key_exists('value', $rule)) {
                    if ((string) $raw !== (string) $rule['value']) {
                        return false;
                    }
                } elseif (!($raw === '1' || $raw === 1 || $raw === true)) {
                    return false;
                }
            }

            return true;
        }

        if (empty($field_config['depends_on'])) {
            return true;
        }

        $controller = (string) $field_config['depends_on'];
        $raw = $data[$controller] ?? '';

        if (array_key_exists('depends_value', $field_config)) {
            $expected = $field_config['depends_value'];
            if (is_array($expected)) {
                return in_array((string) $raw, array_map('strval', $expected), true);
            }

            return (string) $raw === (string) $expected;
        }

        // Default: treat as checkbox / truthy controller.
        return $raw === '1' || $raw === 1 || $raw === true;
    }

    /**
     * HTML attributes for conditional display (course builder JS).
     *
     * @param array<string,mixed> $field_config
     */
    protected function buildDependencyDataAttributes(array $field_config): string
    {
        if (!empty($field_config['depends_all']) && is_array($field_config['depends_all'])) {
            return ' data-sikshya-depends-all="' . esc_attr(wp_json_encode($field_config['depends_all'])) . '"';
        }

        if (empty($field_config['depends_on'])) {
            return '';
        }

        $on = esc_attr((string) $field_config['depends_on']);
        $attrs = ' data-sikshya-depends-on="' . $on . '"';

        if (array_key_exists('depends_value', $field_config)) {
            $v = $field_config['depends_value'];
            if (is_array($v)) {
                $attrs .= ' data-sikshya-depends-values="' . esc_attr(wp_json_encode(array_map('strval', $v))) . '"';
            } else {
                $attrs .= ' data-sikshya-depends-value="' . esc_attr((string) $v) . '"';
            }
        }

        return $attrs;
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

        foreach ($fields as $section_id => $section_config) {
            $section_fields = $section_config['fields'] ?? [];

            foreach ($section_fields as $field_id => $field_config) {
                if (!$this->fieldDependenciesMet($data, $field_config)) {
                    continue;
                }

                $value = $data[$field_id] ?? '';

                $field_type = $field_config['type'] ?? 'text';

                if ($field_type === 'repeater_group') {
                    $gerrs = $this->validateRepeaterGroupField($field_id, $value, $field_config);
                    if (!empty($gerrs)) {
                        $errors[$field_id] = $gerrs;
                    }
                    continue;
                }

                if ($field_type === 'user_select' && !empty($field_config['multiple'])) {
                    if (!empty($field_config['required']) && (!is_array($value) || count(array_filter($value)) === 0)) {
                        $errors[$field_id] = sprintf(
                            /* translators: %s: field label */
                            __('%s is required.', 'sikshya'),
                            $field_config['label'] ?? $field_id
                        );
                    }
                    continue;
                }

                // Check required fields
                if (!empty($field_config['required']) && ($value === '' || $value === null)) {
                    $errors[$field_id] = sprintf(
                        /* translators: %s: field label */
                        __('%s is required.', 'sikshya'),
                        $field_config['label'] ?? $field_id
                    );
                    continue;
                }

                // Skip validation if field is empty and not required
                if ($value === '' || $value === null) {
                    continue;
                }

                // Validate based on field type
                $field_errors = $this->validateField($field_id, $value, $field_config);
                if (!empty($field_errors)) {
                    $errors[$field_id] = $field_errors;
                }
            }
        }

        return $errors;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $field_config
     * @return string|array<string,string>
     */
    protected function validateRepeaterGroupField(string $field_id, $value, array $field_config)
    {
        if (!is_array($value)) {
            return '';
        }

        $subfields = $field_config['subfields'] ?? [];
        if ($subfields === []) {
            return '';
        }

        $rowIndex = 0;
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($subfields as $sub_id => $sub_cfg) {
                $sv = $row[$sub_id] ?? '';
                if (!empty($sub_cfg['required']) && ($sv === '' || $sv === null)) {
                    return sprintf(
                        /* translators: 1: section label, 2: row number */
                        __('Row %2$d: %1$s is required.', 'sikshya'),
                        $sub_cfg['label'] ?? $sub_id,
                        $rowIndex + 1
                    );
                }
                if ($sv !== '' && $sv !== null) {
                    $err = $this->validateField($field_id . '_' . $sub_id, $sv, array_merge($sub_cfg, ['type' => $sub_cfg['type'] ?? 'text']));
                    if (!empty($err)) {
                        return (string) $err;
                    }
                }
            }
            ++$rowIndex;
        }

        return '';
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
        // Check if course_id is valid
        if ($course_id <= 0) {
            return false;
        }

        $courses = $this->courseRepository();
        $meta_repo = $this->postMetaRepository();

        if ($courses && !$courses->isCourse($course_id)) {
            return false;
        }

        if (!$courses) {
            $course = get_post($course_id);
            if (!$course || $course->post_type !== PostTypes::COURSE) {
                return false;
            }
        }

        $fields = $this->getFields();
        $success = true;

        // Save structured fields (sections)
        foreach ($fields as $section_id => $section_config) {
            $section_fields = $section_config['fields'] ?? [];

            foreach ($section_fields as $field_id => $field_config) {
                $value = $data[$field_id] ?? '';

                // Sanitize the value using field configuration
                $sanitized_value = $this->sanitizeField($field_id, $value, $field_config);

                // Save to post meta with _sikshya_ prefix (via repository)
                $meta_key = '_sikshya_' . $field_id;

                if ($meta_repo) {
                    $result = $meta_repo->update($course_id, $meta_key, $sanitized_value);
                } else {
                    $result = update_post_meta($course_id, $meta_key, $sanitized_value);
                }

                if ($result === false && !current_user_can('edit_post', $course_id)) {
                    $success = false;
                }
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

            case 'password':
                return sanitize_text_field((string) $value);

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

            case 'repeater':
                if (!is_array($value)) {
                    return [];
                }

                return array_values(
                    array_filter(
                        array_map(
                            static function ($v) {
                                return sanitize_text_field((string) $v);
                            },
                            $value
                        )
                    )
                );

            case 'repeater_group':
                return $this->sanitizeRepeaterGroup($value, $field_config);

            case 'user_select':
                if (!empty($field_config['multiple'])) {
                    if (!is_array($value)) {
                        return [];
                    }
                    $ids = array_map('intval', $value);

                    return array_values(array_unique(array_filter($ids)));
                }

                return (int) $value;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $field_config
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeRepeaterGroup($value, array $field_config): array
    {
        if (!is_array($value)) {
            return [];
        }

        $subfields = $field_config['subfields'] ?? [];
        $out = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($subfields as $sub_id => $sub_cfg) {
                $sv = $row[$sub_id] ?? '';
                $clean[$sub_id] = $this->sanitizeSubfieldValue($sv, $sub_cfg);
            }
            if ($this->repeaterGroupRowIsEmpty($clean, $subfields)) {
                continue;
            }
            $out[] = $clean;
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $sub_cfg
     * @return mixed
     */
    protected function sanitizeSubfieldValue($value, array $sub_cfg)
    {
        $t = $sub_cfg['type'] ?? 'text';
        switch ($t) {
            case 'textarea':
                return wp_kses_post((string) $value);
            case 'url':
                return esc_url_raw((string) $value);
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $subfields
     */
    protected function repeaterGroupRowIsEmpty(array $row, array $subfields): bool
    {
        foreach (array_keys($subfields) as $key) {
            $v = $row[$key] ?? '';
            if ($v !== '' && $v !== null && $v !== []) {
                return false;
            }
        }

        return true;
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
        $meta_repo = $this->postMetaRepository();

        // Load structured fields (sections)
        foreach ($fields as $section_id => $section_config) {
            $section_fields = $section_config['fields'] ?? [];

            foreach ($section_fields as $field_id => $field_config) {
                $meta_key = '_sikshya_' . $field_id;
                $value = $meta_repo
                    ? $meta_repo->get($course_id, $meta_key, true)
                    : get_post_meta($course_id, $meta_key, true);

                if (($field_config['type'] ?? '') === 'repeater_group' && is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    }
                }

                // Set default value if empty
                if (empty($value) && isset($field_config['default'])) {
                    $value = $field_config['default'];
                }

                if (($field_config['type'] ?? '') === 'user_select' && !empty($field_config['multiple']) && !is_array($value)) {
                    $value = $value !== '' && $value !== null ? [(int) $value] : [];
                }

                $data[$field_id] = $value;
            }
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
     * Render sections dynamically based on field definitions
     *
     * @param array $data
     * @return string
     */
    protected function renderSections(array $data): string
    {
        $this->renderData = $data;
        ob_start();

        $fields = $this->getFields();

        foreach ($fields as $section_id => $section_config) {
            $section = $section_config['section'] ?? [];
            $section_fields = $section_config['fields'] ?? [];

            // Render section header
            ?>
            <div class="sikshya-section sikshya-section-modern">
                <div class="sikshya-section-header">
                    <div class="sikshya-section-icon">
                        <?php
                        $icon = $section['icon'] ?? '';
                        if (!empty($icon)) {
                            // Allow SVG elements and attributes for icons
                            $allowed_html = array(
                                'svg' => array(
                                    'width' => array(),
                                    'height' => array(),
                                    'viewbox' => array(),
                                    'fill' => array(),
                                    'stroke' => array(),
                                    'stroke-width' => array(),
                                    'xmlns' => array(),
                                ),
                                'path' => array(
                                    'stroke-linecap' => array(),
                                    'stroke-linejoin' => array(),
                                    'd' => array(),
                                    'fill' => array(),
                                    'stroke' => array(),
                                    'stroke-width' => array(),
                                ),
                                'circle' => array(
                                    'cx' => array(),
                                    'cy' => array(),
                                    'r' => array(),
                                    'fill' => array(),
                                    'stroke' => array(),
                                    'stroke-width' => array(),
                                ),
                                'rect' => array(
                                    'x' => array(),
                                    'y' => array(),
                                    'width' => array(),
                                    'height' => array(),
                                    'fill' => array(),
                                    'stroke' => array(),
                                    'stroke-width' => array(),
                                ),
                            );
                            echo wp_kses($icon, $allowed_html);
                        } else {
                            // Fallback icon if none provided
                            echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>';
                        }
                        ?>
                    </div>
                    <div class="sikshya-section-content">
                        <h3 class="sikshya-section-title"><?php echo esc_html($section['title'] ?? ''); ?></h3>
                        <p class="sikshya-section-desc"><?php echo esc_html($section['description'] ?? ''); ?></p>
                    </div>
                </div>
                
                <?php
                // Render fields in order, grouping consecutive fields with the same layout
                $current_layout = null;
                $current_group = [];

                foreach ($section_fields as $field_id => $field_config) {
                    $layout = $field_config['layout'] ?? 'single';

                    // If layout changed, render the current group
                    if ($current_layout !== null && $current_layout !== $layout) {
                        $this->renderFieldGroup($current_group, $current_layout, $data);
                        $current_group = [];
                    }

                    $current_layout = $layout;
                    $current_group[$field_id] = $field_config;
                }

                // Render the last group
                if (!empty($current_group)) {
                    $this->renderFieldGroup($current_group, $current_layout, $data);
                }
                ?>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render a group of fields with the same layout
     *
     * @param array $fields
     * @param string $layout
     * @param array $data
     */
    protected function renderFieldGroup(array $fields, string $layout, array $data): void
    {
        if ($layout === 'single') {
            // Render single fields normally
            foreach ($fields as $field_id => $field_config) {
                $field_value = $data[$field_id] ?? '';
                $dep = $this->buildDependencyDataAttributes($field_config);
                echo '<div class="sikshya-form-row sikshya-form-row--field" data-sikshya-field="' . esc_attr($field_id) . '"' . $dep . '>';
                echo $this->renderField($field_id, $field_config, $field_value);
                echo '</div>';
            }
        } else {
            // Render multi-column fields
            echo '<div class="sikshya-multi-column-row sikshya-' . esc_attr($layout) . '">';

            foreach ($fields as $field_id => $field_config) {
                $field_value = $data[$field_id] ?? '';
                $dep = $this->buildDependencyDataAttributes($field_config);
                echo '<div class="sikshya-form-row sikshya-form-row--field" data-sikshya-field="' . esc_attr($field_id) . '"' . $dep . '>';
                echo $this->renderField($field_id, $field_config, $field_value);
                echo '</div>';
            }

            echo '</div>';
        }
    }

    /**
     * Get column count from layout name
     *
     * @param string $layout
     * @return int
     */
    protected function getColumnCountFromLayout(string $layout): int
    {
        switch ($layout) {
            case 'two_column':
                return 2;
            case 'three_column':
                return 3;
            case 'four_column':
                return 4;
            case 'media_row':
                return 2; // Media fields are typically 2 columns
            default:
                return 1;
        }
    }

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
                    '<label class="sikshya-checkbox-label">
                        <input type="checkbox" name="%s" value="1" %s %s>
                        %s
                    </label>',
                    esc_attr($field_id),
                    $checked,
                    $required,
                    esc_html($label)
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

            case 'media_upload':
                $media_type = $field_config['media_type'] ?? 'image';
                $field_html = $this->renderMediaUploadField($field_id, $field_config, $value, $media_type);
                break;

            case 'repeater':
                $field_html = $this->renderRepeaterField($field_id, $field_config, $value);
                break;

            case 'repeater_group':
                $field_html = $this->renderRepeaterGroupField($field_id, $field_config, $value);
                break;

            case 'user_select':
                $field_html = $this->renderUserSelectField($field_id, $field_config, $value);
                break;

            case 'permalink':
                $field_html = $this->renderPermalinkField($field_id, $field_config, $value);
                break;
            case 'curriculum_builder':
                $field_html = $this->renderCurriculumBuilderField($field_id, $field_config, $value);
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

        // For checkbox fields, the label is already included in the field_html
        if ($field_type === 'checkbox') {
            $description_html = !empty($description) ? sprintf('<p class="sikshya-help-text">%s</p>', esc_html($description)) : '';
            return sprintf(
                '<div class="sikshya-form-row">
                    %s
                    %s
                </div>',
                $field_html,
                $description_html
            );
        }

        // For curriculum builder fields, the label is already included in the field_html
        if ($field_type === 'curriculum_builder') {
            $description_html = !empty($description) ? sprintf('<p class="sikshya-help-text">%s</p>', esc_html($description)) : '';
            return sprintf(
                '<div class="sikshya-form-row">
                    %s
                    %s
                </div>',
                $field_html,
                $description_html
            );
        }

        return sprintf(
            '<div class="sikshya-form-row">
                <label>%s %s</label>
                %s
                %s
            </div>',
            esc_html($label),
            $required ? '<span class="required">*</span>' : '',
            $field_html,
            !empty($description) ? sprintf('<p class="sikshya-help-text">%s</p>', esc_html($description)) : ''
        );
    }

    /**
     * Render a media upload field
     *
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @param string $media_type
     * @return string
     */
    protected function renderMediaUploadField(string $field_id, array $field_config, $value, string $media_type): string
    {
        $label = $field_config['label'] ?? $field_id;
        $description = $field_config['description'] ?? '';
        $required = !empty($field_config['required']) ? 'required' : '';

        // Determine icon and placeholder text based on media type
        if ($media_type === 'video') {
            $icon = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>';
            $placeholder_text = __('No video selected', 'sikshya');
            $button_text = __('Upload Trailer Video', 'sikshya');
        } else {
            $icon = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>';
            $placeholder_text = __('No image selected', 'sikshya');
            $button_text = __('Upload Featured Image', 'sikshya');
        }

        ob_start();
        ?>
        <div class="sikshya-media-upload">
            <div class="sikshya-media-preview" id="<?php echo esc_attr($field_id); ?>_preview">
                <div class="sikshya-media-placeholder">
                                                <?php echo wp_kses_post($icon); ?>
                    <span><?php echo esc_html($placeholder_text); ?></span>
                </div>
            </div>
            <input type="hidden" name="<?php echo esc_attr($field_id); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>">
            <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-media-btn" onclick="openMediaUpload('<?php echo esc_attr($field_id); ?>')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                <?php echo esc_html($button_text); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a repeater field
     *
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @return string
     */
    protected function renderRepeaterField(string $field_id, array $field_config, $value): string
    {
        $label = $field_config['label'] ?? $field_id;
        $placeholder = $field_config['placeholder'] ?? '';
        $add_button_text = $field_config['add_button_text'] ?? __('Add Item', 'sikshya');

        // Ensure value is an array
        if (!is_array($value)) {
            $value = [$value];
        }

        // If empty, provide at least one item
        if (empty($value)) {
            $value = [''];
        }

        ob_start();
        ?>
        <div class="sikshya-repeater" id="<?php echo esc_attr($field_id); ?>">
            <?php foreach ($value as $index => $item_value) : ?>
                <div class="sikshya-repeater-item">
                    <div class="sikshya-repeater-input">
                        <input type="text" name="<?php echo esc_attr($field_id); ?>[]" value="<?php echo esc_attr($item_value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
                    </div>
                    <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('<?php echo esc_attr($field_id); ?>', '<?php echo esc_attr($field_id); ?>[]', '<?php echo esc_attr($placeholder); ?>')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <?php echo esc_html($add_button_text); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Repeater with named subfields (FAQ, resources, etc.).
     *
     * @param array<string,mixed> $field_config
     * @param mixed               $value
     */
    protected function renderRepeaterGroupField(string $field_id, array $field_config, $value): string
    {
        $subfields = $field_config['subfields'] ?? [];
        $add_button_text = $field_config['add_button_text'] ?? __('Add row', 'sikshya');

        $rows = [];
        if (is_array($value) && $value !== []) {
            $rows = $value;
        } else {
            $rows = [[]];
        }

        ob_start();
        ?>
        <div class="sikshya-repeater-group" id="<?php echo esc_attr($field_id); ?>_group" data-field-id="<?php echo esc_attr($field_id); ?>">
            <?php foreach ($rows as $index => $row) : ?>
                <?php
                if (!is_array($row)) {
                    $row = [];
                }
                ?>
                <div class="sikshya-repeater-group-row">
                    <?php foreach ($subfields as $sub_id => $sub_cfg) : ?>
                        <?php
                        $sv = $row[$sub_id] ?? '';
                        $ph = $sub_cfg['placeholder'] ?? '';
                        $slabel = $sub_cfg['label'] ?? $sub_id;
                        $stype = $sub_cfg['type'] ?? 'text';
                        $name = $field_id . '[' . (int) $index . '][' . $sub_id . ']';
                        ?>
                        <div class="sikshya-repeater-group-cell">
                            <label class="sikshya-sublabel"><?php echo esc_html($slabel); ?></label>
                            <?php if ($stype === 'textarea') : ?>
                                <textarea name="<?php echo esc_attr($name); ?>" rows="3" placeholder="<?php echo esc_attr($ph); ?>"><?php echo esc_textarea((string) $sv); ?></textarea>
                            <?php else : ?>
                                <input type="<?php echo esc_attr($stype === 'url' ? 'url' : 'text'); ?>"
                                       name="<?php echo esc_attr($name); ?>"
                                       value="<?php echo esc_attr((string) $sv); ?>"
                                       placeholder="<?php echo esc_attr($ph); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger sikshya-repeater-group-remove" aria-label="<?php esc_attr_e('Remove row', 'sikshya'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-repeater-group-add" data-field-id="<?php echo esc_attr($field_id); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <?php echo esc_html($add_button_text); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Multi- or single-user picker for instructors.
     *
     * @param array<string,mixed> $field_config
     * @param mixed               $value
     */
    protected function renderUserSelectField(string $field_id, array $field_config, $value): string
    {
        $roles = $field_config['role_filter'] ?? ['administrator', 'editor', 'author'];
        if (!is_array($roles)) {
            $roles = ['administrator', 'editor', 'author'];
        }

        $users = get_users(
            [
                'role__in' => $roles,
                'orderby' => 'display_name',
                'order' => 'ASC',
                'number' => 300,
                'fields' => ['ID', 'display_name', 'user_login'],
            ]
        );

        $selected = [];
        if (!empty($field_config['multiple'])) {
            $selected = is_array($value) ? array_map('intval', $value) : [];
        } else {
            $selected = $value !== '' && $value !== null ? [(int) $value] : [];
        }

        $name_attr = !empty($field_config['multiple']) ? esc_attr($field_id) . '[]' : esc_attr($field_id);
        $multiple = !empty($field_config['multiple']) ? ' multiple' : '';

        ob_start();
        ?>
        <select name="<?php echo $name_attr; ?>" class="sikshya-user-select"<?php echo $multiple; ?> size="<?php echo !empty($field_config['multiple']) ? '8' : '1'; ?>">
            <?php if (empty($field_config['multiple'])) : ?>
                <option value=""><?php esc_html_e('— Select —', 'sikshya'); ?></option>
            <?php endif; ?>
            <?php foreach ($users as $u) : ?>
                <option value="<?php echo esc_attr((string) $u->ID); ?>"<?php selected(in_array((int) $u->ID, $selected, true)); ?>>
                    <?php echo esc_html($u->display_name . ' (' . $u->user_login . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a permalink field
     *
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @return string
     */
    protected function renderPermalinkField(string $field_id, array $field_config, $value): string
    {
        $label = $field_config['label'] ?? $field_id;
        $description = $field_config['description'] ?? '';

        ob_start();
        ?>
        <div class="sikshya-permalink-wrapper">
            <div class="sikshya-permalink-display" id="permalink-display">
                <span class="sikshya-permalink-base"><?php echo esc_url(home_url('/courses/')); ?></span>
                <span class="sikshya-permalink-slug" id="permalink-slug"><?php echo esc_html($value); ?></span>
            </div>
            <?php if (empty($value) || !isset($this->renderData['id'])) : ?>
                <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" id="edit-permalink-btn" onclick="togglePermalinkEdit()">
                    <?php _e('Edit', 'sikshya'); ?>
                </button>
                <div class="sikshya-permalink-edit" id="permalink-edit" style="display: none;">
                    <input type="text" name="<?php echo esc_attr($field_id); ?>" id="permalink-input" value="<?php echo esc_attr($value); ?>" placeholder="<?php _e('course-slug', 'sikshya'); ?>">
                    <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-btn-sm" onclick="savePermalink()">
                        <?php _e('OK', 'sikshya'); ?>
                    </button>
                    <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" onclick="cancelPermalinkEdit()">
                        <?php _e('Cancel', 'sikshya'); ?>
                    </button>
                </div>
            <?php else : ?>
                <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" id="edit-permalink-btn" onclick="togglePermalinkEdit()" title="<?php _e('Click to edit permalink', 'sikshya'); ?>">
                    <?php _e('Edit', 'sikshya'); ?>
                </button>
                <div class="sikshya-permalink-edit" id="permalink-edit" style="display: none;">
                    <input type="text" name="<?php echo esc_attr($field_id); ?>" id="permalink-input" value="<?php echo esc_attr($value); ?>" placeholder="<?php _e('course-slug', 'sikshya'); ?>">
                    <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-btn-sm" onclick="savePermalink()">
                        <?php _e('OK', 'sikshya'); ?>
                    </button>
                    <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-btn-sm" onclick="cancelPermalinkEdit()">
                        <?php _e('Cancel', 'sikshya'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($description)) : ?>
            <p class="sikshya-help-text">
                <?php if (empty($value) || !isset($this->renderData['id'])) : ?>
                    <?php _e('The URL-friendly version of your course title. Auto-generated from the title.', 'sikshya'); ?>
                <?php else : ?>
                    <?php _e('The URL-friendly version of your course title. Click Edit to modify.', 'sikshya'); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a curriculum builder field
     *
     * @param string $field_id
     * @param array $field_config
     * @param mixed $value
     * @return string
     */
    protected function renderCurriculumBuilderField(string $field_id, array $field_config, $value): string
    {
        $label = $field_config['label'] ?? '';
        $description = $field_config['description'] ?? '';

        ob_start();
        ?>
        <div class="sikshya-form-row">
            <label><?php echo esc_html($label); ?></label>
            <div class="sikshya-curriculum-builder" id="curriculum-content">
                <!-- Compact Empty State -->
                <div class="sikshya-curriculum-empty-state" id="curriculum-empty-state">
                    <!-- Header with Inline Actions -->
                    <div class="sikshya-empty-header">
                        <div class="sikshya-empty-content">
                            <div class="sikshya-empty-icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="sikshya-empty-text">
                                <h3><?php _e('Create Your First Chapter', 'sikshya'); ?></h3>
                                <p><?php _e('Start building your course curriculum with organized chapters and lessons.', 'sikshya'); ?></p>
                            </div>
                        </div>
                        <div class="sikshya-empty-actions">
                            <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <?php _e('Add Chapter', 'sikshya'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Existing Curriculum Structure (Hidden when empty) -->
                <div class="sikshya-curriculum-items" id="curriculum-items" style="display: none;">
                    <?php
                    // Sample chapter for demo (when not empty)
                    // This will be dynamically populated via AJAX using chapter.php template
                    ?>
                </div>
            </div>

            <!-- Curriculum Actions -->
            <div class="sikshya-curriculum-actions">
                <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <?php _e('Add Chapter', 'sikshya'); ?>
                </button>
                
                <!-- Demo Button to Toggle Content -->
                <button class="sikshya-btn sikshya-btn-secondary" onclick="toggleDemoContent()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <?php _e('Load Sample Chapter', 'sikshya'); ?>
                </button>
                
                <div class="sikshya-action-divider"></div>
                
                <button class="sikshya-btn sikshya-btn-secondary" onclick="importFromTemplate()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                    <?php _e('Import from Template', 'sikshya'); ?>
                </button>
                
                <button class="sikshya-btn sikshya-btn-secondary" onclick="bulkImport()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <?php _e('Bulk Import', 'sikshya'); ?>
                </button>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }
}
