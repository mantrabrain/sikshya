<?php

declare(strict_types=1);

namespace Sikshya\Certificates;

/**
 * Default seeded certificate templates: visual-builder layout JSON + rendered HTML body.
 *
 * Tokens use the Advanced Certificates / builder vocabulary ({{student_name}}, …).
 *
 * @package Sikshya\Certificates
 */
final class CertificateTemplateDefaults
{
    public const LEGACY_KEYS = ['classic', 'modern'];

    /** Stable keys stored in {@see Installer::DEFAULT_TEMPLATE_KEYS}. */
    public const KEY_HERITAGE = 'heritage';

    public const KEY_VERTEX = 'vertex';

    /**
     * Option bump when default template art was replaced (classic/modern → builder v2 presets).
     */
    public const TEMPLATE_ART_VERSION_OPTION = 'sikshya_certificate_template_art_version';

    public const CURRENT_TEMPLATE_ART_VERSION = 3;

    /**
     * Mirrors {@see CERT_LAYOUT_VERSION} in the React certificate builder.
     */
    private const LAYOUT_VERSION = 2;

    /**
     * @return array<int, array{key:string,title:string,html:string,meta:array<string,string>,layout:string}>
     */
    public static function templateDefinitions(): array
    {
        return [
            self::definitionHeritage(),
            self::definitionVertex(),
        ];
    }

    /**
     * Migrate or replace legacy “classic” / “modern” seeded posts so sites keep one default pair.
     */
    public static function migrateLegacyTemplatesIfNeeded(): void
    {
        if (!function_exists('get_posts')) {
            return;
        }

        $done = (int) get_option(self::TEMPLATE_ART_VERSION_OPTION, 0);
        if ($done >= self::CURRENT_TEMPLATE_ART_VERSION) {
            return;
        }

        $defs = self::templateDefinitions();
        $heritage = $defs[0];
        $vertex = $defs[1];

        $classic_id = self::findPublishedIdByDefaultKey('classic');
        $modern_id = self::findPublishedIdByDefaultKey('modern');

        $heritage_existing = self::findPublishedIdByDefaultKey(self::KEY_HERITAGE);
        $vertex_existing = self::findPublishedIdByDefaultKey(self::KEY_VERTEX);

        // If legacy rows exist, upgrade them in place so course numeric template picks stay valid.
        if ($classic_id > 0) {
            self::overwriteTemplatePost($classic_id, $heritage);
        } elseif ($heritage_existing === 0) {
            self::insertTemplatePost($heritage);
        }

        if ($modern_id > 0) {
            self::overwriteTemplatePost($modern_id, $vertex);
        } elseif ($vertex_existing === 0) {
            self::insertTemplatePost($vertex);
        }

        // Remove any extra legacy duplicates (rare half-seeded installs).
        self::deleteOtherLegacyDuplicates();

        update_option(self::TEMPLATE_ART_VERSION_OPTION, self::CURRENT_TEMPLATE_ART_VERSION, false);
    }

    private static function deleteOtherLegacyDuplicates(): void
    {
        foreach (self::LEGACY_KEYS as $legacy_key) {
            $ids = get_posts([
                'post_type' => 'sikshya_certificate',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'suppress_filters' => true,
                'meta_query' => [[
                    'key' => '_sikshya_certificate_default_key',
                    'value' => $legacy_key,
                    'compare' => '=',
                ]],
            ]);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                wp_delete_post($pid, true);
            }
        }
    }

    /**
     * @param array{key:string,title:string,html:string,meta:array<string,string>,layout:string} $def
     */
    private static function overwriteTemplatePost(int $post_id, array $def): void
    {
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $def['title'],
            'post_content' => $def['html'],
            'post_status' => 'publish',
        ], true);

        foreach ($def['meta'] as $k => $v) {
            update_post_meta($post_id, (string) $k, (string) $v);
        }
        update_post_meta($post_id, '_sikshya_certificate_layout', $def['layout']);
        update_post_meta($post_id, '_sikshya_certificate_default', '1');
        update_post_meta($post_id, '_sikshya_certificate_default_key', $def['key']);
        delete_post_meta($post_id, '_sikshya_certificate_accent_color');
    }

    /**
     * @param array{key:string,title:string,html:string,meta:array<string,string>,layout:string} $def
     */
    private static function insertTemplatePost(array $def): void
    {
        if (!function_exists('wp_insert_post')) {
            return;
        }

        $post_id = wp_insert_post(
            [
                'post_type' => 'sikshya_certificate',
                'post_status' => 'publish',
                'post_title' => $def['title'],
                'post_content' => $def['html'],
            ],
            true
        );

        if (is_wp_error($post_id) || (int) $post_id <= 0) {
            return;
        }

        $pid = (int) $post_id;
        foreach ($def['meta'] as $k => $v) {
            update_post_meta($pid, (string) $k, (string) $v);
        }
        update_post_meta($pid, '_sikshya_certificate_layout', $def['layout']);
        update_post_meta($pid, '_sikshya_certificate_default', '1');
        update_post_meta($pid, '_sikshya_certificate_default_key', $def['key']);
        update_post_meta($pid, '_sikshya_certificate_preview_hash', self::generatePreviewHash());
    }

    private static function generatePreviewHash(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return bin2hex((string) openssl_random_pseudo_bytes(32));
        }
    }

    private static function findPublishedIdByDefaultKey(string $key): int
    {
        if ($key === '') {
            return 0;
        }

        $existing = get_posts([
            'post_type' => 'sikshya_certificate',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [[
                'key' => '_sikshya_certificate_default_key',
                'value' => $key,
                'compare' => '=',
            ]],
        ]);

        return is_array($existing) && $existing !== [] ? (int) $existing[0] : 0;
    }

    /**
     * @return array{key:string,title:string,html:string,meta:array<string,string>,layout:string}
     */
    private static function definitionHeritage(): array
    {
        $title = __('Regalia — formal achievement', 'sikshya');
        $layout = self::jsonHeritageLayout();
        $bg = self::inlinePageBackground('#fffbf0', 'paperGrain', 'diplomaGold');

        $html = '<div class="sikshya-certificate-layout" data-version="' . esc_attr((string) self::LAYOUT_VERSION) . '" style="position:relative;width:100%;max-width:100%;margin:0 auto;aspect-ratio:297 / 210;' . $bg . '">'
            . self::cb(8, 6, 84, 9, 5, 'heading', '')
            . self::headingInner('Certificate of Achievement', 'h1', 'center', 30, '#713f12', '700', 'serif', 1.1, 0.06)
            . '</div>'
            . self::cb(18, 15, 64, 5, 4, 'text', '')
            . self::textInner(__('Official credential awarded to', 'sikshya'), 'center', 13, '#78716c', '600', 'sans', 1.35, 0.08)
            . '</div>'
            . self::cb(12, 22, 76, 12, 6, 'merge_field', 'student_name')
            . self::mergeInner('student_name', 'center', 38, '#422006', '600', 'serif', 1.05, 0)
            . '</div>'
            . self::cb(28, 35, 44, 0.6, 3, 'divider', '')
            . self::dividerInner('#d97706', 2)
            . '</div>'
            . self::cb(16, 38, 68, 5, 3, 'text', '')
            . self::textInner(__('Program completed', 'sikshya'), 'center', 12, '#a16207', '700', 'sans', 1.2, 0.12)
            . '</div>'
            . self::cb(14, 43.5, 72, 9, 5, 'merge_field', 'course_name')
            . self::mergeInner('course_name', 'center', 24, '#1c1917', '700', 'serif', 1.18, -0.01)
            . '</div>'
            . self::cb(12, 54, 35, 5, 2, 'merge_field', 'completion_date')
            . self::mergeInner('completion_date', 'left', 14, '#44403c', '600', 'sans', 1.2, 0)
            . '</div>'
            . self::cb(53, 54, 35, 5, 2, 'merge_field', 'instructor_name')
            . self::mergeInner('instructor_name', 'right', 14, '#44403c', '600', 'sans', 1.2, 0)
            . '</div>'
            . self::cb(12, 70, 42, 6, 2, 'merge_field', 'verification_code')
            . self::mergeInner('verification_code', 'left', 11, '#57534e', '500', 'mono', 1.3, 0)
            . '</div>'
            . self::cb(29, 70, 42, 6, 2, 'merge_field', 'certificate_number')
            . self::mergeInner('certificate_number', 'center', 11, '#57534e', '600', 'sans', 1.3, 0.04)
            . '</div>'
            . self::cb(73, 68, 16, 16, 4, 'qr', '')
            . self::qrInner()
            . '</div>'
            . self::cb(12, 80, 76, 4, 1, 'merge_field', 'site_name')
            . self::mergeInner('site_name', 'center', 12, '#92400e', '700', 'sans', 1.2, 0.04)
            . '</div>'
            . '</div>';

        return [
            'key' => self::KEY_HERITAGE,
            'title' => $title,
            'html' => $html,
            'layout' => $layout,
            'meta' => [
                '_sikshya_certificate_orientation' => 'landscape',
                '_sikshya_certificate_page_size' => 'a4',
                '_sikshya_certificate_page_color' => '#fffbf0',
                '_sikshya_certificate_page_pattern' => 'paperGrain',
                '_sikshya_certificate_page_deco' => 'diplomaGold',
            ],
        ];
    }

    /**
     * @return array{key:string,title:string,html:string,meta:array<string,string>,layout:string}
     */
    private static function definitionVertex(): array
    {
        $title = __('Vertex — modern recognition', 'sikshya');
        $layout = self::jsonVertexLayout();
        $bg = self::inlinePageBackground('#ffffff', 'microDots', 'minimalFrame');

        $accent = '#0d9488';

        $html = '<div class="sikshya-certificate-layout" data-version="' . esc_attr((string) self::LAYOUT_VERSION) . '" style="position:relative;width:100%;max-width:100%;margin:0 auto;aspect-ratio:297 / 210;' . $bg . '">'
            . self::cb(0, 0, 100, 3.2, 6, 'divider', '')
            . self::dividerInner($accent, 12)
            . '</div>'
            . self::cb(5, 8, 40, 6, 8, 'heading', '')
            . self::headingInner(__('Certificate', 'sikshya'), 'h1', 'left', 11, '#94a3b8', '800', 'sans', 1.0, 0.42)
            . '</div>'
            . self::cb(5, 13, 40, 5, 8, 'heading', '')
            . self::headingInner(__('Of completion', 'sikshya'), 'h2', 'left', 22, '#0f172a', '800', 'sans', 1.05, -0.02)
            . '</div>'
            . self::cb(62, 9, 32, 5, 5, 'text', '')
            . self::textInner(__('Issuer', 'sikshya') . "\n{{site_name}}", 'right', 12, '#64748b', '600', 'sans', 1.35, 0.02)
            . '</div>'
            . self::cb(58, 15, 38, 4, 4, 'merge_field', '')
            . self::mergeInner('completion_date', 'right', 11, '#94a3b8', '600', 'sans', 1.3, 0.03)
            . '</div>'
            . self::cb(22, 32, 70, 5, 4, 'text', '')
            . self::textInner(__('This certifies that', 'sikshya'), 'left', 13, '#64748b', '600', 'sans', 1.4, 0.05)
            . '</div>'
            . self::cb(22, 38.5, 70, 12, 6, 'merge_field', 'student_name')
            . self::mergeInner('student_name', 'left', 44, '#0f172a', '700', 'sans', 1.0, -0.03)
            . '</div>'
            . self::cb(22, 52.5, 70, 0.55, 3, 'divider', '')
            . self::dividerInner('#e2e8f0', 3)
            . '</div>'
            . self::cb(22, 55, 65, 5, 4, 'text', '')
            . self::textInner(__('Successfully completed', 'sikshya'), 'left', 12, '#64748b', '600', 'sans', 1.35, 0.04)
            . '</div>'
            . self::cb(22, 60.5, 72, 9, 5, 'merge_field', 'course_name')
            . self::mergeInner('course_name', 'left', 26, $accent, '700', 'sans', 1.12, -0.02)
            . '</div>'
            . self::cb(22, 74, 30, 4, 2, 'merge_field', 'instructor_name')
            . self::mergeInner('instructor_name', 'left', 12, '#475569', '600', 'sans', 1.25, 0)
            . '</div>'
            . self::cb(44, 74, 30, 4, 2, 'merge_field', 'verification_code')
            . self::mergeInner('verification_code', 'center', 11, '#64748b', '500', 'mono', 1.25, 0)
            . '</div>'
            . self::cb(71, 72, 16, 16, 4, 'qr', '')
            . self::qrInner()
            . '</div>'
            . '</div>';

        return [
            'key' => self::KEY_VERTEX,
            'title' => $title,
            'html' => $html,
            'layout' => $layout,
            'meta' => [
                '_sikshya_certificate_orientation' => 'landscape',
                '_sikshya_certificate_page_size' => 'a4',
                '_sikshya_certificate_page_color' => '#ffffff',
                '_sikshya_certificate_page_pattern' => 'microDots',
                '_sikshya_certificate_page_deco' => 'minimalFrame',
            ],
        ];
    }

    /**
     * @param float|string $left
     * @param float|string $top
     * @param float|string $width
     * @param float|string $height
     */
    private static function cb($left, $top, $width, $height, int $z, string $type, string $_unused): string
    {
        return '<div class="sikshya-cb" data-type="' . esc_attr($type) . '" style="position:absolute;left:'
            . $left . '%;top:' . $top . '%;width:' . $width . '%;height:' . $height
            . '%;z-index:' . $z . ';overflow:hidden;box-sizing:border-box;">';
    }

    private static function headingInner(string $text, string $tag, string $align, int $fs, string $color, string $fw, string $ff_id, float $lh, float $ls_em): string
    {
        $tag = in_array($tag, ['h1', 'h2', 'h3'], true) ? $tag : 'h1';
        $ff = self::fontStack($ff_id);

        return '<' . $tag . ' style="margin:0;max-height:100%;overflow:hidden;text-align:' . esc_attr($align)
            . ';font-size:' . (int) $fs . 'px;color:' . esc_attr(self::sanitizeHexColor($color))
            . ';font-weight:' . esc_attr($fw) . ';font-family:' . esc_attr($ff)
            . ';line-height:' . $lh . ';letter-spacing:' . $ls_em . 'em;">'
            . esc_html($text) . '</' . $tag . '>';
    }

    private static function textInner(string $text, string $align, int $fs, string $color, string $fw, string $ff_id, float $lh, float $ls_em): string
    {
        $ff = self::fontStack($ff_id);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $body = implode('<br />', array_map(static function (string $line): string {
            return esc_html($line);
        }, $lines));

        return '<p style="margin:0;max-height:100%;overflow:hidden;text-align:' . esc_attr($align)
            . ';font-size:' . (int) $fs . 'px;color:' . esc_attr(self::sanitizeHexColor($color))
            . ';font-weight:' . esc_attr($fw) . ';font-family:' . esc_attr($ff)
            . ';line-height:' . $lh . ';letter-spacing:' . $ls_em . 'em;">' . $body . '</p>';
    }

    private static function mergeInner(string $field, string $align, int $fs, string $color, string $fw, string $ff_id, float $lh, float $ls_em): string
    {
        $token = '{{' . preg_replace('/[^a-z_]/', '', $field) . '}}';
        $ff = self::fontStack($ff_id);

        return '<div class="sikshya-cert-merge" data-field="' . esc_attr($field) . '" style="max-height:100%;overflow:hidden;text-align:'
            . esc_attr($align) . ';font-size:' . (int) $fs . 'px;color:' . esc_attr(self::sanitizeHexColor($color))
            . ';font-weight:' . esc_attr($fw) . ';font-family:' . esc_attr($ff)
            . ';line-height:' . $lh . ';letter-spacing:' . $ls_em . 'em;">' . esc_html($token) . '</div>';
    }

    private static function dividerInner(string $color, int $thickness): string
    {
        $hex = self::sanitizeHexColor($color);
        $t = max(1, min(20, $thickness));

        return '<div style="display:flex;align-items:center;height:100%;width:100%;"><hr style="border:none;border-top:'
            . $t . 'px solid ' . $hex . ';margin:0;width:100%;" /></div>';
    }

    private static function qrInner(): string
    {
        return '<div class="sikshya-cert-qr" style="display:flex;align-items:center;justify-content:center;height:100%;width:100%;">{{qr_image}}</div>';
    }

    private static function fontStack(string $id): string
    {
        if ($id === 'serif') {
            return 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
        }
        if ($id === 'mono') {
            return 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
        }

        return 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", sans-serif';
    }

    private static function sanitizeHexColor(string $c): string
    {
        $s = strtolower(trim($c));

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $s) ? $s : '#0f172a';
    }

    /**
     * Composes layered background identical in spirit to the React builder helpers.
     */
    private static function inlinePageBackground(string $page_hex, string $pattern_id, string $deco_id): string
    {
        $images = [];
        $sizes = [];
        $reps = [];
        $pos = [];

        $p_layer = self::patternLayerCss($pattern_id);
        if ($p_layer !== null) {
            $images[] = $p_layer['image'];
            $sizes[] = $p_layer['size'];
            $reps[] = 'repeat';
            $pos[] = '0 0';
        }

        $deco = self::decoGradient($deco_id);
        if ($deco !== '') {
            $images[] = $deco;
            $sizes[] = '100% 100%';
            $reps[] = 'no-repeat';
            $pos[] = 'center center';
        }

        $safe_hex = self::sanitizeHexColor($page_hex);

        $out = 'background-color:' . $safe_hex . ';';
        if ($images !== []) {
            $out .= 'background-image:' . implode(', ', $images) . ';';
            $out .= 'background-size:' . implode(', ', $sizes) . ';';
            $out .= 'background-repeat:' . implode(', ', $reps) . ';';
            $out .= 'background-position:' . implode(', ', $pos) . ';';
        } else {
            $out .= 'background-image:none;background-size:auto;background-repeat:no-repeat;background-position:0 0;';
        }

        return $out;
    }

    /**
     * @return array{image:string,size:string}|null
     */
    private static function patternLayerCss(string $pattern_id): ?array
    {
        if ($pattern_id === '' || $pattern_id === 'none') {
            return null;
        }
        if ($pattern_id === 'microDots') {
            return [
                'image' => 'radial-gradient(rgba(15,23,42,0.075) 0.9px, transparent 0.9px)',
                'size' => '10px 10px',
            ];
        }
        if ($pattern_id === 'paperGrain') {
            return [
                'image' => 'radial-gradient(circle at 10% 20%, rgba(15,23,42,0.035) 0, transparent 45%), radial-gradient(circle at 80% 0%, rgba(15,23,42,0.03) 0, transparent 40%), radial-gradient(circle at 40% 90%, rgba(15,23,42,0.03) 0, transparent 42%)',
                'size' => '180px 180px',
            ];
        }

        return null;
    }

    private static function decoGradient(string $deco_id): string
    {
        $map = [
            'diplomaGold' => 'linear-gradient(165deg, #fffbeb 0%, #ffffff 38%, #fef3c7 78%, #fffbeb 100%)',
            'minimalFrame' => 'linear-gradient(90deg, #eef2f6 0%, #ffffff 10%, #ffffff 90%, #eef2f6 100%)',
        ];

        return $map[$deco_id] ?? '';
    }

    private static function jsonHeritageLayout(): string
    {
        $blocks = [
            ['id' => 'ht_h1', 'type' => 'heading', 'props' => ['x' => 8, 'y' => 6, 'w' => 84, 'h' => 9, 'z' => 5, 'text' => 'Certificate of Achievement', 'tag' => 'h1', 'align' => 'center', 'fontSize' => 30, 'color' => '#713f12', 'fontWeight' => '700', 'fontFamily' => 'serif', 'lineHeight' => 1.1, 'letterSpacing' => 0.06]],
            ['id' => 'ht_sub', 'type' => 'text', 'props' => ['x' => 18, 'y' => 15, 'w' => 64, 'h' => 5, 'z' => 4, 'text' => 'Official credential awarded to', 'align' => 'center', 'fontSize' => 13, 'color' => '#78716c', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.35, 'letterSpacing' => 0.08]],
            ['id' => 'ht_student', 'type' => 'merge_field', 'props' => ['x' => 12, 'y' => 22, 'w' => 76, 'h' => 12, 'z' => 6, 'field' => 'student_name', 'align' => 'center', 'fontSize' => 38, 'color' => '#422006', 'fontWeight' => '600', 'fontFamily' => 'serif', 'lineHeight' => 1.05]],
            ['id' => 'ht_rule', 'type' => 'divider', 'props' => ['x' => 28, 'y' => 35, 'w' => 44, 'h' => 0.6, 'z' => 3, 'color' => '#d97706', 'thickness' => 2]],
            ['id' => 'ht_lbl', 'type' => 'text', 'props' => ['x' => 16, 'y' => 38, 'w' => 68, 'h' => 5, 'z' => 3, 'text' => 'Program completed', 'align' => 'center', 'fontSize' => 12, 'color' => '#a16207', 'fontWeight' => '700', 'fontFamily' => 'sans', 'lineHeight' => 1.2, 'letterSpacing' => 0.12]],
            ['id' => 'ht_course', 'type' => 'merge_field', 'props' => ['x' => 14, 'y' => 43.5, 'w' => 72, 'h' => 9, 'z' => 5, 'field' => 'course_name', 'align' => 'center', 'fontSize' => 24, 'color' => '#1c1917', 'fontWeight' => '700', 'fontFamily' => 'serif', 'lineHeight' => 1.18, 'letterSpacing' => -0.01]],
            ['id' => 'ht_dt', 'type' => 'merge_field', 'props' => ['x' => 12, 'y' => 54, 'w' => 35, 'h' => 5, 'z' => 2, 'field' => 'completion_date', 'align' => 'left', 'fontSize' => 14, 'color' => '#44403c', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.2]],
            ['id' => 'ht_ins', 'type' => 'merge_field', 'props' => ['x' => 53, 'y' => 54, 'w' => 35, 'h' => 5, 'z' => 2, 'field' => 'instructor_name', 'align' => 'right', 'fontSize' => 14, 'color' => '#44403c', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.2]],
            ['id' => 'ht_code', 'type' => 'merge_field', 'props' => ['x' => 12, 'y' => 70, 'w' => 42, 'h' => 6, 'z' => 2, 'field' => 'verification_code', 'align' => 'left', 'fontSize' => 11, 'color' => '#57534e', 'fontWeight' => '500', 'fontFamily' => 'mono', 'lineHeight' => 1.3]],
            ['id' => 'ht_serial', 'type' => 'merge_field', 'props' => ['x' => 29, 'y' => 70, 'w' => 42, 'h' => 6, 'z' => 2, 'field' => 'certificate_number', 'align' => 'center', 'fontSize' => 11, 'color' => '#57534e', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.3, 'letterSpacing' => 0.04]],
            ['id' => 'ht_qr', 'type' => 'qr', 'props' => ['x' => 73, 'y' => 68, 'w' => 16, 'h' => 16, 'z' => 4]],
            ['id' => 'ht_org', 'type' => 'merge_field', 'props' => ['x' => 12, 'y' => 80, 'w' => 76, 'h' => 4, 'z' => 1, 'field' => 'site_name', 'align' => 'center', 'fontSize' => 12, 'color' => '#92400e', 'fontWeight' => '700', 'fontFamily' => 'sans', 'lineHeight' => 1.2, 'letterSpacing' => 0.04]],
        ];

        return wp_json_encode(['version' => self::LAYOUT_VERSION, 'blocks' => $blocks], JSON_UNESCAPED_UNICODE);
    }

    private static function jsonVertexLayout(): string
    {
        $blocks = [
            ['id' => 'vx_top', 'type' => 'divider', 'props' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 3.2, 'z' => 6, 'color' => '#0d9488', 'thickness' => 12]],
            ['id' => 'vx_small', 'type' => 'heading', 'props' => ['x' => 5, 'y' => 8, 'w' => 40, 'h' => 6, 'z' => 8, 'text' => 'Certificate', 'tag' => 'h1', 'align' => 'left', 'fontSize' => 11, 'color' => '#94a3b8', 'fontWeight' => '800', 'fontFamily' => 'sans', 'lineHeight' => 1.0, 'letterSpacing' => 0.42]],
            ['id' => 'vx_big', 'type' => 'heading', 'props' => ['x' => 5, 'y' => 13, 'w' => 40, 'h' => 5, 'z' => 8, 'text' => 'Of completion', 'tag' => 'h2', 'align' => 'left', 'fontSize' => 22, 'color' => '#0f172a', 'fontWeight' => '800', 'fontFamily' => 'sans', 'lineHeight' => 1.05, 'letterSpacing' => -0.02]],
            ['id' => 'vx_issuer', 'type' => 'text', 'props' => ['x' => 62, 'y' => 9, 'w' => 32, 'h' => 5, 'z' => 5, 'text' => "Issuer\n{{site_name}}", 'align' => 'right', 'fontSize' => 12, 'color' => '#64748b', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.35, 'letterSpacing' => 0.02]],
            ['id' => 'vx_grant', 'type' => 'merge_field', 'props' => ['x' => 58, 'y' => 15, 'w' => 38, 'h' => 4, 'z' => 4, 'field' => 'completion_date', 'align' => 'right', 'fontSize' => 11, 'color' => '#94a3b8', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.3, 'letterSpacing' => 0.03]],
            ['id' => 'vx_pres', 'type' => 'text', 'props' => ['x' => 22, 'y' => 32, 'w' => 70, 'h' => 5, 'z' => 4, 'text' => 'This certifies that', 'align' => 'left', 'fontSize' => 13, 'color' => '#64748b', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.4, 'letterSpacing' => 0.05]],
            ['id' => 'vx_student', 'type' => 'merge_field', 'props' => ['x' => 22, 'y' => 38.5, 'w' => 70, 'h' => 12, 'z' => 6, 'field' => 'student_name', 'align' => 'left', 'fontSize' => 44, 'color' => '#0f172a', 'fontWeight' => '700', 'fontFamily' => 'sans', 'lineHeight' => 1.0, 'letterSpacing' => -0.03]],
            ['id' => 'vx_rule', 'type' => 'divider', 'props' => ['x' => 22, 'y' => 52.5, 'w' => 70, 'h' => 0.55, 'z' => 3, 'color' => '#e2e8f0', 'thickness' => 3]],
            ['id' => 'vx_ok', 'type' => 'text', 'props' => ['x' => 22, 'y' => 55, 'w' => 65, 'h' => 5, 'z' => 4, 'text' => 'Successfully completed', 'align' => 'left', 'fontSize' => 12, 'color' => '#64748b', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.35, 'letterSpacing' => 0.04]],
            ['id' => 'vx_course', 'type' => 'merge_field', 'props' => ['x' => 22, 'y' => 60.5, 'w' => 72, 'h' => 9, 'z' => 5, 'field' => 'course_name', 'align' => 'left', 'fontSize' => 26, 'color' => '#0d9488', 'fontWeight' => '700', 'fontFamily' => 'sans', 'lineHeight' => 1.12, 'letterSpacing' => -0.02]],
            ['id' => 'vx_ins', 'type' => 'merge_field', 'props' => ['x' => 22, 'y' => 74, 'w' => 30, 'h' => 4, 'z' => 2, 'field' => 'instructor_name', 'align' => 'left', 'fontSize' => 12, 'color' => '#475569', 'fontWeight' => '600', 'fontFamily' => 'sans', 'lineHeight' => 1.25]],
            ['id' => 'vx_cod', 'type' => 'merge_field', 'props' => ['x' => 44, 'y' => 74, 'w' => 30, 'h' => 4, 'z' => 2, 'field' => 'verification_code', 'align' => 'center', 'fontSize' => 11, 'color' => '#64748b', 'fontWeight' => '500', 'fontFamily' => 'mono', 'lineHeight' => 1.25]],
            ['id' => 'vx_qr', 'type' => 'qr', 'props' => ['x' => 71, 'y' => 72, 'w' => 16, 'h' => 16, 'z' => 4]],
        ];

        return wp_json_encode(['version' => self::LAYOUT_VERSION, 'blocks' => $blocks], JSON_UNESCAPED_UNICODE);
    }
}
