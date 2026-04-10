<?php

/**
 * Product feature catalog — aligns with Sikshya Requirement.txt (Free / Pro / Elite).
 *
 * @package Sikshya\Licensing
 */

namespace Sikshya\Licensing;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry of licensable features (slugs stable for API + React).
 */
final class FeatureRegistry
{
    /**
     * @return array<string, array{label: string, tier: string, group: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            // —— FREE (always available when core is active) ——
            'core_course_builder' => [
                'label' => __('Course builder (curriculum, chapters)', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Unlimited courses, lessons, drag-and-drop curriculum.', 'sikshya'),
            ],
            'lesson_video_text' => [
                'label' => __('Video & text lessons', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Standard lesson types and materials.', 'sikshya'),
            ],
            'lesson_attachments' => [
                'label' => __('Lesson attachments', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Downloadable lesson materials.', 'sikshya'),
            ],
            'course_preview_faq' => [
                'label' => __('Course preview & FAQ', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Preview mode and FAQ on courses.', 'sikshya'),
            ],
            'announcements' => [
                'label' => __('Announcements', 'sikshya'),
                'tier' => 'free',
                'group' => 'communication',
                'description' => __('Course and site announcements.', 'sikshya'),
            ],
            'student_dashboard' => [
                'label' => __('Student dashboard & progress', 'sikshya'),
                'tier' => 'free',
                'group' => 'learner',
                'description' => __('My courses, progress, completion.', 'sikshya'),
            ],
            'wishlist' => [
                'label' => __('Wishlist', 'sikshya'),
                'tier' => 'free',
                'group' => 'learner',
                'description' => __('Save courses for later.', 'sikshya'),
            ],
            'quiz_basic' => [
                'label' => __('Basic quizzes (MCQ, T/F, short answer)', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Passing score, attempts, timer (basic).', 'sikshya'),
            ],
            'certificates_basic' => [
                'label' => __('Basic certificates', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Issue certificates on completion.', 'sikshya'),
            ],
            'assignments_basic' => [
                'label' => __('Basic assignments', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Simple assignment flow.', 'sikshya'),
            ],
            'checkout_native' => [
                'label' => __('Native checkout (Stripe / PayPal)', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Sell courses with core gateways.', 'sikshya'),
            ],
            'woocommerce_integration' => [
                'label' => __('WooCommerce integration', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Sell via WooCommerce.', 'sikshya'),
            ],
            'manual_enrollment' => [
                'label' => __('Manual enrollment', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Enroll learners without payment.', 'sikshya'),
            ],
            'coupons_basic' => [
                'label' => __('Basic coupons', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Simple discount codes.', 'sikshya'),
            ],
            'free_courses' => [
                'label' => __('Free courses', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Offer courses at no cost.', 'sikshya'),
            ],
            'basic_reports' => [
                'label' => __('Basic reports', 'sikshya'),
                'tier' => 'free',
                'group' => 'analytics',
                'description' => __('Dashboard and simple metrics.', 'sikshya'),
            ],
            'single_instructor' => [
                'label' => __('Single instructor (per site default)', 'sikshya'),
                'tier' => 'free',
                'group' => 'people',
                'description' => __('One primary instructor model; roles still available.', 'sikshya'),
            ],
            'email_notifications_basic' => [
                'label' => __('Basic email notifications', 'sikshya'),
                'tier' => 'free',
                'group' => 'communication',
                'description' => __('Core transactional emails.', 'sikshya'),
            ],
            'page_builder_widgets_basic' => [
                'label' => __('Page builder widgets (basic)', 'sikshya'),
                'tier' => 'free',
                'group' => 'integrations',
                'description' => __('Elementor / block widgets where implemented.', 'sikshya'),
            ],
            // —— PRO (Business) ——
            'content_drip' => [
                'label' => __('Content drip & scheduled unlock', 'sikshya'),
                'tier' => 'pro',
                'group' => 'course',
                'description' => __('Date-based drip, cohort release, scheduled lessons.', 'sikshya'),
            ],
            'prerequisites' => [
                'label' => __('Prerequisites (lessons & courses)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'course',
                'description' => __('Require completion before access.', 'sikshya'),
            ],
            'multi_instructor' => [
                'label' => __('Multi-instructor & co-authors', 'sikshya'),
                'tier' => 'pro',
                'group' => 'people',
                'description' => __('Multiple instructors, permissions, revenue split.', 'sikshya'),
            ],
            'instructor_dashboard' => [
                'label' => __('Instructor dashboard', 'sikshya'),
                'tier' => 'pro',
                'group' => 'people',
                'description' => __('Dedicated instructor analytics and tools.', 'sikshya'),
            ],
            'reports_advanced' => [
                'label' => __('Advanced analytics & exports', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __('Student analytics, completion, revenue reports, exports.', 'sikshya'),
            ],
            'gradebook' => [
                'label' => __('Gradebook', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __('Scores across quizzes and assignments.', 'sikshya'),
            ],
            'activity_log' => [
                'label' => __('Student activity log', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __('Audit learner actions.', 'sikshya'),
            ],
            'certificates_advanced' => [
                'label' => __('Advanced certificates (builder, QR, verification)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __('Visual builder, serial IDs, verification page.', 'sikshya'),
            ],
            'subscriptions' => [
                'label' => __('Subscriptions & memberships', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __('Recurring billing, access expiry, bundles.', 'sikshya'),
            ],
            'course_bundles' => [
                'label' => __('Course bundles', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __('Sell multiple courses together.', 'sikshya'),
            ],
            'coupons_advanced' => [
                'label' => __('Advanced coupons & upsells', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __('Rules, upsells, order bumps.', 'sikshya'),
            ],
            'assignments_advanced' => [
                'label' => __('Advanced assignments', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __('Rubrics, file types, grading workflows.', 'sikshya'),
            ],
            'quiz_advanced' => [
                'label' => __('Advanced quiz types', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __('Question banks, richer types, attempt analytics.', 'sikshya'),
            ],
            'live_classes' => [
                'label' => __('Live classes (Zoom / Meet / Classroom)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'integrations',
                'description' => __('Live sessions and attendance.', 'sikshya'),
            ],
            'social_login' => [
                'label' => __('Social login', 'sikshya'),
                'tier' => 'pro',
                'group' => 'learner',
                'description' => __('OAuth / social sign-in.', 'sikshya'),
            ],
            'drip_notifications' => [
                'label' => __('Drip & automation emails', 'sikshya'),
                'tier' => 'pro',
                'group' => 'communication',
                'description' => __('Scheduled nudges tied to drip.', 'sikshya'),
            ],
            'crm_email_automation' => [
                'label' => __('CRM & email marketing hooks', 'sikshya'),
                'tier' => 'pro',
                'group' => 'integrations',
                'description' => __('ESP/CRM automation (FluentCRM, Mailchimp, etc.).', 'sikshya'),
            ],
            'calendar' => [
                'label' => __('Calendar', 'sikshya'),
                'tier' => 'pro',
                'group' => 'learner',
                'description' => __('Learning calendar and deadlines.', 'sikshya'),
            ],
            'scorm_h5p_pro' => [
                'label' => __('SCORM / H5P (Pro tier)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'integrations',
                'description' => __('Interactive packaged content.', 'sikshya'),
            ],
            // —— ELITE (Agency / marketplace) ——
            'marketplace_multivendor' => [
                'label' => __('Multi-vendor marketplace', 'sikshya'),
                'tier' => 'elite',
                'group' => 'commerce',
                'description' => __('Vendor storefronts, commissions, payouts.', 'sikshya'),
            ],
            'white_label' => [
                'label' => __('White label & branding', 'sikshya'),
                'tier' => 'elite',
                'group' => 'platform',
                'description' => __('Remove branding, custom logo, admin labels.', 'sikshya'),
            ],
            'automation_zapier_webhooks' => [
                'label' => __('Zapier, webhooks, automation', 'sikshya'),
                'tier' => 'elite',
                'group' => 'integrations',
                'description' => __('Outgoing events and no-code automation.', 'sikshya'),
            ],
            'public_api_keys' => [
                'label' => __('Public API & API keys', 'sikshya'),
                'tier' => 'elite',
                'group' => 'platform',
                'description' => __('Headless and external integrations.', 'sikshya'),
            ],
            'multisite_agency' => [
                'label' => __('Multisite & agency license tools', 'sikshya'),
                'tier' => 'elite',
                'group' => 'platform',
                'description' => __('Central management, handoff, blueprints.', 'sikshya'),
            ],
            'enterprise_reports' => [
                'label' => __('Enterprise reporting', 'sikshya'),
                'tier' => 'elite',
                'group' => 'analytics',
                'description' => __('Scheduled reports, deeper exports.', 'sikshya'),
            ],
            'multilingual_enterprise' => [
                'label' => __('Multilingual (WPML / Weglot)', 'sikshya'),
                'tier' => 'elite',
                'group' => 'platform',
                'description' => __('Enterprise localization workflows.', 'sikshya'),
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, tier: string, group: string, description: string}>
     */
    public static function catalogForClient(): array
    {
        $out = [];
        foreach (self::definitions() as $id => $row) {
            $out[] = [
                'id' => $id,
                'label' => $row['label'],
                'tier' => $row['tier'],
                'group' => $row['group'],
                'description' => $row['description'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, tier: string, group: string, description: string}|null>
     */
    public static function get(string $featureId): ?array
    {
        $all = self::definitions();

        return $all[$featureId] ?? null;
    }
}
