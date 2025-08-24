<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;

/**
 * Setting Controller Class
 *
 * @package Sikshya\Admin\Controllers
 * @since 1.0.0
 */
class SettingController extends BaseView
{
    /**
     * Settings Manager instance
     *
     * @var SettingsManager
     */
    protected SettingsManager $settingsManager;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);
        $this->settingsManager = new SettingsManager($plugin);
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        // AJAX handlers are now managed by AjaxManager
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        // Get current tab from URL or default to general
        $current_tab = sanitize_text_field($_GET['tab'] ?? 'general');
        
        // Validate tab exists
        $valid_tabs = array_keys($this->settingsManager->getAllSettings());
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'general';
        }
        
        // Render initial tab content
        $initial_content = $this->settingsManager->renderTabSettings($current_tab);
        
        // Pass data to template
        $this->data = [
            'current_tab' => $current_tab,
            'initial_content' => $initial_content
        ];
        
        $this->render('settings');
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-admin');
        wp_enqueue_script('sikshya-admin');
    }
}
