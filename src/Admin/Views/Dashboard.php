<?php

namespace Sikshya\Admin\Views;

/**
 * Custom Dashboard Component
 *
 * @package Sikshya\Admin\Views
 */
class Dashboard extends BaseView
{
    /**
     * Dashboard configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Dashboard widgets
     *
     * @var array
     */
    protected array $widgets = [];

    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     * @param array $config
     */
    public function __construct(\Sikshya\Core\Plugin $plugin, array $config = [])
    {
        parent::__construct($plugin);
        $this->config = $this->getDefaultConfig();
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Add widget
     *
     * @param string $id
     * @param array $widget
     * @return $this
     */
    public function addWidget(string $id, array $widget): self
    {
        $this->widgets[$id] = $widget;
        return $this;
    }

    /**
     * Add widgets
     *
     * @param array $widgets
     * @return $this
     */
    public function addWidgets(array $widgets): self
    {
        foreach ($widgets as $id => $widget) {
            $this->addWidget($id, $widget);
        }
        return $this;
    }

    /**
     * Set dashboard configuration
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Render the dashboard
     *
     * @return string
     */
    public function renderDashboard(): string
    {
        $this->enqueueAssets();

        return $this->renderToString('dashboard', [
            'config' => $this->config,
            'widgets' => $this->widgets,
        ]);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'title' => __('Sikshya LMS Dashboard', 'sikshya'),
            'description' => __('Welcome to your learning management system', 'sikshya'),
            'layout' => 'grid', // grid, list, masonry
            'columns' => 3,
            'show_welcome' => true,
            'show_stats' => true,
            'show_recent' => true,
            'show_quick_actions' => true,
            'show_notifications' => true,
            'refresh_interval' => 300, // 5 minutes
            'theme' => 'light', // light, dark, auto
        ];
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-dashboard');
        wp_enqueue_script('sikshya-dashboard');
    }
}
