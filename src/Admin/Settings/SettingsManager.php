<?php

namespace Sikshya\Admin\Settings;

use Sikshya\Addons\Addons;
use Sikshya\Core\Plugin;
use Sikshya\Licensing\FeatureRegistry;
use Sikshya\Licensing\TierCapabilities;
use Sikshya\Services\Settings;
use Sikshya\Services\PermalinkService;

/**
 * Settings Manager Class
 *
 * @package Sikshya\Admin\Settings
 * @since 1.0.0
 */
class SettingsManager
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    protected Plugin $plugin;

    /**
     * Settings arrays for each tab
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * Ensures one-time option migrations run before settings arrays are built.
     *
     * @var bool
     */
    private $settings_migrations_ran = false;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->initSettings();
    }

    /**
     * Initialize all settings arrays
     */
    protected function initSettings(): void
    {
        // Don't initialize settings arrays here to avoid translation loading too early
        // They will be initialized when first accessed
        $this->settings = [];
    }

    /**
     * Get all settings (lazy loading)
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        $this->maybeRunSettingsMigrations();

        if (empty($this->settings)) {
            $this->settings = [
                'general' => $this->getGeneralSettings(),
                'courses' => $this->getCoursesSettings(),
                'lessons' => $this->getLessonsSettings(),
                'enrollment' => $this->getEnrollmentSettings(),
                'payment' => $this->getPaymentSettings(),
                'certificates' => $this->getCertificatesSettings(),
                'email' => $this->getEmailSettings(),
                'instructors' => $this->getInstructorsSettings(),
                'students' => $this->getStudentsSettings(),
                'quizzes' => $this->getQuizzesSettings(),
                'assignments' => $this->getAssignmentsSettings(),
                'progress' => $this->getProgressSettings(),
                'notifications' => $this->getNotificationsSettings(),
                'integrations' => $this->getIntegrationsSettings(),
                'permalinks' => $this->getPermalinksSettings(),
                'security' => $this->getSecuritySettings(),
                'advanced' => $this->getAdvancedSettings(),
            ];

            /**
             * Allow addons to add/modify settings tabs and sections.
             */
            $this->settings = apply_filters('sikshya_settings_tabs', $this->settings);
        }
        return $this->settings;
    }

    /**
     * One-time migrations for renamed or split option keys.
     */
    private function maybeRunSettingsMigrations(): void
    {
        if ($this->settings_migrations_ran) {
            return;
        }
        $this->settings_migrations_ran = true;

        // Legacy: assignments reused `max_file_size` with general uploads — split into `assignment_max_file_size`.
        $assignment_key = Settings::PREFIX . 'assignment_max_file_size';
        if (get_option($assignment_key, false) === false) {
            $legacy = get_option(Settings::PREFIX . 'max_file_size', false);
            if ($legacy !== false && $legacy !== '') {
                update_option($assignment_key, $legacy);
            }
        }
    }

    /**
     * Find a field definition within a tab (first match).
     *
     * @return array<string, mixed>|null
     */
    private function findFieldInTab(string $tab, string $key): ?array
    {
        $tab_settings = $this->getTabSettings($tab);
        foreach ($tab_settings as $section) {
            if (empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }
            foreach ($section['fields'] as $field) {
                if (($field['key'] ?? '') === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Registered setting keys for a tab (used by tab save + JSON import whitelist).
     *
     * @return string[]
     */
    private function collectFieldKeysForTab(string $tab): array
    {
        $tab_settings = $this->getTabSettings($tab);
        $field_names = [];
        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field) {
                    if (isset($field['key'])) {
                        $field_names[] = (string) $field['key'];
                    }
                }
            }
        }

        return $field_names;
    }

    /**
     * Get settings for a specific tab
     *
     * @param string $tab
     * @return array
     */
    public function getTabSettings(string $tab): array
    {
        $all_settings = $this->getAllSettings();
        $settings = $all_settings[$tab] ?? [];

        /**
         * Allow addons to customize a single tab's sections/fields.
         */
        return apply_filters('sikshya_settings_tab_' . sanitize_key($tab), $settings);
    }

    /**
     * Get setting value with _sikshya_ prefix
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = '')
    {
        return Settings::get($key, $default);
    }

    /**
     * Save setting with _sikshya_ prefix
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function saveSetting(string $key, $value): bool
    {
        Settings::set($key, $value);

        // Side-effect: keep usage tracking scheduler in sync with the consent toggle.
        if ($key === 'allow_usage_tracking' && class_exists('\\Sikshya\\Services\\StatsUsage')) {
            $truthy = Settings::isTruthy($value);
            $u = \Sikshya\Services\StatsUsage::instance();
            if ($truthy) {
                $u->enable(true);
            } else {
                $u->disable();
            }
        }

        $saved_value = Settings::get($key, null);

        $value_normalized = $this->normalizeValue($value);
        $saved_normalized = $this->normalizeValue($saved_value);

        if ($value_normalized === $saved_normalized) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(
                'Sikshya SettingsManager: option mismatch after save for ' . ('_sikshya_' . sanitize_key($key))
            );
        }

        return false;
    }

    /**
     * Save multiple settings
     *
     * @param array $settings
     * @return bool
     */
    public function saveSettings(array $settings): bool
    {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->saveSetting($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Save settings for a specific tab
     *
     * @param string $tab
     * @param array $data
     * @return bool
     */
    public function saveTabSettings(string $tab, array $data): bool
    {
        // Get the settings configuration for this tab
        $tab_settings = $this->getTabSettings($tab);
        if (empty($tab_settings)) {
            return false;
        }

        $field_names = $this->collectFieldKeysForTab($tab);

        // Filter data to only include fields that are in the configuration
        $settings_to_save = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $field_names)) {
                $settings_to_save[$key] = $value;
            }
        }

        // Selects with `select_placeholder`: empty choice means “use default” (avoids invalid stored "").
        foreach (array_keys($settings_to_save) as $key) {
            $field = $this->findFieldInTab($tab, $key);
            if (
                $field
                && ($field['type'] ?? '') === 'select'
                && !empty($field['select_placeholder'])
                && ($settings_to_save[$key] === '' || $settings_to_save[$key] === null)
            ) {
                $settings_to_save[$key] = $field['default'] ?? '';
            }
        }

        $settings_to_save = $this->stripLockedFieldsOnSave($tab, $settings_to_save);

        // Save the settings
        $ok = $this->saveSettings($settings_to_save);
        if ($ok && $tab === 'general') {
            $this->syncWordPressMirrorsFromGeneralTab($settings_to_save);
        }
        if ($ok && $tab === 'permalinks') {
            flush_rewrite_rules(false);
        }

        return $ok;
    }

    /**
     * Keep WordPress core date/time options aligned with Sikshya General tab (used by wp_date(), etc.).
     *
     * @param array<string, mixed> $values
     */
    private function syncWordPressMirrorsFromGeneralTab(array $values): void
    {
        if (isset($values['timezone']) && is_string($values['timezone']) && $values['timezone'] !== '') {
            update_option('timezone_string', sanitize_text_field($values['timezone']));
        }
        if (isset($values['date_format']) && is_string($values['date_format']) && $values['date_format'] !== '') {
            update_option('date_format', sanitize_text_field($values['date_format']));
        }
        if (isset($values['time_format']) && is_string($values['time_format']) && $values['time_format'] !== '') {
            update_option('time_format', sanitize_text_field($values['time_format']));
        }
        if (isset($values['site_title']) && is_string($values['site_title']) && trim($values['site_title']) !== '') {
            update_option('blogname', sanitize_text_field($values['site_title']));
        }
        if (isset($values['site_description']) && is_string($values['site_description'])) {
            update_option('blogdescription', sanitize_textarea_field($values['site_description']));
        }
    }

    /**
     * Reset settings for a specific tab
     *
     * @param string $tab
     * @return bool
     */
    public function resetTabSettings(string $tab): bool
    {
        $tab_settings = $this->getTabSettings($tab);
        if (empty($tab_settings)) {
            return false;
        }

        $success = true;
        foreach ($tab_settings as $section) {
            $section_locked = $this->isSectionLocked($section);
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field) {
                    if (!isset($field['key'])) {
                        continue;
                    }
                    if ($section_locked || $this->isFieldLocked($field)) {
                        // Don't reset gated fields — leave stored value untouched so upgrading
                        // the addon later brings back the admin's previous configuration.
                        continue;
                    }
                    $default = $field['default'] ?? '';
                    if (!$this->saveSetting($field['key'], $default)) {
                        $success = false;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Decide whether a section is currently gated (addon disabled or feature off).
     *
     * A section may opt-in by declaring either `required_addon` (Addon Manager key)
     * or `required_feature` (Pro catalog entitlement).  Both are checked; any one
     * being off locks the section.
     *
     * @param array<string, mixed> $section
     */
    private function isSectionLocked(array $section): bool
    {
        return $this->isGateMet(
            (string) ($section['required_addon'] ?? ''),
            (string) ($section['required_feature'] ?? '')
        ) === false;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isFieldLocked(array $field): bool
    {
        return $this->isGateMet(
            (string) ($field['required_addon'] ?? ''),
            (string) ($field['required_feature'] ?? '')
        ) === false;
    }

    /**
     * Returns true when either the required addon is enabled OR the required
     * feature is available (nothing configured = always unlocked).
     */
    private function isGateMet(string $addon, string $feature): bool
    {
        if ($addon === '' && $feature === '') {
            return true;
        }
        if ($addon !== '' && !Addons::isEnabled($addon)) {
            return false;
        }
        if ($feature !== '' && !TierCapabilities::feature($feature)) {
            return false;
        }
        return true;
    }

    /**
     * Strip values for fields/sections whose Pro gate is not met.  This guards
     * the REST save path so callers can't bypass the UI lock and persist values
     * for disabled addons.
     *
     * @param array<string, mixed> $settings_to_save
     * @return array<string, mixed>
     */
    private function stripLockedFieldsOnSave(string $tab, array $settings_to_save): array
    {
        $tab_settings = $this->getTabSettings($tab);
        foreach ($tab_settings as $section) {
            $section_locked = $this->isSectionLocked($section);
            if (empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }
            foreach ($section['fields'] as $field) {
                if (!isset($field['key'])) {
                    continue;
                }
                if ($section_locked || $this->isFieldLocked($field)) {
                    unset($settings_to_save[(string) $field['key']]);
                }
            }
        }

        return $settings_to_save;
    }

    /**
     * Decorate the tabs array with `locked` flags and propagate section-level
     * gates down to fields so React can render a consistent Pro overlay without
     * having to re-evaluate licensing state on the client.
     *
     * @param array<string, array<int, array<string, mixed>>> $tabs
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function decorateSchemaGating(array $tabs): array
    {
        foreach ($tabs as $tab_key => $sections) {
            if (!is_array($sections)) {
                continue;
            }
            foreach ($sections as $si => $section) {
                if (!is_array($section)) {
                    continue;
                }
                $section_locked = $this->isSectionLocked($section);
                $section_gate = $this->gateLabels(
                    (string) ($section['required_addon'] ?? ''),
                    (string) ($section['required_feature'] ?? '')
                );
                $section_reason = $this->lockReason(
                    (string) ($section['required_addon'] ?? ''),
                    (string) ($section['required_feature'] ?? '')
                );
                if ($section_locked) {
                    $sections[$si]['locked'] = true;
                    $sections[$si]['locked_reason'] = $section_reason;
                    if (!empty($section_gate['required_addon_label'])) {
                        $sections[$si]['required_addon_label'] = $section_gate['required_addon_label'];
                    }
                    if (!empty($section_gate['required_plan_label'])) {
                        $sections[$si]['required_plan_label'] = $section_gate['required_plan_label'];
                    }
                }
                if (!empty($section['fields']) && is_array($section['fields'])) {
                    foreach ($section['fields'] as $fi => $field) {
                        if (!is_array($field)) {
                            continue;
                        }
                        $field_addon = (string) ($field['required_addon'] ?? ($section['required_addon'] ?? ''));
                        $field_feature = (string) ($field['required_feature'] ?? ($section['required_feature'] ?? ''));
                        $is_locked = $section_locked || $this->isFieldLocked($field);
                        if ($is_locked) {
                            $sections[$si]['fields'][$fi]['locked'] = true;
                            $sections[$si]['fields'][$fi]['locked_reason'] = $this->lockReason($field_addon, $field_feature);
                            $field_gate = $this->gateLabels($field_addon, $field_feature);
                            if (!empty($field_gate['required_addon_label'])) {
                                $sections[$si]['fields'][$fi]['required_addon_label'] = $field_gate['required_addon_label'];
                            }
                            if (!empty($field_gate['required_plan_label'])) {
                                $sections[$si]['fields'][$fi]['required_plan_label'] = $field_gate['required_plan_label'];
                            }
                            if ($field_addon !== '' && empty($field['required_addon'])) {
                                $sections[$si]['fields'][$fi]['required_addon'] = $field_addon;
                            }
                            if ($field_feature !== '' && empty($field['required_feature'])) {
                                $sections[$si]['fields'][$fi]['required_feature'] = $field_feature;
                            }
                        }
                    }
                }
            }
            $tabs[$tab_key] = $sections;
        }

        return $tabs;
    }

    /**
     * Provide user-facing labels to explain what is required for a locked field/section.
     *
     * @return array{required_addon_label: string, required_plan_label: string}
     */
    private function gateLabels(string $addon, string $feature): array
    {
        $addon_label = '';
        $plan_label = '';

        $id = $feature !== '' ? $feature : $addon;
        if ($id !== '') {
            $def = FeatureRegistry::get($id);
            if (is_array($def)) {
                if (isset($def['label'])) {
                    $addon_label = (string) $def['label'];
                }
                $tier = isset($def['tier']) ? (string) $def['tier'] : '';
                switch ($tier) {
                    case 'starter':
                        $plan_label = __('Starter', 'sikshya');
                        break;
                    // Product copy: `pro` tier maps to the "Growth" plan in UI/marketing.
                    case 'pro':
                        $plan_label = __('Growth', 'sikshya');
                        break;
                    case 'scale':
                        $plan_label = __('Scale', 'sikshya');
                        break;
                    default:
                        $plan_label = '';
                        break;
                }
            }
        }

        return [
            'required_addon_label' => $addon_label,
            'required_plan_label' => $plan_label,
        ];
    }

    /**
     * Produce a short, human-readable reason for why a field/section is locked.
     */
    private function lockReason(string $addon, string $feature): string
    {
        $labels = $this->gateLabels($addon, $feature);
        $addon_label = $labels['required_addon_label'] !== '' ? $labels['required_addon_label'] : $addon;
        $plan_label = $labels['required_plan_label'];

        if ($addon !== '' && !Addons::isEnabled($addon)) {
            if ($feature !== '' && !TierCapabilities::feature($feature)) {
                return sprintf(
                    /* translators: 1: plan label, 2: addon label */
                    __('Requires a paid plan (%1$s+) and the add-on “%2$s” to be enabled.', 'sikshya'),
                    $plan_label !== '' ? $plan_label : __('Pro', 'sikshya'),
                    $addon_label
                );
            }
            return sprintf(
                /* translators: %s: addon label */
                __('Enable the addon “%s” under Addons to unlock.', 'sikshya'),
                $addon_label
            );
        }
        if ($feature !== '' && !TierCapabilities::feature($feature)) {
            return $plan_label !== ''
                ? sprintf(
                    /* translators: %s: plan label */
                    __('Available on paid Sikshya plans (%s+).', 'sikshya'),
                    $plan_label
                )
                : __('Available on a higher paid plan.', 'sikshya');
        }

        return '';
    }

    /**
     * Reset all settings to defaults
     *
     * @return bool
     */
    public function resetAllSettings(): bool
    {
        $all_settings = $this->getAllSettings();
        $success = true;

        foreach ($all_settings as $tab => $tab_settings) {
            if (!$this->resetTabSettings($tab)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Export settings for a specific tab
     *
     * @param string $tab
     * @return array
     */
    public function exportTabSettings(string $tab): array
    {
        $tab_settings = $this->getTabSettings($tab);
        $export_data = [];

        foreach ($tab_settings as $section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as $field) {
                    if (isset($field['key'])) {
                        $export_data[$field['key']] = $this->getSetting($field['key'], $field['default'] ?? '');
                    }
                }
            }
        }

        return $export_data;
    }

    /**
     * Export all settings
     *
     * @return array
     */
    public function exportAllSettings(): array
    {
        $all_settings = $this->getAllSettings();
        $export_data = [];

        foreach ($all_settings as $tab => $tab_settings) {
            $export_data[$tab] = $this->exportTabSettings($tab);
        }

        return $export_data;
    }

    /**
     * Import settings from a Tools / REST JSON export.
     *
     * Only keys that exist on the tab schema are applied (same surface as {@see saveTabSettings}).
     * Unknown tabs or arbitrary keys are ignored so imports cannot create stray `_sikshya_*` options.
     *
     * @param array<string, mixed> $data Tab slug => key => value (same shape as {@see exportAllSettings}).
     * @param bool                 $overwrite When true, overwrite non-empty values too.
     * @return bool True when nothing failed; false when every attempted write failed or the payload was unusable.
     */
    public function importSettings(array $data, bool $overwrite = false): bool
    {
        if ($data === []) {
            return false;
        }

        $all_tabs = $this->getAllSettings();
        $success = true;
        $expected_writes = 0;
        $applied_writes = 0;

        foreach ($data as $tab => $tab_data) {
            if (!is_array($tab_data)) {
                continue;
            }

            $tab_key = sanitize_key((string) $tab);
            if ($tab_key === '' || !array_key_exists($tab_key, $all_tabs)) {
                continue;
            }

            if ($tab_key === 'assignments' && isset($tab_data['max_file_size']) && !isset($tab_data['assignment_max_file_size'])) {
                $tab_data['assignment_max_file_size'] = $tab_data['max_file_size'];
                unset($tab_data['max_file_size']);
            }

            $field_names = $this->collectFieldKeysForTab($tab_key);
            if ($field_names === []) {
                continue;
            }

            $filtered = [];
            foreach ($tab_data as $key => $value) {
                $key_str = is_string($key) ? sanitize_key($key) : '';
                if ($key_str === '' || !in_array($key_str, $field_names, true)) {
                    continue;
                }
                $filtered[$key_str] = $value;
            }

            $to_save = [];
            foreach ($filtered as $key => $value) {
                if ($overwrite || $this->getSetting($key) === '') {
                    $to_save[$key] = $value;
                }
            }

            $to_save = $this->stripLockedFieldsOnSave($tab_key, $to_save);
            $expected_writes += count($to_save);

            $tab_save_ok = true;
            foreach ($to_save as $key => $value) {
                if ($this->saveSetting($key, $value)) {
                    ++$applied_writes;
                } else {
                    $tab_save_ok = false;
                    $success = false;
                }
            }

            if ($tab_save_ok && $tab_key === 'general' && $to_save !== []) {
                $this->syncWordPressMirrorsFromGeneralTab($to_save);
            }

            if ($tab_save_ok && $tab_key === 'permalinks' && $to_save !== []) {
                flush_rewrite_rules(false);
            }
        }

        if ($expected_writes > 0 && $applied_writes === 0) {
            return false;
        }

        return $success;
    }

    /**
     * Render settings form for a tab
     *
     * @param string $tab
     * @param array $field_errors
     * @return string
     */
    public function renderTabSettings(string $tab, array $field_errors = []): string
    {
        $settings = $this->getTabSettings($tab);
        if (empty($settings)) {
            return '<p>' . esc_html__('No settings found for this tab.', 'sikshya') . '</p>';
        }

        $output = '<div class="sikshya-settings-tab-content">';

        foreach ($settings as $section) {
            $output .= $this->renderSection($section, $field_errors);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a settings section
     *
     * @param array $section
     * @param array $field_errors
     * @return string
     */
    protected function renderSection(array $section, array $field_errors = []): string
    {
        $output = '<div class="sikshya-settings-section">' . "\n";

        if (!empty($section['title'])) {
            $icon = $section['icon'] ?? 'fas fa-cog';
            $output .= '        <h3 class="sikshya-settings-section-title">' . "\n";
            $output .= '            <i class="' . esc_attr($icon) . '"></i>' . "\n";
            $output .= '            ' . esc_html($section['title']) . '        ' . "\n";
            $output .= '        </h3>' . "\n";
        }

        if (!empty($section['fields'])) {
            $output .= '        ' . "\n";
            $output .= '        <div class="sikshya-settings-grid">' . "\n";
            $field_count = count($section['fields']);
            foreach ($section['fields'] as $index => $field) {
                $field_error = $field_errors[$field['key'] ?? ''] ?? '';
                $output .= $this->renderField($field, $field_error);
                // Add empty line between fields (except after the last field)
                if ($index < $field_count - 1) {
                    $output .= '            ' . "\n";
                }
            }
            $output .= '        </div>' . "\n";
        }

        $output .= '    </div>';

        return $output;
    }

    /**
     * Render a settings field
     *
     * @param array $field
     * @param string $error_message
     * @return string
     */
    protected function renderField(array $field, string $error_message = ''): string
    {
        $key = $field['key'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $description = $field['description'] ?? '';
        $default = $field['default'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $options = $field['options'] ?? [];

        $current_value = $this->getSetting($key, $default);

        $field_class = 'sikshya-settings-field';
        if (!empty($error_message)) {
            $field_class .= ' has-error';
        }

        $output = '            <div class="' . $field_class . '">' . "\n";

        // Label (skip for checkbox as it's handled in the checkbox case)
        if (!empty($label) && $type !== 'checkbox') {
            $output .= '                <label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>' . "\n";
        }

        // Field input
        switch ($type) {
            case 'textarea':
                $output .= '                <textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="3"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . esc_textarea($current_value) . '</textarea>' . "\n";
                break;

            case 'select':
                $output .= '                <select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . "\n";
                foreach ($options as $option_value => $option_label) {
                    $selected = selected($current_value, $option_value, false);
                    $output .= '                    <option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>' . "\n";
                }
                $output .= '                </select>' . "\n";
                break;

            case 'checkbox':
                $checked = checked($current_value, '1', false);
                $output .= '                <div class="sikshya-checkbox-wrapper">' . "\n";
                $output .= '                    <input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1"' . $checked . '>' . "\n";
                if (!empty($label)) {
                    $output .= '                    <label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>' . "\n";
                }
                $output .= '                </div>' . "\n";
                break;

            case 'radio':
                foreach ($options as $option_value => $option_label) {
                    $checked = checked($current_value, $option_value, false);
                    $output .= '                <label class="radio-label">' . "\n";
                    $output .= '                    <input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($option_value) . '"' . $checked . '>' . "\n";
                    $output .= '                    <span>' . esc_html($option_label) . '</span>' . "\n";
                    $output .= '                </label>' . "\n";
                }
                break;

            case 'number':
                $output .= '                <input type="number" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                if (isset($field['min'])) {
                    $output .= ' min="' . esc_attr($field['min']) . '"';
                }
                if (isset($field['max'])) {
                    $output .= ' max="' . esc_attr($field['max']) . '"';
                }
                $output .= '>' . "\n";
                break;

            case 'email':
                $output .= '                <input type="email" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . "\n";
                break;

            case 'password':
                $output .= '                <input type="password" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . "\n";
                break;

            case 'url':
                $output .= '                <input type="url" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . "\n";
                break;

            case 'color':
                $output .= '                <input type="color" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                $output .= '>' . "\n";
                break;

            case 'datetime-local':
                $output .= '                <input type="datetime-local" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                $output .= '>' . "\n";
                break;

            default: // text
                $output .= '                <input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"';
                $output .= ' value="' . esc_attr($current_value) . '"';
                if (!empty($placeholder)) {
                    $output .= ' placeholder="' . esc_attr($placeholder) . '"';
                }
                $output .= '>' . "\n";
                break;
        }

        // Description
        if (!empty($description)) {
            $output .= '                <p class="description">' . esc_html($description) . '</p>' . "\n";
        }

        // Error message
        if (!empty($error_message)) {
            $output .= '                <div class="sikshya-field-error">' . "\n";
            $output .= '                    <i class="fas fa-exclamation-triangle"></i>' . "\n";
            $output .= '                    <span>' . esc_html($error_message) . '</span>' . "\n";
            $output .= '                </div>' . "\n";
        }

        $output .= '            </div>' . "\n";

        return $output;
    }

    /**
     * Get General Settings
     *
     * @return array
     */
    protected function getGeneralSettings(): array
    {
        return [
            [
                'title' => __('Basic Information', 'sikshya'),
                'icon' => 'fas fa-info-circle',
                'fields' => [
                    [
                        'key' => 'site_title',
                        'type' => 'text',
                        'label' => __('Site or platform name', 'sikshya'),
                        'description' => __('Shown in the browser tab and as the public site name across Sikshya. Usually matches your WordPress site title.', 'sikshya'),
                        'placeholder' => __('e.g. Acme Online Academy', 'sikshya'),
                        'default' => get_bloginfo('name'),
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            return !empty(trim($value)) ? true : __('Site title cannot be empty.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'site_description',
                        'type' => 'textarea',
                        'label' => __('Short description (tagline)', 'sikshya'),
                        'description' => __('One or two sentences about what learners will find here. Some themes may show this near the top of course pages.', 'sikshya'),
                        'placeholder' => __('e.g. Practical video courses for small business owners.', 'sikshya'),
                        'default' => get_bloginfo('description'),
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => function ($value) {
                            return strlen($value) <= 500 ? true : __('Description cannot exceed 500 characters.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'max_file_size',
                        'type' => 'number',
                        'label' => __('Largest upload size (MB)', 'sikshya'),
                        'description' => __('Upper limit for a single file learners or staff can upload (lessons, assignments). Your host may also impose a lower limit.', 'sikshya'),
                        'placeholder' => __('e.g. 10', 'sikshya'),
                        'default' => 10,
                        'min' => 1,
                        'max' => 100,
                        'sanitize_callback' => 'intval',
                        'validate_callback' => function ($value) {
                            $value = intval($value);
                            if ($value < 1) {
                                return __('File size must be at least 1 MB.', 'sikshya');
                            }
                            if ($value > 100) {
                                return __('File size cannot exceed 100 MB.', 'sikshya');
                            }
                            return true;
                        }
                    ]
                ]
            ],
            [
                'title' => __('Currency & Pricing', 'sikshya'),
                'icon' => 'fas fa-dollar-sign',
                'fields' => [
                    [
                        'key' => 'currency',
                        'type' => 'select',
                        'label' => __('Currency', 'sikshya'),
                        'description' => __('Which money unit prices use everywhere: course prices, cart, checkout, and receipts.', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 'USD',
                        'options' => function_exists('sikshya_get_currency_choices')
                            ? sikshya_get_currency_choices()
                            : ['USD' => 'United States dollar ($)'],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            $valid = function_exists('sikshya_get_currencies')
                                ? array_keys(sikshya_get_currencies())
                                : ['USD'];
                            return in_array(strtoupper((string) $value), $valid, true)
                                ? true
                                : __('Invalid currency selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'currency_position',
                        'type' => 'select',
                        'label' => __('Currency Position', 'sikshya'),
                        'description' => __('Whether the $ or € appears before or after the number, and whether there is a space.', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 'left',
                        'options' => [
                            'left' => __('Left ($99.99)', 'sikshya'),
                            'right' => __('Right (99.99$)', 'sikshya'),
                            'left_space' => __('Left with space ($ 99.99)', 'sikshya'),
                            'right_space' => __('Right with space (99.99 $)', 'sikshya')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            $valid_positions = ['left', 'right', 'left_space', 'right_space'];
                            return in_array($value, $valid_positions) ? true : __('Invalid currency position selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'currency_thousand_separator',
                        'type' => 'text',
                        'label' => __('Thousand Separator', 'sikshya'),
                        'description' => __('Symbol between groups of three digits (for example 1,000). In many regions this is a comma.', 'sikshya'),
                        'placeholder' => ',',
                        'default' => ',',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    [
                        'key' => 'currency_decimal_separator',
                        'type' => 'text',
                        'label' => __('Decimal Separator', 'sikshya'),
                        'description' => __('Symbol between whole and cents (for example 10.99). In many regions this is a period.', 'sikshya'),
                        'placeholder' => '.',
                        'default' => '.',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    [
                        'key' => 'currency_decimal_places',
                        'type' => 'select',
                        'label' => __('Number of Decimals', 'sikshya'),
                        'description' => __('How many digits after the decimal point prices show (for example two for cents).', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 2,
                        'options' => [
                            0 => __('0 (whole numbers)', 'sikshya'),
                            2 => __('2 (e.g. 99.99)', 'sikshya'),
                            3 => __('3 (e.g. 99.999)', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Date & Time', 'sikshya'),
                'icon' => 'fas fa-clock',
                'fields' => [
                    [
                        'key' => 'timezone',
                        'type' => 'select',
                        'label' => __('Timezone', 'sikshya'),
                        'description' => __('Your local time zone so lesson times, deadlines, and emails show the correct clock time.', 'sikshya'),
                        'default' => Settings::getRaw('timezone_string'),
                        'options' => $this->getTimezoneOptions(),
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            return in_array($value, \DateTimeZone::listIdentifiers()) ? true : __('Invalid timezone selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'date_format',
                        'type' => 'select',
                        'label' => __('Date Format', 'sikshya'),
                        'description' => __('How day, month, and year are ordered and separated across Sikshya screens.', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => Settings::getRaw('date_format'),
                        'options' => [
                            'F j, Y' => date('F j, Y'),
                            'Y-m-d' => date('Y-m-d'),
                            'm/d/Y' => date('m/d/Y'),
                            'd/m/Y' => date('d/m/Y'),
                            'j F Y' => date('j F Y')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            $valid_formats = ['F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'j F Y'];
                            return in_array($value, $valid_formats) ? true : __('Invalid date format selected.', 'sikshya');
                        }
                    ],
                    [
                        'key' => 'time_format',
                        'type' => 'select',
                        'label' => __('Time Format', 'sikshya'),
                        'description' => __('12-hour with am/pm or 24-hour clock for times shown in Sikshya.', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => Settings::getRaw('time_format'),
                        'options' => [
                            'g:i a' => date('g:i a'),
                            'H:i' => date('H:i')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($value) {
                            $valid_formats = ['g:i a', 'H:i'];
                            return in_array($value, $valid_formats) ? true : __('Invalid time format selected.', 'sikshya');
                        }
                    ]
                ]
            ],
        ];
    }

    /**
     * Get Courses Settings
     *
     * @return array
     */
    protected function getCoursesSettings(): array
    {
        return [
            [
                'section_key' => 'course_tax',
                'title' => __('Categories', 'sikshya'),
                'icon' => 'fas fa-folder',
                'description' => __(
                    'Categories group courses into broad topics and help learners browse and search.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_course_categories',
                        'type' => 'checkbox',
                        'label' => __('Use course categories', 'sikshya'),
                        'description' => __('Turns on hierarchical topics (for example “Design”, “Development”).', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'category_display',
                        'type' => 'select',
                        'label' => __('How categories appear on the site', 'sikshya'),
                        'description' => __('Pick list, grid, or a compact dropdown—whatever fits your theme best.', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 'dropdown',
                        'options' => [
                            'list' => __('List View', 'sikshya'),
                            'grid' => __('Grid View', 'sikshya'),
                            'dropdown' => __('Dropdown Menu', 'sikshya')
                        ]
                    ]
                ]
            ],
            [
                'section_key' => 'course_search',
                'title' => __('Search & Filters', 'sikshya'),
                'icon' => 'fas fa-search',
                'description' => __(
                    'Control the course search box and optional filters (price, level, etc.) on your public catalog.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_course_search',
                        'type' => 'checkbox',
                        'label' => __('Show course search', 'sikshya'),
                        'description' => __('Visitors can type keywords to find courses.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_course_filters',
                        'type' => 'checkbox',
                        'label' => __('Show filter controls', 'sikshya'),
                        'description' => __('Adds ways to narrow the list (for example by price or level), if your theme supports it.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_title',
                        'type' => 'checkbox',
                        'label' => __('Match course titles', 'sikshya'),
                        'description' => __('Include each course’s name when someone searches.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_description',
                        'type' => 'checkbox',
                        'label' => __('Match course descriptions', 'sikshya'),
                        'description' => __('Include the long description text in search (helps find topics mentioned only there).', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_instructor',
                        'type' => 'checkbox',
                        'label' => __('Match instructor names', 'sikshya'),
                        'description' => __('Include the teacher’s display name so learners can search by instructor.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'search_categories',
                        'type' => 'checkbox',
                        'label' => __('Match categories', 'sikshya'),
                        'description' => __('Include category names so searches can find courses by topic.', 'sikshya'),
                        'default' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Lesson-level defaults (player, previews, progress). Option keys unchanged — fields relocated from Courses / Progress tabs.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getLessonsSettings(): array
    {
        return [
            [
                'section_key' => 'lesson_progress',
                'title' => __('Lesson progress', 'sikshya'),
                'icon' => 'fas fa-chart-line',
                'description' => __(
                    'Decide whether Sikshya records each lesson as “done” so you can show progress bars and completion rules.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'track_lesson_progress',
                        'type' => 'checkbox',
                        'label' => __('Track lesson progress', 'sikshya'),
                        'description' => __('Record when learners complete individual lessons (used in the player and reports).', 'sikshya'),
                        'default' => '1',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Enrollment Settings
     *
     * @return array
     */
    protected function getEnrollmentSettings(): array
    {
        return [
            [
                'section_key' => 'enrollment_checkout',
                'title' => __('Purchase & enrollment buttons', 'sikshya'),
                'icon' => 'fas fa-shopping-cart',
                'description' => __(
                    'Words shown on course pages for buying or joining, and whether checkout immediately adds the learner to the course.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'auto_enroll',
                        'type' => 'checkbox',
                        'label' => __('Auto-enroll on purchase', 'sikshya'),
                        'description' => __('When someone pays successfully, add them to the course right away without an extra step.', 'sikshya'),
                        'default' => true,
                    ],
                    [
                        'key' => 'enrollment_button_text',
                        'type' => 'text',
                        'label' => __('Button text for paid courses', 'sikshya'),
                        'description' => __('The label on the main action for courses that cost money (for example “Enroll now” or “Buy now”).', 'sikshya'),
                        'placeholder' => __('Enroll Now', 'sikshya'),
                        'default' => 'Enroll Now',
                    ],
                    [
                        'key' => 'free_course_text',
                        'type' => 'text',
                        'label' => __('Button text for free courses', 'sikshya'),
                        'description' => __('Shown when the course price is zero—usually something like “Start learning”.', 'sikshya'),
                        'placeholder' => __('Start Learning', 'sikshya'),
                        'default' => 'Start Learning',
                    ],
                    [
                        'key' => 'allow_admin_enroll_without_purchase',
                        'type' => 'checkbox',
                        'label' => __('Let administrators enroll without purchase', 'sikshya'),
                        'description' => __(
                            'When enabled, site managers can use “Enroll without purchase” on paid courses (testing or demos). Keep off on public sites unless needed.',
                            'sikshya'
                        ),
                        'default' => true,
                    ],
                    [
                        'key' => 'enable_guest_checkout',
                        'type' => 'checkbox',
                        'label' => __('Enable guest checkout (recommended)', 'sikshya'),
                        'description' => __(
                            'Let new visitors purchase without logging in first. Sikshya will create a student account automatically after payment succeeds and enroll them.',
                            'sikshya'
                        ),
                        'default' => true,
                    ],
                ],
            ],
            [
                'section_key' => 'enrollment_dynamic_checkout_fields',
                'title' => __('Dynamic checkout fields', 'sikshya'),
                'icon' => 'fas fa-list-check',
                'description' => __(
                    'Add extra questions to the checkout form (company, VAT, referral, consent) and show/hide them based on answers.',
                    'sikshya'
                ),
                'required_addon' => 'dynamic_checkout_fields',
                'required_feature' => 'dynamic_checkout_fields',
                'fields' => [
                    [
                        'key' => 'checkout_dynamic_fields_schema',
                        'type' => 'dynamic_fields_builder',
                        'label' => __('Checkout fields', 'sikshya'),
                        'description' => __('Add, reorder, and configure custom checkout fields. Values are saved to the order and can be used again for the same user.', 'sikshya'),
                        'default' => wp_json_encode(
                            [
                                [
                                    'id' => 'email',
                                    'label' => __('Email', 'sikshya'),
                                    'type' => 'email',
                                    'required' => true,
                                    'enabled' => true,
                                    'system' => true,
                                    'locked' => true,
                                    'width' => 'full',
                                ],
                                [
                                    'id' => 'name',
                                    'label' => __('Name', 'sikshya'),
                                    'type' => 'text',
                                    'required' => false,
                                    'enabled' => true,
                                    'width' => 'full',
                                    'placeholder' => __('Your name', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                                [
                                    'id' => 'phone',
                                    'label' => __('Phone', 'sikshya'),
                                    'type' => 'tel',
                                    'required' => true,
                                    'enabled' => true,
                                    'width' => 'half',
                                    'placeholder' => __('Phone number', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                                [
                                    'id' => 'country',
                                    'label' => __('Country', 'sikshya'),
                                    'type' => 'country',
                                    'required' => true,
                                    'enabled' => true,
                                    'width' => 'half',
                                    'placeholder' => __('Country', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                                [
                                    'id' => 'address_1',
                                    'label' => __('Address line 1', 'sikshya'),
                                    'type' => 'text',
                                    'required' => true,
                                    'enabled' => true,
                                    'width' => 'full',
                                    'placeholder' => __('Street address', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                                [
                                    'id' => 'city',
                                    'label' => __('City', 'sikshya'),
                                    'type' => 'text',
                                    'required' => true,
                                    'enabled' => true,
                                    'width' => 'half',
                                    'placeholder' => __('City', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                                [
                                    'id' => 'postcode',
                                    'label' => __('Postal code', 'sikshya'),
                                    'type' => 'text',
                                    'required' => false,
                                    'enabled' => true,
                                    'width' => 'half',
                                    'placeholder' => __('Postal code', 'sikshya'),
                                    'persist_to_user' => true,
                                ],
                            ],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                        'required_addon' => 'dynamic_checkout_fields',
                        'required_feature' => 'dynamic_checkout_fields',
                    ],
                ],
            ],
            [
                'section_key' => 'enrollment_completion',
                'title' => __('Course completion', 'sikshya'),
                'icon' => 'fas fa-check-circle',
                'description' => __(
                    'Defines when a learner is marked “finished” for certificates, reports, and prerequisite rules.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'course_completion_criteria',
                        'type' => 'select',
                        'label' => __('When is a course “complete”?', 'sikshya'),
                        'description' => __('Pick the rule that best matches how you grade completion (lessons only, lessons + quizzes, a percentage, or staff marking done by hand).', 'sikshya'),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 'all_lessons',
                        'options' => [
                            'all_lessons' => __('All lessons completed', 'sikshya'),
                            'all_lessons_quizzes' => __('All lessons and quizzes', 'sikshya'),
                            'percentage' => __('Percentage based', 'sikshya'),
                            'manual' => __('Manual completion', 'sikshya'),
                        ],
                    ],
                    [
                        'key' => 'completion_percentage',
                        'type' => 'number',
                        'label' => __('Minimum progress (%)', 'sikshya'),
                        'description' => __('Only used when you chose percentage-based completion: the learner must reach at least this much of the course.', 'sikshya'),
                        'placeholder' => __('e.g. 80', 'sikshya'),
                        'default' => 80,
                        'min' => 1,
                        'max' => 100,
                    ],
                ],
            ],
            [
                'section_key' => 'enrollment_limits',
                'title' => __('Enrollment limits', 'sikshya'),
                'icon' => 'fas fa-users',
                'description' => __(
                    'Optional caps on class size, how long access lasts, and how many courses one account may take at once. Use 0 where it means “no limit”.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'max_students_per_course',
                        'type' => 'number',
                        'label' => __('Maximum learners per course', 'sikshya'),
                        'description' => __('Stops new sign-ups when the class is full. 0 means no cap.', 'sikshya'),
                        'placeholder' => __('0 = unlimited', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 10000
                    ],
                    [
                        'key' => 'enrollment_expiry_days',
                        'type' => 'number',
                        'label' => __('Access length after enroll (days)', 'sikshya'),
                        'description' => __('After this many days from enrollment, access can end (depending on your setup). 0 usually means lifetime access—confirm with your theme or extensions.', 'sikshya'),
                        'placeholder' => __('0 = no expiry', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 3650
                    ],
                    [
                        'key' => 'max_courses_per_student',
                        'type' => 'number',
                        'label' => __('Maximum active courses per student', 'sikshya'),
                        'description' => __('Limit how many courses one person may be enrolled in at the same time. 0 means unlimited.', 'sikshya'),
                        'placeholder' => __('0 = unlimited', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 1000
                    ]
                ]
            ],
            [
                'section_key' => 'enrollment_unenroll',
                'title' => __('Unenrollment', 'sikshya'),
                'icon' => 'fas fa-sign-out-alt',
                'description' => __(
                    'Whether learners can leave a course on their own, and what happens to payments.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'allow_unenroll',
                        'type' => 'checkbox',
                        'label' => __('Let students leave (unenroll)', 'sikshya'),
                        'description' => __('If enabled, learners can remove themselves from the course roster when your theme provides that control.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'unenroll_refund',
                        'type' => 'checkbox',
                        'label' => __('Try to refund automatically when they leave', 'sikshya'),
                        'description' => __('When supported by the payment method, issue a refund if someone unenrolls. Verify behavior with your gateway.', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'unenroll_deadline_days',
                        'type' => 'number',
                        'label' => __('Days after signup they can still drop', 'sikshya'),
                        'description' => __('After this many days from enrollment, self-service unenroll may be disabled. Use 0 only if your policy allows dropping anytime.', 'sikshya'),
                        'placeholder' => __('e.g. 7', 'sikshya'),
                        'default' => 7,
                        'min' => 0,
                        'max' => 365
                    ]
                ]
            ],
            [
                'section_key' => 'enrollment_periods',
                'title' => __('Enrollment periods', 'sikshya'),
                'icon' => 'fas fa-calendar-alt',
                'description' => __(
                    'Optional default window when new students may join. Individual courses can still override these dates.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_enrollment_periods',
                        'type' => 'checkbox',
                        'label' => __('Limit enrollment to date ranges', 'sikshya'),
                        'description' => __('Turn on to use start/end times below as site-wide defaults for new enrollments.', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'default_enrollment_start',
                        'type' => 'datetime-local',
                        'label' => __('Default enrollment opens', 'sikshya'),
                        'description' => __('First moment sign-up is allowed, using your WordPress timezone. Leave empty for no default start.', 'sikshya'),
                        'placeholder' => __('YYYY-MM-DD — pick from calendar', 'sikshya'),
                        'default' => ''
                    ],
                    [
                        'key' => 'default_enrollment_end',
                        'type' => 'datetime-local',
                        'label' => __('Default enrollment closes', 'sikshya'),
                        'description' => __('Last moment new students can join. Leave empty for no default end.', 'sikshya'),
                        'placeholder' => __('YYYY-MM-DD — pick from calendar', 'sikshya'),
                        'default' => ''
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Payment Settings
     *
     * @return array
     */
    protected function getPaymentSettings(): array
    {
        return [
            [
                'section_key' => 'payment_gateways',
                'title' => __('Payment Gateways', 'sikshya'),
                'icon' => 'fas fa-credit-card',
                'description' => __(
                    'Choose how students pay. Turn each method on below and paste keys from your payment provider’s dashboard. Extra gateways need the commercial add-on and an eligible plan.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'payment_gateway',
                        'type' => 'select',
                        'label' => __('Preferred gateway', 'sikshya'),
                        'description' => __(
                            'A default for the system; checkout may still show every gateway you enable and configure.',
                            'sikshya'
                        ),
                        'default' => 'offline',
                        'options' => [
                            '' => __('Select Gateway', 'sikshya'),
                            'offline' => __('Offline / manual', 'sikshya'),
                            'paypal' => __('PayPal', 'sikshya'),
                            'stripe' => __('Stripe (add-on)', 'sikshya'),
                            'razorpay' => __('Razorpay (add-on)', 'sikshya'),
                            'mollie' => __('Mollie (add-on)', 'sikshya'),
                            'paystack' => __('Paystack (add-on)', 'sikshya'),
                            'square' => __('Square (add-on)', 'sikshya'),
                            'authorize_net' => __('Authorize.Net (add-on)', 'sikshya'),
                            'bank_transfer' => __('Bank transfer (add-on)', 'sikshya'),
                        ]
                    ],
                    [
                        'key' => 'payment_gateways_order',
                        'type' => 'text',
                        'label' => __('Gateway order (advanced)', 'sikshya'),
                        'description' => __(
                            'Optional internal order for checkout. Usually leave blank unless support gave you a specific list.',
                            'sikshya'
                        ),
                        'placeholder' => __('e.g. stripe,paypal', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'enable_offline_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable offline / manual payment at checkout', 'sikshya'),
                        'description' => __(
                            'Shows “Offline payment” on checkout (no API keys). Add clear instructions below (bank details, reference format, and how to send proof).',
                            'sikshya'
                        ),
                        'default' => true
                    ],
                    [
                        'key' => 'enable_paypal_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable PayPal at checkout', 'sikshya'),
                        'description' => __(
                            'Shows PayPal on checkout when enabled and configured. Choose “Simple” (email + IPN) or “Advanced” (REST API capture).',
                            'sikshya'
                        ),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_stripe_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Stripe at checkout (add-on)', 'sikshya'),
                        'description' => __(
                            'Shows Stripe on checkout when the commercial add-on is active, this is enabled, and API keys are added below.',
                            'sikshya'
                        ),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_razorpay_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Razorpay at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Add your Razorpay API keys below to accept payments.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_mollie_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Mollie at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Add your Mollie API key below to accept payments.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_paystack_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Paystack at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Add your Paystack API keys below to accept payments.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_square_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Square at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Add your Square credentials below to accept payments.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_authorize_net_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Authorize.Net at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Add your Authorize.Net API credentials below to accept payments.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'enable_bank_transfer_payment',
                        'type' => 'checkbox',
                        'label' => __('Enable Bank Transfer at checkout (add-on)', 'sikshya'),
                        'description' => __('Requires the commercial add-on and plan. Show structured bank transfer details after checkout.', 'sikshya'),
                        'default' => false,
                    ],
                    [
                        'key' => 'offline_payment_instructions',
                        'type' => 'textarea',
                        'label' => __('Offline payment instructions', 'sikshya'),
                        'description' => __(
                            'Tell the buyer exactly how to pay offline: bank name, account number, what to put in the reference field, and who to email the receipt to. Simple HTML is allowed.',
                            'sikshya'
                        ),
                        'placeholder' => __('e.g. Pay to: … Reference: your order number …', 'sikshya'),
                        'default' => ''
                    ],
                    [
                        'key' => 'offline_payment_auto_fulfill',
                        'type' => 'checkbox',
                        'label' => __('Auto-enroll after offline checkout', 'sikshya'),
                        'description' => __(
                            'When enabled, learners are enrolled immediately after choosing offline payment (honor system). When disabled, orders stay on hold until an administrator marks them paid under Sikshya → Orders.',
                            'sikshya'
                        ),
                        'default' => false
                    ],
                    [
                        'key' => 'paypal_client_id',
                        'type' => 'text',
                        'label' => __('PayPal — Client ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: PayPal developer dashboard link */
                            __('For “Advanced (REST API)”. Copy from your PayPal REST app: %s → My Apps & Credentials → Client ID. (Public key, safe to expose to the browser.)', 'sikshya'),
                            '<a href="https://developer.paypal.com/dashboard/applications/live" target="_blank" rel="noopener noreferrer">developer.paypal.com</a>'
                        ),
                        'placeholder' => __('Starts with A…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'paypal_integration_mode',
                        'type' => 'select',
                        'label' => __('PayPal — Integration mode', 'sikshya'),
                        'description' => __(
                            'Simple: PayPal email + IPN (no REST API). Advanced: REST API capture (Client ID + Secret) and stronger verification.',
                            'sikshya'
                        ),
                        'select_placeholder' => __('Choose one…', 'sikshya'),
                        'default' => 'advanced',
                        'options' => [
                            'simple' => __('Simple (email + IPN)', 'sikshya'),
                            'advanced' => __('Advanced (REST API)', 'sikshya'),
                        ],
                    ],
                    [
                        'key' => 'paypal_email',
                        'type' => 'email',
                        'label' => __('PayPal — Email (Simple mode)', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: PayPal account link */
                            __('For “Simple (email + IPN)”. Use your PayPal Business account email. You can verify it in your PayPal profile: %s.', 'sikshya'),
                            '<a href="https://www.paypal.com/myaccount/settings/" target="_blank" rel="noopener noreferrer">paypal.com</a>'
                        ),
                        'placeholder' => 'your-paypal@example.com',
                        'default' => '',
                    ],
                    [
                        'key' => 'paypal_secret',
                        'type' => 'password',
                        'label' => __('PayPal — Secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: PayPal developer dashboard link */
                            __('For “Advanced (REST API)”. Copy from the same REST app as the Client ID: %s → My Apps & Credentials → Secret. Keep this private.', 'sikshya'),
                            '<a href="https://developer.paypal.com/dashboard/applications/live" target="_blank" rel="noopener noreferrer">developer.paypal.com</a>'
                        ),
                        'placeholder' => __('Paste secret from PayPal', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'paypal_webhook_id',
                        'type' => 'text',
                        'label' => __('PayPal — Webhook ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: PayPal webhooks link */
                            __('Optional (Advanced mode). Create a webhook in your PayPal app and paste the Webhook ID here to strengthen verification: %s.', 'sikshya'),
                            '<a href="https://developer.paypal.com/dashboard/applications/live" target="_blank" rel="noopener noreferrer">PayPal app settings</a>'
                        ),
                        'placeholder' => __('Webhook ID if you use instant payment notifications', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'stripe_publishable_key',
                        'type' => 'text',
                        'label' => __('Stripe — Publishable key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Stripe API keys link */
                            __('Copy from %s → Developers → API keys. Use pk_test_… for testing and pk_live_… for production.', 'sikshya'),
                            '<a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">dashboard.stripe.com</a>'
                        ),
                        'placeholder' => 'pk_test_...',
                        'default' => '',
                    ],
                    [
                        'key' => 'stripe_secret_key',
                        'type' => 'password',
                        'label' => __('Stripe — Secret key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Stripe API keys link */
                            __('Copy from %s → Developers → API keys. Use sk_test_… for testing and sk_live_… for production. Keep this private.', 'sikshya'),
                            '<a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">dashboard.stripe.com</a>'
                        ),
                        'placeholder' => 'sk_test_...',
                        'default' => '',
                    ],
                    [
                        'key' => 'stripe_webhook_secret',
                        'type' => 'password',
                        'label' => __('Stripe — Webhook secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Stripe webhooks link */
                            __('Optional but recommended. Create a webhook endpoint in %s and copy the signing secret (whsec_…).', 'sikshya'),
                            '<a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener noreferrer">Stripe webhooks</a>'
                        ),
                        'placeholder' => 'whsec_...',
                        'default' => '',
                    ],
                    [
                        'key' => 'razorpay_key_id',
                        'type' => 'text',
                        'label' => __('Razorpay — Key ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Razorpay API keys link */
                            __('Copy from %s → Settings → API Keys. Key ID is public (rzp_test_… / rzp_live_…).', 'sikshya'),
                            '<a href="https://dashboard.razorpay.com/app/keys" target="_blank" rel="noopener noreferrer">dashboard.razorpay.com</a>'
                        ),
                        'placeholder' => __('rzp_live_… or rzp_test_…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'razorpay_key_secret',
                        'type' => 'password',
                        'label' => __('Razorpay — Key secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Razorpay API keys link */
                            __('Copy from the same page as Key ID: %s → Settings → API Keys. Keep this private.', 'sikshya'),
                            '<a href="https://dashboard.razorpay.com/app/keys" target="_blank" rel="noopener noreferrer">dashboard.razorpay.com</a>'
                        ),
                        'placeholder' => __('Paste key secret', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'razorpay_webhook_secret',
                        'type' => 'password',
                        'label' => __('Razorpay — Webhook secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Razorpay webhooks link */
                            __('Optional but recommended. Create a webhook in %s and paste the webhook secret to verify events.', 'sikshya'),
                            '<a href="https://dashboard.razorpay.com/app/webhooks" target="_blank" rel="noopener noreferrer">Razorpay webhooks</a>'
                        ),
                        'placeholder' => __('Webhook signing secret', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'mollie_api_key',
                        'type' => 'password',
                        'label' => __('Mollie — API key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Mollie API keys link */
                            __('Copy from %s → Developers → API keys. Use test_… for testing and live_… for production. Keep this private.', 'sikshya'),
                            '<a href="https://my.mollie.com/dashboard/developers/api-keys" target="_blank" rel="noopener noreferrer">my.mollie.com</a>'
                        ),
                        'placeholder' => __('live_… or test_…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'mollie_payment_methods',
                        'type' => 'text',
                        'label' => __('Mollie — Payment methods (optional)', 'sikshya'),
                        'description' => __(
                            'Optional. Comma-separated method IDs to limit which methods Mollie shows (example: creditcard,ideal,paypal). Leave blank to let Mollie decide.',
                            'sikshya'
                        ),
                        'placeholder' => 'creditcard,ideal,paypal',
                        'default' => 'creditcard,ideal,paypal',
                    ],
                    [
                        'key' => 'mollie_webhook_secret',
                        'type' => 'password',
                        'label' => __('Mollie — Webhook secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Mollie webhooks link */
                            __('Optional. If you enable Mollie webhooks, paste the webhook signing secret here to verify events. Manage webhooks in %s.', 'sikshya'),
                            '<a href="https://my.mollie.com/dashboard/developers/webhooks" target="_blank" rel="noopener noreferrer">my.mollie.com</a>'
                        ),
                        'placeholder' => __('Optional webhook secret', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'paystack_public_key',
                        'type' => 'text',
                        'label' => __('Paystack — Public key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Paystack API keys link */
                            __('Copy from %s → Settings → API Keys & Webhooks → Public key (pk_test_… / pk_live_…).', 'sikshya'),
                            '<a href="https://dashboard.paystack.com/#/settings/developer" target="_blank" rel="noopener noreferrer">dashboard.paystack.com</a>'
                        ),
                        'placeholder' => __('pk_test_… or pk_live_…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'paystack_payment_channels',
                        'type' => 'text',
                        'label' => __('Paystack — Payment channels (optional)', 'sikshya'),
                        'description' => __(
                            'Optional. Comma-separated channels to limit which payment methods Paystack shows (example: card,bank,ussd). Leave blank to allow Paystack defaults.',
                            'sikshya'
                        ),
                        'placeholder' => 'card,bank,ussd',
                        'default' => 'card,bank,ussd',
                    ],
                    [
                        'key' => 'paystack_secret_key',
                        'type' => 'password',
                        'label' => __('Paystack — Secret key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Paystack API keys link */
                            __('Copy from %s → Settings → API Keys & Webhooks → Secret key (sk_test_… / sk_live_…). Keep this private.', 'sikshya'),
                            '<a href="https://dashboard.paystack.com/#/settings/developer" target="_blank" rel="noopener noreferrer">dashboard.paystack.com</a>'
                        ),
                        'placeholder' => __('sk_test_… or sk_live_…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'paystack_webhook_secret',
                        'type' => 'password',
                        'label' => __('Paystack — Webhook secret', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Paystack webhooks link */
                            __('Optional but recommended. Configure a webhook URL in %s and paste the webhook secret / signature key here to verify events.', 'sikshya'),
                            '<a href="https://dashboard.paystack.com/#/settings/developer" target="_blank" rel="noopener noreferrer">Paystack settings</a>'
                        ),
                        'placeholder' => __('Webhook secret if configured', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'square_application_id',
                        'type' => 'text',
                        'label' => __('Square — Application ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Square developer dashboard link */
                            __('Copy from %s → Applications → your app → Credentials → Application ID.', 'sikshya'),
                            '<a href="https://developer.squareup.com/apps" target="_blank" rel="noopener noreferrer">developer.squareup.com</a>'
                        ),
                        'placeholder' => __('sandbox-sq0idb-… or sq0idp-…', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'square_access_token',
                        'type' => 'password',
                        'label' => __('Square — Access token', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Square developer dashboard link */
                            __('Copy from %s → Applications → your app → Credentials → Access token. Use Sandbox token while testing. Keep this private.', 'sikshya'),
                            '<a href="https://developer.squareup.com/apps" target="_blank" rel="noopener noreferrer">developer.squareup.com</a>'
                        ),
                        'placeholder' => __('EAAA… or sandbox token', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'square_location_id',
                        'type' => 'text',
                        'label' => __('Square — Location ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Square locations link */
                            __('Choose the Square location that receives payments. Copy the Location ID from %s.', 'sikshya'),
                            '<a href="https://squareup.com/dashboard/locations" target="_blank" rel="noopener noreferrer">Square Dashboard → Locations</a>'
                        ),
                        'placeholder' => __('e.g. LXXXXXXX', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'square_webhook_signature_key',
                        'type' => 'password',
                        'label' => __('Square — Webhook signature key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Square webhooks docs link */
                            __('Optional but recommended. In the Square Developer Dashboard, create a webhook subscription and copy the “Signature key” here to verify events. Docs: %s.', 'sikshya'),
                            '<a href="https://developer.squareup.com/docs/webhooks/overview" target="_blank" rel="noopener noreferrer">Square webhooks</a>'
                        ),
                        'placeholder' => __('Signature key from Square webhooks', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'authorize_net_login_id',
                        'type' => 'text',
                        'label' => __('Authorize.Net — API Login ID', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Authorize.Net docs link */
                            __('Copy from your Authorize.Net Merchant Interface → Account → API Credentials & Keys. Docs: %s.', 'sikshya'),
                            '<a href="https://developer.authorize.net/api/reference/features/authentication.html" target="_blank" rel="noopener noreferrer">developer.authorize.net</a>'
                        ),
                        'placeholder' => __('Your API Login ID', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'authorize_net_public_client_key',
                        'type' => 'text',
                        'label' => __('Authorize.Net — Public client key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Accept.js link */
                            __('Public key used for Accept.js tokenization. Copy from API Credentials & Keys. Learn more: %s.', 'sikshya'),
                            '<a href="https://developer.authorize.net/api/reference/features/acceptjs.html" target="_blank" rel="noopener noreferrer">Accept.js docs</a>'
                        ),
                        'placeholder' => __('Public client key', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'authorize_net_transaction_key',
                        'type' => 'password',
                        'label' => __('Authorize.Net — Transaction key', 'sikshya'),
                        'description' => __('Secret credential from API Credentials & Keys. Keep this private.', 'sikshya'),
                        'placeholder' => __('Transaction key', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'authorize_net_signature_key',
                        'type' => 'password',
                        'label' => __('Authorize.Net — Signature key', 'sikshya'),
                        'description' => sprintf(
                            /* translators: %s: Authorize.Net webhooks docs link */
                            __('Optional. Use if your setup verifies Authorize.Net webhooks / silent posts. Docs: %s.', 'sikshya'),
                            '<a href="https://developer.authorize.net/api/reference/features/webhooks.html" target="_blank" rel="noopener noreferrer">Authorize.Net webhooks</a>'
                        ),
                        'placeholder' => __('Optional signature key', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'bank_transfer_bank_name',
                        'type' => 'text',
                        'label' => __('Bank transfer — Bank name', 'sikshya'),
                        'description' => __('Shown to the buyer after checkout (example: “HSBC”, “Chase”, “Nabil Bank”).', 'sikshya'),
                        'placeholder' => __('e.g. HSBC', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'bank_transfer_account_name',
                        'type' => 'text',
                        'label' => __('Bank transfer — Account name', 'sikshya'),
                        'description' => __('Account holder name shown to the buyer (your business / organization name).', 'sikshya'),
                        'placeholder' => __('e.g. Sikshya Academy', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'bank_transfer_account_number',
                        'type' => 'text',
                        'label' => __('Bank transfer — Account number', 'sikshya'),
                        'description' => __('Bank account number or IBAN shown to the buyer.', 'sikshya'),
                        'placeholder' => __('Account number / IBAN', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'bank_transfer_routing_code',
                        'type' => 'text',
                        'label' => __('Bank transfer — Routing / SWIFT code', 'sikshya'),
                        'description' => __('Routing number (US) or SWIFT/BIC code (international), if applicable.', 'sikshya'),
                        'placeholder' => __('SWIFT/BIC', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'bank_transfer_instructions',
                        'type' => 'textarea',
                        'label' => __('Bank transfer — Instructions', 'sikshya'),
                        'description' => __(
                            'Shown after checkout. Tell buyers exactly what to do: transfer amount, account details, what to use as the reference (recommend: order number), and where to send proof. Basic HTML allowed.',
                            'sikshya'
                        ),
                        'placeholder' => __('e.g. Transfer to … use order ID as reference …', 'sikshya'),
                        'default' => '',
                    ],
                    [
                        'key' => 'enable_test_mode',
                        'type' => 'checkbox',
                        'label' => __('Test mode (sandbox)', 'sikshya'),
                        'description' => __(
                            'Use sandbox/test credentials while testing checkout. For Stripe/PayPal/Razorpay/Mollie/Paystack/Square, “test vs live” is ultimately determined by the keys you paste (e.g. sk_test_ vs sk_live_).',
                            'sikshya'
                        ),
                        'default' => true
                    ],
                    [
                        'key' => 'accept_credit_cards',
                        'type' => 'checkbox',
                        'label' => __('Offer card payments', 'sikshya'),
                        'description' => __(
                            'Reserved for future use. Actual card acceptance is controlled by each gateway (e.g. Stripe, PayPal). Sikshya does not use this toggle.',
                            'sikshya'
                        ),
                        'default' => true
                    ],
                    [
                        'key' => 'accept_bank_transfer',
                        'type' => 'checkbox',
                        'label' => __('Offer bank transfer', 'sikshya'),
                        'description' => __(
                            'Reserved for future use. Use “Enable Bank Transfer at checkout (add-on)” and Bank transfer instructions instead. Sikshya does not use this toggle.',
                            'sikshya'
                        ),
                        'default' => false
                    ],
                    [
                        'key' => 'accept_digital_wallets',
                        'type' => 'checkbox',
                        'label' => __('Offer digital wallets', 'sikshya'),
                        'description' => __(
                            'Reserved for future use. Wallet support depends on the gateway you enable. Sikshya does not use this toggle.',
                            'sikshya'
                        ),
                        'default' => false
                    ],
                    [
                        'key' => 'accept_cryptocurrency',
                        'type' => 'checkbox',
                        'label' => __('Offer cryptocurrency', 'sikshya'),
                        'description' => __(
                            'Reserved for future use. Sikshya does not use this toggle.',
                            'sikshya'
                        ),
                        'default' => false
                    ]
                ]
            ],
            [
                'title' => __('Pricing & Taxes', 'sikshya'),
                'icon' => 'fas fa-percentage',
                'description' => __(
                    'Simple tax defaults for course prices. For complex VAT rules, confirm with your accountant or a tax extension.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'tax_rate',
                        'type' => 'number',
                        'label' => __('Tax rate (%)', 'sikshya'),
                        'description' => __('Percent added to the price (or included in it, see next option). Use 0 if you do not charge tax.', 'sikshya'),
                        'placeholder' => __('e.g. 0 or 8.25', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.01
                    ],
                    [
                        'key' => 'tax_inclusive',
                        'type' => 'checkbox',
                        'label' => __('Prices already include tax', 'sikshya'),
                        'description' => __(
                            'On: the listed course price contains tax. Off: tax is calculated on top of the listed price.',
                            'sikshya'
                        ),
                        'default' => false
                    ]
                ]
            ],
            [
                'title' => __('Discounts & Coupons', 'sikshya'),
                'icon' => 'fas fa-tags',
                'description' => __(
                    'Promo codes reduce the price at checkout. Set sensible limits so staff cannot create 100% off codes by mistake.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_coupons',
                        'type' => 'checkbox',
                        'label' => __('Allow coupon codes', 'sikshya'),
                        'description' => __('Learners can enter a code at checkout to get a discount.', 'sikshya'),
                        'default' => false
                    ],
                    [
                        'key' => 'max_discount_percentage',
                        'type' => 'number',
                        'label' => __('Largest discount allowed (%)', 'sikshya'),
                        'description' => __('No coupon can reduce the price by more than this percentage.', 'sikshya'),
                        'placeholder' => __('e.g. 50', 'sikshya'),
                        'default' => 50,
                        'min' => 0,
                        'max' => 100
                    ],
                    [
                        'key' => 'coupon_expiry_days',
                        'type' => 'number',
                        'label' => __('Default coupon lifetime (days)', 'sikshya'),
                        'description' => __('When you create a new coupon, it can expire after this many days unless you override it.', 'sikshya'),
                        'placeholder' => __('e.g. 30', 'sikshya'),
                        'default' => 30,
                        'min' => 1,
                        'max' => 365
                    ]
                ]
            ],
            [
                'title' => __('Invoicing & Receipts', 'sikshya'),
                'icon' => 'fas fa-receipt',
                'description' => __(
                    'Paper trail for accounting: invoices and invoice numbering. To email the buyer after a successful payment, use Sikshya → Email hub → Email templates (“Payment receipt”).',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'auto_generate_invoices',
                        'type' => 'checkbox',
                        'label' => __('Create invoices automatically', 'sikshya'),
                        'description' => __('Generate a numbered invoice when a payment succeeds (if your setup supports it).', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'invoice_prefix',
                        'type' => 'text',
                        'label' => __('Invoice number prefix', 'sikshya'),
                        'description' => __('Text before the automatic number (for example INV-2026-0001).', 'sikshya'),
                        'placeholder' => __('INV-', 'sikshya'),
                        'default' => 'INV-'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Certificates Settings
     *
     * @return array
     */
    protected function getCertificatesSettings(): array
    {
        return [
            [
                'title' => __('Certificate Settings', 'sikshya'),
                'icon' => 'fas fa-certificate',
                'description' => __(
                    'Site-wide switches for issuing certificates. Visual layout, colors, and merge fields are edited per template in Sikshya → Certificates (template builder), not here.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_certificates',
                        'type' => 'checkbox',
                        'label' => __('Issue completion certificates', 'sikshya'),
                        'description' => __('When off, Sikshya will not create or show certificates (only turn off if you truly do not need them).', 'sikshya'),
                        'default' => true
                    ],
                ]
            ],
            [
                'title' => __('Certificate Behavior', 'sikshya'),
                'icon' => 'fas fa-cog',
                'description' => __(
                    'Automation for PDF generation and expiry. Sending certificates by email is configured under Sikshya → Email → Delivery.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'auto_generate_certificates',
                        'type' => 'checkbox',
                        'label' => __('Create certificate when course is finished', 'sikshya'),
                        'description' => __('As soon as a learner meets your completion rules, generate their certificate.', 'sikshya'),
                        'default' => true
                    ],
                    [
                        'key' => 'certificate_expiry_days',
                        'type' => 'number',
                        'label' => __('Certificate validity (days)', 'sikshya'),
                        'description' => __('Some programs require re-certification after a period. 0 means the credential does not expire.', 'sikshya'),
                        'placeholder' => __('0 = never expires', 'sikshya'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 3650
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Email Settings
     *
     * @return array
     */
    protected function getEmailSettings(): array
    {
        $wp_admin = trim((string) get_option('admin_email', ''));
        $stored_main = trim((string) Settings::get('admin_email', ''));
        $main_default = ($stored_main !== '' && is_email($stored_main)) ? $stored_main : ($wp_admin !== '' ? $wp_admin : '');

        return [
            [
                'section_key' => 'email_config',
                'title' => __('Addresses & sender identity', 'sikshya'),
                'icon' => 'fas fa-envelope',
                'description' => __(
                    'Platform contact addresses and how outbound mail appears. Match your sending domain where possible.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'admin_email',
                        'type' => 'email',
                        'label' => __('Main LMS contact email', 'sikshya'),
                        'description' => __(
                            'Used as a fallback when “Where to send admin notices” is empty (merge tag {{admin_email}} and plugin notices).',
                            'sikshya'
                        ),
                        'placeholder' => __('you@example.com', 'sikshya'),
                        'default' => $main_default !== '' ? $main_default : $wp_admin,
                        'required' => false,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => function ($value) {
                            return $value === '' || $value === null || is_email((string) $value)
                                ? true
                                : __('Please enter a valid email address.', 'sikshya');
                        },
                    ],
                    [
                        'key' => 'admin_notification_email',
                        'type' => 'email',
                        'label' => __('Where to send admin notices', 'sikshya'),
                        'description' => __('Orders, enrollment alerts, and similar messages go here.', 'sikshya'),
                        'placeholder' => 'admin@yoursite.com',
                        'default' => $main_default !== '' ? $main_default : $wp_admin,
                    ],
                    [
                        'key' => 'from_email',
                        'type' => 'email',
                        'label' => __('“From” address for learners', 'sikshya'),
                        'description' => __('What students see in the sender field. Use an address on your domain if possible.', 'sikshya'),
                        'placeholder' => 'noreply@yoursite.com',
                        'default' => $main_default !== '' ? $main_default : $wp_admin,
                    ],
                    [
                        'key' => 'from_name',
                        'type' => 'text',
                        'label' => __('“From” name for learners', 'sikshya'),
                        'description' => __('Friendly label next to the address (your brand or site name).', 'sikshya'),
                        'placeholder' => 'Your LMS Name',
                        'default' => get_bloginfo('name'),
                    ],
                    [
                        'key' => 'reply_to_email',
                        'type' => 'email',
                        'label' => __('Reply address (optional)', 'sikshya'),
                        'description' => __('When a student hits “Reply”, mail goes here—often support or helpdesk.', 'sikshya'),
                        'placeholder' => 'support@yoursite.com',
                        'default' => $main_default !== '' ? $main_default : $wp_admin,
                    ],
                ],
            ],
            [
                'section_key' => 'email_master_switches',
                'title' => __('Sending rules', 'sikshya'),
                'icon' => 'fas fa-paper-plane',
                'description' => __(
                    'Global toggles that affect Sikshya’s automated messages. Subject and body for each trigger are edited under Email → Email templates.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_email_notifications',
                        'type' => 'checkbox',
                        'label' => __('Allow Sikshya to send transactional email', 'sikshya'),
                        'description' => __(
                            'When off, Sikshya will not enqueue or send LMS emails (welcome, receipts, moderation, drip, certificates, etc.). Does not disable marketing automations.',
                            'sikshya'
                        ),
                        'default' => '1',
                    ],
                ],
            ],
            [
                'section_key' => 'email_certificate_delivery',
                'title' => __('Completion certificates', 'sikshya'),
                'icon' => 'fas fa-certificate',
                'description' => __(
                    'Uses the issuance settings under Sikshya → Settings → Certificates. Turning this off stops the learner certificate-email only; downloading from the profile may still work.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'email_certificates',
                        'type' => 'checkbox',
                        'label' => __('Email the certificate to the learner', 'sikshya'),
                        'description' => __('Sends a download link or attachment when the certificate is ready.', 'sikshya'),
                        'default' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Instructors Settings
     *
     * @return array
     */
    protected function getInstructorsSettings(): array
    {
        return [
            [
                'title' => __('Instructor Permissions', 'sikshya'),
                'icon' => 'fas fa-chalkboard-teacher',
                'description' => __(
                    'Control what teachers with the instructor role may do. Tighter permissions reduce accidents on shared sites.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'instructors_can_create_courses',
                        'type' => 'checkbox',
                        'label' => __('Teachers can create new courses', 'sikshya'),
                        'description' => __('Allow adding blank courses from the dashboard.', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'instructors_can_edit_courses',
                        'type' => 'checkbox',
                        'label' => __('Teachers can edit their own courses', 'sikshya'),
                        'description' => __('Lessons, pricing, and settings for courses they own.', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'instructors_can_delete_courses',
                        'type' => 'checkbox',
                        'label' => __('Teachers can delete their own courses', 'sikshya'),
                        'description' => __('Dangerous on production—only enable if you trust every instructor.', 'sikshya'),
                        'default' => '0'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Students Settings
     *
     * @return array
     */
    protected function getStudentsSettings(): array
    {
        return [
            [
                'title' => __('Student Features', 'sikshya'),
                'icon' => 'fas fa-users',
                'description' => __(
                    'What enrolled learners see in their account: progress bars and certificate downloads.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'students_can_see_progress',
                        'type' => 'checkbox',
                        'label' => __('Show progress to learners', 'sikshya'),
                        'description' => __('Bars or percentages for lessons and quizzes completed.', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'students_can_download_certificates',
                        'type' => 'checkbox',
                        'label' => __('Let learners download certificates', 'sikshya'),
                        'description' => __('PDF or image files for completed courses from their profile.', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Quizzes Settings
     *
     * @return array
     */
    protected function getQuizzesSettings(): array
    {
        return [
            [
                'title' => __('Quiz Settings', 'sikshya'),
                'icon' => 'fas fa-question-circle',
                'description' => __(
                    'Defaults for new quizzes. You can still change each quiz individually when editing.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'quiz_time_limit',
                        'type' => 'number',
                        'label' => __('Default time limit (minutes)', 'sikshya'),
                        'description' => __('How long the learner has to submit once they start. 0 means no timer.', 'sikshya'),
                        'placeholder' => __('e.g. 30', 'sikshya'),
                        'default' => 30,
                        'min' => 0
                    ],
                    [
                        'key' => 'quiz_attempts_limit',
                        'type' => 'number',
                        'label' => __('Default number of attempts', 'sikshya'),
                        'description' => __('How many times they may retake the quiz. 0 means unlimited tries.', 'sikshya'),
                        'placeholder' => __('e.g. 3', 'sikshya'),
                        'default' => 3,
                        'min' => 0
                    ],
                    [
                        'key' => 'show_quiz_results',
                        'type' => 'checkbox',
                        'label' => __('Show score after submission', 'sikshya'),
                        'description' => __('Let students see correct answers or scores when they finish (per-quiz settings may add detail).', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Assignments Settings
     *
     * @return array
     */
    protected function getAssignmentsSettings(): array
    {
        return [
            [
                'title' => __('Assignment Settings', 'sikshya'),
                'icon' => 'fas fa-tasks',
                'description' => __(
                    'When learners upload homework, these limits reduce oversized or risky files.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'assignment_file_types',
                        'type' => 'text',
                        'label' => __('Allowed file extensions', 'sikshya'),
                        'description' => __('Comma-separated, no dots: only these types can be uploaded (lowercase).', 'sikshya'),
                        'default' => 'pdf,doc,docx,txt,jpg,jpeg,png',
                        'placeholder' => __('pdf,doc,docx,txt,jpg,jpeg,png', 'sikshya')
                    ],
                    [
                        'key' => 'assignment_max_file_size',
                        'type' => 'number',
                        'label' => __('Largest upload for one assignment (MB)', 'sikshya'),
                        'description' => __('Per submission cap. Your host may force a lower maximum. Separate from “Largest upload size” under General.', 'sikshya'),
                        'placeholder' => __('e.g. 10', 'sikshya'),
                        'default' => 10,
                        'min' => 1,
                        'max' => 100
                    ]
                ]
            ]
        ];
    }

    /**
     * Quiz and assignment progress toggles. Lesson progress lives under {@see getLessonsSettings()}.
     *
     * @return array
     */
    protected function getProgressSettings(): array
    {
        return [
            [
                'title' => __('Progress Tracking', 'sikshya'),
                'icon' => 'fas fa-chart-line',
                'description' => __(
                    'Whether Sikshya records quiz and assignment activity for reports and completion rules. Lesson progress is under Lessons settings.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'track_quiz_progress',
                        'type' => 'checkbox',
                        'label' => __('Record quiz completion and scores', 'sikshya'),
                        'description' => __('Needed for gradebooks and “complete all quizzes” type rules.', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'track_assignment_progress',
                        'type' => 'checkbox',
                        'label' => __('Record assignment uploads and grades', 'sikshya'),
                        'description' => __('Tracks submitted files and instructor feedback.', 'sikshya'),
                        'default' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Notifications Settings
     *
     * @return array
     */
    protected function getNotificationsSettings(): array
    {
        return [
            [
                'title' => __('Notification Settings', 'sikshya'),
                'icon' => 'fas fa-bell',
                'description' => __(
                    'In-dashboard and browser cues. LMS email sending (receipts, enrollments, etc.) lives under Sikshya → Email → Delivery.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_browser_notifications',
                        'type' => 'checkbox',
                        'label' => __('Browser pop-up notices', 'sikshya'),
                        'description' => __('Short messages in the browser when the tab is open—learners must allow permission.', 'sikshya'),
                        'default' => '0'
                    ],
                ]
            ]
        ];
    }

    /**
     * Get Integrations Settings
     *
     * @return array
     */
    protected function getIntegrationsSettings(): array
    {
        return [
            [
                'title' => __('Third-party Integrations', 'sikshya'),
                'icon' => 'fas fa-plug',
                'description' => __(
                    'Optional marketing tags. Only paste IDs if you use these tools and understand privacy rules (cookies, GDPR).',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'google_analytics_id',
                        'type' => 'text',
                        'label' => __('Google Analytics measurement ID', 'sikshya'),
                        'description' => __('From Google Analytics → Admin → Data streams. Looks like G-XXXXXXXXXX.', 'sikshya'),
                        'placeholder' => __('G-XXXXXXXXXX', 'sikshya')
                    ],
                    [
                        'key' => 'facebook_pixel_id',
                        'type' => 'text',
                        'label' => __('Meta (Facebook) Pixel ID', 'sikshya'),
                        'description' => __('From Meta Events Manager → your Pixel → ID number.', 'sikshya'),
                        'placeholder' => __('XXXXXXXXXX', 'sikshya')
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Security Settings
     *
     * @return array
     */
    protected function getSecuritySettings(): array
    {
        return [
            [
                'title' => __('Security Options', 'sikshya'),
                'icon' => 'fas fa-shield-alt',
                'description' => __(
                    'Reduce bots and idle logins. CAPTCHA needs extra setup in many cases—enable only when spam is a problem.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_captcha',
                        'type' => 'checkbox',
                        'label' => __('Use CAPTCHA on forms', 'sikshya'),
                        'description' => __('Adds “I am human” challenges where the theme supports it—may need API keys elsewhere.', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'session_timeout',
                        'type' => 'number',
                        'label' => __('Log out inactive users after (minutes)', 'sikshya'),
                        'description' => __('For shared computers, shorter times are safer; longer times are more convenient at home.', 'sikshya'),
                        'placeholder' => __('e.g. 120', 'sikshya'),
                        'default' => 120,
                        'min' => 15,
                        'max' => 1440
                    ]
                ]
            ]
        ];
    }

    /**
     * Permalink settings (virtual LMS pages + CPT/taxonomy URL bases).
     *
     * @return array
     */
    protected function getPermalinksSettings(): array
    {
        $defaults = PermalinkService::defaults();
        $slug_validate = function ($value) {
            $s = sanitize_title((string) $value);

            return $s !== '' ? true : __('Enter a valid URL slug.', 'sikshya');
        };

        return [
            [
                'title' => __('Learner pages (URL segment)', 'sikshya'),
                'icon' => 'fas fa-link',
                'description' => __(
                    'Short word in the address bar after your domain (for example …/cart). Use lowercase letters, numbers, and hyphens only. With plain WordPress permalinks, URLs may use query strings instead.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'permalink_cart',
                        'type' => 'text',
                        'label' => __('Cart page slug', 'sikshya'),
                        'description' => __('URL segment for the shopping cart (where items are reviewed before paying).', 'sikshya'),
                        'placeholder' => __('cart', 'sikshya'),
                        'default' => $defaults['permalink_cart'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'permalink_checkout',
                        'type' => 'text',
                        'label' => __('Checkout page slug', 'sikshya'),
                        'description' => __('Where buyers enter payment details.', 'sikshya'),
                        'placeholder' => __('checkout', 'sikshya'),
                        'default' => $defaults['permalink_checkout'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'permalink_account',
                        'type' => 'text',
                        'label' => __('Student account / dashboard slug', 'sikshya'),
                        'description' => __('Where enrolled learners see courses, orders, and profile.', 'sikshya'),
                        'placeholder' => __('account', 'sikshya'),
                        'default' => $defaults['permalink_account'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'permalink_learn',
                        'type' => 'text',
                        'label' => __('Course player / learn area slug', 'sikshya'),
                        'description' => __('Base path for lessons and the course player.', 'sikshya'),
                        'placeholder' => __('learn', 'sikshya'),
                        'default' => $defaults['permalink_learn'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'permalink_order',
                        'type' => 'text',
                        'label' => __('Order receipt slug', 'sikshya'),
                        'description' => __(
                            'Path to the “thank you” / receipt page. The full URL still includes a secret key so orders are not guessable.',
                            'sikshya'
                        ),
                        'placeholder' => __('order', 'sikshya'),
                        'default' => $defaults['permalink_order'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                ],
            ],
            [
                'title' => __('Learn player URL format', 'sikshya'),
                'icon' => 'fas fa-route',
                'description' => __(
                    'Recommended: include a stable ID in lesson URLs so links keep working if you rename a lesson or reuse it in another course.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'learn_permalink_use_public_id',
                        'type' => 'checkbox',
                        'label' => __('Use public_id in Learn URLs (recommended)', 'sikshya'),
                        'description' => __(
                            'Enabled: /learn/lesson/{public_id}/{slug}. Disabled: /learn/lesson/{slug}. Existing links will continue to work via redirects.',
                            'sikshya'
                        ),
                        'default' => '1',
                    ],
                ],
            ],
            [
                'title' => __('Content type bases', 'sikshya'),
                'icon' => 'fas fa-folder-open',
                'description' => __(
                    'WordPress uses these words in URLs for courses, lessons, and related content. After changing, save here and visit Settings → Permalinks in WordPress once if links break.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'rewrite_base_course',
                        'type' => 'text',
                        'label' => __('Course base', 'sikshya'),
                        'description' => __('Appears in course URLs and the course archive (e.g. …/courses/…).', 'sikshya'),
                        'placeholder' => __('courses', 'sikshya'),
                        'default' => $defaults['rewrite_base_course'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'rewrite_base_lesson',
                        'type' => 'text',
                        'label' => __('Lesson base', 'sikshya'),
                        'description' => __('Single lesson addresses contain this word.', 'sikshya'),
                        'placeholder' => __('lessons', 'sikshya'),
                        'default' => $defaults['rewrite_base_lesson'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'rewrite_base_quiz',
                        'type' => 'text',
                        'label' => __('Quiz base', 'sikshya'),
                        'description' => __('Single quiz addresses contain this word.', 'sikshya'),
                        'placeholder' => __('quizzes', 'sikshya'),
                        'default' => $defaults['rewrite_base_quiz'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'rewrite_base_assignment',
                        'type' => 'text',
                        'label' => __('Assignment base', 'sikshya'),
                        'description' => __('Single assignment URLs contain this word.', 'sikshya'),
                        'placeholder' => __('assignments', 'sikshya'),
                        'default' => $defaults['rewrite_base_assignment'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'rewrite_base_certificate',
                        'type' => 'text',
                        'label' => __('Certificate base', 'sikshya'),
                        'description' => __('Public certificate view URLs contain this word.', 'sikshya'),
                        'placeholder' => __('certificates', 'sikshya'),
                        'default' => $defaults['rewrite_base_certificate'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                    [
                        'key' => 'rewrite_base_author',
                        'type' => 'text',
                        'label' => __('Instructor archive base', 'sikshya'),
                        'description' => __('Sikshya instructor course listing URL base (separate from regular WordPress post-author archives).', 'sikshya'),
                        'placeholder' => __('author', 'sikshya'),
                        'default' => $defaults['rewrite_base_author'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                ],
            ],
            [
                'title' => __('Taxonomy base', 'sikshya'),
                'icon' => 'fas fa-folder',
                'description' => __(
                    'URL segment for browsing all courses in a category. Pick a slug that does not clash with normal WordPress pages.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'rewrite_tax_course_category',
                        'type' => 'text',
                        'label' => __('Course category base', 'sikshya'),
                        'description' => __(
                            'Segment for category archive pages (lists of courses in a topic).',
                            'sikshya'
                        ),
                        'placeholder' => __('course-category', 'sikshya'),
                        'default' => $defaults['rewrite_tax_course_category'],
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => $slug_validate,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Advanced Settings
     *
     * @return array
     */
    protected function getAdvancedSettings(): array
    {
        return [
            [
                'title' => __('Advanced Options', 'sikshya'),
                'icon' => 'fas fa-tools',
                'description' => __(
                    'For troubleshooting and performance tuning. Leave debug off on live sites unless support asks for it.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'enable_debug_mode',
                        'type' => 'checkbox',
                        'label' => __('Debug mode (developers)', 'sikshya'),
                        'description' => __('Logs extra detail—can slow the site and expose information. Use on staging only.', 'sikshya'),
                        'default' => '0'
                    ],
                    [
                        'key' => 'cache_enabled',
                        'type' => 'checkbox',
                        'label' => __('Use Sikshya caching', 'sikshya'),
                        'description' => __('Stores prepared data in memory or transients to speed repeat page loads.', 'sikshya'),
                        'default' => '1'
                    ],
                    [
                        'key' => 'cache_duration',
                        'type' => 'number',
                        'label' => __('Keep cached data for (hours)', 'sikshya'),
                        'description' => __('How long before Sikshya refreshes cached lists and settings. Lower if you need changes to appear instantly.', 'sikshya'),
                        'placeholder' => __('e.g. 24', 'sikshya'),
                        'default' => 24,
                        'min' => 1,
                        'max' => 168
                    ]
                ]
            ],
            [
                'title' => __('Uninstall', 'sikshya'),
                'section_key' => 'uninstall',
                'icon' => 'fas fa-trash',
                'description' => __(
                    'By default, Sikshya keeps your courses and records when the plugin is deleted. Enable this only if you are sure you want to permanently remove Sikshya data on uninstall.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'erase_data_on_uninstall',
                        'type' => 'checkbox',
                        'label' => __('Erase all Sikshya data on uninstall', 'sikshya'),
                        'description' => __('Deletes courses/lessons/quizzes/assignments, custom tables, and plugin options when Sikshya is uninstalled.', 'sikshya'),
                        'default' => '0',
                    ],
                    [
                        'key' => 'erase_files_on_uninstall',
                        'type' => 'checkbox',
                        'label' => __('Also erase uploaded files', 'sikshya'),
                        'description' => __('Deletes files under the WordPress uploads directory used by Sikshya. Requires the “Erase all data” option.', 'sikshya'),
                        'default' => '0',
                    ],
                ],
            ],
            [
                'title' => __('Privacy & Usage', 'sikshya'),
                'section_key' => 'privacy_usage',
                'icon' => 'fas fa-user-shield',
                'description' => __(
                    'Control whether Sikshya can send non-sensitive usage data to help improve the product. We never send learner/customer PII.',
                    'sikshya'
                ),
                'fields' => [
                    [
                        'key' => 'allow_usage_tracking',
                        'type' => 'checkbox',
                        'label' => __('Help us improve the product by sharing non-sensitive data', 'sikshya'),
                        'description' => __(
                            'No personal or learner details—only technical signals.',
                            'sikshya'
                        ),
                        'default' => '0'
                    ],
                ],
            ],
        ];
    }

    /**
     * Get timezone options
     *
     * @return array
     */
    protected function getTimezoneOptions(): array
    {
        $timezones = \DateTimeZone::listIdentifiers();
        $options = [];

        foreach ($timezones as $timezone) {
            $options[$timezone] = $timezone;
        }

        return $options;
    }

    /**
     * Normalize value for comparison (handle type differences)
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeValue($value): string
    {
        // Convert null to empty string
        if ($value === null) {
            return '';
        }

        // Convert to string and trim
        $normalized = (string) $value;
        $normalized = trim($normalized);

        // Handle numeric values (convert "0" to "0", not empty)
        if (is_numeric($value) && $value == 0) {
            return '0';
        }

        return $normalized;
    }
}
