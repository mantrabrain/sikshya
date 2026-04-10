<?php

namespace Sikshya\Admin\Views;

use Sikshya\Core\Plugin;

/**
 * Base View Class for Custom UI Components
 *
 * @package Sikshya\Admin\Views
 */
abstract class BaseView
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    protected Plugin $plugin;

    /**
     * View data
     *
     * @var array
     */
    protected array $data = [];

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
     * Set view data
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get view data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Render the view
     *
     * @param string $template
     * @param array $data
     */
    public function render(string $template, array $data = []): void
    {
        try {
            $this->setData($data);
            $this->includeTemplate($template);
        } catch (\Exception $e) {
            error_log('Sikshya BaseView Error: ' . $e->getMessage());
            error_log('Sikshya BaseView Stack: ' . $e->getTraceAsString());
        }
    }

    /**
     * Render the view and return as string
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    public function renderToString(string $template, array $data = []): string
    {
        try {
            $this->setData($data);

            ob_start();
            $this->includeTemplate($template);
            $output = ob_get_clean();

            return $output !== false ? $output : '';
        } catch (\Exception $e) {
            error_log('Sikshya BaseView Error: ' . $e->getMessage());
            error_log('Sikshya BaseView Stack: ' . $e->getTraceAsString());
            return '<!-- Sikshya View Error: ' . esc_html($e->getMessage()) . ' -->';
        }
    }

    /**
     * Include template file
     *
     * @param string $template
     */
    protected function includeTemplate(string $template): void
    {
        $template_path = $this->plugin->getTemplatePath("admin/views/{$template}.php");

        if (file_exists($template_path)) {
            try {
                extract($this->data);
                include $template_path;
            } catch (\Exception $e) {
                error_log('Sikshya BaseView: Template include error: ' . $e->getMessage());
                throw $e;
            }
        } else {
            echo '<!-- Template Not Found: ' . esc_html($template) . ' -->';
        }
    }

    /**
     * Get template path
     *
     * @param string $template
     * @return string
     */
    protected function getTemplatePath(string $template): string
    {
        return $this->plugin->getTemplatePath("admin/views/{$template}.php");
    }

    /**
     * Enqueue view assets
     */
    abstract public function enqueueAssets(): void;
}
