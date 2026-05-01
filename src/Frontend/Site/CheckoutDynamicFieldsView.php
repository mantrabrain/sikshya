<?php

namespace Sikshya\Frontend\Site;

/**
 * Server-rendered markup for checkout dynamic fields (matches assets/js/checkout-page.js structure).
 *
 * @package Sikshya\Frontend\Site
 */
final class CheckoutDynamicFieldsView
{
    /**
     * Mirrors checkout-page.js `dfSlug`.
     */
    public static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
        $s = preg_replace('/^_+|_+$/', '', $s);
        if (strlen($s) > 64) {
            $s = substr($s, 0, 64);
        }

        return $s;
    }

    /**
     * @param array<string, mixed> $dynamic_fields Same shape as checkout template `dynamic_fields`.
     * @param array<string, string> $countries      ISO code => label (from `sikshya_get_country_choices()`).
     */
    public static function render_host(string $host_id, array $dynamic_fields, array $countries = []): void
    {
        $enabled = !empty($dynamic_fields['enabled']);
        $schema = isset($dynamic_fields['schema']) && is_array($dynamic_fields['schema']) ? $dynamic_fields['schema'] : [];
        $prefills = isset($dynamic_fields['prefills']) && is_array($dynamic_fields['prefills']) ? $dynamic_fields['prefills'] : [];

        echo '<div id="' . esc_attr($host_id) . '" class="sikshya-checkout-dynamic-fields-host" style="margin-top:1rem;" aria-live="polite">';
        if ($enabled && $schema !== []) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped fragments.
            echo self::render_inner($schema, $prefills, $countries);
        }
        echo '</div>';
    }

    /**
     * @param array<int, mixed>     $schema
     * @param array<string, string> $prefills
     * @param array<string, string> $countries
     */
    public static function render_inner(array $schema, array $prefills, array $countries): string
    {
        $values = self::initial_values($schema, $prefills);

        $html = '';
        $html .= '<div class="sikshya-checkout-df">';
        $html .= '<h3 class="sikshya-checkout-df__title">' . esc_html__('Additional information', 'sikshya') . '</h3>';
        $html .= '<div class="sikshya-checkout-df__grid">';

        foreach ($schema as $f) {
            if (!is_array($f)) {
                continue;
            }
            $raw_id = isset($f['id']) ? (string) $f['id'] : '';
            $id = self::slug($raw_id);
            if ($id === '') {
                continue;
            }
            if (isset($f['enabled']) && !$f['enabled']) {
                continue;
            }
            if (!empty($f['system'])) {
                continue;
            }

            $type = isset($f['type']) ? sanitize_key((string) $f['type']) : 'text';
            $label = isset($f['label']) ? (string) $f['label'] : $id;
            $help = isset($f['help']) ? (string) $f['help'] : '';
            $ph = isset($f['placeholder']) ? (string) $f['placeholder'] : '';
            $required = !empty($f['required']);
            $visible = self::field_visible($f, $values);
            $val = isset($values[$id]) ? (string) $values[$id] : '';
            $width = isset($f['width']) ? (string) $f['width'] : '';
            $span2 = $width === 'full' || $type === 'textarea' || $type === 'checkbox';

            $field_class = 'sikshya-checkout-df__field' . ($span2 ? ' sikshya-checkout-df__field--full' : '');
            $style = $visible ? '' : ' style="display:none;"';
            $html .= '<div data-df-field="' . esc_attr($id) . '" class="' . esc_attr($field_class) . '"' . $style . '>';

            $req_star = $required
                ? ' <span class="sikshya-checkout-field__required" aria-hidden="true">*</span>'
                : '';

            if ($type === 'checkbox') {
                $checked = $val === '1' ? ' checked' : '';
                $html .= '<label class="sikshya-checkout-df__checkbox" for="sikshya-df-' . esc_attr($id) . '">';
                $html .= '<input type="checkbox" class="sikshya-checkout-df__checkbox-input" id="sikshya-df-' . esc_attr($id) . '"'
                    . ' data-df-input="' . esc_attr($id) . '"' . $checked . ' />';
                $html .= '<span class="sikshya-checkout-df__checkbox-text">' . esc_html($label) . $req_star . '</span>';
                $html .= '</label>';
            } else {
                $html .= '<label class="sikshya-checkout-field__label" for="sikshya-df-' . esc_attr($id) . '">'
                    . esc_html($label) . $req_star . '</label>';

                if ($type === 'textarea') {
                    $html .= '<textarea id="sikshya-df-' . esc_attr($id) . '" data-df-input="' . esc_attr($id) . '" rows="4" class="sikshya-input sikshya-checkout-field__control sikshya-checkout-field__control--textarea">'
                        . esc_textarea($val) . '</textarea>';
                } elseif ($type === 'country') {
                    $html .= '<select id="sikshya-df-' . esc_attr($id) . '" data-df-input="' . esc_attr($id) . '" class="sikshya-input sikshya-checkout-field__control">';
                    $html .= '<option value="">' . esc_html__('Choose country…', 'sikshya') . '</option>';
                    foreach ($countries as $code => $name) {
                        $c = is_string($code) ? strtoupper(preg_replace('/[^a-zA-Z]/', '', $code)) : '';
                        if ($c === '' || !is_string($name)) {
                            continue;
                        }
                        $sel = $val === $c ? ' selected' : '';
                        $html .= '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($name) . '</option>';
                    }
                    $html .= '</select>';
                } elseif ($type === 'select' || $type === 'radio') {
                    $opts = isset($f['options']) && is_array($f['options']) ? $f['options'] : [];
                    if ($type === 'select') {
                        $html .= '<select id="sikshya-df-' . esc_attr($id) . '" data-df-input="' . esc_attr($id) . '" class="sikshya-input sikshya-checkout-field__control">';
                        $html .= '<option value="">' . esc_html__('Choose…', 'sikshya') . '</option>';
                        foreach ($opts as $o) {
                            if (!is_array($o)) {
                                continue;
                            }
                            $ov = isset($o['value']) ? (string) $o['value'] : '';
                            $ol = array_key_exists('label', $o) ? (string) $o['label'] : $ov;
                            $sel = $val === $ov ? ' selected' : '';
                            $html .= '<option value="' . esc_attr($ov) . '"' . $sel . '>' . esc_html($ol) . '</option>';
                        }
                        $html .= '</select>';
                    } else {
                        $html .= '<div class="sikshya-checkout-df__radio-stack" role="radiogroup">';
                        foreach ($opts as $idx => $o) {
                            if (!is_array($o)) {
                                continue;
                            }
                            $ov = isset($o['value']) ? (string) $o['value'] : '';
                            $ol = array_key_exists('label', $o) ? (string) $o['label'] : $ov;
                            $rid = 'sikshya-df-' . $id . '-' . (int) $idx;
                            $chk = $val === $ov ? ' checked' : '';
                            $html .= '<label class="sikshya-checkout-df__radio-row" for="' . esc_attr($rid) . '">';
                            $html .= '<input type="radio" name="sikshya-df-radio-' . esc_attr($id) . '" id="' . esc_attr($rid) . '"'
                                . ' data-df-input="' . esc_attr($id) . '" value="' . esc_attr($ov) . '"' . $chk . ' />';
                            $html .= '<span>' . esc_html($ol) . '</span>';
                            $html .= '</label>';
                        }
                        $html .= '</div>';
                    }
                } else {
                    $input_type = $type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : ($type === 'number' ? 'number' : 'text'));
                    $html .= '<input id="sikshya-df-' . esc_attr($id) . '" data-df-input="' . esc_attr($id) . '" type="' . esc_attr($input_type) . '"'
                        . ' class="sikshya-input sikshya-checkout-field__control" placeholder="' . esc_attr($ph) . '" value="' . esc_attr($val) . '" />';
                }
            }

            if ($help !== '') {
                $html .= '<p class="sikshya-checkout-df__help">' . esc_html($help) . '</p>';
            }

            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param array<int, mixed>     $schema
     * @param array<string, string> $prefills
     *
     * @return array<string, string> slug => value
     */
    private static function initial_values(array $schema, array $prefills): array
    {
        $out = [];

        foreach ($schema as $f) {
            if (!is_array($f)) {
                continue;
            }
            $raw_id = isset($f['id']) ? (string) $f['id'] : '';
            $id = self::slug($raw_id);
            if ($id === '') {
                continue;
            }
            if (isset($f['enabled']) && !$f['enabled']) {
                continue;
            }
            if (!empty($f['system'])) {
                continue;
            }

            $type = isset($f['type']) ? sanitize_key((string) $f['type']) : 'text';
            $sk = $raw_id !== '' ? sanitize_key($raw_id) : '';

            $v = '';
            if ($sk !== '' && isset($prefills[$sk])) {
                $v = (string) $prefills[$sk];
            } elseif (isset($prefills[$id])) {
                $v = (string) $prefills[$id];
            }

            if ($v !== '') {
                $out[$id] = $v;
            } elseif ($type === 'checkbox') {
                $out[$id] = '0';
            } else {
                $out[$id] = '';
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>    $field
     * @param array<string, string> $values_by_slug
     */
    private static function field_visible(array $field, array $values_by_slug): bool
    {
        $vis = isset($field['visibility']) && is_array($field['visibility']) ? $field['visibility'] : [];
        if (empty($vis['depends_on'])) {
            return true;
        }

        $dep = self::slug((string) $vis['depends_on']);
        $cur = isset($values_by_slug[$dep]) ? (string) $values_by_slug[$dep] : '';

        if (isset($vis['depends_value']) && (string) $vis['depends_value'] !== '') {
            return $cur === (string) $vis['depends_value'];
        }

        if (isset($vis['depends_in']) && is_array($vis['depends_in'])) {
            return in_array($cur, array_map('strval', $vis['depends_in']), true);
        }

        return true;
    }
}
