<?php

namespace Sikshya\Admin;

use Sikshya\Admin\Controllers\ReportController;
use Sikshya\Constants\AdminPages;
use Sikshya\Constants\PostTypes;
use Sikshya\Addons\Addons;
use Sikshya\Licensing\Pro;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\Settings;

/**
 * Bootstrap payload for the React admin shell (URL-based pages, full-width layout).
 */
final class ReactAdminConfig
{
    /**
     * Volatile shell payload (alerts, licensing, Pro version flags) for REST refresh without full reload.
     *
     * @return array{shellAlerts: array<int, array<string, mixed>>, licensing: array<string, mixed>, proVersion: string, proPluginVersion: string}
     */
    public static function shellBootstrap(string $pageKey): array
    {
        $pro_plugin_version = defined('SIKSHYA_PRO_VERSION') ? (string) constant('SIKSHYA_PRO_VERSION') : '';
        $pro_version = ($pro_plugin_version !== '' && Pro::isActive()) ? $pro_plugin_version : '';

        return [
            'shellAlerts' => apply_filters('sikshya_react_shell_alerts', [], $pageKey),
            'licensing' => Pro::getClientPayload(),
            'proVersion' => $pro_version,
            'proPluginVersion' => $pro_plugin_version,
        ];
    }

    /**
     * Build config for window.sikshyaReact.
     *
     * @param string               $pageKey     Logical page (dashboard, courses, add-course, …).
     * @param array<string, mixed> $initialData Page-specific data (stats, chart, …).
     * @return array<string, mixed>
     */
    public static function build(string $pageKey, array $initialData = []): array
    {
        $user = wp_get_current_user();
        $query = [];
        foreach (['tab', 'course_id', 'id', 'view', 'post_type', 'post_id', 'template_id', 'force_bundle_ui'] as $key) {
            if (isset($_GET[$key])) {
                $query[$key] = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            }
        }

        $email_raw = (string) $user->user_email;
        $gravatar_url = '';
        if ($email_raw !== '' && is_email($email_raw)) {
            $gravatar_url = sprintf(
                'https://www.gravatar.com/avatar/%s?s=160&d=mp&r=g',
                md5(strtolower(trim($email_raw)))
            );
        }
        $avatar_fallback = get_avatar_url($user->ID, ['size' => 160]);
        $avatar_url = $gravatar_url !== '' ? $gravatar_url : (string) $avatar_fallback;

        $shell = self::shellBootstrap($pageKey);

        $logo_path = SIKSHYA_PLUGIN_DIR . 'assets/images/logo-white.png';
        $logo_url  = file_exists($logo_path) ? (SIKSHYA_PLUGIN_URL . 'assets/images/logo-white.png') : '';

        $config = [
            'page' => $pageKey,
            'version' => SIKSHYA_VERSION,
            /**
             * Pro add-on semver when the licence is active (empty when Pro is inactive or unlicensed).
             * @deprecated Prefer {@see self::shellBootstrap()} `proPluginVersion` + `licensing.isProActive` for UI.
             */
            'proVersion' => $shell['proVersion'],
            /** Pro add-on semver when the Pro plugin is present (even before licence activation). */
            'proPluginVersion' => $shell['proPluginVersion'],
            'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
            'wpRestUrl' => esc_url_raw(rest_url('wp/v2/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('/'),
            /** `admin.php?page=sikshya` (append `&view=` for subpages). */
            'appAdminBase' => add_query_arg(['page' => AdminPages::DASHBOARD], admin_url('admin.php')),
            'siteUrl' => home_url('/'),
            'pluginUrl' => SIKSHYA_PLUGIN_URL,
            'branding' => [
                'pluginName' => function_exists('sikshya_brand_name') ? (string) sikshya_brand_name('admin') : __('Sikshya LMS', 'sikshya'),
                'logoUrl' => esc_url_raw((string) $logo_url),
            ],
            // Frontend permalink bases (must mirror global Sikshya permalink settings).
            'permalinks' => PermalinkService::get(),
            'plainPermalinks' => PermalinkService::isPlainPermalinks(),
            'postTypes' => [
                'course' => PostTypes::COURSE,
                'lesson' => PostTypes::LESSON,
                'quiz' => PostTypes::QUIZ,
                'assignment' => PostTypes::ASSIGNMENT,
            ],
            'user' => [
                'name' => $user->display_name ?: $user->user_login,
                'email' => $email_raw,
                'avatarUrl' => esc_url_raw($avatar_url),
                'profileUrl' => esc_url_raw(admin_url('profile.php')),
                'logoutUrl' => esc_url_raw(wp_logout_url(admin_url())),
            ],
            'navigation' => self::navigationItems(),
            'initialData' => $initialData,
            'query' => $query,
            /** Feature catalog + gates; all admin UIs read this for upsell / locks. */
            'licensing' => $shell['licensing'],
            /**
             * Full-width in-shell alerts (below the React header). Not WordPress {@see admin_notices()}.
             *
             * @var array<int, array<string, mixed>>
             */
            'shellAlerts' => $shell['shellAlerts'],
            /**
             * Storefront: offline / manual gateway enabled (see Settings → Payment).
             * Used by Orders and hubs so help text does not imply it is still turned off.
             */
            'offlineCheckoutEnabled' => self::isOfflineCheckoutEnabled(),
        ];

        if (current_user_can('manage_options')) {
            $config['setupWizardUrl'] = esc_url_raw(
                add_query_arg(
                    [
                        'page' => SetupWizardController::MENU_SLUG,
                        SetupWizardController::STEP_QUERY => '1',
                    ],
                    admin_url('admin.php')
                )
            );
        }

        /**
         * Allow Pro add-ons and custom code to inject extra config keys.
         *
         * Important: The React shell expects a stable minimum payload. Some third-party code
         * may filter config keys; keep required keys populated to avoid hard crashes.
         *
         * @param array<string, mixed> $config
         * @param string              $pageKey
         */
        $filtered = (array) apply_filters('sikshya_react_admin_config', $config, $pageKey);

        if (!isset($filtered['page']) || !is_string($filtered['page']) || $filtered['page'] === '') {
            $filtered['page'] = $config['page'];
        }

        if (!isset($filtered['user']) || !is_array($filtered['user'])) {
            $filtered['user'] = $config['user'];
        }
        if (!isset($filtered['user']['name']) || !is_string($filtered['user']['name']) || $filtered['user']['name'] === '') {
            $filtered['user']['name'] = $config['user']['name'];
        }
        if (!isset($filtered['user']['avatarUrl']) || !is_string($filtered['user']['avatarUrl'])) {
            $filtered['user']['avatarUrl'] = $config['user']['avatarUrl'];
        }

        if (!isset($filtered['navigation']) || !is_array($filtered['navigation'])) {
            $filtered['navigation'] = $config['navigation'];
        }

        return $filtered;
    }

    /**
     * In-app URL: one wp-admin page (`page=sikshya`) with a `view` sub-route.
     *
     * @param array<string, string|int> $extra Query args (merged; overwrites reserved keys if duplicated).
     */
    public static function reactAppUrl(string $view, array $extra = []): string
    {
        $args = [
            'page' => AdminPages::DASHBOARD,
            'view' => $view,
        ];

        foreach ($extra as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $k = sanitize_key((string) $key);
            if ($k === '') {
                continue;
            }
            $args[$k] = is_scalar($value) ? (string) $value : '';
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Nested nav for React sidebar (Course group with Lessons, Quizzes, etc.).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navigationItems(): array
    {
        $items = [];

        if (current_user_can('edit_posts')) {
            $items[] = [
                'id' => 'dashboard',
                'label' => __('Dashboard', 'sikshya'),
                'icon' => 'dashboard',
                'href' => self::reactAppUrl('dashboard'),
            ];
        }

        // Course group: replaces 9 children (Courses + Categories + Lessons/Quizzes/
        // Assignments/Questions/Chapters + Drip + Prerequisites) with 4 — five of those
        // are now tabs inside the Content library hub, and the two access-rules pages
        // are tabs inside the Learning rules hub.
        $course_children = [
            [
                'id' => 'courses',
                'label' => sikshya_label('courses', __('Courses', 'sikshya'), 'admin'),
                'icon' => 'table',
                'href' => self::reactAppUrl('courses'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'course-categories',
                'label' => __('Categories', 'sikshya'),
                'icon' => 'tag',
                'href' => self::reactAppUrl('course-categories'),
                'cap' => 'manage_categories',
            ],
            [
                'id' => 'content-library',
                'label' => __('Content library', 'sikshya'),
                'icon' => 'bookOpen',
                'href' => self::reactAppUrl('content-library'),
                'cap' => 'edit_posts',
            ],
            self::withLearningRulesNavBadge(
                [
                    'id' => 'learning-rules',
                    'label' => __('Learning rules', 'sikshya'),
                    'icon' => 'schedule',
                    'href' => self::reactAppUrl('learning-rules'),
                    'cap' => 'edit_posts',
                ]
            ),
            self::withNavGate([
                'id' => 'reviews',
                'label' => __('Reviews', 'sikshya'),
                'icon' => 'star',
                'href' => self::reactAppUrl('reviews'),
                'cap' => 'edit_posts',
            ], 'course_reviews'),
            self::withNavGate([
                'id' => 'course-team',
                'label' => __('Course staff', 'sikshya'),
                'icon' => 'course',
                'href' => self::reactAppUrl('course-team'),
                'cap' => 'edit_posts',
            ], 'multi_instructor'),
        ];

        $children = self::filterNavChildren($course_children);
        if ($children !== []) {
            $items[] = [
                'id' => 'course',
                'label' => sikshya_label('course', __('Course', 'sikshya'), 'admin'),
                'icon' => 'course',
                'children' => $children,
            ];
        }

        // Certificates: was a 2-child group (Templates + Issued) — now a single hub
        // entry with those as tabs.
        if (current_user_can('edit_posts')) {
            $items[] = [
                'id' => 'certificates-hub',
                'label' => __('Certificates', 'sikshya'),
                'icon' => 'badge',
                'href' => self::reactAppUrl('certificates-hub'),
            ];
        }

        // People: Students/Instructors collapse into the People hub (tabs). Enrollments
        // is a different entity (per-course join table) so it stays separate.
        $people_children = [
            [
                'id' => 'people',
                'label' => sprintf(
                    /* translators: 1: students label, 2: instructors label */
                    __('%1$s & %2$s', 'sikshya'),
                    sikshya_label('students', __('Students', 'sikshya'), 'admin'),
                    sikshya_label('instructors', __('Instructors', 'sikshya'), 'admin')
                ),
                'icon' => 'users',
                'href' => self::reactAppUrl('people'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'enrollments',
                'label' => sikshya_label('enrollments', __('Enrollments', 'sikshya'), 'admin'),
                'icon' => 'clipboard',
                'href' => self::reactAppUrl('enrollments'),
                'cap' => 'sikshya_enrollments_nav',
            ],
        ];
        $people_children = self::filterPeopleNavChildren($people_children);
        if ($people_children !== []) {
            $items[] = [
                'id' => 'people-group',
                'label' => __('People', 'sikshya'),
                'icon' => 'users',
                'children' => $people_children,
            ];
        }

        // Reports: Calendar moves into the Reports hub as a tab; Activity log moves to
        // Tools (it's an audit trail, not a report). Gradebook keeps its own entry —
        // grading is a different daily job from "view metrics".
        $reports_children = [
            [
                'id' => 'reports',
                'label' => __('Overview', 'sikshya'),
                'icon' => 'chart',
                'href' => self::reactAppUrl('reports'),
                'cap' => 'edit_posts',
            ],
            [
                'id' => 'gradebook',
                'label' => __('Gradebook', 'sikshya'),
                'icon' => 'table',
                'href' => self::reactAppUrl('gradebook'),
                'cap' => 'edit_posts',
            ],
        ];

        foreach ($reports_children as $i => $row) {
            $id = isset($row['id']) ? (string) $row['id'] : '';
            if ($id === 'gradebook') {
                $reports_children[ $i ] = self::withNavGate($row, 'gradebook');
            }
        }
        $reports_children = self::filterNavChildren($reports_children);
        if ($reports_children !== []) {
            $items[] = [
                'id' => 'reports-group',
                'label' => __('Reports', 'sikshya'),
                'icon' => 'chart',
                'children' => $reports_children,
            ];
        }

        // Commerce: Payments + Orders are the same job (transactions) so they collapse
        // into the Sales hub. Coupons / Subscriptions / Marketplace / bundle tools are
        // separate entries. Primary bundle creation is Courses → Add course → “Course bundle”.
        $commerce_children = [
            [
                'id' => 'sales',
                'label' => __('Sales', 'sikshya'),
                'icon' => 'columns',
                'href' => self::reactAppUrl('sales'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'coupons',
                'label' => __('Coupons', 'sikshya'),
                'icon' => 'tag',
                'href' => self::reactAppUrl('coupons'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'subscriptions',
                'label' => __('Subscriptions', 'sikshya'),
                'icon' => 'plusCircle',
                'href' => self::reactAppUrl('subscriptions'),
                'cap' => 'manage_options',
            ],
            [
                'id' => 'marketplace',
                'label' => __('Marketplace', 'sikshya'),
                'icon' => 'course',
                'href' => self::reactAppUrl('marketplace'),
                'cap' => 'manage_options',
            ],
        ];

        foreach ($commerce_children as $i => $row) {
            $id = isset($row['id']) ? (string) $row['id'] : '';
            if ($id === 'subscriptions') {
                $commerce_children[ $i ] = self::withNavGate($row, 'subscriptions');
            } elseif ($id === 'marketplace') {
                $commerce_children[ $i ] = self::withNavGate($row, 'marketplace_multivendor');
            }
        }
        $commerce_children = self::filterNavChildren($commerce_children);
        if ($commerce_children !== []) {
            $items[] = [
                'id' => 'commerce',
                'label' => __('Commerce', 'sikshya'),
                'icon' => 'columns',
                'children' => $commerce_children,
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'addons',
                'label' => __('Addons', 'sikshya'),
                'icon' => 'plusCircle',
                'href' => self::reactAppUrl('addons'),
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'license',
                'label' => __('License', 'sikshya'),
                'icon' => 'licenseKey',
                'href' => self::reactAppUrl('license'),
            ];
        }

        // Integrations: Webhooks/API + CRM automation are the same domain (outbound
        // automation) so they collapse into one tabbed hub entry.
        if (current_user_can('manage_options')) {
            $items[] = self::withIntegrationsHubNavBadge(
                [
                    'id' => 'integrations-hub',
                    'label' => __('Integrations', 'sikshya'),
                    'icon' => 'columns',
                    'href' => self::reactAppUrl('integrations-hub'),
                ]
            );
        }

        // Branding: White label + Social login both shape the public-facing brand /
        // auth surface, so they collapse into one tabbed hub entry.
        if (current_user_can('manage_options')) {
            $items[] = self::withBrandingHubNavBadge(
                [
                    'id' => 'branding',
                    'label' => __('Branding', 'sikshya'),
                    'icon' => 'badge',
                    'href' => self::reactAppUrl('branding'),
                ]
            );
        }

        // Email: Delivery + Templates collapse into one Email hub entry (tabs).
        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'email-hub',
                'label' => __('Email', 'sikshya'),
                'icon' => 'plusDocument',
                'href' => self::reactAppUrl('email-hub'),
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = [
                'id' => 'settings',
                'label' => __('Settings', 'sikshya'),
                'icon' => 'cog',
                'href' => self::reactAppUrl('settings'),
            ];
        }

        if (current_user_can('manage_options')) {
            $items[] = self::withNavGate(
                [
                    'id' => 'grading',
                    'label' => __('Grading', 'sikshya'),
                    'icon' => 'badge',
                    'href' => self::reactAppUrl('grading'),
                ],
                'gradebook'
            );
        }

        if (current_user_can('manage_options')) {
            // Tools stays accessible from the top header. Remove from sidebar to reduce duplication.
        }

        return $items;
    }

    /**
     * Optional sidebar badge for gated features (routes stay visible).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function withNavGate(array $row, string $feature_id): array
    {
        if (! Pro::feature($feature_id)) {
            $row['badge'] = 'upgrade';
        } elseif (! Addons::isEnabled($feature_id)) {
            $row['badge'] = 'off';
        }

        return $row;
    }

    /**
     * Integrations combines webhooks + API keys (Scale-tier features).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function withIntegrationsNavBadge(array $row): array
    {
        $webhooks_ok = Pro::feature('webhooks');
        $zapier_ok = Pro::feature('zapier');
        $keys_ok = Pro::feature('public_api_keys');
        if ((! $webhooks_ok && ! $zapier_ok) || ! $keys_ok) {
            $row['badge'] = 'upgrade';
        } elseif (
            (! Addons::isEnabled('webhooks') && ! Addons::isEnabled('zapier'))
            || ! Addons::isEnabled('public_api_keys')
        ) {
            $row['badge'] = 'off';
        }

        return $row;
    }

    /**
     * Single-entry Integrations hub badge: shows "upgrade" if NEITHER webhooks/api nor
     * CRM is licensed (so the entire hub is locked), and "off" if all licensed
     * integrations have their addons turned off.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function withIntegrationsHubNavBadge(array $row): array
    {
        $webhooks_ok = Pro::feature('webhooks');
        $zapier_ok = Pro::feature('zapier');
        $keys_ok = Pro::feature('public_api_keys');
        $mkt_ok = Pro::feature('email_marketing');

        if (! $webhooks_ok && ! $zapier_ok && ! $keys_ok && ! $mkt_ok) {
            $row['badge'] = 'upgrade';

            return $row;
        }

        $any_on = false;
        if ($webhooks_ok && Addons::isEnabled('webhooks')) {
            $any_on = true;
        }
        if ($zapier_ok && Addons::isEnabled('zapier')) {
            $any_on = true;
        }
        if ($keys_ok && Addons::isEnabled('public_api_keys')) {
            $any_on = true;
        }
        if ($mkt_ok && Addons::isEnabled('email_marketing')) {
            $any_on = true;
        }
        if (! $any_on) {
            $row['badge'] = 'off';
        }

        return $row;
    }

    /**
     * Branding hub badge: covers white label + social login. We surface the badge if
     * neither addon is licensed; "off" if licensed but both turned off.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function withBrandingHubNavBadge(array $row): array
    {
        $wl_ok = Pro::feature('white_label');
        $sl_ok = Pro::feature('social_login');
        if (! $wl_ok && ! $sl_ok) {
            $row['badge'] = 'upgrade';

            return $row;
        }

        $any_on = false;
        if ($wl_ok && Addons::isEnabled('white_label')) {
            $any_on = true;
        }
        if ($sl_ok && Addons::isEnabled('social_login')) {
            $any_on = true;
        }
        if (! $any_on) {
            $row['badge'] = 'off';
        }

        return $row;
    }

    /**
     * Learning rules hub badge: covers content drip + prerequisites. Same model as
     * the branding hub above.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function withLearningRulesNavBadge(array $row): array
    {
        $drip_ok = Pro::feature('content_drip');
        $prereq_ok = Pro::feature('prerequisites');
        if (! $drip_ok && ! $prereq_ok) {
            $row['badge'] = 'upgrade';

            return $row;
        }

        $any_on = false;
        if ($drip_ok && Addons::isEnabled('content_drip')) {
            $any_on = true;
        }
        if ($prereq_ok && Addons::isEnabled('prerequisites')) {
            $any_on = true;
        }
        if (! $any_on) {
            $row['badge'] = 'off';
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function filterNavChildren(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cap = isset($row['cap']) ? (string) $row['cap'] : 'edit_posts';
            if (!current_user_can($cap)) {
                continue;
            }
            unset($row['cap']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * People nav: same capability rules as legacy flat menu (enrollments uses Sikshya caps).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function filterPeopleNavChildren(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cap = isset($row['cap']) ? (string) $row['cap'] : 'edit_posts';
            if ($cap === 'sikshya_enrollments_nav') {
                if (!current_user_can('manage_sikshya') && !current_user_can('edit_sikshya_courses')) {
                    continue;
                }
            } elseif (!current_user_can($cap)) {
                continue;
            }
            unset($row['cap']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Dashboard KPIs: post counts, roles, enrollments/revenue when DB tables exist.
     *
     * @return array<string, int|string|bool>
     */
    public static function enrichedDashboardStats(): array
    {
        $course_counts = wp_count_posts(PostTypes::COURSE);
        $published = isset($course_counts->publish) ? (int) $course_counts->publish : 0;
        $draft = isset($course_counts->draft) ? (int) $course_counts->draft : 0;
        $user_counts = count_users();
        $students = isset($user_counts['avail_roles']['sikshya_student'])
            ? (int) $user_counts['avail_roles']['sikshya_student']
            : 0;
        $instructors = isset($user_counts['avail_roles']['sikshya_instructor'])
            ? (int) $user_counts['avail_roles']['sikshya_instructor']
            : 0;

        $snap = ReportController::getReportsPageSnapshot();
        $st = $snap['stats'] ?? [];

        $cert_counts = wp_count_posts(PostTypes::CERTIFICATE);

        return [
            'publishedCourses' => $published,
            'draftCourses' => $draft,
            'lessons' => self::count_published_posts(PostTypes::LESSON),
            'quizzes' => self::count_published_posts(PostTypes::QUIZ),
            'assignments' => self::count_published_posts(PostTypes::ASSIGNMENT),
            'questions' => self::count_published_posts(PostTypes::QUESTION),
            'chapters' => self::count_published_posts(PostTypes::CHAPTER),
            'certificateTemplates' => isset($cert_counts->publish) ? (int) $cert_counts->publish : 0,
            'students' => $students,
            'instructors' => $instructors,
            'revenue' => isset($st['revenue_html']) ? (string) $st['revenue_html'] : '$0.00',
            'enrollments' => isset($st['total_enrollments']) ? (int) $st['total_enrollments'] : 0,
            'completedEnrollments' => isset($st['completed_enrollments']) ? (int) $st['completed_enrollments'] : 0,
            'distinctLearners' => isset($st['distinct_learners']) ? (int) $st['distinct_learners'] : 0,
            'hasEnrollmentTable' => !empty($st['has_enrollment_table']),
            'hasPaymentsTable' => !empty($st['has_payments_table']),
        ];
    }

    /**
     * Dashboard stat cards (mirrors legacy dashboard widget data).
     *
     * @return array<string, mixed>
     */
    public static function dashboardInitialData(): array
    {
        return [
            'siteName' => get_bloginfo('name'),
            'stats' => self::enrichedDashboardStats(),
            'recentCourses' => self::dashboard_recent_courses(6),
            'dashboardLinks' => [
                'enrollments' => current_user_can('manage_sikshya') || current_user_can('edit_sikshya_courses'),
                'payments' => current_user_can('manage_options'),
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, status: string, modified: string}>
     */
    private static function dashboard_recent_courses(int $limit): array
    {
        $query = new \WP_Query(
            [
                'post_type' => PostTypes::COURSE,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ]
        );

        $rows = [];
        while ($query->have_posts()) {
            $query->the_post();
            $rows[] = [
                'id' => (int) get_the_ID(),
                'title' => get_the_title(),
                'status' => (string) get_post_status(),
                'modified' => get_post_modified_time('c', true),
            ];
        }
        wp_reset_postdata();

        return $rows;
    }

    private static function count_published_posts(string $post_type): int
    {
        $counts = wp_count_posts($post_type);

        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    /**
     * Mirrors {@see \Sikshya\Commerce\CheckoutService::isOfflinePaymentEnabled()} truthiness for the React shell.
     */
    private static function isOfflineCheckoutEnabled(): bool
    {
        $v = Settings::get('enable_offline_payment', '1');

        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /**
     * Reports chart payload for React.
     *
     * @return array<string, mixed>
     */
    public static function reportsInitialData(): array
    {
        $snap = ReportController::getReportsPageSnapshot();

        return [
            'chart' => $snap['chart'] ?? ['labels' => [], 'counts' => []],
            'stats' => $snap['stats'] ?? [],
        ];
    }
}
