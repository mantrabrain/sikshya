<?php

namespace Sikshya\Core;

use Sikshya\Admin\Admin;
use Sikshya\Frontend\Frontend;
use Sikshya\Api\Api;
use Sikshya\Database\Database;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\PostTypeService;
use Sikshya\Services\TaxonomyService;
use Sikshya\Services\CacheService;
use Sikshya\Services\LogService;
use Sikshya\Services\SecurityService;
use Sikshya\Services\AnalyticsService;
use Sikshya\Services\CourseService;
use Sikshya\Services\FrontendAssetsService;
use Sikshya\Services\AdminAssetsService;
use Sikshya\Services\RestCollectionQueryService;
use Sikshya\Services\Settings;

/**
 * Main Plugin Class
 *
 * @package Sikshya\Core
 */
final class Plugin
{
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    public string $version = SIKSHYA_VERSION;

    /**
     * Plugin directory path
     *
     * @var string
     */
    public string $pluginPath = SIKSHYA_PLUGIN_DIR;

    /**
     * Plugin URL
     *
     * @var string
     */
    public string $pluginUrl = SIKSHYA_PLUGIN_URL;

    /**
     * Service container
     *
     * @var array
     */
    private array $services = [];

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->initHooks();
        $this->loadTextdomain();
        $this->initializeServices();
        $this->initializeComponents();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('init', [$this, 'init'], 0);
        add_action('wp_loaded', [$this, 'onWpLoaded']);
        add_action('admin_init', [$this, 'onAdminInit']);
        add_action('wp_enqueue_scripts', [$this, 'onEnqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'onAdminEnqueueScripts']);
    }

    /**
     * Initialize services
     */
    private function initializeServices(): void
    {
        try {
            // Core services
            $this->services['database'] = new Database($this);
            $this->services['cache'] = new CacheService($this);
            $this->services['log'] = new LogService($this);
            $this->services['security'] = new SecurityService($this);
            $this->services['analytics'] = new AnalyticsService($this);
            $this->services['view'] = new View($this);

            // Repositories (shared; all DB access should flow through these from services)
            $this->services['courseRepository'] = new \Sikshya\Database\Repositories\CourseRepository();
            $this->services['postMetaRepository'] = new \Sikshya\Database\Repositories\PostMetaRepository();
            $this->services['contentPostRepository'] = new \Sikshya\Database\Repositories\ContentPostRepository();
            $this->services['taxonomyRepository'] = new \Sikshya\Database\Repositories\TaxonomyRepository();
            $this->services['jwtAuth'] = new \Sikshya\Api\JwtAuthService();

            // Course service (uses shared course repository)
            $this->services['course'] = new \Sikshya\Services\CourseService($this->services['courseRepository']);

            $this->services['enrollment'] = new \Sikshya\Services\LearnerEnrollmentService($this->services['course']);
            $this->services['progress'] = new \Sikshya\Services\LearningProgressService();
            $this->services['user'] = new \Sikshya\Services\FrontendUserService();
            $this->services['activity'] = new \Sikshya\Services\LearnerActivityStub();
            $this->services['certificate'] = new \Sikshya\Services\LearnerCertificateStub();
            $this->services['achievement'] = new \Sikshya\Services\LearnerAchievementStub();
            $this->services['quiz'] = new \Sikshya\Services\LearnerQuizStatsStub();

            // Course builder orchestration (admin UI + REST)
            $this->services['courseBuilder'] = new \Sikshya\Services\CourseBuilderService(
                $this,
                $this->services['courseRepository']
            );

            // Settings service
            $this->services['settings'] = new \Sikshya\Admin\Settings\SettingsManager($this);

            // REST API services (used by \Sikshya\Api\Api route callbacks)
            $this->services['api.course'] = new \Sikshya\Api\CourseService();
            $this->services['api.lesson'] = new \Sikshya\Api\LessonService();
            $this->services['api.quiz'] = new \Sikshya\Api\QuizService();
            $this->services['api.user'] = new \Sikshya\Api\UserService();
            $this->services['api.enrollment'] = new \Sikshya\Api\EnrollmentService();
            $this->services['api.progress'] = new \Sikshya\Api\ProgressService();
            $this->services['api.certificate'] = new \Sikshya\Api\CertificateService();
            $this->services['api.payment'] = new \Sikshya\Api\PaymentService();

            // WordPress integration services
            $this->services['frontendAssets'] = new FrontendAssetsService($this);
            $this->services['adminAssets'] = new AdminAssetsService($this);
            $this->services['restCollectionQuery'] = new RestCollectionQueryService();
            $this->services['postTypes'] = new PostTypeService($this);
            $this->services['taxonomies'] = new TaxonomyService($this);

            // Curriculum chapter/content helpers for REST (React admin).
            $this->services['courseBuilderUi'] = new \Sikshya\Services\CourseCurriculumActions();

            $this->services['categoryService'] = new \Sikshya\Services\CategoryService($this->services['taxonomyRepository']);
            $this->services['curriculum'] = new \Sikshya\Services\CurriculumService(
                $this,
                $this->services['postMetaRepository'],
                $this->services['courseRepository'],
                $this->services['contentPostRepository']
            );
        } catch (\Exception $e) {
            error_log('Sikshya Plugin Error: ' . $e->getMessage());
            error_log('Sikshya Plugin Error Stack: ' . $e->getTraceAsString());

            // Add admin notice
            add_action('admin_notices', function () use ($e) {
                // Error logged instead of displayed on Sikshya pages
                error_log('Sikshya LMS Error: ' . $e->getMessage());
            });
        }
    }

    /**
     * Initialize components
     */
    private function initializeComponents(): void
    {
        // Initialize admin interface
        if (is_admin()) {
            $this->services['admin'] = new \Sikshya\Admin\Admin($this);
        }

        // Initialize frontend interface
        if (!is_admin() || wp_doing_ajax()) {
            $this->services['frontend'] = new \Sikshya\Frontend\Frontend($this);
        }

        // Initialize API
        $this->services['api'] = new \Sikshya\Api\Api($this);
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        PermalinkService::boot();

        $stored_ver = (string) Settings::getRaw('sikshya_plugin_version', '');
        if ($stored_ver !== $this->version) {
            Settings::setRaw('sikshya_plugin_version', $this->version);
            flush_rewrite_rules(false);
        }

        if (isset($this->services['database']) && $this->services['database'] instanceof \Sikshya\Database\Database) {
            $this->services['database']->maybeUpgrade();
        }

        // Initialize post types and taxonomies
        $this->services['postTypes']->register();
        $this->services['taxonomies']->register();

        // Initialize assets
        $this->services['frontendAssets']->init();
        $this->services['adminAssets']->init();
        if (isset($this->services['restCollectionQuery']) && $this->services['restCollectionQuery'] instanceof RestCollectionQueryService) {
            $this->services['restCollectionQuery']->init();
        }

        // Hook into WordPress
        do_action('sikshya_init', $this);
    }

    /**
     * On WordPress loaded
     */
    public function onWpLoaded(): void
    {
        do_action('sikshya_loaded', $this);
    }

    /**
     * On admin init
     */
    public function onAdminInit(): void
    {
        do_action('sikshya_admin_init', $this);
    }

    /**
     * On enqueue scripts
     */
    public function onEnqueueScripts(): void
    {
        $this->services['frontendAssets']->enqueueFrontendAssets();
    }

    /**
     * On admin enqueue scripts
     */
    public function onAdminEnqueueScripts(): void
    {
        $this->services['adminAssets']->enqueueAdminAssets();
    }



    /**
     * Load plugin textdomain
     */
    private function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'sikshya',
            false,
            dirname(plugin_basename(SIKSHYA_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Get service
     *
     * @param string $service
     * @return mixed|null
     */
    public function getService(string $service)
    {
        return $this->services[$service] ?? null;
    }

    /**
     * Get all services
     *
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Get view service
     *
     * @return View|null
     */
    public function getView()
    {
        return $this->services['view'] ?? null;
    }

    /**
     * Get plugin path
     *
     * @param string $path
     * @return string
     */
    public function getPluginPath(string $path = ''): string
    {
        return untrailingslashit($this->pluginPath) . '/' . $path;
    }

    /**
     * Get plugin URL
     *
     * @param string $path
     * @return string
     */
    public function getPluginUrl(string $path = ''): string
    {
        return untrailingslashit($this->pluginUrl) . '/' . $path;
    }

    /**
     * Get asset URL
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path = ''): string
    {
        return $this->getPluginUrl('assets/' . ltrim($path, '/'));
    }

    /**
     * Get asset path
     *
     * @param string $path
     * @return string
     */
    public function getAssetPath(string $path = ''): string
    {
        return $this->getPluginPath('assets/' . ltrim($path, '/'));
    }

    /**
     * Get template path
     *
     * @param string $path
     * @return string
     */
    public function getTemplatePath(string $path = ''): string
    {
        return $this->getPluginPath('templates/' . ltrim($path, '/'));
    }

    /**
     * Get config path
     *
     * @param string $path
     * @return string
     */
    public function getConfigPath(string $path = ''): string
    {
        return $this->getPluginPath('config/' . ltrim($path, '/'));
    }

    /**
     * Check if plugin is in development mode
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if plugin is in production mode
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return !$this->isDevelopment();
    }

    /**
     * Get plugin info
     *
     * @return array
     */
    public function getPluginInfo(): array
    {
        return [
            'name' => 'Sikshya LMS',
            'version' => $this->version,
            'description' => 'A comprehensive WordPress Learning Management System plugin',
            'author' => 'Sikshya Team',
            'author_url' => 'https://sikshya.com',
            'plugin_url' => 'https://sikshya.com',
            'support_url' => 'https://support.sikshya.com',
            'documentation_url' => 'https://docs.sikshya.com',
        ];
    }
}
