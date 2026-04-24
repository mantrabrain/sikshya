<?php

namespace Sikshya\Core;

use Sikshya\Services\Settings;
use Sikshya\Services\PostTypeService;

/**
 * Plugin Activator
 *
 * @package Sikshya\Core
 */
class Activator
{
    /**
     * Activate the plugin
     */
    public static function activate(): void
    {
        // Check requirements
        if (!Requirements::check()) {
            deactivate_plugins(plugin_basename(SIKSHYA_PLUGIN_FILE));
            wp_die(__('Sikshya LMS requires WordPress 6.0+ and PHP 8.1+', 'sikshya'));
        }

        // Create database tables + incremental migrations
        $database = new \Sikshya\Database\Database(Plugin::getInstance());
        $database->createTables();
        $database->maybeUpgrade();

        // Create user roles
        self::createRoles();

        // Set default options
        self::setDefaultOptions();

        // Create default certificate templates for Free users.
        self::createDefaultCertificateTemplates();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation time
        Settings::setRaw('sikshya_activation_time', current_time('timestamp'));

        // Trigger activation hook
        do_action('sikshya_activated');
    }

    /**
     * Create user roles
     */
    private static function createRoles(): void
    {
        // Instructor role
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

        // Student role
        add_role('sikshya_student', __('Student', 'sikshya'), [
            'read' => true,
            'enroll_sikshya_courses' => true,
            'access_sikshya_courses' => true,
            'submit_sikshya_assignments' => true,
            'take_sikshya_quizzes' => true,
            'view_sikshya_certificates' => true,
        ]);

        // Assistant role
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

        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'manage_sikshya',
                'edit_sikshya_courses',
                'edit_others_sikshya_courses',
                'publish_sikshya_courses',
                'read_private_sikshya_courses',
                'delete_sikshya_courses',
                'delete_private_sikshya_courses',
                'delete_published_sikshya_courses',
                'delete_others_sikshya_courses',
                'edit_private_sikshya_courses',
                'edit_published_sikshya_courses',
                'edit_sikshya_lessons',
                'edit_others_sikshya_lessons',
                'publish_sikshya_lessons',
                'read_private_sikshya_lessons',
                'delete_sikshya_lessons',
                'delete_private_sikshya_lessons',
                'delete_published_sikshya_lessons',
                'delete_others_sikshya_lessons',
                'edit_private_sikshya_lessons',
                'edit_published_sikshya_lessons',
                'edit_sikshya_quizzes',
                'edit_others_sikshya_quizzes',
                'publish_sikshya_quizzes',
                'read_private_sikshya_quizzes',
                'delete_sikshya_quizzes',
                'delete_private_sikshya_quizzes',
                'delete_published_sikshya_quizzes',
                'delete_others_sikshya_quizzes',
                'edit_private_sikshya_quizzes',
                'edit_published_sikshya_quizzes',
            ];

            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Set default options
     */
    private static function setDefaultOptions(): void
    {
        // General settings
        $general_settings = [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'currency' => 'USD',
            'currency_symbol' => '$',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i a',
            'timezone' => wp_timezone_string(),
        ];

        Settings::setRaw('sikshya_general_settings', $general_settings);

        // Course settings
        $course_settings = [
            'courses_per_page' => 12,
            'featured_courses_count' => 6,
            'popular_courses_count' => 6,
            'enable_reviews' => true,
            'enable_ratings' => true,
            'enable_certificates' => true,
            'enable_progress_tracking' => true,
            'enable_discussions' => true,
            'enable_assignments' => true,
        ];

        Settings::setRaw('sikshya_course_settings', $course_settings);

        // Email settings
        $email_settings = [
            'from_name' => get_bloginfo('name'),
            'from_email' => Settings::getRaw('admin_email'),
            'welcome_email' => true,
            'course_completion_email' => true,
            'certificate_email' => true,
            'reminder_emails' => true,
        ];

        Settings::setRaw('sikshya_email_settings', $email_settings);

        // Payment settings
        $payment_settings = [
            'enable_payments' => false,
            'payment_methods' => ['offline', 'stripe', 'paypal'],
            'test_mode' => true,
            'currency' => 'USD',
        ];

        Settings::setRaw('sikshya_payment_settings', $payment_settings);

        // Uninstall options
        $uninstall_options = [
            'remove_data' => false,
            'remove_tables' => false,
            'remove_options' => false,
            'remove_files' => false,
        ];

        Settings::setRaw('sikshya_uninstall_options', $uninstall_options);
    }

    /**
     * Create a couple of published certificate templates so free users can start immediately.
     *
     * IMPORTANT: These are regular `sikshya_certificate` posts with HTML `post_content`
     * (not visual-builder layouts). They intentionally do NOT include QR placeholders.
     */
    private static function createDefaultCertificateTemplates(): void
    {
        if (!function_exists('wp_insert_post')) {
            return;
        }

        // Idempotent: never create duplicates.
        if (Settings::getRaw('sikshya_default_certificate_templates_created', false)) {
            return;
        }

        // Ensure the CPT exists before inserting posts.
        try {
            $pts = new PostTypeService(Plugin::getInstance());
            $pts->registerCertificatePostType();
        } catch (\Throwable $e) {
            // If CPT is not available, skip silently.
            return;
        }

        $now = current_time('mysql');

        $templates = [
            [
                'title' => __('Certificate (Classic)', 'sikshya'),
                'html' => self::defaultClassicCertificateHtml(),
                'meta' => [
                    '_sikshya_certificate_orientation' => 'landscape',
                    '_sikshya_certificate_page_size' => 'a4',
                    '_sikshya_certificate_page_color' => '#ffffff',
                    '_sikshya_certificate_page_pattern' => 'none',
                    '_sikshya_certificate_page_deco' => 'none',
                ],
            ],
            [
                'title' => __('Certificate (Modern)', 'sikshya'),
                'html' => self::defaultModernCertificateHtml(),
                'meta' => [
                    '_sikshya_certificate_orientation' => 'landscape',
                    '_sikshya_certificate_page_size' => 'a4',
                    '_sikshya_certificate_page_color' => '#ffffff',
                    '_sikshya_certificate_page_pattern' => 'none',
                    '_sikshya_certificate_page_deco' => 'none',
                ],
            ],
        ];

        $created_any = false;
        foreach ($templates as $t) {
            $post_id = wp_insert_post(
                [
                    'post_type' => 'sikshya_certificate',
                    'post_status' => 'publish',
                    'post_title' => (string) $t['title'],
                    'post_content' => (string) $t['html'],
                    'post_date' => $now,
                    'post_date_gmt' => get_gmt_from_date($now),
                ],
                true
            );

            if (is_wp_error($post_id) || (int) $post_id <= 0) {
                continue;
            }
            $created_any = true;

            // Assign meta.
            if (isset($t['meta']) && is_array($t['meta'])) {
                foreach ($t['meta'] as $k => $v) {
                    update_post_meta((int) $post_id, (string) $k, (string) $v);
                }
            }

            // Stable template preview hash (64 hex) for public preview links.
            try {
                $hash = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $hash = bin2hex(openssl_random_pseudo_bytes(32) ?: random_bytes(32));
            }
            update_post_meta((int) $post_id, '_sikshya_certificate_preview_hash', $hash);
        }

        if ($created_any) {
            Settings::setRaw('sikshya_default_certificate_templates_created', 1);
        }
    }

    private static function defaultClassicCertificateHtml(): string
    {
        // No QR in Free templates.
        return '<div style="position:relative;width:100%;height:100%;box-sizing:border-box;padding:56px 64px;">'
            . '<div style="position:absolute;inset:18px;border:2px solid #e2e8f0;border-radius:10px;"></div>'
            . '<div style="position:absolute;inset:32px;border:1px solid #cbd5e1;border-radius:8px;"></div>'
            . '<div style="position:relative;z-index:1;text-align:center;">'
            . '<div style="font-size:12px;letter-spacing:.22em;font-weight:600;color:#475569;">' . esc_html__('CERTIFICATE OF COMPLETION', 'sikshya') . '</div>'
            . '<div style="margin-top:18px;font-size:44px;font-weight:700;color:#0f172a;">' . esc_html__('Certificate', 'sikshya') . '</div>'
            . '<div style="margin-top:6px;font-size:13px;color:#64748b;">' . esc_html__('This is to certify that', 'sikshya') . '</div>'
            . '<div style="margin-top:16px;font-size:28px;font-weight:700;color:#0f172a;">{{learner_name}}</div>'
            . '<div style="margin-top:10px;font-size:13px;color:#64748b;">' . esc_html__('has successfully completed the course', 'sikshya') . '</div>'
            . '<div style="margin-top:10px;font-size:18px;font-weight:600;color:#0f172a;">{{course_title}}</div>'
            . '<div style="margin-top:14px;font-size:13px;color:#64748b;">' . esc_html__('Issued on', 'sikshya') . ' <strong style="color:#0f172a">{{issued_date}}</strong></div>'
            . '</div>'
            . '<div style="position:absolute;left:64px;right:64px;bottom:54px;display:flex;gap:24px;justify-content:space-between;align-items:flex-end;">'
            . '<div style="text-align:left;font-size:12px;color:#64748b;">'
            . '<div style="font-weight:600;color:#0f172a;">' . esc_html__('Credential ID', 'sikshya') . '</div>'
            . '<div style="margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">{{verification_code}}</div>'
            . '</div>'
            . '<div style="text-align:right;font-size:12px;color:#64748b;">'
            . '<div style="font-weight:600;color:#0f172a;">' . esc_html__('Issued by', 'sikshya') . '</div>'
            . '<div style="margin-top:4px;">{{site_name}}</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function defaultModernCertificateHtml(): string
    {
        // No QR in Free templates.
        return '<div style="position:relative;width:100%;height:100%;box-sizing:border-box;padding:58px 70px;">'
            . '<div style="position:absolute;inset:0;background:linear-gradient(135deg,#0f172a 0%,#0f172a 18%,#ffffff 18%,#ffffff 100%);opacity:.06;"></div>'
            . '<div style="position:absolute;inset:20px;border:1px solid #e2e8f0;"></div>'
            . '<div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;">'
            . '<div style="display:flex;justify-content:space-between;align-items:flex-start;">'
            . '<div>'
            . '<div style="font-size:12px;font-weight:700;letter-spacing:.18em;color:#0f172a;">' . esc_html__('CERTIFICATE', 'sikshya') . '</div>'
            . '<div style="margin-top:8px;font-size:13px;color:#64748b;">' . esc_html__('of Achievement', 'sikshya') . '</div>'
            . '</div>'
            . '<div style="text-align:right;font-size:12px;color:#64748b;">'
            . '<div style="font-weight:600;color:#0f172a;">{{site_name}}</div>'
            . '<div style="margin-top:6px;">' . esc_html__('Issued', 'sikshya') . ' {{issued_date}}</div>'
            . '</div>'
            . '</div>'
            . '<div style="flex:1;display:flex;flex-direction:column;justify-content:center;text-align:center;">'
            . '<div style="font-size:14px;color:#64748b;">' . esc_html__('Presented to', 'sikshya') . '</div>'
            . '<div style="margin-top:14px;font-size:34px;font-weight:800;color:#0f172a;">{{learner_name}}</div>'
            . '<div style="margin-top:12px;font-size:14px;color:#64748b;">' . esc_html__('for successfully completing', 'sikshya') . '</div>'
            . '<div style="margin-top:10px;font-size:20px;font-weight:650;color:#0f172a;">{{course_title}}</div>'
            . '</div>'
            . '<div style="display:flex;justify-content:space-between;align-items:flex-end;">'
            . '<div style="font-size:12px;color:#64748b;">'
            . '<div style="font-weight:600;color:#0f172a;">' . esc_html__('Credential ID', 'sikshya') . '</div>'
            . '<div style="margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">{{verification_code}}</div>'
            . '</div>'
            . '<div style="text-align:right;font-size:12px;color:#64748b;">'
            . '<div style="font-weight:600;color:#0f172a;">' . esc_html__('Certificate No.', 'sikshya') . '</div>'
            . '<div style="margin-top:4px;">{{certificate_number}}</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
