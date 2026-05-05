<?php

namespace Sikshya\Admin;

use Sikshya\Core\LegacyAjax;
use Sikshya\Core\Plugin;
use Sikshya\Admin\Controllers\CourseController;
use Sikshya\Admin\Controllers\CourseCategoriesController;
use Sikshya\Admin\Controllers\LessonController;
use Sikshya\Admin\Controllers\QuizController;
use Sikshya\Admin\Controllers\StudentController;
use Sikshya\Admin\Controllers\InstructorController;
use Sikshya\Admin\Controllers\ReportController;
use Sikshya\Admin\Controllers\ToolsController;
use Sikshya\Admin\Controllers\SettingController;
use Sikshya\Constants\AdminPages;
use Sikshya\Constants\PostTypes;
use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Admin\ReactAdminView;
use Sikshya\Security\AdminBackendAccess;
use Sikshya\Services\Settings;
use Sikshya\Admin\ProUpgradeAdminNudge;
use Sikshya\Admin\SetupWizardController;
use Sikshya\Services\AdminMarketingNoticeService;

/**
 * Admin Management Class
 *
 * @package Sikshya\Admin
 */
class Admin
{
    /**
     * Size the Sikshya top-level WP admin menu icon.
     *
     * When `add_menu_page()` receives an image URL, WordPress renders an `<img>` inside the menu icon slot.
     * Without a size override, custom logos can appear oversized/misaligned.
     */
    public static function printSikshyaAdminMenuIconCss(): void
    {
        echo '<style id="sikshya-admin-menu-icon">
            #adminmenu #toplevel_page_sikshya .wp-menu-image img {
                width: 20px;
                height: 20px;
                padding: 7px 0 0;
                margin: 0 auto;
                display: block;
                box-sizing: content-box;
            }
        </style>';
    }

    /**
     * Hide the Setup Wizard entry under the Sikshya top-level menu while keeping it registered.
     *
     * Calling {@see remove_submenu_page()} removes the item from `$submenu`, which breaks
     * {@see get_admin_page_parent()} for `admin.php?page=sikshya-setup`. Core then fails
     * {@see user_can_access_admin_page()} (wrong hook / empty parent) and shows
     * “Sorry, you are not allowed to access this page.” for direct wizard URLs.
     */
    public static function hideSetupWizardSubmenuLink(): void
    {
        if (!is_admin()) {
            return;
        }
        echo '<style id="sikshya-hide-setup-wizard-menu-item">
            #adminmenu #toplevel_page_sikshya .wp-submenu li:has(a[href*="page=sikshya-setup"]) {
                display: none !important;
            }
        </style>';
    }

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Controllers
     *
     * @var array
     */
    private array $controllers = [];

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->initControllers();
        $this->initHooks();
    }

    /**
     * Initialize controllers
     */
    private function initControllers(): void
    {
        $this->controllers['course'] = new \Sikshya\Admin\Controllers\CourseController($this->plugin);
        $this->controllers['course_categories'] = new \Sikshya\Admin\Controllers\CourseCategoriesController($this->plugin);
        $this->controllers['lesson'] = new \Sikshya\Admin\Controllers\LessonController($this->plugin);
        $this->controllers['quiz'] = new \Sikshya\Admin\Controllers\QuizController($this->plugin);
        $this->controllers['student'] = new \Sikshya\Admin\Controllers\StudentController($this->plugin);
        $this->controllers['instructor'] = new \Sikshya\Admin\Controllers\InstructorController($this->plugin);
        $this->controllers['report'] = new \Sikshya\Admin\Controllers\ReportController($this->plugin);
        $this->controllers['setting'] = new \Sikshya\Admin\Controllers\SettingController($this->plugin);
        $this->controllers['tools'] = new \Sikshya\Admin\Controllers\ToolsController($this->plugin);
        $this->controllers['sample_data'] = new \Sikshya\Admin\Controllers\SampleDataController($this->plugin);
        $this->controllers['setup_wizard'] = new SetupWizardController($this->plugin);
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenus']);
        ProUpgradeAdminNudge::register();
        AdminMarketingNoticeService::init();
        add_action('admin_init', [$this, 'initAdmin']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_init', [$this->controllers['setup_wizard'], 'maybeRedirectLegacyWizardAdminUrl'], 0);
        add_action('admin_init', [$this->controllers['setup_wizard'], 'maybeRedirectToWizard'], 1);
        // Process wizard form POSTs (save/skip) before any output, so redirects work cleanly.
        add_action('admin_init', [$this->controllers['setup_wizard'], 'handleEarlyPost'], 2);
        if (LegacyAjax::hooksEnabled()) {
            add_action('wp_ajax_sikshya_admin_action', [$this, 'handleAjaxRequest']);
            add_action('wp_ajax_sikshya_categories_action', [$this, 'handleCategoriesAjaxRequest']);
        }
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        add_action('admin_footer', [$this, 'addAdminFooter']);
        add_filter('admin_body_class', [$this, 'filterAdminBodyClass']);
        add_action('admin_init', [self::class, 'redirectLegacySikshyaReactMenus'], 0);
        add_filter('show_admin_bar', [self::class, 'hideAdminBarOnSikshyaApp']);
        add_action('admin_enqueue_scripts', [self::class, 'dequeueWordPressUiOnSikshyaApp'], 10000);
        add_action('admin_head', [self::class, 'printSikshyaReactShellHead'], 1);
        add_action('admin_head', [self::class, 'printSikshyaAdminMenuIconCss'], 2);
        add_action('admin_head', [self::class, 'hideSetupWizardSubmenuLink'], 3);

        // Remove all other admin notices on Sikshya pages
        add_action('admin_head', [$this, 'removeOtherNoticesOnSikshyaPages']);

        // Late strip: plugins often register `admin_notices` after `admin_init`.
        add_action('current_screen', [$this, 'stripAdminNoticesOnReactShell'], 0);

        // Remove WordPress notices at action level
        add_action('admin_init', [$this, 'removeWordPressNoticesOnSikshyaPages'], 1);

        // Suppress PHP notices and warnings on Sikshya pages
        add_action('admin_init', [$this, 'suppressPHPNoticesOnSikshyaPages'], 1);
    }

    /**
     * Add admin menus
     */
    public function addAdminMenus(): void
    {
        // Single top-level entry: all React areas are sub-routes (`view=`). Legacy `page=sikshya-*` URLs redirect in admin_init.
        $menu_label = (string) apply_filters('sikshya_admin_menu_label', sikshya_brand_name('admin'));
        $logo_path = SIKSHYA_PLUGIN_DIR . 'assets/images/logo-white.png';
        $default_icon = file_exists($logo_path)
            ? (SIKSHYA_PLUGIN_URL . 'assets/images/logo-white.png')
            : 'dashicons-welcome-learn-more';
        $menu_icon = (string) apply_filters('sikshya_admin_menu_icon', $default_icon);

        /**
         * Instructors get capability `sikshya_access_admin_app` via Installer role sync (not students).
         * Filter `sikshya_react_admin_menu_capability` for custom LMS teaching roles.
         */
        $react_menu_cap = (string) apply_filters(
            'sikshya_react_admin_menu_capability',
            'sikshya_access_admin_app'
        );

        add_menu_page(
            $menu_label,
            $menu_label,
            $react_menu_cap,
            AdminPages::DASHBOARD,
            [$this, 'renderSikshyaApp'],
            $menu_icon,
            5
        );

        /*
         * Setup wizard: registered under Sikshya so `page=sikshya-setup` resolves and core access
         * checks see a parent (`get_admin_page_parent()` reads `$submenu`). Do not call
         * `remove_submenu_page()` — it drops the submenu row and breaks direct wizard URLs.
         * The link is hidden via {@see hideSetupWizardSubmenuLink()}.
         */
        add_submenu_page(
            AdminPages::DASHBOARD,
            __('Sikshya Setup Wizard', 'sikshya'),
            __('Setup Wizard', 'sikshya'),
            'manage_options',
            SetupWizardController::MENU_SLUG,
            [$this->controllers['setup_wizard'], 'renderWizard']
        );
    }

    /**
     * True when the request targets the unified React admin (`admin.php?page=sikshya`).
     */
    public static function isSikshyaReactAppRequest(): bool
    {
        if (!is_admin()) {
            return false;
        }
        if (empty($_GET['page']) || !is_string($_GET['page'])) {
            return false;
        }

        return sanitize_key(wp_unslash($_GET['page'])) === AdminPages::DASHBOARD;
    }

    /**
     * True when the request targets the PHP setup wizard (`admin.php?page=sikshya-setup`).
     */
    public static function isSikshyaSetupWizardRequest(): bool
    {
        if (!is_admin()) {
            return false;
        }
        if (empty($_GET['page']) || !is_string($_GET['page'])) {
            return false;
        }

        return sanitize_key(wp_unslash($_GET['page'])) === SetupWizardController::MENU_SLUG;
    }

    /**
     * Hide WP skip links and other chrome on the Sikshya full-screen shell.
     */
    public static function printSikshyaReactShellHead(): void
    {
        if (!self::isSikshyaReactAppRequest() && !self::isSikshyaSetupWizardRequest()) {
            return;
        }
        echo '<style id="sikshya-react-wp-chrome-hide">
            body.sikshya-react-shell .screen-reader-shortcut,
            body.sikshya-react-shell #wpbody-content > .screen-reader-text,
            body.sikshya-react-shell a[href="#wpbody-content"],
            body.sikshya-react-shell a[href="#wp-toolbar"] {
                clip: rect(0, 0, 0, 0) !important;
                clip-path: inset(50%) !important;
                height: 1px !important;
                width: 1px !important;
                margin: -1px !important;
                overflow: hidden !important;
                padding: 0 !important;
                position: absolute !important;
                white-space: nowrap !important;
                border: 0 !important;
                display: none !important;
            }
            /* WordPress core / third-party admin_notices must not appear inside the React shell. */
            body.sikshya-react-shell #wpbody-content > .notice,
            body.sikshya-react-shell #wpbody-content > div.error,
            body.sikshya-react-shell #wpbody-content > .updated,
            body.sikshya-react-shell #wpbody-content > #message,
            body.sikshya-react-shell #wpbody-content > .update-nag,
            body.sikshya-react-shell #wpbody-content > .error,
            body.sikshya-react-shell #wpbody-content > p.notice {
                display: none !important;
            }
        </style>';
    }

    /**
     * Redirect old per-screen menu slugs to the unified app (`page=sikshya&view=…`).
     */
    public static function redirectLegacySikshyaReactMenus(): void
    {
        if (!is_user_logged_in() || wp_doing_ajax()) {
            return;
        }

        if (empty($_GET['page']) || !is_string($_GET['page'])) {
            return;
        }

        $page = sanitize_key(wp_unslash($_GET['page']));

        $map = [
            AdminPages::COURSES => 'courses',
            AdminPages::ADD_COURSE => 'add-course',
            AdminPages::COURSE_CATEGORIES => 'course-categories',
            AdminPages::LESSONS => 'lessons',
            AdminPages::ADD_LESSON => 'add-lesson',
            AdminPages::QUIZZES => 'quizzes',
            AdminPages::STUDENTS => 'students',
            AdminPages::INSTRUCTORS => 'instructors',
            AdminPages::REPORTS => 'reports',
            AdminPages::SETTINGS => 'settings',
        ];

        if (!isset($map[$page])) {
            return;
        }

        $params = [
            'page' => AdminPages::DASHBOARD,
            'view' => $map[$page],
        ];

        $whitelist = ['tab', 'course_id', 'id', 'action', 'post', '_wpnonce'];
        foreach ($whitelist as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }
            $val = wp_unslash($_GET[$key]);
            if (is_array($val)) {
                continue;
            }
            $params[$key] = sanitize_text_field((string) $val);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    /**
     * Hide the WP admin bar on the unified Sikshya React screen.
     *
     * @param bool $show Whether to show the admin bar.
     * @return bool
     */
    public static function hideAdminBarOnSikshyaApp($show)
    {
        return (self::isSikshyaReactAppRequest() || self::isSikshyaSetupWizardRequest()) ? false : $show;
    }

    /**
     * Strip core wp-admin styles/scripts on the Sikshya app so only the React shell shows.
     * We keep styles/scripts that the WordPress media modal depends on.
     */
    public static function dequeueWordPressUiOnSikshyaApp(): void
    {
        if (!self::isSikshyaSetupWizardRequest()) {
            $screen = get_current_screen();
            if (!$screen || $screen->id !== 'toplevel_page_sikshya') {
                return;
            }
        }

        $styles = [
            'wp-admin',
            'admin-menu',
            'common',
            'forms',
            'list-tables',
            'admin-bar',
            'colors',
            'ie',
            'site-icon',
        ];

        foreach ($styles as $handle) {
            wp_dequeue_style($handle);
        }

        $scripts = [
            'admin-bar',
            'hoverintent-js',
            'admin-widgets',
            'wp-embed',
        ];

        foreach ($scripts as $handle) {
            wp_dequeue_script($handle);
        }
    }

    /**
     * Render the unified Sikshya React admin (`view` query selects the subpage).
     */
    public function renderSikshyaApp(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $view = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : 'dashboard';

        // Centralize transactional templates under Email → Templates (hub tab).
        if ($view === 'email-templates') {
            wp_safe_redirect(admin_url('admin.php?page=sikshya&view=email-hub&tab=templates'));
            exit;
        }

        $allowed = [
            'dashboard',
            'courses',
            'add-course',
            'course-categories',
            'edit-content',
            // Tabbed hubs (sidebar entries):
            'content-library',
            'people',
            'certificates-hub',
            'sales',
            'email-hub',
            'branding',
            'integrations-hub',
            'learning-rules',
            // Standalone routes — kept whitelisted so deep links and in-app cross-links
            // (e.g. row actions, "open in builder" links) keep working even though the
            // sidebar now points at the hubs above.
            'lessons',
            'add-lesson',
            'quizzes',
            'assignments',
            'questions',
            'chapters',
            'certificates',
            'issued-certificates',
            'students',
            'instructors',
            'enrollments',
            'reports',
            'payments',
            'orders',
            'coupons',
            'gradebook',
            'grading',
            'assignment-submissions',
            'discussions',
            'activity-log',
            'content-drip',
            'subscriptions',
            'course-team',
            'marketplace',
            'bundles',
            'prerequisites',
            'social-login',
            'white-label',
            'crm-automation',
            'calendar',
            'settings',
            'tools',
            'addons',
            'integrations',
            'license',
            'email',
            'email-template-edit',
        ];

        if (!in_array($view, $allowed, true)) {
            $view = 'dashboard';
        }

        if ($view === 'tools' && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if (in_array($view, ['settings', 'email', 'email-template-edit'], true) && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if ($view === 'payments' && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if (
            $view === 'enrollments'
            && !current_user_can('manage_sikshya')
            && !current_user_can('edit_sikshya_courses')
        ) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if (
            $view === 'issued-certificates'
            && !current_user_can('manage_sikshya')
            && !current_user_can('edit_sikshya_courses')
        ) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if (
            in_array($view, ['orders', 'coupons', 'subscriptions'], true)
            && !current_user_can('manage_options')
        ) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if ($view === 'marketplace' && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if (in_array($view, ['license', 'addons', 'integrations'], true) && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        if ($view === 'course-categories' && !current_user_can('manage_categories')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $initial = [];

        if ($view === 'dashboard') {
            $initial = ReactAdminConfig::dashboardInitialData();
        } elseif ($view === 'reports') {
            $initial = ReactAdminConfig::reportsInitialData();
        }

        ReactAdminView::render($view, $initial);
    }

    /**
     * Initialize admin
     */
    public function initAdmin(): void
    {
        // Initialize admin-specific functionality
        do_action('sikshya_admin_init', $this);

        // Hook into admin_enqueue_scripts early to dequeue WordPress core styles
        add_action('admin_enqueue_scripts', [$this, 'dequeueWordPressCoreStylesEarly'], 1);

        add_filter('print_styles_array', [$this, 'filterChunkedStyles'], 10, 1);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        if (self::isSikshyaSetupWizardRequest()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $sikshya_post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];
        $is_sikshya_post_screen = isset($screen->post_type)
            && in_array($screen->post_type, $sikshya_post_types, true);
        $sid = (string) ($screen->id ?? '');
        $sbase = (string) ($screen->base ?? '');

        $is_sikshya_screen = $is_sikshya_post_screen
            || strpos($sid, 'sikshya') !== false
            || strpos($sbase, 'sikshya') !== false;

        if (!$is_sikshya_screen) {
            return;
        }

        // Enqueue Font Awesome for icons (Sikshya admin only).
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
            [],
            '6.0.0'
        );

        // React admin shell owns the Settings UI. Do not enqueue legacy settings.css/settings.js
        // on the React screen; it can cause visual conflicts and flicker on tab changes.

        // Toast (Sikshya admin only).
        wp_enqueue_style(
            'sikshya-toast',
            SIKSHYA_PLUGIN_URL . 'assets/admin/css/toast.css',
            [],
            SIKSHYA_VERSION
        );
        wp_enqueue_script(
            'sikshya-toast',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/toast.js',
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );
    }

    /**
     * Dequeue WordPress core styles that interfere with our custom design
     */
    private function dequeueWordPressCoreStyles(): void
    {
        wp_dequeue_style('forms');
        wp_dequeue_style('wp-admin');

        wp_dequeue_script('admin-bar');
        wp_dequeue_script('wp-embed');
    }



    /**
     * Filter chunked styles to remove WordPress core styles on our pages
     */
    public function filterChunkedStyles($styles): array
    {
        if (!is_array($styles)) {
            $styles = [];
        }

        $screen = get_current_screen();

        $sid = $screen ? (string) ($screen->id ?? '') : '';
        if (self::isSikshyaSetupWizardRequest() || ($screen && $sid === 'toplevel_page_sikshya')) {
            $styles = array_filter($styles, function ($style) {
                return !in_array($style, ['forms', 'list-tables'], true);
            });
        }

        return $styles;
    }


    /**
     * Dequeue WordPress core styles early in the process
     */
    public function dequeueWordPressCoreStylesEarly(): void
    {
        $screen = get_current_screen();

        if (self::isSikshyaSetupWizardRequest() || ($screen && $screen->id === 'toplevel_page_sikshya')) {
            wp_dequeue_style('list-tables');
            wp_dequeue_style('forms');
            wp_dequeue_style('wp-admin');
            wp_dequeue_style('admin-menu');
        }
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjaxRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_admin_nonce')) {
            wp_die(__('Security check failed.', 'sikshya'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'sikshya'));
        }

        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $controller = sanitize_text_field($_POST['controller'] ?? '');

        // Route to appropriate controller
        if (isset($this->controllers[$controller])) {
            $this->controllers[$controller]->handleAjax($action);
        } else {
            wp_send_json_error(__('Invalid controller.', 'sikshya'));
        }
    }

    /**
     * Handle categories AJAX requests
     */
    public function handleCategoriesAjaxRequest(): void
    {
        $this->controllers['course_categories']->handleAjaxRequest();
    }

    /**
     * Handle form submissions
     */
    private function handleFormSubmissions(): void
    {
        if (!isset($_POST['sikshya_action'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['sikshya_nonce'] ?? '', 'sikshya_admin_action')) {
            wp_die(__('Security check failed.', 'sikshya'));
        }

        $action = $_POST['sikshya_action'];
        $controller = $_POST['sikshya_controller'] ?? '';

        if (isset($this->controllers[$controller]) && method_exists($this->controllers[$controller], 'handleForm')) {
            $this->controllers[$controller]->handleForm($action);
        }
    }

    /**
     * Display admin notices
     */
    public function displayAdminNotices(): void
    {
        // Check if we're on a Sikshya admin page
        if ($this->isSikshyaAdminPage()) {
            // Don't display any notices on Sikshya pages
            // Clear notices anyway to prevent accumulation
            delete_option('sikshya_admin_notices');
            return;
        }

        // Get notices from session or options
        $notices = Settings::getRaw('sikshya_admin_notices', []);

        if (!empty($notices)) {
            foreach ($notices as $notice) {
                $class = 'notice notice-' . ($notice['type'] ?? 'info');
                $message = wp_kses_post($notice['message']);
                echo "<div class='{$class}'><p>{$message}</p></div>";
            }

            // Clear notices
            delete_option('sikshya_admin_notices');
        }
    }

    /**
     * Check if current page is a Sikshya admin page
     *
     * @return bool
     */
    private function isSikshyaAdminPage(): bool
    {
        $screen = get_current_screen();

        if (!$screen) {
            return false;
        }

        $sikshya_post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];
        $is_sikshya_post_screen = isset($screen->post_type)
            && in_array($screen->post_type, $sikshya_post_types, true);

        $sid = (string) ($screen->id ?? '');
        $sbase = (string) ($screen->base ?? '');

        return $is_sikshya_post_screen
            || strpos($sid, 'sikshya') !== false
            || strpos($sbase, 'sikshya') !== false;
    }

    /**
     * Remove WordPress notices on Sikshya pages using proper WordPress functions
     */
    public function removeOtherNoticesOnSikshyaPages(): void
    {
        if ($this->isSikshyaAdminPage()) {
            // Simple CSS to hide only known WordPress notice classes
            echo '<style>
                /* Hide only specific WordPress admin notice classes */
                .notice.is-dismissible,
                .error.is-dismissible, 
                .updated.is-dismissible,
                .update-nag,
                .settings-error {
                    display: none !important;
                }
            </style>';
        }
    }

    /**
     * Remove WordPress notices at the action level on Sikshya pages
     */
    public function removeWordPressNoticesOnSikshyaPages(): void
    {
        $is_react_shell = self::isSikshyaReactAppRequest();
        $is_setup_wizard = self::isSikshyaSetupWizardRequest();
        if (!$is_react_shell && !$is_setup_wizard && !$this->isSikshyaAdminPage()) {
            return;
        }

        // Unified React app and setup wizard: no WordPress admin notices (custom shell alerts only).
        if ($is_react_shell || $is_setup_wizard) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('network_admin_notices');
            remove_all_actions('user_admin_notices');
            delete_option('sikshya_admin_notices');

            return;
        }

        // Legacy Sikshya wp-admin screens (CPT lists, etc.).
        $our_handler = [$this, 'displayAdminNotices'];

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');

        add_action('admin_notices', $our_handler);

        remove_action('admin_notices', 'update_nag', 3);
        remove_action('network_admin_notices', 'update_nag', 3);
        remove_action('admin_notices', 'maintenance_nag', 10);

        remove_action('admin_notices', 'wp_print_file_editor_templates');

        remove_action('load-update-core.php', 'wp_update_plugins');
        remove_action('load-plugins.php', 'wp_update_plugins');
        remove_action('load-update.php', 'wp_update_plugins');

        delete_option('sikshya_admin_notices');
    }

    /**
     * Strip every `admin_notices` callback on the React shell screen (runs after most registrations).
     */
    public function stripAdminNoticesOnReactShell($screen): void
    {
        if (!$screen instanceof \WP_Screen) {
            return;
        }

        if ($screen->id !== 'toplevel_page_sikshya' && !self::isSikshyaSetupWizardRequest()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');
    }

    /**
     * Suppress PHP notices and warnings on Sikshya pages (simplified)
     */
    public function suppressPHPNoticesOnSikshyaPages(): void
    {
        if ($this->isSikshyaAdminPage()) {
            // Add a custom error handler to filter out theme-related notices
            set_error_handler([$this, 'sikshyaErrorHandler'], E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);
        }
    }

    /**
     * Custom error handler for Sikshya pages
     */
    public function sikshyaErrorHandler(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        // Only handle notices and warnings
        if (!in_array($errno, [E_NOTICE, E_WARNING, E_USER_NOTICE, E_USER_WARNING])) {
            return false; // Let WordPress handle other errors
        }

        // List of notices/warnings to suppress on Sikshya pages
        $suppress_patterns = [
            '/textdomain.*was triggered too early/i',
            '/translation loading.*domain.*triggered too early/i',
            '/_load_textdomain_just_in_time.*incorrectly/i',
            '/pragyan.*domain.*triggered too early/i',
        ];

        foreach ($suppress_patterns as $pattern) {
            if (preg_match($pattern, $errstr)) {
                // Log to our own log if needed (optional)
                error_log("Sikshya: Suppressed notice on admin page: {$errstr}");
                return true; // Suppress this error
            }
        }

        // For other notices/warnings, let WordPress handle them
        return false;
    }

    /**
     * Filter debug log contents for Sikshya pages
     */
    public function filterDebugLogForSikshyaPages(string $contents, string $file): string
    {
        if ($this->isSikshyaAdminPage()) {
            // Remove lines containing theme-related notices
            $lines = explode("\n", $contents);
            $filtered_lines = [];

            foreach ($lines as $line) {
                $should_suppress = false;

                $suppress_patterns = [
                    '/textdomain.*was triggered too early/i',
                    '/translation loading.*domain.*triggered too early/i',
                    '/_load_textdomain_just_in_time.*incorrectly/i',
                    '/pragyan.*domain.*triggered too early/i',
                ];

                foreach ($suppress_patterns as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $should_suppress = true;
                        break;
                    }
                }

                if (!$should_suppress) {
                    $filtered_lines[] = $line;
                }
            }

            return implode("\n", $filtered_lines);
        }

        return $contents;
    }

    /**
     * Restore error reporting when leaving Sikshya pages
     */
    public function restoreErrorReporting(): void
    {
        if (defined('SIKSHYA_ORIGINAL_ERROR_REPORTING')) {
            error_reporting(SIKSHYA_ORIGINAL_ERROR_REPORTING);
            restore_error_handler();
        }
    }

    /**
     * Add admin footer
     */
    public function addAdminFooter(): void
    {
        $screen = get_current_screen();

        // Restore error reporting if not on Sikshya page
        if (!$screen || !$this->isSikshyaAdminPage()) {
            $this->restoreErrorReporting();
        }

        if (!$screen) {
            return;
        }

        $sikshya_post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];
        $is_sikshya_post_screen = isset($screen->post_type)
            && in_array($screen->post_type, $sikshya_post_types, true);
        $sid = (string) ($screen->id ?? '');
        $sbase = (string) ($screen->base ?? '');

        $is_sikshya = $is_sikshya_post_screen
            || strpos($sid, 'sikshya') !== false
            || strpos($sbase, 'sikshya') !== false;

        if (!$is_sikshya) {
            return;
        }

        if (self::isSikshyaSetupWizardRequest()) {
            return;
        }

        echo '<div class="sikshya-admin-footer">';
        $links = function_exists('sikshya_brand_links') ? sikshya_brand_links() : [];
        $docs = isset($links['documentationUrl']) ? esc_url((string) $links['documentationUrl']) : 'https://docs.sikshya.com';
        $support = isset($links['supportUrl']) ? esc_url((string) $links['supportUrl']) : 'https://support.sikshya.com';
        $brand = function_exists('sikshya_brand_name') ? sikshya_brand_name('admin') : __('Sikshya LMS', 'sikshya');

        echo '<p>' . sprintf(
            /* translators: 1: brand name, 2: version, 3: docs url, 4: support url */
            __('%1$s v%2$s | <a href="%3$s" target="_blank" rel="noopener">Documentation</a> | <a href="%4$s" target="_blank" rel="noopener">Support</a>', 'sikshya'),
            esc_html($brand),
            esc_html($this->plugin->version),
            $docs,
            $support
        ) . '</p>';
        echo '</div>';
    }

    /**
     * Add body classes for Sikshya wp-admin screens (layout + shell CSS).
     *
     * @param string $classes Space-separated classes from WordPress.
     * @return string
     */
    public function filterAdminBodyClass($classes): string
    {
        $classes = is_string($classes) ? $classes : '';

        if (self::isSikshyaSetupWizardRequest()) {
            return trim($classes . ' sikshya-lms-admin sikshya-react-shell sikshya-setup-wizard-body');
        }

        $screen = get_current_screen();
        if (!$screen) {
            return $classes;
        }

        $sikshya_post_types = [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::ASSIGNMENT,
            PostTypes::QUESTION,
            PostTypes::CHAPTER,
            PostTypes::CERTIFICATE,
        ];
        $is_sikshya_post_screen = isset($screen->post_type)
            && in_array($screen->post_type, $sikshya_post_types, true);

        $sid = (string) ($screen->id ?? '');
        $sbase = (string) ($screen->base ?? '');

        if ($screen->id === 'toplevel_page_sikshya') {
            return trim($classes . ' sikshya-lms-admin sikshya-react-shell');
        }

        if (
            strpos($sid, 'sikshya') === false
            && strpos($sbase, 'sikshya') === false
            && !$is_sikshya_post_screen
        ) {
            return $classes;
        }

        return trim($classes . ' sikshya-lms-admin');
    }

    /**
     * Add admin notice
     *
     * @param string $message
     * @param string $type
     */
    public function addNotice(string $message, string $type = 'info'): void
    {
        $notices = Settings::getRaw('sikshya_admin_notices', []);
        $notices[] = [
            'message' => $message,
            'type' => $type,
        ];
        Settings::setRaw('sikshya_admin_notices', $notices);
    }

    /**
     * Get plugin instance
     */
    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }

    /**
     * Get controller
     *
     * @param string $name
     * @return mixed|null
     */
    public function getController(string $name)
    {
        return $this->controllers[$name] ?? null;
    }

    /**
     * Get all controllers
     *
     * @return array
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        $this->renderSikshyaApp();
    }

    /**
     * Render courses page
     */
    public function renderCoursesPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['course']->renderCoursesPage();
    }

    /**
     * Render add course page
     */
    public function renderAddCoursePage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        try {
            $this->controllers['course']->renderAddCoursePage();
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sikshya: Error rendering Add Course Page: ' . $e->getMessage());
            }
            wp_die(esc_html($e->getMessage()));
        }
    }

    /**
     * Render course categories page
     */
    public function renderCourseCategoriesPage(): void
    {
        if (!current_user_can('manage_categories')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['course_categories']->renderCourseCategoriesPage();
    }

    /**
     * Render lessons page
     */
    public function renderLessonsPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['lesson']->renderLessonsPage();
    }

    /**
     * Render add lesson page
     */
    public function renderAddLessonPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['lesson']->renderAddLessonPage();
    }

    /**
     * Render quizzes page
     */
    public function renderQuizzesPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['quiz']->renderQuizzesPage();
    }

    /**
     * Render students page
     */
    public function renderStudentsPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['student']->renderStudentsPage();
    }

    /**
     * Render instructors page
     */
    public function renderInstructorsPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['instructor']->renderInstructorsPage();
    }

    /**
     * Render reports page
     */
    public function renderReportsPage(): void
    {
        if (!AdminBackendAccess::canAccessStaffBackend()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['report']->renderReportsPage();
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['setting']->renderSettingsPage();
    }

    /**
     * Render stats widget
     */
    private function renderStatsWidget(): string
    {
        $course_counts = wp_count_posts(PostTypes::COURSE);
        $total_courses = isset($course_counts->publish) ? (int) $course_counts->publish : 0;

        $user_counts = count_users();
        $total_students = isset($user_counts['avail_roles']['sikshya_student'])
            ? (int) $user_counts['avail_roles']['sikshya_student']
            : 0;

        $stats = [
            'total_courses' => $total_courses,
            'total_students' => $total_students,
            'total_revenue' => '$0.00',
            'active_enrollments' => 0,
        ];

        ob_start();
        ?>
        <div class="sikshya-stat-card">
            <div class="sikshya-stat-header">
                <div class="sikshya-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
            </div>
            <div class="sikshya-stat-number"><?php echo esc_html((string) $stats['total_courses']); ?></div>
            <div class="sikshya-stat-label"><?php esc_html_e('Published courses', 'sikshya'); ?></div>
            <div class="sikshya-stat-change neutral">
                <span class="sikshya-stat-note"><?php esc_html_e('Live count', 'sikshya'); ?></span>
            </div>
        </div>
        
        <div class="sikshya-stat-card">
            <div class="sikshya-stat-header">
                <div class="sikshya-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                </div>
            </div>
            <div class="sikshya-stat-number"><?php echo esc_html((string) $stats['total_students']); ?></div>
            <div class="sikshya-stat-label"><?php esc_html_e('Students (Sikshya role)', 'sikshya'); ?></div>
            <div class="sikshya-stat-change neutral">
                <span class="sikshya-stat-note"><?php esc_html_e('Live count', 'sikshya'); ?></span>
            </div>
        </div>
        
        <div class="sikshya-stat-card">
            <div class="sikshya-stat-header">
                <div class="sikshya-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                </div>
            </div>
            <div class="sikshya-stat-number"><?php echo esc_html($stats['total_revenue']); ?></div>
            <div class="sikshya-stat-label"><?php esc_html_e('Total revenue', 'sikshya'); ?></div>
            <div class="sikshya-stat-change neutral">
                <span class="sikshya-stat-note"><?php esc_html_e('Connect payments in Settings', 'sikshya'); ?></span>
            </div>
        </div>
        
        <div class="sikshya-stat-card">
            <div class="sikshya-stat-header">
                <div class="sikshya-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="sikshya-stat-number"><?php echo esc_html((string) $stats['active_enrollments']); ?></div>
            <div class="sikshya-stat-label"><?php esc_html_e('Active enrollments', 'sikshya'); ?></div>
            <div class="sikshya-stat-change neutral">
                <span class="sikshya-stat-note"><?php esc_html_e('Reports → Enrollments when available', 'sikshya'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render recent courses widget
     */
    private function renderRecentCoursesWidget(): string
    {
        $recent_courses = get_posts([
            'post_type' => PostTypes::COURSE,
            'posts_per_page' => 5,
            'post_status' => 'publish',
        ]);

        ob_start();
        if (!empty($recent_courses)) {
            echo '<ul class="sikshya-list">';
            foreach ($recent_courses as $course) {
                $author = get_userdata($course->post_author);
                $author_initials = substr($author->display_name, 0, 2);
                $course_date = get_the_date('M j, Y', $course->ID);

                echo '<li class="sikshya-list-item">';
                echo '<div class="sikshya-list-item-content">';
                echo '<div class="sikshya-list-item-avatar">' . esc_html($author_initials) . '</div>';
                echo '<div class="sikshya-list-item-text">';
                echo '<div class="sikshya-list-item-title">';
                echo '<a href="' . get_edit_post_link($course->ID) . '" class="sikshya-link">';
                echo esc_html($course->post_title);
                echo '</a>';
                echo '</div>';
                echo '<div class="sikshya-list-item-subtitle">'
                    . esc_html(sprintf(
                        /* translators: %s: author display name */
                        __('By %s', 'sikshya'),
                        $author ? $author->display_name : ''
                    ))
                    . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="sikshya-list-item-meta">';
                echo '<span class="sikshya-list-item-badge success">' . esc_html__('Published', 'sikshya') . '</span>';
                echo '<span>' . esc_html($course_date) . '</span>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="sikshya-empty-state">';
            echo '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>';
            echo '</svg>';
            echo '<p>' . esc_html__('No courses found. Create your first course to get started.', 'sikshya') . '</p>';
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Render quick actions widget
     */
    private function renderQuickActionsWidget(): string
    {
        ob_start();
        ?>
        <div class="sikshya-quick-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminPages::ADD_COURSE)); ?>" class="sikshya-btn sikshya-btn-primary sikshya-mb-2">
                <?php esc_html_e('Add course', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminPages::ADD_LESSON)); ?>" class="sikshya-btn sikshya-btn-secondary sikshya-mb-2">
                <?php esc_html_e('Add lesson', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . rawurlencode(PostTypes::QUIZ))); ?>" class="sikshya-btn sikshya-btn-secondary sikshya-mb-2">
                <?php esc_html_e('Add quiz', 'sikshya'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
