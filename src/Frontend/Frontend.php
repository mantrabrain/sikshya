<?php

namespace Sikshya\Frontend;
use Sikshya\Core\Plugin;
use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Public\CartFormHandler;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\LearnPublicIdService;
use Sikshya\Services\Settings;
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
        add_action('admin_bar_menu', [$this, 'addSikshyaAdminBarLinks'], 80);
        // Run on init (before template output) so redirects/cookies are not broken by theme notices.
        add_action('init', [CartFormHandler::class, 'maybeHandle'], 20);
        add_action('template_redirect', [$this, 'handleTemplateRedirect']);
        add_action('wp', [$this, 'initFrontend']);
        add_filter('template_include', [$this, 'loadCustomTemplates'], 99);
        add_action('wp_body_open', [$this, 'addBodyClasses']);
    }

    /**
     * Add quick "Edit" links for Sikshya-specific frontend pages (admin bar).
     *
     * @param \WP_Admin_Bar $bar
     */
    public function addSikshyaAdminBarLinks($bar): void
    {
        if (!is_admin_bar_showing() || is_admin()) {
            return;
        }
        if (!$bar instanceof \WP_Admin_Bar) {
            return;
        }

        // Singular Sikshya post types (course/lesson/quiz/assignment/certificate).
        if (is_singular([PostTypes::COURSE, PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT, PostTypes::CERTIFICATE])) {
            $post_id = get_queried_object_id();
            if ($post_id > 0 && current_user_can('edit_post', $post_id)) {
                $pt = (string) get_post_type($post_id);
                $label = __('Edit', 'sikshya');
                $href = get_edit_post_link($post_id, 'raw');

                if ($pt === PostTypes::COURSE) {
                    $label = __('Edit course', 'sikshya');
                    // New admin UI: course builder.
                    $href = ReactAdminConfig::reactAppUrl('add-course', ['course_id' => (string) $post_id]);
                } elseif ($pt === PostTypes::LESSON) {
                    $label = __('Edit lesson', 'sikshya');
                    // New admin UI: edit within course builder curriculum tab.
                    $cid = (int) get_post_meta($post_id, '_sikshya_lesson_course', true);
                    if ($cid > 0) {
                        $href = ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::QUIZ) {
                    $label = __('Edit quiz', 'sikshya');
                    $cid = (int) get_post_meta($post_id, '_sikshya_quiz_course', true);
                    if ($cid > 0) {
                        $href = ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::ASSIGNMENT) {
                    $label = __('Edit assignment', 'sikshya');
                    $cid = (int) get_post_meta($post_id, '_sikshya_assignment_course', true);
                    if ($cid > 0) {
                        $href = ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::CERTIFICATE) {
                    $label = __('Edit certificate', 'sikshya');
                    // Certificates are managed in the React admin "Certificates" section.
                    $href = ReactAdminConfig::reactAppUrl('certificates');
                }
                $bar->add_node([
                    'id' => 'sikshya-edit-current',
                    // `site-name` is present across themes (incl. block themes); safest place.
                    'parent' => 'site-name',
                    'title' => $label,
                    'href' => $href,
                ]);

                // Extra fallback: add a top-level node too (some themes hide `site-name` children).
                $bar->add_node([
                    'id' => 'sikshya-edit-current-top',
                    'title' => $label,
                    'href' => $href,
                ]);
            }
            return;
        }

        // Course archive page (quick link to course list).
        if (is_post_type_archive(PostTypes::COURSE) && current_user_can('edit_posts')) {
            $bar->add_node([
                'id' => 'sikshya-edit-courses',
                'parent' => 'site-name',
                'title' => __('Edit courses', 'sikshya'),
                'href' => admin_url('edit.php?post_type=' . PostTypes::COURSE),
            ]);
            return;
        }
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

        // Distraction-free learn/player pages load a standalone shell and their own CSS/JS.
        if (PublicPageUrls::isCurrentVirtualPage('learn')) {
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

        // Course listings (archive, taxonomy, shortcodes/blocks that render cards)
        wp_enqueue_style(
            'sikshya-course-listing',
            $this->plugin->getAssetUrl('css/course-listing.css'),
            ['sikshya-public-ds', 'sikshya-frontend'],
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

        $frontendStrings = [
            'confirmEnroll' => __('Are you sure you want to enroll in this course?', 'sikshya'),
            'confirmUnenroll' => __('Are you sure you want to unenroll from this course?', 'sikshya'),
            'confirmSubmit' => __('Are you sure you want to submit this quiz?', 'sikshya'),
            'saving' => __('Saving...', 'sikshya'),
            'saved' => __('Saved successfully!', 'sikshya'),
            'error' => __('An error occurred. Please try again.', 'sikshya'),
            'loading' => __('Loading...', 'sikshya'),
            'noResults' => __('No results found.', 'sikshya'),
        ];

        // Localize script (camelCase object for newer code).
        wp_localize_script('sikshya-frontend', 'sikshyaFrontend', [
            'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => $frontendStrings,
        ]);

        // Legacy object expected by assets/js/frontend.js (snake_case + enroll AJAX nonce).
        wp_localize_script('sikshya-frontend', 'sikshya_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_url' => \Sikshya\Frontend\Public\PublicPageUrls::login(),
            'is_user_logged_in' => is_user_logged_in(),
            'nonce' => wp_create_nonce('sikshya_enroll_nonce'),
            'strings' => [
                'confirm_enroll' => $frontendStrings['confirmEnroll'],
                'confirm_quiz_submit' => $frontendStrings['confirmSubmit'],
                'loading' => $frontendStrings['loading'],
                'enroll_success' => __('Successfully enrolled.', 'sikshya'),
                'enroll_error' => __('Enrollment failed. Please try again.', 'sikshya'),
                'quiz_submit_error' => __('Could not submit the quiz.', 'sikshya'),
                'progress_update_error' => __('Could not update progress.', 'sikshya'),
                'error' => $frontendStrings['error'],
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

            wp_enqueue_script(
                'sikshya-course-reviews',
                $this->plugin->getAssetUrl('js/course-reviews.js'),
                [],
                $this->plugin->version,
                true
            );
            wp_localize_script('sikshya-course-reviews', 'sikshyaReviewsL10n', [
                'submitting' => __('Submitting…', 'sikshya'),
                'confirmDelete' => __('Delete your review? This cannot be undone.', 'sikshya'),
                'pickRating' => __('Please choose a rating before submitting.', 'sikshya'),
                'genericError' => __('Something went wrong. Please try again.', 'sikshya'),
                'loadMore' => __('Load more reviews', 'sikshya'),
                'loading' => __('Loading…', 'sikshya'),
                'youLabel' => __('You', 'sikshya'),
            ]);
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
                            'Payment session ready. If you are not redirected automatically, your checkout may still be using a legacy Stripe response—contact support.',
                            'sikshya'
                        ),
                        'stripeCancelled' => __(
                            'Stripe checkout was cancelled. You can choose a payment method again.',
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
        // Learn player routes: /learn/{type}/{slug} (no theme header/footer).
        if (PublicPageUrls::isCurrentVirtualPage('learn')) {
            $type = (string) get_query_var(PermalinkService::LEARN_TYPE_VAR);
            $slug = (string) get_query_var(PermalinkService::LEARN_SLUG_VAR);
            $pid  = (string) get_query_var(PermalinkService::LEARN_PUBLIC_ID_VAR);

            // Non-logged-in users landing on /learn/ should be redirected to discovery.
            // The learn hub is primarily a "My learning" dashboard; public/free access starts from courses.
            if ($type === '' && $slug === '' && !isset($_GET['course_id']) && !is_user_logged_in()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $archive = get_post_type_archive_link(PostTypes::COURSE);
                wp_safe_redirect($archive ?: home_url('/'), 302);
                exit;
            }

            // If the request is the generic learn page with course_id, redirect to the first item.
            // This keeps /learn/?course_id=123 from being a dead-end.
            if ($type === '' && $slug === '' && isset($_GET['course_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $course_id = (int) $_GET['course_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ($course_id > 0 && function_exists('sikshya_get_course_curriculum_public')) {
                    $curriculum = sikshya_get_course_curriculum_public($course_id);
                    foreach ($curriculum as $block) {
                        foreach ((array) ($block['contents'] ?? []) as $p) {
                            if (!$p instanceof \WP_Post) {
                                continue;
                            }
                            $pt = (string) $p->post_type;
                            if ($pt === PostTypes::LESSON) {
                                wp_safe_redirect(\Sikshya\Frontend\Public\PublicPageUrls::learnContentForPost($p), 302);
                                exit;
                            }
                            if ($pt === PostTypes::QUIZ) {
                                wp_safe_redirect(\Sikshya\Frontend\Public\PublicPageUrls::learnContentForPost($p), 302);
                                exit;
                            }
                            if ($pt === PostTypes::ASSIGNMENT) {
                                wp_safe_redirect(\Sikshya\Frontend\Public\PublicPageUrls::learnContentForPost($p), 302);
                                exit;
                            }
                        }
                    }
                }
            }

            if ($type !== '' && $slug !== '') {
                $post_type = '';
                $template  = '';

                switch ($type) {
                    case 'lesson':
                        $post_type = PostTypes::LESSON;
                        $template  = $this->plugin->getTemplatePath('single-lesson.php');
                        break;
                    case 'quiz':
                        $post_type = PostTypes::QUIZ;
                        $template  = $this->plugin->getTemplatePath('single-quiz.php');
                        break;
                    case 'assignment':
                        $post_type = PostTypes::ASSIGNMENT;
                        // If/when an assignment template exists, use it. Fallback to lesson template for now.
                        $template  = $this->plugin->getTemplatePath('single-lesson.php');
                        break;
                }

                $p = null;

                // Preferred (when enabled): resolve by public id and redirect if slug mismatches.
                if ($post_type !== '' && PermalinkService::learnUsePublicId() && $pid !== '') {
                    $resolved_id = LearnPublicIdService::postIdFromPublicId($pid, $post_type);
                    if ($resolved_id > 0) {
                        $p = get_post($resolved_id);
                        if ($p instanceof \WP_Post && $p->post_status === 'publish') {
                            $canonical = PublicPageUrls::learnContentForPost($p);
                            $req_slug = sanitize_title($slug);
                            if ($req_slug !== (string) $p->post_name) {
                                wp_safe_redirect($canonical, 301);
                                exit;
                            }
                        }
                    }
                }

                // Legacy: resolve by slug, and if public id mode is on, redirect to canonical URL with public id.
                if (!$p instanceof \WP_Post && $post_type !== '') {
                    $p = get_page_by_path(sanitize_title($slug), OBJECT, $post_type);
                    if ($p instanceof \WP_Post && $p->post_status === 'publish' && PermalinkService::learnUsePublicId()) {
                        wp_safe_redirect(PublicPageUrls::learnContentForPost($p), 301);
                        exit;
                    }
                }

                if ($p instanceof \WP_Post && $p->post_status === 'publish' && $template !== '' && file_exists($template)) {
                    global $wp_query;

                    $wp_query = new \WP_Query(
                        [
                            'post_type' => $post_type,
                            'p' => (int) $p->ID,
                        ]
                    );
                    $wp_query->is_singular = true;
                    $wp_query->is_single   = true;
                    $wp_query->is_home     = false;
                    $wp_query->is_page     = false;
                    $wp_query->is_archive  = false;

                    $GLOBALS['post'] = $p;
                    setup_postdata($p);

                    include $template;
                    exit;
                }

                // If we got here, the route is invalid.
                global $wp_query;
                if ($wp_query instanceof \WP_Query) {
                    $wp_query->set_404();
                    status_header(404);
                    nocache_headers();
                }
            }
        }

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
                'login' => 'login.php',
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

        foreach (['cart' => 'sikshya-cart-page', 'checkout' => 'sikshya-checkout-page', 'order' => 'sikshya-order-page', 'account' => 'sikshya-account-page', 'learn' => 'sikshya-learn-page', 'login' => 'sikshya-login-page'] as $k => $class) {
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
        $notices = Settings::getRaw('sikshya_frontend_notices', []);
        $notices[] = [
            'message' => $message,
            'type' => $type,
        ];
        Settings::setRaw('sikshya_frontend_notices', $notices);
    }

    /**
     * Display frontend notices
     */
    public function displayNotices(): void
    {
        $notices = Settings::getRaw('sikshya_frontend_notices', []);

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
