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
            error_log('Sikshya BaseView: Starting render for template: ' . $template);
            error_log('Sikshya BaseView: Data passed to render - active_tab: ' . ($data['active_tab'] ?? 'NOT SET') . ', course_id: ' . ($data['course_id'] ?? 'NOT SET') . ', course_data: ' . ($data['course_data'] ? 'SET' : 'NULL'));
            
            $this->setData($data);
            error_log('Sikshya BaseView: Data after setData - active_tab: ' . ($this->data['active_tab'] ?? 'NOT SET') . ', course_id: ' . ($this->data['course_id'] ?? 'NOT SET') . ', course_data: ' . ($this->data['course_data'] ? 'SET' : 'NULL'));
            $this->includeTemplate($template);
            
            error_log('Sikshya BaseView: Finished render for template: ' . $template);
        } catch (\Exception $e) {
            error_log('Sikshya BaseView Error: ' . $e->getMessage());
            error_log('Sikshya BaseView Stack: ' . $e->getTraceAsString());
            // Removed notice display - errors are logged instead
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
            error_log('Sikshya BaseView: Starting renderToString for template: ' . $template);
            
            $this->setData($data);
            
            ob_start();
            $this->includeTemplate($template);
            $output = ob_get_clean();
            
            error_log('Sikshya BaseView: Finished renderToString for template: ' . $template);
            
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
        
        error_log('Sikshya BaseView: Looking for template at: ' . $template_path);
        error_log('Sikshya BaseView: Template file exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
        
        if (file_exists($template_path)) {
            try {
                error_log('Sikshya BaseView: About to extract data - active_tab: ' . ($this->data['active_tab'] ?? 'NOT SET') . ', course_id: ' . ($this->data['course_id'] ?? 'NOT SET') . ', course_data: ' . ($this->data['course_data'] ? 'SET' : 'NULL'));
                extract($this->data);
                error_log('Sikshya BaseView: After extract - active_tab: ' . (isset($active_tab) ? $active_tab : 'NOT SET') . ', course_id: ' . (isset($course_id) ? $course_id : 'NOT SET') . ', course_data: ' . (isset($course_data) ? 'SET' : 'NULL'));
                include $template_path;
                error_log('Sikshya BaseView: Template included successfully: ' . $template);
            } catch (\Exception $e) {
                error_log('Sikshya BaseView: Template include error: ' . $e->getMessage());
                throw $e;
            }
        } else {
            error_log('Sikshya BaseView: Template not found: ' . $template_path);
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