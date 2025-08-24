<?php
/**
 * AJAX Manager
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AjaxManager
{
    /**
     * Plugin instance
     * 
     * @var \Sikshya\Core\Plugin
     */
    private $plugin;
    
    /**
     * AJAX handlers
     * 
     * @var array
     */
    private $handlers = [];
    
    /**
     * Constructor
     * 
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        error_log('Sikshya: AjaxManager constructor called');
        $this->initHandlers();
        error_log('Sikshya: AjaxManager handlers initialized');
    }
    
    /**
     * Initialize AJAX handlers
     * 
     * @return void
     */
    private function initHandlers(): void
    {
        error_log('Sikshya: AjaxManager initHandlers called');
        
        // Course AJAX handler
        $this->handlers['course'] = new CourseAjax($this->plugin);
        error_log('Sikshya: CourseAjax handler created');
        
        // Settings AJAX handler
        $this->handlers['settings'] = new SettingsAjax($this->plugin);
        error_log('Sikshya: SettingsAjax handler created');
        
        // Allow other plugins to register additional handlers
        do_action('sikshya_register_ajax_handlers', $this->plugin, $this);
        
        error_log('Sikshya: AjaxManager initHandlers completed');
    }
    
    /**
     * Register a custom AJAX handler
     * 
     * @param string $name
     * @param AjaxAbstract $handler
     * @return void
     */
    public function registerHandler(string $name, AjaxAbstract $handler): void
    {
        $this->handlers[$name] = $handler;
    }
    
    /**
     * Get an AJAX handler
     * 
     * @param string $name
     * @return AjaxAbstract|null
     */
    public function getHandler(string $name): ?AjaxAbstract
    {
        return $this->handlers[$name] ?? null;
    }
    
    /**
     * Get all AJAX handlers
     * 
     * @return array
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
