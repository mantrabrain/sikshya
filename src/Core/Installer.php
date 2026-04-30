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

use Sikshya\Certificates\CertificateTemplateDefaults;
use Sikshya\Services\PostTypeService;
use Sikshya\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    /** Stable keys used to identify the seeded Free templates across upgrades. */
    public const DEFAULT_TEMPLATE_KEYS = [
        CertificateTemplateDefaults::KEY_HERITAGE,
        CertificateTemplateDefaults::KEY_VERTEX,
    ];

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
    }

    /**
     * Re-run role bootstrap (calls `installRoles()`; idempotent `add_cap` merge on existing roles).
     *
     * Primary path is {@see install()} on activation. Optional entry point for programmatic repair /
     * legacy import tooling (no dependency on incremental DB version flags).
     */
    public static function syncSikshyaRoleCapabilities(): void
    {
        self::installRoles();
    }

    /**
     * @param array<string, bool> $caps
     */
    private static function mergeCapsOntoRole(string $slug, array $caps): void
    {
        $role = get_role($slug);
        if (!$role) {
            return;
        }
        foreach ($caps as $cap => $granted) {
            if ($granted) {
                $role->add_cap((string) $cap);
            }
        }
    }

    /**
     * Create the Sikshya roles (instructor, student, assistant) and grant the
     * matching capabilities to the WordPress administrator role.
     */
    private static function installRoles(): void
    {
        $instructor_caps = [
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
            /** Explicit wp-admin Sikshya React entry (see Admin::addAdminMenus()). */
            'sikshya_access_admin_app' => true,
        ];

        add_role('sikshya_instructor', __('Instructor', 'sikshya'), $instructor_caps);
        self::mergeCapsOntoRole('sikshya_instructor', $instructor_caps);

        $student_caps = [
            'read' => true,
            'enroll_sikshya_courses' => true,
            'access_sikshya_courses' => true,
            'submit_sikshya_assignments' => true,
            'take_sikshya_quizzes' => true,
            'view_sikshya_certificates' => true,
        ];

        add_role('sikshya_student', __('Student', 'sikshya'), $student_caps);
        self::mergeCapsOntoRole('sikshya_student', $student_caps);

        $assistant_caps = [
            'read' => true,
            'edit_sikshya_courses' => true,
            'edit_published_sikshya_courses' => true,
            'edit_sikshya_lessons' => true,
            'edit_published_sikshya_lessons' => true,
            'edit_sikshya_quizzes' => true,
            'edit_published_sikshya_quizzes' => true,
            'upload_files' => true,
            'view_sikshya_reports' => true,
            'sikshya_access_admin_app' => true,
        ];

        add_role('sikshya_assistant', __('Assistant', 'sikshya'), $assistant_caps);
        self::mergeCapsOntoRole('sikshya_assistant', $assistant_caps);

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'sikshya_access_admin_app',
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

        // Off by default: only wipe plugin data when explicitly enabled in wp-admin settings.
        Settings::set('erase_data_on_uninstall', '0');
        Settings::set('erase_files_on_uninstall', '0');
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

            // Visual builder snapshot (editable in the Certificate template admin).
            if (!empty($template['layout'])) {
                update_post_meta((int) $post_id, '_sikshya_certificate_layout', (string) $template['layout']);
            }

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
     * @return array<int, array{key:string,title:string,html:string,meta:array<string,string>,layout:string}>
     */
    private static function defaultTemplateDefinitions(): array
    {
        return CertificateTemplateDefaults::templateDefinitions();
    }
}
