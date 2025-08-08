<?php

namespace Sikshya\Core;

use Sikshya\Admin\Admin;
use Sikshya\Frontend\Frontend;
use Sikshya\Api\Api;
use Sikshya\Database\Database;
use Sikshya\Services\AssetService;
use Sikshya\Services\PostTypeService;
use Sikshya\Services\TaxonomyService;
use Sikshya\Services\CacheService;
use Sikshya\Services\LogService;
use Sikshya\Services\SecurityService;
use Sikshya\Services\AnalyticsService;
use Sikshya\Services\CourseService;

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

            // Course service
            $this->services['course'] = new \Sikshya\Services\CourseService();

            // WordPress integration services
            $this->services['assets'] = new AssetService($this);
            $this->services['postTypes'] = new PostTypeService($this);
            $this->services['taxonomies'] = new TaxonomyService($this);
        } catch (\Exception $e) {
            error_log('Sikshya Plugin Error: ' . $e->getMessage());
            error_log('Sikshya Plugin Error Stack: ' . $e->getTraceAsString());
            
            // Add admin notice
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>Sikshya LMS Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
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
        // Initialize post types and taxonomies
        $this->services['postTypes']->register();
        $this->services['taxonomies']->register();

        // Initialize assets
        $this->services['assets']->init();

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
        $this->services['assets']->enqueueFrontendAssets();
    }

    /**
     * On admin enqueue scripts
     */
    public function onAdminEnqueueScripts(): void
    {
        $this->services['assets']->enqueueAdminAssets();
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