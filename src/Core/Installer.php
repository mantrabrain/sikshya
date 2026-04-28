<?php

/**
 * Single source of truth for plugin installation activity.
 *
 * Everything that has to happen on activation lives here:
 *   - WordPress / PHP requirements check
 *   - Database tables + incremental upgrades
 *   - Custom roles + admin capability grants
 *   - Default option values
 *   - Default certificate templates (Free) marked as protected
 *   - One-time setup-wizard redirect flag
 *   - Rewrite rule flush
 *   - `sikshya_activated` action firing
 *
 * Idempotent: every step is safe to run multiple times. {@see \Sikshya\Core\Activator::activate()}
 * is a thin wrapper that delegates to {@see Installer::install()}.
 *
 * @package Sikshya\Core
 */

namespace Sikshya\Core;

use Sikshya\Services\PostTypeService;
use Sikshya\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    /** Stable keys used to identify the seeded Free templates across upgrades. */
    public const DEFAULT_TEMPLATE_KEYS = ['classic', 'modern'];

    /** Option flag that records that the seeded templates have been created at least once. */
    private const TEMPLATES_SEEDED_FLAG = 'sikshya_default_certificate_templates_created';

    /**
     * Run the full install sequence. Safe to call repeatedly.
     */
    public static function install(): void
    {
        if (!self::ensureRequirements()) {
            return;
        }

        self::installDatabase();
        self::installRoles();
        self::installDefaultOptions();
        self::installDefaultCertificateTemplates();
        self::scheduleSetupWizardRedirect();

        flush_rewrite_rules();

        Settings::setRaw('sikshya_activation_time', current_time('timestamp'));

        do_action('sikshya_activated');
    }

    /**
     * Verify host requirements; deactivate the plugin and emit a hard failure
     * if the environment is too old. Returns true if installation should continue.
     */
    private static function ensureRequirements(): bool
    {
        if (Requirements::check()) {
            return true;
        }

        if (function_exists('deactivate_plugins') && defined('SIKSHYA_PLUGIN_FILE')) {
            deactivate_plugins(plugin_basename((string) constant('SIKSHYA_PLUGIN_FILE')));
        }

        wp_die(esc_html__('Sikshya LMS requires WordPress 6.0+ and PHP 8.1+', 'sikshya'));

        return false; // Unreachable; appeases static analysis.
    }

    private static function installDatabase(): void
    {
        $database = new \Sikshya\Database\Database(Plugin::getInstance());
        $database->createTables();
        $database->maybeUpgrade();
    }

    /**
     * Create the Sikshya roles (instructor, student, assistant) and grant the
     * matching capabilities to the WordPress administrator role.
     */
    private static function installRoles(): void
    {
        add_role('sikshya_instructor', __('Instructor', 'sikshya'), [
            'read' => true,
            'edit_sikshya_courses' => true,
            'edit_published_sikshya_courses' => true,
            'publish_sikshya_courses' => true,
            'delete_sikshya_courses' => true,
            'delete_published_sikshya_courses' => true,
            'edit_sikshya_lessons' => true,
            'edit_published_sikshya_lessons' => true,
            'publish_sikshya_lessons' => true,
            'delete_sikshya_lessons' => true,
            'delete_published_sikshya_lessons' => true,
            'edit_sikshya_quizzes' => true,
            'edit_published_sikshya_quizzes' => true,
            'publish_sikshya_quizzes' => true,
            'delete_sikshya_quizzes' => true,
            'delete_published_sikshya_quizzes' => true,
            'upload_files' => true,
            'manage_sikshya_students' => true,
            'view_sikshya_reports' => true,
        ]);

        add_role('sikshya_student', __('Student', 'sikshya'), [
            'read' => true,
            'enroll_sikshya_courses' => true,
            'access_sikshya_courses' => true,
            'submit_sikshya_assignments' => true,
            'take_sikshya_quizzes' => true,
            'view_sikshya_certificates' => true,
        ]);

        add_role('sikshya_assistant', __('Assistant', 'sikshya'), [
            'read' => true,
            'edit_sikshya_courses' => true,
            'edit_published_sikshya_courses' => true,
            'edit_sikshya_lessons' => true,
            'edit_published_sikshya_lessons' => true,
            'edit_sikshya_quizzes' => true,
            'edit_published_sikshya_quizzes' => true,
            'upload_files' => true,
            'view_sikshya_reports' => true,
        ]);

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'edit_sikshya_courses', 'edit_others_sikshya_courses', 'publish_sikshya_courses',
                'read_private_sikshya_courses', 'delete_sikshya_courses', 'delete_private_sikshya_courses',
                'delete_published_sikshya_courses', 'delete_others_sikshya_courses',
                'edit_private_sikshya_courses', 'edit_published_sikshya_courses',
                'edit_sikshya_lessons', 'edit_others_sikshya_lessons', 'publish_sikshya_lessons',
                'read_private_sikshya_lessons', 'delete_sikshya_lessons', 'delete_private_sikshya_lessons',
                'delete_published_sikshya_lessons', 'delete_others_sikshya_lessons',
                'edit_private_sikshya_lessons', 'edit_published_sikshya_lessons',
                'edit_sikshya_quizzes', 'edit_others_sikshya_quizzes', 'publish_sikshya_quizzes',
                'read_private_sikshya_quizzes', 'delete_sikshya_quizzes', 'delete_private_sikshya_quizzes',
                'delete_published_sikshya_quizzes', 'delete_others_sikshya_quizzes',
                'edit_private_sikshya_quizzes', 'edit_published_sikshya_quizzes',
            ];

            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Seed default option groups. Stored under raw option names so they're
     * independent of user-facing settings (which live under `sikshya_settings`).
     */
    private static function installDefaultOptions(): void
    {
        Settings::setRaw('sikshya_general_settings', [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'currency' => 'USD',
            'currency_symbol' => '$',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i a',
            'timezone' => wp_timezone_string(),
        ]);

        Settings::setRaw('sikshya_course_settings', [
            'featured_courses_count' => 6,
            'popular_courses_count' => 6,
            'enable_reviews' => true,
            'enable_ratings' => true,
            'enable_certificates' => true,
            'enable_progress_tracking' => true,
            'enable_discussions' => true,
            'enable_assignments' => true,
        ]);

        Settings::setRaw('sikshya_email_settings', [
            'from_name' => get_bloginfo('name'),
            'from_email' => Settings::getRaw('admin_email'),
            'welcome_email' => true,
            'course_completion_email' => true,
            'certificate_email' => true,
            'reminder_emails' => true,
        ]);

        Settings::setRaw('sikshya_payment_settings', [
            'enable_payments' => false,
            'payment_methods' => ['offline', 'stripe', 'paypal'],
            'test_mode' => true,
            'currency' => 'USD',
        ]);

        Settings::setRaw('sikshya_uninstall_options', [
            'remove_data' => false,
            'remove_tables' => false,
            'remove_options' => false,
            'remove_files' => false,
        ]);
    }

    /**
     * Set the one-time setup-wizard redirect flag on first activation.
     *
     * `sikshya_setup_redirect` is stored as a raw option so it can be deleted
     * without touching user-facing settings export/import.
     */
    private static function scheduleSetupWizardRedirect(): void
    {
        if (Settings::isTruthy(Settings::get('setup_completed', '0'))) {
            return;
        }
        Settings::setRaw('sikshya_setup_redirect', 1, false);
    }

    /**
     * Seed two ready-to-use certificate templates so Free users can issue
     * certificates immediately. Marked as “default” so the Free build can
     * protect them from accidental deletion (see {@see \Sikshya\Certificates\TemplateGuard}).
     *
     * Idempotent in two ways:
     *   1) Skips entirely once the seeded flag is set.
     *   2) Searches by `_sikshya_certificate_default_key` before inserting so a
     *      half-finished previous run never produces duplicates.
     */
    private static function installDefaultCertificateTemplates(): void
    {
        if (!function_exists('wp_insert_post')) {
            return;
        }

        if (Settings::getRaw(self::TEMPLATES_SEEDED_FLAG, false)) {
            return;
        }

        // The CPT must be registered before we can insert posts. During
        // activation `init` may not have fired yet, so register once defensively.
        try {
            (new PostTypeService(Plugin::getInstance()))->registerCertificatePostType();
        } catch (\Throwable $e) {
            return;
        }

        $created_any = false;

        foreach (self::defaultTemplateDefinitions() as $template) {
            $existing_id = self::findTemplateByKey((string) $template['key']);
            if ($existing_id > 0) {
                $created_any = true;
                continue;
            }

            $post_id = wp_insert_post(
                [
                    'post_type' => 'sikshya_certificate',
                    'post_status' => 'publish',
                    'post_title' => (string) $template['title'],
                    'post_content' => (string) $template['html'],
                ],
                true
            );

            if (is_wp_error($post_id) || (int) $post_id <= 0) {
                continue;
            }

            $created_any = true;

            $meta = isset($template['meta']) && is_array($template['meta']) ? $template['meta'] : [];
            foreach ($meta as $k => $v) {
                update_post_meta((int) $post_id, (string) $k, (string) $v);
            }

            // Mark as a protected default; key lets us identify each template
            // distinctly so future updates can target the right post.
            update_post_meta((int) $post_id, '_sikshya_certificate_default', '1');
            update_post_meta((int) $post_id, '_sikshya_certificate_default_key', (string) $template['key']);

            // Stable 64-hex preview hash for public preview / verification links.
            update_post_meta((int) $post_id, '_sikshya_certificate_preview_hash', self::generatePreviewHash());
        }

        if ($created_any) {
            Settings::setRaw(self::TEMPLATES_SEEDED_FLAG, 1);
        }
    }

    /**
     * Look up a seeded template post id by its stable default key.
     */
    private static function findTemplateByKey(string $key): int
    {
        if ($key === '') {
            return 0;
        }

        $existing = get_posts([
            'post_type' => 'sikshya_certificate',
            'post_status' => 'any',
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
     * 64 hex chars (256 bits) — never logged, only used to build public URLs.
     */
    private static function generatePreviewHash(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return bin2hex((string) openssl_random_pseudo_bytes(32));
        }
    }

    /**
     * @return array<int, array{key:string,title:string,html:string,meta:array<string,string>}>
     */
    private static function defaultTemplateDefinitions(): array
    {
        return [
            [
                'key' => 'classic',
                'title' => __('Certificate of Completion — Classic', 'sikshya'),
                'html' => self::classicCertificateHtml(),
                'meta' => [
                    '_sikshya_certificate_orientation' => 'landscape',
                    '_sikshya_certificate_page_size' => 'a4',
                    '_sikshya_certificate_page_color' => '#fbf7ee',
                    '_sikshya_certificate_page_pattern' => 'none',
                    '_sikshya_certificate_page_deco' => 'none',
                    '_sikshya_certificate_accent_color' => '#a87a2c',
                ],
            ],
            [
                'key' => 'modern',
                'title' => __('Certificate of Achievement — Modern', 'sikshya'),
                'html' => self::modernCertificateHtml(),
                'meta' => [
                    '_sikshya_certificate_orientation' => 'landscape',
                    '_sikshya_certificate_page_size' => 'a4',
                    '_sikshya_certificate_page_color' => '#ffffff',
                    '_sikshya_certificate_page_pattern' => 'none',
                    '_sikshya_certificate_page_deco' => 'none',
                    '_sikshya_certificate_accent_color' => '#1d4ed8',
                ],
            ],
        ];
    }

    /**
     * Classic — formal, ivory paper, gold double-border with corner flourishes,
     * centred award copy, calligraphic name. Print-ready (no gradients, no
     * external fonts, no JS).
     */
    private static function classicCertificateHtml(): string
    {
        $css_outer = 'position:relative;width:100%;height:100%;box-sizing:border-box;padding:48px 56px;'
            . 'background:#fbf7ee;color:#3a2f1f;font-family:Georgia,"Times New Roman",serif;';
        $css_double_border = 'position:absolute;inset:18px;border:3px double #a87a2c;border-radius:6px;';
        $css_inner_border = 'position:absolute;inset:32px;border:1px solid #c9a86b;border-radius:4px;';
        $css_corner = 'position:absolute;width:34px;height:34px;border:2px solid #a87a2c;';
        $css_inner = 'position:relative;z-index:1;display:flex;flex-direction:column;justify-content:space-between;height:100%;text-align:center;padding:18px 22px;';

        $eyebrow = esc_html__('Certificate of Completion', 'sikshya');
        $is_certify = esc_html__('This certificate is proudly presented to', 'sikshya');
        $for_completing = esc_html__('for successfully completing the course', 'sikshya');
        $issued_on_label = esc_html__('Issued on', 'sikshya');
        $credential_label = esc_html__('Credential ID', 'sikshya');
        $signature_label = esc_html__('Authorized Signature', 'sikshya');
        $issued_by_label = esc_html__('Issued by', 'sikshya');
        $verify_label = esc_html__('Verify at', 'sikshya');

        return '<div style="' . $css_outer . '">'
            . '<div style="' . $css_double_border . '"></div>'
            . '<div style="' . $css_inner_border . '"></div>'
            . '<div style="' . $css_corner . 'left:24px;top:24px;border-right:none;border-bottom:none;"></div>'
            . '<div style="' . $css_corner . 'right:24px;top:24px;border-left:none;border-bottom:none;"></div>'
            . '<div style="' . $css_corner . 'left:24px;bottom:24px;border-right:none;border-top:none;"></div>'
            . '<div style="' . $css_corner . 'right:24px;bottom:24px;border-left:none;border-top:none;"></div>'
            . '<div style="' . $css_inner . '">'
                . '<div>'
                    . '<div style="display:inline-block;padding:0 14px;border-bottom:1px solid #c9a86b;">'
                        . '<div style="font-size:11px;letter-spacing:.32em;font-weight:700;color:#a87a2c;text-transform:uppercase;">' . $eyebrow . '</div>'
                    . '</div>'
                    . '<div style="margin-top:14px;font-size:13px;color:#7a6644;font-style:italic;">' . $is_certify . '</div>'
                . '</div>'
                . '<div>'
                    . '<div style="font-family:\'Brush Script MT\',\'Lucida Handwriting\',Georgia,serif;font-size:54px;font-weight:400;color:#3a2f1f;line-height:1.05;">{{learner_name}}</div>'
                    . '<div style="margin:14px auto 0;width:60%;height:1px;background:#c9a86b;"></div>'
                    . '<div style="margin-top:18px;font-size:13px;color:#7a6644;">' . $for_completing . '</div>'
                    . '<div style="margin-top:6px;font-size:22px;font-weight:600;color:#3a2f1f;font-style:italic;">{{course_title}}</div>'
                . '</div>'
                . '<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-top:8px;">'
                    . '<div style="text-align:left;font-size:11px;color:#7a6644;line-height:1.5;">'
                        . '<div style="font-weight:700;color:#3a2f1f;text-transform:uppercase;letter-spacing:.08em;">' . $credential_label . '</div>'
                        . '<div style="margin-top:2px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#3a2f1f;">{{verification_code}}</div>'
                        . '<div style="margin-top:8px;font-weight:700;color:#3a2f1f;text-transform:uppercase;letter-spacing:.08em;">' . $issued_on_label . '</div>'
                        . '<div style="margin-top:2px;color:#3a2f1f;">{{issued_date}}</div>'
                    . '</div>'
                    . '<div style="text-align:center;">'
                        . '<div style="position:relative;width:78px;height:78px;margin:0 auto;border-radius:50%;border:2px solid #a87a2c;display:flex;align-items:center;justify-content:center;background:#fbf7ee;">'
                            . '<div style="position:absolute;inset:6px;border:1px dashed #c9a86b;border-radius:50%;"></div>'
                            . '<div style="font-size:22px;font-weight:700;color:#a87a2c;letter-spacing:.06em;">★</div>'
                        . '</div>'
                        . '<div style="margin-top:6px;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#a87a2c;">' . $eyebrow . '</div>'
                    . '</div>'
                    . '<div style="text-align:right;font-size:11px;color:#7a6644;line-height:1.5;">'
                        . '<div style="border-top:1px solid #3a2f1f;padding-top:6px;min-width:170px;">'
                            . '<div style="font-style:italic;color:#3a2f1f;">{{site_name}}</div>'
                            . '<div style="font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#3a2f1f;">' . $signature_label . '</div>'
                        . '</div>'
                        . '<div style="margin-top:8px;font-weight:700;color:#3a2f1f;text-transform:uppercase;letter-spacing:.08em;">' . $issued_by_label . '</div>'
                        . '<div style="margin-top:2px;color:#3a2f1f;">{{site_name}}</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
        . '</div>';
    }

    /**
     * Modern — minimalist, white, bold accent bar on the left, sans-serif,
     * generous whitespace, clean credential strip across the bottom.
     */
    private static function modernCertificateHtml(): string
    {
        $css_outer = 'position:relative;width:100%;height:100%;box-sizing:border-box;'
            . 'background:#ffffff;color:#0f172a;'
            . 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;';
        $css_accent = 'position:absolute;left:0;top:0;bottom:0;width:14px;background:#1d4ed8;';
        $css_corner_strip = 'position:absolute;right:0;top:0;height:14px;width:60%;background:#0f172a;';
        $css_inner = 'position:relative;z-index:1;display:flex;flex-direction:column;justify-content:space-between;height:100%;padding:54px 60px 46px 76px;';

        $eyebrow_top = esc_html__('CERTIFICATE', 'sikshya');
        $sub = esc_html__('of Achievement', 'sikshya');
        $presented_to = esc_html__('Presented to', 'sikshya');
        $for_text = esc_html__('in recognition of completing', 'sikshya');
        $issued_label = esc_html__('Issued', 'sikshya');
        $credential_label = esc_html__('Credential ID', 'sikshya');
        $cert_no_label = esc_html__('Certificate No.', 'sikshya');
        $signature_caption = esc_html__('Authorized by', 'sikshya');
        $verify_text = esc_html__('Verify online', 'sikshya');

        return '<div style="' . $css_outer . '">'
            . '<div style="' . $css_accent . '"></div>'
            . '<div style="' . $css_corner_strip . '"></div>'
            . '<div style="' . $css_inner . '">'
                . '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;">'
                    . '<div>'
                        . '<div style="font-size:11px;font-weight:800;letter-spacing:.32em;color:#1d4ed8;">' . $eyebrow_top . '</div>'
                        . '<div style="margin-top:4px;font-size:13px;color:#475569;letter-spacing:.06em;">' . $sub . '</div>'
                    . '</div>'
                    . '<div style="text-align:right;">'
                        . '<div style="font-size:13px;font-weight:700;color:#0f172a;">{{site_name}}</div>'
                        . '<div style="margin-top:4px;font-size:11px;color:#64748b;letter-spacing:.04em;">' . $issued_label . ' · {{issued_date}}</div>'
                    . '</div>'
                . '</div>'
                . '<div style="text-align:left;">'
                    . '<div style="font-size:13px;color:#64748b;letter-spacing:.05em;text-transform:uppercase;font-weight:600;">' . $presented_to . '</div>'
                    . '<div style="margin-top:10px;font-size:46px;font-weight:800;color:#0f172a;letter-spacing:-0.01em;line-height:1.05;">{{learner_name}}</div>'
                    . '<div style="margin-top:18px;font-size:13px;color:#64748b;">' . $for_text . '</div>'
                    . '<div style="margin-top:6px;font-size:22px;font-weight:700;color:#1d4ed8;letter-spacing:-0.005em;">{{course_title}}</div>'
                . '</div>'
                . '<div>'
                    . '<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;">'
                        . '<div style="font-size:11px;color:#64748b;line-height:1.6;">'
                            . '<div style="font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:.1em;">' . $credential_label . '</div>'
                            . '<div style="margin-top:2px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#0f172a;font-size:12px;">{{verification_code}}</div>'
                        . '</div>'
                        . '<div style="text-align:center;">'
                            . '<div style="border-top:2px solid #0f172a;padding-top:6px;min-width:180px;">'
                                . '<div style="font-size:13px;font-weight:700;color:#0f172a;">{{site_name}}</div>'
                            . '</div>'
                            . '<div style="margin-top:4px;font-size:10px;color:#64748b;letter-spacing:.12em;text-transform:uppercase;font-weight:600;">' . $signature_caption . '</div>'
                        . '</div>'
                        . '<div style="text-align:right;font-size:11px;color:#64748b;line-height:1.6;">'
                            . '<div style="font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:.1em;">' . $cert_no_label . '</div>'
                            . '<div style="margin-top:2px;color:#0f172a;font-size:12px;">{{certificate_number}}</div>'
                        . '</div>'
                    . '</div>'
                    . '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#64748b;">'
                        . '<span>' . $verify_text . ': <span style="color:#1d4ed8;font-weight:600;">{{verification_url}}</span></span>'
                        . '<span style="font-weight:600;color:#1d4ed8;letter-spacing:.06em;">' . $eyebrow_top . '</span>'
                    . '</div>'
                . '</div>'
            . '</div>'
        . '</div>';
    }
}
