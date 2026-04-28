<?php

namespace Sikshya\Services;

use Sikshya\Admin\SetupWizardController;
use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;

/**
 * Admin Asset Management Service
 *
 * @package Sikshya\Services
 */
class AdminAssetsService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Initialize admin assets
     */
    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_init', [$this, 'registerAdminAssets']);
        add_filter('script_loader_tag', [$this, 'filterReactAdminScriptLoaderTag'], 10, 3);
    }

    /**
     * Register admin assets
     */
    public function registerAdminAssets(): void
    {
        // Register toast assets
        wp_register_style(
            'sikshya-admin',
            $this->plugin->getAssetUrl('admin/css/admin.css'),
            [],
            SIKSHYA_VERSION
        );

        wp_register_style(
            'sikshya-admin-shell',
            $this->plugin->getAssetUrl('admin/css/admin-shell.css'),
            ['sikshya-admin', 'dashicons'],
            SIKSHYA_VERSION
        );

        wp_register_style(
            'sikshya-toast',
            $this->plugin->getAssetUrl('admin/css/toast.css'),
            [],
            SIKSHYA_VERSION
        );

        wp_register_script(
            'sikshya-toast',
            $this->plugin->getAssetUrl('admin/js/toast.js'),
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        // Setup wizard (one-time admin onboarding).
        wp_register_style(
            'sikshya-setup-wizard',
            $this->plugin->getAssetUrl('admin/css/setup-wizard.css'),
            [],
            SIKSHYA_VERSION
        );

        wp_register_script(
            'sikshya-setup-wizard',
            $this->plugin->getAssetUrl('admin/js/setup-wizard.js'),
            [],
            SIKSHYA_VERSION,
            true
        );

        $react_css = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.css';
        $react_js = SIKSHYA_PLUGIN_DIR . 'assets/admin/react/sikshya-admin.js';
        if (file_exists($react_css)) {
            wp_register_style(
                'sikshya-react-admin',
                $this->plugin->getAssetUrl('admin/react/sikshya-admin.css'),
                [],
                (string) filemtime($react_css)
            );
        }
        wp_register_script(
            'sikshya-react-admin',
            $this->plugin->getAssetUrl('admin/react/sikshya-admin.js'),
            ['jquery'],
            file_exists($react_js) ? (string) filemtime($react_js) : SIKSHYA_VERSION,
            true
        );

        wp_register_style(
            'sikshya-react-shell',
            $this->plugin->getAssetUrl('admin/css/react-shell.css'),
            [],
            SIKSHYA_VERSION
        );

    }

    /**
     * Vite emits an ES-module graph (`import` + shared chunks). WordPress defaults to classic scripts.
     *
     * @param string $tag    Full HTML markup for the script element.
     * @param string $handle Script handle.
     * @param string $src    Script source URL (unused; kept for the filter signature).
     * @return string
     */
    public function filterReactAdminScriptLoaderTag(string $tag, string $handle, string $src): string
    {
        unset($src);
        if ($handle !== 'sikshya-react-admin') {
            return $tag;
        }
        if (strpos($tag, 'type=') !== false) {
            return $tag;
        }
        return str_replace('<script ', '<script type="module" ', $tag);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $page_query = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $is_setup_wizard = $page_query === SetupWizardController::MENU_SLUG;

        $screen = get_current_screen();

        if ($is_setup_wizard) {
            if (wp_style_is('sikshya-react-shell', 'registered')) {
                wp_enqueue_style('sikshya-react-shell');
            }
            if (wp_style_is('sikshya-setup-wizard', 'registered')) {
                wp_enqueue_style('sikshya-setup-wizard');
            }
            if (wp_script_is('sikshya-setup-wizard', 'registered')) {
                wp_enqueue_script('sikshya-setup-wizard');
            }

            wp_localize_script(
                'sikshya-setup-wizard',
                'sikshyaSetupWizard',
                [
                    'restUrl' => esc_url_raw(rest_url('sikshya/v1/admin/setup-wizard/step')),
                    'sampleImportUrl' => esc_url_raw(rest_url('sikshya/v1/admin/setup-wizard/sample-import')),
                    'coursesUrl' => esc_url_raw(admin_url('admin.php?page=sikshya&view=courses')),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'dashboardUrl' => esc_url_raw(admin_url('admin.php?page=sikshya')),
                    'siteUrl' => esc_url_raw(home_url('/')),
                    'strings' => [
                        'saving' => __('Saving…', 'sikshya'),
                        'confirmSkipAll' => __('Skip setup? You can re-run the wizard anytime from Sikshya → Tools.', 'sikshya'),
                        'sampleAddLabel' => __('Add sample course', 'sikshya'),
                        'sampleAdding' => __('Adding sample course…', 'sikshya'),
                        'sampleAdded' => __('Sample course added.', 'sikshya'),
                        'sampleAddFailed' => __('Sample course could not be added.', 'sikshya'),
                        'sampleViewCourses' => __('View courses', 'sikshya'),
                    ],
                ]
            );

            return;
        }

        // Check if we're on a Sikshya admin page
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

        $screen_id = (string) ($screen->id ?? '');
        $screen_base = (string) ($screen->base ?? '');

        // React shell bundle: only the unified Sikshya app screen (subpages use `view=`).
        if ($screen_id === 'toplevel_page_sikshya') {
            wp_enqueue_media();

            wp_enqueue_style('sikshya-react-shell');
            if (wp_style_is('sikshya-react-admin', 'registered')) {
                wp_enqueue_style('sikshya-react-admin');
            }

            // Defensive: some environments can load the React module without the expected
            // bootstrap payload (caching/filters/partial output). Ensure the shell always
            // has a minimal `user` object so it never hard-crashes on destructuring.
            wp_add_inline_script(
                'sikshya-react-admin',
                'window.sikshyaReact=window.sikshyaReact||{};window.sikshyaReact.user=window.sikshyaReact.user||{name:"Admin",avatarUrl:""};',
                'before'
            );
            wp_enqueue_script('sikshya-react-admin');

            wp_enqueue_style('sikshya-toast');
            wp_enqueue_script('sikshya-toast');

            return;
        }

        if (
            strpos($screen_id, 'sikshya') === false
            && strpos($screen_base, 'sikshya') === false
            && !$is_sikshya_post_screen
        ) {
            return;
        }
    }

    /**
     * Get asset URL
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path = ''): string
    {
        return $this->plugin->getAssetUrl($path);
    }

    /**
     * Get asset path
     *
     * @param string $path
     * @return string
     */
    public function getAssetPath(string $path = ''): string
    {
        return $this->plugin->getAssetPath($path);
    }
}
