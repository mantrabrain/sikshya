<?php

namespace Sikshya\Frontend;
use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Public\CartFormHandler;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Frontend\Controllers\CourseController;
use Sikshya\Frontend\Controllers\LessonController;
use Sikshya\Frontend\Controllers\QuizController;
use Sikshya\Frontend\Controllers\UserController;
use Sikshya\Frontend\Controllers\EnrollmentController;
use Sikshya\Frontend\Controllers\ProgressController;
use Sikshya\Frontend\Controllers\CertificateController;
use Sikshya\Frontend\Controllers\DiscussionController;
use Sikshya\Frontend\Controllers\AssignmentController;

/**
 * Frontend Management Class
 *
 * @package Sikshya\Frontend
 */
class Frontend
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
        $this->plugin = $plugin;
        $this->initControllers();
        $this->initHooks();
    }

    /**
     * Initialize controllers
     */
    private function initControllers(): void
    {
        $this->controllers['course'] = new CourseController($this->plugin);
        $this->controllers['lesson'] = new LessonController($this->plugin);
        $this->controllers['quiz'] = new QuizController($this->plugin);
        $this->controllers['user'] = new UserController($this->plugin);
        $this->controllers['enrollment'] = new EnrollmentController($this->plugin);
        $this->controllers['progress'] = new ProgressController($this->plugin);
        $this->controllers['certificate'] = new CertificateController($this->plugin);
        $this->controllers['discussion'] = new DiscussionController($this->plugin);
        $this->controllers['assignment'] = new AssignmentController($this->plugin);
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('wp', [$this, 'maybeConfigureAccountShellPage']);
        add_action('wp_head', [$this, 'addFrontendMeta']);
        add_action('wp_footer', [$this, 'addFrontendFooter']);
        // Run on init (before template output) so redirects/cookies are not broken by theme notices.
        add_action('init', [CartFormHandler::class, 'maybeHandle'], 20);
        add_action('template_redirect', [$this, 'handleTemplateRedirect']);
        add_action('wp', [$this, 'initFrontend']);
        add_filter('template_include', [$this, 'loadCustomTemplates'], 99);
        add_action('wp_body_open', [$this, 'addBodyClasses']);
    }

    /**
     * Initialize frontend
     */
    public function initFrontend(): void
    {
        // Initialize frontend-specific functionality
        do_action('sikshya_frontend_init', $this);
    }

    /**
     * Standalone account shell: no shared Sikshya frontend CSS/JS (template loads account-shell.css only).
     */
    public function maybeConfigureAccountShellPage(): void
    {
        if (!PublicPageUrls::isCurrentVirtualPage('account')) {
            return;
        }

        add_filter('show_admin_bar', '__return_false');
        add_action('wp_enqueue_scripts', [$this, 'dequeueAllAssetsForAccountShell'], PHP_INT_MAX - 1);
    }

    /**
     * Remove theme and third-party enqueues on the standalone account page (template does not call wp_head).
     */
    public function dequeueAllAssetsForAccountShell(): void
    {
        if (!PublicPageUrls::isCurrentVirtualPage('account')) {
            return;
        }

        global $wp_styles, $wp_scripts;

        if ($wp_styles instanceof \WP_Styles) {
            foreach ((array) $wp_styles->queue as $handle) {
                wp_dequeue_style($handle);
            }
        }

        if ($wp_scripts instanceof \WP_Scripts) {
            foreach ((array) $wp_scripts->queue as $handle) {
                wp_dequeue_script($handle);
            }
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        if (PublicPageUrls::isCurrentVirtualPage('account')) {
            return;
        }

        wp_enqueue_style(
            'sikshya-public-ds',
            $this->plugin->getAssetUrl('css/public-design-system.css'),
            [],
            $this->plugin->version
        );

        wp_enqueue_style(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('css/frontend.css'),
            ['sikshya-public-ds'],
            $this->plugin->version
        );

        if (is_singular(PostTypes::COURSE)) {
            wp_enqueue_style(
                'sikshya-single-course',
                $this->plugin->getAssetUrl('css/single-course.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $this->plugin->version
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('cart')) {
            wp_enqueue_style(
                'sikshya-cart',
                $this->plugin->getAssetUrl('css/cart.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $this->plugin->version
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('checkout')) {
            wp_enqueue_style(
                'sikshya-checkout',
                $this->plugin->getAssetUrl('css/checkout.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $this->plugin->version
            );
        }

        if (PublicPageUrls::isCurrentVirtualPage('order')) {
            wp_enqueue_style(
                'sikshya-order',
                $this->plugin->getAssetUrl('css/order.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $this->plugin->version
            );
        }

        // Enqueue frontend scripts
        wp_enqueue_script(
            'sikshya-frontend',
            $this->plugin->getAssetUrl('js/frontend.js'),
            ['jquery'],
            $this->plugin->version,
            true
        );

        // Localize script
        wp_localize_script('sikshya-frontend', 'sikshyaFrontend', [
            'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => [
                'confirmEnroll' => __('Are you sure you want to enroll in this course?', 'sikshya'),
                'confirmUnenroll' => __('Are you sure you want to unenroll from this course?', 'sikshya'),
                'confirmSubmit' => __('Are you sure you want to submit this quiz?', 'sikshya'),
                'saving' => __('Saving...', 'sikshya'),
                'saved' => __('Saved successfully!', 'sikshya'),
                'error' => __('An error occurred. Please try again.', 'sikshya'),
                'loading' => __('Loading...', 'sikshya'),
                'noResults' => __('No results found.', 'sikshya'),
            ],
        ]);

        // Enqueue specific page assets
        $this->enqueuePageSpecificAssets();
    }

    /**
     * Enqueue page-specific assets
     */
    private function enqueuePageSpecificAssets(): void
    {
        if (is_singular(PostTypes::COURSE)) {
            wp_enqueue_script('sikshya-course-viewer');
            wp_enqueue_style('sikshya-course-viewer');
        }

        if (is_singular(PostTypes::LESSON)) {
            wp_enqueue_script('sikshya-lesson-viewer');
            wp_enqueue_style('sikshya-lesson-viewer');
        }

        if (is_page('sikshya-dashboard')) {
            wp_enqueue_script('sikshya-dashboard');
            wp_enqueue_style('sikshya-dashboard');
        }

        if (is_page('sikshya-courses')) {
            wp_enqueue_script('sikshya-course-catalog');
            wp_enqueue_style('sikshya-course-catalog');
        }

        if (PublicPageUrls::isCurrentVirtualPage('checkout')) {
            wp_enqueue_script(
                'sikshya-checkout-page',
                $this->plugin->getAssetUrl('js/checkout-page.js'),
                ['sikshya-frontend'],
                $this->plugin->version,
                true
            );
            wp_localize_script(
                'sikshya-checkout-page',
                'sikshyaCheckout',
                [
                    'i18n' => [
                        'noCourses' => __('No courses in checkout.', 'sikshya'),
                        'startingCheckout' => __('Starting checkout…', 'sikshya'),
                        'checkoutFailed' => __('Checkout failed.', 'sikshya'),
                        'quoteFailed' => __('Could not update totals.', 'sikshya'),
                        'networkError' => __('Network error. Please try again.', 'sikshya'),
                        'updatingTotals' => __('Updating totals…', 'sikshya'),
                        'stripeSessionReady' => __(
                            'Payment session ready. Your site should confirm the Stripe PaymentIntent on return (see Sikshya checkout docs).',
                            'sikshya'
                        ),
                        'checkoutStarted' => __('Checkout started.', 'sikshya'),
                    ],
                ]
            );
        }

        if (is_singular(PostTypes::QUIZ)) {
            wp_enqueue_script(
                'sikshya-quiz-taker',
                $this->plugin->getAssetUrl('js/quiz-taker.js'),
                [],
                $this->plugin->version,
                true
            );
            wp_localize_script(
                'sikshya-quiz-taker',
                'sikshyaQuizTaker',
                [
                    'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
                    'restNonce' => wp_create_nonce('wp_rest'),
                    'quizId' => (string) get_queried_object_id(),
                    'i18n' => [
                        'score' => __('Your score: %s%%', 'sikshya'),
                        'passed' => __('You passed this quiz.', 'sikshya'),
                        'notPassed' => __('You did not reach the passing score.', 'sikshya'),
                        'error' => __('Could not submit the quiz. Please try again.', 'sikshya'),
                    ],
                ]
            );
        }
    }

    /**
     * Add frontend meta tags
     */
    public function addFrontendMeta(): void
    {
        if (is_singular(PostTypes::COURSE)) {
            $course_id = get_the_ID();
            $course = get_post($course_id);

            if ($course) {
                echo '<meta property="og:title" content="' . esc_attr($course->post_title) . '" />';
                echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags($course->post_excerpt)) . '" />';
                echo '<meta property="og:type" content="course" />';
                echo '<meta property="og:url" content="' . esc_url(get_permalink($course_id)) . '" />';

                if (has_post_thumbnail($course_id)) {
                    $thumbnail_url = get_the_post_thumbnail_url($course_id, 'large');
                    echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '" />';
                }
            }
        }
    }

    /**
     * Add frontend footer
     */
    public function addFrontendFooter(): void
    {
        // Add any frontend-specific footer content
        do_action('sikshya_frontend_footer', $this);
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjaxRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_frontend_nonce')) {
            wp_die(__('Security check failed.', 'sikshya'));
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
     * Handle template redirect
     */
    public function handleTemplateRedirect(): void
    {
        if (PublicPageUrls::isCurrentVirtualPage('order')) {
            $legacy_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
            $has_key = isset($_GET['order_key']) && OrderRepository::sanitizePublicToken(
                sanitize_text_field(wp_unslash((string) $_GET['order_key']))
            ) !== '';
            if ($legacy_id > 0 && !$has_key && is_user_logged_in()) {
                $repo = new OrderRepository();
                $row = $repo->findByIdForUser($legacy_id, get_current_user_id());
                if ($row) {
                    $tok = $repo->ensurePublicToken((int) $row->id);
                    if ($tok !== '') {
                        wp_safe_redirect(PublicPageUrls::orderView($tok), 301);
                        exit;
                    }
                }
            }
        }

        // Handle custom page templates
        if (is_page('sikshya-dashboard')) {
            $this->controllers['user']->dashboard();
            exit;
        }

        if (is_page('sikshya-courses')) {
            // CourseController implements index() for the catalog view.
            $this->controllers['course']->index();
            exit;
        }

        if (is_page('sikshya-enroll')) {
            $this->controllers['enrollment']->enroll();
            exit;
        }

        if (is_page('sikshya-certificates')) {
            $this->controllers['certificate']->certificates();
            exit;
        }
    }

    /**
     * Load custom templates
     */
    public function loadCustomTemplates(string $template): string
    {
        // Load custom templates for Sikshya post types
        if (is_singular(PostTypes::COURSE)) {
            $custom_template = $this->plugin->getTemplatePath('single-course.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular(PostTypes::LESSON)) {
            $custom_template = $this->plugin->getTemplatePath('single-lesson.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular(PostTypes::QUIZ)) {
            $custom_template = $this->plugin->getTemplatePath('single-quiz.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_post_type_archive(PostTypes::COURSE)) {
            $custom_template = $this->plugin->getTemplatePath('archive-sik_course.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_tax(Taxonomies::COURSE_CATEGORY)) {
            $custom_template = $this->plugin->getTemplatePath('taxonomy-course-category.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_tax(Taxonomies::COURSE_TAG)) {
            $custom_template = $this->plugin->getTemplatePath('taxonomy-course-tag.php');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        foreach (
            [
                'cart' => 'cart.php',
                'checkout' => 'checkout.php',
                'order' => 'order.php',
                'account' => 'account.php',
                'learn' => 'learn.php',
            ] as $key => $file
        ) {
            if (PublicPageUrls::isCurrentVirtualPage($key)) {
                $path = $this->plugin->getTemplatePath($file);
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return $template;
    }

    /**
     * Add body classes
     */
    public function addBodyClasses(): void
    {
        $classes = [];

        if (is_singular(PostTypes::COURSE)) {
            $classes[] = 'sikshya-course-page';
        }

        if (is_singular(PostTypes::LESSON)) {
            $classes[] = 'sikshya-lesson-page';
        }

        if (is_singular(PostTypes::QUIZ)) {
            $classes[] = 'sikshya-quiz-page';
        }

        if (is_page('sikshya-dashboard')) {
            $classes[] = 'sikshya-dashboard-page';
        }

        if (is_page('sikshya-courses')) {
            $classes[] = 'sikshya-catalog-page';
        }

        if (is_post_type_archive(PostTypes::COURSE)) {
            $classes[] = 'sikshya-course-archive';
        }

        foreach (['cart' => 'sikshya-cart-page', 'checkout' => 'sikshya-checkout-page', 'order' => 'sikshya-order-page', 'account' => 'sikshya-account-page', 'learn' => 'sikshya-learn-page'] as $k => $class) {
            if (PublicPageUrls::isCurrentVirtualPage($k)) {
                $classes[] = $class;
            }
        }

        if (!empty($classes)) {
            echo '<script>document.body.className += " ' . esc_attr(implode(' ', $classes)) . '";</script>';
        }
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
     */
    public function getController(string $name)
    {
        return $this->controllers[$name] ?? null;
    }

    /**
     * Add frontend notice
     */
    public function addNotice(string $message, string $type = 'info'): void
    {
        $notices = get_option('sikshya_frontend_notices', []);
        $notices[] = [
            'message' => $message,
            'type' => $type,
        ];
        update_option('sikshya_frontend_notices', $notices);
    }

    /**
     * Display frontend notices
     */
    public function displayNotices(): void
    {
        $notices = get_option('sikshya_frontend_notices', []);

        if (!empty($notices)) {
            foreach ($notices as $notice) {
                $class = 'sikshya-notice sikshya-notice-' . ($notice['type'] ?? 'info');
                $message = wp_kses_post($notice['message']);
                echo "<div class='{$class}'><p>{$message}</p></div>";
            }

            // Clear notices
            delete_option('sikshya_frontend_notices');
        }
    }

    /**
     * Check if user is enrolled in course
     */
    public function isUserEnrolled(int $course_id, int $user_id = 0): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return $this->controllers['enrollment']->isEnrolled($course_id, $user_id);
    }

    /**
     * Get user progress for course
     */
    public function getUserProgress(int $course_id, int $user_id = 0): array
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        return $this->controllers['progress']->getCourseProgress($course_id, $user_id);
    }

    /**
     * Get course completion percentage
     */
    public function getCourseCompletionPercentage(int $course_id, int $user_id = 0): int
    {
        $progress = $this->getUserProgress($course_id, $user_id);
        return $progress['percentage'] ?? 0;
    }

    /**
     * Check if course is completed
     */
    public function isCourseCompleted(int $course_id, int $user_id = 0): bool
    {
        $progress = $this->getUserProgress($course_id, $user_id);
        return $progress['completed'] ?? false;
    }
}
