<?php

namespace Sikshya\Admin;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Controllers\DashboardController;
use Sikshya\Admin\Controllers\CourseController;
use Sikshya\Admin\Controllers\LessonController;
use Sikshya\Admin\Controllers\QuizController;
use Sikshya\Admin\Controllers\UserController;
use Sikshya\Admin\Controllers\ReportController;
use Sikshya\Admin\Controllers\SettingController;

/**
 * Admin Management Class
 *
 * @package Sikshya\Admin
 */
class Admin
{
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
        error_log('Sikshya: Admin constructor called');
        $this->plugin = $plugin;
        $this->initControllers();
        $this->initHooks();
        error_log('Sikshya: Admin constructor completed');
    }

    /**
     * Initialize controllers
     */
    private function initControllers(): void
    {
        error_log('Sikshya: Admin initControllers called');
        $this->controllers['course'] = new \Sikshya\Admin\Controllers\CourseController($this->plugin);
        $this->controllers['lesson'] = new \Sikshya\Admin\Controllers\LessonController($this->plugin);
        $this->controllers['quiz'] = new \Sikshya\Admin\Controllers\QuizController($this->plugin);
        $this->controllers['student'] = new \Sikshya\Admin\Controllers\StudentController($this->plugin);
        $this->controllers['instructor'] = new \Sikshya\Admin\Controllers\InstructorController($this->plugin);
        $this->controllers['report'] = new \Sikshya\Admin\Controllers\ReportController($this->plugin);
        $this->controllers['setting'] = new \Sikshya\Admin\Controllers\SettingController($this->plugin);
        error_log('Sikshya: Admin controllers initialized');
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenus']);
        add_action('admin_init', [$this, 'initAdmin']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_sikshya_admin_action', [$this, 'handleAjaxRequest']);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        add_action('admin_footer', [$this, 'addAdminFooter']);
        
        // Remove all other admin notices on Sikshya pages
        add_action('admin_head', [$this, 'removeOtherNoticesOnSikshyaPages']);
        
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
        // Main menu
        add_menu_page(
            __('Sikshya LMS', 'sikshya'),
            __('Sikshya LMS', 'sikshya'),
            'edit_posts',
            'sikshya',
            [$this, 'renderDashboardPage'],
            'dashicons-welcome-learn-more',
            5
        );

        // Dashboard submenu
        add_submenu_page(
            'sikshya',
            __('Dashboard', 'sikshya'),
            __('Dashboard', 'sikshya'),
            'edit_posts',
            'sikshya',
            [$this, 'renderDashboardPage']
        );

        // Courses submenu
        add_submenu_page(
            'sikshya',
            __('Courses', 'sikshya'),
            __('Courses', 'sikshya'),
            'edit_posts',
            'sikshya-courses',
            [$this, 'renderCoursesPage']
        );

        // Add Course submenu
        add_submenu_page(
            'sikshya',
            __('Add Course', 'sikshya'),
            __('Add Course', 'sikshya'),
            'edit_posts',
            'sikshya-add-course',
            [$this, 'renderAddCoursePage']
        );

        // Lessons submenu
        add_submenu_page(
            'sikshya',
            __('Lessons', 'sikshya'),
            __('Lessons', 'sikshya'),
            'edit_posts',
            'sikshya-lessons',
            [$this, 'renderLessonsPage']
        );

        // Quizzes submenu
        add_submenu_page(
            'sikshya',
            __('Quizzes', 'sikshya'),
            __('Quizzes', 'sikshya'),
            'edit_posts',
            'sikshya-quizzes',
            [$this, 'renderQuizzesPage']
        );

        // Students submenu
        add_submenu_page(
            'sikshya',
            __('Students', 'sikshya'),
            __('Students', 'sikshya'),
            'edit_posts',
            'sikshya-students',
            [$this, 'renderStudentsPage']
        );

        // Instructors submenu
        add_submenu_page(
            'sikshya',
            __('Instructors', 'sikshya'),
            __('Instructors', 'sikshya'),
            'edit_posts',
            'sikshya-instructors',
            [$this, 'renderInstructorsPage']
        );

        // Reports submenu
        add_submenu_page(
            'sikshya',
            __('Reports', 'sikshya'),
            __('Reports', 'sikshya'),
            'edit_posts',
            'sikshya-reports',
            [$this, 'renderReportsPage']
        );

        // Settings submenu
        add_submenu_page(
            'sikshya',
            __('Settings', 'sikshya'),
            __('Settings', 'sikshya'),
            'manage_options',
            'sikshya-settings',
            [$this, 'renderSettingsPage']
        );

        // Tools submenu
        add_submenu_page(
            'sikshya',
            __('Tools', 'sikshya'),
            __('Tools', 'sikshya'),
            'manage_options',
            'sikshya-tools',
            [$this->controllers['setting'], 'tools']
        );

        // Help submenu
        add_submenu_page(
            'sikshya',
            __('Help & Support', 'sikshya'),
            __('Help & Support', 'sikshya'),
            'manage_options',
            'sikshya-help',
            [$this->controllers['setting'], 'help']
        );
    }

    /**
     * Initialize admin
     */
    public function initAdmin(): void
    {
        // Initialize admin-specific functionality
        do_action('sikshya_admin_init', $this);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        
        // Debug: Log screen ID for troubleshooting
        if ($screen) {
            error_log('Sikshya Admin Screen ID: ' . $screen->id);
            error_log('Sikshya Admin Screen Base: ' . $screen->base);
            error_log('Sikshya Admin Screen Parent: ' . $screen->parent_base);
            error_log('Sikshya Admin Screen Hook: ' . $screen->parent_file);
        } else {
            error_log('Sikshya Admin: No screen object available');
        }
        
        // Enqueue Font Awesome for icons
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
            [],
            '6.0.0'
        );

        // Enqueue admin styles
        wp_enqueue_style(
            'sikshya-admin',
            SIKSHYA_PLUGIN_URL . 'assets/admin/css/admin.css',
            [],
            SIKSHYA_VERSION
        );

        // Enqueue jQuery first
        wp_enqueue_script('jquery');

        // Enqueue admin scripts
        wp_enqueue_script(
            'sikshya-admin',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/admin.js',
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script(
            'sikshya-admin',
            'sikshya_ajax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('sikshya_course_builder'),
            ]
        );

        // Enqueue course builder assets on specific pages
        if ($screen && in_array($screen->id, ['sikshya_page_sikshya-add-course'])) {
            $this->controllers['course']->enqueueCourseBuilderAssets();
        }

        // Enqueue settings assets only on settings page
        if ($screen && $screen->id === 'sikshya-lms_page_sikshya-settings') {
            error_log('Sikshya: Enqueuing settings assets for screen: ' . $screen->id);
            wp_enqueue_style(
                'sikshya-settings',
                SIKSHYA_PLUGIN_URL . 'assets/admin/css/settings.css',
                [],
                SIKSHYA_VERSION
            );
            wp_enqueue_script(
                'sikshya-settings',
                SIKSHYA_PLUGIN_URL . 'assets/admin/js/settings.js',
                ['jquery'],
                SIKSHYA_VERSION,
                true
            );
            
            // Enqueue modal system for settings page
            wp_enqueue_style(
                'sikshya-modal',
                SIKSHYA_PLUGIN_URL . 'assets/admin/css/modal.css',
                [],
                SIKSHYA_VERSION
            );
            wp_enqueue_script(
                'sikshya-modal',
                SIKSHYA_PLUGIN_URL . 'assets/admin/js/modal.js',
                ['jquery'],
                SIKSHYA_VERSION,
                true
            );
            
            error_log('Sikshya: Settings assets enqueued successfully');
        } else {
            error_log('Sikshya: Not enqueuing settings assets. Screen ID: ' . ($screen ? $screen->id : 'null'));
            error_log('Sikshya: Expected screen ID: sikshya_page_sikshya-settings');
        }
        
        // Enqueue toast system assets on all admin pages
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
     * Enqueue page-specific assets
     */
    private function enqueuePageSpecificAssets($screen): void
    {
        switch ($screen->id) {
            case 'sikshya_page_sikshya-courses':
                wp_enqueue_script('sikshya-course-manager');
                wp_enqueue_style('sikshya-course-manager');
                break;
            case 'sikshya_page_sikshya-lessons':
                wp_enqueue_script('sikshya-lesson-manager');
                wp_enqueue_style('sikshya-lesson-manager');
                break;
            case 'sikshya_page_sikshya-quizzes':
                wp_enqueue_script('sikshya-quiz-manager');
                wp_enqueue_style('sikshya-quiz-manager');
                break;
            case 'sikshya_page_sikshya-reports':
                wp_enqueue_script('sikshya-charts');
                wp_enqueue_script('sikshya-reports');
                wp_enqueue_style('sikshya-reports');
                break;
            case 'sikshya_page_sikshya-settings':
                wp_enqueue_script('sikshya-settings');
                wp_enqueue_style('sikshya-settings');
                break;
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
        $notices = get_option('sikshya_admin_notices', []);

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

        // List of Sikshya admin page IDs
        $sikshya_pages = [
            'toplevel_page_sikshya',
            'sikshya_page_sikshya-courses',
            'sikshya_page_sikshya-add-course', 
            'sikshya_page_sikshya-lessons',
            'sikshya_page_sikshya-quizzes',
            'sikshya_page_sikshya-questions',
            'sikshya_page_sikshya-enrollments',
            'sikshya_page_sikshya-reports',
            'sikshya_page_sikshya-settings',
            'sikshya_page_sikshya-tools',
            'sikshya_page_sikshya-addons'
        ];

        return in_array($screen->id, $sikshya_pages, true);
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
        if ($this->isSikshyaAdminPage()) {
            // Store our handler before removing all actions
            $our_handler = [$this, 'displayAdminNotices'];
            
            // Remove all admin notice actions
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('network_admin_notices');
            remove_all_actions('user_admin_notices');
            
            // Re-add only our Sikshya notice handler
            add_action('admin_notices', $our_handler);
            
            // Remove specific WordPress update notices
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('network_admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag', 10);
            
            // Remove file editing warnings
            remove_action('admin_notices', 'wp_print_file_editor_templates');
            
            // Remove plugin update nags
            remove_action('load-update-core.php', 'wp_update_plugins');
            remove_action('load-plugins.php', 'wp_update_plugins');
            remove_action('load-update.php', 'wp_update_plugins');
            
            // Clear any existing notices in the options
            delete_option('sikshya_admin_notices');
        }
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
        
        if (!$screen || strpos($screen->id, 'sikshya') === false) {
            return;
        }

        echo '<div class="sikshya-admin-footer">';
        echo '<p>' . sprintf(
            __('Sikshya LMS v%s | <a href="%s" target="_blank">Documentation</a> | <a href="%s" target="_blank">Support</a>', 'sikshya'),
            $this->plugin->version,
            'https://docs.sikshya.com',
            'https://support.sikshya.com'
        ) . '</p>';
        echo '</div>';
    }

    /**
     * Add admin notice
     *
     * @param string $message
     * @param string $type
     */
    public function addNotice(string $message, string $type = 'info'): void
    {
        $notices = get_option('sikshya_admin_notices', []);
        $notices[] = [
            'message' => $message,
            'type' => $type,
        ];
        update_option('sikshya_admin_notices', $notices);
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
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $dashboard = new \Sikshya\Admin\Views\Dashboard($this->plugin, [
            'title' => __('Sikshya LMS Dashboard', 'sikshya'),
            'description' => __('Welcome to your learning management system', 'sikshya'),
        ]);

        // Add dashboard widgets
        $dashboard->addWidgets([
            'stats' => [
                'title' => __('Statistics', 'sikshya'),
                'content' => $this->renderStatsWidget(),
                'type' => 'stats',
            ],
            'recent_courses' => [
                'title' => __('Recent Courses', 'sikshya'),
                'content' => $this->renderRecentCoursesWidget(),
                'type' => 'list',
            ],
            'quick_actions' => [
                'title' => __('Quick Actions', 'sikshya'),
                'content' => $this->renderQuickActionsWidget(),
                'type' => 'actions',
            ],
        ]);

        echo $dashboard->renderDashboard();
    }

    /**
     * Render courses page
     */
    public function renderCoursesPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['course']->renderCoursesPage();
    }

    /**
     * Render add course page
     */
    public function renderAddCoursePage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sikshya: Rendering Add Course Page');
            error_log('Sikshya: Current user ID: ' . get_current_user_id());
            error_log('Sikshya: Current user capabilities: ' . print_r(wp_get_current_user()->allcaps, true));
        }

        try {
            $this->controllers['course']->renderAddCoursePage();
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sikshya: Error rendering Add Course Page: ' . $e->getMessage());
            }
            wp_die('Error rendering Add Course Page: ' . $e->getMessage());
        }
    }

    /**
     * Render lessons page
     */
    public function renderLessonsPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['lesson']->renderLessonsPage();
    }

    /**
     * Render quizzes page
     */
    public function renderQuizzesPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['quiz']->renderQuizzesPage();
    }

    /**
     * Render students page
     */
    public function renderStudentsPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['student']->renderStudentsPage();
    }

    /**
     * Render instructors page
     */
    public function renderInstructorsPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $this->controllers['instructor']->renderInstructorsPage();
    }

    /**
     * Render reports page
     */
    public function renderReportsPage(): void
    {
        if (!current_user_can('edit_posts')) {
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
        $stats = [
            'total_courses' => wp_count_posts('sikshya_course')->publish,
            'total_students' => count_users()['total_users'],
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
            <div class="sikshya-stat-number"><?php echo esc_html($stats['total_courses']); ?></div>
            <div class="sikshya-stat-label"><?php _e('Total Courses', 'sikshya'); ?></div>
            <div class="sikshya-stat-change positive">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 14l3-3m0 0l3 3m-3-3v9"/>
                </svg>
                +12% from last month
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
            <div class="sikshya-stat-number"><?php echo esc_html($stats['total_students']); ?></div>
            <div class="sikshya-stat-label"><?php _e('Total Students', 'sikshya'); ?></div>
            <div class="sikshya-stat-change positive">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 14l3-3m0 0l3 3m-3-3v9"/>
                </svg>
                +8% from last month
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
            <div class="sikshya-stat-label"><?php _e('Total Revenue', 'sikshya'); ?></div>
            <div class="sikshya-stat-change positive">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 14l3-3m0 0l3 3m-3-3v9"/>
                </svg>
                +15% from last month
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
            <div class="sikshya-stat-number"><?php echo esc_html($stats['active_enrollments']); ?></div>
            <div class="sikshya-stat-label"><?php _e('Active Enrollments', 'sikshya'); ?></div>
            <div class="sikshya-stat-change neutral">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
                No change
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
            'post_type' => 'sikshya_course',
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
                echo '<div class="sikshya-list-item-subtitle">By ' . esc_html($author->display_name) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="sikshya-list-item-meta">';
                echo '<span class="sikshya-list-item-badge success">Published</span>';
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
            echo '<p>' . __('No courses found. Create your first course to get started.', 'sikshya') . '</p>';
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
            <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary sikshya-mb-2">
                <?php _e('Add Course', 'sikshya'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=sikshya-lessons'); ?>" class="sikshya-btn sikshya-btn-secondary sikshya-mb-2">
                <?php _e('Add Lesson', 'sikshya'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=sikshya-quizzes'); ?>" class="sikshya-btn sikshya-btn-secondary sikshya-mb-2">
                <?php _e('Add Quiz', 'sikshya'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
} 