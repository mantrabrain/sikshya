<?php

namespace Sikshya\Frontend;
use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Site\CartFormHandler;
use Sikshya\Frontend\Site\CartStorage;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Services\CourseFrontendSettings;
use Sikshya\Services\LessonCourseLink;
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
use Sikshya\Blocks\ContentHasSikshyaBlock;
use Sikshya\Shortcodes\AuthShortcodes;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

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
        add_action('admin_bar_menu', [$this, 'customizeTaxonomyAdminBarEdit'], 999);
        // Run on init (before template output) so redirects/cookies are not broken by theme notices.
        add_action('init', [CartStorage::class, 'registerHooks'], 12);
        add_action('init', [CartFormHandler::class, 'maybeHandle'], 20);
        // Before other plugins that hook template_redirect and match the same path (e.g. Yatra `/account/`).
        add_action('template_redirect', [$this, 'maybeServeVirtualAccountEarly'], 0);
        add_action('template_redirect', [$this, 'handleTemplateRedirect']);
        add_action('wp', [$this, 'initFrontend']);
        add_filter('template_include', [$this, 'loadCustomTemplates'], 99);
        add_filter('document_title_parts', [$this, 'filterDocumentTitleCourseCategoryRoot'], 20);
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
                    $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('add-course', ['course_id' => (string) $post_id]);
                } elseif ($pt === PostTypes::LESSON) {
                    $label = __('Edit lesson', 'sikshya');
                    // New admin UI: edit within course builder curriculum tab.
                    $cid = LessonCourseLink::resolvedCourseIdForLesson($post_id);
                    if ($cid > 0) {
                        $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::QUIZ) {
                    $label = __('Edit quiz', 'sikshya');
                    $cid = \Sikshya\Services\LessonCourseLink::resolvedCourseIdForQuiz($post_id);
                    if ($cid > 0) {
                        $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::ASSIGNMENT) {
                    $label = __('Edit assignment', 'sikshya');
                    $cid = \Sikshya\Services\LessonCourseLink::resolvedCourseIdForAssignment($post_id);
                    if ($cid > 0) {
                        $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('add-course', [
                            'course_id' => (string) $cid,
                            'tab' => 'curriculum',
                        ]);
                    }
                } elseif ($pt === PostTypes::CERTIFICATE) {
                    $label = __('Edit certificate', 'sikshya');
                    // Certificates are managed in the React admin "Certificates" section.
                    $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('certificates');
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

        // Course archive page (quick link to React course list).
        if (is_post_type_archive(PostTypes::COURSE) && current_user_can('edit_posts')) {
            $bar->add_node([
                'id' => 'sikshya-edit-courses',
                'parent' => 'site-name',
                'title' => __('Edit courses', 'sikshya'),
                'href' => \Sikshya\Admin\ReactAdminConfig::reactAppUrl('courses'),
            ]);
            return;
        }
    }

    /**
     * Replace WordPress core "Edit Course Category" admin-bar link (runs after core at priority 80).
     *
     * @param \WP_Admin_Bar $bar Admin bar.
     */
    public function customizeTaxonomyAdminBarEdit($bar): void
    {
        if (!is_admin_bar_showing() || is_admin()) {
            return;
        }
        if (!$bar instanceof \WP_Admin_Bar) {
            return;
        }
        if (!is_tax(Taxonomies::COURSE_CATEGORY)) {
            return;
        }

        $term = get_queried_object();
        if (!$term instanceof \WP_Term || !current_user_can('edit_term', (int) $term->term_id, $term->taxonomy)) {
            return;
        }

        $href = \Sikshya\Admin\ReactAdminConfig::reactAppUrl('course-categories', [
            'category_id' => (string) (int) $term->term_id,
        ]);

        $bar->add_node([
            'id' => 'edit',
            'title' => __('Edit course category', 'sikshya'),
            'href' => $href,
        ]);

        $bar->remove_node('sikshya-edit-current');
        $bar->remove_node('sikshya-edit-current-top');
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

        if ($this->shouldEnqueueCourseListingStyles()) {
            wp_enqueue_style(
                'sikshya-course-listing',
                $this->plugin->getAssetUrl('css/course-listing.css'),
                ['sikshya-public-ds', 'sikshya-frontend'],
                $this->plugin->version
            );
        }

        if ($this->shouldEnqueueAuthAssets()) {
            AuthShortcodes::registerPublicScript();
            wp_enqueue_script('sikshya-auth-public');
        }

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
            wp_enqueue_script(
                'sikshya-cart-page',
                $this->plugin->getAssetUrl('js/cart-page.js'),
                [],
                $this->plugin->version,
                true
            );
            wp_localize_script(
                'sikshya-cart-page',
                'sikshyaCartConfig',
                [
                    'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
                    'restNonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
                    'guestNonce' => wp_create_nonce('sikshya_guest_checkout'),
                    'isLoggedIn' => is_user_logged_in(),
                    'guestEnabled' => Settings::isTruthy(Settings::get('enable_guest_checkout', true)),
                    'i18n' => [
                        'updatingTotals' => __('Updating totals…', 'sikshya'),
                        'quoteFailed' => __('Could not update totals.', 'sikshya'),
                        'networkError' => __('Network error. Please try again.', 'sikshya'),
                        'signInToQuote' => __('Please sign in to apply a discount code.', 'sikshya'),
                        'cartCouponSaved' => __('Totals updated. This code will appear on checkout.', 'sikshya'),
                    ],
                ]
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
            'login_url' => \Sikshya\Frontend\Site\PublicPageUrls::login(),
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
            // Note: there used to be `wp_enqueue_script('sikshya-course-viewer')`
            // / `…-style` calls here, and the same pattern for `…-lesson-viewer`,
            // `…-dashboard`, and `…-course-catalog` below. Those handles were
            // never registered (no `wp_register_script`/`_style` call anywhere
            // in the plugin) and the underlying JS/CSS files don't exist on
            // disk. WP responded with `doing_it_wrong()` notices on every
            // course/lesson view in production logs. The single-course and
            // single-lesson templates are server-rendered today (Vite builds
            // ship via `sikshya-frontend` already enqueued above), so the
            // page-specific bundles are dead enqueues. Removed.

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
                        'signInToCheckout' => __('Please sign in to continue checkout.', 'sikshya'),
                        'guestEmailInvalid' => __('Please enter a valid email address to continue.', 'sikshya'),
                        'missingRequired' => __('Please complete all required fields to continue.', 'sikshya'),
                        'confirmFailed' => __('Could not confirm payment.', 'sikshya'),
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

        // Course archive FOUC prevention: a blocking inline script reads the
        // learner's saved grid/list preference and sets a data attribute on
        // <html> before the body paints. CSS rules in `course-listing.css`
        // override the server-rendered class so the toggle never flickers.
        if (is_post_type_archive(PostTypes::COURSE)) {
            $admin_default = \Sikshya\Services\CourseFrontendSettings::archiveLayout();
            $admin_default = ($admin_default === 'list') ? 'list' : 'grid';
            echo "<script id=\"sikshya-archive-view-boot\">(function(){var v='" . esc_js($admin_default) . "';try{var s=window.localStorage.getItem('sikshya_course_archive_view');if(s==='list'||s==='grid'){v=s;}}catch(e){}document.documentElement.setAttribute('data-sikshya-archive-view',v);})();</script>";
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
        if (PublicPageUrls::isCourseCategoryRootRequest()) {
            if (!CourseFrontendSettings::areCategoriesEnabled()) {
                wp_safe_redirect(get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'), 302);
                exit;
            }
        }

        // Published assignments use the Learn player (`/learn/assignment/{slug}/`). CPT permalinks
        // (`…/assignments/{slug}/`) have no dedicated template — the theme would show a bare post shell.
        if (
            !is_admin()
            && !(function_exists('wp_doing_ajax') && wp_doing_ajax())
            && !(function_exists('wp_is_json_request') && wp_is_json_request())
            && is_singular(PostTypes::ASSIGNMENT)
        ) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post && $post->post_status === 'publish' && !is_preview()) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route branching.
                if (!isset($_GET['preview'])) {
                    wp_safe_redirect(PublicPageUrls::learnContentForPost($post), 301);
                    exit;
                }
            }
        }

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
                                wp_safe_redirect(\Sikshya\Frontend\Site\PublicPageUrls::learnContentForPost($p), 302);
                                exit;
                            }
                            if ($pt === PostTypes::QUIZ) {
                                wp_safe_redirect(\Sikshya\Frontend\Site\PublicPageUrls::learnContentForPost($p), 302);
                                exit;
                            }
                            if ($pt === PostTypes::ASSIGNMENT) {
                                wp_safe_redirect(\Sikshya\Frontend\Site\PublicPageUrls::learnContentForPost($p), 302);
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
     * Output the Sikshya learner account template at priority 0 so path-based routers in other plugins
     * (notably Yatra, which defaults to `/account/` and exits in template_redirect) cannot preempt it
     * when WordPress has already resolved {@see PermalinkService::QUERY_VAR}=account.
     */
    public function maybeServeVirtualAccountEarly(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return;
        }
        if (is_feed() || is_embed() || is_trackback()) {
            return;
        }
        if (!PublicPageUrls::isCurrentVirtualPage('account')) {
            return;
        }

        $path = $this->plugin->getTemplatePath('account.php');
        if ($path === '' || !is_readable($path)) {
            return;
        }

        include $path;
        exit;
    }

    /**
     * Load custom templates
     */
    public function loadCustomTemplates(string $template): string
    {
        if (PublicPageUrls::isCourseCategoryRootRequest() && CourseFrontendSettings::areCategoriesEnabled()) {
            $custom_template = $this->plugin->getTemplatePath('taxonomy-course-category-root.php');
            if ($custom_template !== '' && is_readable($custom_template)) {
                return $custom_template;
            }
        }

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

        if ((string) get_query_var(PermalinkService::INSTRUCTOR_VAR) !== '') {
            $custom_template = $this->plugin->getTemplatePath('author.php');
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
     * Browser tab title for /course-category/ (category index).
     *
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filterDocumentTitleCourseCategoryRoot(array $parts): array
    {
        if (!PublicPageUrls::isCourseCategoryRootRequest() || !CourseFrontendSettings::areCategoriesEnabled()) {
            return $parts;
        }

        $label_courses = function_exists('sikshya_label_plural')
            ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend')
            : __('Courses', 'sikshya');
        $parts['title'] = sprintf(
            /* translators: %s: plural course label (e.g. Courses) */
            __('%s categories', 'sikshya'),
            $label_courses
        );

        return $parts;
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

        if (is_tax([Taxonomies::COURSE_CATEGORY, Taxonomies::COURSE_TAG])) {
            $classes[] = 'sikshya-course-taxonomy';
            $classes[] = 'sikshya-course-page';
        }

        if (PublicPageUrls::isCourseCategoryRootRequest() && CourseFrontendSettings::areCategoriesEnabled()) {
            $classes[] = 'sikshya-course-category-index';
            $classes[] = 'sikshya-course-page';
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

    private function shouldEnqueueCourseListingStyles(): bool
    {
        if (is_post_type_archive(PostTypes::COURSE)) {
            return true;
        }

        if (is_tax([Taxonomies::COURSE_CATEGORY, Taxonomies::COURSE_TAG])) {
            return true;
        }

        if (is_page('sikshya-courses')) {
            return true;
        }

        if ((string) get_query_var(PermalinkService::INSTRUCTOR_VAR) !== '') {
            return true;
        }

        return ContentHasSikshyaBlock::hasCoursesListing();
    }

    private function shouldEnqueueAuthAssets(): bool
    {
        if (PublicPageUrls::isCurrentVirtualPage('checkout')
            || PublicPageUrls::isCurrentVirtualPage('login')
        ) {
            return true;
        }

        return ContentHasSikshyaBlock::hasAuth();
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
