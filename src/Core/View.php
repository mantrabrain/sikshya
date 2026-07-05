<?php

namespace Sikshya\Core;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * View Class
 *
 * Handles template rendering and view management
 *
 * @package Sikshya\Core
 */
class View
{
    /**
     * Plugin instance
     */
    private Plugin $plugin;

    /**
     * Constructor
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Render a template
     *
     * @param string $template Template path relative to templates directory
     * @param array $data Data to pass to template
     * @return void
     */
    public function render(string $template, array $data = []): void
    {
        $template_path = $this->plugin->getTemplatePath($template . '.php');

        if (!file_exists($template_path)) {
            error_log("Template not found: {$template_path}");
            return;
        }

        // Extract data to make variables available in template.
        //
        // EXTR_SKIP: never overwrite a local in this scope (`$template_path`,
        // `$template`, `$data`, `$this`). Today every caller passes a sane
        // associative array, but `extract($data)` with no guard would
        // silently clobber any local that happened to share a key name —
        // a fragile-by-default pattern. If a future maintainer wires
        // request data (`$_GET`/`$_POST`/REST params) into the `$data` array,
        // this flag turns "silent variable injection" into "ignored".
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        // Include the template
        include $template_path;
    }

    /**
     * Render a template and return the output
     *
     * @param string $template Template path relative to templates directory
     * @param array $data Data to pass to template
     * @return string Rendered template
     */
    public function renderToString(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);
        return ob_get_clean();
    }

    /**
     * Get template path
     *
     * @param string $template Template path relative to templates directory
     * @return string Full template path
     */
    public function getTemplatePath(string $template): string
    {
        return $this->plugin->getTemplatePath($template);
    }

    /**
     * Check if template exists
     *
     * @param string $template Template path relative to templates directory
     * @return bool True if template exists
     */
    public function templateExists(string $template): bool
    {
        $template_path = $this->plugin->getTemplatePath($template . '.php');
        return file_exists($template_path);
    }

    /**
     * Get all available templates
     *
     * @param string $directory Directory to search in
     * @return array Array of template files
     */
    public function getTemplates(string $directory = ''): array
    {
        $templates_dir = $this->plugin->getTemplatePath($directory);

        if (!is_dir($templates_dir)) {
            return [];
        }

        $templates = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templates_dir)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative_path = str_replace($templates_dir . '/', '', $file->getPathname());
                $relative_path = str_replace('.php', '', $relative_path);
                $templates[] = $relative_path;
            }
        }

        return $templates;
    }
}
